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
namespace DreamFactory\Platform\Utility;

use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Services\BaseDbSvc;
use DreamFactory\Platform\Services\BaseFileSvc;
use DreamFactory\Platform\Services\SchemaSvc;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Platform\Yii\Models\Service;
use Kisma\Core\Utility\Option;

/**
 * Packager
 * DSP app packaging utilities
 */
class Packager
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param string $app_id
	 * @param bool   $include_files
	 * @param bool   $include_services
	 * @param bool   $include_schema
	 * @param bool   $include_data
	 *
	 * @throws \Exception
	 * @return null
	 */
	public static function exportAppAsPackage( $app_id, $include_files = false, $include_services = false, $include_schema = false, $include_data = false )
	{
		$_model = ResourceStore::model( 'app' );

		if ( $include_services || $include_schema )
		{
			$_model->with( 'app_service_relations.service' );
		}

		$_app = $_model->findByPk( $app_id );

		if ( null === $_app )
		{
			throw new NotFoundException( "No database entry exists for this application with id '$app_id'." );
		}

		$_appApiName = $_app->api_name;

		$_zipFileName = null;
		try
		{
			$_zip = new \ZipArchive();
			$_tempDir = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
			$_zipFileName = $_tempDir . $_appApiName . '.dfpkg';
			if ( true !== $_zip->open( $_zipFileName, \ZipArchive::CREATE ) )
			{
				throw new InternalServerErrorException( 'Can not create package file for this application.' );
			}

			// add database entry file
			$_appFields = array(
				'api_name',
				'name',
				'description',
				'is_active',
				'url',
				'is_url_external',
				'import_url',
				'requires_fullscreen',
				'requires_plugin'
			);
			$_record = $_app->getAttributes( $_appFields );
			if ( !$_zip->addFromString( 'description.json', json_encode( $_record ) ) )
			{
				throw new InternalServerErrorException( "Can not include description in package file." );
			}
			if ( $include_services || $include_schema )
			{
				/**
				 * @var Service[] $_serviceRelations
				 */
				$_serviceRelations = $_app->getRelated( 'app_service_relations' );
				if ( !empty( $_serviceRelations ) )
				{
					$_services = array();
					$_schemas = array();
					$_serviceFields = array(
						'name',
						'api_name',
						'description',
						'is_active',
						'type',
						'type_id',
						'is_system',
						'storage_name',
						'storage_type',
						'storage_type_id',
						'credentials',
						'native_format',
						'base_url',
						'parameters',
						'headers',
					);
					foreach ( $_serviceRelations as $_relation )
					{
						/** @var Service $_service */
						$_service = $_relation->getRelated( 'service' );
						if ( !empty( $_service ) )
						{
							if ( $include_services )
							{
								if ( !DataFormat::boolval( $_service->getAttribute( 'is_system' ) ) )
								{
									// get service details to restore with app
									$_temp = $_service->getAttributes( $_serviceFields );
									$_services[] = $_temp;
								}
							}
							if ( $include_schema )
							{
								$_component = $_relation->getAttribute( 'component' );
								if ( !empty( $_component ) )
								{
									$_component = json_decode( $_component, true );
									// service is probably a db, export table schema if possible
									$_serviceName = $_service->api_name;
									$_serviceType = $_service->type_id;
									switch ( strtolower( $_serviceType ) )
									{
										case PlatformServiceTypes::LOCAL_SQL_DB_SCHEMA:
										case PlatformServiceTypes::REMOTE_SQL_DB_SCHEMA:
											/** @var $_schema SchemaSvc */
											$_schema = ServiceHandler::getServiceObject( $_serviceName );
											$_describe = $_schema->describeTables( implode( ',', $_component ) );
											$_temp = array(
												'api_name' => $_serviceName,
												'table'    => $_describe
											);
											$_schemas[] = $_temp;
											break;
									}
								}
							}
						}
					}
					if ( !empty( $_services ) && !$_zip->addFromString( 'services.json', json_encode( $_services ) ) )
					{
						throw new InternalServerErrorException( "Can not include services in package file." );
					}
					if ( !empty( $_schemas ) && !$_zip->addFromString( 'schema.json', json_encode( array( 'service' => $_schemas ) ) ) )
					{
						throw new InternalServerErrorException( "Can not include database schema in package file." );
					}
				}
			}

			if ( !$_app->is_url_external && $include_files )
			{
				// add files
				$_storageServiceId = $_app->storage_service_id;
				$_container = $_app->storage_container;
				/** @var $_storage BaseFileSvc */
				if ( empty( $_storageServiceId ) )
				{
					$_service = Service::model()->find(
						'type_id = :type',
						array( ':type' => PlatformServiceTypes::LOCAL_FILE_STORAGE )
					);
					$_storageServiceId = ( $_service ) ? $_service->getPrimaryKey() : null;
					$_container = 'applications';
				}
				if ( empty( $_storageServiceId ) )
				{
					throw new InternalServerErrorException( "Can not find storage service identifier." );
				}

				$_storage = ServiceHandler::getServiceObjectById( $_storageServiceId );
				if ( !$_storage )
				{
					throw new InternalServerErrorException( "Can not find storage service by identifier '$_storageServiceId''." );
				}

				if ( empty( $_container ) )
				{
					if ( $_storage->containerExists( $_appApiName ) )
					{
						$_storage->getFolderAsZip( $_appApiName, '', $_zip, $_zipFileName, true );
					}
				}
				else
				{
					if ( $_storage->folderExists( $_container, $_appApiName ) )
					{
						$_storage->getFolderAsZip( $_container, $_appApiName, $_zip, $_zipFileName, true );
					}
				}
			}
			if ( $include_data )
			{
				// todo do we need to load data unfiltered
			}
			$_zip->close();

			$_fd = fopen( $_zipFileName, "r" );
			if ( $_fd )
			{
				$_pathParts = pathinfo( $_zipFileName );
				header( "Content-type: application/zip" );
				header( "Content-Disposition: filename=\"" . $_pathParts["basename"] . "\"" );
				header( "Content-length: " . filesize( $_zipFileName ) );
				header( "Cache-control: private" ); //use this to open files directly
				while ( !feof( $_fd ) )
				{
					$_buffer = fread( $_fd, 2048 );
					echo $_buffer;
				}
			}
			fclose( $_fd );
			unlink( $_zipFileName );

			return null;
		}
		catch ( \Exception $ex )
		{
			if ( !empty( $_zipFileName ) )
			{
				unlink( $_zipFileName );
			}

			throw $ex;
		}
	}

	/**
	 * @param string $pkg_file
	 * @param string $import_url
	 *
	 * @throws \Exception
	 * @return array
	 */
	public static function importAppFromPackage( $pkg_file, $import_url = '' )
	{
		$_zip = new \ZipArchive();
		if ( true !== $_zip->open( $pkg_file ) )
		{
			throw new InternalServerErrorException( 'Error opening zip file.' );
		}

		$_data = $_zip->getFromName( 'description.json' );
		if ( false === $_data )
		{
			throw new BadRequestException( 'No application description file in this package file.' );
		}

		$_record = DataFormat::jsonToArray( $_data );
		if ( !empty( $import_url ) && !isset( $_record['import_url'] ) )
		{
			$_record['import_url'] = $import_url;
		}
		$_storageServiceId = Option::get( $_record, 'storage_service_id' );
		$_container = Option::get( $_record, 'storage_container' );
		if ( empty( $_storageServiceId ) )
		{
			// must be set or defaulted to local
			$_model = Service::model()->find(
				'type_id = :type',
				array( ':type' => PlatformServiceTypes::LOCAL_FILE_STORAGE )
			);
			$_storageServiceId = ( $_model ) ? $_model->getPrimaryKey() : null;
			$_record['storage_service_id'] = $_storageServiceId;
			if ( empty( $_container ) )
			{
				$_container = 'applications';
				$_record['storage_container'] = $_container;
			}
		}
		try
		{
			ResourceStore::setResourceName( 'app' );
			$returnData = ResourceStore::insertOne( $_record, 'id,api_name' );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Could not create the application.\n{$ex->getMessage()}" );
		}

		$id = Option::get( $returnData, 'id' );
		$_zip->deleteName( 'description.json' );
		try
		{
			$_data = $_zip->getFromName( 'services.json' );
			if ( false !== $_data )
			{
				$_data = DataFormat::jsonToArray( $_data );
				try
				{
					//set service 'service',
					ResourceStore::setResourceName( 'service' );
					$result = ResourceStore::insert( $_data );
					if ( empty( $result ) )
					{
						// error nothing
					}
					// clear swagger cache upon any service changes.
					SwaggerManager::clearCache();
				}
				catch ( \Exception $ex )
				{
					throw new InternalServerErrorException( "Could not create the services.\n{$ex->getMessage()}" );
				}
				$_zip->deleteName( 'services.json' );
			}
			$_data = $_zip->getFromName( 'schema.json' );
			if ( false !== $_data )
			{
				$_data = DataFormat::jsonToArray( $_data );
				$services = Option::get( $_data, 'service' );
				if ( !empty( $services ) )
				{
					foreach ( $services as $schemas )
					{
						$serviceName = Option::get( $schemas, 'api_name' );
						$db = ServiceHandler::getServiceObject( $serviceName );
						$tables = Option::get( $schemas, 'table' );
						if ( !empty( $tables ) )
						{
							/** @var $db SchemaSvc */
							$result = $db->createTables( $tables, true );
							if ( isset( $result[0]['error'] ) )
							{
								$msg = $result[0]['error']['message'];
								throw new InternalServerErrorException( "Could not create the database tables for this application.\n$msg" );
							}
						}
					}
				}
				else
				{
					// single or multiple tables for one service
					$tables = Option::get( $_data, 'table' );
					if ( !empty( $tables ) )
					{
						$serviceName = Option::get( $_data, 'api_name' );
						if ( empty( $serviceName ) )
						{
							$serviceName = 'schema'; // for older packages
						}
						/** @var $db SchemaSvc */
						$db = ServiceHandler::getServiceObject( $serviceName );
						$result = $db->createTables( $tables, true );
						if ( isset( $result[0]['error'] ) )
						{
							$msg = $result[0]['error']['message'];
							throw new InternalServerErrorException( "Could not create the database tables for this application.\n$msg" );
						}
					}
					else
					{
						// single table with no wrappers - try default schema service
						$table = Option::get( $_data, 'name' );
						if ( !empty( $table ) )
						{
							$serviceName = 'schema';
							/** @var $db SchemaSvc */
							$db = ServiceHandler::getServiceObject( $serviceName );
							$result = $db->createTables( $_data, true );
							if ( isset( $result['error'] ) )
							{
								$msg = $result['error']['message'];
								throw new InternalServerErrorException( "Could not create the database tables for this application.\n$msg" );
							}
						}
					}
				}
				$_zip->deleteName( 'schema.json' );
			}

			$_data = $_zip->getFromName( 'data.json' );
			if ( false !== $_data )
			{
				$_data = DataFormat::jsonToArray( $_data );
				$services = Option::get( $_data, 'service' );
				if ( !empty( $services ) )
				{
					foreach ( $services as $service )
					{
						$serviceName = Option::get( $service, 'api_name' );

						/** @var BaseDbSvc $db */
						$db = ServiceHandler::getServiceObject( $serviceName );
						$tables = Option::get( $_data, 'table' );

						foreach ( $tables as $table )
						{
							$tableName = Option::get( $table, 'name' );
							$records = Option::get( $table, 'record' );

							$result = $db->createRecords( $tableName, $records );

							if ( isset( $result['record'][0]['error'] ) )
							{
								$msg = $result['record'][0]['error']['message'];
								throw new InternalServerErrorException( "Could not insert the database entries for table '$tableName'' for this application.\n$msg" );
							}
						}
					}
				}
				else
				{
					// single or multiple tables for one service
					$tables = Option::get( $_data, 'table' );
					if ( !empty( $tables ) )
					{
						$serviceName = Option::get( $_data, 'api_name' );
						if ( empty( $serviceName ) )
						{
							$serviceName = 'db'; // for older packages
						}
						$db = ServiceHandler::getServiceObject( $serviceName );
						foreach ( $tables as $table )
						{
							$tableName = Option::get( $table, 'name' );
							$records = Option::get( $table, 'record' );
							/** @var $db BaseDbSvc */
							$result = $db->createRecords( $tableName, $records );
							if ( isset( $result['record'][0]['error'] ) )
							{
								$msg = $result['record'][0]['error']['message'];
								throw new InternalServerErrorException( "Could not insert the database entries for table '$tableName'' for this application.\n$msg" );
							}
						}
					}
					else
					{
						// single table with no wrappers - try default database service
						$tableName = Option::get( $_data, 'name' );
						if ( !empty( $tableName ) )
						{
							$serviceName = 'db';
							$db = ServiceHandler::getServiceObject( $serviceName );
							$records = Option::get( $_data, 'record' );
							/** @var $db BaseDbSvc */
							$result = $db->createRecords( $tableName, $records );
							if ( isset( $result['record'][0]['error'] ) )
							{
								$msg = $result['record'][0]['error']['message'];
								throw new InternalServerErrorException( "Could not insert the database entries for table '$tableName'' for this application.\n$msg" );
							}
						}
					}
				}
				$_zip->deleteName( 'data.json' );
			}
		}
		catch ( \Exception $ex )
		{
			// delete db record
			// todo anyone else using schema created?
			if ( !empty( $id ) )
			{
				ResourceStore::setResourceName( 'app' );
				ResourceStore::delete( array( 'id' => $id ) );
			}
			throw $ex;
		}

		// extract the rest of the zip file into storage
		$_apiName = Option::get( $_record, 'api_name' );
		/** @var $_service BaseFileSvc */
		$_service = ServiceHandler::getServiceObjectById( $_storageServiceId );
		if ( empty( $_service ) )
		{
			throw new InternalServerErrorException( "App record created, but failed to import files due to unknown storage service with id '$_storageServiceId'." );
		}
		if ( empty( $_container ) )
		{
			$_service->extractZipFile( $_apiName, '', $_zip, false, $_apiName . '/' );
		}
		else
		{
			$_service->extractZipFile( $_container, '', $_zip );
		}

		return $returnData;
	}
}
