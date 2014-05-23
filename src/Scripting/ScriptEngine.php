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
use Kisma\Core\Utility\Option;

/**
 * Contains functions to generically invoke a scripting engine
 */
abstract class ScriptEngine implements ScriptingEngineLike
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var array The module(s) we add to the scripting context
	 */
	protected static $_libraryModules = array();
	/**
	 * @var string One path to rule them all
	 */
	protected static $_libraryScriptPath = null;
	/**
	 * @var array A list of paths that will be searched for unknown scripts
	 */
	protected static $_supportedScriptPaths = array();
	/**
	 * @var ScriptingEngineLike
	 */
	protected static $_engine;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Force use of create
	 */
	public function __construct()
	{
		throw new \LogicException( 'You create this object directly. Please use the ::create() method.' );
	}

	/**
	 * @param string $script
	 *
	 * @return string|bool Returns the absolute path to the script file to be run or FALSE if not found
	 */
	public static function locateScriptFile( $script )
	{
		$_fullScriptPath = false;

		//  Remove any quotes from this passed in module
		$script = trim( str_replace( array( "'", '"' ), null, $script ), ' /' );

		//  Check the configured script paths
		if ( null === ( $_script = Option::get( static::$_libraryModules, $script ) ) )
		{
			$_script = $script;
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
			return false;
		}

		//  Return the full path to the script in the template
		return $_fullScriptPath;
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
	 * @return ScriptingEngineLike
	 */
	public static function getEngine()
	{
		return self::$_engine;
	}

	/**
	 * @return array
	 */
	public static function getLibraryModules()
	{
		return self::$_libraryModules;
	}

	/**
	 * @param array $libraryModules
	 */
	public static function setLibraryModules( $libraryModules )
	{
		self::$_libraryModules = $libraryModules;
	}

	/**
	 * @return string
	 */
	public static function getLibraryScriptPath()
	{
		return self::$_libraryScriptPath;
	}

	/**
	 * @param string $libraryScriptPath
	 */
	public static function setLibraryScriptPath( $libraryScriptPath )
	{
		self::$_libraryScriptPath = $libraryScriptPath;
	}

	/**
	 * @return array
	 */
	public static function getSupportedScriptPaths()
	{
		return self::$_supportedScriptPaths;
	}

	/**
	 * @param array $supportedScriptPaths
	 */
	public static function setSupportedScriptPaths( $supportedScriptPaths )
	{
		self::$_supportedScriptPaths = $supportedScriptPaths;
	}

	/**
	 * @param string $path
	 */
	public static function addSupportedScriptPath( $path )
	{
		if ( !in_array( $path, static::$_supportedScriptPaths ) )
		{
			static::$_supportedScriptPaths = $path;
		}

		static ::$_supportedScriptPaths = $path;
	}

}