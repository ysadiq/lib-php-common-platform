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

$_addModels = array(
    'TableSchemas' => array(
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
    'TableSchema'  => array(
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
        ),
    ),
    'FieldSchema'  => array(
        'id'         => 'FieldSchema',
        'properties' => array(
            'name'           => array(
                'type'        => 'string',
                'description' => 'The API name of the field.',
            ),
            'label'          => array(
                'type'        => 'string',
                'description' => 'The displayable label for the field.',
            ),
            'type'           => array(
                'type'        => 'string',
                'description' => 'The DSP abstract data type for this field.',
            ),
            'db_type'        => array(
                'type'        => 'string',
                'description' => 'The native database type used for this field.',
            ),
            'is_primary_key' => array(
                'type'        => 'boolean',
                'description' => 'Is this field used as/part of the primary key.',
            ),
            'validation'     => array(
                'type'        => 'Array',
                'description' => 'validations to be performed on this field.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
            'value'          => array(
                'type'        => 'Array',
                'description' => 'Selectable string values for client menus and picklist validation.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
        ),
    ),
);

$_base = require( __DIR__ . '/BaseDbSvc.swagger.php' );

return $_base;
