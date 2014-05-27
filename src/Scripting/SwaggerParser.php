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

use DreamFactory\Platform\Resources\System\Config;
use DreamFactory\Platform\Resources\System\User;
use DreamFactory\Platform\Utility\Platform;
use Jeremeamia\SuperClosure\SerializableClosure;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Converts a Swagger configuration file into an API object consumable by server-side scripts
 */
class SwaggerParser
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var string The Swagger cache
	 */
	protected $_cachePath;
	/**
	 * @var string The Swagger custom cache
	 */
	protected $_customPath;

	/**
	 * @param string $swaggerPath If specified, used as Swagger base path
	 */
	public function __construct( $swaggerPath = null )
	{
		$swaggerPath = $swaggerPath ? : Platform::getSwaggerPath();

		$this->_cachePath = $swaggerPath . '/cache';
		$this->_customPath = $swaggerPath . '/custom';
		$_me = $this;

		//	Rebuild our cache when Swagger cache is cleared
		Platform::on( 'swagger.cache_cleared',
			function ( $eventName, $event, $dispatcher ) use ( $_me )
			{
				Log::debug( '  * Rebuilding scripting API because Swagger cache cleared' );
				$_me->buildApi( true );
			} );
	}

	/**
	 * Reads the Swagger configuration and rebuilds the server-side scripting API
	 *
	 * @param bool $force If true, rebuild regardless of cached state
	 *
	 * @return \stdClass
	 */
	public function buildApi( $force = false )
	{
		$_apiObject = Platform::storeGet( 'scripting.swagger_api' );

		if ( !$force && !empty( $_apiObject ) )
		{
			return $_apiObject;
		}

		if ( false === ( $_base = $this->_loadCacheFile() ) )
		{
			return false;
		}

		$_apiObject = new \stdClass();

		foreach ( $_base['apis'] as $_baseApi )
		{
			$_path = str_replace( '/', null, $_baseApi['path'] );

			if ( false === ( $_cacheFile = $this->_loadCacheFile( $_path ) ) )
			{
				continue;
			}

			if ( false !== ( strpos( $_resourcePath = Option::get( $_cacheFile, 'resourcePath', $_path ), '/', 0 ) ) )
			{
				$_resourcePath = ltrim( $_resourcePath, '/' );
			}

			$_service = new \stdClass();

			foreach ( $_cacheFile['apis'] as $_serviceApi )
			{
				foreach ( $_serviceApi['operations'] as $_operation )
				{
					$_service->{$_operation['nickname']} = new SerializableClosure( function ( $payload = null ) use ( $_operation, $_serviceApi )
					{
						return ScriptEngine::inlineRequest( $_operation['method'], $_operation['nickname'], ltrim( $_serviceApi['path'], '/' ), $payload );
					} );
				}
			}

			$_apiObject->{$_resourcePath} = $_service;
			unset( $_service, $_cacheFile );
		}

		//	Store it
		Platform::storeSet( 'scripting.swagger_api', $_apiObject );

		return $_apiObject;
	}

	/**
	 * Loads and returns the Swagger cache
	 *
	 * @param string $cacheFile The name of the cache to load, or null for the base
	 *
	 * @return bool
	 */
	protected function _loadCacheFile( $cacheFile = null )
	{
		$_cache = json_decode( file_get_contents( $this->_cachePath . '/' . ( $cacheFile ? : '_' ) . '.json' ), true );

		if ( empty( $_cache ) || JSON_ERROR_NONE !== json_last_error() )
		{
			Log::error( 'No Swagger cache or invalid JSON detected.' );

			return false;
		}

		return $_cache;
	}
}
