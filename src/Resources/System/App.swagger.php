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

$_app = array();

$_app['apis'] = array(
    array(
        'path'        => '/{api_name}/app',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getApps() - Retrieve one or more applications.',
                'nickname'         => 'getApps',
                'type'             => 'AppsResponse',
                'event_name'       => '{api_name}.apps.list',
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
                'summary'          => 'createApps() - Create one or more applications.',
                'nickname'         => 'createApps',
                'type'             => 'AppsResponse',
                'event_name'       => '{api_name}.apps.create',
                'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to create.',
                        'allowMultiple' => false,
                        'type'          => 'AppsRequest',
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
                'summary'          => 'updateApps() - Update one or more applications.',
                'nickname'         => 'updateApps',
                'type'             => 'AppsResponse',
                'event_name'       => '{api_name}.apps.update',
                'consumes'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'produces'         => array( 'application/json', 'application/xml', 'text/csv' ),
                'parameters'       => array(
                    array(
                        'name'          => 'body',
                        'description'   => 'Data containing name-value pairs of records to update.',
                        'allowMultiple' => false,
                        'type'          => 'AppsRequest',
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
                'summary'          => 'deleteApps() - Delete one or more applications.',
                'nickname'         => 'deleteApps',
                'type'             => 'AppsResponse',
                'event_name'       => '{api_name}.apps.delete',
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
                    array(
                        'name'          => 'delete_storage',
                        'description'   => 'If the app is hosted in a storage service, the storage will be deleted as well.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'default'       => false,
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
        'description' => 'Operations for application administration.',
    ),
    array(
        'path'        => '/{api_name}/app/{id}',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getApp() - Retrieve one application.',
                'nickname'         => 'getApp',
                'type'             => 'AppResponse',
                'event_name'       => '{api_name}.app.read',
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
                    array(
                        'name'          => 'pkg',
                        'description'   => 'Download this app as a DreamFactory package file.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'include_files',
                        'description'   => "If 'pkg' is true, include hosted files in the package.",
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'include_services',
                        'description'   => "If 'pkg' is true, include associated services configuration in the package.",
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'include_schema',
                        'description'   => "If 'pkg' is true, include associated database schema in the package.",
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                    ),
                    array(
                        'name'          => 'sdk',
                        'description'   => 'Download the DreamFactory Javascript SDK amended for this app.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
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
                'summary'          => 'updateApp() - Update one application.',
                'nickname'         => 'updateApp',
                'type'             => 'AppResponse',
                'event_name'       => '{api_name}.app.update',
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
                        'type'          => 'AppRequest',
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
                'summary'          => 'deleteApp() - Delete one application.',
                'nickname'         => 'deleteApp',
                'type'             => 'AppResponse',
                'event_name'       => '{api_name}.app.delete',
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
                    array(
                        'name'          => 'delete_storage',
                        'description'   => 'If the app is hosted in a storage service, the storage will be deleted as well.',
                        'allowMultiple' => false,
                        'type'          => 'boolean',
                        'paramType'     => 'query',
                        'required'      => false,
                        'default'       => false,
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
                'notes'            => ' By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return deleted properties.',
            ),
        ),
        'description' => 'Operations for individual application administration.',
    ),
);

$_commonProperties = array(
    'id'                      => array(
        'type'        => 'integer',
        'format'      => 'int32',
        'description' => 'Identifier of this application.',
    ),
    'name'                    => array(
        'type'        => 'string',
        'description' => 'Displayable name of this application.',
    ),
    'api_name'                => array(
        'type'        => 'string',
        'description' => 'Name of the application to use in API transactions.',
    ),
    'description'             => array(
        'type'        => 'string',
        'description' => 'Description of this application.',
    ),
    'is_active'               => array(
        'type'        => 'boolean',
        'description' => 'Is this system application active for use.',
    ),
    'url'                     => array(
        'type'        => 'string',
        'description' => 'URL for accessing this application.',
    ),
    'is_url_external'         => array(
        'type'        => 'boolean',
        'description' => 'True when this application is hosted elsewhere, but available in Launchpad.',
    ),
    'import_url'              => array(
        'type'        => 'string',
        'description' => 'If hosted and imported, the url of zip or package file where the code originated.',
    ),
    'storage_service_id'      => array(
        'type'        => 'string',
        'description' => 'If hosted, the storage service identifier.',
    ),
    'storage_container'       => array(
        'type'        => 'string',
        'description' => 'If hosted, the container of the storage service.',
    ),
    'requires_fullscreen'     => array(
        'type'        => 'boolean',
        'description' => 'True when this app needs to hide launchpad.',
    ),
    'allow_fullscreen_toggle' => array(
        'type'        => 'boolean',
        'description' => 'True to allow launchpad access via toggle.',
    ),
    'toggle_location'         => array(
        'type'        => 'string',
        'description' => 'Screen location for toggle placement.',
    ),
    'requires_plugin'         => array(
        'type'        => 'boolean',
        'description' => 'True when the app relies on a browser plugin.',
    ),
);

$_relatedProperties = array(
    'roles_default_app' => array(
        'type'        => 'RelatedRoles',
        'description' => 'Related roles by Role.default_app_id.',
    ),
    'users_default_app' => array(
        'type'        => 'RelatedUsers',
        'description' => 'Related users by User.default_app_id.',
    ),
    'app_groups'        => array(
        'type'        => 'RelatedAppGroups',
        'description' => 'Related groups by app to group assignment.',
    ),
    'roles'             => array(
        'type'        => 'RelatedRoles',
        'description' => 'Related roles by app to role assignment.',
    ),
    'services'          => array(
        'type'        => 'RelatedServices',
        'description' => 'Related services by app to service assignment.',
    ),
);

$_stampProperties = array(
    'created_date'        => array(
        'type'        => 'string',
        'description' => 'Date this application was created.',
        'readOnly'    => true,
    ),
    'created_by_id'       => array(
        'type'        => 'integer',
        'format'      => 'int32',
        'description' => 'User Id of who created this application.',
        'readOnly'    => true,
    ),
    'last_modified_date'  => array(
        'type'        => 'string',
        'description' => 'Date this application was last modified.',
        'readOnly'    => true,
    ),
    'last_modified_by_id' => array(
        'type'        => 'integer',
        'format'      => 'int32',
        'description' => 'User Id of who last modified this application.',
        'readOnly'    => true,
    ),
);

$_app['models'] = array(
    'AppRequest'   => array(
        'id'         => 'AppRequest',
        'properties' => array_merge(
            $_commonProperties,
            $_relatedProperties
        )
    ),
    'AppsRequest'  => array(
        'id'         => 'AppsRequest',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
                'description' => 'Array of system application records.',
                'items'       => array(
                    '$ref' => 'AppRequest',
                ),
            ),
            'ids'    => array(
                'type'        => 'array',
                'description' => 'Array of system application record identifiers, used for batch GET, PUT, PATCH, and DELETE.',
                'items'       => array(
                    'type'   => 'integer',
                    'format' => 'int32',
                ),
            ),
        ),
    ),
    'AppResponse'  => array(
        'id'         => 'AppResponse',
        'properties' => array_merge(
            $_commonProperties,
            $_relatedProperties,
            $_stampProperties
        ),
    ),
    'AppsResponse' => array(
        'id'         => 'AppsResponse',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
                'description' => 'Array of system application records.',
                'items'       => array(
                    '$ref' => 'AppResponse',
                ),
            ),
            'meta'   => array(
                'type'        => 'Metadata',
                'description' => 'Array of metadata returned for GET requests.',
            ),
        ),
    ),
    'RelatedApp'   => array(
        'id'         => 'RelatedApp',
        'properties' => array_merge(
            $_commonProperties,
            $_stampProperties
        )
    ),
    'RelatedApps'  => array(
        'id'         => 'RelatedApps',
        'properties' => array(
            'record' => array(
                'type'        => 'array',
                'description' => 'Array of system application records.',
                'items'       => array(
                    '$ref' => 'RelatedApp',
                ),
            ),
            'meta'   => array(
                'type'        => 'Metadata',
                'description' => 'Array of metadata returned for GET requests.',
            ),
        ),
    ),
);

return $_app;
