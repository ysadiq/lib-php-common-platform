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

use DreamFactory\Platform\Enums\INotify;
use DreamFactory\Platform\Events\Enums\DspEvents;
use DreamFactory\Platform\Events\StorageChangeEvent;
use DreamFactory\Platform\Exceptions\UnavailableExtensionException;
use DreamFactory\Platform\Interfaces\WatcherLike;
use DreamFactory\Platform\Utility\Platform;

/**
 * Path/File watching object
 */
class PathWatcher implements WatcherLike
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var resource
     */
    protected $_stream;
    /**
     * @var array
     */
    protected $_watches = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Checks to see if this service is available
     *
     * @throws \DreamFactory\Platform\Exceptions\UnavailableExtensionException
     * @return bool
     */
    public static function available()
    {
        return function_exists( 'inotify_init' );
    }

    /**
     * @param resource $stream A stream resource to check
     *
     * @return bool True if the stream given is valid
     */
    public static function streamValid( $stream )
    {
        return $stream && is_resource( $stream ) && 'Unknown' != get_resource_type( $stream );
    }

    /**
     * @throws \DreamFactory\Platform\Exceptions\UnavailableExtensionException
     */
    public function __construct()
    {
        if ( !static::available( true ) )
        {
            throw new UnavailableExtensionException( 'The pecl "inotify" extension is required to use this class.' );
        }

        \register_shutdown_function(
            function ( $watcher )
            {
                /** @var PathWatcher $watcher */
                $watcher->_flush();
            },
            $this
        );

        /** @noinspection PhpUndefinedFunctionInspection */
        $this->_stream = inotify_init();
    }

    /**
     * Clean up stream and remove any watches
     */
    public function _flush()
    {
        if ( static::streamValid( $this->_stream ) && !empty( $this->_watches ) )
        {
            foreach ( $this->_watches as $_watchId )
            {
                /** @noinspection PhpUndefinedFunctionInspection */
                inotify_rm_watch( $this->_stream, $_watchId );
            }

            fclose( $this->_stream );
        }
    }

    /**
     * Watch a file/path
     *
     * @param string $path
     * @param int    $mask
     * @param bool   $overwrite If true, the mask will be added to any existing mask on the path. If false, the $mask
     *                          will replace an existing watch mask.
     *
     * @return int The ID of this watch
     */
    public function watch( $path, $mask = INotify::IN_ATTRIB, $overwrite = false )
    {
        if ( !$overwrite && isset( $this->_watches[$path] ) )
        {
            $mask |= INotify::IN_MASK_ADD;
        }

        /** @noinspection PhpUndefinedFunctionInspection */
        $_id = inotify_add_watch( $this->_stream, $path, $mask );

        $this->_watches[$path] = $_id;

        return $_id;
    }

    /**
     * Stop watching a file/path
     *
     * @param string $path The path to stop watching
     *
     * @return bool
     */
    public function unwatch( $path )
    {
        if ( !isset( $this->_watches[$path] ) )
        {
            return true;
        }

        /** @noinspection PhpUndefinedFunctionInspection */

        return inotify_rm_watch( $this->_stream, $this->_watches[$path] );
    }

    /**
     * Checks the stream and fires appropriate events
     *
     * @param bool $trigger If true, a DspEvents::STORAGE_CHANGE event is fired for each event
     *
     * @return array The array of triggered events
     */
    public function processEvents( $trigger = false )
    {
        $_result = array();

        //  Check for changes
        if ( 0 == $this->_processStream() )
        {
            return $_result;
        }

        //  Check for events
        /** @noinspection PhpUndefinedFunctionInspection */
        while ( ( $_length = inotify_queue_len( $this->_stream ) ) )
        {
            /** @noinspection PhpUndefinedFunctionInspection */
            if ( false !== ( $_events = inotify_read( $this->_stream ) ) )
            {
                //  Handle events
                foreach ( $_events as $_watchEvent )
                {
                    $_result[] = $_watchEvent;

                    if ( $trigger )
                    {
                        Platform::trigger(
                            DspEvents::STORAGE_CHANGE,
                            new StorageChangeEvent( $_watchEvent )
                        );
                    }
                }
            }
        }

        return $_result;
    }

    /**
     * @param int $timeout
     *
     * @return int
     */
    protected function _processStream( $timeout = 0 )
    {
        if ( !$this->streamValid( $this->_stream ) )
        {
            return array();
        }

        $_read = array( $this->_stream );
        $_except = $_write = array();

        return stream_select( $_read, $_write, $_except, $timeout );
    }

    public function getPathChecksum( $path )
    {
        if ( !is_dir( $path ) )
        {
            return false;
        }

        $_files = array();
        $_dir = dir( $path );

        while ( false !== ( $_entry = $_dir->read() ) )
        {
            if ( '.' != $_entry && '..' != $_entry )
            {
                $_filePath = $path . DIRECTORY_SEPARATOR . $_entry;
                $_files[] = is_dir( $_filePath ) ? $this->getPathChecksum( $_filePath ) : md5_file( $_filePath );
            }
        }

        $_dir->close();

        return md5( implode( '', $_files ) );
    }
}