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

use DreamFactory\Library\Enterprise\Storage\Enums\EnterpriseDefaults;
use DreamFactory\Library\Utility\AppInstance;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Exceptions\FileSystemException;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Platform\Enums\FabricPlatformStates;
use DreamFactory\Platform\Enums\LocalStorageTypes;
use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\Interfaces\EventObserverLike;
use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Log;
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
    /**
     * @type int The default cache ttl, 5m = 300s
     */
    const DEFAULT_CACHE_TTL = 300;
    /**
     * @type string The default date() format (YYYY-MM-DD HH:MM:SS)
     */
    const DEFAULT_TIMESTAMP_FORMAT = 'Y-m-d H:i:s';
    /**
     * @var string
     */
    const FABRIC_API_ENDPOINT = EnterpriseDefaults::DFE_ENDPOINT;
    /**
     * @type string The current version of the platform core
     */
    const PLATFORM_CORE_VERSION = DSP_VERSION;
    /**
     * @type string The current version of the platform API
     */
    const PLATFORM_API_VERSION = API_VERSION;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return string The current platform core version
     */
    public static function getPlatformCoreVersion()
    {
        return static::PLATFORM_CORE_VERSION;
    }

    /**
     * @return string The current platform API version
     */
    public static function getPlatformApiVersion()
    {
        return static::PLATFORM_API_VERSION;
    }

    /**
     * Constructs a virtual platform path
     *
     * @param string $type            The type of path, used as a key into config
     * @param string $append
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     *
     * @param bool   $includesFile
     *
     * @throws FileSystemException
     * @return string
     */
    protected static function _getPlatformPath( $type, $append = null, $createIfMissing = true, $includesFile = false )
    {
        static $_cache = array(), $_app = null;

        $_app && $_app = AppInstance::getInstance();
        $_appendage = ( $append ? '/' . ltrim( $append, '/' ) : null );

        if ( !LocalStorageTypes::contains( $type ) )
        {
            throw new \InvalidArgumentException( 'Type "' . $type . '" is invalid.' );
        }

        //	Make a cache tag that includes the requested path...
        $_cacheKey = $type . $_appendage;

        if ( null === ( $_path = IfSet::get( $_cache, $_cacheKey ) ) )
        {
            $_path = $_app->getParameter( $type );

            if ( empty( $_path ) )
            {
                $_path = $_app->getParameter( 'app.base_path' ) . '/storage';
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
            $_cache[$_cacheKey] = $_path;
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
        return static::getStoragePath( $append, $createIfMissing, $includesFile );
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
     * Returns the platform's local configuration path, not the platform's config path in the root
     *
     * @param string $append
     * @param bool   $createIfMissing
     * @param bool   $includesFile
     *
     * @throws FileSystemException
     * @return string
     */
    public static function getLocalConfigPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return static::getPrivateConfigPath( $append, $createIfMissing, $includesFile );
    }

    /**
     * Returns the platform's private configuration path, not the platform's config path in the root
     *
     * @param string $append
     * @param bool   $createIfMissing
     * @param bool   $includesFile
     *
     * @throws FileSystemException
     * @return string
     */
    public static function getPrivateConfigPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return static::_getPlatformPath( LocalStorageTypes::PRIVATE_CONFIG_PATH, $append, $createIfMissing, $includesFile );
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
        $autoloaders = is_array( $autoloaders ) ? $autoloaders : array($autoloaders);

        foreach ( $autoloaders as $_file )
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
    //	Persistent Storage (Disk and Memcache)
    //*************************************************************************

    /**
     * @param string $addendum Additional data to add to key
     *
     * @return string A string that uniquely identifies the owner
     */
    public static function getCacheKey( $addendum = null )
    {
        return PHP_SAPI . '.' . ( isset( $_SERVER, $_SERVER['REMOTE_ADDR'] )
            ? $_SERVER['REMOTE_ADDR']
            : gethostname() . '.' . ( isset( $_SERVER, $_SERVER['HTTP_HOST'] )
                ? $_SERVER['HTTP_HOST']
                : gethostname() . ( $addendum
                    ? '.' . $addendum
                    : null
                )
            )
        );
    }

    /**
     * @param string $key
     * @param mixed  $defaultValue
     * @param bool   $remove
     *
     * @return mixed
     */
    public static function storeGet( $key, $defaultValue = null, $remove = false )
    {
        return static::_doGet( $key, $defaultValue, $remove );
    }

    /**
     * Sets a value in the platform cache
     * $key can be specified as an array of key-value pairs: array( 'alpha' => 'xyz', 'beta' => 'qrs', 'gamma' =>
     * 'lmo', ... )
     *
     * @param string|array $key  The cache id or array of key-value pairs
     * @param mixed        $data The cache entry/data.
     * @param int          $ttl  The cache lifetime. Sets a specific lifetime for this cache entry. Defaults to 0, or
     *                           "never expire"
     *
     * @return boolean|boolean[] TRUE if the entry was successfully stored in the cache, FALSE otherwise.
     */
    public static function storeSet( $key, $data, $ttl = self::DEFAULT_CACHE_TTL )
    {
        return static::_doSet( $key, $data, $ttl );
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public static function storeContains( $key )
    {
        return static::_doContains( $key );
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public static function storeDelete( $key )
    {
        return static::_doDelete( $key );
    }

    /**
     * @return bool
     */
    public static function storeDeleteAll()
    {
        return static::_doDeleteAll();
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl
     * @param int    $flag
     *
     * @return bool|bool[]
     */
    protected static function _doSet( $key, $value = null, $ttl = self::MEMCACHE_TTL, $flag = 0 )
    {
        return Pii::appStoreSet( $key, $value, $ttl, $flag );
    }

    /**
     * @param string $key
     * @param mixed  $defaultValue
     * @param bool   $remove
     *
     * @return bool|bool[]
     */
    protected static function _doGet( $key, $defaultValue = null, $remove = false )
    {
        return Pii::appStoreGet( $key, $defaultValue, $remove );
    }

    /**
     * @param string $key
     * @param bool   $returnValue
     *
     * @return bool|mixed
     */
    protected static function _doContains( $key, $returnValue = false )
    {
        return Pii::appStoreContains( $key );
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    protected static function _doDelete( $key )
    {
        return Pii::appStoreDelete( $key );
    }

    /**
     * @return bool
     */
    protected static function _doDeleteAll()
    {
        return Pii::appStoreDeleteAll();
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
     * @param bool                                  $fromCache True if the cache is adding this handler Ignored by
     *                                                         default
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
     * Generates a timestamp in a consistent format.
     * Value is set in config/common.config.php and stored in the "platform.timestamp_format" key
     *
     * @param string $format Valid date() format to override configured or default
     *
     * @return bool|string
     */
    public static function getSystemTimestamp( $format = null )
    {
        $_format = $format ?: Pii::getParam( 'platform.timestamp_format', static::DEFAULT_TIMESTAMP_FORMAT );

        return date( $_format );
    }

    /**
     * Retrieve platform states from the mothership
     *
     * @param string $dspName
     *
     * @return \stdClass|bool
     */
    public static function getPlatformStates( $dspName = null )
    {
        //  We do nothing on private installs
        if ( !Pii::getParam( 'dsp.fabric_hosted', false ) )
        {
            return array('provision_state' => 0, 'operation_state' => FabricPlatformStates::UNKNOWN, 'ready_state' => 0);
        }

        $dspName = $dspName ?: Pii::getParam( 'dsp_name' );

        if ( empty( $dspName ) )
        {
            $dspName = gethostname();
        }

        if ( false === ( $_response = Fabric::api( Verbs::GET, '/state/' . $dspName ) ) )
        {
            return false;
        }

        //Log::debug( 'Retrieved platform states: ' . print_r( $_response, true ) );

        return array(
            'provision_state' => $_response->details->state,
            'operation_state' => $_response->details->platform_state,
            'ready_state'     => $_response->details->ready_state,
        );
    }

    /**
     * @param string $stateName The state to change
     * @param int    $state     The state value
     *
     * @return bool|mixed|\stdClass
     */
    public static function setPlatformState( $stateName, $state )
    {
        static $_debug = false;

        try
        {
            //  We do nothing on private installs
            if ( !Pii::getParam( 'dsp.fabric_hosted', false ) )
            {
                $_debug &&
                Log::info( 'setPlatformState( "' . $stateName . '", ' . $state . ' ): ignoring. not fabric-hosted' );

                return true;
            }

            $stateName = trim( strtolower( $stateName ) );

            if ( 'ready' != $stateName && 'platform' != $stateName )
            {
                $_debug && Log::error(
                    'setPlatformState( "' . $stateName . '", ' . $state . ' ): invalid state name"' . $stateName . '"'
                );

                throw new \InvalidArgumentException( 'The state name "' . $stateName . '" is invalid.' );
            }

            //  Don't make unnecessary calls
            if ( \Kisma::get( 'platform.' . $stateName ) == $state )
            {
                $_debug &&
                Log::info( 'setPlatformState( "' . $stateName . '", ' . $state . ' ): no change from current state' );

                return true;
            }

            try
            {
                //  Called before DSP name is set
                if ( null === ( $_instanceId = \Kisma::get( 'platform.dsp_name' ) ) )
                {
                    $_debug &&
                    Log::notice( 'setPlatformState( "' . $stateName . '", ' . $state . ' ): empty DSP name' );

                    return false;
                }

                $_result = Fabric::api(
                    Verbs::POST,
                    '/state/' . $_instanceId,
                    array(
                        'instance_id' => $_instanceId,
                        'state_name'  => $stateName,
                        'state'       => $state
                    )
                );

                if ( !$_result->success )
                {
                    $_debug && Log::notice(
                        'setPlatformState( "' .
                        $stateName .
                        '", ' .
                        $state .
                        ' ): error saving state: ' .
                        print_r( $_result, true )
                    );

                    throw new \Exception( 'Could not change state to "' . $state . '":' . $_result->error->message );
                }

                \Kisma::set( 'platform.' . $stateName, $_result->details->state );

                return true;
            }
            catch ( \Exception $_ex )
            {
                Log::error( $_ex->getMessage() );

                return false;
            }
        }
        catch ( \Exception $_ex )
        {
            Log::error( $_ex->getMessage() );

            return false;
        }
    }
}
