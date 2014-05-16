<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Events\Stores;

use DreamFactory\Platform\Components\PlatformStore;
use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\EventStoreLike;
use Kisma\Core\Enums\CacheTypes;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A store for the platform event system
 */
class EventStore extends PlatformStore implements EventStoreLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const CACHE_NAMESPACE = 'dsp.event_store';
    /**
     * @type string
     */
    const CACHE_PATH = PlatformStore::STORE_CACHE_PATH;
    /**
     * @type string
     */
    const CACHE_EXTENSION = '.dfec';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string
     */
    protected $_storeId = null;
    /**
     * @var EventDispatcher
     */
    protected $_dispatcher = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Constructor.
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct( EventDispatcherInterface $dispatcher )
    {
        parent::__construct( CacheTypes::FILE_SYSTEM );
        $this->setNamespace( static::CACHE_NAMESPACE );

        //  Get set up...
        $this->_storeId = hash( 'sha256', Inflector::neutralize( get_class( $dispatcher ) ) );
        $this->_dispatcher = $dispatcher;
    }

    /**
     * @return bool|void
     */
    public function load()
    {
        $_data = parent::fetch( $this->_storeId, array(), false );

        if ( $this->_dispatcher->getLogAllEvents() )
        {
            Log::debug( 'EventStore: Loading dispatcher state' );
        }

        //  Listeners
        foreach ( Option::get( $_data, 'listeners', array() ) as $_eventName => $_callables )
        {
            if ( empty( $_callables ) )
            {
                continue;
            }

            foreach ( $_callables as $_priority => $_listeners )
            {
                foreach ( $_listeners as $_listener )
                {
                    $this->_dispatcher->addListener( $_eventName, $_listener, $_priority, true );
                }
            }
        }

        //  Scripts
        foreach ( Option::get( $_data, 'scripts', array() ) as $_eventName => $_scripts )
        {
            $this->_dispatcher->addScript( $_eventName, $_scripts, true );
        }

        //  Observers
        $this->_dispatcher->addObserver( Option::get( $_data, 'observers', array() ), true );

        return true;
    }

    /**
     * @return bool|void
     */
    public function save()
    {
        if ( $this->_dispatcher->getLogAllEvents() )
        {
            Log::debug( 'EventStore: Saving dispatcher state', array( 'name' => 'EventStore' ) );
        }

        $_data = array(
            'listeners' => $this->_dispatcher->getAllListeners(),
            'observers' => $this->_dispatcher->getObservers(),
            'scripts'   => $this->_dispatcher->getScripts(),
        );

        parent::save( $this->_storeId, $_data );
    }

    /**
     * Flush the cache
     *
     * @return bool
     */
    public function flushAll()
    {
        //  drop a null in for 1 second
        return parent::save( $this->_storeId, null, 1 );
    }

    /**
     * Hashes the key for this store
     *
     * @param string $id
     *
     * @return string
     */
    protected function _obscureKey( $id )
    {
        return hash( 'sha256', parent::_obscureKey( $id ) );
    }
}
