<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
namespace DreamFactory\Platform\Utility;

use Doctrine\Common\Cache\CacheProvider;
use DreamFactory\Platform\Components\PlatformStore;
use DreamFactory\Platform\Enums\LocalStorageTypes;
use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\Interfaces\EventObserverLike;
use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\CacheTypes;
use Kisma\Core\Exceptions\FileSystemException;
use Kisma\Core\Interfaces\StoreLike;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Platform
 * System constants and generic platform helpers
 */
class Platform
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string The name of the storage container that stores applications
     */
    const APP_STORAGE_CONTAINER = 'applications';
    /**
     * @type int Default memcache data expire time (24 mins)...
     */
    const MEMCACHE_TTL = 1440;
    /**
     * @type string The memcache host
     */
    const MEMCACHE_HOST = 'localhost';
    /**
     * @type int The memcache port
     */
    const MEMCACHE_PORT = 11211;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var CacheProvider The persistent store to use for local storage
     */
    protected static $_persistentStore;
    /**
     * @var \Memcached A memcached persistent store
     */
    protected static $_memcache;
    /**
     * @var array
     */
    protected static $_paths;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Constructs a virtual platform path
     *
     * @param string $type            The type of path, used as a key into config
     * @param string $append
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     *
     * @param bool   $includesFile
     *
     * @throws \InvalidArgumentException
     * @throws \Kisma\Core\Exceptions\FileSystemException
     * @return string
     */
    protected static function _getPlatformPath( $type, $append = null, $createIfMissing = true, $includesFile = false )
    {
        static $_cache = array();

        $_appendage = ( $append ? '/' . ltrim( $append, '/' ) : null );

        if ( !LocalStorageTypes::contains( $_tag = Inflector::neutralize( $type ) ) )
        {
            throw new \InvalidArgumentException( 'Type "' . $type . '" is invalid.' );
        }

        //	Make a cache tag that includes the requested path...
        $_cacheTag = $_tag . '/' . Inflector::neutralize( $_appendage );

        if ( null === ( $_path = Option::get( $_cache, $_cacheTag ) ) )
        {
            $_path = trim( Pii::getParam( $_tag ) );

            if ( empty( $_path ) )
            {
                $_path = \Kisma::get( 'app.project_root' ) . '/storage';
                Log::notice( 'Empty path for platform path type "' . $type . '". Defaulting to "' . $_path . '"' );
            }

            $_checkPath = $_path . $_appendage;

            if ( $includesFile )
            {
                $_checkPath = dirname( $_checkPath );
            }

            if ( true === $createIfMissing && !is_dir( $_checkPath ) )
            {
                if ( false === @\mkdir( $_checkPath, 0777, true ) )
                {
                    throw new FileSystemException( 'File system error creating directory: ' . $_checkPath );
                }
            }

            $_path .= $_appendage;

            //	Store path for next time...
            Option::set( $_cache, $_cacheTag, $_path );
        }

        return $_path;
    }

    /**
     * Constructs the virtual storage path
     *
     * @param string $append
     * @param bool   $createIfMissing
     * @param bool   $includesFile
     *
     * @return string
     */
    public static function getStorageBasePath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return static::_getPlatformPath(
            LocalStorageTypes::STORAGE_BASE_PATH,
            $append,
            $createIfMissing,
            $includesFile
        );
    }

    /**
     * Constructs the virtual storage path
     *
     * @param string $append
     * @param bool   $createIfMissing
     * @param bool   $includesFile
     *
     * @return string
     */
    public static function getStoragePath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return static::_getPlatformPath( LocalStorageTypes::STORAGE_PATH, $append, $createIfMissing, $includesFile );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     * @param bool   $createIfMissing
     * @param bool   $includesFile
     *
     * @return string
     */
    public static function getPrivatePath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return static::_getPlatformPath( LocalStorageTypes::PRIVATE_PATH, $append, $createIfMissing, $includesFile );
    }

    /**
     * Returns the library configuration path, not the platform's config path in the root
     *
     * @param string $append
     *
     * @return string
     */
    public static function getLibraryConfigPath( $append = null )
    {
        return SystemManager::getConfigPath() . ( $append ? '/' . ltrim( $append, '/' ) : null );
    }

    /**
     * Returns the library template configuration path, not the platform's config path in the root
     *
     * @param string $append
     *
     * @return string
     */
    public static function getLibraryTemplatePath( $append = null )
    {
        return static::getLibraryConfigPath( '/templates' ) . ( $append ? '/' . ltrim( $append, '/' ) : null );
    }

    /**
     * Returns the platform configuration path, in the root
     *
     * @param string $append
     *
     * @return string
     */
    public static function getPlatformConfigPath( $append = null )
    {
        return Pii::getPathOfAlias( 'application.config' ) . ( $append ? '/' . ltrim( $append, '/' ) : null );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     * @param bool   $createIfMissing
     * @param bool   $includesFile
     *
     * @return string
     */
    public static function getSnapshotPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return static::_getPlatformPath( LocalStorageTypes::SNAPSHOT_PATH, $append, $createIfMissing, $includesFile );
    }

    /**
     * Constructs the virtual swagger path
     *
     * @param string $append
     * @param bool   $createIfMissing
     * @param bool   $includesFile
     *
     * @return string
     */
    public static function getSwaggerPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return static::_getPlatformPath( LocalStorageTypes::SWAGGER_PATH, $append, $createIfMissing, $includesFile );
    }

    /**
     * Constructs the virtual plugins path
     *
     * @param string $append
     * @param bool   $createIfMissing
     * @param bool   $includesFile
     *
     * @return string
     */
    public static function getPluginsPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return static::_getPlatformPath( LocalStorageTypes::PLUGINS_PATH, $append, $createIfMissing, $includesFile );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     * @param bool   $createIfMissing
     * @param bool   $includesFile
     *
     * @return string
     */
    public static function getApplicationsPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return static::_getPlatformPath(
            LocalStorageTypes::APPLICATIONS_PATH,
            $append,
            $createIfMissing,
            $includesFile
        );
    }

    /**
     * @param string $namespace
     *
     * @return string
     */
    public static function uuid( $namespace = null )
    {
        static $_uuid = null;

        $_hash = strtoupper(
            hash(
                'ripemd128',
                uniqid( '', true ) . ( $_uuid ?: microtime( true ) ) . md5(
                    $namespace .
                    $_SERVER['REQUEST_TIME'] .
                    $_SERVER['HTTP_USER_AGENT'] .
                    $_SERVER['LOCAL_ADDR'] .
                    $_SERVER['LOCAL_PORT'] .
                    $_SERVER['REMOTE_ADDR'] .
                    $_SERVER['REMOTE_PORT']
                )
            )
        );

        $_uuid =
            '{' .
            substr( $_hash, 0, 8 ) .
            '-' .
            substr( $_hash, 8, 4 ) .
            '-' .
            substr( $_hash, 12, 4 ) .
            '-' .
            substr( $_hash, 16, 4 ) .
            '-' .
            substr( $_hash, 20, 12 ) .
            '}';

        return $_uuid;
    }

    /**
     * Attempts to require one or more autoload files.
     * fUseful for DSP apps written in PHP.
     *
     * @param array $autoloaders
     *
     * @return mixed|bool
     */
    public static function registerAutoloaders( $autoloaders = array() )
    {
        foreach ( Option::clean( $autoloaders ) as $_file )
        {
            if ( file_exists( $_file ) )
            {
                /** @noinspection PhpIncludeInspection */
                return require_once $_file;
            }
        }

        return false;
    }

    //*************************************************************************
    //	Persistent Storage
    //*************************************************************************

    /**
     * Retrieves the store instance for the platform. If it has not yet been created,
     * a new instance is created and seeded with $data
     *
     * @param array $data An array of key value pairs with which to seed the store
     *
     * @return StoreLike|CacheProvider
     */
    public static function getStore( array $data = array() )
    {
        if ( null === static::$_persistentStore )
        {
            static::$_persistentStore = new PlatformStore( CacheTypes::FILE_SYSTEM, $data );
        }

        return static::$_persistentStore;
    }

    /**
     * @param string $key
     * @param mixed  $defaultValue
     * @param bool   $remove
     * @param int    $ttl The TTL for non-removed defaults
     *
     * @return mixed
     */
    public static function storeGet( $key, $defaultValue = null, $remove = false, $ttl = PlatformStore::DEFAULT_TTL )
    {
        return static::getStore()->get( $key, $defaultValue, $remove, $ttl );
    }

    /**
     * Sets a value in the platform cache
     * $key can be specified as an array of key-value pairs: array( 'alpha' => 'xyz', 'beta' => 'qrs', 'gamma' => 'lmo', ... )
     *
     * @param string|array $key  The cache id or array of key-value pairs
     * @param mixed        $data The cache entry/data.
     * @param int          $ttl  The cache lifetime. Sets a specific lifetime for this cache entry. Defaults to 0, or "never expire"
     *
     * @return boolean|boolean[] TRUE if the entry was successfully stored in the cache, FALSE otherwise.
     */
    public static function storeSet( $key, $data, $ttl = PlatformStore::DEFAULT_TTL )
    {
        return static::getStore()->set( $key, $data, $ttl );
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public static function storeContains( $id )
    {
        return static::getStore()->contains( $id );
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public static function storeDelete( $id )
    {
        return static::getStore()->delete( $id );
    }

    /**
     * @return bool
     */
    public static function storeDeleteAll()
    {
        return static::getStore()->deleteAll();
    }

    //*************************************************************************
    //	Event convenience methods
    //*************************************************************************

    /**
     * Triggers an event
     *
     * @param string        $eventName
     * @param PlatformEvent $event
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \Exception
     * @return PlatformEvent
     */
    public static function trigger( $eventName, $event = null )
    {
        return static::getDispatcher()->dispatch( $eventName, $event );
    }

    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string   $eventName            The event to listen on
     * @param callable $listener             The listener
     * @param integer  $priority             The higher this value, the earlier an event
     *                                       listener will be triggered in the chain (defaults to 0)
     *
     * @return void
     */
    public static function on( $eventName, $listener, $priority = 0 )
    {
        static::getDispatcher()->addListener( $eventName, $listener, $priority );
    }

    /**
     * Add a subscriber to the dispatcher
     *
     * @param EventSubscriberInterface $subscriber
     *
     * @return void
     */
    public static function subscribe( EventSubscriberInterface $subscriber )
    {
        static::getDispatcher()->addSubscriber( $subscriber );
    }

    /**
     * Remove an event subscriber from the dispatcher
     *
     * @param EventSubscriberInterface $subscriber
     *
     * @return void
     */
    public static function unsubscribe( EventSubscriberInterface $subscriber )
    {
        static::getDispatcher()->removeSubscriber( $subscriber );
    }

    /**
     * @param EventObserverLike|EventObserverLike[] $observer
     * @param bool                                  $fromCache True if the cache is adding this handler Ignored by default
     *
     * @return bool False if no observers added, otherwise how many were added
     */
    public static function addObserver( $observer, $fromCache = false )
    {
        return static::getDispatcher()->addObserver( $observer, $fromCache );
    }

    /**
     * @param EventObserverLike $observer
     *
     * @return void
     */
    public static function removeObserver( $observer )
    {
        static::getDispatcher()->removeObserver( $observer );
    }

    /**
     * Turn off/unbind/remove $listener from an event
     *
     * @param string   $eventName
     * @param callable $listener
     *
     * @return void
     */
    public static function off( $eventName, $listener )
    {
        static::getDispatcher()->removeListener( $eventName, $listener );
    }

    /**
     * @return EventDispatcher
     */
    public static function getDispatcher()
    {
        static $_dispatcher;

        //  This is the only place in the library where we call Pii to get the dispatcher.
        //  In v2, the source of the dispatcher location will be different, most likely a service

        return $_dispatcher ?: $_dispatcher = Pii::app()->getDispatcher();
    }

    /**
     * @param string $key
     * @param mixed  $defaultValue
     * @param bool   $remove
     *
     * @return array|null|string
     */
    public static function mcGet( $key, $defaultValue = null, $remove = false )
    {
        $_singleton = false;
        $_cache = static::_getMemcache();

        if ( !is_array( $key ) )
        {
            $key = array( $key );
            $_singleton = true;
        }

        $_result = array();

        foreach ( $key as $_key )
        {
            if ( $_cache )
            {
                if ( false === ( $_value = $_cache->get( $_key ) ) )
                {
                    static::mcSet( $_key, $defaultValue );

                    $_value = $defaultValue;
                }

                if ( $remove )
                {
                    $_cache->delete( $_key );
                }

                $_result[ $_key ] = $_value;

                continue;
            }

            //  Degrade to platform store
            $_result[ $_key ] = static::storeGet( $_key, $defaultValue, $remove );
        }

        return $_singleton ? current( $_result ) : $_result;
    }

    /**
     * @param string|array $key
     * @param mixed        $value
     * @param int          $flag
     * @param int          $ttl
     *
     * @return bool|array
     */
    public static function mcSet( $key, $value = null, $flag = 0, $ttl = self::MEMCACHE_TTL )
    {
        $_singleton = false;

        if ( !is_array( $key ) )
        {
            $key = array( $key => $value );
            $value = null;
            $_singleton = true;
        }

        $_cache = static::_getMemcache();

        $_result = array();

        foreach ( $key as $_key => $_value )
        {
            $_result[ $_key ] =
                ( $_cache ? $_cache->set( $_key, $_value, $ttl ) : static::storeSet( $_key, $_value, $ttl ) );
        }

        return $_singleton ? current( $_result ) : $_result;
    }

    /**
     * @return \Memcached|boolean
     */
    protected static function _getMemcache()
    {
        if ( !class_exists( '\\Memcached', false ) )
        {
            return false;
        }

        if ( null === static::$_memcache )
        {
            static::$_memcache = new \Memcached( __CLASS__ );

            if ( !static::$_memcache->addServer( static::MEMCACHE_HOST, static::MEMCACHE_PORT ) )
            {
//                Log::debug( 'Platform memcache not available.' );
                return static::$_memcache = false;
            }

//            Log::debug( 'Platform memcache enabled.' );
        }

        return static::$_memcache;
    }
}
