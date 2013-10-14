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

$_base['models'] = array(
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
);

return $_base;
