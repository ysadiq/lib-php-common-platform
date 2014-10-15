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

use DreamFactory\Platform\Services\BaseDbSvc;
use DreamFactory\Platform\Services\SwaggerManager;

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );
$_commonResponses = SwaggerManager::getCommonResponses();

if ( !isset( $_addTableNotes ) )
{
    $_addTableNotes = '';
}

if ( !isset( $_addTableParameters ) )
{
    $_addTableParameters = array();
}

if ( !isset( $_addApis ) )
{
    $_addApis = array();
}

if ( !isset( $_addModels ) )
{
    $_addModels = array();
}

if ( !isset( $_baseDbOps ) )
{
    $_baseDbOps = array(
        array(
            'method'           => 'GET',
            'summary'          => 'getResources() - List all resources.',
            'nickname'         => 'getResources',
            'notes'            => 'List the names of the available tables in this storage. ',
            'type'             => 'Resources',
            'event_name'       => array('{api_name}.list'),
            'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
        ),
        array(
            'method'           => 'GET',
            'summary'          => 'getTables() - List all table names.',
            'nickname'         => 'getTables',
            'notes'            => 'List the table names in this storage, return as an array.',
            'type'             => 'ComponentList',
            'event_name'       => array('{api_name}.list'),
            'parameters'       => array(
                array(
                    'name'          => 'names_only',
                    'description'   => 'Return only the names of the tables in an array.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
                    'paramType'     => 'query',
                    'required'      => true,
                    'default'       => true,
                ),
                array(
                    'name'          => 'include_schemas',
                    'description'   => 'Also return the names of the tables where the schema is retrievable.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
                    'paramType'     => 'query',
                    'required'      => true,
                    'default'       => false,
                ),
                array(
                    'name'          => 'refresh',
                    'description'   => 'Refresh any cached copy of the resource list.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
            ),
            'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
        ),
        array(
            'method'           => 'GET',
            'summary'          => 'getAccessComponents() - List all role accessible components.',
            'nickname'         => 'getAccessComponents',
            'notes'            => 'List the names of all the role accessible components.',
            'type'             => 'ComponentList',
            'event_name'       => array('{api_name}.list'),
            'parameters'       => array(
                array(
                    'name'          => 'as_access_components',
                    'description'   => 'Return the names of all the accessible components.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
                    'paramType'     => 'query',
                    'required'      => true,
                    'default'       => true,
                ),
            ),
            'responseMessages' => SwaggerManager::getCommonResponses( array(400, 401, 500) ),
        ),
    );
}

if ( !isset( $_addDbOps ) )
{
    $_addDbOps = array();
}

if ( !isset( $_baseTableOps ) )
{
    $_baseTableOps = array(
        array(
            'method'           => 'GET',
            'summary'          => 'getRecordsByFilter() - Retrieve one or more records by using a filter.',
            'nickname'         => 'getRecordsByFilter',
            'notes'            =>
                'Set the <b>filter</b> parameter to a SQL WHERE clause (optional native filter accepted in some scenarios) ' .
                'to limit records returned or leave it blank to return all records up to the maximum limit.<br/> ' .
                'Set the <b>limit</b> parameter with or without a filter to return a specific amount of records.<br/> ' .
                'Use the <b>offset</b> parameter along with the <b>limit</b> parameter to page through sets of records.<br/> ' .
                'Set the <b>order</b> parameter to SQL ORDER_BY clause containing field and optional direction (<field_name> [ASC|DESC]) to order the returned records.<br/> ' .
                'Alternatively, to send the <b>filter</b> with or without <b>params</b> as posted data, ' .
                'use the getRecordsByPost() POST request and post a filter with or without params.<br/>' .
                $_addTableNotes .
                'Use the <b>fields</b> parameter to limit properties returned for each record. ' .
                'By default, all fields are returned for all records. ',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.select', '{api_name}.table_selected',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'filter',
                        'description'   => 'SQL WHERE clause filter to limit the records retrieved.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'limit',
                        'description'   => 'Maximum number of records to return.',
                        'allowMultiple' => false,
                        'type'          => 'integer',
                        'format'        => 'int32',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'offset',
                        'description'   => 'Offset the filter results to a particular record index (may require <b>order</b>> parameter in some scenarios).',
                        'allowMultiple' => false,
                        'type'          => 'integer',
                        'format'        => 'int32',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'order',
                        'description'   => 'SQL ORDER_BY clause containing field and direction for filter results.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'include_count',
                        'description'   => 'Include the total number of filter results as meta data.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'include_schema',
                        'description'   => 'Include table properties, including indexes and field details where available, as meta data.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'GET',
            'summary'          => 'getRecordsByIds() - Retrieve one or more records by identifiers.',
            'nickname'         => 'getRecordsByIds',
            'notes'            =>
                'Pass the identifying field values as a comma-separated list in the <b>ids</b> parameter.<br/> ' .
                'Use the <b>id_field</b> and <b>id_type</b> parameters to override or specify detail for identifying fields where applicable.<br/> ' .
                'Alternatively, to send the <b>ids</b> as posted data, use the getRecordsByPost() POST request.<br/> ' .
                $_addTableNotes .
                'Use the <b>fields</b> parameter to limit properties returned for each record. ' .
                'By default, all fields are returned for identified records. ',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.select', '{api_name}.table_selected',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'ids',
                        'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'continue',
                        'description'   =>
                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'POST',
            'summary'          => 'getRecordsByPost() - Retrieve one or more records by posting necessary data.',
            'nickname'         => 'getRecordsByPost',
            'notes'            =>
                'Post data should be an array of records wrapped in a <b>record</b> element - including the identifying fields at a minimum, ' .
                'or a <b>filter</b> in the SQL or other appropriate formats with or without a replacement <b>params</b> array, ' .
                'or a list of <b>ids</b> in a string list or an array.<br/> ' .
                $_addTableNotes .
                'Use the <b>fields</b> parameter to limit properties returned for each record. ' .
                'By default, all fields are returned for identified records. ',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.select', '{api_name}.table_selected',),
            'parameters'       => array(
                array(
                    'name'          => 'table_name',
                    'description'   => 'Name of the table to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'body',
                    'description'   => 'Data containing name-value pairs of records to retrieve.',
                    'allowMultiple' => false,
                    'type'          => 'GetRecordsRequest',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
                array(
                    'name'          => 'fields',
                    'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                    'allowMultiple' => true,
                    'type'          => 'string',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
                array(
                    'name'          => 'id_field',
                    'description'   =>
                        'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                        'used to override defaults or provide identifiers when none are provisioned.',
                    'allowMultiple' => true,
                    'type'          => 'string',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
                array(
                    'name'          => 'id_type',
                    'description'   =>
                        'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                        'used to override defaults or provide identifiers when none are provisioned.',
                    'allowMultiple' => true,
                    'type'          => 'string',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
                array(
                    'name'          => 'continue',
                    'description'   =>
                        'In batch scenarios, where supported, continue processing even after one record fails. ' .
                        'Default behavior is to halt and return results up to the first point of failure.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
                array(
                    'name'          => 'X-HTTP-METHOD',
                    'description'   => 'Override request using POST to tunnel other http request, such as GET.',
                    'enum'          => array('GET'),
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'header',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'GET',
            'summary'          => 'getRecords() - Retrieve one or more records.',
            'nickname'         => 'getRecords',
            'notes'            => 'Here for SDK backwards compatibility, see getRecordsByFilter(), getRecordsByIds(), and getRecordsByPost()',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.select', '{api_name}.table_selected',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'ids',
                        'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'filter',
                        'description'   => 'SQL-like filter to limit the records to retrieve.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'limit',
                        'description'   => 'Set to limit the filter results.',
                        'allowMultiple' => false,
                        'type'          => 'integer',
                        'format'        => 'int32',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'offset',
                        'description'   => 'Set to offset the filter results to a particular record count.',
                        'allowMultiple' => false,
                        'type'          => 'integer',
                        'format'        => 'int32',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'order',
                        'description'   => 'SQL-like order containing field and direction for filter results.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'include_count',
                        'description'   => 'Include the total number of filter results as meta data.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'include_schema',
                        'description'   => 'Include the table schema as meta data.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'continue',
                        'description'   =>
                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'POST',
            'summary'          => 'createRecords() - Create one or more records.',
            'nickname'         => 'createRecords',
            'notes'            =>
                'Posted data should be an array of records wrapped in a <b>record</b> element.<br/> ' .
                $_addTableNotes .
                'By default, only the id property of the record is returned on success. ' .
                'Use <b>fields</b> parameter to return more info.',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.insert', '{api_name}.table_inserted',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to create.',
                        'allowMultiple' => false,
                        'type'          => 'RecordsRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'continue',
                        'description'   =>
                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'rollback',
                        'description'   =>
                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'X-HTTP-METHOD',
                        'description'   => 'Override request using POST to tunnel other http request, such as DELETE.',
                        'enum'          => array('GET', 'PUT', 'PATCH', 'DELETE'),
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'header',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'PUT',
            'summary'          => 'replaceRecordsByIds() - Update (replace) one or more records.',
            'nickname'         => 'replaceRecordsByIds',
            'notes'            =>
                'Posted body should be a single record with name-value pairs to update wrapped in a <b>record</b> tag.<br/> ' .
                'Ids can be included via URL parameter or included in the posted body.<br/> ' .
                $_addTableNotes .
                'By default, only the id property of the record is returned on success. ' .
                'Use <b>fields</b> parameter to return more info.',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.update', '{api_name}.table_updated',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to update.',
                        'allowMultiple' => false,
                        'type'          => 'IdsRecordRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'ids',
                        'description'   => 'Comma-delimited list of the identifiers of the records to modify.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'continue',
                        'description'   =>
                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'rollback',
                        'description'   =>
                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'PUT',
            'summary'          => 'replaceRecordsByFilter() - Update (replace) one or more records.',
            'nickname'         => 'replaceRecordsByFilter',
            'notes'            =>
                'Posted body should be a single record with name-value pairs to update wrapped in a <b>record</b> tag.<br/> ' .
                'Filter can be included via URL parameter or included in the posted body.<br/> ' .
                $_addTableNotes .
                'By default, only the id property of the record is returned on success. ' .
                'Use <b>fields</b> parameter to return more info.',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.update', '{api_name}.table_updated',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to update.',
                        'allowMultiple' => false,
                        'type'          => 'FilterRecordRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'filter',
                        'description'   => 'SQL-like filter to limit the records to modify.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'PUT',
            'summary'          => 'replaceRecords() - Update (replace) one or more records.',
            'nickname'         => 'replaceRecords',
            'notes'            =>
                'Post data should be an array of records wrapped in a <b>record</b> tag.<br/> ' .
                $_addTableNotes .
                'By default, only the id property of the record is returned on success. ' .
                'Use <b>fields</b> parameter to return more info.',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.update', '{api_name}.table_updated',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to update.',
                        'allowMultiple' => false,
                        'type'          => 'RecordsRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'continue',
                        'description'   =>
                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'rollback',
                        'description'   =>
                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'PATCH',
            'summary'          => 'updateRecordsByIds() - Update (patch) one or more records.',
            'nickname'         => 'updateRecordsByIds',
            'notes'            =>
                'Posted body should be a single record with name-value pairs to update wrapped in a <b>record</b> tag.<br/> ' .
                'Ids can be included via URL parameter or included in the posted body.<br/> ' .
                $_addTableNotes .
                'By default, only the id property of the record is returned on success. ' .
                'Use <b>fields</b> parameter to return more info.',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.update', '{api_name}.table_updated',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'A single record containing name-value pairs of fields to update.',
                        'allowMultiple' => false,
                        'type'          => 'IdsRecordRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'ids',
                        'description'   => 'Comma-delimited list of the identifiers of the records to modify.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'continue',
                        'description'   =>
                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'rollback',
                        'description'   =>
                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'PATCH',
            'summary'          => 'updateRecordsByFilter() - Update (patch) one or more records.',
            'nickname'         => 'updateRecordsByFilter',
            'notes'            =>
                'Posted body should be a single record with name-value pairs to update wrapped in a <b>record</b> tag.<br/> ' .
                'Filter can be included via URL parameter or included in the posted body.<br/> ' .
                $_addTableNotes .
                'By default, only the id property of the record is returned on success. ' .
                'Use <b>fields</b> parameter to return more info.',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.update', '{api_name}.table_updated',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of fields to update.',
                        'allowMultiple' => false,
                        'type'          => 'FilterRecordRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'filter',
                        'description'   => 'SQL-like filter to limit the records to modify.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'PATCH',
            'summary'          => 'updateRecords() - Update (patch) one or more records.',
            'nickname'         => 'updateRecords',
            'notes'            =>
                'Post data should be an array of records containing at least the identifying fields for each record.<br/> ' .
                $_addTableNotes .
                'By default, only the id property of the record is returned on success. ' .
                'Use <b>fields</b> parameter to return more info.',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.update', '{api_name}.table_updated',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to update.',
                        'allowMultiple' => false,
                        'type'          => 'RecordsRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'continue',
                        'description'   =>
                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'rollback',
                        'description'   =>
                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'DELETE',
            'summary'          => 'deleteRecordsByIds() - Delete one or more records.',
            'nickname'         => 'deleteRecordsByIds',
            'notes'            =>
                'Set the <b>ids</b> parameter to a list of record identifying (primary key) values to delete specific records.<br/> ' .
                'Alternatively, to delete records by a large list of ids, pass the ids in the <b>body</b>.<br/> ' .
                $_addTableNotes .
                'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.delete', '{api_name}.table_deleted',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'ids',
                        'description'   => 'Comma-delimited list of the identifiers of the records to delete.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing ids of records to delete.',
                        'allowMultiple' => false,
                        'type'          => 'IdsRequest',
                        'paramType'     => 'body',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'continue',
                        'description'   =>
                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'rollback',
                        'description'   =>
                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'DELETE',
            'summary'          => 'deleteRecordsByFilter() - Delete one or more records by using a filter.',
            'nickname'         => 'deleteRecordsByFilter',
            'notes'            =>
                'Set the <b>filter</b> parameter to a SQL WHERE clause to delete specific records, ' .
                'otherwise set <b>force</b> to true to clear the table.<br/> ' .
                'Alternatively, to delete by a complicated filter or to use parameter replacement, pass the filter with or without params as the <b>body</b>.<br/> ' .
                $_addTableNotes .
                'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.delete', '{api_name}.table_deleted',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'filter',
                        'description'   => 'SQL WHERE clause filter to limit the records deleted.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing filter and/or params of records to delete.',
                        'allowMultiple' => false,
                        'type'          => 'FilterRequest',
                        'paramType'     => 'body',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'force',
                        'description'   => 'Set force to true to delete all records in this table, otherwise <b>filter</b> parameter is required.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'default'       => false,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'DELETE',
            'summary'          => 'deleteRecords() - Delete one or more records.',
            'nickname'         => 'deleteRecords',
            'notes'            =>
                'Set the <b>body</b> to an array of records, minimally including the identifying fields, to delete specific records.<br/> ' .
                $_addTableNotes .
                'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ',
            'type'             => 'RecordsResponse',
            'event_name'       => array('{api_name}.{table_name}.delete', '{api_name}.table_deleted',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to delete.',
                        'allowMultiple' => false,
                        'type'          => 'RecordsRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'continue',
                        'description'   =>
                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'rollback',
                        'description'   =>
                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'filter',
                        'description'   => 'For SDK backwards compatibility.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'ids',
                        'description'   => 'For SDK backwards compatibility.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
    );
}

if ( !isset( $_addTableOps ) )
{
    $_addTableOps = array();
}

if ( !isset( $_baseRecordOps ) )
{
    $_baseRecordOps = array(
        array(
            'method'           => 'GET',
            'summary'          => 'getRecord() - Retrieve one record by identifier.',
            'nickname'         => 'getRecord',
            'notes'            =>
                $_addTableNotes .
                'Use the <b>fields</b> parameter to limit properties that are returned. ' .
                'By default, all fields are returned.',
            'type'             => 'RecordResponse',
            'event_name'       => array('{api_name}.{table_name}.select', '{api_name}.table_selected',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'id',
                        'description'   => 'Identifier of the record to retrieve.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for the record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'POST',
            'summary'          => 'createRecord() - Create one record with given identifier.',
            'nickname'         => 'createRecord',
            'notes'            =>
                'Post data should be an array of fields for a single record.<br/> ' .
                $_addTableNotes .
                'Use the <b>fields</b> parameter to return more properties. By default, the id is returned.',
            'type'             => 'RecordResponse',
            'event_name'       => array('{api_name}.{table_name}.create', '{api_name}.table_created',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'id',
                        'description'   => 'Identifier of the record to create.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of the record to create.',
                        'allowMultiple' => false,
                        'type'          => 'RecordRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'PUT',
            'summary'          => 'replaceRecord() - Replace the content of one record by identifier.',
            'nickname'         => 'replaceRecord',
            'notes'            =>
                'Post data should be an array of fields for a single record.<br/> ' .
                $_addTableNotes .
                'Use the <b>fields</b> parameter to return more properties. By default, the id is returned.',
            'type'             => 'RecordResponse',
            'event_name'       => array('{api_name}.{table_name}.update', '{api_name}.table_updated',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'id',
                        'description'   => 'Identifier of the record to update.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of the replacement record.',
                        'allowMultiple' => false,
                        'type'          => 'RecordRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'PATCH',
            'summary'          => 'updateRecord() - Update (patch) one record by identifier.',
            'nickname'         => 'updateRecord',
            'notes'            =>
                'Post data should be an array of fields for a single record.<br/> ' .
                $_addTableNotes .
                'Use the <b>fields</b> parameter to return more properties. By default, the id is returned.',
            'type'             => 'RecordResponse',
            'event_name'       => array('{api_name}.{table_name}.update', '{api_name}.table_updated',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'The name of the table you want to update.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'id',
                        'description'   => 'Identifier of the record to update.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of the fields to update.',
                        'allowMultiple' => false,
                        'type'          => 'RecordRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
        array(
            'method'           => 'DELETE',
            'summary'          => 'deleteRecord() - Delete one record by identifier.',
            'nickname'         => 'deleteRecord',
            'notes'            => $_addTableNotes . 'Use the <b>fields</b> parameter to return more deleted properties. By default, the id is returned.',
            'type'             => 'RecordResponse',
            'event_name'       => array('{api_name}.{table_name}.delete', '{api_name}.table_deleted',),
            'parameters'       => array_merge(
                array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'id',
                        'description'   => 'Identifier of the record to delete.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_field',
                        'description'   =>
                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'id_type',
                        'description'   =>
                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                            'used to override defaults or provide identifiers when none are provisioned.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                ),
                $_addTableParameters
            ),
            'responseMessages' => $_commonResponses,
        ),
    );
}

if ( !isset( $_addRecordOps ) )
{
    $_addRecordOps = array();
}

if ( !isset( $_baseSchemaOps ) )
{
    $_baseSchemaOps = array(
        array(
            'method'           => 'GET',
            'summary'          => 'getSchemas() - List resources available for database schema.',
            'nickname'         => 'getSchemas',
            'type'             => 'Resources',
            'event_name'       => '{api_name}._schema.list',
            'parameters'       => array(
                array(
                    'name'          => 'refresh',
                    'description'   => 'Refresh any cached copy of the schema list.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'See listed operations for each resource available.',
        ),
        array(
            'method'           => 'POST',
            'summary'          => 'createTables() - Create one or more tables.',
            'nickname'         => 'createTables',
            'type'             => 'Resources',
            'event_name'       => '{api_name}._schema.create',
            'parameters'       => array(
                array(
                    'name'          => 'tables',
                    'description'   => 'Array of table definitions.',
                    'allowMultiple' => false,
                    'type'          => 'TableSchemas',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'Post data should be a single table definition or an array of table definitions.',
        ),
        array(
            'method'           => 'PUT',
            'summary'          => 'replaceTables() - Update (replace) one or more tables.',
            'nickname'         => 'replaceTables',
            'event_name'       => '{api_name}._schema.alter',
            'type'             => 'Resources',
            'parameters'       => array(
                array(
                    'name'          => 'tables',
                    'description'   => 'Array of table definitions.',
                    'allowMultiple' => false,
                    'type'          => 'TableSchemas',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'Post data should be a single table definition or an array of table definitions.',
        ),
        array(
            'method'           => 'PATCH',
            'summary'          => 'updateTables() - Update (patch) one or more tables.',
            'nickname'         => 'updateTables',
            'event_name'       => '{api_name}._schema.alter',
            'type'             => 'Resources',
            'parameters'       => array(
                array(
                    'name'          => 'tables',
                    'description'   => 'Array of table definitions.',
                    'allowMultiple' => false,
                    'type'          => 'TableSchemas',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'Post data should be a single table definition or an array of table definitions.',
        ),
    );
}

if ( !isset( $_addSchemaOps ) )
{
    $_addSchemaOps = array();
}

if ( !isset( $_baseSchemaTableOps ) )
{
    $_baseSchemaTableOps = array(
        array(
            'method'           => 'GET',
            'summary'          => 'describeTable() - Retrieve table definition for the given table.',
            'nickname'         => 'describeTable',
            'event_name'       => array(
                '{api_name}._schema.{table_name}.describe',
                '{api_name}._schema.table_described'
            ),
            'type'             => 'TableSchema',
            'parameters'       => array(
                array(
                    'name'          => 'table_name',
                    'description'   => 'Name of the table to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'refresh',
                    'description'   => 'Refresh any cached copy of the schema.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'This describes the table, its fields and relations to other tables.',
        ),
        array(
            'method'           => 'POST',
            'summary'          => 'createTable() - Create a table with the given properties and fields.',
            'nickname'         => 'createTable',
            'type'             => 'Success',
            'event_name'       => array(
                '{api_name}._schema.{table_name}.create',
                '{api_name}._schema.table_created'
            ),
            'parameters'       => array(
                array(
                    'name'          => 'table_name',
                    'description'   => 'Name of the table to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'schema',
                    'description'   => 'Array of table properties and fields definitions.',
                    'allowMultiple' => false,
                    'type'          => 'TableSchema',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'Post data should be an array of field properties for a single record or an array of fields.',
        ),
        array(
            'method'           => 'PUT',
            'summary'          => 'replaceTable() - Update (replace) a table with the given properties.',
            'nickname'         => 'replaceTable',
            'type'             => 'Success',
            'event_name'       => array(
                '{api_name}._schema.{table_name}.alter',
                '{api_name}._schema.table_altered'
            ),
            'parameters'       => array(
                array(
                    'name'          => 'table_name',
                    'description'   => 'Name of the table to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'schema',
                    'description'   => 'Array of field definitions.',
                    'allowMultiple' => false,
                    'type'          => 'TableSchema',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'Post data should be an array of field properties for a single record or an array of fields.',
        ),
        array(
            'method'           => 'PATCH',
            'summary'          => 'updateTable() - Update (patch) a table with the given properties.',
            'nickname'         => 'updateTable',
            'type'             => 'Success',
            'event_name'       => array(
                '{api_name}._schema.{table_name}.alter',
                '{api_name}._schema.table_altered'
            ),
            'parameters'       => array(
                array(
                    'name'          => 'table_name',
                    'description'   => 'Name of the table to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'schema',
                    'description'   => 'Array of field definitions.',
                    'allowMultiple' => false,
                    'type'          => 'TableSchema',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'Post data should be an array of field properties for a single record or an array of fields.',
        ),
        array(
            'method'           => 'DELETE',
            'summary'          => 'deleteTable() - Delete (aka drop) the given table.',
            'nickname'         => 'deleteTable',
            'type'             => 'Success',
            'event_name'       => array('{api_name}._schema.{table_name}.drop', '{api_name}._schema.table_dropped'),
            'parameters'       => array(
                array(
                    'name'          => 'table_name',
                    'description'   => 'Name of the table to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'Careful, this drops the database table and all of its contents.',
        ),
    );
}

if ( !isset( $_addSchemaTableOps ) )
{
    $_addSchemaTableOps = array();
}

if ( !isset( $_baseSchemaFieldOps ) )
{
    $_baseSchemaFieldOps = array(
        array(
            'method'           => 'GET',
            'summary'          => 'describeField() - Retrieve the definition of the given field for the given table.',
            'nickname'         => 'describeField',
            'type'             => 'FieldSchema',
            'event_name'       => array(
                '{api_name}._schema.{table_name}.{field_name}.describe',
                '{api_name}._schema.{table_name}.field_described'
            ),
            'parameters'       => array(
                array(
                    'name'          => 'table_name',
                    'description'   => 'Name of the table to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'field_name',
                    'description'   => 'Name of the field to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'refresh',
                    'description'   => 'Refresh any cached copy of the schema.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'This describes the field and its properties.',
        ),
        array(
            'method'           => 'PUT',
            'summary'          => 'replaceField() - Update one record by identifier.',
            'nickname'         => 'replaceField',
            'type'             => 'Success',
            'event_name'       => array(
                '{api_name}._schema.{table_name}.{field_name}.alter',
                '{api_name}._schema.{table_name}.field_altered'
            ),
            'parameters'       => array(
                array(
                    'name'          => 'table_name',
                    'description'   => 'Name of the table to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'field_name',
                    'description'   => 'Name of the field to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'properties',
                    'description'   => 'Array of field properties.',
                    'allowMultiple' => false,
                    'type'          => 'FieldSchema',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'Post data should be an array of field properties for the given field.',
        ),
        array(
            'method'           => 'PATCH',
            'summary'          => 'updateField() - Update one record by identifier.',
            'nickname'         => 'updateField',
            'type'             => 'Success',
            'event_name'       => array(
                '{api_name}._schema.{table_name}.{field_name}.alter',
                '{api_name}._schema.{table_name}.field_altered'
            ),
            'parameters'       => array(
                array(
                    'name'          => 'table_name',
                    'description'   => 'Name of the table to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'field_name',
                    'description'   => 'Name of the field to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'properties',
                    'description'   => 'Array of field properties.',
                    'allowMultiple' => false,
                    'type'          => 'FieldSchema',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'Post data should be an array of field properties for the given field.',
        ),
        array(
            'method'           => 'DELETE',
            'summary'          => 'deleteField() - Remove the given field from the given table.',
            'nickname'         => 'deleteField',
            'type'             => 'Success',
            'event_name'       => array(
                '{api_name}._schema.{table_name}.{field_name}.drop',
                '{api_name}._schema.{table_name}.field_dropped'
            ),
            'parameters'       => array(
                array(
                    'name'          => 'table_name',
                    'description'   => 'Name of the table to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
                array(
                    'name'          => 'field_name',
                    'description'   => 'Name of the field to perform operations on.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'path',
                    'required'      => true,
                ),
            ),
            'responseMessages' => $_commonResponses,
            'notes'            => 'Careful, this drops the database table field/column and all of its contents.',
        ),
    );
}

if ( !isset( $_addSchemaFieldOps ) )
{
    $_addSchemaFieldOps = array();
}

$_base['apis'] = array_merge(
    array(
        array(
            'path'        => '/{api_name}',
            'description' => 'Operations available for database tables.',
            'operations'  => array_merge(
                $_baseDbOps,
                $_addDbOps
            ),
        ),
        array(
            'path'        => '/{api_name}/{table_name}',
            'description' => 'Operations for table records administration.',
            'operations'  => array_merge(
                $_baseTableOps,
                $_addTableOps
            ),
        ),
        array(
            'path'        => '/{api_name}/{table_name}/{id}',
            'description' => 'Operations for single record administration.',
            'operations'  => array_merge(
                $_baseRecordOps,
                $_addRecordOps
            ),
        ),
        array(
            'path'        => '/{api_name}/' . BaseDbSvc::SCHEMA_RESOURCE,
            'description' => 'Operations available for SQL DB Schemas.',
            'operations'  => array_merge(
                $_baseSchemaOps,
                $_addSchemaOps
            ),
        ),
        array(
            'path'        => '/{api_name}/' . BaseDbSvc::SCHEMA_RESOURCE . '/{table_name}',
            'description' => 'Operations for per table administration.',
            'operations'  => array_merge(
                $_baseSchemaTableOps,
                $_addSchemaTableOps
            ),
        ),
        array(
            'path'        => '/{api_name}/' . BaseDbSvc::SCHEMA_RESOURCE . '/{table_name}/{field_name}',
            'description' => 'Operations for single field administration.',
            'operations'  => array_merge(
                $_baseSchemaFieldOps,
                $_addSchemaFieldOps
            ),
        ),
    ),
    $_addApis
);

$_commonProperties = array(
    'id' => array(
        'type'        => 'integer',
        'format'      => 'int32',
        'description' => 'Sample identifier of this record.',
    ),
);

$_models = array(
    'Tables'              => array(
        'id'         => 'Tables',
        'properties' => array(
            'table' => array(
                'type'        => 'array',
                'description' => 'Array of tables and their properties.',
                'items'       => array(
                    '$ref' => 'Table',
                ),
            ),
        ),
    ),
    'Table'               => array(
        'id'         => 'Table',
        'properties' => array(
            'name' => array(
                'type'        => 'string',
                'description' => 'Name of the table.',
            ),
        ),
    ),
    'RecordRequest'       => array(
        'id'         => 'RecordRequest',
        'properties' => array_merge(
            $_commonProperties
        )
    ),
    'RecordsRequest'      => array(
        'id'         => 'RecordsRequest',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
                'description' => 'Array of records.',
                'items'       => array(
                    '$ref' => 'RecordRequest',
                ),
            ),
        ),
    ),
    'IdsRequest'          => array(
        'id'         => 'IdsRequest',
        'properties' => array(
            'ids' => array(
                'type'        => 'array',
                'description' => 'Array of record identifiers.',
                'items'       => array(
                    'type'   => 'integer',
                    'format' => 'int32',
                ),
            ),
        ),
    ),
    'IdsRecordRequest'    => array(
        'id'         => 'IdsRecordRequest',
        'properties' => array(
            'record' => array(
                'type'        => 'RecordRequest',
                'description' => 'A single record, array of fields, used to modify existing records.',
            ),
            'ids'    => array(
                'type'        => 'array',
                'description' => 'Array of record identifiers.',
                'items'       => array(
                    'type'   => 'integer',
                    'format' => 'int32',
                ),
            ),
        ),
    ),
    'FilterRequest'       => array(
        'id'         => 'FilterRequest',
        'properties' => array(
            'filter' => array(
                'type'        => 'string',
                'description' => 'SQL or native filter to determine records where modifications will be applied.',
            ),
            'params' => array(
                'type'        => 'array',
                'description' => 'Array of name-value pairs, used for parameter replacement on filters.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
        ),
    ),
    'FilterRecordRequest' => array(
        'id'         => 'FilterRecordRequest',
        'properties' => array(
            'record' => array(
                'type'        => 'RecordRequest',
                'description' => 'A single record, array of fields, used to modify existing records.',
            ),
            'filter' => array(
                'type'        => 'string',
                'description' => 'SQL or native filter to determine records where modifications will be applied.',
            ),
            'params' => array(
                'type'        => 'array',
                'description' => 'Array of name-value pairs, used for parameter replacement on filters.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
        ),
    ),
    'GetRecordsRequest'   => array(
        'id'         => 'GetRecordsRequest',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
                'description' => 'Array of records.',
                'items'       => array(
                    '$ref' => 'RecordRequest',
                ),
            ),
            'ids'    => array(
                'type'        => 'array',
                'description' => 'Array of record identifiers.',
                'items'       => array(
                    'type'   => 'integer',
                    'format' => 'int32',
                ),
            ),
            'filter' => array(
                'type'        => 'string',
                'description' => 'SQL or native filter to determine records where modifications will be applied.',
            ),
            'params' => array(
                'type'        => 'array',
                'description' => 'Array of name-value pairs, used for parameter replacement on filters.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
        ),
    ),
    'RecordResponse'      => array(
        'id'         => 'RecordResponse',
        'properties' => array_merge(
            $_commonProperties
        ),
    ),
    'RecordsResponse'     => array(
        'id'         => 'RecordsResponse',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
                'description' => 'Array of system user records.',
                'items'       => array(
                    '$ref' => 'RecordResponse',
                ),
            ),
            'meta'   => array(
                'type'        => 'Metadata',
                'description' => 'Array of metadata returned for GET requests.',
            ),
        ),
    ),
    'Metadata'            => array(
        'id'         => 'Metadata',
        'properties' => array(
            'schema' => array(
                'type'        => 'Array',
                'description' => 'Array of table schema.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
            'count'  => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Record count returned for GET requests.',
            ),
        ),
    ),
);

$_base['models'] = array_merge( $_base['models'], $_models, $_addModels );

unset( $_commonProperties, $_commonResponses, $_models, $_baseDbOps, $_baseTableOps, $_baseRecordOps );

return $_base;