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
//  The script record model properties
$_properties = array_merge(
    array(
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
        )
    ),
    require( __DIR__ . '/BaseResourceProperties.swagger.php' )
);

$_script = array(
    'produces' => array( 'application/json' ),
    'consumes' => array( 'application/javascript', 'text/javascript', 'text/plain' ),
    'apis'     => array(
        array(
            'path'        => '/{api_name}/script',
            'description' => 'Operations available for the system script resource',
            'operations'  => array(
                array(
                    'method'           => 'GET',
                    'summary'          => 'getScripts() - List all scripts',
                    'nickname'         => 'getScripts',
                    'type'             => 'Scripts',
                    'event_name'       => 'scripts.list',
                    'notes'            => 'List all known scripts',
                    'responseMessages' => $_commonResponses,
                ),
            ),
        ),
        array(
            'path'        => '/{api_name}/script/{script_id}',
            'description' => 'Operations on scripts',
            'operations'  => array(
                array(
                    'method'           => 'GET',
                    'summary'          => 'getScript() - Get the script with ID provided',
                    'nickname'         => 'getScript',
                    'type'             => 'ScriptResponse',
                    'event_name'       => 'script.read',
                    'parameters'       => array(
                        array(
                            'name'          => 'script_id',
                            'description'   => 'The ID of the record to retrieve',
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
                    'type'             => 'ScriptOutput',
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
                    'notes'            => 'Loads and executes the specified script',
                ),
                array(
                    'method'           => 'PUT',
                    'summary'          => 'writeScript() - Writes the specified script to the file system.',
                    'notes'            => 'Post data as a string.',
                    'nickname'         => 'writeScript',
                    'type'             => 'ScriptResponse',
                    'event_name'       => 'script.write',
                    'parameters'       => array(
                        array(
                            'name'          => 'script_id',
                            'description'   => 'The ID of the script which you want to retrieve.',
                            'allowMultiple' => false,
                            'type'          => 'string',
                            'paramType'     => 'path',
                            'required'      => true,
                        ),
                        array(
                            'name'          => 'script_body',
                            'description'   => 'The body of the script to write.',
                            'allowMultiple' => false,
                            'type'          => 'string',
                            'paramType'     => 'body',
                            'required'      => true,
                        ),
                    ),
                    'responseMessages' => $_commonResponses,
                ),
            ),
        ),
    ),
);

$_script['models'] = array(
    'Script'          => array(
        'id'         => 'Script',
        'properties' => $_properties,
    ),
    'ScriptRequest'   => array(
        'id'         => 'ScriptRequest',
        'properties' => $_properties,
    ),
    'ScriptResponse'  => array(
        'id'         => 'ScriptResponse',
        'properties' => $_properties,
    ),
    'ScriptsRequest'  => array(
        'id'         => 'ScriptsRequest',
        'properties' => array(
            'container' => array(
                'type'        => 'Array',
                'description' => 'An array of script resources',
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
                'description' => 'An array of script resources',
                'items'       => array(
                    '$ref' => 'Script',
                ),
            ),
        ),
    ),
    'ScriptOutput'    => array(
        'id'         => 'ScriptOutput',
        'properties' => array(
            'script_output'        => array(
                'type'        => 'string',
                'description' => 'The output of the script, if any.',
            ),
            'script_last_variable' => array(
                'type'        => 'string',
                'description' => 'The value of the last variable created within the script.',
            ),
        ),
    ),
);

return $_script;
