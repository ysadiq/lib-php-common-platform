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
		'path'        => '/{api_name}/service',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve multiple services.',
				'nickname'         => 'getServices',
				'type'             => 'Services',
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
				'summary'          => 'Create one or more services.',
				'nickname'         => 'createServices',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'type'          => 'Services',
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
				'summary'          => 'Update one or more services.',
				'nickname'         => 'updateServices',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'Services',
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
				'summary'          => 'Delete one or more services.',
				'nickname'         => 'deleteServices',
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
						'type'          => 'Services',
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
		'description' => 'Operations for service administration.',
	),
	array(
		'path'        => '/{api_name}/service/{id}',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve one service by identifier.',
				'nickname'         => 'getService',
				'type'             => 'Service',
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
				'summary'          => 'Update one service.',
				'nickname'         => 'updateService',
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
						'type'          => 'Service',
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
				'summary'          => 'Delete one service.',
				'nickname'         => 'deleteService',
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
		'description' => 'Operations for individual service administration.',
	),
);

$_base['models'] = array(
	'Service'        =>
	array(
		'id'         => 'Service',
		'properties' =>
		array(
			'id'                  =>
			array(
				'type'        => 'integer',
				'description' => 'Identifier of this service.',
			),
			'name'                =>
			array(
				'type'        => 'string',
				'description' => 'Displayable name of this service.',
			),
			'api_name'            =>
			array(
				'type'        => 'string',
				'description' => 'Name of the service to use in API transactions.',
			),
			'description'         =>
			array(
				'type'        => 'string',
				'description' => 'Description of this service.',
			),
			'is_active'           =>
			array(
				'type'        => 'boolean',
				'description' => 'True if this service is active for use.',
			),
			'is_system'           =>
			array(
				'type'        => 'boolean',
				'description' => 'True if this service is a default system service.',
			),
			'type'                =>
			array(
				'type'        => 'string',
				'description' => 'One of the supported service types.',
				'deprecated' => true,
			),
			'storage_name'        =>
			array(
				'type'        => 'string',
				'description' => 'The local or remote storage name (i.e. root folder).',
			),
			'storage_type'        =>
			array(
				'type'        => 'string',
				'description' => 'They supported storage service type.',
				'deprecated' => true,
			),
			'credentials'         =>
			array(
				'type'        => 'string',
				'description' => 'Any credentials data required by the service.',
			),
			'native_format'       =>
			array(
				'type'        => 'string',
				'description' => 'The format of the returned data of the service.',
			),
			'base_url'            =>
			array(
				'type'        => 'string',
				'description' => 'The base URL for remote web services.',
			),
			'parameters'          =>
			array(
				'type'        => 'string',
				'description' => 'Additional URL parameters required by the service.',
			),
			'headers'             =>
			array(
				'type'        => 'string',
				'description' => 'Additional headers required by the service.',
			),
			'apps'                =>
			array(
				'type'        => 'Array',
				'description' => 'Related apps by app to service assignment.',
				'items'       =>
				array(
					'$ref' => 'App',
				),
			),
			'roles'               =>
			array(
				'type'        => 'Array',
				'description' => 'Related roles by service to role assignment.',
				'items'       =>
				array(
					'$ref' => 'Role',
				),
			),
			'created_date'        =>
			array(
				'type'        => 'string',
				'description' => 'Date this service was created.',
			),
			'created_by_id'       =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who created this service.',
			),
			'last_modified_date'  =>
			array(
				'type'        => 'string',
				'description' => 'Date this service was last modified.',
			),
			'last_modified_by_id' =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who last modified this service.',
			),
		),
	),
	'Services'       =>
	array(
		'id'         => 'Services',
		'properties' =>
		array(
			'record' =>
			array(
				'type'        => 'Array',
				'description' => 'Array of system service records.',
				'items'       =>
				array(
					'$ref' => 'Service',
				),
			),
		),
	),
);

return $_base;
