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
	0 =>
	array(
		'path'        => '/{api_name}',
		'operations'  =>
		array(
			0 =>
			array(
				'httpMethod'     => 'GET',
				'summary'        => 'List all tables.',
				'nickname'       => 'getTables',
				'responseClass'  => 'Tables',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'include_properties',
						'description'   => 'Return all properties of the tables, if any.',
						'allowMultiple' => false,
						'dataType'      => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
					),
					1 =>
					array(
						'name'          => 'names',
						'description'   => 'Comma-delimited list of the table names to retrieve.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					2 =>
					array(
						'name'          => 'tables',
						'description'   => 'Array of tables to retrieve.',
						'allowMultiple' => false,
						'dataType'      => 'Tables',
						'paramType'     => 'body',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'List the names of the available tables in this storage. Use \'include_properties\' to include any properties of the tables.',
			),
			1 =>
			array(
				'httpMethod'     => 'POST',
				'summary'        => 'Create one or more tables.',
				'nickname'       => 'createTables',
				'responseClass'  => 'Tables',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'tables',
						'description'   => 'Array of tables to create.',
						'allowMultiple' => false,
						'dataType'      => 'Tables',
						'paramType'     => 'body',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'check_exist',
						'description'   => 'If true, the request fails when the table to create already exists.',
						'allowMultiple' => false,
						'dataType'      => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Post data should be a single table definition or an array of table definitions.',
			),
			2 =>
			array(
				'httpMethod'     => 'PATCH',
				'summary'        => 'Update properties of one or more tables.',
				'nickname'       => 'updateTables',
				'responseClass'  => 'Tables',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'tables',
						'description'   => 'Array of tables to create.',
						'allowMultiple' => false,
						'dataType'      => 'Tables',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Post data should be a single table definition or an array of table definitions.',
			),
			3 =>
			array(
				'httpMethod'     => 'DELETE',
				'summary'        => 'Delete one or more tables.',
				'nickname'       => 'deleteTables',
				'responseClass'  => 'Tables',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'names',
						'description'   => 'Comma-delimited list of the table names to delete.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					1 =>
					array(
						'name'          => 'tables',
						'description'   => 'Array of tables to delete.',
						'allowMultiple' => false,
						'dataType'      => 'Tables',
						'paramType'     => 'body',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Post data should be a single table definition or an array of table definitions.',
			),
		),
		'description' => 'Operations available for NoSQL database tables.',
	),
	1 =>
	array(
		'path'        => '/{api_name}/{table_name}',
		'operations'  =>
		array(
			0 =>
			array(
				'httpMethod'     => 'GET',
				'summary'        => 'Retrieve multiple records.',
				'nickname'       => 'getRecords',
				'responseClass'  => 'Records',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'ids',
						'description'   => 'Comma-delimited list of the identifiers of the resources to retrieve.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					2 =>
					array(
						'name'          => 'filter',
						'description'   => 'SQL-like filter to limit the resources to retrieve.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'limit',
						'description'   => 'Set to limit the filter results.',
						'allowMultiple' => false,
						'dataType'      => 'int',
						'paramType'     => 'query',
						'required'      => false,
					),
					4 =>
					array(
						'name'          => 'offset',
						'description'   => 'Set to offset the filter results to a particular record count.',
						'allowMultiple' => false,
						'dataType'      => 'int',
						'paramType'     => 'query',
						'required'      => false,
					),
					5 =>
					array(
						'name'          => 'order',
						'description'   => 'SQL-like order containing field and direction for filter results.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					6 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					7 =>
					array(
						'name'          => 'include_count',
						'description'   => 'Include the total number of filter results.',
						'allowMultiple' => false,
						'dataType'      => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
					),
					8 =>
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					9 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing records to retrieve by identifying fields.',
						'allowMultiple' => false,
						'dataType'      => 'Records',
						'paramType'     => 'body',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Use the \'ids\' or \'filter\' parameter to limit resources that are returned. Use the \'fields\' parameter to limit properties returned for each resource. By default, all fields are returned for all resources.',
			),
			1 =>
			array(
				'httpMethod'     => 'POST',
				'summary'        => 'Create one or more records.',
				'nickname'       => 'createRecords',
				'responseClass'  => 'Records',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'dataType'      => 'Records',
						'paramType'     => 'body',
						'required'      => true,
					),
					2 =>
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' to return more info.',
			),
			2 =>
			array(
				'httpMethod'     => 'PUT',
				'summary'        => 'Update (replace) one or more records.',
				'nickname'       => 'updateRecords',
				'responseClass'  => 'Records',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'dataType'      => 'Records',
						'paramType'     => 'body',
						'required'      => true,
					),
					2 =>
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' to return more info.',
			),
			3 =>
			array(
				'httpMethod'     => 'PATCH',
				'summary'        => 'Update (merge) one or more records.',
				'nickname'       => 'mergeRecords',
				'responseClass'  => 'Records',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'dataType'      => 'Table',
						'paramType'     => 'body',
						'required'      => true,
					),
					2 =>
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' to return more info.',
			),
			4 =>
			array(
				'httpMethod'     => 'DELETE',
				'summary'        => 'Delete one or more records.',
				'nickname'       => 'deleteRecords',
				'responseClass'  => 'Records',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'ids',
						'description'   => 'Comma-delimited list of the identifiers of the resources to delete.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					2 =>
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to delete.',
						'allowMultiple' => false,
						'dataType'      => 'Records',
						'paramType'     => 'body',
						'required'      => false,
					),
					4 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Use \'ids\' or post data should be a single record or an array of records (shown) containing an id. By default, only the id property of the record is returned on success, use \'fields\' to return more info.',
			),
		),
		'description' => 'Operations for table records administration.',
	),
	2 =>
	array(
		'path'        => '/{api_name}/{table_name}/{id}',
		'operations'  =>
		array(
			0 =>
			array(
				'httpMethod'     => 'GET',
				'summary'        => 'Retrieve one record by identifier.',
				'nickname'       => 'getRecord',
				'responseClass'  => 'Record',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the resource to retrieve.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					2 =>
					array(
						'name'          => 'properties_only',
						'description'   => 'Return just the properties of the record.',
						'allowMultiple' => true,
						'dataType'      => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					4 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table or record does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Use the \'fields\' parameter to limit properties that are returned. By default, all fields are returned.',
			),
			1 =>
			array(
				'httpMethod'     => 'POST',
				'summary'        => 'Create one record by identifier.',
				'nickname'       => 'createRecord',
				'responseClass'  => 'Record',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the resource to create.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					2 =>
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'dataType'      => 'Record',
						'paramType'     => 'body',
						'required'      => true,
					),
					4 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Post data should be an array of fields for a single record. Use the \'fields\' parameter to return more properties. By default, the id is returned.',
			),
			2 =>
			array(
				'httpMethod'     => 'PUT',
				'summary'        => 'Update (replace) one record by identifier.',
				'nickname'       => 'updateRecord',
				'responseClass'  => 'Record',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the resource to update.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					2 =>
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'record',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'dataType'      => 'Record',
						'paramType'     => 'body',
						'required'      => true,
					),
					4 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table or record does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Post data should be an array of fields for a single record. Use the \'fields\' parameter to return more properties. By default, the id is returned.',
			),
			3 =>
			array(
				'httpMethod'     => 'PATCH',
				'summary'        => 'Update (merge) one record by identifier.',
				'nickname'       => 'mergeRecord',
				'responseClass'  => 'Record',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'table_name',
						'description'   => 'The name of the table you want to update.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the resource to update.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					2 =>
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'record',
						'description'   => 'An array of record properties.',
						'allowMultiple' => false,
						'dataType'      => 'Table',
						'paramType'     => 'body',
						'required'      => true,
					),
					4 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table or record does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Post data should be an array of fields for a single record. Use the \'fields\' parameter to return more properties. By default, the id is returned.',
			),
			4 =>
			array(
				'httpMethod'     => 'DELETE',
				'summary'        => 'Delete one record by identifier.',
				'nickname'       => 'deleteRecord',
				'responseClass'  => 'Record',
				'parameters'     =>
				array(
					0 =>
					array(
						'name'          => 'table_name',
						'description'   => 'Name of the table to perform operations on.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					1 =>
					array(
						'name'          => 'id',
						'description'   => 'Identifier of the resource to delete.',
						'allowMultiple' => false,
						'dataType'      => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
					2 =>
					array(
						'name'          => 'id_field',
						'description'   => 'Comma-delimited list of the fields used as identifiers or primary keys for the table.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
					3 =>
					array(
						'name'          => 'fields',
						'description'   => 'Comma-delimited list of field names to retrieve for each record.',
						'allowMultiple' => true,
						'dataType'      => 'string',
						'paramType'     => 'query',
						'required'      => false,
					),
				),
				'errorResponses' =>
				array(
					0 =>
					array(
						'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'   => 400,
					),
					1 =>
					array(
						'reason' => 'Unauthorized Access - No currently valid session available.',
						'code'   => 401,
					),
					2 =>
					array(
						'reason' => 'Not Found - Requested table or record does not exist.',
						'code'   => 404,
					),
					3 =>
					array(
						'reason' => 'System Error - Specific reason is included in the error message.',
						'code'   => 500,
					),
				),
				'notes'          => 'Use the \'fields\' parameter to return deleted properties. By default, the id is returned.',
			),
		),
		'description' => 'Operations for single record administration.',
	),
);

$_models = array(
	'Tables'  =>
	array(
		'id'         => 'Tables',
		'properties' =>
		array(
			'table' =>
			array(
				'type'        => 'Array',
				'description' => 'Array of tables and their properties.',
				'items'       =>
				array(
					'$ref' => 'Table',
				),
			),
		),
	),
	'Table'   =>
	array(
		'id'         => 'Table',
		'properties' =>
		array(
			'name' =>
			array(
				'type'        => 'string',
				'description' => 'Name of the table.',
			),
		),
	),
	'Records' =>
	array(
		'id'         => 'Records',
		'properties' =>
		array(
			'record' =>
			array(
				'type'        => 'Array',
				'description' => 'Array of records of the given resource.',
				'items'       =>
				array(
					'$ref' => 'Record',
				),
			),
			'meta'   =>
			array(
				'type'        => 'MetaData',
				'description' => 'Available meta data for the response.',
			),
		),
	),
	'Record'  =>
	array(
		'id'         => 'Record',
		'properties' =>
		array(
			'field' =>
			array(
				'type'        => 'Array',
				'description' => 'Example field name-value pairs.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
		),
	),
);

$_base['models'] = array_merge( $_base['models'], $_models );

return $_base;