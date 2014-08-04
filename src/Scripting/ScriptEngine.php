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
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Scripting;

use DreamFactory\Platform\Components\StateStack;
use DreamFactory\Platform\Enums\DataFormats;
use DreamFactory\Platform\Events\Exceptions\ScriptException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\RestResponse;
use DreamFactory\Platform\Utility\ServiceHandler;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Interfaces\HttpMethod;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Symfony\Component\HttpFoundation\Request;

/**
 * V8Js scripting engine
 */

/** @noinspection PhpUndefinedClassInspection */
class ScriptEngine
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string The name of the object which exposes PHP
     */
    const EXPOSED_OBJECT_NAME = 'DSP';
    /**
     * @type string The template for all module loading
     */
    const MODULE_LOADER_TEMPLATE = 'require("{module}");';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array The module(s) we add to the scripting context
     */
    protected static $_libraryModules = array(
        'lodash' => 'lodash.min.js',
    );
    /**
     * @var array The currently supported scripting languages
     */
    protected static $_supportedLanguages = array(
        'js'  => 'Javascript',
        'lua' => 'Lua',
        'py'  => 'Python',
    );
    /**
     * @var string One path to rule them all
     */
    protected static $_libraryScriptPath;
    /**
     * @var array A list of paths that will be searched for unknown scripts
     */
    protected static $_supportedScriptPaths;
    /**
     * @var \ReflectionClass
     */
    protected static $_mirror;
    /**
     * @var bool True if system version of V8Js supports module loading
     */
    protected static $_moduleLoaderAvailable = false;
    /**
     * @var bool If true, memory usage is output to log after script execution
     */
    protected static $_logScriptMemoryUsage = false;
    /**
     * @var array Array of running script engines
     */
    protected static $_instances = array();

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
     *
     * @return \V8Js
     * @throws RestException
     */
    public static function create( $libraryScriptPath = null, array $variables = array(), array $extensions = array(), $reportUncaughtExceptions = true )
    {
        //  Create the mirror if we haven't already...
        static::_initializeEngine( $libraryScriptPath );

        /** @noinspection PhpUndefinedClassInspection */
        $_engine = new \V8Js( static::EXPOSED_OBJECT_NAME, $variables, $extensions, $reportUncaughtExceptions );

        if ( static::$_logScriptMemoryUsage )
        {
            /** @noinspection PhpUndefinedMethodInspection */
            $_loadedExtensions = $_engine->getExtensions();

            Log::debug(
                '  * engine created with the following extensions: ' .
                ( !empty( $_loadedExtensions ) ? implode( ', ', array_keys( $_loadedExtensions ) ) : '**NONE**' )
            );
        }

        /**
         * This is the callback for the exposed "require()" function in the sandbox
         */
        if ( static::$_moduleLoaderAvailable )
        {
            /** @noinspection PhpUndefinedMethodInspection */
            $_engine->setModuleLoader(
                function ( $module )
                {
                    return static::loadScriptingModule( $module );
                }
            );
        }

        //  Stuff it in our instances array
        static::$_instances[spl_object_hash( $_engine )] = $_engine;

        return $_engine;
    }

    /**
     * Publically destroy engine
     *
     * @param \V8Js $engine
     */
    public static function destroy( $engine )
    {
        $_hash = spl_object_hash( $engine );

        if ( isset( static::$_instances[$_hash] ) )
        {
            unset( static::$_instances[$_hash] );
        }

        unset( $engine );
    }

    /**
     * @param string $libraryScriptPath
     *
     * @throws \DreamFactory\Platform\Exceptions\RestException
     */
    protected static function _initializeEngine( $libraryScriptPath = null )
    {
        if ( static::$_mirror )
        {
            return;
        }

        //	Find out if we have support for "require()"
        static::$_mirror = new \ReflectionClass( '\\V8Js' );

        /**
         * Check to see if the V8 version that we have has a module loader. If there is one, we register any extensions that are configured.
         * If not, library code must be injected in the wrapper.
         */
        if ( false !== ( static::$_moduleLoaderAvailable = static::$_mirror->hasMethod( 'setModuleLoader' ) ) )
        {
            //  Register any extensions
            static::_registerExtensions();
        }
        else
        {
            if ( static::$_logScriptMemoryUsage )
            {
                /** @noinspection PhpUndefinedClassInspection */
                Log::debug( '  * no "require()" support in V8 library v' . \V8Js::V8_VERSION );
            }
        }

        //  Set up our script mappings for module loading
        static::_initializeScriptPaths( $libraryScriptPath );
    }

    /**
     * @param string $scriptName      The absolute path to the script to be run
     * @param string $scriptId        The name of this script
     * @param array  $exposedEvent    The event as it will be exposed to script
     * @param array  $exposedPlatform The platform object
     * @param string $output          Any output of the script
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array
     */
    public static function runScript( $scriptName, $scriptId = null, array &$exposedEvent = array(), array &$exposedPlatform = array(), &$output = null )
    {
        $scriptId = $scriptId ?: $scriptName;

        if ( !is_file( $scriptName ) || !is_readable( $scriptName ) )
        {
            throw new InternalServerErrorException( 'The script ID "' . $scriptId . '" is not valid or unreadable.' );
        }

        if ( false === ( $_script = @file_get_contents( $scriptName ) ) )
        {
            throw new InternalServerErrorException(
                'The script ID "' . $scriptId . '" cannot be retrieved at this time.'
            );
        }

        $_engine = static::create();

        $_result = $_message = false;

        try
        {
            $_runnerShell = static::enrobeScript( $_engine, $_script, $exposedEvent, $exposedPlatform );

            //  Don't show output
            ob_start();

            /** @noinspection PhpUndefinedMethodInspection */
            /** @noinspection PhpUndefinedClassInspection */
            $_result = $_engine->executeString( $_runnerShell, $scriptId, \V8Js::FLAG_FORCE_ARRAY );
        }
            /** @noinspection PhpUndefinedClassInspection */
        catch ( \V8JsException $_ex )
        {
            $output = ob_end_clean();
            $_message = $_ex->getMessage();

            /**
             * @note     V8JsTimeLimitException was released in a later version of the libv8 library than is supported by the current PECL v8js extension. Hence the check below.
             * @noteDate 2014-04-03
             */

            /** @noinspection PhpUndefinedClassInspection */
            if ( class_exists( '\\V8JsTimeLimitException', false ) && ( $_ex instanceof \V8JsTimeLimitException ) )
            {
                /** @var \Exception $_ex */
                Log::error( $_message = 'Timeout while running script "' . $scriptId . '": ' . $_message );
            }
            else
            {
                /** @noinspection PhpUndefinedClassInspection */
                if ( class_exists( '\\V8JsMemoryLimitException', false ) && $_ex instanceof \V8JsMemoryLimitException )
                {
                    Log::error( $_message = 'Out of memory while running script "' . $scriptId . '": ' . $_message );
                }
                else
                {
                    Log::error( $_message = 'Exception executing javascript: ' . $_message );
                }
            }
        }

        static::destroy( $_engine );

        $output = ob_get_clean();

        if ( static::$_logScriptMemoryUsage )
        {
            Log::debug( 'Engine memory usage: ' . Utilities::resizeBytes( memory_get_usage( true ) ) );
        }

        if ( false !== $_message )
        {
            throw new ScriptException( $_message, $output );
        }

        return $_result;
    }

    /**
     * @param string $module      The name of the module to load
     * @param bool   $useTemplate If true, the module source will use the template format, otherwise the raw string is returned
     *
     * @throws InternalServerErrorException
     *
     * @return mixed
     */
    public static function loadScriptingModule( $module, $useTemplate = true )
    {
        $_fullScriptPath = false;

        //  Remove any quotes from this passed in module
        $module = trim( str_replace( array("'", '"'), null, $module ), ' /' );

        //  Check the configured script paths
        if ( null === ( $_script = Option::get( static::$_libraryModules, $module ) ) )
        {
            $_script = $module;
        }

        foreach ( static::$_supportedScriptPaths as $_key => $_path )
        {
            $_checkScriptPath = $_path . '/' . $_script;

            if ( is_file( $_checkScriptPath ) && is_readable( $_checkScriptPath ) )
            {
                $_fullScriptPath = $_checkScriptPath;
                break;
            }
        }

        //  Me no likey
        if ( !$_script || !$_fullScriptPath )
        {
            throw new InternalServerErrorException(
                'The module "' . $module . '" could not be found in any known locations . '
            );
        }

        if ( !$useTemplate )
        {
            return file_get_contents( $_fullScriptPath );
        }

        //  Return the full path to the script in the template
        return str_replace( '{module}', $_script, static::MODULE_LOADER_TEMPLATE );
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
        static::$_libraryScriptPath = $libraryScriptPath ?: Platform::getLibraryConfigPath( '/scripts' );

        if ( empty( static::$_libraryScriptPath ) || !is_dir( static::$_libraryScriptPath ) )
        {
            throw new RestException(
                HttpResponse::ServiceUnavailable, 'This service is not available . Storage path and/or required libraries not available . '
            );
        }

        static::$_logScriptMemoryUsage = Pii::getParam( 'dsp.log_script_memory_usage', false );

        //  All the paths that we will check for scripts
        static::$_supportedScriptPaths = array(
            //  This is ONLY the root of the app store
            'app'      => Platform::getApplicationsPath(),
            //  This is the user's private scripting area used by the admin console
            'storage'  => Platform::getPrivatePath( '/scripts' ),
            //  Scripts here override library scripts
            'platform' => $_platformConfigPath . '/scripts',
            //  Now check library distribution
            'library'  => static::$_libraryScriptPath,
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
     * Please note that this requires a version of the V8 library above any that are currently
     * distributed with popular distributions. As such, if this feature is not available
     * (module loading), the "lodash" library will be automatically registered and injected
     * into all script contexts.
     *
     * @return array|bool
     */
    protected static function _registerExtensions()
    {
        $_registered = array();

        foreach ( static::$_libraryModules as $_module => $_path )
        {
            /** @noinspection PhpUndefinedClassInspection */
            \V8Js::registerExtension(
                $_module,
                str_replace( '{module}', $_module, static::MODULE_LOADER_TEMPLATE ),
                array(),
                true
            );
        }

        return empty( $_registered ) ? false : $_registered;
    }

    /**
     * @param \V8Js  $engine
     * @param string $script
     * @param array  $exposedEvent
     * @param array  $exposedPlatform
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return string
     */
    public static function enrobeScript( $engine, $script, array $exposedEvent = array(), array $exposedPlatform = array() )
    {
        $exposedEvent['__tag__'] = 'exposed_event';
        $exposedPlatform['api'] = static::_getExposedApi();

        $engine->event = $exposedEvent;
        $engine->platform = $exposedPlatform;

        $_jsonEvent = json_encode( $exposedEvent, JSON_UNESCAPED_SLASHES );

        $_enrobedScript = <<<JS
        
_wrapperResult = (function() {

    var _event = {$_jsonEvent};

	try	{
		_event.script_result = (function(event, platform) {
		
			{$script}
			;
			
		})(_event, DSP.platform);
	}
	catch ( _ex ) {
		_event.script_result = {error:_ex.message};
		_event.exception = _ex;
	}

	return _event;

})();

JS;

        if ( !static::$_moduleLoaderAvailable )
        {
            $_enrobedScript = Platform::mcGet(
                    'scripting.modules.lodash',
                    static::loadScriptingModule( 'lodash', false ),
                    false,
                    3600
                ) . ';' . $_enrobedScript;
        }

        return $_enrobedScript;
    }

    /**
     * @param string $method
     * @param string $url
     * @param mixed  $payload
     * @param array  $curlOptions
     *
     * @return \stdClass|string
     */
    protected static function _externalRequest( $method, $url, $payload = array(), $curlOptions = array() )
    {
        try
        {
            $_result = Curl::request( $method, $url, $payload, $curlOptions );
        }
        catch ( \Exception $_ex )
        {
            $_result = RestResponse::sendErrors( $_ex, DataFormats::PHP_ARRAY, false, false );

            Log::error( 'Exception: ' . $_ex->getMessage(), array(), array('response' => $_result) );
        }

        return $_result;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array  $payload
     * @param array  $curlOptions Additional CURL options for external requests
     *
     * @return array
     */
    public static function inlineRequest( $method, $path, $payload = null, $curlOptions = array() )
    {
        if ( null === $payload || 'null' == $payload )
        {
            $payload = array();
        }

        if ( 'https:/' == ( $_protocol = substr( $path, 0, 7 ) ) || 'http://' == $_protocol )
        {
            return static::_externalRequest( $method, $path, $payload ?: array(), $curlOptions );
        }

        $_result = null;
        $_requestUri = '/rest/' . ltrim( $path, '/' );
        $_contentType = 'application/json';

        if ( false === ( $_pos = strpos( $path, '/' ) ) )
        {
            $_serviceId = $path;
            $_resource = null;
        }
        else
        {
            $_serviceId = substr( $path, 0, $_pos );
            $_resource = substr( $path, $_pos + 1 );

            //	Fix removal of trailing slashes from resource
            if ( !empty( $_resource ) )
            {
                if ( ( false === strpos( $_requestUri, '?' ) && '/' === substr( $_requestUri, strlen( $_requestUri ) - 1, 1 ) ) ||
                     ( '/' === substr( $_requestUri, strpos( $_requestUri, '?' ) - 1, 1 ) )
                )
                {
                    $_resource .= '/';
                }
            }
        }

        if ( empty( $_serviceId ) )
        {
            return null;
        }

        if ( false === ( $_payload = json_encode( $payload, JSON_UNESCAPED_SLASHES ) ) || JSON_ERROR_NONE != json_last_error()
        )
        {
            $_contentType = 'text/plain';
            $_payload = $payload;
        }

        StateStack::push();

        try
        {
            $_request = new Request( array(), array(), array(), $_COOKIE, $_FILES, $_SERVER, $_payload );
            $_request->query->set( 'app_name', 'dsp.scripting' );
            $_request->query->set( 'path', $path );
            $_request->server->set( 'REQUEST_METHOD', $method );
            $_request->server->set( 'INLINE_REQUEST_URI', $_requestUri );

            if ( $_contentType )
            {
                $_request->headers->set( 'CONTENT_TYPE', $_contentType );
            }

            $_request->overrideGlobals();

            //  Now set the request object and go...
            Pii::app()->setRequestObject( $_request );

            $_service = ServiceHandler::getService( $_serviceId );
            $_result = $_service->processRequest( $_resource, $method, false );
        }
        catch ( \Exception $_ex )
        {
            $_result = RestResponse::sendErrors( $_ex, DataFormats::PHP_ARRAY, false, false, false );

            Log::error( 'Exception: ' . $_ex->getMessage(), array(), array('response' => $_result) );
        }

        StateStack::pop();

        return $_result;
    }

    /**
     * @return \stdClass
     */
    protected static function _getExposedApi()
    {
        $_api = new \stdClass();

        $_api->_call = function ( $method, $path, $payload = null, $curlOptions = array() )
        {
            return static::inlineRequest( $method, $path, $payload, $curlOptions );
        };

        $_api->get = function ( $path, $payload = null, $curlOptions = array() )
        {
            return static::inlineRequest( HttpMethod::GET, $path, $payload, $curlOptions );
        };

        $_api->put = function ( $path, $payload = null, $curlOptions = array() )
        {
            return static::inlineRequest( HttpMethod::PUT, $path, $payload, $curlOptions );
        };

        $_api->post = function ( $path, $payload = null, $curlOptions = array() )
        {
            return static::inlineRequest( HttpMethod::POST, $path, $payload, $curlOptions );
        };

        $_api->delete = function ( $path, $payload = null, $curlOptions )
        {
            return static::inlineRequest( HttpMethod::DELETE, $path, $payload, $curlOptions );
        };

        $_api->merge = function ( $path, $payload = null, $curlOptions = array() )
        {
            return static::inlineRequest( HttpMethod::MERGE, $path, $payload, $curlOptions );
        };

        $_api->patch = function ( $path, $payload = null, $curlOptions = array() )
        {
            return static::inlineRequest( HttpMethod::PATCH, $path, $payload, $curlOptions );
        };

        return $_api;
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

    /**
     * @return array
     */
    public static function getSupportedExtensions()
    {
        return array_keys( static::$_supportedLanguages );
    }
}