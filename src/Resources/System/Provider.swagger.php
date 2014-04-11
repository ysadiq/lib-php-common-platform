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

$_provider = array();

$_provider['apis'] = array(
    array(
        'path'        => '/{api_name}/provider',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getProviders() - Retrieve one or more providers.',
                'nickname'         => 'getProviders',
                'type'             => 'ProvidersResponse',
                'provider_name'    => 'providers.list',
                'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'parameters'       => array(
                    array(
                        'name'          => 'user_id',
                        'description'   => 'If specified, filter the providers by the user ID given.',
                        'allowMultiple' => false,
                        'type'          => 'integer',
                        'format'        => 'int64',
                        'paramType'     => 'query',
                        'required'      => false,
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
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createProviders() - Create one or more providers.',
                'nickname'         => 'createProviders',
                'type'             => 'ProvidersResponse',
                'provider_name'    => 'providers.create',
                'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to create.',
                        'allowMultiple' => false,
                        'type'          => 'ProvidersRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to return for each record affected.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'related',
                        'description'   => 'Comma-delimited list of related names to return for each record affected.',
                        'allowMultiple' => true,
                        'type'          => 'string',
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
                    'Post data should be a single record or an array of records (shown). ' .
                    'By default, only the id property of the record affected is returned on success, ' .
                    'use \'fields\' and \'related\' to return more info.',
            ),
            array(
                'method'           => 'PATCH',
                'summary'          => 'updateProviders() - Update one or more providers.',
                'nickname'         => 'updateProviders',
                'type'             => 'ProvidersResponse',
                'provider_name'    => 'providers.update',
                'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to update.',
                        'allowMultiple' => false,
                        'type'          => 'ProvidersRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to return for each record affected.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'related',
                        'description'   => 'Comma-delimited list of related names to return for each record affected.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
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
                    'Post data should be a single record or an array of records (shown). ' .
                    'By default, only the id property of the record is returned on success, ' .
                    'use \'fields\' and \'related\' to return more info.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteProviders() - Delete one or more providers.',
                'nickname'         => 'deleteProviders',
                'type'             => 'ProvidersResponse',
                'provider_name'    => 'providers.delete',
                'parameters'       => array(
                    array(
                        'name'          => 'ids',
                        'description'   => 'Comma-delimited list of the identifiers of the records to delete.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'force',
                        'description'   => 'Set force to true to delete all records in this table, otherwise \'ids\' parameter is required.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'default'       => false,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to return for each record affected.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'related',
                        'description'   => 'Comma-delimited list of related names to return for each record affected.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
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
                    'By default, only the id property of the record deleted is returned on success. ' .
                    'Use \'fields\' and \'related\' to return more properties of the deleted records. <br>' .
                    'Alternatively, to delete by record or a large list of ids, ' .
                    'use the POST request with X-HTTP-METHOD = DELETE header and post records or ids.',
            ),
        ),
        'description' => 'Operations for provider administration.',
    ),
    array(
        'path'        => '/{api_name}/provider/{id}',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getProvider() - Retrieve one provider.',
                'nickname'         => 'getProvider',
                'type'             => 'ProviderResponse',
                'provider_name'    => 'provider.read',
                'parameters'       => array(
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
                        'description'   => 'Comma-delimited list of field names to return.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'related',
                        'description'   => 'Comma-delimited list of related records to return.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
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
                'notes'            => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
            ),
            array(
                'method'           => 'PATCH',
                'summary'          => 'updateProvider() - Update one provider.',
                'nickname'         => 'updateProvider',
                'type'             => 'ProviderResponse',
                'provider_name'    => 'provider.update',
                'parameters'       => array(
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
                        'description'   => 'Data containing name-value pairs of fields to update.',
                        'allowMultiple' => false,
                        'type'          => 'ProviderRequest',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to return.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'related',
                        'description'   => 'Comma-delimited list of related records to return.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
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
                    'Post data should be an array of fields to update for a single record. <br>' .
                    'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return more properties.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteProvider() - Delete one provider.',
                'nickname'         => 'deleteProvider',
                'type'             => 'ProviderResponse',
                'provider_name'    => 'provider.delete',
                'parameters'       => array(
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
                        'description'   => 'Comma-delimited list of field names to return.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'related',
                        'description'   => 'Comma-delimited list of related records to return.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
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
                'notes'            => 'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return deleted properties.',
            ),
        ),
        'description' => 'Operations for individual provider administration.',
    ),
);

$_configSettings = array(
    'ProviderConfigSettings' => array(
        'id'         => 'ProviderConfigSettings',
        'properties' => array(
            'settings' => array(
                'type'        => 'array',
                'description' => 'Array of provider configuration settings',
                'items'       => array(
                    '$ref' => 'ProviderConfigSetting',
                ),
            ),
        ),
    ),
    'ProviderConfigSetting'  => array(
        'id'         => 'ProviderConfigSetting',
        'required'   => array( 'key' ),
        'properties' => array(
            'key'   => array(
                'type' => 'string',
            ),
            'value' => array(
                'type' => 'string',
            ),
        ),
    ),
);

$_commonProperties = array(
    'id'                => array(
        'type'        => 'integer',
        'format'      => 'int32',
        'description' => 'Identifier of this provider.',
        'required'    => true,
    ),
    'provider_name'     => array(
        'type'        => 'string',
        'description' => 'The name of this provider',
        'required'    => true,
    ),
    'api_name'          => array(
        'type'        => 'string',
        'description' => 'The "api_name" or endpoint of this provider.',
        'required'    => true,
    ),
    'is_active'         => array(
        'type'         => 'boolean',
        'description'  => 'If true, this provider is active and available for use.',
        'required'     => false,
        'defaultValue' => false,
    ),
    'is_login_provider' => array(
        'type'         => 'boolean',
        'description'  => 'If true, this provider can be used to authenticate users.',
        'required'     => false,
        'defaultValue' => false,
    ),
    'is_system'         => array(
        'type'         => 'boolean',
        'description'  => 'If true, this provider is a system provider and cannot be changed.',
        'readOnly'     => true,
        'defaultValue' => false,
    ),
    'base_provider_id'  => array(
        'type'         => 'integer',
        'format'       => 'int64',
        'description'  => 'The ID of the provider upon which this provider is based. This parameter is deprecated in favor of the new "provider_name" field.',
        'readOnly'     => true,
        'defaultValue' => null,
    ),
    'config_text'       => array(
        'type'         => 'ProviderConfigSettings',
        'description'  => 'An array of configuration settings for this provider.',
        'defaultValue' => array(),
    ),
);

$_stampProperties = array(
    'created_date'        => array(
        'type'        => 'string',
        'format'      => 'date-time',
        'description' => 'Date this record was created.',
        'readOnly'    => true,
    ),
    'created_by_id'       => array(
        'type'        => 'integer',
        'format'      => 'int32',
        'description' => 'User Id of who created this record.',
        'readOnly'    => true,
    ),
    'last_modified_date'  => array(
        'type'        => 'string',
        'format'      => 'date-time',
        'description' => 'Date this record was last modified.',
        'readOnly'    => true,
    ),
    'last_modified_by_id' => array(
        'type'        => 'integer',
        'format'      => 'int32',
        'description' => 'User Id of who last modified this record.',
        'readOnly'    => true,
    ),
);

$_provider['models'] = array_merge(
    array(
        'ProviderRequest'   => array(
            'id'         => 'ProviderRequest',
            'properties' => array_merge(
                $_commonProperties,
                $_relatedProperties
            )
        ),
        'ProvidersRequest'  => array(
            'id'         => 'ProvidersRequest',
            'properties' => array(
                'record' => array(
                    'type'        => 'array',
                    'description' => 'Array of system provider records.',
                    'items'       => array(
                        'type' => 'string',
                    ),
                ),
                'ids'    => array(
                    'type'        => 'array',
                    'description' => 'Array of system record identifiers, used for batch GET, PUT, PATCH, and DELETE.',
                    'items'       => array(
                        'type'   => 'integer',
                        'format' => 'int32',
                    ),
                ),
            ),
        ),
        'ProviderResponse'  => array(
            'id'         => 'ProviderResponse',
            'properties' => array_merge(
                $_commonProperties,
                $_stampProperties
            ),
        ),
        'ProvidersResponse' => array(
            'id'         => 'ProvidersResponse',
            'properties' => array(
                'record' => array(
                    'type'        => 'array',
                    'description' => 'Array of system provider records.',
                    'items'       => array(
                        '$ref' => 'ProviderResponse',
                    ),
                ),
                'meta'   => array(
                    'type'        => 'Metadata',
                    'description' => 'Array of metadata returned for GET requests.',
                ),
            ),
        ),
    ),
    $_configSettings
);

return $_provider;
