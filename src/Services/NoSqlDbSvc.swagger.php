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

$_commonResponses = SwaggerManager::getCommonResponses();

$_addDbOps = array(
    array(
        'method'           => 'POST',
        'summary'          => 'createTables() - Create one or more tables.',
        'nickname'         => 'createTables',
        'notes'            => 'Post body should be a single table definition or an array of table definitions.',
        'type'             => 'Tables',
        'event_name'       => array( '{api_name}.tables.create', ),
        'parameters'       => array(
            array(
                'name'          => 'tables',
                'description'   => 'Array of tables to create.',
                'allowMultiple' => false,
                'type'          => 'Tables',
                'paramType'     => 'body',
                'required'      => true,
            ),
            array(
                'name'          => 'check_exist',
                'description'   => 'If true, the request fails when the table to create already exists.',
                'allowMultiple' => false,
                'type'          => 'boolean',
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
        'responseMessages' => SwaggerManager::getCommonResponses( array( 400, 401, 500 ) ),
    ),
    array(
        'method'           => 'PATCH',
        'summary'          => 'updateTableProperties() - Update properties of one or more tables.',
        'nickname'         => 'updateTableProperties',
        'notes'            => 'Post body should be a single table definition or an array of table definitions.',
        'type'             => 'Tables',
        'event_name'       => array( '{api_name}.tables.update' ),
        'parameters'       => array(
            array(
                'name'          => 'body',
                'description'   => 'Array of tables with properties to update.',
                'allowMultiple' => false,
                'type'          => 'Tables',
                'paramType'     => 'body',
                'required'      => true,
            ),
        ),
        'responseMessages' => $_commonResponses,
    ),
    array(
        'method'           => 'DELETE',
        'summary'          => 'deleteTables() - Delete one or more tables.',
        'nickname'         => 'deleteTables',
        'notes'            =>
            'Set the <b>names</b> of the tables to delete or set <b>force</b> to true to clear the database.' .
            'Alternatively, to delete by table definitions or a large list of names, ' .
            'use the POST request with X-HTTP-METHOD = DELETE header and post array of definitions or names.',
        'type'             => 'Tables',
        'event_name'       => array( '{api_name}.tables.delete' ),
        'parameters'       => array(
            array(
                'name'          => 'names',
                'description'   => 'Comma-delimited list of the table names to delete.',
                'allowMultiple' => true,
                'type'          => 'string',
                'paramType'     => 'query',
                'required'      => false,
            ),
            array(
                'name'          => 'force',
                'description'   => 'Set force to true to delete all tables in this database, otherwise <b>names</b> parameter is required.',
                'allowMultiple' => false,
                'type'          => 'boolean',
                'paramType'     => 'query',
                'required'      => false,
                'default'       => false,
            ),
        ),
        'responseMessages' => SwaggerManager::getCommonResponses( array( 400, 401, 500 ) ),
    ),
);

$_base = require( __DIR__ . '/BaseDbSvc.swagger.php' );

unset( $_addDbOps );

return $_base;
