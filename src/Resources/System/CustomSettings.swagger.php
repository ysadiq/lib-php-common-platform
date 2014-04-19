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

$_custom = array();

$_custom['apis'] = array(
    array(
        'path'        => '/{api_name}/custom',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getCustomSettings() - Retrieve all custom system settings.',
                'nickname'         => 'getCustomSettings',
                'type'             => 'CustomSettings',
                'event_name'       => 'settings.read',
                'responseMessages' => array(
                    array(
                        'message' => 'System Error - Specific reason is included in the error message.',
                        'code'    => 500,
                    ),
                ),
                'notes'            => 'Returns an object containing name-value pairs for custom system settings',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'setCustomSettings() - Update one or more custom system settings.',
                'nickname'         => 'setCustomSettings',
                'type'             => 'Success',
                'event_name'       => 'settings.update',
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of desired settings.',
                        'allowMultiple' => false,
                        'type'          => 'CustomSettings',
                        'paramType'     => 'body',
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
                        'message' => 'System Error - Specific reason is included in the error message.',
                        'code'    => 500,
                    ),
                ),
                'notes'            =>
                    'A valid session and system configuration permissions is required to edit settings. ' . 'Post body should be an array of name-value pairs.',
            ),
        ),
        'description' => 'Operations for managing custom system settings.',
    ),
    array(
        'path'        => '/{api_name}/custom/{setting}',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getCustomSetting() - Retrieve one custom system setting.',
                'nickname'         => 'getCustomSetting',
                'type'             => 'CustomSetting',
                'event_name'       => 'setting.read',
                'parameters'       => array(
                    array(
                        'name'          => 'setting',
                        'description'   => 'Name of the setting to retrieve.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => array(
                    array(
                        'message' => 'System Error - Specific reason is included in the error message.',
                        'code'    => 500,
                    ),
                ),
                'notes'            => 'Setting will be returned as an object containing name-value pair.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteCustomSetting() - Delete one custom setting.',
                'nickname'         => 'deleteCustomSetting',
                'type'             => 'Success',
                'event_name'       => 'setting.delete',
                'parameters'       => array(
                    array(
                        'name'          => 'setting',
                        'description'   => 'Name of the setting to delete.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => array(
                    array(
                        'message' => 'Unauthorized Access - No currently valid session available.',
                        'code'    => 401,
                    ),
                    array(
                        'message' => 'Denied Access - No permission.',
                        'code'    => 403,
                    ),
                    array(
                        'message' => 'System Error - Specific reason is included in the error message.',
                        'code'    => 500,
                    ),
                ),
                'notes'            => 'A valid session with system configuration permissions is required to delete settings.',
            ),
        ),
        'description' => 'Operations for individual custom system settings.',
    ),
);

$_custom['models'] = array(
    'CustomSettings' => array(
        'id'         => 'CustomSettings',
        'properties' => array(
            'type_name' => array(
                'type'  => 'array',
                'items' => array(
                    '$ref' => 'CustomSetting',
                ),
            ),
        ),
    ),
    'CustomSetting'  => array(
        'id'         => 'CustomSetting',
        'properties' => array(
            'name' => array(
                'type'  => 'array',
                'items' => array(
                    'type' => 'string',
                ),
            ),
        ),
    ),
);

return $_custom;
