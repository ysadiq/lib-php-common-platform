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
namespace DreamFactory\Platform\Scripting\Engines;

use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Interfaces\ScriptingEngineLike;
use DreamFactory\Platform\Scripting\BaseEngineAdapter;
use DreamFactory\Platform\Utility\Platform;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Exceptions\FileSystemException;

/**
 * Plugin for the php-v8js extension which exposes the V8 Javascript engine
 */
class V8Js extends BaseEngineAdapter implements ScriptingEngineLike
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
	protected static $_systemModules
		= array(
			'lodash' => 'lodash.min.js',
		);
	/**
	 * @var bool True if system version of V8Js supports module loading
	 */
	protected static $_moduleLoaderAvailable = false;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param string $exposedObjectName
	 * @param array  $variables
	 * @param array  $exceptions
	 * @param bool   $reportUncaughtExceptions
	 *
	 * @internal param array $options
	 */
	public function __construct( $exposedObjectName = self::EXPOSED_OBJECT_NAME, array $variables = array(), $exceptions = array(), $reportUncaughtExceptions = false )
	{
		parent::__construct();

		//  Set up our script mappings for module loading
		$this->_engine = new \V8Js( $exposedObjectName, $variables, $exceptions, $reportUncaughtExceptions );

		/**
		 * This is the callback for the exposed "require()" function in the sandbox
		 */
		if ( static::$_moduleLoaderAvailable )
		{
			/** @noinspection PhpUndefinedMethodInspection */
			$this->_engine->setModuleLoader( function ( $module )
			{
				return static::loadScriptingModule( $module );
			} );
		}
	}

	/**
	 * Handle setup for global/all instances of engine
	 *
	 * @param array $options
	 *
	 * @return mixed
	 */
	public static function startup( $options = null )
	{
		parent::startup( $options );

		//	Find out if we have support for "require()"
		$_mirror = new \ReflectionClass( '\\V8Js' );

		/** @noinspection PhpUndefinedMethodInspection */
		if ( false !== ( static::$_moduleLoaderAvailable = $_mirror->hasMethod( 'setModuleLoader' ) ) )
		{
			//  Register any extensions
			static::_registerExtensions();
		}
	}

	/**
	 * Called before script is executed so you can wrap the script and add injections
	 *
	 * @param string $script
	 * @param array  $normalizedEvent
	 *
	 * @return string
	 */
	protected function _wrapScript( $script )
	{
		$_wrappedScript
			= <<<JS

_result = (function() {
	//	The event information
	//noinspection JSUnresolvedVariable
	var _event = DSP.event;

	return (function(event) {
		var _scriptResult = (function(event) {
			//noinspection BadExpressionStatementJS,JSUnresolvedVariable
			{$script};
		})(_event);

		if ( _event ) {
			_event.script_result = _scriptResult;
		}

		return _event;
	})(_event);
})();

JS;

		if ( !static::$_moduleLoaderAvailable )
		{
			$_wrappedScript
				= Platform::storeGet( 'scripting.module.lodash', static::loadScriptingModule( 'lodash', false ), false, 3600 ) . ';' . $_wrappedScript;
		}

		return $_wrappedScript;
	}

	/**
	 * Process a single script
	 *
	 * @param string $script          The string to execute
	 * @param string $scriptId        A string identifying this script
	 * @param array  $eventInfo       An array of information about the event triggering this script
	 * @param array  $engineArguments An array of arguments to pass when executing the string
	 *
	 * @internal param string $eventName
	 * @internal param \DreamFactory\Platform\Events\PlatformEvent $event
	 * @internal param \DreamFactory\Platform\Events\EventDispatcher $dispatcher
	 * @return mixed
	 */
	public function executeString( $script, $scriptId, $eventInfo, array $engineArguments = array() )
	{
		$_wrapped = $this->_wrapScript( $script, $eventInfo );

	}

	/**
	 * Process a single script
	 *
	 * @param string $script          The path/to/the/script to read and execute
	 * @param string $scriptId        A string identifying this script
	 * @param array  $eventInfo       An array of information about the event triggering this script
	 * @param array  $engineArguments An array of arguments to pass when executing the string
	 *
	 * @return mixed
	 */
	public function executeScript( $script, $scriptId, $eventInfo, array $engineArguments = array() )
	{
		return $this->executeString( static::loadScript( $scriptId, $script, true ), $scriptId, $eventInfo, $engineArguments );
	}

	/**
	 * @param string $module      The name of the module to load
	 * @param bool   $useTemplate If true, the module source will use the template format, otherwise the raw string is returned
	 * @param array  $engineArguments
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return mixed
	 */
	public static function loadScriptingModule( $module, $useTemplate = true )
	{
		//  Remove any quotes from this passed in module
		$module = trim( str_replace( array( "'", '"' ), null, $module ), ' /' );

		//  Check the configured script paths
		if ( null === ( $_script = static::loadScript( $module, $module ) ) )
		{
			//  Me no likey
			throw new InternalServerErrorException( 'The module "' . $module . '" could not be found in any known locations . ' );
		}

		if ( !$useTemplate )
		{
			return file_get_contents( $_script );
		}

		//  Return the full path to the script in the template
		return str_replace( '{module}', $_script, static::MODULE_LOADER_TEMPLATE );
	}

	/**
	 * @param string $script The path/file to load
	 * @param bool   $useTemplate
	 *
	 * @throws \Kisma\Core\Exceptions\FileSystemException
	 * @return mixed
	 */
	protected static function _loadScript( $script, $useTemplate = true )
	{
		$_contents = null;

		if ( is_file( $script ) && is_readable( $script ) )
		{
			if ( false === ( $_contents = file_get_contents( $script ) ) )
			{
				throw new FileSystemException( 'Unable to read the contents of the script file.' );
			}
		}

		if ( !$useTemplate )
		{
			return $_contents;
		}

//  Return the full path to the script in the template
		return str_replace( '{module}', $_contents, static::MODULE_LOADER_TEMPLATE );
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
				'This service is not available . Storage path and/or required libraries not available.' );
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

		if ( static::$_moduleLoaderAvailable )
		{
			foreach ( static::$_systemModules as $_module => $_path )
			{
				\V8Js::registerExtension( $_module,
					str_replace( '{module}', $_module, static::MODULE_LOADER_TEMPLATE ),
					array(),
					true );
			}
		}

		return empty( $_registered ) ? false : $_registered;
	}

	/**
	 * @param string $script
	 *
	 * @return string
	 */
	public static function enrobeScript( $script )
	{
		$_wrappedScript
			= <<<JS

_result = (function() {
	//	The event information
	//noinspection JSUnresolvedVariable
	var _event = DSP.event;

	return (function(event) {
		var _scriptResult = (function(event) {
			//noinspection BadExpressionStatementJS,JSUnresolvedVariable
			{$script};
		})(_event);

		if ( _event ) {
			_event.script_result = _scriptResult;
		}

		return _event;
	})(_event);
})();

JS;

		//	Inject lo-dash if no module loader
		if ( !static::$_moduleLoaderAvailable )
		{
			$_wrappedScript
				= Platform::storeGet( 'scripting.module.lodash', static::loadScriptingModule( 'lodash', false ), false, 3600 ) . ';' . $_wrappedScript;
		}

		return $_wrappedScript;
	}

	/**
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return mixed
	 */
	public function __call( $name, $arguments )
	{
		if ( $this->_engine )
		{
			return call_user_func_array( array( $this->_engine, $name ), $arguments );
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

}