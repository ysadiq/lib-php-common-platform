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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Utility\FileSystem;
use DreamFactory\Platform\Utility\Packager;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
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
	 *
	 */
	public function __construct( $consumer, $resources = array() )
	{
		$_config = array(
			'service_name'   => 'system',
			'name'           => 'Application',
			'api_name'       => 'app',
			'type'           => 'System',
			'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
			'description'    => 'System application administration.',
			'is_active'      => true,
		);

		parent::__construct( $consumer, $_config, $resources );
	}

	/**
	 * @throws \Exception
	 * @return array|bool
	 */
	protected function _handleGet()
	{
		if ( false !== $this->_exportPackage && !empty( $this->_resourceId ) )
		{
			$_includeFiles = Option::getBool( $_REQUEST, 'include_files' );
			$_includeServices = Option::getBool( $_REQUEST, 'include_services' );
			$_includeSchema = Option::getBool( $_REQUEST, 'include_schema' );
			$_includeData = Option::getBool( $_REQUEST, 'include_data' );

			$this->checkPermission( 'admin', $this->_resource );

			return Packager::exportAppAsPackage( $this->_resourceId, $_includeFiles, $_includeServices, $_includeSchema, $_includeData );
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
				// need to download and extract zip file and move contents to storage
				$_filename = FileSystem::importUrlFileToTemp( $_importUrl );

				try
				{
					return Packager::importAppFromPackage( $_filename, $_importUrl );
				}
				catch ( \Exception $ex )
				{
					throw new \Exception( "Failed to import application package $_importUrl.\n{$ex->getMessage()}" );
				}
			}

			throw new BadRequestException( "Only application package files ending with 'dfpkg' are allowed for import." );
		}

		if ( null !== ( $_files = Option::get( $_FILES, 'files' ) ) )
		{
			//	Older html multi-part/form-data post, single or multiple files
			if ( is_array( $_files['error'] ) )
			{
				throw new \Exception( "Only a single application package file is allowed for import." );
			}

			if ( UPLOAD_ERR_OK !== ( $_error = $_files['error'] ) )
			{
				throw new \Exception( 'Failed to receive upload of "' . $_files['name'] . '": ' . $_error );
			}

			$this->checkPermission( 'admin', $this->_resource );

			$_filename = $_files['tmp_name'];
			$_extension = strtolower( pathinfo( $_files['name'], PATHINFO_EXTENSION ) );
			if ( 'dfpkg' == $_extension )
			{
				try
				{
					return Packager::importAppFromPackage( $_filename );
				}
				catch ( \Exception $ex )
				{
					throw new \Exception( "Failed to import application package " . $_files['name'] . "\n{$ex->getMessage()}" );
				}
			}

			throw new BadRequestException( "Only application package files ending with 'dfpkg' are allowed for import." );
		}

		return parent::_handlePost();
	}
}
