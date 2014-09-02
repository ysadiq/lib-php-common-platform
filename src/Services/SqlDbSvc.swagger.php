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

$_commonResponses = SwaggerManager::getCommonResponses( array(400, 401, 500) );

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
        'path'        => '/{api_name}/' . SqlDbSvc::STORED_PROC_RESOURCE,
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getStoredProcs() - List callable stored procedures.',
                'nickname'         => 'getStoredProcs',
                'notes'            => 'List the names of the available stored procedures on this database. ',
                'type'             => 'Resources',
                'event_name'       => array('{api_name}.' . SqlDbSvc::STORED_PROC_RESOURCE . '.list'),
                'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
            ),
        ),
        'description' => 'Operations for retrieving callable stored procedures.',
    ),
    array(
        'path'        => '/{api_name}/' . SqlDbSvc::STORED_PROC_RESOURCE . '/{procedure_name}',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'callStoredProc() - Call a stored procedure.',
                'nickname'         => 'callStoredProc',
                'notes'            =>
                    'Call a stored procedure with no parameters. ' .
                    'Set an optional wrapper for the returned data set. ',
                'type'             => 'StoredProcResponse',
                'event_name'       => array(
                    '{api_name}.' . SqlDbSvc::STORED_PROC_RESOURCE . '.{procedure_name}.call',
                    '{api_name}.' . SqlDbSvc::STORED_PROC_RESOURCE . '.procedure_called',
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
                'summary'          => 'callStoredProcWithParams() - Call a stored procedure.',
                'nickname'         => 'callStoredProcWithParams',
                'notes'            =>
                    'Call a stored procedure with parameters. ' .
                    'Set an optional wrapper and schema for the returned data set. ',
                'type'             => 'StoredProcResponse',
                'event_name'       => array(
                    '{api_name}.' . SqlDbSvc::STORED_PROC_RESOURCE . '.{procedure_name}.call',
                    '{api_name}.' . SqlDbSvc::STORED_PROC_RESOURCE . '.procedure_called',
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
                        'type'          => 'StoredProcRequest',
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
    'TableSchemas'           => array(
        'id'         => 'TableSchemas',
        'properties' => array(
            'table' => array(
                'type'        => 'Array',
                'description' => 'An array of table definitions.',
                'items'       => array(
                    '$ref' => 'TableSchema',
                ),
            ),
        ),
    ),
    'TableSchema'            => array(
        'id'         => 'TableSchema',
        'properties' => array(
            'name'        => array(
                'type'        => 'string',
                'description' => 'Identifier/Name for the table.',
            ),
            'label'       => array(
                'type'        => 'string',
                'description' => 'Displayable singular name for the table.',
            ),
            'plural'      => array(
                'type'        => 'string',
                'description' => 'Displayable plural name for the table.',
            ),
            'primary_key' => array(
                'type'        => 'string',
                'description' => 'Field(s), if any, that represent the primary key of each record.',
            ),
            'name_field'  => array(
                'type'        => 'string',
                'description' => 'Field(s), if any, that represent the name of each record.',
            ),
            'field'       => array(
                'type'        => 'Array',
                'description' => 'An array of available fields in each record.',
                'items'       => array(
                    '$ref' => 'FieldSchema',
                ),
            ),
            'related'     => array(
                'type'        => 'Array',
                'description' => 'An array of available relationships to other tables.',
                'items'       => array(
                    '$ref' => 'RelatedSchema',
                ),
            ),
        ),
    ),
    'FieldSchema'            => array(
        'id'         => 'FieldSchema',
        'properties' => array(
            'name'               => array(
                'type'        => 'string',
                'description' => 'The API name of the field.',
            ),
            'label'              => array(
                'type'        => 'string',
                'description' => 'The displayable label for the field.',
            ),
            'type'               => array(
                'type'        => 'string',
                'description' => 'The DSP abstract data type for this field.',
            ),
            'db_type'            => array(
                'type'        => 'string',
                'description' => 'The native database type used for this field.',
            ),
            'length'             => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'The maximum length allowed (in characters for string, displayed for numbers).',
            ),
            'precision'          => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Total number of places for numbers.',
            ),
            'scale'              => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Number of decimal places allowed for numbers.',
            ),
            'default_value'      => array(
                'type'        => 'string',
                'description' => 'Default value for this field.',
            ),
            'required'           => array(
                'type'        => 'boolean',
                'description' => 'Is a value required for record creation.',
            ),
            'allow_null'         => array(
                'type'        => 'boolean',
                'description' => 'Is null allowed as a value.',
            ),
            'fixed_length'       => array(
                'type'        => 'boolean',
                'description' => 'Is the length fixed (not variable).',
            ),
            'supports_multibyte' => array(
                'type'        => 'boolean',
                'description' => 'Does the data type support multibyte characters.',
            ),
            'auto_increment'     => array(
                'type'        => 'boolean',
                'description' => 'Does the integer field value increment upon new record creation.',
            ),
            'is_primary_key'     => array(
                'type'        => 'boolean',
                'description' => 'Is this field used as/part of the primary key.',
            ),
            'is_foreign_key'     => array(
                'type'        => 'boolean',
                'description' => 'Is this field used as a foreign key.',
            ),
            'ref_table'          => array(
                'type'        => 'string',
                'description' => 'For foreign keys, the referenced table name.',
            ),
            'ref_fields'         => array(
                'type'        => 'string',
                'description' => 'For foreign keys, the referenced table field name.',
            ),
            'validation'         => array(
                'type'        => 'Array',
                'description' => 'validations to be performed on this field.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
            'value'              => array(
                'type'        => 'Array',
                'description' => 'Selectable string values for client menus and picklist validation.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
        ),
    ),
    'RelatedSchema'          => array(
        'id'         => 'RelatedSchema',
        'properties' => array(
            'name'      => array(
                'type'        => 'string',
                'description' => 'Name of the relationship.',
            ),
            'type'      => array(
                'type'        => 'string',
                'description' => 'Relationship type - belongs_to, has_many, many_many.',
            ),
            'ref_table' => array(
                'type'        => 'string',
                'description' => 'The table name that is referenced by the relationship.',
            ),
            'ref_field' => array(
                'type'        => 'string',
                'description' => 'The field name that is referenced by the relationship.',
            ),
            'join'      => array(
                'type'        => 'string',
                'description' => 'The intermediate joining table used for many_many relationships.',
            ),
            'field'     => array(
                'type'        => 'string',
                'description' => 'The current table field that is used in the relationship.',
            ),
        ),
    ),
    'StoredProcResponse'     => array(
        'id'         => 'StoredProcResponse',
        'properties' => array(
            '_wrapper_if_supplied_' => array(
                'type'        => 'Array',
                'description' => 'Array of returned data.',
                'items'       => array(
                    'type' => 'string'
                ),
            ),
            '_out_param_name_'      => array(
                'type'        => 'string',
                'description' => 'Name and value of any given output parameter.',
            ),
        ),
    ),
    'StoredProcRequest'      => array(
        'id'         => 'StoredProcRequest',
        'properties' => array(
            'params'  => array(
                'type'        => 'array',
                'description' => 'Optional array of input and output parameters.',
                'items'       => array(
                    '$ref' => 'StoredProcParam',
                ),
            ),
            'schema'  => array(
                'type'        => 'StoredProcResultSchema',
                'description' => 'Optional name to type pairs to be applied to returned data.',
            ),
            'wrapper' => array(
                'type'        => 'string',
                'description' => 'Add this wrapper around the expected data set before returning, same as URL parameter.',
            ),
        ),
    ),
    'StoredProcParam'        => array(
        'id'         => 'StoredProcParam',
        'properties' => array(
            'name'       => array(
                'type'        => 'string',
                'description' =>
                    'Name of the parameter, required for OUT and INOUT types, ' .
                    'must be the same as the stored procedure\'s parameter name.',
            ),
            'param_type' => array(
                'type'        => 'string',
                'description' => 'Parameter type of IN, OUT, or INOUT, defaults to IN.',
            ),
            'value'      => array(
                'type'        => 'string',
                'description' => 'Value of the parameter, used for the IN and INOUT types, defaults to NULL.',
            ),
            'type'       => array(
                'type'        => 'string',
                'description' =>
                    'For INOUT and OUT parameters, the requested type for the returned value, ' .
                    'i.e. integer, boolean, string, etc. Defaults to value type for INOUT and string for OUT.',
            ),
            'length'     => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' =>
                    'For INOUT and OUT parameters, the requested length for the returned value. ' .
                    'May be required by some database drivers.',
            ),
        ),
    ),
    'StoredProcResultSchema' => array(
        'id'         => 'StoredProcResultSchema',
        'properties' => array(
            '_field_name_' => array(
                'type'        => 'string',
                'description' =>
                    'The name of the returned element where the value is set to the requested type ' .
                    'for the returned value, i.e. integer, boolean, string, etc.',
            ),
        ),
    )
);

$_base = require( __DIR__ . '/BaseDbSvc.swagger.php' );

unset( $_addTableNotes, $_addTableParameters, $_addApis, $_addModels );

return $_base;
