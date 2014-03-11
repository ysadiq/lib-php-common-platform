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

$_event = array();

$_event['apis'] = array(
	array(
		'path'        => '/{api_name}/event',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'getEvents() - Retrieve one or more events.',
				'nickname'         => 'getEvents',
				'type'             => 'EventsResponse',
				'event_name'       => 'events.list',
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
				'summary'          => 'createEvents() - Create one or more events.',
				'nickname'         => 'createEvents',
				'type'             => 'EventsResponse',
				'event_name'       => 'events.create',
				'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
				'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
				'parameters'       => array(
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of records to create.',
						'allowMultiple' => false,
						'type'          => 'EventsRequest',
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
				'summary'          => 'updateEvents() - Update one or more events.',
				'nickname'         => 'updateEvents',
				'type'             => 'EventsResponse',
				'event_name'       => 'events.update',
				'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
				'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
				'parameters'       => array(
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of records to update.',
						'allowMultiple' => false,
						'type'          => 'EventsRequest',
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
				'summary'          => 'deleteEvents() - Delete one or more events.',
				'nickname'         => 'deleteEvents',
				'type'             => 'EventsResponse',
				'event_name'       => 'events.delete',
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
		'description' => 'Operations for event administration.',
	),
	array(
		'path'        => '/{api_name}/event/{id}',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'getEvent() - Retrieve one event.',
				'nickname'         => 'getEvent',
				'type'             => 'EventResponse',
				'event_name'       => 'event.read',
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
				'summary'          => 'updateEvent() - Update one event.',
				'nickname'         => 'updateEvent',
				'type'             => 'EventResponse',
				'event_name'       => 'event.update',
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
						'type'          => 'EventRequest',
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
				'summary'          => 'deleteEvent() - Delete one event.',
				'nickname'         => 'deleteEvent',
				'type'             => 'EventResponse',
				'event_name'       => 'event.delete',
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
		'description' => 'Operations for individual event administration.',
	),
);

$_commonProperties = array(
	'id'         => array(
		'type'        => 'integer',
		'format'      => 'int32',
		'description' => 'Identifier of this event.',
	),
	'event_name' => array(
		'type'        => 'string',
		'description' => 'The name of this event',
	),
	'listeners'  => array(
		'type'        => 'Array',
		'description' => 'An array of listeners attached to this event.',
	),
);

$_stampProperties = array(
	'created_date'        => array(
		'type'        => 'string',
		'description' => 'Date this event was created.',
		'readOnly'    => true,
	),
	'created_by_id'       => array(
		'type'        => 'integer',
		'format'      => 'int32',
		'description' => 'User Id of who created this event.',
		'readOnly'    => true,
	),
	'last_modified_date'  => array(
		'type'        => 'string',
		'description' => 'Date this event was last modified.',
		'readOnly'    => true,
	),
	'last_modified_by_id' => array(
		'type'        => 'integer',
		'format'      => 'int32',
		'description' => 'User Id of who last modified this event.',
		'readOnly'    => true,
	),
);

$_event['models'] = array(
	'EventRequest'   => array(
		'id'         => 'EventRequest',
		'properties' => array_merge(
			$_commonProperties,
			$_relatedProperties
		)
	),
	'EventsRequest'  => array(
		'id'         => 'EventsRequest',
		'properties' => array(
			'record' => array(
				'type'        => 'Array',
				'description' => 'Array of system event records.',
				'items'       => array(
					'type' => 'string',
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
	'EventResponse'  => array(
		'id'         => 'EventResponse',
		'properties' => array_merge(
			$_commonProperties,
			$_stampProperties
		),
	),
	'EventsResponse' => array(
		'id'         => 'EventsResponse',
		'properties' => array(
			'record' => array(
				'type'        => 'Array',
				'description' => 'Array of system event records.',
				'items'       => array(
					'type' => 'string',
				),
			),
			'meta'   => array(
				'type'        => 'Metadata',
				'description' => 'Array of metadata returned for GET requests.',
			),
		),
	),
);

return $_event;
