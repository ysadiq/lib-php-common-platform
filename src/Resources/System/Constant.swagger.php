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

$_constant = array();

$_constant['apis'] = array(
    array(
        'path'        => '/{api_name}/constant',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getConstants() - Retrieve all platform enumerated constants.',
                'nickname'         => 'getConstants',
                'type'             => 'Constants',
                'event_name'       => 'constants.list',
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
                'notes'            => 'Returns an object containing every enumerated type and its constant values',
            ),
        ),
        'description' => 'Operations for retrieving platform constants.',
    ),
    array(
        'path'        => '/{api_name}/constant/{type}',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getConstant() - Retrieve one constant type enumeration.',
                'nickname'         => 'getConstant',
                'type'             => 'Constant',
                'event_name'       => 'constant.read',
                'parameters'       => array(
                    array(
                        'name'          => 'type',
                        'description'   => 'Identifier of the enumeration type to retrieve.',
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
                        'message' => 'System Error - Specific reason is included in the error message.',
                        'code'    => 500,
                    ),
                ),
                'notes'            => 'Returns , all fields and no relations are returned.',
            ),
        ),
        'description' => 'Operations for retrieval individual platform constant enumerations.',
    ),
);

$_constant['models'] = array(
    'Constants' => array(
        'id'         => 'Constants',
        'properties' => array(
            'type_name' => array(
                'type'  => 'array',
                'items' => array(
                    '$ref' => 'Constant',
                ),
            ),
        ),
    ),
    'Constant'  => array(
        'id'         => 'Constant',
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

return $_constant;
