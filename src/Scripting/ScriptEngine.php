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

use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Utility\Platform;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Option;

/**
 * Wrapper around V8Js which sets up some basic things for dispatching events
 * @method mixed executeString( string $script, string $identifier = "V8Js::executeString()", int $flags = \V8Js::FLAG_NONE )
 * @method array getExtensions()
 *
 * @property array $event
 */
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
	protected static $_libraryModules
		= array(
			'lodash' => 'lodash.min.js',
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
	 * @var \V8Js
	 */
	protected static $_engine;
	/**
	 * @var bool True if system version of V8Js supports module loading
	 */
	protected static $_moduleLoaderAvailable = false;

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
	 * @return static
	 * @throws RestException
	 */
	public static function create( $libraryScriptPath = null, array $variables = array(), array $extensions = array(), $reportUncaughtExceptions = true )
	{
		//  Create the engine
		if ( !static::$_engine )
		{
			//	Find out if we have support for "require()"
			$_mirror = new \ReflectionClass( '\\V8Js' );

			/** @noinspection PhpUndefinedMethodInspection */
			if ( false !== ( static::$_moduleLoaderAvailable = $_mirror->hasMethod( 'setModuleLoader' ) ) )
			{
				//  Register any extensions
				static::_registerExtensions();

				/** @noinspection PhpUndefinedMethodInspection */
//				$_loadedExtensions = static::$_engine->getExtensions();
//				Log::debug( '  * engine created with the following extensions: ' .
//					( !empty( $_loadedExtensions ) ? implode( ', ', array_keys( $_loadedExtensions ) ) : '**NONE**' ) );
			}
			else
			{
				//	Remove underscore from module list so "lodash" will auto-load
//				Log::debug( '  * no "require()" support in V8 library v' . \V8Js::V8_VERSION );
			}

			//  Set up our script mappings for module loading
			static::_initializeScriptPaths( $libraryScriptPath );
			static::$_engine = new \V8Js( static::EXPOSED_OBJECT_NAME, $variables, $extensions, $reportUncaughtExceptions );
		}

		/**
		 * This is the callback for the exposed "require()" function in the sandbox
		 */
		if ( static::$_moduleLoaderAvailable )
		{
			/** @noinspection PhpUndefinedMethodInspection */
			static::$_engine->setModuleLoader( function ( $module )
			{
				return static::loadScriptingModule( $module );
			} );
		}

		return static::$_engine;
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
//		Log::debug( '  * loading module: ' . $module );

		$_fullScriptPath = false;

		//  Remove any quotes from this passed in module
		$module = trim( str_replace( array( "'", '"' ), null, $module ), ' /' );

		//  Check the configured script paths
		if ( null === ( $_script = Option::get( static::$_libraryModules, $module ) ) )
		{
			$_script = $module;
		}

		foreach ( static::$_supportedScriptPaths as $_key => $_path )
		{
			$_checkScriptPath = $_path . '/' . $_script;
//			Log::debug( '  * Checking: ' . $_checkScriptPath );

			if ( is_file( $_checkScriptPath ) && is_readable( $_checkScriptPath ) )
			{
				$_fullScriptPath = $_checkScriptPath;
//				Log::debug( '    * Found module "' . $module . '" in "' . $_checkScriptPath . '"' );
				break;
			}

//			Log::debug( '  * "' . $module . '" not found in "' . $_checkScriptPath . '" . ' );
		}

		//  Me no likey
		if ( !$_script || !$_fullScriptPath )
		{
			throw new InternalServerErrorException( 'The module "' . $module . '" could not be found in any known locations . ' );
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
		static::$_libraryScriptPath = $libraryScriptPath ? : Platform::getLibraryConfigPath( '/scripts' );

		if ( empty( static::$_libraryScriptPath ) || !is_dir( static::$_libraryScriptPath ) )
		{
			throw new RestException( HttpResponse::ServiceUnavailable,
				'This service is not available . Storage path and/or required libraries not available . ' );
		}

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
			\V8Js::registerExtension( $_module, str_replace( '{module}', $_module, static::MODULE_LOADER_TEMPLATE ), array(), true );
//			Log::debug( '  * registered extension "' . $_module . '"' );
		}

		return empty( $_registered ) ? false : $_registered;
	}

	/**
	 * @param string $script
	 * @param array  $normalizedEvent
	 *
	 * @return string
	 */
	public static function enrobeScript( $script, array $normalizedEvent = array() )
	{
		$_enrobedScript
			= <<<JS

_result = (function() {
	//	The event information
	//noinspection JSUnresolvedVariable
	var _event = DSP.event;

	return (function() {
		var _scriptResult = (function(event) {
			//noinspection BadExpressionStatementJS,JSUnresolvedVariable
			{$script};
		})(_event);

		if ( _event ) {
			_event.script_result = _scriptResult;
		}

		return _event;
	})();
})();

JS;

		if ( !static::$_moduleLoaderAvailable )
		{
			$_enrobedScript
				= Platform::storeGet( 'scripting.module.lodash', static::loadScriptingModule( 'lodash', false ), false, 3600 ) . ';' . $_enrobedScript;
		}

		return $_enrobedScript;
	}

	/**
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return mixed
	 */
	public function __call( $name, $arguments )
	{
		if ( static::$_engine )
		{
			return call_user_func_array( array( static::$_engine, $name ), $arguments );
		}
	}

	/**
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return mixed
	 */
	public static function __callStatic( $name, $arguments )
	{
		return call_user_func_array( array( '\\V8Js', $name ), $arguments );
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