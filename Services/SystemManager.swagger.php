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

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );

$_base['apis'] = array(
	0  =>
	array(
		'path'        => '/{api_name}',
		'operations'  =>
		array(
			0 =>
			array(
				'method'   => 'GET',
				'summary'  => 'List resources available for system management.',
				'nickname' => 'getResources',
				'type'     => 'Resources',
				'notes'    => 'See listed operations for each resource available.',
			),
		),
		'description' => 'Operations available for system management.',
	),
	1  =>
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
	2  =>
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
	3  =>
	array(
		'path'        => '/{api_name}/app_group',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve multiple application groups.',
				'nickname'         => 'getAppGroups',
				'type'             => 'AppGroups',
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
				'summary'          => 'Create one or more application groups.',
				'nickname'         => 'createAppGroups',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'type'          => 'AppGroups',
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
				'summary'          => 'Update one or more application groups.',
				'nickname'         => 'updateAppGroups',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'AppGroups',
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
				'summary'          => 'Delete one or more application groups.',
				'nickname'         => 'deleteAppGroups',
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
						'type'          => 'AppGroups',
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
		'description' => 'Operations for application group administration.',
	),
	4  =>
	array(
		'path'        => '/{api_name}/app_group/{id}',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve one application group by identifier.',
				'nickname'         => 'getAppGroup',
				'type'             => 'AppGroup',
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
				'summary'          => 'Update one application group.',
				'nickname'         => 'updateAppGroup',
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
						'type'          => 'AppGroup',
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
				'summary'          => 'Delete one application group.',
				'nickname'         => 'deleteAppGroup',
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
		'description' => 'Operations for individual application group administration.',
	),
	5  =>
	array(
		'path'        => '/{api_name}/config',
		'operations'  =>
		array(
			0 =>
			array(
				'method'   => 'GET',
				'summary'  => 'Retrieve system configuration options.',
				'nickname' => 'getConfig',
				'type'     => 'Config',
				'notes'    => 'The retrieved properties control how the system behaves.',
			),
			1 =>
			array(
				'method'           => 'POST',
				'summary'          => 'Update one or more system configuration properties.',
				'nickname'         => 'setConfig',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'config',
						'description'   => 'Data containing name-value pairs of properties to set.',
						'allowMultiple' => false,
						'type'          => 'Config',
						'paramType'     => 'body',
						'required'      => true,
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
				'notes'            => 'Post data should be an array of properties.',
			),
		),
		'description' => 'Operations for system configuration options.',
	),
	6  =>
	array(
		'path'        => '/{api_name}/email_template',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve multiple email templates.',
				'nickname'         => 'getEmailTemplates',
				'type'             => 'EmailTemplates',
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
				'summary'          => 'Create one or more email templates.',
				'nickname'         => 'createEmailTemplates',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'type'          => 'EmailTemplates',
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
				'summary'          => 'Update one or more email templates.',
				'nickname'         => 'updateEmailTemplates',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'EmailTemplates',
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
				'summary'          => 'Delete one or more email templates.',
				'nickname'         => 'deleteEmailTemplates',
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
						'type'          => 'EmailTemplates',
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
		'description' => 'Operations for email template administration.',
	),
	7  =>
	array(
		'path'        => '/{api_name}/email_template/{id}',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve one application by identifier.',
				'nickname'         => 'getEmailTemplate',
				'type'             => 'EmailTemplate',
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
				'summary'          => 'Update one email template.',
				'nickname'         => 'updateEmailTemplate',
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
						'type'          => 'EmailTemplate',
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
				'summary'          => 'Delete one email template.',
				'nickname'         => 'deleteEmailTemplate',
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
		'description' => 'Operations for individual email template administration.',
	),
	8  =>
	array(
		'path'        => '/{api_name}/role',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve multiple roles.',
				'nickname'         => 'getRoles',
				'type'             => 'Roles',
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
				'summary'          => 'Create one or more roles.',
				'nickname'         => 'createRoles',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'type'          => 'Roles',
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
				'summary'          => 'Update one or more roles.',
				'nickname'         => 'updateRoles',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'Roles',
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
				'summary'          => 'Delete one or more roles.',
				'nickname'         => 'deleteRoles',
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
						'type'          => 'Roles',
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
		'description' => 'Operations for role administration.',
	),
	9  =>
	array(
		'path'        => '/{api_name}/role/{id}',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve one role by identifier.',
				'nickname'         => 'getRole',
				'type'             => 'Role',
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
				'summary'          => 'Update one role.',
				'nickname'         => 'updateRole',
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
						'type'          => 'Role',
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
				'summary'          => 'Update one role.',
				'nickname'         => 'deleteRole',
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
		'description' => 'Operations for individual role administration.',
	),
	10 =>
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
	11 =>
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
	12 =>
	array(
		'path'        => '/{api_name}/user',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve multiple users.',
				'nickname'         => 'getUsers',
				'type'             => 'Users',
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
				'summary'          => 'Create one or more users.',
				'nickname'         => 'createUsers',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'type'          => 'Users',
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
				'summary'          => 'Update one or more users.',
				'nickname'         => 'updateUsers',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'Users',
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
				'summary'          => 'Delete one or more users.',
				'nickname'         => 'deleteUsers',
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
						'type'          => 'Users',
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
		'description' => 'Operations for user administration.',
	),
	13 =>
	array(
		'path'        => '/{api_name}/user/{id}',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve one user by identifier.',
				'nickname'         => 'getUser',
				'type'             => 'User',
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
				'summary'          => 'Update one user.',
				'nickname'         => 'updateUser',
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
						'type'          => 'User',
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
				'summary'          => 'Delete one user.',
				'nickname'         => 'deleteUser',
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
		'description' => 'Operations for individual user administration.',
	),
);

$_models = array(
	'Users'          =>
	array(
		'id'         => 'Users',
		'properties' =>
		array(
			'record' =>
			array(
				'type'        => 'Array',
				'description' => 'Array of system user records.',
				'items'       =>
				array(
					'$ref' => 'User',
				),
			),
		),
	),
	'User'           =>
	array(
		'id'         => 'User',
		'properties' =>
		array(
			'id'                  =>
			array(
				'type'        => 'integer',
				'description' => 'Identifier of this user.',
			),
			'email'               =>
			array(
				'type'        => 'string',
				'description' => 'The email address required for this user.',
			),
			'password'            =>
			array(
				'type'        => 'string',
				'description' => 'The set-able, but never readable, password.',
			),
			'first_name'          =>
			array(
				'type'        => 'string',
				'description' => 'The first name for this user.',
			),
			'last_name'           =>
			array(
				'type'        => 'string',
				'description' => 'The last name for this user.',
			),
			'display_name'        =>
			array(
				'type'        => 'string',
				'description' => 'Displayable name of this user.',
			),
			'phone'               =>
			array(
				'type'        => 'string',
				'description' => 'Phone number for this user.',
			),
			'is_active'           =>
			array(
				'type'        => 'boolean',
				'description' => 'True if this user is active for use.',
			),
			'is_sys_admin'        =>
			array(
				'type'        => 'boolean',
				'description' => 'True if this user is a system admin.',
			),
			'default_app_id'      =>
			array(
				'type'        => 'string',
				'description' => 'The default launched app for this user.',
			),
			'role_id'             =>
			array(
				'type'        => 'string',
				'description' => 'The role to which this user is assigned.',
			),
			'last_login_date'     =>
			array(
				'type'        => 'string',
				'description' => 'Timestamp of the last login.',
			),
			'default_app'         =>
			array(
				'type'        => 'App',
				'description' => 'Related app by default_app_id.',
			),
			'role'                =>
			array(
				'type'        => 'Role',
				'description' => 'Related role by role_id.',
			),
			'created_date'        =>
			array(
				'type'        => 'string',
				'description' => 'Date this user was created.',
			),
			'created_by_id'       =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who created this user.',
			),
			'last_modified_date'  =>
			array(
				'type'        => 'string',
				'description' => 'Date this user was last modified.',
			),
			'last_modified_by_id' =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who last modified this user.',
			),
		),
	),
	'App'            =>
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
				'description' => 'True when this application is hosted elsewhere.',
			),
			'imported_url'            =>
			array(
				'type'        => 'string',
				'description' => 'If imported, the url of where the code originated.',
			),
			'storage_service_id'      =>
			array(
				'type'        => 'string',
				'description' => 'If locally stored, the storage service identifier.',
			),
			'storage_container'       =>
			array(
				'type'        => 'string',
				'description' => 'If locally stored, the container of the storage service.',
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
	'Role'           =>
	array(
		'id'         => 'Role',
		'properties' =>
		array(
			'id'                  =>
			array(
				'type'        => 'integer',
				'description' => 'Identifier of this role.',
			),
			'name'                =>
			array(
				'type'        => 'string',
				'description' => 'Displayable name of this role.',
			),
			'description'         =>
			array(
				'type'        => 'string',
				'description' => 'Description of this role.',
			),
			'is_active'           =>
			array(
				'type'        => 'boolean',
				'description' => 'Is this role active for use.',
			),
			'default_app_id'      =>
			array(
				'type'        => 'integer',
				'description' => 'Default launched app for this role.',
			),
			'default_app'         =>
			array(
				'type'        => 'App',
				'description' => 'Related app by default_app_id.',
			),
			'users'               =>
			array(
				'type'        => 'Array',
				'description' => 'Related users by User.role_id.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'apps'                =>
			array(
				'type'        => 'Array',
				'description' => 'Related apps by role assignment.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'services'            =>
			array(
				'type'        => 'Array',
				'description' => 'Related services by role assignment.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'created_date'        =>
			array(
				'type'        => 'string',
				'description' => 'Date this role was created.',
			),
			'created_by_id'       =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who created this role.',
			),
			'last_modified_date'  =>
			array(
				'type'        => 'string',
				'description' => 'Date this role was last modified.',
			),
			'last_modified_by_id' =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who last modified this role.',
			),
		),
	),
	'Apps'           =>
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
	'AppGroups'      =>
	array(
		'id'         => 'AppGroups',
		'properties' =>
		array(
			'record' =>
			array(
				'type'        => 'Array',
				'description' => 'Array of system application group records of the given resource.',
				'items'       =>
				array(
					'$ref' => 'AppGroup',
				),
			),
		),
	),
	'AppGroup'       =>
	array(
		'id'         => 'AppGroup',
		'properties' =>
		array(
			'id'                  =>
			array(
				'type'        => 'integer',
				'description' => 'Identifier of this application grouping.',
			),
			'name'                =>
			array(
				'type'        => 'string',
				'description' => 'Displayable name of this application group.',
			),
			'description'         =>
			array(
				'type'        => 'string',
				'description' => 'Description of this application group.',
			),
			'apps'                =>
			array(
				'type'        => 'Array',
				'description' => 'Related apps by app to group assignment.',
				'items'       =>
				array(
					'$ref' => 'App',
				),
			),
			'created_date'        =>
			array(
				'type'        => 'string',
				'description' => 'Date this application group was created.',
			),
			'created_by_id'       =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who created this application group.',
			),
			'last_modified_date'  =>
			array(
				'type'        => 'string',
				'description' => 'Date this application group was last modified.',
			),
			'last_modified_by_id' =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who last modified this application group.',
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
	'EmailTemplates' =>
	array(
		'id'         => 'EmailTemplates',
		'properties' =>
		array(
			'record' =>
			array(
				'type'        => 'Array',
				'description' => 'Array of system email template records.',
				'items'       =>
				array(
					'$ref' => 'EmailTemplate',
				),
			),
		),
	),
	'EmailTemplate'  =>
	array(
		'id'         => 'EmailTemplate',
		'properties' =>
		array(
			'id'                  =>
			array(
				'type'        => 'integer',
				'description' => 'Identifier of this email template.',
			),
			'name'                =>
			array(
				'type'        => 'string',
				'description' => 'Displayable name of this email template.',
			),
			'description'         =>
			array(
				'type'        => 'string',
				'description' => 'Description of this email template.',
			),
			'to'                  =>
			array(
				'type'        => 'Array',
				'description' => 'Single or multiple receiver addresses.',
				'items'       =>
				array(
					'$ref' => 'EmailAddress',
				),
			),
			'cc'                  =>
			array(
				'type'        => 'Array',
				'description' => 'Optional CC receiver addresses.',
				'items'       =>
				array(
					'$ref' => 'EmailAddress',
				),
			),
			'bcc'                 =>
			array(
				'type'        => 'Array',
				'description' => 'Optional BCC receiver addresses.',
				'items'       =>
				array(
					'$ref' => 'EmailAddress',
				),
			),
			'subject'             =>
			array(
				'type'        => 'string',
				'description' => 'Text only subject line.',
			),
			'body_text'           =>
			array(
				'type'        => 'string',
				'description' => 'Text only version of the body.',
			),
			'body_html'           =>
			array(
				'type'        => 'string',
				'description' => 'Escaped HTML version of the body.',
			),
			'from'                =>
			array(
				'type'        => 'EmailAddress',
				'description' => 'Required sender name and email.',
			),
			'reply_to'            =>
			array(
				'type'        => 'EmailAddress',
				'description' => 'Optional reply to name and email.',
			),
			'defaults'            =>
			array(
				'type'        => 'Array',
				'description' => 'Array of default name value pairs for template replacement.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'created_date'        =>
			array(
				'type'        => 'string',
				'description' => 'Date this email template was created.',
			),
			'created_by_id'       =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who created this email template.',
			),
			'last_modified_date'  =>
			array(
				'type'        => 'string',
				'description' => 'Date this email template was last modified.',
			),
			'last_modified_by_id' =>
			array(
				'type'        => 'integer',
				'description' => 'User Id of who last modified this email template.',
			),
		),
	),
	'EmailAddress'   =>
	array(
		'id'         => 'EmailAddress',
		'properties' =>
		array(
			'name'  =>
			array(
				'type'        => 'string',
				'description' => 'Optional name displayed along with the email address.',
			),
			'email' =>
			array(
				'type'        => 'string',
				'description' => 'Required email address.',
			),
		),
	),
	'Config'         =>
	array(
		'id'         => 'Config',
		'properties' =>
		array(
			'dsp_version'             =>
			array(
				'type'        => 'string',
				'description' => 'Version of the DSP software.',
			),
			'db_version'              =>
			array(
				'type'        => 'string',
				'description' => 'Version of the database schema.',
			),
			'allow_open_registration' =>
			array(
				'type'        => 'boolean',
				'description' => 'Allow guests to register for a user account.',
			),
			'open_reg_role_id'        =>
			array(
				'type'        => 'integer',
				'description' => 'Default Role Id assigned to newly registered users.',
			),
			'allow_guest_user'        =>
			array(
				'type'        => 'boolean',
				'description' => 'Allow app access for non-authenticated users.',
			),
			'guest_role_id'           =>
			array(
				'type'        => 'integer',
				'description' => 'Role Id assigned for all guest sessions.',
			),
			'editable_profile_fields' =>
			array(
				'type'        => 'string',
				'description' => 'Comma-delimited list of fields the user is allowed to edit.',
			),
			'allowed_hosts'           =>
			array(
				'type'        => 'Array',
				'description' => 'CORS whitelist of allowed remote hosts.',
				'items'       =>
				array(
					'$ref' => 'HostInfo',
				),
			),
		),
	),
	'HostInfo'       =>
	array(
		'id'         => 'HostInfo',
		'properties' =>
		array(
			'host'       =>
			array(
				'type'        => 'string',
				'description' => 'URL, server name, or * to define the CORS host.',
			),
			'is_enabled' =>
			array(
				'type'        => 'boolean',
				'description' => 'Allow this host\'s configuration to be used by CORS.',
			),
			'verbs'      =>
			array(
				'type'        => 'Array',
				'description' => 'Allowed HTTP verbs for this host.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
		),
	),
	'Roles'          =>
	array(
		'id'         => 'Roles',
		'properties' =>
		array(
			'record' =>
			array(
				'type'        => 'Array',
				'description' => 'Array of system role records.',
				'items'       =>
				array(
					'$ref' => 'Role',
				),
			),
		),
	),
);

$_base['models'] = array_merge( $_base['models'], $_models );

return $_base;
