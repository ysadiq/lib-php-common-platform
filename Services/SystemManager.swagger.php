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

return array(
	'resourcePath' => '/system',
	'apis'         =>
	array(
		0  =>
		array(
			'path'        => '/system',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'    => 'GET',
					'summary'       => 'List resources available for system management.',
					'nickname'      => 'getResources',
					'responseClass' => 'Resources',
					'notes'         => 'See listed operations for each resource available.',
				),
			),
			'description' => 'Operations available for system management.',
		),
		1  =>
		array(
			'path'        => '/system/app',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve multiple applications.',
					'nickname'       => 'getApps',
					'responseClass'  => 'Apps',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'filter',
							'description'   => 'SQL-like filter to limit the records to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'limit',
							'description'   => 'Set to limit the filter results.',
							'allowMultiple' => false,
							'dataType'      => 'int',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'order',
							'description'   => 'SQL-like order containing field and direction for filter results.',
							'allowMultiple' => false,
							'dataType'      => 'string',
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
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						6 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'name'          => 'include_schema',
							'description'   => 'Include the schema of the table queried.',
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
					'notes'          => 'Use the \'ids\' or \'filter\' parameter to limit records that are returned. Use the \'fields\' and \'related\' parameters to limit properties returned for each record. By default, all fields and no relations are returned for all records.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Create one or more applications.',
					'nickname'       => 'createApps',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to create.',
							'allowMultiple' => false,
							'dataType'      => 'Apps',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				2 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one or more applications.',
					'nickname'       => 'updateApps',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to update.',
							'allowMultiple' => false,
							'dataType'      => 'Apps',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				3 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one or more applications.',
					'nickname'       => 'deleteApps',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to delete.',
							'allowMultiple' => false,
							'dataType'      => 'Apps',
							'paramType'     => 'body',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use \'ids\' or post data should be a single record or an array of records (shown) containing an id. By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
			),
			'description' => 'Operations for application administration.',
		),
		2  =>
		array(
			'path'        => '/system/app/{id}',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve one application by identifier.',
					'nickname'       => 'getApp',
					'responseClass'  => 'App',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
				),
				1 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one application.',
					'nickname'       => 'updateApp',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
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
							'dataType'      => 'App',
							'paramType'     => 'body',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be an array of fields for a single record. Use the \'fields\' and/or \'related\' parameter to return more properties. By default, the id is returned.',
				),
				2 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one application.',
					'nickname'       => 'deleteApp',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to return deleted properties. By default, the id is returned.',
				),
			),
			'description' => 'Operations for individual application administration.',
		),
		3  =>
		array(
			'path'        => '/system/app_group',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve multiple application groups.',
					'nickname'       => 'getAppGroups',
					'responseClass'  => 'AppGroups',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'filter',
							'description'   => 'SQL-like filter to limit the records to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'limit',
							'description'   => 'Set to limit the filter results.',
							'allowMultiple' => false,
							'dataType'      => 'int',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'order',
							'description'   => 'SQL-like order containing field and direction for filter results.',
							'allowMultiple' => false,
							'dataType'      => 'string',
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
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						6 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'name'          => 'include_schema',
							'description'   => 'Include the schema of the table queried.',
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
					'notes'          => 'Use the \'ids\' or \'filter\' parameter to limit records that are returned. Use the \'fields\' and \'related\' parameters to limit properties returned for each record. By default, all fields and no relations are returned for all records.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Create one or more application groups.',
					'nickname'       => 'createAppGroups',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to create.',
							'allowMultiple' => false,
							'dataType'      => 'AppGroups',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				2 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one or more application groups.',
					'nickname'       => 'updateAppGroups',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to update.',
							'allowMultiple' => false,
							'dataType'      => 'AppGroups',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				3 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one or more application groups.',
					'nickname'       => 'deleteAppGroups',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to delete.',
							'allowMultiple' => false,
							'dataType'      => 'AppGroups',
							'paramType'     => 'body',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use \'ids\' or post data should be a single record or an array of records (shown) containing an id. By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
			),
			'description' => 'Operations for application group administration.',
		),
		4  =>
		array(
			'path'        => '/system/app_group/{id}',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve one application group by identifier.',
					'nickname'       => 'getAppGroup',
					'responseClass'  => 'AppGroup',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
				),
				1 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one application group.',
					'nickname'       => 'updateAppGroup',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
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
							'dataType'      => 'AppGroup',
							'paramType'     => 'body',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be an array of fields for a single record. Use the \'fields\' and/or \'related\' parameter to return more properties. By default, the id is returned.',
				),
				2 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one application group.',
					'nickname'       => 'deleteAppGroup',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to return deleted properties. By default, the id is returned.',
				),
			),
			'description' => 'Operations for individual application group administration.',
		),
		5  =>
		array(
			'path'        => '/system/config',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'    => 'GET',
					'summary'       => 'Retrieve system configuration options.',
					'nickname'      => 'getConfig',
					'responseClass' => 'Config',
					'notes'         => 'The retrieved properties control how the system behaves.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Update one or more system configuration properties.',
					'nickname'       => 'setConfig',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'config',
							'description'   => 'Data containing name-value pairs of properties to set.',
							'allowMultiple' => false,
							'dataType'      => 'Config',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be an array of properties.',
				),
			),
			'description' => 'Operations for system configuration options.',
		),
		6  =>
		array(
			'path'        => '/system/email_template',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve multiple email templates.',
					'nickname'       => 'getEmailTemplates',
					'responseClass'  => 'EmailTemplates',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'filter',
							'description'   => 'SQL-like filter to limit the records to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'limit',
							'description'   => 'Set to limit the filter results.',
							'allowMultiple' => false,
							'dataType'      => 'int',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'order',
							'description'   => 'SQL-like order containing field and direction for filter results.',
							'allowMultiple' => false,
							'dataType'      => 'string',
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
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						6 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'name'          => 'include_schema',
							'description'   => 'Include the schema of the table queried.',
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
					'notes'          => 'Use the \'ids\' or \'filter\' parameter to limit records that are returned. Use the \'fields\' and \'related\' parameters to limit properties returned for each record. By default, all fields and no relations are returned for all records.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Create one or more email templates.',
					'nickname'       => 'createEmailTemplates',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to create.',
							'allowMultiple' => false,
							'dataType'      => 'EmailTemplates',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				2 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one or more email templates.',
					'nickname'       => 'updateEmailTemplates',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to update.',
							'allowMultiple' => false,
							'dataType'      => 'EmailTemplates',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				3 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one or more email templates.',
					'nickname'       => 'deleteEmailTemplates',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to delete.',
							'allowMultiple' => false,
							'dataType'      => 'EmailTemplates',
							'paramType'     => 'body',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use \'ids\' or post data should be a single record or an array of records (shown) containing an id. By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
			),
			'description' => 'Operations for email template administration.',
		),
		7  =>
		array(
			'path'        => '/system/email_template/{id}',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve one application by identifier.',
					'nickname'       => 'getEmailTemplate',
					'responseClass'  => 'EmailTemplate',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
				),
				1 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one email template.',
					'nickname'       => 'updateEmailTemplate',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
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
							'dataType'      => 'EmailTemplate',
							'paramType'     => 'body',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be an array of fields for a single record. Use the \'fields\' and/or \'related\' parameter to return more properties. By default, the id is returned.',
				),
				2 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one email template.',
					'nickname'       => 'deleteEmailTemplate',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to return deleted properties. By default, the id is returned.',
				),
			),
			'description' => 'Operations for individual email template administration.',
		),
		8  =>
		array(
			'path'        => '/system/role',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve multiple roles.',
					'nickname'       => 'getRoles',
					'responseClass'  => 'Roles',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'filter',
							'description'   => 'SQL-like filter to limit the records to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'limit',
							'description'   => 'Set to limit the filter results.',
							'allowMultiple' => false,
							'dataType'      => 'int',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'order',
							'description'   => 'SQL-like order containing field and direction for filter results.',
							'allowMultiple' => false,
							'dataType'      => 'string',
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
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						6 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'name'          => 'include_schema',
							'description'   => 'Include the schema of the table queried.',
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
					'notes'          => 'Use the \'ids\' or \'filter\' parameter to limit records that are returned. Use the \'fields\' and \'related\' parameters to limit properties returned for each record. By default, all fields and no relations are returned for all records.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Create one or more roles.',
					'nickname'       => 'createRoles',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to create.',
							'allowMultiple' => false,
							'dataType'      => 'Roles',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				2 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one or more roles.',
					'nickname'       => 'updateRoles',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to update.',
							'allowMultiple' => false,
							'dataType'      => 'Roles',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				3 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one or more roles.',
					'nickname'       => 'deleteRoles',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to delete.',
							'allowMultiple' => false,
							'dataType'      => 'Roles',
							'paramType'     => 'body',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use \'ids\' or post data should be a single record or an array of records (shown) containing an id. By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
			),
			'description' => 'Operations for role administration.',
		),
		9  =>
		array(
			'path'        => '/system/role/{id}',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve one role by identifier.',
					'nickname'       => 'getRole',
					'responseClass'  => 'Role',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
				),
				1 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one role.',
					'nickname'       => 'updateRole',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
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
							'dataType'      => 'Role',
							'paramType'     => 'body',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be an array of fields for a single record. Use the \'fields\' and/or \'related\' parameter to return more properties. By default, the id is returned.',
				),
				2 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Update one role.',
					'nickname'       => 'deleteRole',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to return deleted properties. By default, the id is returned.',
				),
			),
			'description' => 'Operations for individual role administration.',
		),
		10 =>
		array(
			'path'        => '/system/service',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve multiple services.',
					'nickname'       => 'getServices',
					'responseClass'  => 'Services',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'filter',
							'description'   => 'SQL-like filter to limit the records to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'limit',
							'description'   => 'Set to limit the filter results.',
							'allowMultiple' => false,
							'dataType'      => 'int',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'order',
							'description'   => 'SQL-like order containing field and direction for filter results.',
							'allowMultiple' => false,
							'dataType'      => 'string',
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
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						6 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'name'          => 'include_schema',
							'description'   => 'Include the schema of the table queried.',
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
					'notes'          => 'Use the \'ids\' or \'filter\' parameter to limit records that are returned. Use the \'fields\' and \'related\' parameters to limit properties returned for each record. By default, all fields and no relations are returned for all records.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Create one or more services.',
					'nickname'       => 'createServices',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to create.',
							'allowMultiple' => false,
							'dataType'      => 'Services',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				2 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one or more services.',
					'nickname'       => 'updateServices',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to update.',
							'allowMultiple' => false,
							'dataType'      => 'Services',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				3 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one or more services.',
					'nickname'       => 'deleteServices',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to delete.',
							'allowMultiple' => false,
							'dataType'      => 'Services',
							'paramType'     => 'body',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use \'ids\' or post data should be a single record or an array of records (shown) containing an id. By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
			),
			'description' => 'Operations for service administration.',
		),
		11 =>
		array(
			'path'        => '/system/service/{id}',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve one service by identifier.',
					'nickname'       => 'getService',
					'responseClass'  => 'Service',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
				),
				1 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one service.',
					'nickname'       => 'updateService',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
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
							'dataType'      => 'Service',
							'paramType'     => 'body',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be an array of fields for a single record. Use the \'fields\' and/or \'related\' parameter to return more properties. By default, the id is returned.',
				),
				2 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one service.',
					'nickname'       => 'deleteService',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to return deleted properties. By default, the id is returned.',
				),
			),
			'description' => 'Operations for individual service administration.',
		),
		12 =>
		array(
			'path'        => '/system/user',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve multiple users.',
					'nickname'       => 'getUsers',
					'responseClass'  => 'Users',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'filter',
							'description'   => 'SQL-like filter to limit the records to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'limit',
							'description'   => 'Set to limit the filter results.',
							'allowMultiple' => false,
							'dataType'      => 'int',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'order',
							'description'   => 'SQL-like order containing field and direction for filter results.',
							'allowMultiple' => false,
							'dataType'      => 'string',
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
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						6 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'name'          => 'include_schema',
							'description'   => 'Include the schema of the table queried.',
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
					'notes'          => 'Use the \'ids\' or \'filter\' parameter to limit records that are returned. Use the \'fields\' and \'related\' parameters to limit properties returned for each record. By default, all fields and no relations are returned for all records.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Create one or more users.',
					'nickname'       => 'createUsers',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to create.',
							'allowMultiple' => false,
							'dataType'      => 'Users',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				2 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one or more users.',
					'nickname'       => 'updateUsers',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to update.',
							'allowMultiple' => false,
							'dataType'      => 'Users',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
				3 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one or more users.',
					'nickname'       => 'deleteUsers',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'ids',
							'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						1 =>
						array(
							'name'          => 'record',
							'description'   => 'Data containing name-value pairs of records to delete.',
							'allowMultiple' => false,
							'dataType'      => 'Users',
							'paramType'     => 'body',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use \'ids\' or post data should be a single record or an array of records (shown) containing an id. By default, only the id property of the record is returned on success, use \'fields\' and \'related\' to return more info.',
				),
			),
			'description' => 'Operations for user administration.',
		),
		13 =>
		array(
			'path'        => '/system/user/{id}',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Retrieve one user by identifier.',
					'nickname'       => 'getUser',
					'responseClass'  => 'User',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
				),
				1 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update one user.',
					'nickname'       => 'updateUser',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
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
							'dataType'      => 'User',
							'paramType'     => 'body',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be an array of fields for a single record. Use the \'fields\' and/or \'related\' parameter to return more properties. By default, the id is returned.',
				),
				2 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one user.',
					'nickname'       => 'deleteUser',
					'responseClass'  => 'Success',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'id',
							'description'   => 'Identifier of the record to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'fields',
							'description'   => 'Comma-delimited list of field names to retrieve for each record.',
							'allowMultiple' => true,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'related',
							'description'   => 'Comma-delimited list of related names to retrieve for each record.',
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
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use the \'fields\' and/or \'related\' parameter to return deleted properties. By default, the id is returned.',
				),
			),
			'description' => 'Operations for individual user administration.',
		),
	),
	'models'       =>
	array(
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
		'Success'        =>
		array(
			'id'         => 'Success',
			'properties' =>
			array(
				'success' =>
				array(
					'type' => 'boolean',
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
					'type'        => 'int',
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
					'type'        => 'int',
					'description' => 'User Id of who created this user.',
				),
				'last_modified_date'  =>
				array(
					'type'        => 'string',
					'description' => 'Date this user was last modified.',
				),
				'last_modified_by_id' =>
				array(
					'type'        => 'int',
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
					'type'        => 'int',
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
					'type'        => 'int',
					'description' => 'User Id of who created this application.',
				),
				'last_modified_date'      =>
				array(
					'type'        => 'string',
					'description' => 'Date this application was last modified.',
				),
				'last_modified_by_id'     =>
				array(
					'type'        => 'int',
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
					'type'        => 'int',
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
					'type'        => 'int',
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
					'type'        => 'int',
					'description' => 'User Id of who created this role.',
				),
				'last_modified_date'  =>
				array(
					'type'        => 'string',
					'description' => 'Date this role was last modified.',
				),
				'last_modified_by_id' =>
				array(
					'type'        => 'int',
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
					'type'        => 'int',
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
					'type'        => 'int',
					'description' => 'User Id of who created this application group.',
				),
				'last_modified_date'  =>
				array(
					'type'        => 'string',
					'description' => 'Date this application group was last modified.',
				),
				'last_modified_by_id' =>
				array(
					'type'        => 'int',
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
					'type'        => 'int',
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
					'type'        => 'int',
					'description' => 'User Id of who created this service.',
				),
				'last_modified_date'  =>
				array(
					'type'        => 'string',
					'description' => 'Date this service was last modified.',
				),
				'last_modified_by_id' =>
				array(
					'type'        => 'int',
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
					'type'        => 'int',
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
					'type'        => 'int',
					'description' => 'User Id of who created this email template.',
				),
				'last_modified_date'  =>
				array(
					'type'        => 'string',
					'description' => 'Date this email template was last modified.',
				),
				'last_modified_by_id' =>
				array(
					'type'        => 'int',
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
					'type'        => 'int',
					'description' => 'Default Role Id assigned to newly registered users.',
				),
				'allow_guest_user'        =>
				array(
					'type'        => 'boolean',
					'description' => 'Allow app access for non-authenticated users.',
				),
				'guest_role_id'           =>
				array(
					'type'        => 'int',
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
		'Resources'      =>
		array(
			'id'         => 'Resources',
			'properties' =>
			array(
				'resource' =>
				array(
					'type'  => 'Array',
					'items' =>
					array(
						'$ref' => 'Resource',
					),
				),
			),
		),
		'Resource'       =>
		array(
			'id'         => 'Resource',
			'properties' =>
			array(
				'name' =>
				array(
					'type' => 'string',
				),
			),
		),
	),
);