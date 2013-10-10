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

$_base = array();

$_base['apis'] = array(
	array(
		'path'        => '/{api_name}/app',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve multiple applications.',
				'nickname'         => 'getApps',
				'type'             => 'Apps',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'ids',
						'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					1 =>
					array(
						'name'          => 'filter',
						'description'   => 'SQL-like filter to limit the records to retrieve.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					2 =>
					array(
						'name'          => 'limit',
						'description'   => 'Set to limit the filter results.',
						'allowMultiple' => false,
						'type'          => 'integer',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'order',
						'description'   => 'SQL-like order containing field and direction for filter results.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					4 =>
					array(
						'name'          => 'offset',
						'description'   => 'Set to offset the filter results to a particular record count.',
						'allowMultiple' => false,
						'type'          => 'integer',
						'paramType'     => 'query',
						'required'      => false,
					),
					5 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					6 =>
					array(
						'name'          => 'related',
						'description'   => 'Comma-delimited list of related names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					7 =>
					array(
						'name'          => 'include_count',
						'description'   => 'Include the total number of filter results.',
						'allowMultiple' => false,
						'type'          => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
					),
					8 =>
					array(
						'name'          => 'include_schema',
						'description'   => 'Include the schema of the table queried.',
						'allowMultiple' => false,
						'type'          => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					1 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					2 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Use the \'ids\' or \'filter\' parameter to limit records that are returned. Use the \'fields\' and \'related\' parameters to limit properties returned for each record. By default, all fields and no relations are returned for all records.',
			),
			1 =>
			array(
				'method'           => 'POST',
				'summary'          => 'Create one or more applications.',
				'nickname'         => 'createApps',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'type'          => 'Apps',
						'paramType'     => 'body',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					2 =>
					array(
						'name'          => 'related',
						'description'   => 'Comma-delimited list of related names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					1 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					2 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
			),
			2 =>
			array(
				'method'           => 'PUT',
				'summary'          => 'Update one or more applications.',
				'nickname'         => 'updateApps',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'Apps',
						'paramType'     => 'body',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					2 =>
					array(
						'name'          => 'related',
						'description'   => 'Comma-delimited list of related names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					1 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					2 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
			),
			3 =>
			array(
				'method'           => 'DELETE',
				'summary'          => 'Delete one or more applications.',
				'nickname'         => 'deleteApps',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'ids',
						'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					1 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to delete.',
						'allowMultiple' => false,
						'type'          => 'Apps',
						'paramType'     => 'body',
						'required'      => false,
					),
					2 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'related',
						'description'   => 'Comma-delimited list of related names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					1 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					2 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Use \'ids\' or post data should be a single record or an array of records (shown) containing an id. By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
			),
		),
		'description' => 'Operations for application administration.',
	),
	array(
		'path'        => '/{api_name}/app/{id}',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve one application by identifier.',
				'nickname'         => 'getApp',
				'type'             => 'App',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the record to retrieve.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					2 =>
					array(
						'name'          => 'related',
						'description'   => 'Comma-delimited list of related names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					1 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					2 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
			),
			1 =>
			array(
				'method'           => 'PUT',
				'summary'          => 'Update one application.',
				'nickname'         => 'updateApp',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the record to retrieve.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'App',
						'paramType'     => 'body',
						'required'      => true,
					),
					2 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'related',
						'description'   => 'Comma-delimited list of related names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					1 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					2 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Post data should be an array of fields for a single record. Use the \'fields\' and/or \'related\' parameter to return more properties. By default, the id is returned.',
			),
			2 =>
			array(
				'method'           => 'DELETE',
				'summary'          => 'Delete one application.',
				'nickname'         => 'deleteApp',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the record to retrieve.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					2 =>
					array(
						'name'          => 'related',
						'description'   => 'Comma-delimited list of related names to retrieve for each record.',
						'allowMultiple' => true,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					1 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					2 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Use the \'fields\' and/or \'related\' parameter to return deleted properties. By default, the id is returned.',
			),
		),
		'description' => 'Operations for individual application administration.',
	),
);

$_setProps = array(
	'api_name'                =>
	array(
		'type'        => 'string',
		'description' => 'Name of the application to use in API transactions.',
	),
	'name'                    =>
	array(
		'type'        => 'string',
		'description' => 'Displayable name of this application.',
	),
	'description'             =>
	array(
		'type'        => 'string',
		'description' => 'Description of this application.',
	),
	'is_active'               =>
	array(
		'type'        => 'boolean',
		'description' => 'Is this system application active for use.',
	),
	'url'                     =>
	array(
		'type'        => 'string',
		'description' => 'URL for accessing this application.',
	),
	'is_url_external'         =>
	array(
		'type'        => 'boolean',
		'description' => 'True when this application is hosted elsewhere, but available in Launchpad.',
	),
	'import_url'              =>
	array(
		'type'        => 'string',
		'description' => 'If hosted and imported, the url of zip or package file where the code originated.',
	),
	'storage_service_id'      =>
	array(
		'type'        => 'string',
		'description' => 'If hosted, the storage service identifier.',
	),
	'storage_container'       =>
	array(
		'type'        => 'string',
		'description' => 'If hosted, the container of the storage service.',
	),
	'requires_fullscreen'     =>
	array(
		'type'        => 'boolean',
		'description' => 'True when this app needs to hide launchpad.',
	),
	'allow_fullscreen_toggle' =>
	array(
		'type'        => 'boolean',
		'description' => 'True to allow launchpad access via toggle.',
	),
	'toggle_location'         =>
	array(
		'type'        => 'string',
		'description' => 'Screen location for toggle placement.',
	),
	'requires_plugin'         =>
	array(
		'type'        => 'boolean',
		'description' => 'True when the app relies on a browser plugin.',
	),
	'roles_default_app'       =>
	array(
		'type'        => 'Array',
		'description' => 'Related roles by Role.default_app_id.',
		'items'       =>
		array(
			'type' => 'string',
		),
	),
	'users_default_app'       =>
	array(
		'type'        => 'Array',
		'description' => 'Related users by User.default_app_id.',
		'items'       =>
		array(
			'type' => 'string',
		),
	),
	'app_groups'              =>
	array(
		'type'        => 'Array',
		'description' => 'Related groups by app to group assignment.',
		'items'       =>
		array(
			'type' => 'string',
		),
	),
	'roles'                   =>
	array(
		'type'        => 'Array',
		'description' => 'Related roles by app to role assignment.',
		'items'       =>
		array(
			'type' => 'string',
		),
	),
	'services'                =>
	array(
		'type'        => 'Array',
		'description' => 'Related services by app to service assignment.',
		'items'       =>
		array(
			'type' => 'string',
		),
	),
);

$_base['models'] = array(
	'App'  =>
	array(
		'id'         => 'App',
		'properties' =>
		array(
			'id'                      =>
			array(
				'type'        => 'integer',
				'description' => 'Identifier of this application.',
			),
			'name'                    =>
			array(
				'type'        => 'string',
				'description' => 'Displayable name of this application.',
			),
			'api_name'                =>
			array(
				'type'        => 'string',
				'description' => 'Name of the application to use in API transactions.',
			),
			'description'             =>
			array(
				'type'        => 'string',
				'description' => 'Description of this application.',
			),
			'is_active'               =>
			array(
				'type'        => 'boolean',
				'description' => 'Is this system application active for use.',
			),
			'url'                     =>
			array(
				'type'        => 'string',
				'description' => 'URL for accessing this application.',
			),
			'is_url_external'         =>
			array(
				'type'        => 'boolean',
				'description' => 'True when this application is hosted elsewhere, but available in Launchpad.',
			),
			'import_url'              =>
			array(
				'type'        => 'string',
				'description' => 'If hosted and imported, the url of zip or package file where the code originated.',
			),
			'storage_service_id'      =>
			array(
				'type'        => 'string',
				'description' => 'If hosted, the storage service identifier.',
			),
			'storage_container'       =>
			array(
				'type'        => 'string',
				'description' => 'If hosted, the container of the storage service.',
			),
			'requires_fullscreen'     =>
			array(
				'type'        => 'boolean',
				'description' => 'True when this app needs to hide launchpad.',
			),
			'allow_fullscreen_toggle' =>
			array(
				'type'        => 'boolean',
				'description' => 'True to allow launchpad access via toggle.',
			),
			'toggle_location'         =>
			array(
				'type'        => 'string',
				'description' => 'Screen location for toggle placement.',
			),
			'requires_plugin'         =>
			array(
				'type'        => 'boolean',
				'description' => 'True when the app relies on a browser plugin.',
			),
			'roles_default_app'       =>
			array(
				'type'        => 'Array',
				'description' => 'Related roles by Role.default_app_id.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'users_default_app'       =>
			array(
				'type'        => 'Array',
				'description' => 'Related users by User.default_app_id.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'app_groups'              =>
			array(
				'type'        => 'Array',
				'description' => 'Related groups by app to group assignment.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'roles'                   =>
			array(
				'type'        => 'Array',
				'description' => 'Related roles by app to role assignment.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'services'                =>
			array(
				'type'        => 'Array',
				'description' => 'Related services by app to service assignment.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'created_date'            =>
			array(
				'type'        => 'string',
				'description' => 'Date this application was created.',
			),
			'created_by_id'           =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who created this application.',
			),
			'last_modified_date'      =>
			array(
				'type'        => 'string',
				'description' => 'Date this application was last modified.',
			),
			'last_modified_by_id'     =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who last modified this application.',
			),
		),
	),
	'Apps' =>
	array(
		'id'         => 'Apps',
		'properties' =>
		array(
			'record' =>
			array(
				'type'        => 'Array',
				'description' => 'Array of system application records.',
				'items'       =>
				array(
					'$ref' => 'App',
				),
			),
		),
	),
);

return $_base;
