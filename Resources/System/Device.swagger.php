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

$_device = array();

$_device['apis'] = array(
	array(
		'path'        => '/{api_name}/device',
		'operations'  =>
			array(
				array(
					'method'           => 'GET',
					'summary'          => 'getDevices() - Retrieve one or more devices.',
					'nickname'         => 'getDevices',
					'type'             => 'DevicesResponse',
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
					'method'           => 'DELETE',
					'summary'          => 'deleteDevices() - Delete one or more devices.',
					'nickname'         => 'deleteDevices',
					'type'             => 'DevicesResponse',
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
		'description' => 'Operations for device administration.',
	),
	array(
		'path'        => '/{api_name}/device/{id}',
		'operations'  =>
			array(
				array(
					'method'           => 'GET',
					'summary'          => 'getDevice() - Retrieve one device.',
					'nickname'         => 'getDevice',
					'type'             => 'DeviceResponse',
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
					'method'           => 'DELETE',
					'summary'          => 'deleteDevice() - Delete one device.',
					'nickname'         => 'deleteDevice',
					'type'             => 'DeviceResponse',
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
		'description' => 'Operations for individual device administration.',
	),
);

$_commonProperties = array(
	'id'       =>
		array(
			'type'        => 'integer',
			'format'      => 'int32',
			'description' => 'Identifier of this device.',
		),
	'uuid'     =>
		array(
			'type'        => 'string',
			'description' => 'Unique ID generated by the device.',
		),
	'platform' =>
		array(
			'type'        => 'string',
			'description' => 'Platform information of the device.',
		),
	'version'  =>
		array(
			'type'        => 'string',
			'description' => 'Version information of the device.',
		),
	'model'    =>
		array(
			'type'        => 'string',
			'description' => 'Model information of the device.',
		),
	'extra'    =>
		array(
			'type'        => 'string',
			'description' => 'Extra information from the device.',
		),
	'user_id'  =>
		array(
			'type'        => 'integer',
			'format'      => 'int32',
			'description' => 'Id of the User using this device.',
		),
	'user'     =>
		array(
			'type'        => 'RelatedUser',
			'description' => 'Related user by user_id.',
		),
);

$_device['models'] = array(
	'DeviceRequest'   =>
		array(
			'id'         => 'DeviceRequest',
			'properties' => $_commonProperties,
		),
	'DeviceResponse'  =>
		array(
			'id'         => 'DeviceResponse',
			'properties' =>
				array_merge(
					$_commonProperties,
					array(
						 'created_date'       =>
							 array(
								 'type'        => 'string',
								 'description' => 'Date this device was created.',
							 ),
						 'last_modified_date' =>
							 array(
								 'type'        => 'string',
								 'description' => 'Date this device was last modified.',
							 ),
					)
				),
		),
	'DevicesRequest'  =>
		array(
			'id'         => 'DevicesRequest',
			'properties' =>
				array(
					'record' =>
						array(
							'type'        => 'Array',
							'description' => 'Array of system device records.',
							'items'       =>
								array(
									'$ref' => 'DeviceRequest',
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
	'DevicesResponse' =>
		array(
			'id'         => 'DevicesResponse',
			'properties' =>
				array(
					'record' =>
						array(
							'type'        => 'Array',
							'description' => 'Array of system device records.',
							'items'       =>
								array(
									'$ref' => 'DeviceResponse',
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

return $_device;
