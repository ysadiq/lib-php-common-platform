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
use DreamFactory\Platform\Services\SwaggerManager;

$_commonResponses = SwaggerManager::getCommonResponses( array( 400, 401, 500 ) );

$_eventProperties = array_merge(
    array(
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
            'type'        => 'array',
            'description' => 'An array of listeners attached to this event.',
        ),
    ),
    SwaggerManager::getCommonProperties()
);

$_event = array(
    'apis'   => array(
        array(
            'path'        => '/{api_name}/event',
            'operations'  => array(
                array(
                    'method'           => 'GET',
                    'summary'          => 'getEvents() - Retrieve one or more events/listeners.',
                    'nickname'         => 'getEvents',
                    'type'             => 'EventsResponse',
                    'event_name'       => array( '{api_name}.events.list' ),
                    'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                    'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                    'parameters'       => array(
                        array(
                            'name'          => 'all_events',
                            'description'   => 'If set to true, all events that are available are returned. Otherwise only events that are have registered listeners are returned.',
                            'allowMultiple' => false,
                            'type'          => 'boolean',
                            'paramType'     => 'query',
                            'required'      => false,
                        ),
                        array(
                            'name'          => 'as_cached',
                            'description'   => 'If set to true, the returned structure is identical the stored structure. If false, a simpler form is returned for client consumption.',
                            'allowMultiple' => false,
                            'type'          => 'boolean',
                            'paramType'     => 'query',
                            'required'      => false,
                        ),
                    ),
                    'responseMessages' => $_commonResponses,
                ),
                array(
                    'method'           => 'POST',
                    'summary'          => 'createEvents() - Register one or more event listeners.',
                    'nickname'         => 'createEvents',
                    'type'             => 'EventsResponse',
                    'event_name'       => array( '{api_name}.events.create' ),
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
                    ),
                    'responseMessages' => $_commonResponses,
                    'notes'            =>
                        'Post data should be a single record or an array of records (shown). ' .
                        'By default, only the id property of the record affected is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                ),
                array(
                    'method'           => 'PATCH',
                    'summary'          => 'updateEvents() - Update one or more event listeners.',
                    'nickname'         => 'updateEvents',
                    'type'             => 'EventsResponse',
                    'event_name'       => array( '{api_name}.events.update' ),
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
                    ),
                    'responseMessages' => $_commonResponses,
                    'notes'            =>
                        'Post data should be a single record or an array of records (shown). ' .
                        'By default, only the id property of the record is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                ),
                array(
                    'method'           => 'DELETE',
                    'summary'          => 'deleteEvents() - Delete one or more event listeners.',
                    'nickname'         => 'deleteEvents',
                    'type'             => 'EventsResponse',
                    'event_name'       => array( '{api_name}.events.delete' ),
                    'parameters'       => array(
                        array(
                            'name'          => 'body',
                            'description'   => 'Data containing name-value pairs of records to create.',
                            'allowMultiple' => false,
                            'type'          => 'EventsRequest',
                            'paramType'     => 'body',
                            'required'      => true,
                        ),
                    ),
                    'responseMessages' => $_commonResponses,
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
                    'event_name'       => array( '{api_name}.event.read' ),
                    'parameters'       => array(
                        array(
                            'name'          => 'id',
                            'description'   => 'The event ID',
                            'allowMultiple' => false,
                            'type'          => 'string',
                            'paramType'     => 'path',
                            'required'      => true,
                        ),
                    ),
                    'responseMessages' => $_commonResponses,
                ),
                array(
                    'method'           => 'PATCH',
                    'summary'          => 'updateEvent() - Update one event listeners.',
                    'nickname'         => 'updateEvent',
                    'type'             => 'EventResponse',
                    'event_name'       => array( '{api_name}.event.update' ),
                    'parameters'       => array(
                        array(
                            'name'          => 'id',
                            'description'   => 'The event ID',
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
                    ),
                    'responseMessages' => $_commonResponses,
                ),
                array(
                    'method'           => 'DELETE',
                    'summary'          => 'deleteEvent() - Delete one event listener.',
                    'nickname'         => 'deleteEvent',
                    'type'             => 'EventResponse',
                    'event_name'       => array( '{api_name}.event.delete' ),
                    'parameters'       => array(
                        array(
                            'name'          => 'id',
                            'description'   => 'The event ID',
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
                    ),
                    'responseMessages' => $_commonResponses,
                ),
            ),
            'description' => 'Operations for individual event administration.',
        ),
    ),
    'models' => array(
        'EventRequest'   => array(
            'id'         => 'EventRequest',
            'properties' => $_eventProperties,
        ),
        'EventsRequest'  => array(
            'id'         => 'EventsRequest',
            'properties' => array(
                'record' => array(
                    'type'        => 'array',
                    'description' => 'Array of system event records.',
                    'items'       => array(
                        '$ref' => 'EventRequest',
                    ),
                ),
            ),
        ),
        'EventResponse'  => array(
            'id'         => 'EventResponse',
            'properties' => $_eventProperties,
        ),
        'EventsResponse' => array(
            'id'         => 'EventsResponse',
            'properties' => array(
                'record' => array(
                    'type'        => 'array',
                    'description' => 'Array of event records.',
                    'items'       => array(
                        '$ref' => 'EventResponse',
                    ),
                ),
                'meta'   => array(
                    'type'        => 'Metadata',
                    'description' => 'Array of metadata returned for GET requests.',
                ),
            ),
        ),
    ),
);

return $_event;
