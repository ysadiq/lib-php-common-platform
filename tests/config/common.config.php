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
use DreamFactory\Platform\Utility\Fabric;

//*************************************************************************
//* Global Configuration Settings
//*************************************************************************

/**
 * common.config.php
 * This file contains any application-level parameters that are to be shared between the background and web services
 */
if ( !defined( 'DSP_VERSION' ) && file_exists( __DIR__ . '/constants.config.php' ) )
{
    require __DIR__ . '/constants.config.php';
}

//	What's my name biotch!?
$_appName = 'DreamFactory Services Platform';
//	The base path of the project, where it's checked out basically
$_basePath = dirname( __DIR__ );
//	The document root
$_docRoot = $_basePath;
//	The vendor path
$_vendorPath = dirname( $_basePath ) . '/vendor';
//	Set to false to disable database caching
$_dbCacheEnabled = false;
//	The name of the default controller. "site" just sucks
$_defaultController = 'web';
//	Where the log files go and the name...
if ( defined( 'TEST_BOOTSTRAP' ) )
{
    $_logFilePath = $_basePath;
}
else
{
    $_logFilePath = $_basePath . '/log';
}

$_logFileName = basename( \Kisma::get( 'app.log_file' ) );

/**
 * Aliases
 */
file_exists( __DIR__ . ALIASES_CONFIG_PATH ) && require __DIR__ . ALIASES_CONFIG_PATH;

/**
 * Application Paths
 */
\Kisma::set(
    array(
        'app.app_name'      => $_appName,
        'app.doc_root'      => $_docRoot,
        'app.log_path'      => $_logFilePath,
        'app.vendor_path'   => $_vendorPath,
        'app.log_file_name' => $_logFileName,
        'app.project_root'  => $_basePath,
    )
);

/**
 * Database Caching
 */
$_dbCache = $_dbCacheEnabled ? array(
    'class'                => 'CDbCache',
    'connectionID'         => 'db',
    'cacheTableName'       => 'df_sys_cache',
    'autoCreateCacheTable' => true,
) : null;

/**
 * Set up and return the common settings...
 */
if ( Fabric::fabricHosted() )
{
    $_blob = ( version_compare( DSP_VERSION, '2.0.0', '<' ) ? $_blob = '/blob' : null );

    $_instanceSettings = array(
        'storage_base_path'      => '/data/storage/' . \Kisma::get( 'platform.storage_key' ),
        'storage_path'           => '/data/storage/' . \Kisma::get( 'platform.storage_key' ) . '/blob',
        'private_path'           => \Kisma::get( 'platform.private_path' ),
        'snapshot_path'          => \Kisma::get( 'platform.private_path' ) . '/snapshots',
        'applications_path'      => '/data/storage/' . \Kisma::get( 'platform.storage_key' ) . $_blob . '/applications',
        'library_path'           => '/data/storage/' . \Kisma::get( 'platform.storage_key' ) . $_blob . '/plugins',
        'plugins_path'           => '/data/storage/' . \Kisma::get( 'platform.storage_key' ) . $_blob . '/plugins',
        'swagger_path'           => '/data/storage/' . \Kisma::get( 'platform.storage_key' ) . $_blob . '/swagger',
        'dsp_name'               => \Kisma::get( 'platform.dsp_name' ),
        'dsp.storage_id'         => \Kisma::get( 'platform.storage_key' ),
        'dsp.private_storage_id' => \Kisma::get( 'platform.private_storage_key' ),
    );
}
else
{
    $_instanceSettings = array(
        'storage_base_path'      => $_basePath . '/storage',
        'storage_path'           => $_basePath . '/storage',
        'private_path'           => $_basePath . '/storage/.private',
        'snapshot_path'          => $_basePath . '/storage/.private/snapshots',
        'applications_path'      => $_basePath . '/storage/applications',
        'library_path'           => $_basePath . '/storage/plugins',
        'plugins_path'           => $_basePath . '/storage/plugins',
        'swagger_path'           => $_basePath . '/storage/swagger',
        'dsp_name'               => gethostname(),
        'dsp.storage_id'         => null,
        'dsp.private_storage_id' => null,
    );
}

return array_merge(
    $_instanceSettings,
    array(
        /**
         * App Information
         */
        'base_path'                     => $_basePath,
        /**
         * DSP Information
         */
        'dsp.version'                   => DSP_VERSION,
        'dsp.name'                      => $_instanceSettings['dsp_name'],
        'dsp.auth_endpoint'             => DEFAULT_INSTANCE_AUTH_ENDPOINT,
        'cloud.endpoint'                => DEFAULT_CLOUD_API_ENDPOINT,
        'oauth.salt'                    => 'rW64wRUk6Ocs+5c7JwQ{69U{]MBdIHqmx9Wj,=C%S#cA%+?!cJMbaQ+juMjHeEx[dlSe%h%kcI',
        /**
         * Remote Logins
         */
        'dsp.allow_remote_logins'       => true,
        'dsp.allow_admin_remote_logins' => true,
        /**
         * User data
         */
        'adminEmail'                    => DEFAULT_SUPPORT_EMAIL,
        /**
         * The default service configuration
         */
        'dsp.service_config'            => require( __DIR__ . SERVICES_CONFIG_PATH ),
        /** Array of namespaces to locations for service discovery */
        'dsp.service_location_map'      => array(),
        /**
         * Default services provided by all DSPs
         */
        'dsp.default_services'          => array(
            array( 'api_name' => 'user', 'name' => 'User Login' ),
            array( 'api_name' => 'system', 'name' => 'System Configuration' ),
            array( 'api_name' => 'api_docs', 'name' => 'API Documentation' ),
        ),
        /**
         * The default application to start
         */
        'dsp.default_app'               => '/launchpad/index.html',
        /**
         * The default landing pages for email confirmations
         */
        'dsp.confirm_invite_url'        => '/confirm_invite.html',
        'dsp.confirm_register_url'      => '/confirm_reg.html',
        'dsp.confirm_reset_url'         => '/confirm_reset.html',
        /**
         * The default number of records to return at once for database queries
         */
        'dsp.db_max_records_returned'   => 1000,
    )
);
