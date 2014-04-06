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

use DreamFactory\Platform\Events\Enums\StreamEvents;
use DreamFactory\Platform\Services\SwaggerManager;

/**
 * Returns the EventStream resource description
 */
return array(
    'apis'   => array(
        array(
            'path'        => '/{api_name}/event_stream',
            'description' => 'Event stream endpoints',
            'operations'  => array(
                array(
                    'method'           => 'GET',
                    'summary'          => 'startEventStream() - Starts the DSP server-side event stream',
                    'notes'            => 'Initializes and starts the event stream to a client listener. You may specify a prior stream ID and, if found, will be reopened. If you do not specify a stream ID, a new stream is created and the stream ID is returned.',
                    'nickname'         => 'startEventStream',
                    'type'             => 'EventStreamResponse',
                    'event_name'       => StreamEvents::STREAM_STARTED,
                    'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                    'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                    'parameters'       => array(
                        array(
                            'name'          => 'stream_id',
                            'description'   => 'If specified, re-opens/re-connects to stream with the given ID, if found (404 otherwise). If no ID is specified, a new stream is created and the new ID is returned.',
                            'allowMultiple' => false,
                            'type'          => 'string',
                            'paramType'     => 'query',
                            'required'      => false,
                        ),
                    ),
                    'responseMessages' => SwaggerManager::getCommonResponses(),
                ),
            ),
        ),
    ),
    'models' => array(
        'EventStreamRequest'  => array(
            'id'         => 'EventStreamRequest',
            'properties' => array(
                'stream_id' => array(
                    'type'        => 'string',
                    'description' => 'The stream ID',
                ),
            )
        ),
        'EventStreamResponse' => array(
            'id'         => 'EventStreamResponse',
            'properties' => array(
                'stream_id' => array(
                    'type'        => 'string',
                    'description' => 'The stream ID',
                ),
                'timestamp' => array(
                    'type'        => 'float',
                    'description' => 'The timestamp of the event',
                ),
            )
        ),
    ),
);
