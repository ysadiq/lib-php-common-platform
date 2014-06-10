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

$_baseTableOps = array(
    array(
        'method'           => 'GET',
        'summary'          => 'getRecordsByView() - Retrieve one or more records by using a view.',
        'nickname'         => 'getRecordsByView',
        'notes'            =>
            'Use the <b>design</b> and <b>view</b> parameters to retrieve data according to a view.<br/> ' .
            'Alternatively, to send the <b>design</b> and <b>view</b> with or without additional URL parameters as posted data ' .
            'use the POST request with X-HTTP-METHOD = GET header.<br/> ' .
            'Refer to http://docs.couchdb.org/en/latest/api/ddoc/views.html for additional allowed query parameters.<br/> ' .
            'Use the <b>fields</b> parameter to limit properties returned for each resource. ' .
            'By default, all fields are returned for all resources. ',
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
                    'name'          => 'design',
                    'description'   => 'The design document name for the desired view.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'query',
                    'required'      => true,
                ),
                array(
                    'name'          => 'view',
                    'description'   => 'The view function name for the given design document.',
                    'allowMultiple' => false,
                    'type'          => 'string',
                    'paramType'     => 'query',
                    'required'      => true,
                ),
                array(
                    'name'          => 'limit',
                    'description'   => 'Set to limit the view results.',
                    'allowMultiple' => false,
                    'type'          => 'integer',
                    'format'        => 'int32',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
                array(
                    'name'          => 'skip',
                    'description'   => 'Set to offset the view results to a particular record count.',
                    'allowMultiple' => false,
                    'type'          => 'integer',
                    'format'        => 'int32',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
                array(
                    'name'          => 'reduce',
                    'description'   => 'Use the reduce function. Default is true.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
                array(
                    'name'          => 'include_docs',
                    'description'   =>
                        'Include the associated document with each row. Default is false. ' .
                        'If set to true, just the documents as a record array will be returned, like getRecordsByIds does.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
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
                    'description'   => 'Include the total number of view results as meta data.',
                    'allowMultiple' => false,
                    'type'          => 'boolean',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
            )
        ),
        'responseMessages' => $_commonResponses,
    ),
    array(
        'method'           => 'GET',
        'summary'          => 'getRecordsByIds() - Retrieve one or more records by identifiers.',
        'nickname'         => 'getRecordsByIds',
        'notes'            =>
            'Pass the identifying field values as a comma-separated list in the <b>ids</b> parameter.<br/> ' .
            'Alternatively, to send the <b>ids</b> as posted data use the POST request with X-HTTP-METHOD = GET header and post array of ids.<br/> ' .
            'Use the <b>fields</b> parameter to limit properties returned for each resource. ' .
            'By default, all fields are returned for identified resources. ',
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
                    'description'   => 'Comma-delimited list of the identifiers of the resources to retrieve.',
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
            )
        ),
        'responseMessages' => $_commonResponses,
    ),
    array(
        'method'           => 'POST',
        'summary'          => 'getRecordsByPost() - Retrieve one or more records by posting necessary data.',
        'nickname'         => 'getRecordsByPost',
        'notes'            =>
            'Post data should be an array of records wrapped in a <b>record</b> element - including the identifying fields at a minimum, ' .
            'or a list of <b>ids</b> in a string list or an array.<br/> ' .
            'Use the <b>fields</b> parameter to limit properties returned for each resource. ' .
            'By default, all fields are returned for identified resources. ',
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
        'notes'            =>
            'Use the <b>ids</b> parameter to limit resources that are returned.<br/> ' .
            'Alternatively, to send the <b>ids</b> as posted data use the POST request with X-HTTP-METHOD = GET header.<br/> ' .
            'Use the <b>fields</b> parameter to limit properties returned for each resource. ' .
            'By default, all fields are returned for all resources. ',
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
                    'description'   => 'Comma-delimited list of the identifiers of the resources to retrieve.',
                    'allowMultiple' => true,
                    'type'          => 'string',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
                array(
                    'name'          => 'limit',
                    'description'   => 'Set to limit the view results.',
                    'allowMultiple' => false,
                    'type'          => 'integer',
                    'format'        => 'int32',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
                array(
                    'name'          => 'offset',
                    'description'   => 'Set to offset the view results to a particular record count.',
                    'allowMultiple' => false,
                    'type'          => 'integer',
                    'format'        => 'int32',
                    'paramType'     => 'query',
                    'required'      => false,
                ),
                array(
                    'name'          => 'order',
                    'description'   => 'SQL-like order containing field and direction for view results.',
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
                    'description'   => 'Include the total number of view results as meta data.',
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
            )
        ),
        'responseMessages' => $_commonResponses,
    ),
    array(
        'method'           => 'POST',
        'summary'          => 'createRecords() - Create one or more records.',
        'nickname'         => 'createRecords',
        'notes'            =>
            'Posted data should be an array of records wrapped in a <b>record</b> element.<br/> ' .
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
            )
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
                    'name'          => 'ids',
                    'description'   => 'Comma-delimited list of the identifiers of the resources to modify.',
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
            )
        ),
        'responseMessages' => $_commonResponses,
    ),
    array(
        'method'           => 'PUT',
        'summary'          => 'replaceRecords() - Update (replace) one or more records.',
        'nickname'         => 'replaceRecords',
        'notes'            =>
            'Post data should be an array of records wrapped in a <b>record</b> tag.<br/> ' .
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
            )
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
                    'type'          => 'RecordsRequest',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
                array(
                    'name'          => 'ids',
                    'description'   => 'Comma-delimited list of the identifiers of the resources to modify.',
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
            )
        ),
        'responseMessages' => $_commonResponses,
    ),
    array(
        'method'           => 'PATCH',
        'summary'          => 'updateRecords() - Update (patch) one or more records.',
        'nickname'         => 'updateRecords',
        'notes'            =>
            'Post data should be an array of records containing at least the identifying fields for each record.<br/> ' .
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
            )
        ),
        'responseMessages' => $_commonResponses,
    ),
    array(
        'method'           => 'DELETE',
        'summary'          => 'deleteRecordsByIds() - Delete one or more records.',
        'nickname'         => 'deleteRecordsByIds',
        'notes'            =>
            'Use <b>ids</b> to delete specific records.<br/> ' .
            'Alternatively, to delete by records, or a large list of ids, ' .
            'use the POST request with X-HTTP-METHOD = DELETE header.<br/> ' .
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
                    'description'   => 'Comma-delimited list of the identifiers of the resources to delete.',
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
            )
        ),
        'responseMessages' => $_commonResponses,
    ),
    array(
        'method'           => 'DELETE',
        'summary'          => 'deleteRecords() - Delete one or more records.',
        'nickname'         => 'deleteRecords',
        'notes'            =>
            'Use <b>ids</b> to delete specific records, otherwise set <b>force</b> to true to clear the table.<br/> ' .
            'Alternatively, to delete by records, or a large list of ids, ' .
            'use the POST request with X-HTTP-METHOD = DELETE header.<br/> ' .
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
                    'description'   => 'Data containing name-value pairs of records to update.',
                    'allowMultiple' => false,
                    'type'          => 'RecordsRequest',
                    'paramType'     => 'body',
                    'required'      => true,
                ),
                array(
                    'name'          => 'force',
                    'description'   => 'Set force to true to delete all records in this table, otherwise <b>ids</b> parameter is required.',
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
            )
        ),
        'responseMessages' => $_commonResponses,
    ),
);

$_base = require( __DIR__ . '/NoSqlDbSvc.swagger.php' );

return $_base;
