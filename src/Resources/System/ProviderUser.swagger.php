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

$_providerProviderUser = array();

$_providerProviderUser['apis'] = array(
    array(
        'path'        => '/{api_name}/provider_user',
        'operations'  => array(
            array(
                'method'           => 'GET',
<<<<<<< HEAD
<<<<<<< HEAD
                'summary'          => 'getProviderUsers() - Retrieve one or more provider provider users.',
                'nickname'         => 'getProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'provider_users.list',
=======
                'summary'          => 'getProviderUsers() - Retrieve one or more users.',
                'nickname'         => 'getProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'users.list',
>>>>>>> Swagger updates. Event container tweak
=======
                'summary'          => 'getProviderUsers() - Retrieve one or more provider provider users.',
                'nickname'         => 'getProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'provider_users.list',
>>>>>>> ProviderUser swagger file
                'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'parameters'       => array(
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
                        'name'          => 'order',
                        'description'   => 'SQL-like order containing field and direction for filter results.',
                        'allowMultiple' => false,
                        'type'          => 'string',
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
                        'name'          => 'fields',
                        'description'   => 'Comma-delimited list of field names to retrieve for each record.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'related',
                        'description'   => 'Comma-delimited list of related names to retrieve for each record.',
                        'allowMultiple' => true,
                        'type'          => 'string',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'include_count',
                        'description'   => 'Include the total number of filter results in returned metadata.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'include_schema',
                        'description'   => 'Include the schema of the table queried in returned metadata.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'file',
                        'description'   => 'Download the results of the request as a file.',
                        'allowMultiple' => false,
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
                    'Use the \'ids\' or \'filter\' parameter to limit records that are returned. ' .
                    'By default, all records up to the maximum are returned. <br>' .
                    'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
                    'By default, all fields and no relations are returned for each record. <br>' .
                    'Alternatively, to retrieve by record, a large list of ids, or a complicated filter, ' .
                    'use the POST request with X-HTTP-METHOD = GET header and post records or ids.',
            ),
            array(
                'method'           => 'POST',
<<<<<<< HEAD
<<<<<<< HEAD
                'summary'          => 'createProviderUsers() - Create one or more provider users.',
                'nickname'         => 'createProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'provider_users.create',
=======
                'summary'          => 'createProviderUsers() - Create one or more users.',
                'nickname'         => 'createProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'users.create',
>>>>>>> Swagger updates. Event container tweak
=======
                'summary'          => 'createProviderUsers() - Create one or more provider users.',
                'nickname'         => 'createProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'provider_users.create',
>>>>>>> ProviderUser swagger file
                'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to create.',
                        'allowMultiple' => false,
                        'type'          => 'ProviderUsersRequest',
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
<<<<<<< HEAD
<<<<<<< HEAD
                'summary'          => 'updateProviderUsers() - Update one or more provider provider users.',
                'nickname'         => 'updateProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'provider_users.update',
=======
                'summary'          => 'updateProviderUsers() - Update one or more users.',
                'nickname'         => 'updateProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'users.update',
>>>>>>> Swagger updates. Event container tweak
=======
                'summary'          => 'updateProviderUsers() - Update one or more provider provider users.',
                'nickname'         => 'updateProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'provider_users.update',
>>>>>>> ProviderUser swagger file
                'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to update.',
                        'allowMultiple' => false,
                        'type'          => 'ProviderUsersRequest',
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
<<<<<<< HEAD
<<<<<<< HEAD
                'summary'          => 'deleteProviderUsers() - Delete one or more provider users.',
                'nickname'         => 'deleteProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'provider_users.delete',
=======
                'summary'          => 'deleteProviderUsers() - Delete one or more users.',
                'nickname'         => 'deleteProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'users.delete',
>>>>>>> Swagger updates. Event container tweak
=======
                'summary'          => 'deleteProviderUsers() - Delete one or more provider users.',
                'nickname'         => 'deleteProviderUsers',
                'type'             => 'ProviderUsersResponse',
                'event_name'       => 'provider_users.delete',
>>>>>>> ProviderUser swagger file
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
<<<<<<< HEAD
<<<<<<< HEAD
        'description' => 'Operations for provider user administration.',
=======
        'description' => 'Operations for user administration.',
>>>>>>> Swagger updates. Event container tweak
=======
        'description' => 'Operations for provider user administration.',
>>>>>>> ProviderUser swagger file
    ),
    array(
        'path'        => '/{api_name}/user/{id}',
        'operations'  => array(
            array(
                'method'           => 'GET',
<<<<<<< HEAD
<<<<<<< HEAD
                'summary'          => 'getProviderUser() - Retrieve one provider user.',
                'nickname'         => 'getProviderUser',
                'type'             => 'ProviderUserResponse',
                'event_name'       => 'provider_user.read',
=======
                'summary'          => 'getProviderUser() - Retrieve one user.',
                'nickname'         => 'getProviderUser',
                'type'             => 'ProviderUserResponse',
                'event_name'       => 'user.read',
>>>>>>> Swagger updates. Event container tweak
=======
                'summary'          => 'getProviderUser() - Retrieve one provider user.',
                'nickname'         => 'getProviderUser',
                'type'             => 'ProviderUserResponse',
                'event_name'       => 'provider_user.read',
>>>>>>> ProviderUser swagger file
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
<<<<<<< HEAD
<<<<<<< HEAD
                'summary'          => 'updateProviderUser() - Update one provider user.',
                'nickname'         => 'updateProviderUser',
                'type'             => 'ProviderUserResponse',
                'event_name'       => 'provider_user.update',
=======
                'summary'          => 'updateProviderUser() - Update one user.',
                'nickname'         => 'updateProviderUser',
                'type'             => 'ProviderUserResponse',
                'event_name'       => 'user.update',
>>>>>>> Swagger updates. Event container tweak
=======
                'summary'          => 'updateProviderUser() - Update one provider user.',
                'nickname'         => 'updateProviderUser',
                'type'             => 'ProviderUserResponse',
                'event_name'       => 'provider_user.update',
>>>>>>> ProviderUser swagger file
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
                        'type'          => 'ProviderUserRequest',
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
<<<<<<< HEAD
<<<<<<< HEAD
                'summary'          => 'deleteProviderUser() - Delete one provider user.',
                'nickname'         => 'deleteProviderUser',
                'type'             => 'ProviderUserResponse',
                'event_name'       => 'provider_user.delete',
=======
                'summary'          => 'deleteProviderUser() - Delete one user.',
                'nickname'         => 'deleteProviderUser',
                'type'             => 'ProviderUserResponse',
                'event_name'       => 'user.delete',
>>>>>>> Swagger updates. Event container tweak
=======
                'summary'          => 'deleteProviderUser() - Delete one provider user.',
                'nickname'         => 'deleteProviderUser',
                'type'             => 'ProviderUserResponse',
                'event_name'       => 'provider_user.delete',
>>>>>>> ProviderUser swagger file
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
<<<<<<< HEAD
<<<<<<< HEAD
        'description' => 'Operations for individual provider user administration.',
=======
        'description' => 'Operations for individual user administration.',
>>>>>>> Swagger updates. Event container tweak
=======
        'description' => 'Operations for individual provider user administration.',
>>>>>>> ProviderUser swagger file
    ),
);

$_commonProperties = array(
    'id'             => array(
        'type'        => 'integer',
        'format'      => 'int32',
<<<<<<< HEAD
<<<<<<< HEAD
        'description' => 'Identifier of this provider user.',
    ),
    'email'          => array(
        'type'        => 'string',
        'description' => 'The email address required for this provider user.',
=======
        'description' => 'Identifier of this user.',
    ),
    'email'          => array(
        'type'        => 'string',
        'description' => 'The email address required for this user.',
>>>>>>> Swagger updates. Event container tweak
=======
        'description' => 'Identifier of this provider user.',
    ),
    'email'          => array(
        'type'        => 'string',
        'description' => 'The email address required for this provider user.',
>>>>>>> ProviderUser swagger file
    ),
    'password'       => array(
        'type'        => 'string',
        'description' => 'The set-able, but never readable, password.',
    ),
    'first_name'     => array(
        'type'        => 'string',
<<<<<<< HEAD
<<<<<<< HEAD
        'description' => 'The first name for this provider user.',
    ),
    'last_name'      => array(
        'type'        => 'string',
        'description' => 'The last name for this provider user.',
    ),
    'display_name'   => array(
        'type'        => 'string',
        'description' => 'Displayable name of this provider user.',
    ),
    'phone'          => array(
        'type'        => 'string',
        'description' => 'Phone number for this provider user.',
    ),
    'is_active'      => array(
        'type'        => 'boolean',
        'description' => 'True if this provider user is active for use.',
    ),
    'is_sys_admin'   => array(
        'type'        => 'boolean',
        'description' => 'True if this provider user is a system admin.',
    ),
    'default_app_id' => array(
        'type'        => 'string',
        'description' => 'The default launched app for this provider user.',
    ),
    'role_id'        => array(
        'type'        => 'string',
        'description' => 'The role to which this provider user is assigned.',
=======
        'description' => 'The first name for this user.',
=======
        'description' => 'The first name for this provider user.',
>>>>>>> ProviderUser swagger file
    ),
    'last_name'      => array(
        'type'        => 'string',
        'description' => 'The last name for this provider user.',
    ),
    'display_name'   => array(
        'type'        => 'string',
        'description' => 'Displayable name of this provider user.',
    ),
    'phone'          => array(
        'type'        => 'string',
        'description' => 'Phone number for this provider user.',
    ),
    'is_active'      => array(
        'type'        => 'boolean',
        'description' => 'True if this provider user is active for use.',
    ),
    'is_sys_admin'   => array(
        'type'        => 'boolean',
        'description' => 'True if this provider user is a system admin.',
    ),
    'default_app_id' => array(
        'type'        => 'string',
        'description' => 'The default launched app for this provider user.',
    ),
    'role_id'        => array(
        'type'        => 'string',
<<<<<<< HEAD
        'description' => 'The role to which this user is assigned.',
>>>>>>> Swagger updates. Event container tweak
=======
        'description' => 'The role to which this provider user is assigned.',
>>>>>>> ProviderUser swagger file
    ),
);

$_relatedProperties = array(
    'default_app' => array(
        'type'        => 'RelatedApp',
        'description' => 'Related app by default_app_id.',
    ),
    'role'        => array(
        'type'        => 'RelatedRole',
        'description' => 'Related role by role_id.',
    ),
);

$_stampProperties = array(
    'created_date'        => array(
        'type'        => 'string',
<<<<<<< HEAD
<<<<<<< HEAD
        'description' => 'Date this provider user was created.',
=======
        'description' => 'Date this user was created.',
>>>>>>> Swagger updates. Event container tweak
=======
        'description' => 'Date this provider user was created.',
>>>>>>> ProviderUser swagger file
    ),
    'created_by_id'       => array(
        'type'        => 'integer',
        'format'      => 'int32',
<<<<<<< HEAD
<<<<<<< HEAD
        'description' => 'ProviderUser Id of who created this provider user.',
    ),
    'last_modified_date'  => array(
        'type'        => 'string',
        'description' => 'Date this provider user was last modified.',
=======
        'description' => 'ProviderUser Id of who created this user.',
    ),
    'last_modified_date'  => array(
        'type'        => 'string',
        'description' => 'Date this user was last modified.',
>>>>>>> Swagger updates. Event container tweak
=======
        'description' => 'ProviderUser Id of who created this provider user.',
    ),
    'last_modified_date'  => array(
        'type'        => 'string',
        'description' => 'Date this provider user was last modified.',
>>>>>>> ProviderUser swagger file
    ),
    'last_modified_by_id' => array(
        'type'        => 'integer',
        'format'      => 'int32',
<<<<<<< HEAD
<<<<<<< HEAD
        'description' => 'ProviderUser Id of who last modified this provider user.',
=======
        'description' => 'ProviderUser Id of who last modified this user.',
>>>>>>> Swagger updates. Event container tweak
=======
        'description' => 'ProviderUser Id of who last modified this provider user.',
>>>>>>> ProviderUser swagger file
    ),
);

$_providerProviderUser['models'] = array(
    'ProviderUserRequest'   => array(
        'id'         => 'ProviderUserRequest',
        'properties' => array_merge(
            $_commonProperties,
            $_relatedProperties
        )
    ),
    'ProviderUsersRequest'  => array(
        'id'         => 'ProviderUsersRequest',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
<<<<<<< HEAD
<<<<<<< HEAD
                'description' => 'Array of system provider user records.',
=======
                'description' => 'Array of system user records.',
>>>>>>> Swagger updates. Event container tweak
=======
                'description' => 'Array of system provider user records.',
>>>>>>> ProviderUser swagger file
                'items'       => array(
                    '$ref' => 'ProviderUserRequest',
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
    'ProviderUserResponse'  => array(
        'id'         => 'ProviderUserResponse',
        'properties' => array_merge(
            $_commonProperties,
            $_relatedProperties,
            $_stampProperties,
            array(
                'last_login_date' => array(
                    'type'        => 'string',
                    'description' => 'Timestamp of the last login.',
                ),
            )
        ),
    ),
    'ProviderUsersResponse' => array(
        'id'         => 'ProviderUsersResponse',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
<<<<<<< HEAD
<<<<<<< HEAD
                'description' => 'Array of system provider user records.',
=======
                'description' => 'Array of system user records.',
>>>>>>> Swagger updates. Event container tweak
=======
                'description' => 'Array of system provider user records.',
>>>>>>> ProviderUser swagger file
                'items'       => array(
                    '$ref' => 'ProviderUserResponse',
                ),
            ),
            'meta'   => array(
                'type'        => 'Metadata',
                'description' => 'Array of metadata returned for GET requests.',
            ),
        ),
    ),
    'RelatedProviderUser'   => array(
        'id'         => 'RelatedProviderUser',
        'properties' => array_merge(
            $_commonProperties,
            $_stampProperties
        )
    ),
    'RelatedProviderUsers'  => array(
        'id'         => 'RelatedProviderUsers',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
<<<<<<< HEAD
<<<<<<< HEAD
                'description' => 'Array of system provider provider user records.',
=======
                'description' => 'Array of system user records.',
>>>>>>> Swagger updates. Event container tweak
=======
                'description' => 'Array of system provider provider user records.',
>>>>>>> ProviderUser swagger file
                'items'       => array(
                    '$ref' => 'RelatedProviderUser',
                ),
            ),
            'meta'   => array(
                'type'        => 'Metadata',
                'description' => 'Array of metadata returned for GET requests.',
            ),
        ),
    ),
);

return $_providerProviderUser;
