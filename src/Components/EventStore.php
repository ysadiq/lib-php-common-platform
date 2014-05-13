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
namespace DreamFactory\Platform\Components;

use DreamFactory\Platform\Events\EventDispatcher;
use Kisma\Core\Enums\CacheTypes;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A store for the platform event system
 */
class EventStore extends PlatformStore
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

        //  Freshen up
        $this->_storeId = hash( 'sha256', Inflector::neutralize( get_class( $dispatcher ) ) );

        if ( false !== ( $_dispatcher = $this->fetch( $this->_storeId ) ) )
        {
            Log::debug( 'Event store id #' . $this->_storeId . ' retrieved from cache.' );
        }

        //  Set our version...
        $this->_dispatcher = $_dispatcher ? : $dispatcher;
    }

    /**
     * Kill!
     */
    public function __destruct()
    {
        //  Save the dispatcher state
        $this->setCachedData( $this->_dispatcher );
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

    /**
     * @param EventDispatcher $dispatcher
     *
     * @return $this
     */
    public function setCachedData( $dispatcher )
    {
        //  Save the dispatcher state
        $this->set( $this->_storeId, $this->_dispatcher = $dispatcher, static::DEFAULT_TTL );

        return $this;
    }

    /**
     * @return EventDispatcher
     */
    public function getCachedData()
    {
        return $this->_dispatcher;
    }
}
