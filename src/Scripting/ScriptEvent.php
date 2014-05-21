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

use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\DateTime;
use Kisma\Core\Seed;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Acts as a proxy between a DSP PHP $event and a server-side script
 */
class ScriptEvent extends Seed
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string One path to rule them all
     */
    protected static $_libraryScriptPath;
    /**
     * @var array The modules we current support in our library script path
     */
    protected static $_libraryModules = array(
        'lodash'     => 'lodash.min.js',
        'underscore' => 'underscore-min.js',
    );
    /**
     * @var array A list of paths that will be searched for unknown scripts
     */
    protected static $_supportedScriptPaths;
    /**
     * @var bool If true, the complete event is passed to scripts, otherwise,
     * they just get the data object. This setting is to help migrate 1.5.x users
     * to the new event structure.
     */
    protected $_passFullEvent = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Registers various available extensions to the v8 instance...
     *
     * @param string $libraryScriptPath
     * @param array  $variables
     * @param array  $extensions
     * @param bool   $reportUncaughtExceptions
     * @param bool   $passFullEvent
     *
     * @throws RestException
     */
    public static function create( $libraryScriptPath = null, array $variables = null, array $extensions = null, $reportUncaughtExceptions = true, $passFullEvent = false )
    {
        //  Set up our script mappings for require()
        static::_initializeScriptPaths( $libraryScriptPath );

        //  Register any extensions
        static::_registerExtensions();

        //  Create the engine
        $_engine = new static( static::EXPOSED_OBJECT_NAME, $variables, $extensions, $reportUncaughtExceptions );
        $_engine->setPassFullEvent( Pii::getParam( 'dsp.pass_full_event', $passFullEvent ) );

        /**
         * This is the callback for the exposed "require()" function in the sandbox
         */
        /** @noinspection PhpUndefinedMethodInspection */
        $_engine->setModuleLoader(
            function ( $module )
            {
                Log::debug( 'ModuleLoader: ' . $module . ' requested.' );

                $_fullScriptPath = false;

                //  Remove any quotes from this passed in module
                $module = trim( str_replace( array( "'", '"' ), null, $module ), ' /' );

                //  Check the configured script paths
                if ( null === ( $_script = Option::get( static::$_libraryModules, $module ) ) )
                {
                    foreach ( static::$_supportedScriptPaths as $_key => $_path )
                    {
                        $_checkScriptPath = $_path . '/' . $module;

                        if ( is_file( $_checkScriptPath ) && is_readable( $_checkScriptPath ) )
                        {
                            $_script = $module;
                            $_fullScriptPath = $_checkScriptPath;
                            Log::debug( '  * Found user script "' . $module . '" in "' . $_checkScriptPath . '"' );
                            break;
                        }

                        Log::debug( '  * "' . $module . '" not found in "' . $_checkScriptPath . '" . ' );
                    }
                }

                //  Me no likey
                if ( !$_script || !$_fullScriptPath )
                {
                    throw new InternalServerErrorException( 'The module "' . $module . '" could not be found in any known locations . ' );
                }

                //  Return the full path to the script in the template
                return str_replace( '{module}', $_script, static::MODULE_LOADER_TEMPLATE );
            }
        );
    }

    /**
     * @param string $libraryScriptPath
     *
     * @throws RestException
     */
    protected static function _initializeScriptPaths( $libraryScriptPath = null )
    {
        //  Set up
        $_platformConfigPath = Platform::getPlatformConfigPath();

        //  Get our script path
        static::$_libraryScriptPath = $libraryScriptPath ? : $_platformConfigPath . '/scripts';

        if ( empty( static::$_libraryScriptPath ) || !is_dir( static::$_libraryScriptPath ) || !is_writable( static::$_libraryScriptPath ) )
        {
            throw new RestException(
                HttpResponse::ServiceUnavailable, 'This service is not available . Storage path and/or required libraries not available . '
            );
        }

        //  All the paths that we will check for scripts
        static::$_supportedScriptPaths = array(
            //  This is ONLY the root of the app store 
            'app'      => Platform::getApplicationsPath(),
            //  Scripts here override library scripts 
            'platform' => $_platformConfigPath . '/scripts',
            //  Now check library distribution 
            'library'  => Platform::getLibraryConfigPath( '/scripts' ),
        );
    }

    /**
     * Registers all distribution library modules as extensions.
     * These can be accessed from scripts like this:
     *
     * require("lodash");
     *
     * var a = [ 'one', 'two', 'three' ];
     *
     * _.each( a, function( element ) {
     *      print( "Found " + element + " in array\n" );
     * });
     *
     * @return void
     */
    protected static function _registerExtensions()
    {
        foreach ( static::$_libraryModules as $_module => $_path )
        {
            /** @noinspection PhpUndefinedMethodInspection */
            static::registerExtension( 'lodash', str_replace( '{module}', 'lodash', static::MODULE_LOADER_TEMPLATE ), array(), false );
        }
    }

    /**
     * @return boolean
     */
    public function getPassFullEvent()
    {
        return $this->_passFullEvent;
    }

    /**
     * @param boolean $passFullEvent
     *
     * @return ScriptEngine
     */
    public function setPassFullEvent( $passFullEvent )
    {
        $this->_passFullEvent = $passFullEvent;

        return $this;
    }

    /**
     * @return string
     */
    public static function getLibraryScriptPath()
    {
        return static::$_libraryScriptPath;
    }

    /**
     * @return array
     */
    public static function getScriptPaths()
    {
        return static::$_supportedScriptPaths;
    }

    /**
     * @return array
     */
    public static function getLibraryModules()
    {
        return static::$_libraryModules;
    }
}