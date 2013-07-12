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

use DreamFactory\Platform\Interfaces\PlatformServiceLike;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Utility\FileSystem;
use DreamFactory\Platform\Utility\Packager;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Swagger\Annotations as SWG;

/**
 * App
 * DSP system administration manager
 *
 * @SWG\Resource(
 *   resourcePath="/system"
 * )
 *
 * @SWG\Model(id="Apps",
 * @SWG\Property(name="record",type="Array",items="$ref:App",description="Array of system application records.")
 * )
 * @SWG\Model(id="App",
 * @SWG\Property(name="id",type="int",description="Identifier of this application."),
 * @SWG\Property(name="name",type="string",description="Displayable name of this application."),
 * @SWG\Property(name="api_name",type="string",description="Name of the application to use in API transactions."),
 * @SWG\Property(name="description",type="string",description="Description of this application."),
 * @SWG\Property(name="is_active",type="boolean",description="Is this system application active for use."),
 * @SWG\Property(name="url",type="string",description="URL for accessing this application."),
 * @SWG\Property(name="is_url_external",type="boolean",description="True when this application is hosted elsewhere."),
 * @SWG\Property(name="imported_url",type="string",description="If imported, the url of where the code originated."),
 * @SWG\Property(name="storage_service_id",type="string",description="If locally stored, the storage service identifier."),
 * @SWG\Property(name="storage_container",type="string",description="If locally stored, the container of the storage service."),
 * @SWG\Property(name="requires_fullscreen",type="boolean",description="True when this app needs to hide launchpad."),
 * @SWG\Property(name="allow_fullscreen_toggle",type="boolean",description="True to allow launchpad access via toggle."),
 * @SWG\Property(name="toggle_location",type="string",description="Screen location for toggle placement."),
 * @SWG\Property(name="requires_plugin",type="boolean",description="True when the app relies on a browser plugin."),
 * @SWG\Property(name="roles_default_app",type="Array",items="$ref:string",description="Related roles by Role.default_app_id."),
 * @SWG\Property(name="users_default_app",type="Array",items="$ref:string",description="Related users by User.default_app_id."),
 * @SWG\Property(name="app_groups",type="Array",items="$ref:string",description="Related groups by app to group assignment."),
 * @SWG\Property(name="roles",type="Array",items="$ref:string",description="Related roles by app to role assignment."),
 * @SWG\Property(name="services",type="Array",items="$ref:string",description="Related services by app to service assignment."),
 * @SWG\Property(name="created_date",type="string",description="Date this application was created."),
 * @SWG\Property(name="created_by_id",type="int",description="User Id of who created this application."),
 * @SWG\Property(name="last_modified_date",type="string",description="Date this application was last modified."),
 * @SWG\Property(name="last_modified_by_id",type="int",description="User Id of who last modified this application.")
 * )
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
	 *
	 * @SWG\Api(
	 *             path="/system/app", description="Operations for application administration.",
	 * @SWG\Operations(
	 * @SWG\Operation(
	 *             httpMethod="GET", summary="Retrieve multiple applications.",
	 *             notes="Use the 'ids' or 'filter' parameter to limit records that are returned. Use the 'fields' and 'related' parameters to limit properties returned for each record. By default, all fields and no relations are returned for all records.",
	 *             responseClass="Apps", nickname="getApps",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="ids", description="Comma-delimited list of the identifiers of the records to retrieve.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="filter", description="SQL-like filter to limit the records to retrieve.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="limit", description="Set to limit the filter results.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="int"
	 *           ),
	 * @SWG\Parameter(
	 *             name="order", description="SQL-like order containing field and direction for filter results.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="offset", description="Set to offset the filter results to a particular record count.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="int"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="include_count", description="Include the total number of filter results.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="boolean"
	 *           ),
	 * @SWG\Parameter(
	 *             name="include_schema", description="Include the schema of the table queried.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="boolean"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       ),
	 * @SWG\Operation(
	 *             httpMethod="POST", summary="Create one or more applications.",
	 *             notes="Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use 'fields' and 'related' to return more info.",
	 *             responseClass="Success", nickname="createApps",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="record", description="Data containing name-value pairs of records to create.",
	 *             paramType="body", required="true", allowMultiple=false, dataType="Apps"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       ),
	 * @SWG\Operation(
	 *             httpMethod="PUT", summary="Update one or more applications.",
	 *             notes="Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use 'fields' and 'related' to return more info.",
	 *             responseClass="Success", nickname="updateApps",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="record", description="Data containing name-value pairs of records to update.",
	 *             paramType="body", required="true", allowMultiple=false, dataType="Apps"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       ),
	 * @SWG\Operation(
	 *             httpMethod="DELETE", summary="Delete one or more applications.",
	 *             notes="Use 'ids' or post data should be a single record or an array of records (shown) containing an id. By default, only the id property of the record is returned on success, use 'fields' and 'related' to return more info.",
	 *             responseClass="Success", nickname="deleteApps",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="ids", description="Comma-delimited list of the identifiers of the records to retrieve.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="record", description="Data containing name-value pairs of records to delete.",
	 *             paramType="body", required="false", allowMultiple=false, dataType="Apps"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       )
	 *     )
	 *   )
	 *
	 * @SWG\Api(
	 *             path="/system/app/{id}", description="Operations for individual application administration.",
	 * @SWG\Operations(
	 * @SWG\Operation(
	 *             httpMethod="GET", summary="Retrieve one application by identifier.",
	 *             notes="Use the 'fields' and/or 'related' parameter to limit properties that are returned. By default, all fields and no relations are returned.",
	 *             responseClass="App", nickname="getApp",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="id", description="Identifier of the record to retrieve.",
	 *             paramType="path", required="true", allowMultiple=false, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       ),
	 * @SWG\Operation(
	 *             httpMethod="PUT", summary="Update one application.",
	 *             notes="Post data should be an array of fields for a single record. Use the 'fields' and/or 'related' parameter to return more properties. By default, the id is returned.",
	 *             responseClass="Success", nickname="updateApp",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="id", description="Identifier of the record to retrieve.",
	 *             paramType="path", required="true", allowMultiple=false, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="record", description="Data containing name-value pairs of records to update.",
	 *             paramType="body", required="true", allowMultiple=false, dataType="App"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       ),
	 * @SWG\Operation(
	 *             httpMethod="DELETE", summary="Delete one application.",
	 *             notes="Use the 'fields' and/or 'related' parameter to return deleted properties. By default, the id is returned.",
	 *             responseClass="Success", nickname="deleteApp",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="id", description="Identifier of the record to retrieve.",
	 *             paramType="path", required="true", allowMultiple=false, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       )
	 *     )
	 *   )
	 *
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
				$_filename = FileSystem::importUrlFileToTemp( $_packageUrl );

				try
				{
					return Packager::importAppFromPackage( $_filename, $_packageUrl );
				}
				catch ( \Exception $ex )
				{
					throw new \Exception( "Failed to import application package $_packageUrl.\n{$ex->getMessage()}" );
				}
			}

			// from repo or remote zip file
			if ( !empty( $_name ) && 'zip' == $_extension )
			{
				// need to download and extract zip file and move contents to storage
				$_filename = FileSystem::importUrlFileToTemp( $_packageUrl );

				try
				{
					return Packager::importAppFromZip( $_name, $_filename );
					// todo save url for later updates
				}
				catch ( \Exception $ex )
				{
					throw new \Exception( "Failed to import application package $_packageUrl.\n{$ex->getMessage()}" );
				}
			}
		}

		return parent::_handlePost();
	}
}
