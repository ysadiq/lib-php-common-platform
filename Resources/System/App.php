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
	public function __construct( $consumer, $resourceArray = array() )
	{
		parent::__construct(
			$consumer,
			array(
				 'service_name'   => 'system',
				 'name'           => 'Application',
				 'api_name'       => 'app',
				 'type'           => 'System',
				 'description'    => 'System application administration.',
				 'is_active'      => true,
				 'resource_array' => $resourceArray,
			)
		);
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

			//@TODO What permissions to check?
			//$this->checkPermission( 'read' );

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
		//@TODO What permissions to check?
		//$this->checkPermission( 'create' );

		//	You can import an application package file, local or remote, or from zip, but nothing else
		$_name = FilterInput::request( 'name' );
		$_importUrl = FilterInput::request( 'url' );
		$_extension = strtolower( pathinfo( $_importUrl, PATHINFO_EXTENSION ) );

		if ( null !== ( $_files = Option::get( $_FILES, 'files' ) ) )
		{
			//	Older html multi-part/form-data post, single or multiple files
			if ( is_array( $_files['error'] ) )
			{
				throw new \Exception( "Only a single application package file is allowed for import." );
			}

			$_importUrl = 'file://' . $_files['tmp_name'] . '#' . $_files['name'] . '#' . $_files['type'];

			if ( UPLOAD_ERR_OK !== ( $_error = $_files['error'] ) )
			{
				throw new \Exception( 'Failed to receive upload of "' . $_files['name'] . '": ' . $_error );
			}
		}

		if ( !empty( $_importUrl ) )
		{
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

			// from repo or remote zip file
			if ( !empty( $_name ) && 'zip' == $_extension )
			{
				// need to download and extract zip file and move contents to storage
				$_filename = FileSystem::importUrlFileToTemp( $_importUrl );

				try
				{
					//@todo save url for later updates
					return Packager::importAppFromZip( $_name, $_filename );
				}
				catch ( \Exception $ex )
				{
					throw new \Exception( "Failed to import application package $_importUrl.\n{$ex->getMessage()}" );
				}
			}
		}

		return parent::_handlePost();
	}
}
