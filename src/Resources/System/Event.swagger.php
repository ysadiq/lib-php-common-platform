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

$_eventProperties = array(
    'event_name' => array(
        'type'        => 'string',
        'description' => 'The name of this event',
        'required'    => true,
    ),
    'listeners'  => array(
        'type'        => 'array',
        'description' => 'An array of listeners attached to this event.',
        'required'    => true,
    ),
);

//*************************************************************************
//	API/Operations
//*************************************************************************

$_event = array(
    'apis' => array(
        array(
            'path'        => '/{api_name}/event',
            'operations'  => array(
                array(
                    'method'           => 'GET',
                    'summary'          => 'getEvents() - Retrieve events and registered listeners.',
                    'nickname'         => 'getEvents',
                    'type'             => 'EventCacheResponse',
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
                    'summary'          => 'registerEvents() - Register one or more event listeners.',
                    'nickname'         => 'registerEvents',
                    'type'             => 'EventsResponse',
                    'event_name'       => array( '{api_name}.events.create' ),
                    'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                    'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                    'parameters'       => array(
                        array(
                            'name'          => 'body',
                            'description'   => 'Data containing event registration records to create.',
                            'allowMultiple' => false,
                            'type'          => 'EventsRequest',
                            'paramType'     => 'body',
                            'required'      => true,
                        ),
                    ),
                    'responseMessages' => $_commonResponses,
                    'notes'            => 'Post data should be a single record or an array of records. No data is returned from this call. You will get a 201 (created) upon success.',
                ),
                array(
                    'method'           => 'DELETE',
                    'summary'          => 'unregisterEvents() - Delete one or more event listeners.',
                    'nickname'         => 'unregisterEvents',
                    'type'             => 'EventsResponse',
                    'event_name'       => array( '{api_name}.events.delete' ),
                    'parameters'       => array(
                        array(
                            'name'          => 'body',
                            'description'   => 'Data containing event registration records to delete.',
                            'allowMultiple' => false,
                            'type'          => 'EventsRequest',
                            'paramType'     => 'body',
                            'required'      => true,
                        ),
                    ),
                    'responseMessages' => $_commonResponses,
                    'notes'            => 'Post data should be a single record or an array of records. No data is returned from this call. You will get a 200 (OK) upon success.',
                ),
            ),
            'description' => 'Operations for event administration.',
        ),
        array(
            'path'        => '/{api_name}/event/{event_name}',
            'operations'  => array(
                array(
                    'method'           => 'GET',
                    'summary'          => 'getEvent() - Retrieve one event.',
                    'nickname'         => 'getEvent',
                    'type'             => 'EventResponse',
                    'event_name'       => array( '{api_name}.event.read' ),
                    'parameters'       => array(
                        array(
                            'name'          => 'event_name',
                            'description'   => 'Identifier of the record to retrieve.',
                            'allowMultiple' => false,
                            'type'          => 'string',
                            'paramType'     => 'path',
                            'required'      => true,
                        ),
                    ),
                    'responseMessages' => $_commonResponses,
                ),
                array(
                    'method'           => 'POST',
                    'summary'          => 'registerEvent() - Register one event listeners.',
                    'nickname'         => 'registerEvent',
                    'type'             => 'EventResponse',
                    'event_name'       => array( '{api_name}.event.create' ),
                    'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                    'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                    'parameters'       => array(
                        array(
                            'name'          => 'body',
                            'description'   => 'Data containing event registration record to create.',
                            'allowMultiple' => false,
                            'type'          => 'EventRequest',
                            'paramType'     => 'body',
                            'required'      => true,
                        ),
                    ),
                    'responseMessages' => $_commonResponses,
                    'notes'            => 'Post data must be a single record. No data is returned from this call. You will get a 201 (created) upon success.',
                ),
                array(
                    'method'           => 'PATCH',
                    'summary'          => 'updateEvent() - Update one listener(s) for a single event.',
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
                            'description'   => 'Data containing event registration record to update.',
                            'allowMultiple' => false,
                            'type'          => 'EventRequest',
                            'paramType'     => 'body',
                            'required'      => true,
                        ),
                    ),
                    'responseMessages' => $_commonResponses,
                    'notes'            => 'Post data must be a single record. No data is returned from this call. You will get a 200 (OK) upon success.',
                ),
                array(
                    'method'           => 'DELETE',
                    'summary'          => 'unregisterEvent() - Delete one event.',
                    'nickname'         => 'unregisterEvent',
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
                            'description'   => 'Data containing event registration record to delete.',
                            'allowMultiple' => false,
                            'type'          => 'EventRequest',
                            'paramType'     => 'body',
                            'required'      => true,
                        ),
                    ),
                    'responseMessages' => $_commonResponses,
                    'notes'            => 'Post data must be a single record. No data is returned from this call. You will get a 200 (OK) upon success.',
                ),
            ),
            'description' => 'Operations for individual event administration.',
        ),
    ),
);

//*************************************************************************
//	Models
//*************************************************************************

$_event['models'] = array(
    'EventVerbs'         => array(
        'id'         => 'EventVerbs',
        'properties' => array(
            'type'      => array(
                'type'        => 'string',
                'description' => 'The verb for this path',
                'required'    => true,
            ),
            'event'     => array(
                'type'        => 'array',
                'description' => 'An array of event names triggered by this path/verb combo',
                'required'    => true,
            ),
            'scripts'   => array(
                'type'        => 'array',
                'description' => 'An array of scripts registered to this event',
                'required'    => true,
            ),
            'listeners' => array(
                'type'        => 'array',
                'description' => 'An array of listeners registered to this event',
                'required'    => true,
            ),
        ),
    ),
    'EventPaths'         => array(
        'id'         => 'EventPaths',
        'properties' => array(
            'path'  => array(
                'type'        => 'string',
                'description' => 'The full path to which triggers this event',
                'required'    => true,
            ),
            'verbs' => array(
                'type'        => 'array',
                'description' => 'An array of path/verb combinations which contain events',
                'required'    => true,
                'items'       => array(
                    '$ref' => 'EventVerbs',
                ),
            ),
        ),
    ),
    //  Event Cache Response
    'EventCacheResponse' => array(
        'id'         => 'EventCacheResponse',
        'properties' => array(
            'name'  => array(
                'type'        => 'string',
                'description' => 'The owner API of this event',
                'required'    => true,
            ),
            'paths' => array(
                'type'        => 'array',
                'description' => 'An array of paths which trigger this event',
                'items'       => array(
                    '$ref' => 'EventPaths',
                ),
                'required'    => true,
            ),
        ),
    ),
    //  Single event
    'EventRequest'       => array(
        'id'         => 'EventRequest',
        'properties' => $_eventProperties,
    ),
    //  Multiple events
    'EventsRequest'      => array(
        'id'         => 'EventsRequest',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
                'description' => 'Array of system event records.',
                'required'    => true,
                'items'       => array(
                    '$ref' => 'EventRequest',
                ),
            ),
        ),
    ),
    //  Single event response
    'EventResponse'      => array(
        'id'         => 'EventResponse',
        'properties' => $_eventProperties,
    ),
    //  Multiple events response
    'EventsResponse'     => array(
        'id'         => 'EventsResponse',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
                'description' => 'Array of event records.',
                'required'    => true,
                'items'       => array(
                    '$ref' => 'EventResponse',
                ),
            ),
        ),
    ),
);

return $_event;
