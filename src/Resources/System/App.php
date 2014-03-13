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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Services\BaseFileSvc;
use DreamFactory\Platform\Utility\FileUtilities;
use DreamFactory\Platform\Utility\Packager;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\ServiceHandler;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * App
 * DSP system administration manager
 *
 */
class App extends BaseSystemRestResource
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Creates a new SystemResource instance
	 *
	 */
	public function __construct( $consumer, $resources = array() )
	{
		$_config = array(
			'service_name' => 'system',
			'name'         => 'Application',
			'api_name'     => 'app',
			'type'         => 'System',
			'type_id'      => PlatformServiceTypes::SYSTEM_SERVICE,
			'description'  => 'System application administration.',
			'is_active'    => true,
		);

		parent::__construct( $consumer, $_config, $resources );
	}

	/**
	 * @throws \Exception
	 * @return array|bool
	 */
	protected function _handleGet()
	{
		if ( !empty( $this->_resourceId ) )
		{
			//	Export the app as a package file
			if ( Option::getBool( $_REQUEST, 'pkg' ) )
			{
				$_includeFiles = Option::getBool( $_REQUEST, 'include_files' );
				$_includeServices = Option::getBool( $_REQUEST, 'include_services' );
				$_includeSchema = Option::getBool( $_REQUEST, 'include_schema' );
				$_includeData = Option::getBool( $_REQUEST, 'include_data' );

				$this->checkPermission( 'admin', $this->_resource );

				return Packager::exportAppAsPackage( $this->_resourceId, $_includeFiles, $_includeServices, $_includeSchema, $_includeData );
			}

			// Export the sdk amended for this app
			if ( Option::getBool( $_REQUEST, 'sdk' ) )
			{
				$this->checkPermission( 'read', $this->_resource );

				return static::exportAppSDK( $this->_resourceId );
			}
		}

		return parent::_handleGet();
	}

	/**
	 * @return array|bool
	 * @throws \Exception
	 */
	protected function _handlePost()
	{
		//	You can import an application package file, local or remote, but nothing else
		$_importUrl = FilterInput::request( 'url' );
		if ( !empty( $_importUrl ) )
		{
			$this->checkPermission( 'admin', $this->_resource );

			$_extension = strtolower( pathinfo( $_importUrl, PATHINFO_EXTENSION ) );
			if ( 'dfpkg' == $_extension )
			{
				$_filename = null;
				try
				{
					// need to download and extract zip file and move contents to storage
					$_filename = FileUtilities::importUrlFileToTemp( $_importUrl );
					$_results = Packager::importAppFromPackage( $_filename, $_importUrl );
				}
				catch ( \Exception $ex )
				{
					if ( !empty( $_filename ) )
					{
						unlink( $_filename );
					}

					throw new InternalServerErrorException( "Failed to import application package $_importUrl.\n{$ex->getMessage()}" );
				}

				if ( !empty( $_filename ) )
				{
					unlink( $_filename );
				}
			}
			else
			{
				throw new BadRequestException( "Only application package files ending with 'dfpkg' are allowed for import." );
			}
		}
		elseif ( null !== ( $_files = Option::get( $_FILES, 'files' ) ) )
		{
			//	Older html multi-part/form-data post, single or multiple files
			if ( is_array( $_files['error'] ) )
			{
				throw new \Exception( "Only a single application package file is allowed for import." );
			}

			if ( UPLOAD_ERR_OK !== ( $_error = $_files['error'] ) )
			{
				throw new InternalServerErrorException( 'Failed to receive upload of "' . $_files['name'] . '": ' . $_error );
			}

			$this->checkPermission( 'admin', $this->_resource );

			$_filename = $_files['tmp_name'];
			$_extension = strtolower( pathinfo( $_files['name'], PATHINFO_EXTENSION ) );
			if ( 'dfpkg' == $_extension )
			{
				try
				{
					$_results = Packager::importAppFromPackage( $_filename );
				}
				catch ( \Exception $ex )
				{
					throw new InternalServerErrorException( "Failed to import application package " . $_files['name'] . "\n{$ex->getMessage()}" );
				}
			}
			else
			{
				throw new BadRequestException( "Only application package files ending with 'dfpkg' are allowed for import." );
			}
		}
		else
		{
			$_results = parent::_handlePost();
		}

		$_records = Option::get( $_results, 'record' );
		if ( empty( $_records ) )
		{
			static::initHostedAppStorage( $_results );
		}
		else
		{
			foreach ( $_records as $_record )
			{
				static::initHostedAppStorage( $_record );
			}
		}

		return $_results;
	}

	/**
	 * Default PUT implementation
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return bool
	 */
	protected function _handlePut()
	{
		$_results = parent::_handlePut();

		//@todo may need to create storage or remove it
		return $_results;
	}

	/**
	 * Default DELETE implementation
	 *
	 * @return bool|void
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _handleDelete()
	{
		$_results = parent::_handleDelete();

		if ( Option::getBool( $_REQUEST, 'delete_storage' ) )
		{
			$_records = Option::get( $_results, 'record' );
			if ( empty( $_records ) )
			{
				static::deleteHostedAppStorage( $_results );
			}
			else
			{
				foreach ( $_records as $_record )
				{
					static::deleteHostedAppStorage( $_record );
				}
			}
		}

		return $_results;
	}

	/**
	 * @param array $record
	 *
	 * @throws \Exception
	 */
	public static function initHostedAppStorage( $record )
	{
		// create storage for all new apps where storage service is set
		$_apiName = Option::get( $record, 'api_name' );
		$_storageServiceId = Option::get( $record, 'storage_service_id' );
		if ( empty( $_apiName ) || empty( $_storageServiceId ) )
		{
			// not necessary
			return;
		}

		/** @var BaseFileSvc $_service */
		$_service = ServiceHandler::getService( $_storageServiceId );
		$_container = Option::get( $record, 'storage_container' );
		$_rootFolder = null;
		if ( empty( $_container ) )
		{
			$_container = $_apiName;
		}
		else
		{
			$_rootFolder = $_apiName;
		}
		if ( !$_service->containerExists( $_container ) )
		{
			$_service->createContainer( array( 'name' => $_container ) );
		}
		else
		{
			if ( empty( $_rootFolder ) )
			{
				return; // app directory has already been created
			}
		}
		if ( !empty( $_rootFolder ) )
		{
			if ( !$_service->folderExists( $_container, $_rootFolder ) )
			{
				// create in permanent storage
				$_service->createFolder( $_container, $_rootFolder );
			}
			else
			{
				return; // app directory has already been created
			}
		}

		$_templateBaseDir = \Kisma::get( 'app.vendor_path' ) . '/dreamfactory/javascript-sdk';
		if ( is_dir( $_templateBaseDir ) )
		{
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
						$_storePath = ( empty( $_rootFolder ) ? : $_rootFolder . '/' ) . $_file;
						$_service->createFolder( $_container, $_storePath );
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
									$_content = file_get_contents( $_templateSubPath );
									if ( 'sdk-init.js' == $_subFile )
									{
										$_dspHost = Curl::currentUrl( false, false );
										$_content = str_replace( 'https://_your_dsp_hostname_here_', $_dspHost, $_content );
										$_content = str_replace( '_your_app_api_name_here_', $_apiName, $_content );
									}
									$_service->writeFile( $_container, $_storePath . '/' . $_subFile, $_content, false );
								}
							}
						}
					}
					else if ( file_exists( $_templatePath ) )
					{
						$_content = file_get_contents( $_templatePath );
						$_storePath = ( empty( $_rootFolder ) ? : $_rootFolder . '/' ) . $_file;
						$_service->writeFile( $_container, $_storePath, $_content, false );
					}
				}
			}
		}
	}

	/**
	 * @param array $record
	 *
	 * @throws \Exception
	 */
	public static function deleteHostedAppStorage( $record )
	{
		// create storage for all new apps where storage service is set
		$_apiName = Option::get( $record, 'api_name' );
		$_storageServiceId = Option::get( $record, 'storage_service_id' );
		if ( empty( $_apiName ) || empty( $_storageServiceId ) )
		{
			// not necessary
			return;
		}

		/** @var BaseFileSvc $_service */
		$_service = ServiceHandler::getService( $_storageServiceId );
		$_container = Option::get( $record, 'storage_container' );
		if ( empty( $_container ) )
		{
			if ( $_service->containerExists( $_apiName ) )
			{
				// delete from permanent storage
				$_service->deleteContainer( $_apiName, true );
			}
		}
		else
		{
			if ( $_service->containerExists( $_container ) )
			{
				if ( $_service->folderExists( $_container, $_apiName ) )
				{
					// delete from permanent storage
					$_service->deleteFolder( $_container, $_apiName, true );
				}
			}
		}
	}

	/**
	 * @param string $app_id
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \Exception
	 * @internal param bool $include_files
	 *
	 * @return null
	 */
	public static function exportAppSDK( $app_id )
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
										$_dspHost = Curl::currentUrl( false, false );
										$_content = str_replace( 'https://_your_dsp_hostname_here_', $_dspHost, $_content );
										$_content = str_replace( '_your_app_api_name_here_', $_apiName, $_content );
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
