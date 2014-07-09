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

use DreamFactory\Platform\Services\SqlDbSvc;
use DreamFactory\Platform\Services\SwaggerManager;

$_addTableParameters = array(
    array(
        'name'          => 'related',
        'description'   => 'Comma-delimited list of relationship names to retrieve for each record, or \'*\' to retrieve all.',
        'allowMultiple' => true,
        'type'          => 'string',
        'paramType'     => 'query',
        'required'      => false,
    )
);

$_addTableNotes =
    'Use the <b>related</b> parameter to return related records for each resource. ' .
    'By default, no related records are returned.<br/> ';

$_addApis = array(
    array(
        'path'        => '/{api_name}/' . SqlDbSvc::STORED_PROCEDURE_RESOURCE,
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getStoredProcedures() - List callable stored procedures.',
                'nickname'         => 'getStoredProcedures',
                'notes'            => 'List the names of the available stored procedures on this database. ',
                'type'             => 'Resources',
                'event_name'       => array('{api_name}.' . SqlDbSvc::STORED_PROCEDURE_RESOURCE . '.list'),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
        ),
        'description' => 'Operations for retrieving callable stored procedures.',
    ),
    array(
        'path'        => '/{api_name}/' . SqlDbSvc::STORED_PROCEDURE_RESOURCE . '/{procedure_name}',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'callStoredProcedure() - Call a stored procedure.',
                'nickname'         => 'callStoredProcedure()',
                'notes'            => 'Call a stored procedure with no parameters. Set an optional wrapper for the returned data set. ',
                'type'             => 'ProcedureResponse',
                'event_name'       => array(
                    '{api_name}.' . SqlDbSvc::STORED_PROCEDURE_RESOURCE . '.{procedure_name}.call',
                    '{api_name}.' . SqlDbSvc::STORED_PROCEDURE_RESOURCE . '.procedure_called',
                ),
                'parameters'       => array(
                    array(
                        'name'          => 'procedure_name',
                        'description'   => 'Name of the stored procedure to call.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'wrapper',
                        'description'   => 'Add this wrapper around the expected data set before returning.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses(),
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'callStoredProcedureWithParams() - Call a stored procedure.',
                'nickname'         => 'callStoredProcedureWithParams()',
                'notes'            => 'Call a stored procedure with parameters. Set an optional wrapper for the returned data set. ',
                'type'             => 'ProcedureResponse',
                'event_name'       => array(
                    '{api_name}.' . SqlDbSvc::STORED_PROCEDURE_RESOURCE . '.{procedure_name}.call',
                    '{api_name}.' . SqlDbSvc::STORED_PROCEDURE_RESOURCE . '.procedure_called',
                ),
                'parameters'       => array(
                    array(
                        'name'          => 'procedure_name',
                        'description'   => 'Name of the stored procedure to call.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing in and out parameters to pass to procedure.',
                        'allowMultiple' => false,
                        'type'          => 'ProcedureRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'wrapper',
                        'description'   => 'Add this wrapper around the expected data set before returning.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                'responseMessages' => SwaggerManager::getCommonResponses(),
            ),
        ),
        'description' => 'Operations for SQL database stored procedures.',
    ),
);

$_addModels = array(
    'ProcedureResponse'          => array(
        'id'         => 'ProcedureResponse',
        'properties' => array(
            '_wrapper_if_supplied_' => array(
                'type'        => 'Array',
                'description' => 'Array of returned data.',
                'items'       => array(
                    'type'   => 'string'
                ),
            ),
            '_out_param_name_' => array(
                'type'        => 'string',
                'description' => 'Name and value of any given output parameter.',
            ),
        ),
    ),
    'ProcedureRequest'   => array(
        'id'         => 'ProcedureRequest',
        'properties' => array(
            'params'    => array(
                'type'        => 'array',
                'description' => 'Optional array of input and output parameters.',
                'items'       => array(
                    '$ref' => 'ProcedureParam',
                ),
            ),
            'schema'    => array(
                'type'        => 'array',
                'description' => 'Optional array of name and type to be applied to returned data.',
                'items'       => array(
                    '$ref' => 'ProcedureResultSchema',
                ),
            ),
            'name' => array(
                'type'        => 'string',
                'description' => "Name of the parameter, if not provided for OUT and INOUT types, " .
                                 "'param_' + numeric index is used for return, i.e. 'param_0.'",
            ),
        ),
    ),
    'ProcedureParam'  => array(
        'id'         => 'ProcedureParam',
        'properties' => array(
            'name' => array(
                'type'        => 'string',
                'description' => "Name of the parameter, if not provided for OUT and INOUT types, " .
                                 "'param_' + numeric index is used for return, i.e. 'param_0.'",
            ),
            'param_type' => array(
                'type'        => 'string',
                'description' => 'Parameter type of IN, OUT, or INOUT, defaults to IN.',
            ),
            'value' => array(
                'type'        => 'string',
                'description' => 'Value of the parameter, used for the IN and INOUT types, defaults to NULL.',
            ),
            'type' => array(
                'type'        => 'string',
                'description' => 'For OUT parameters, the requested type for the returned value, i.e. int, boolean, string, etc.',
            ),
        ),
    ),
    'ProcedureResultSchema'  => array(
    'id'         => 'ProcedureResultSchema',
    'properties' => array(
        'name' => array(
            'type'        => 'string',
            'description' => 'Name of the returned element.',
        ),
        'type' => array(
            'type'        => 'string',
            'description' => 'The requested type for the returned value, i.e. int, boolean, string, etc.',
        ),
    ),
)
);

$_base = require( __DIR__ . '/BaseDbSvc.swagger.php' );

unset( $_addTableNotes, $_addTableParameters, $_addApis );

return $_base;
