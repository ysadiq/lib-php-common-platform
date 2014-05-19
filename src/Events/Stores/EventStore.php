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

use Doctrine\Common\Cache\Cache;
use DreamFactory\Platform\Components\PlatformStore;
use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\Interfaces\EventStoreLike;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\System;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A store for the platform event system
 */
class EventStore implements EventStoreLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const DEFAULT_NAMESPACE = 'dsp.event_store';
    /**
     * @type string
     */
    const STATS_CACHE_KEY = 'stats';
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
     * @var EventStoreLike
     */
    protected $_store = null;
    /**
     * @var EventDispatcher
     */
    protected $_dispatcher = null;
    /**
     * @var array Statistics for the cache
     */
    protected $_cacheStats = null;

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
        $this->_dispatcher = $dispatcher;

        $this->_initializeStore();
    }

    /**
     * Initialize the store
     */
    protected function _initializeStore()
    {
        $this->_store = new PlatformStore();
        /** @noinspection PhpUndefinedMethodInspection */
        $this->_store->setNamespace( statidc::CACHE_NAMESPACE );

        //  Get set up...
        $this->_store->set(
            array(
                'dispatcher.id' => $this->_storeId = hash(
                    'sha256',
                    Inflector::neutralize( get_class( $this->_dispatcher ) )
                ),
            )
        );

        $this->_initializeStatistics();
    }

    /**
     * Initialize the statistics for the cache
     */
    protected function _initializeStatistics()
    {
        $this->_cacheStats = array(
            Cache::STATS_HITS             => 0,
            Cache::STATS_MISSES           => 0,
            Cache::STATS_UPTIME           => microtime( true ),
            Cache::STATS_MEMORY_USAGE     => 0,
            Cache::STATS_MEMORY_AVAILABLE => 0,
        );

        $_mem = System::memory();

        if ( false !== ( $_cacheStats = $this->fetch( static::STATS_CACHE_KEY ) ) )
        {
            Option::set( $this->_cacheStats, Cache::STATS_HITS, Option::get( $_cacheStats, Cache::STATS_HITS, 0 ) );
            Option::set( $this->_cacheStats, Cache::STATS_MISSES, Option::get( $_cacheStats, Cache::STATS_MISSES, 0 ) );

            $this->_cacheStats[ Cache::STATS_UPTIME ] = $_mem[ Cache::STATS_UPTIME ];
            $this->_cacheStats[ Cache::STATS_MEMORY_USAGE ] = $_mem[ Cache::STATS_MEMORY_USAGE ];
            $this->_cacheStats[ Cache::STATS_MEMORY_AVAILABLE ] = $_mem[ Cache::STATS_MEMORY_AVAILABLE ];
            $this->_cacheStats['memory_pct_available'] = $_mem['memory_pct_free'];
            $this->_cacheStats['memory_total'] = $_mem['memory_total'];
            $this->_cacheStats['php_memory_limit'] = ini_get( 'memory_limit' );
        }

        $this->_store->save( 'event_store.stats', $this->_cacheStats );

        return $this->_cacheStats;
    }

    /**
     * @return bool|void
     */
    public function load()
    {
        if ( false === ( $_data = parent::fetch( $this->_storeId, array(), false ) ) || empty( $_data ) )
        {
            $this->_cacheStats[ Cache::STATS_MISSES ]++;
        }

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
                    $this->_cacheStats[ Cache::STATS_HITS ]++;
                }
            }
        }

        //  Scripts
        foreach ( ( $_scripts = Option::get( $_data, 'scripts', array() ) ) as $_eventName => $_scripts )
        {
            $this->_dispatcher->addScript( $_eventName, $_scripts, true );
            $this->_cacheStats[ Cache::STATS_HITS ] += is_string( $_scripts ) ? 1 : count( $_scripts );
        }

        //  Observers
        $this->_dispatcher->addObserver( $_observers = Option::get( $_data, 'observers', array() ), true );
        $this->_cacheStats[ Cache::STATS_HITS ] += is_string( $_observers ) ? 1 : count( $_observers );

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

    /**
     * Retrieves cached information from the data store.
     *
     * @since 2.2
     *
     * @return array|null An associative array with server's statistics if available, NULL otherwise.
     */
    function getStats()
    {
        return $this->_cacheStats;
    }
}
