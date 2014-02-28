<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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

$_role = array();

$_role['apis'] = array(
	array(
		'path'        => '/{api_name}/role',
		'operations'  =>
			array(
				array(
					'method'           => 'GET',
					'summary'          => 'getRoles() - Retrieve one or more roles.',
					'nickname'         => 'getRoles',
					'type'             => 'RolesResponse',
					'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
					'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
					'parameters'       =>
						array(
							array(
								'name'          => 'ids',
								'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'filter',
								'description'   => 'SQL-like filter to limit the records to retrieve.',
								'allowMultiple' => false,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'limit',
								'description'   => 'Set to limit the filter results.',
								'allowMultiple' => false,
								'type'          => 'integer',
								'format'        => 'int32',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'order',
								'description'   => 'SQL-like order containing field and direction for filter results.',
								'allowMultiple' => false,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'offset',
								'description'   => 'Set to offset the filter results to a particular record count.',
								'allowMultiple' => false,
								'type'          => 'integer',
								'format'        => 'int32',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'fields',
								'description'   => 'Comma-delimited list of field names to retrieve for each record.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'related',
								'description'   => 'Comma-delimited list of related names to retrieve for each record.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'include_count',
								'description'   => 'Include the total number of filter results in returned metadata.',
								'allowMultiple' => false,
								'type'          => 'boolean',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'include_schema',
								'description'   => 'Include the schema of the table queried in returned metadata.',
								'allowMultiple' => false,
								'type'          => 'boolean',
								'paramType'     => 'query',
								'required'      => false,
							),
						),
					'responseMessages' =>
						array(
							array(
								'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
								'code'    => 400,
							),
							array(
								'message' => 'Unauthorized Access - No currently valid session available.',
								'code'    => 401,
							),
							array(
								'message' => 'System Error - Specific reason is included in the error message.',
								'code'    => 500,
							),
						),
					'notes'            => 'Use the \'ids\' or \'filter\' parameter to limit records that are returned. ' .
										  'By default, all records up to the maximum are returned. <br>' .
										  'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
										  'By default, all fields and no relations are returned for each record. <br>' .
										  'Alternatively, to retrieve by record, a large list of ids, or a complicated filter, ' .
										  'use the POST request with X-HTTP-METHOD = GET header and post records or ids.',
				),
				array(
					'method'           => 'POST',
					'summary'          => 'createRoles() - Create one or more roles.',
					'nickname'         => 'createRoles',
					'type'             => 'RolesResponse',
					'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
					'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
					'parameters'       =>
						array(
							array(
								'name'          => 'body',
								'description'   => 'Data containing name-value pairs of records to create.',
								'allowMultiple' => false,
								'type'          => 'RolesRequest',
								'paramType'     => 'body',
								'required'      => true,
							),
							array(
								'name'          => 'fields',
								'description'   => 'Comma-delimited list of field names to return for each record affected.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'related',
								'description'   => 'Comma-delimited list of related names to return for each record affected.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'X-HTTP-METHOD',
								'description'   => 'Override request using POST to tunnel other http request, such as DELETE.',
								'enum'          => array( 'GET', 'PUT', 'PATCH', 'DELETE' ),
								'allowMultiple' => false,
								'type'          => 'string',
								'paramType'     => 'header',
								'required'      => false,
							),
						),
					'responseMessages' =>
						array(
							array(
								'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
								'code'    => 400,
							),
							array(
								'message' => 'Unauthorized Access - No currently valid session available.',
								'code'    => 401,
							),
							array(
								'message' => 'System Error - Specific reason is included in the error message.',
								'code'    => 500,
							),
						),
					'notes'            => 'Post data should be a single record or an array of records (shown). ' .
										  'By default, only the id property of the record affected is returned on success, ' .
										  'use \'fields\' and \'related\' to return more info.',
				),
				array(
					'method'           => 'PATCH',
					'summary'          => 'updateRoles() - Update one or more roles.',
					'nickname'         => 'updateRoles',
					'type'             => 'RolesResponse',
					'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
					'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
					'parameters'       =>
						array(
							array(
								'name'          => 'body',
								'description'   => 'Data containing name-value pairs of records to update.',
								'allowMultiple' => false,
								'type'          => 'RolesRequest',
								'paramType'     => 'body',
								'required'      => true,
							),
							array(
								'name'          => 'fields',
								'description'   => 'Comma-delimited list of field names to return for each record affected.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'related',
								'description'   => 'Comma-delimited list of related names to return for each record affected.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
						),
					'responseMessages' =>
						array(
							array(
								'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
								'code'    => 400,
							),
							array(
								'message' => 'Unauthorized Access - No currently valid session available.',
								'code'    => 401,
							),
							array(
								'message' => 'System Error - Specific reason is included in the error message.',
								'code'    => 500,
							),
						),
					'notes'            => 'Post data should be a single record or an array of records (shown). ' .
										  'By default, only the id property of the record is returned on success, ' .
										  'use \'fields\' and \'related\' to return more info.',
				),
				array(
					'method'           => 'DELETE',
					'summary'          => 'deleteRoles() - Delete one or more roles.',
					'nickname'         => 'deleteRoles',
					'type'             => 'RolesResponse',
					'parameters'       =>
						array(
							array(
								'name'          => 'ids',
								'description'   => 'Comma-delimited list of the identifiers of the records to delete.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'force',
								'description'   => 'Set force to true to delete all records in this table, otherwise \'ids\' parameter is required.',
								'allowMultiple' => false,
								'type'          => 'boolean',
								'paramType'     => 'query',
								'required'      => false,
								'default'       => false,
							),
							array(
								'name'          => 'fields',
								'description'   => 'Comma-delimited list of field names to return for each record affected.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'related',
								'description'   => 'Comma-delimited list of related names to return for each record affected.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
						),
					'responseMessages' =>
						array(
							array(
								'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
								'code'    => 400,
							),
							array(
								'message' => 'Unauthorized Access - No currently valid session available.',
								'code'    => 401,
							),
							array(
								'message' => 'System Error - Specific reason is included in the error message.',
								'code'    => 500,
							),
						),
					'notes'            => 'By default, only the id property of the record deleted is returned on success. ' .
										  'Use \'fields\' and \'related\' to return more properties of the deleted records. <br>' .
										  'Alternatively, to delete by record or a large list of ids, ' .
										  'use the POST request with X-HTTP-METHOD = DELETE header and post records or ids.',
				),
			),
		'description' => 'Operations for role administration.',
	),
	array(
		'path'        => '/{api_name}/role/{id}',
		'operations'  =>
			array(
				array(
					'method'           => 'GET',
					'summary'          => 'getRole() - Retrieve one role.',
					'nickname'         => 'getRole',
					'type'             => 'RoleResponse',
					'parameters'       =>
						array(
							array(
								'name'          => 'id',
								'description'   => 'Identifier of the record to retrieve.',
								'allowMultiple' => false,
								'type'          => 'string',
								'paramType'     => 'path',
								'required'      => true,
							),
							array(
								'name'          => 'fields',
								'description'   => 'Comma-delimited list of field names to return.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'related',
								'description'   => 'Comma-delimited list of related records to return.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
						),
					'responseMessages' =>
						array(
							array(
								'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
								'code'    => 400,
							),
							array(
								'message' => 'Unauthorized Access - No currently valid session available.',
								'code'    => 401,
							),
							array(
								'message' => 'System Error - Specific reason is included in the error message.',
								'code'    => 500,
							),
						),
					'notes'            => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
				),
				array(
					'method'           => 'PATCH',
					'summary'          => 'updateRole() - Update one role.',
					'nickname'         => 'updateRole',
					'type'             => 'RoleResponse',
					'parameters'       =>
						array(
							array(
								'name'          => 'id',
								'description'   => 'Identifier of the record to update.',
								'allowMultiple' => false,
								'type'          => 'string',
								'paramType'     => 'path',
								'required'      => true,
							),
							array(
								'name'          => 'body',
								'description'   => 'Data containing name-value pairs of fields to update.',
								'allowMultiple' => false,
								'type'          => 'RoleRequest',
								'paramType'     => 'body',
								'required'      => true,
							),
							array(
								'name'          => 'fields',
								'description'   => 'Comma-delimited list of field names to return.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'related',
								'description'   => 'Comma-delimited list of related records to return.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
						),
					'responseMessages' =>
						array(
							array(
								'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
								'code'    => 400,
							),
							array(
								'message' => 'Unauthorized Access - No currently valid session available.',
								'code'    => 401,
							),
							array(
								'message' => 'System Error - Specific reason is included in the error message.',
								'code'    => 500,
							),
						),
					'notes'            => 'Post data should be an array of fields to update for a single record. <br>' .
										  'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return more properties.',
				),
				array(
					'method'           => 'DELETE',
					'summary'          => 'deleteRole() - Delete one role.',
					'nickname'         => 'deleteRole',
					'type'             => 'RoleResponse',
					'parameters'       =>
						array(
							array(
								'name'          => 'id',
								'description'   => 'Identifier of the record to delete.',
								'allowMultiple' => false,
								'type'          => 'string',
								'paramType'     => 'path',
								'required'      => true,
							),
							array(
								'name'          => 'fields',
								'description'   => 'Comma-delimited list of field names to return.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
							array(
								'name'          => 'related',
								'description'   => 'Comma-delimited list of related records to return.',
								'allowMultiple' => true,
								'type'          => 'string',
								'paramType'     => 'query',
								'required'      => false,
							),
						),
					'responseMessages' =>
						array(
							array(
								'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
								'code'    => 400,
							),
							array(
								'message' => 'Unauthorized Access - No currently valid session available.',
								'code'    => 401,
							),
							array(
								'message' => 'System Error - Specific reason is included in the error message.',
								'code'    => 500,
							),
						),
					'notes'            => 'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return deleted properties.',
				),
			),
		'description' => 'Operations for individual role administration.',
	),
);

$_commonProperties = array(
	'id'             =>
		array(
			'type'        => 'integer',
			'format'      => 'int32',
			'description' => 'Identifier of this role.',
		),
	'name'           =>
		array(
			'type'        => 'string',
			'description' => 'Displayable name of this role.',
		),
	'description'    =>
		array(
			'type'        => 'string',
			'description' => 'Description of this role.',
		),
	'is_active'      =>
		array(
			'type'        => 'boolean',
			'description' => 'Is this role active for use.',
		),
	'default_app_id' =>
		array(
			'type'        => 'integer',
			'format'      => 'int32',
			'description' => 'Default launched app for this role.',
		),
);

$_relatedProperties = array(
	'default_app' =>
		array(
			'type'        => 'RelatedApp',
			'description' => 'Related app by default_app_id.',
		),
	'users'       =>
		array(
			'type'        => 'RelatedUsers',
			'description' => 'Related users by User.role_id.',
		),
	'apps'        =>
		array(
			'type'        => 'RelatedApps',
			'description' => 'Related apps by role assignment.',
		),
	'services'    =>
		array(
			'type'        => 'RelatedServices',
			'description' => 'Related services by role assignment.',
		),
);

$_stampProperties = array(
	'created_date'        =>
		array(
			'type'        => 'string',
			'description' => 'Date this role was created.',
			'readOnly'    => true,
		),
	'created_by_id'       =>
		array(
			'type'        => 'integer',
			'format'      => 'int32',
			'description' => 'User Id of who created this role.',
			'readOnly'    => true,
		),
	'last_modified_date'  =>
		array(
			'type'        => 'string',
			'description' => 'Date this role was last modified.',
			'readOnly'    => true,
		),
	'last_modified_by_id' =>
		array(
			'type'        => 'integer',
			'format'      => 'int32',
			'description' => 'User Id of who last modified this role.',
			'readOnly'    => true,
		),
);

$_role['models'] = array(
	'RoleRequest'   =>
		array(
			'id'         => 'RoleRequest',
			'properties' =>
				array_merge(
					$_commonProperties,
					$_relatedProperties
				)
		),
	'RolesRequest'  =>
		array(
			'id'         => 'RolesRequest',
			'properties' =>
				array(
					'record' =>
						array(
							'type'        => 'Array',
							'description' => 'Array of system role records.',
							'items'       =>
								array(
									'$ref' => 'RoleRequest',
								),
						),
					'ids'    =>
						array(
							'type'        => 'Array',
							'description' => 'Array of system record identifiers, used for batch GET, PUT, PATCH, and DELETE.',
							'items'       =>
								array(
									'type'   => 'integer',
									'format' => 'int32',
								),
						),
				),
		),
	'RoleResponse'  =>
		array(
			'id'         => 'RoleResponse',
			'properties' =>
				array_merge(
					$_commonProperties,
					$_relatedProperties,
					$_stampProperties
				),
		),
	'RolesResponse' =>
		array(
			'id'         => 'RolesResponse',
			'properties' =>
				array(
					'record' =>
						array(
							'type'        => 'Array',
							'description' => 'Array of system role records.',
							'items'       =>
								array(
									'$ref' => 'RoleResponse',
								),
						),
					'meta'   =>
						array(
							'type'        => 'Metadata',
							'description' => 'Array of metadata returned for GET requests.',
						),
				),
		),
	'RelatedRole'   =>
		array(
			'id'         => 'RelatedRole',
			'properties' =>
				array_merge(
					$_commonProperties,
					$_stampProperties
				)
		),
	'RelatedRoles'  =>
		array(
			'id'         => 'RelatedRoles',
			'properties' =>
				array(
					'record' =>
						array(
							'type'        => 'Array',
							'description' => 'Array of system role records.',
							'items'       =>
								array(
									'$ref' => 'RelatedRole',
								),
						),
					'meta'   =>
						array(
							'type'        => 'Metadata',
							'description' => 'Array of metadata returned for GET requests.',
						),
				),
		),
);

return $_role;
