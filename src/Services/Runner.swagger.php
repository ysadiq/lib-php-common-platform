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

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );

$_base['apis'] = array(
    array(
        'path'        => '/{api_name}',
        'description' => 'Operations available for Script Runner Service.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getScripts() - List all resources.',
                'nickname'         => 'getScripts',
                'type'             => 'Scripts',
                'event_name'       => 'scripts.list',
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
                'notes'            => 'List the available scripts. ',
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/{script_id}',
        'description' => 'Operations on scripts.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getScript() - Get the script with ID provided.',
                'nickname'         => 'getScript',
                'type'             => 'ScriptResponse',
                'event_name'       => 'script.read',
                'parameters'       => array(
                    array(
                        'name'          => 'script_id',
                        'description'   => 'The ID of the script which you want to retrieve.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
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
                        'message' => 'Not Found - Requested container does not exist.',
                        'code'    => 404,
                    ),
                    array(
                        'message' => 'System Error - Specific reason is included in the error message.',
                        'code'    => 500,
                    ),
                ),
                'notes'            => '',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'runScript() - Runs the specified script.',
                'nickname'         => 'runScript',
                'type'             => 'ScriptResponse',
                'event_name'       => 'script.run',
                'parameters'       => array(
                    array(
                        'name'          => 'script_id',
                        'description'   => 'The ID of the script which you want to retrieve.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
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
                        'message' => 'Not Found - Requested container does not exist.',
                        'code'    => 404,
                    ),
                    array(
                        'message' => 'System Error - Specific reason is included in the error message.',
                        'code'    => 500,
                    ),
                ),
                'notes'            => 'Post data as an array of folders and/or files.',
            ),
        ),
    ),
);

$_commonScript = array(
    'script_id'   => array(
        'type'        => 'string',
        'description' => 'The script ID',
    ),
    'script_body' => array(
        'type'        => 'string',
        'description' => 'The body of the script',
    ),
    'metadata'    => array(
        'type'        => 'Array',
        'description' => 'An array of name-value pairs.',
        'items'       => array(
            'type' => 'string',
        ),
    ),
);

$_models = array(
    'ScriptRequest'   => array(
        'id'         => 'ScriptRequest',
        'properties' => $_commonScript,
    ),
    'ScriptResponse'  => array(
        'id'         => 'ScriptResponse',
        'properties' => array_merge(
            $_commonScript,
            array(
                'last_modified' => array(
                    'type'        => 'string',
                    'description' => 'A GMT date timestamp of when the script was last modified.',
                ),
            )
        ),
    ),
    'Script'          => array(
        'id'         => 'Script',
        'properties' => $_commonScript,
    ),
    'ScriptsRequest'  => array(
        'id'         => 'ScriptsRequest',
        'properties' => array(
            'container' => array(
                'type'        => 'Array',
                'description' => 'An array of scripts to modify.',
                'items'       => array(
                    '$ref' => 'Script',
                ),
            ),
        ),
    ),
    'ScriptsResponse' => array(
        'id'         => 'ScriptsResponse',
        'properties' => array(
            'script' => array(
                'type'        => 'Array',
                'description' => 'An array of scripts.',
                'items'       => array(
                    '$ref' => 'Script',
                ),
            ),
        ),
    ),
);

$_base['models'] = array_merge( $_base['models'], $_models );

return $_base;
