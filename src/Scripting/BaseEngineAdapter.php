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
namespace DreamFactory\Platform\Scripting;

use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Interfaces\ScriptingEngineLike;
use DreamFactory\Platform\Utility\Platform;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Allows platform access to a scripting engine
 */
abstract class BaseEngineAdapter
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array A list of paths where we know our scripts live
     */
    protected static $_libraryPaths;
    /**
     * @var array The list of registered/known libraries
     */
    protected static $_libraries = array();
    /**
     * @var ScriptingEngineLike The engine
     */
    protected $_engine;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $options
     */
    public function __construct( array $options = array() )
    {
        //  Save off the engine
        $this->_engine = Option::get( $options, 'engine', $this->_engine );
    }

    /**
     * Called before script is executed so you can wrap the script and add injections
     *
     * @param string $script
     * @param array  $normalizedEvent
     *
     * @return string
     */
    abstract protected function _wrapScript( $script, array $normalizedEvent );

    /**
     * Handle setup for global/all instances of engine
     *
     * @param array $options
     *
     * @return mixed
     */
    public static function startup( $options = null )
    {
        static::_initializeLibraryPaths( Option::get( $options, 'library_paths', array() ) );
    }

    /**
     * Handle cleanup for global/all instances of engine
     *
     * @return mixed
     */
    public static function shutdown()
    {
        Platform::storeSet( 'scripting.library_paths', static::$_libraryPaths );
        Platform::storeSet( 'scripting.libraries', static::$_libraries );
    }

    /**
     * Look through the known paths for a particular script. Returns full path to script file.
     *
     * @param string $name           The name/id of the script
     * @param string $script         The name of the script
     * @param bool   $returnContents If true, the contents of the file, if found, are returned. Otherwise, the only the path is returned
     *
     * @return string
     */
    public static function loadScript( $name, $script, $returnContents = true )
    {
        //  Already read, return script
        if ( null !== ( $_script = Option::get( static::$_libraries, $name ) ) )
        {
            return $returnContents ? file_get_contents( $_script ) : $_script;
        }

        $_script = ltrim( $script, ' /' );

        //  Spin through paths and look for the script
        foreach ( static::$_libraryPaths as $_path )
        {
            $_check = $_path . '/' . $_script;

            if ( is_file( $_check ) && is_readable( $_check ) )
            {
                Option::set( static::$_libraries, $name, $_check );

                return $returnContents ? file_get_contents( $_check ) : $_check;
            }
        }

        return false;
    }

    /**
     * @param array $libraryPaths
     *
     * @throws \DreamFactory\Platform\Exceptions\RestException
     *
     */
    protected static function _initializeLibraryPaths( $libraryPaths = null )
    {
        static::$_libraryPaths = Platform::storeGet( 'scripting.library_paths', array() );
        static::$_libraries = Platform::storeGet( 'scripting.libraries', array() );

        //  Set up
        $_platformConfigPath = Platform::getPlatformConfigPath();

        //  Get our library's script path
        $_libraryPath = Platform::getLibraryConfigPath( '/scripts' );

        if ( empty( $_libraryPath ) || !is_dir( $_libraryPath ) || !is_readable( $_libraryPath ) )
        {
            throw new RestException(
                HttpResponse::ServiceUnavailable, 'This service is not available . Storage path and/or required libraries not available . '
            );
        }

        //  Add ones from constructor
        if ( is_array( $libraryPaths ) )
        {
            foreach ( $libraryPaths as $_path )
            {
                if ( !in_array( $_path, static::$_libraryPaths ) )
                {
                    static::$_libraryPaths[] = $_path;
                }
            }
        }

        //  All the paths that we will check for scripts
        static::$_libraryPaths = array(
            //  This is ONLY the root of the app store
            'app'      => Platform::getApplicationsPath(),
            //  This is the user's private scripting area used by the admin console
            'storage'  => Platform::getPrivatePath( '/scripts' ),
            //  Scripts here override library scripts
            'platform' => $_platformConfigPath . '/scripts',
            //  Now check library distribution
            'library'  => $_libraryPath,
        );

        Platform::storeSet( 'scripting.library_paths', static::$_libraryPaths );
    }

    /**
     * @return array
     */
    public static function getLibraries()
    {
        return static::$_libraries;
    }

    /**
     * @return array
     */
    public static function getLibraryPaths()
    {
        return static::$_libraryPaths;
    }

    /**
     * @param string $libraryPath An absolute path to a script library
     */
    public static function addLibraryPath( $libraryPath )
    {
        if ( !is_dir( $libraryPath ) || !is_readable( $libraryPath ) )
        {
            throw new \InvalidArgumentException( 'The path "' . $libraryPath . '" is invalid.' );
        }

        if ( !in_array( $libraryPath, static::$_libraryPaths ) )
        {
            static::$_libraryPaths[] = $libraryPath;
        }
    }

    /**
     * @param string $name   The name/id of this script
     * @param string $script The file for this script
     */
    public static function addLibrary( $name, $script )
    {
        if ( false === ( $_path = static::loadScript( $name, $script, false ) ) )
        {
            throw new \InvalidArgumentException( 'The script "' . $script . '" was not found.' );
        }
    }
}