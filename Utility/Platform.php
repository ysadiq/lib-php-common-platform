<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Utility;

use DreamFactory\Platform\Enums\LocalStorageTypes;
use DreamFactory\Platform\Events\BasePlatformEvent;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Platform
 * Generic platform helpers
 */
class Platform
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
	 * @var EventDispatcherInterface
     */
    protected static $_eventManager = null;

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
     * @throws \InvalidArgumentException
     * @return string
     */
    protected static function _getPlatformPath( $type, $append = null, $createIfMissing = true )
    {
        static $_cache = array();

        if ( !LocalStorageTypes::contains( $_tag = Inflector::neutralize( $type ) ) )
        {
            throw new \InvalidArgumentException( 'Type "' . $type . '" is invalid.' );
        }

        //	Make a cache tag that includes the requested path...
        $_cacheTag = Inflector::neutralize( $type . ( $append ? '/' . $append : null ) );

        if ( null === ( $_path = Option::get( $_cache, $_cacheTag ) ) )
        {
            $_path = trim( Pii::getParam( $_tag ) );

            if ( empty( $_path ) )
            {
                $_path = \Kisma::get( 'app.project_root' ) . '/storage';
                Log::notice( 'Empty path for platform path type "' . $type . '". Defaulting to "' . $_path . '"' );
            }

            if ( !is_dir( $_path ) && true === $createIfMissing )
            {
                if ( false === @\mkdir( $_path, 0777, true ) )
                {
                    Log::error( 'File system error creating directory: ' . $_path );
                }
            }

            //	Store path for next time...
            Option::set( $_cache, $_cacheTag, $_path );
        }

        return $_path . ( $append ? '/' . ltrim( $append, '/' ) : null );
    }

    /**
     * Constructs the virtual storage path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getStoragePath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::STORAGE_PATH, $append );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getPrivatePath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::PRIVATE_PATH, $append );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getSnapshotPath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::SNAPSHOT_PATH, $append );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getLibraryPath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::LIBRARY_PATH, $append );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getApplicationsPath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::APPLICATIONS_PATH, $append );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append
     *
     * @return string
     */
    public static function getPluginsPath( $append = null )
    {
        return static::_getPlatformPath( LocalStorageTypes::PLUGINS_PATH, $append );
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
                uniqid( '', true ) . ( $_uuid ? : microtime( true ) ) . md5(
                    $namespace . $_SERVER['REQUEST_TIME'] . $_SERVER['HTTP_USER_AGENT'] . $_SERVER['LOCAL_ADDR'] . $_SERVER['LOCAL_PORT'] . $_SERVER['REMOTE_ADDR'] . $_SERVER['REMOTE_PORT']
                )
            )
        );

        $_uuid = '{' . substr( $_hash, 0, 8 ) . '-' . substr( $_hash, 8, 4 ) . '-' . substr( $_hash, 12, 4 ) . '-' . substr( $_hash, 16, 4 ) . '-' . substr(
                $_hash,
                20,
                12
            ) . '}';

        return $_uuid;
    }

    /**
     * Add a listener to an event
     *
     * @param string   $eventName
     * @param callable $callback
     */
    public static function on( $eventName, $callback )
    {
        static::_getEventManager()->addListener( $eventName, $callback );
    }

    /**
     * Remove a listener from an event
     *
     * @param string   $eventName
     * @param callable $callback
     */
    public static function off( $eventName, $callback )
    {
        static::_getEventManager()->removeListener( $eventName, $callback );
    }

    /**
     * @param string                                          $eventName
     * @param \DreamFactory\Platform\Events\BasePlatformEvent $event
     *
     * @return \Symfony\Component\EventDispatcher\Event
     */
    public static function trigger( $eventName, BasePlatformEvent $event = null )
    {
        return static::_getEventManager()->dispatch( $eventName, $event );
    }

    /**
     * @return EventDispatcher
     */
    protected static function _getEventManager()
    {
        if ( null === static::$_eventManager )
        {
            static::$_eventManager = new EventDispatcher();
        }

        return static::$_eventManager;
    }
}
