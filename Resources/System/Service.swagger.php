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

$_service = array();

$_service['apis'] = array(
	array(
		'path'        => '/{api_name}/service',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'getServices() - Retrieve one or more services.',
				'nickname'         => 'getServices',
				'type'             => 'ServicesResponse',
				'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
				'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
				'parameters'       => array(
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
				'responseMessages' => array(
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
				'notes'            =>
					'Use the \'ids\' or \'filter\' parameter to limit records that are returned. ' .
					'By default, all records up to the maximum are returned. <br>' .
					'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
					'By default, all fields and no relations are returned for each record. <br>' .
					'Alternatively, to retrieve by record, a large list of ids, or a complicated filter, ' .
					'use the POST request with X-HTTP-METHOD = GET header and post records or ids.',
			),
			array(
				'method'           => 'POST',
				'summary'          => 'createServices() - Create one or more services.',
				'nickname'         => 'createServices',
				'type'             => 'ServicesResponse',
				'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
				'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
				'parameters'       => array(
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'type'          => 'ServicesRequest',
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
				'responseMessages' => array(
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
				'notes'            =>
					'Post data should be a single record or an array of records (shown). ' .
					'By default, only the id property of the record affected is returned on success, ' .
					'use \'fields\' and \'related\' to return more info.',
			),
			array(
				'method'           => 'PATCH',
				'summary'          => 'updateServices() - Update one or more services.',
				'nickname'         => 'updateServices',
				'type'             => 'ServicesResponse',
				'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
				'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
				'parameters'       => array(
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'ServicesRequest',
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
				'responseMessages' => array(
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
				'notes'            =>
					'Post data should be a single record or an array of records (shown). ' .
					'By default, only the id property of the record is returned on success, ' .
					'use \'fields\' and \'related\' to return more info.',
			),
			array(
				'method'           => 'DELETE',
				'summary'          => 'deleteServices() - Delete one or more services.',
				'nickname'         => 'deleteServices',
				'type'             => 'ServicesResponse',
				'parameters'       => array(
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
				'responseMessages' => array(
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
				'notes'            =>
					'By default, only the id property of the record deleted is returned on success. ' .
					'Use \'fields\' and \'related\' to return more properties of the deleted records. <br>' .
					'Alternatively, to delete by record or a large list of ids, ' .
					'use the POST request with X-HTTP-METHOD = DELETE header and post records or ids.',
			),
		),
		'description' => 'Operations for service administration.',
	),
	array(
		'path'        => '/{api_name}/service/{id}',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'getService() - Retrieve one service.',
				'nickname'         => 'getService',
				'type'             => 'ServiceResponse',
				'parameters'       => array(
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
				'responseMessages' => array(
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
				'summary'          => 'updateService() - Update one service.',
				'nickname'         => 'updateService',
				'type'             => 'ServiceResponse',
				'parameters'       => array(
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
						'type'          => 'ServiceRequest',
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
				'responseMessages' => array(
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
				'notes'            =>
					'Post data should be an array of fields to update for a single record. <br>' .
					'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return more properties.',
			),
			array(
				'method'           => 'DELETE',
				'summary'          => 'deleteService() - Delete one service.',
				'nickname'         => 'deleteService',
				'type'             => 'ServiceResponse',
				'parameters'       => array(
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
				'responseMessages' => array(
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
		'description' => 'Operations for individual service administration.',
	),
);

$_commonProperties = array(
	'id'              => array(
		'type'        => 'integer',
		'format'      => 'int32',
		'description' => 'Identifier of this service.',
	),
	'name'            => array(
		'type'        => 'string',
		'description' => 'Displayable name of this service.',
	),
	'api_name'        => array(
		'type'        => 'string',
		'description' => 'Name of the service to use in API transactions.',
	),
	'description'     => array(
		'type'        => 'string',
		'description' => 'Description of this service.',
	),
	'is_active'       => array(
		'type'        => 'boolean',
		'description' => 'True if this service is active for use.',
	),
	'type'            => array(
		'type'        => 'string',
		'description' => 'One of the supported service types.',
		'deprecated'  => true,
	),
	'type_id'         => array(
		'type'        => 'integer',
		'format'      => 'int32',
		'description' => 'One of the supported enumerated service types.',
	),
	'storage_name'    => array(
		'type'        => 'string',
		'description' => 'The local or remote storage name (i.e. root folder).',
	),
	'storage_type'    => array(
		'type'        => 'string',
		'description' => 'They supported storage service type.',
		'deprecated'  => true,
	),
	'storage_type_id' => array(
		'type'        => 'integer',
		'format'      => 'int32',
		'description' => 'One of the supported enumerated storage service types.',
	),
	'credentials'     => array(
		'type'        => 'string',
		'description' => 'Any credentials data required by the service.',
	),
	'native_format'   => array(
		'type'        => 'string',
		'description' => 'The format of the returned data of the service.',
	),
	'base_url'        => array(
		'type'        => 'string',
		'description' => 'The base URL for remote web services.',
	),
	'parameters'      => array(
		'type'        => 'string',
		'description' => 'Additional URL parameters required by the service.',
	),
	'headers'         => array(
		'type'        => 'string',
		'description' => 'Additional headers required by the service.',
	),
);

$_relatedProperties = array(
	'apps'  => array(
		'type'        => 'RelatedApps',
		'description' => 'Related apps by app to service assignment.',
	),
	'roles' => array(
		'type'        => 'RelatedRoles',
		'description' => 'Related roles by service to role assignment.',
	),
);

$_stampProperties = array(
	'created_date'        => array(
		'type'        => 'string',
		'description' => 'Date this service was created.',
		'readOnly'    => true,
	),
	'created_by_id'       => array(
		'type'        => 'integer',
		'format'      => 'int32',
		'description' => 'User Id of who created this service.',
		'readOnly'    => true,
	),
	'last_modified_date'  => array(
		'type'        => 'string',
		'description' => 'Date this service was last modified.',
		'readOnly'    => true,
	),
	'last_modified_by_id' => array(
		'type'        => 'integer',
		'format'      => 'int32',
		'description' => 'User Id of who last modified this service.',
		'readOnly'    => true,
	),
);

$_service['models'] = array(
	'ServiceRequest'   => array(
		'id'         => 'ServiceRequest',
		'properties' => array_merge(
			$_commonProperties,
			$_relatedProperties
		)
	),
	'ServicesRequest'  => array(
		'id'         => 'ServicesRequest',
		'properties' => array(
			'record' => array(
				'type'        => 'Array',
				'description' => 'Array of system service records.',
				'items'       => array(
					'$ref' => 'ServiceRequest',
				),
			),
			'ids'    => array(
				'type'        => 'Array',
				'description' => 'Array of system record identifiers, used for batch GET, PUT, PATCH, and DELETE.',
				'items'       => array(
					'type'   => 'integer',
					'format' => 'int32',
				),
			),
		),
	),
	'ServiceResponse'  => array(
		'id'         => 'ServiceResponse',
		'properties' => array_merge(
			$_commonProperties,
			$_relatedProperties,
			$_stampProperties,
			array(
				'is_system' => array(
					'type'        => 'boolean',
					'description' => 'True if this service is a default system service.',
				),
			)
		),
	),
	'ServicesResponse' => array(
		'id'         => 'ServicesResponse',
		'properties' => array(
			'record' => array(
				'type'        => 'Array',
				'description' => 'Array of system service records.',
				'items'       => array(
					'$ref' => 'ServiceResponse',
				),
			),
			'meta'   => array(
				'type'        => 'Metadata',
				'description' => 'Array of metadata returned for GET requests.',
			),
		),
	),
	'RelatedService'   => array(
		'id'         => 'RelatedService',
		'properties' => array_merge(
			$_commonProperties,
			$_stampProperties
		)
	),
	'RelatedServices'  => array(
		'id'         => 'RelatedServices',
		'properties' => array(
			'record' => array(
				'type'        => 'Array',
				'description' => 'Array of system service records.',
				'items'       => array(
					'$ref' => 'RelatedService',
				),
			),
			'meta'   => array(
				'type'        => 'Metadata',
				'description' => 'Array of metadata returned for GET requests.',
			),
		),
	),
);

return $_service;
