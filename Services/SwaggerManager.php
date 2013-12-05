<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Log;

/**
 * SwaggerManager
 * DSP API Documentation manager
 *
 */
class SwaggerManager extends BasePlatformRestService
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @var string The private caching directory
	 */
	const SWAGGER_CACHE_DIR = '/swagger/';

	/**
	 * @var string The private storage directory for non-generated files
	 */
	const SWAGGER_STORE_DIR = '/swagger_store/';

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Create a new SwaggerManager
	 *
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				 'name'          => 'Swagger Documentation Management',
				 'apiName'       => 'api_docs',
				 'type'          => 'Swagger',
				 'type_id'       => PlatformServiceTypes::SYSTEM_SERVICE,
				 'description'   => 'Service for a user to see the API documentation provided via Swagger.',
				 'is_active'     => true,
				 'native_format' => 'json',
			)
		);
	}

	/**
	 * @return array
	 */
	protected function _listResources()
	{
		return static::getSwagger();
	}

	/**
	 * @return array|string|bool
	 */
	protected function _handleResource()
	{
		if ( self::Get == $this->_action )
		{
			switch ( $this->_resource )
			{
				case '':
					return static::getSwagger();
					break;

				default:
					return static::getSwaggerForService( $this->_resource );
					break;
			}
		}

		return false;
	}

	/**
	 * Internal building method builds all static services and some dynamic
	 * services from file annotations, otherwise swagger info is loaded from
	 * database or storage files for each service, if it exists.
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected static function buildSwagger()
	{
		$_basePath = Pii::request()->getHostInfo() . '/rest';
		$_swaggerPath = Pii::getParam( 'storage_base_path' ) . static::SWAGGER_CACHE_DIR;

		// create root directory if it doesn't exists
		if ( !file_exists( $_swaggerPath ) )
		{
			@\mkdir( $_swaggerPath, 0777, true );
		}

		// generate swagger output from file annotations
		$_scanPath = rtrim( __DIR__, '/' ) . '/';

		$_baseSwagger = array(
			'swaggerVersion' => '1.2',
			'apiVersion'     => API_VERSION,
			'basePath'       => $_basePath,
		);

		// build services from database
		$_command = Pii::db()->createCommand();
		$_result = $_command->select( 'api_name,type_id,storage_type_id,description' )
			->from( 'df_sys_service' )
			->order( 'api_name' )
			->queryAll();

		// add static services
		$_other = array(
			array( 'api_name' => 'user', 'type_id' => 0, 'description' => 'User Login' ),
			array( 'api_name' => 'system', 'type_id' => 0, 'description' => 'System Configuration' )
		);
		$_result = array_merge( $_other, $_result );

		// gather the services
		$_services = array();

		foreach ( $_result as $_service )
		{
			$_apiName = Option::get( $_service, 'api_name' );
			$_typeId = Option::get( $_service, 'type_id' );
			$_fileName = $_apiName;

			switch ( $_typeId )
			{
				case PlatformServiceTypes::SYSTEM_SERVICE:
					if ( 0 == strcasecmp( $_apiName, 'system' ) )
					{
						$_fileName = 'SystemManager';
					}
					else
					{
						$_fileName = 'UserManager';
					}
					break;
				case PlatformServiceTypes::LOCAL_PORTAL_SERVICE:
					$_fileName = 'Portal';
					break;
				case PlatformServiceTypes::REMOTE_WEB_SERVICE:
					$_fileName = 'RemoteWebSvc';
					break;
				case PlatformServiceTypes::LOCAL_FILE_STORAGE:
					$_fileName = 'LocalFileSvc';
					break;
				case PlatformServiceTypes::REMOTE_FILE_STORAGE:
					$_fileName = 'RemoteFileSvc';
					break;
				case PlatformServiceTypes::LOCAL_SQL_DB:
				case PlatformServiceTypes::REMOTE_SQL_DB:
					$_fileName = 'SqlDbSvc';
					break;
				case PlatformServiceTypes::LOCAL_SQL_DB_SCHEMA:
				case PlatformServiceTypes::REMOTE_SQL_DB_SCHEMA:
					$_fileName = 'SchemaSvc';
					break;
				case PlatformServiceTypes::EMAIL_SERVICE:
					$_fileName = 'EmailSvc';
					break;
				case PlatformServiceTypes::NOSQL_DB:
					$_fileName = 'NoSqlDbSvc';
					break;
				case PlatformServiceTypes::SALESFORCE:
					$_fileName = 'SalesforceDbSvc';
					break;
			}

			$_content = null;
			$_fileName = $_scanPath . $_fileName . '.swagger.php';
			if ( !file_exists( $_fileName ) )
			{
				$_storePath = Pii::getParam( 'storage_base_path' ) . static::SWAGGER_STORE_DIR;
				$_filePath = $_storePath . $_apiName . '.json';
				if ( !file_exists( $_filePath ) )
				{
					$_defaultPath = dirname( __DIR__ ) . '/Templates/Swagger/default_service_swagger.json';
					if ( !file_exists( $_defaultPath ) )
					{
						Log::error( "No default swagger file at $_defaultPath." );
						continue;
					}
					if ( false === ( $_content = file_get_contents( $_defaultPath ) ) )
					{
						Log::error( "Failed to get default swagger file contents." );
						continue;
					}
				}
				else
				{
					if ( false === ( $_content = file_get_contents( $_filePath ) ) )
					{
						Log::error( "Failed to get default swagger file contents for service $_apiName." );
						continue;
					}
				}
			}
			else
			{
				$_fromFile = require( $_fileName );
				if ( is_array( $_fromFile ) )
				{
					$_content = array_merge( $_baseSwagger, $_fromFile );
				}
				$_content = json_encode( $_content );
			}

			if ( empty( $_content ) )
			{
				Log::error( "Empty swagger file contents for service $_apiName." );
				continue;
			}

			// replace service type placeholder with api name for this service instance
			$_content = str_replace( '/{api_name}', '/' . $_apiName, $_content );

			// cache it to a file for later access
			$_filePath = $_swaggerPath . $_apiName . '.json';
			if ( false === file_put_contents( $_filePath, $_content ) )
			{
				Log::error( "Failed to write cache file $_filePath." );
			}

			// build main services list
			$_services[] = array(
				'path'        => '/' . $_apiName,
				'description' => Option::get( $_service, 'description' )
			);
		}

		// cache main api listing file
		$_main = $_scanPath . 'SwaggerManager.swagger.php';
		$_resourceListing = require( $_main );
		$_out = array_merge( $_resourceListing, array( 'apis' => $_services ) );
		$_filePath = $_swaggerPath . '_.json';
		if ( false === file_put_contents( $_filePath, json_encode( $_out ) ) )
		{
			Log::error( "Failed to write cache file $_filePath." );
		}

		return $_out;
	}

	/**
	 * Main retrieve point for a list of swagger-able services
	 * This builds the full swagger cache if it does not exist
	 *
	 * @return string The JSON contents of the swagger api listing.
	 * @throws InternalServerErrorException
	 */
	public static function getSwagger()
	{
		$_swaggerPath = Pii::getParam( 'storage_base_path' ) . static::SWAGGER_CACHE_DIR;
		$_filePath = $_swaggerPath . '_.json';
		if ( !file_exists( $_filePath ) )
		{
			static::buildSwagger();
			if ( !file_exists( $_filePath ) )
			{
				throw new InternalServerErrorException( "Failed to create swagger cache." );
			}
		}

		if ( false === ( $_content = file_get_contents( $_filePath ) ) )
		{
			throw new InternalServerErrorException( "Failed to retrieve swagger cache." );
		}

		return $_content;
	}

	/**
	 * Main retrieve point for each service
	 *
	 * @param string $service Which service (api_name) to retrieve.
	 *
	 * @throws InternalServerErrorException
	 * @return string The JSON contents of the swagger service.
	 */
	public static function getSwaggerForService( $service )
	{
		$_swaggerPath = Pii::getParam( 'storage_base_path' ) . static::SWAGGER_CACHE_DIR;
		$_filePath = $_swaggerPath . $service . '.json';
		if ( !file_exists( $_filePath ) )
		{
			static::buildSwagger();
			if ( !file_exists( $_filePath ) )
			{
				throw new InternalServerErrorException( "Failed to create swagger cache for service '$service'." );
			}
		}

		if ( false === ( $_content = file_get_contents( $_filePath ) ) )
		{
			throw new InternalServerErrorException( "Failed to retrieve swagger cache." );
		}

		return $_content;
	}

	/**
	 * Clears the cached files produced by the swagger annotations
	 */
	public static function clearCache()
	{
		$_swaggerPath = Pii::getParam( 'storage_base_path' ) . static::SWAGGER_CACHE_DIR;
		if ( file_exists( $_swaggerPath ) )
		{
			$files = array_diff( scandir( $_swaggerPath ), array( '.', '..' ) );
			foreach ( $files as $file )
			{
				@unlink( $_swaggerPath . $file );
			}
		}
	}
}
