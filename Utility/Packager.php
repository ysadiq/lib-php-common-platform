<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Services\BaseDbSvc;
use DreamFactory\Platform\Services\BaseFileSvc;
use DreamFactory\Platform\Services\SchemaSvc;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Platform\Utility\FileSystem;
use DreamFactory\Platform\Utility\FileUtilities;
use DreamFactory\Platform\Utility\ServiceHandler;
use DreamFactory\Platform\Yii\Models\App;
use DreamFactory\Platform\Yii\Models\Service;
use Kisma\Core\SeedUtility;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;

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
		$model = ResourceStore::model( 'app' );

		if ( $include_services || $include_schema )
		{
			$model->with( 'app_service_relations.service' );
		}

		$app = $model->findByPk( $app_id );

		if ( null === $app )
		{
			throw new NotFoundException( "No database entry exists for this application with id '$app_id'." );
		}

		$fields = array(
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
		$record = $app->getAttributes( $fields );
		$app_root = Option::get( $record, 'api_name' );

		try
		{
			$zip = new \ZipArchive();
			$tempDir = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
			$zipFileName = $tempDir . $app_root . '.dfpkg';
			if ( true !== $zip->open( $zipFileName, \ZipArchive::CREATE ) )
			{
				throw new InternalServerErrorException( 'Can not create package file for this application.' );
			}

			// add database entry file
			if ( !$zip->addFromString( 'description.json', json_encode( $record ) ) )
			{
				throw new InternalServerErrorException( "Can not include description in package file." );
			}
			if ( $include_services || $include_schema )
			{
				/**
				 * @var Service[] $serviceRelations
				 */
				$serviceRelations = $app->getRelated( 'app_service_relations' );
				if ( !empty( $serviceRelations ) )
				{
					$services = array();
					$schemas = array();
					$serviceFields = array(
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
					foreach ( $serviceRelations as $relation )
					{
						/** @var Service $service */
						$service = $relation->getRelated( 'service' );
						if ( !empty( $service ) )
						{
							if ( $include_services )
							{
								if ( !DataFormat::boolval( $service->getAttribute( 'is_system' ) ) )
								{
									// get service details to restore with app
									$temp = $service->getAttributes( $serviceFields );
									$services[] = $temp;
								}
							}
							if ( $include_schema )
							{
								$component = $relation->getAttribute( 'component' );
								if ( !empty( $component ) )
								{
									$component = json_decode( $component, true );
									// service is probably a db, export table schema if possible
									$serviceName = $service->getAttribute( 'api_name' );
									$serviceType = $service->getAttribute( 'type' );
									switch ( strtolower( $serviceType ) )
									{
										case 'local sql db schema':
										case 'remote sql db schema':
											/** @var $db SchemaSvc */
											$db = ServiceHandler::getServiceObject( $serviceName );
											$describe = $db->describeTables( implode( ',', $component ) );
											$temp = array(
												'api_name' => $serviceName,
												'table'    => $describe
											);
											$schemas[] = $temp;
											break;
									}
								}
							}
						}
					}
					if ( !empty( $services ) && !$zip->addFromString( 'services.json', json_encode( $services ) ) )
					{
						throw new InternalServerErrorException( "Can not include services in package file." );
					}
					if ( !empty( $schemas ) && !$zip->addFromString( 'schema.json', json_encode( array( 'service' => $schemas ) ) ) )
					{
						throw new InternalServerErrorException( "Can not include database schema in package file." );
					}
				}
			}
			$isExternal = DataFormat::boolval( Option::get( $record, 'is_url_external', false ) );
			if ( !$isExternal && $include_files )
			{
				// add files
				$_storageServiceId = Option::get( $record, 'storage_service_id' );
				/** @var $_service BaseFileSvc */
				if ( empty( $_storageServiceId ) )
				{
					$_service = ServiceHandler::getServiceObject( 'app' );
					$_container = 'applications';
				}
				else
				{
					$_service = ServiceHandler::getServiceObjectById( $_storageServiceId );
					$_container = Option::get( $record, 'storage_container' );
				}
				if ( empty( $_container ) )
				{
					if ( $_service->containerExists( $app_root ) )
					{
						$_service->getFolderAsZip( $app_root, '', $zip, $zipFileName, true );
					}
				}
				else
				{
					if ( $_service->folderExists( $_container, $app_root ) )
					{
						$_service->getFolderAsZip( $_container, $app_root, $zip, $zipFileName, true );
					}
				}
			}
			$zip->close();

			$fd = fopen( $zipFileName, "r" );
			if ( $fd )
			{
				$fsize = filesize( $zipFileName );
				$path_parts = pathinfo( $zipFileName );
				header( "Content-type: application/zip" );
				header( "Content-Disposition: filename=\"" . $path_parts["basename"] . "\"" );
				header( "Content-length: $fsize" );
				header( "Cache-control: private" ); //use this to open files directly
				while ( !feof( $fd ) )
				{
					$buffer = fread( $fd, 2048 );
					echo $buffer;
				}
			}
			fclose( $fd );
			unlink( $zipFileName );

			return null;
		}
		catch ( \Exception $ex )
		{
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
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $pkg_file ) )
		{
			throw new InternalServerErrorException( 'Error opening zip file.' );
		}

		$data = $zip->getFromName( 'description.json' );
		if ( false === $data )
		{
			throw new BadRequestException( 'No application description file in this package file.' );
		}

		$record = DataFormat::jsonToArray( $data );
		if ( !empty( $import_url ) && !isset( $record['import_url'] ) )
		{
			$record['import_url'] = $import_url;
		}
		$_storageServiceId = Option::get( $record, 'storage_service_id' );
		$_container = Option::get( $record, 'storage_container' );
		if ( empty( $_storageServiceId ) )
		{
			// must be set or defaulted to local
			$_model = Service::model()->find( 'api_name = :api_name', array( ':api_name' => 'app' ) );
			$_storageServiceId = ( $_model ) ? $_model->getPrimaryKey() : null;
			$record['storage_service_id'] = $_storageServiceId;
			if ( empty( $_container ) )
			{
				$_container = 'applications';
				$record['storage_container'] = $_container;
			}
		}
		try
		{
			ResourceStore::setResourceName( 'app' );
			$returnData = ResourceStore::insert( $record, 'id,api_name' );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Could not create the application.\n{$ex->getMessage()}" );
		}
		$id = Option::get( $returnData, 'id' );
		$zip->deleteName( 'description.json' );
		try
		{
			$data = $zip->getFromName( 'services.json' );
			if ( false !== $data )
			{
				$data = DataFormat::jsonToArray( $data );
				try
				{
					//set service 'service',
					ResourceStore::setResourceName( 'service' );
					$result = ResourceStore::insert( $data );
					// clear swagger cache upon any service changes.
					SwaggerManager::clearCache();
				}
				catch ( \Exception $ex )
				{
					throw new InternalServerErrorException( "Could not create the services.\n{$ex->getMessage()}" );
				}
				$zip->deleteName( 'services.json' );
			}
			$data = $zip->getFromName( 'schema.json' );
			if ( false !== $data )
			{
				$data = DataFormat::jsonToArray( $data );
				$services = Option::get( $data, 'service' );
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
					$tables = Option::get( $data, 'table' );
					if ( !empty( $tables ) )
					{
						$serviceName = Option::get( $data, 'api_name' );
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
						$table = Option::get( $data, 'name' );
						if ( !empty( $table ) )
						{
							$serviceName = 'schema';
							/** @var $db SchemaSvc */
							$db = ServiceHandler::getServiceObject( $serviceName );
							$result = $db->createTables( $data, true );
							if ( isset( $result['error'] ) )
							{
								$msg = $result['error']['message'];
								throw new InternalServerErrorException( "Could not create the database tables for this application.\n$msg" );
							}
						}
					}
				}
				$zip->deleteName( 'schema.json' );
			}

			$data = $zip->getFromName( 'data.json' );
			if ( false !== $data )
			{
				$data = DataFormat::jsonToArray( $data );
				$services = Option::get( $data, 'service' );
				if ( !empty( $services ) )
				{
					foreach ( $services as $service )
					{
						$serviceName = Option::get( $service, 'api_name' );

						/** @var BaseDbSvc $db */
						$db = ServiceHandler::getServiceObject( $serviceName );
						$tables = Option::get( $data, 'table' );

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
					$tables = Option::get( $data, 'table' );
					if ( !empty( $tables ) )
					{
						$serviceName = Option::get( $data, 'api_name' );
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
						$tableName = Option::get( $data, 'name' );
						if ( !empty( $tableName ) )
						{
							$serviceName = 'db';
							$db = ServiceHandler::getServiceObject( $serviceName );
							$records = Option::get( $data, 'record' );
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
				$zip->deleteName( 'data.json' );
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
		$_apiName = Option::get( $record, 'api_name' );
		/** @var $_service BaseFileSvc */
		$_service = ServiceHandler::getServiceObjectById( $_storageServiceId );
		if ( empty( $_service ) )
		{
			throw new InternalServerErrorException( "App record created, but failed to import files due to unknown storage service with id '$_storageServiceId'." );
		}
		if ( empty( $_container ) )
		{
			$_service->extractZipFile( $_apiName, '', $zip, false, $_apiName . '/' );
		}
		else
		{
			$_service->extractZipFile( $_container, '', $zip );
		}

		return $returnData;
	}

	/**
	 * @param string $app_id
	 * @param bool   $include_files
	 *
	 * @throws \Exception
	 * @return null
	 */
	public static function exportAppAsSDK( $app_id )
	{
		$_model = ResourceStore::model( 'app' );

		$_app = $_model->findByPk( $app_id );

		if ( null === $_app )
		{
			throw new NotFoundException( "No database entry exists for this application with id '$app_id'." );
		}

		$_record = $_app->getAttributes( array( 'api_name', 'name' ) );
		$_apiName = Option::get( $_record, 'api_name' );

		try
		{
			$_zip = new \ZipArchive();
			$_tempDir = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
			$_zipFileName = $_tempDir . $_apiName . '.zip';
			if ( true !== $_zip->open( $_zipFileName, \ZipArchive::CREATE ) )
			{
				throw new InternalServerErrorException( 'Can not create sdk zip file for this application.' );
			}

			$_templateBaseDir = \Kisma::get( 'app.vendor_path' ) . '/dreamfactory/javascript-sdk';
			if ( !is_dir( $_templateBaseDir ) )
			{
				throw new InternalServerErrorException( 'Bad path to sdk template.' );
			}

			$_files = array_diff(
				scandir( $_templateBaseDir ),
				array( '.', '..', '.gitignore', 'composer.json', 'README.md' )
			);
			if ( !empty( $_files ) )
			{
				foreach ( $_files as $_file )
				{
					$_templatePath = $_templateBaseDir . '/' . $_file;
					if ( is_dir( $_templatePath ) )
					{
						$_subFiles = array_diff( scandir( $_templatePath ), array( '.', '..' ) );
						if ( !empty( $_subFiles ) )
						{
							foreach ( $_subFiles as $_subFile )
							{
								$_templateSubPath = $_templatePath . '/' . $_subFile;
								if ( is_dir( $_templateSubPath ) )
								{
									// support this deep?
								}
								else if ( file_exists( $_templateSubPath ) )
								{
									if ( 'sdk-init.js' == $_subFile )
									{
										$_content = file_get_contents( $_templateSubPath );
										$_protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ) ? 'https' : 'http';
										$_dspHost = $_protocol . '://' . FilterInput::server( 'HTTP_HOST' );
										$_content = str_replace('https://_your_dsp_hostname_here_', $_dspHost, $_content );
										$_content = str_replace('_your_app_api_name_here_', $_apiName, $_content );
										$_zip->addFromString( $_file . '/' . $_subFile, $_content );
									}
									else
									{
										$_zip->addFile( $_templateSubPath, $_file . '/' . $_subFile );
									}
								}
							}
						}
						else
						{
							$_zip->addEmptyDir( $_file );
						}
					}
					else if ( file_exists( $_templatePath ) )
					{
						$_zip->addFile( $_templatePath, $_file );
					}
				}
			}

			$_zip->close();

			$fd = fopen( $_zipFileName, "r" );
			if ( $fd )
			{
				$fsize = filesize( $_zipFileName );
				$path_parts = pathinfo( $_zipFileName );
				header( "Content-type: application/zip" );
				header( "Content-Disposition: filename=\"" . $path_parts["basename"] . "\"" );
				header( "Content-length: $fsize" );
				header( "Cache-control: private" ); //use this to open files directly
				while ( !feof( $fd ) )
				{
					$buffer = fread( $fd, 2048 );
					echo $buffer;
				}
			}
			fclose( $fd );
			unlink( $_zipFileName );

			return null;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}
}
