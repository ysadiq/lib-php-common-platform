<?php
/**
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Utility;

use DreamFactory\Library\Utility\Exceptions\FileException;
use DreamFactory\Library\Utility\Includer;
use DreamFactory\Library\Utility\JsonFile;
use DreamFactory\Platform\Enums\FabricPlatformStates;
use DreamFactory\Platform\Enums\LocalStoragePaths;
use DreamFactory\Platform\Interfaces\ClusterStorageProviderLike;
use DreamFactory\Platform\Interfaces\PlatformStates;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\DateTime;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//********************************************************************************
//* Check for maintenance mode...
//********************************************************************************

if ( is_file( Fabric::MAINTENANCE_MARKER ) && Fabric::MAINTENANCE_URI != Option::server( 'REQUEST_URI' ) )
{
    header( 'Location: ' . Fabric::MAINTENANCE_URI );
    die();
}

/** Initialize the class */
Fabric::initialize();

/**
 * Fabric.php
 * The configuration file for fabric-hosted DSPs
 */
class Fabric
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var string
     */
    const FABRIC_API_ENDPOINT = 'http://cerberus.fabric.dreamfactory.com/api';
    /**
     * @var string
     */
    const DEFAULT_AUTH_ENDPOINT = 'http://cerberus.fabric.dreamfactory.com/api/instance/credentials';
    /**
     * @var string
     */
    const DEFAULT_PROVIDER_ENDPOINT = 'http://oasys.cloud.dreamfactory.com/oauth/providerCredentials';
    /**
     * @var string
     */
    const INSTANCE_CONFIG_FILE_NAME_PATTERN = '/instance.json';
    /**
     * @var string
     */
    const DB_CONFIG_FILE_NAME_PATTERN = '/{instance_name}.database.config.php';
    /**
     * @var string
     */
    const DSP_DEFAULT_SUBDOMAIN = '.cloud.dreamfactory.com';
    /**
     * @var string
     */
    const FABRIC_MARKER = '/var/www/.fabric_hosted';
    /**
     * @var string
     */
    const DEFAULT_DOC_ROOT = '/var/www/launchpad/web';
    /**
     * @var string
     */
    const MAINTENANCE_MARKER = '/var/www/.fabric_maintenance';
    /**
     * @var string
     */
    const MAINTENANCE_URI = '/static/dreamfactory/maintenance.php';
    /**
     * @var string
     */
    const UNAVAILABLE_URI = '/static/dreamfactory/unavailable.php';
    /**
     * @var int
     */
    const EXPIRATION_THRESHOLD = 30;
    /**
     * @var string Public storage cookie key
     */
    const PUBLIC_STORAGE_COOKIE = 'dsp.storage_key';
    /**
     * @var string Private storage cookie key
     */
    const PRIVATE_STORAGE_COOKIE = 'dsp.private_storage_id';

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type Request
     */
    protected static $_request = null;
    /**
     * @type ClusterStorageProviderLike
     */
    protected static $_clusterStorage = null;
    /**
     * @type string The instance host name
     */
    protected static $_hostname = null;
    /**
     * @type callable Callback to get the list of allowed hosts at runtime
     */
    protected static $_allowedHostsCallback = null;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Initialization for hosted DSPs
     *
     * @throws \CHttpException
     * @return array|mixed
     * @throws \RuntimeException
     * @throws \CHttpException
     */
    public static function initialize()
    {
        static $_config = false;

        if ( !$_config )
        {
            static::$_hostname = static::getHostname();

            //  Initialize the storage system
            if ( empty( static::$_clusterStorage ) )
            {
                static::$_clusterStorage = new ClusterStorage();
                static::$_clusterStorage->initialize( static::$_hostname, LocalStoragePaths::STORAGE_MOUNT_POINT );
            }

            //	If this isn't a hosted instance, bail
            if ( !static::isAllowedHost() && false === stripos( static::$_hostname, static::DSP_DEFAULT_SUBDOMAIN ) )
            {
                throw new \CHttpException(
                    Response::HTTP_FORBIDDEN,
                    'You are not authorized to access this system you cheeky devil you. (' . static::$_hostname . ').'
                );
            }

            if ( false !== ( list( $_instance, $_config ) = static::_getInstanceConfig( static::$_hostname ) ) )
            {
                //	Save it for later (don't run away and let me down <== extra points if you get the reference)
                setcookie( static::PUBLIC_STORAGE_COOKIE, $_instance->storage_key, time() + DateTime::TheEnd, '/' );
                setcookie( static::PRIVATE_STORAGE_COOKIE, $_instance->private_storage_key, time() + DateTime::TheEnd, '/' );

                return $_config;
            }

            //  Wipe out the cookies
            setcookie( static::PUBLIC_STORAGE_COOKIE, '', 0, '/' );
            setcookie( static::PRIVATE_STORAGE_COOKIE, '', 0, '/' );

            throw new \LogicException( 'Unable to find database configuration' );
        }

        return $_config;
    }

    /**
     * @return bool True if this DSP is fabric-hosted (i.e. marker exists and doc root matches)
     */
    public static function fabricHosted()
    {
        static $_fabricHosted = null;

        if ( null !== $_fabricHosted )
        {
            return $_fabricHosted;
        }

        return $_fabricHosted =
            ( static::DEFAULT_DOC_ROOT == static::getRequest()->server->get( 'document-root' ) && file_exists( static::FABRIC_MARKER ) );
    }

    /**
     * @param bool $returnHost If true and the host is private, the host name is returned instead of TRUE. FALSE is
     *                         still returned if false
     *
     * @return bool
     */
    public static function isAllowedHost( $returnHost = false )
    {
        /**
         * Add host names to this list to white-list...
         */
        static $_allowedHosts = array(
            'launchpad-dev.dreamfactory.com',
            'launchpad-demo.dreamfactory.com',
            'next.cloud.dreamfactory.com',
        );

        if ( is_callable( static::$_allowedHostsCallback ) )
        {
            $_result = call_user_func( static::$_allowedHostsCallback );

            if ( !is_array( $_result ) )
            {
                throw new \LogicException( 'The $allowedHostsCallback must return an array.' );
            }

            $_allowedHosts = array_merge( $_allowedHosts, array_values( $_result ) );
        }

        return in_array( static::$_hostname, $_allowedHosts ) ? ( $returnHost ? static::$_hostname : true ) : false;
    }

    /**
     * Retrieves a global login provider credential set
     *
     * @param string $id
     * @param bool   $force
     *
     * @return array
     */
    public static function getProviderCredentials( $id = null, $force = false )
    {
        if ( !$force && !static::fabricHosted() && !static::isAllowedHost() )
        {
            Log::info( 'Global provider credential pull skipped: not hosted entity.' );

            return array();
        }

        //	Otherwise, get the credentials from the auth server...
        $_url = static::DEFAULT_PROVIDER_ENDPOINT . '/';

        if ( null !== $id )
        {
            $_url .= $id . '/';
        }

        $_response = Curl::get( $_url . '?oasys=' . urlencode( Pii::getParam( 'oauth.salt' ) ) );
        $_code = Curl::getLastHttpCode();

        if ( !$_response || Response::HTTP_OK != $_code || !is_object( $_response ) || !$_response->success )
        {
            static::_errorLog( 'Global provider credential pull failed: ' . $_code . PHP_EOL . print_r( $_response, true ) );

            return array();
        }

        return Option::get( $_response, 'details', array() );
    }

    /**
     * Writes the cache file out to disk
     *
     * @param \stdClass $instance
     * @param array     $dbConfig
     *
     * @throws \Exception
     */
    protected static function _cacheSettings( $instance, $dbConfig )
    {
        static::_writeInstanceConfig( $instance );
        static::_writeDbConfig( $instance, false );
    }

    /**
     * @param string $message
     * @param array  $context
     */
    protected static function _errorLog( $message, $context = array() )
    {
        Log::error( $message, $context );
    }

    /**
     * @param string $instanceName
     *
     * @return \stdClass|array
     * @throws \CHttpException
     */
    protected static function _getInstanceConfig( $instanceName )
    {
        $_config = null;
        $_dspName = str_ireplace( static::DSP_DEFAULT_SUBDOMAIN, null, $instanceName );

        $_instanceDetails = static::_readInstanceConfig( $_dspName );
        $_config = static::_readDbConfig( $_dspName );

        if ( false === $_instanceDetails || false === $_config )
        {
            //	Try and get them from server...
            $_response = static::_getInstanceCredentials( $instanceName, $_dspName );

            static::_writeInstanceConfig( $_instanceDetails = $_response->details );
            $_config = static::_writeDbConfig( $_instanceDetails );
        }

        //  Check for enterprise status
        static::_checkPlatformState( $_dspName );

        return array($_instanceDetails, $_config);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array  $payload
     * @param array  $curlOptions
     *
     * @return bool|\stdClass|array
     */
    public static function api( $method, $uri, $payload = array(), $curlOptions = array() )
    {
        if ( !HttpMethod::contains( strtoupper( $method ) ) )
        {
            throw new \InvalidArgumentException( 'The method "' . $method . '" is invalid.' );
        }

        try
        {
            if ( false ===
                ( $_result =
                    Curl::request(
                        $method,
                        static::FABRIC_API_ENDPOINT . '/' . ltrim( $uri, '/ ' ),
                        $payload,
                        $curlOptions
                    ) )
            )
            {
                throw new \RuntimeException( 'Failed to contact API server.' );
            }

            return $_result;
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Fabric::api error: ' . $_ex->getMessage() );

            return false;
        }
    }

    /**
     * Check platform state for locks, etc.
     *
     * @param string $dspName
     */
    protected static function _checkPlatformState( $dspName )
    {
        if ( false !== ( $_states = Platform::getPlatformStates( $dspName ) ) )
        {
            if ( $_states['operation_state'] > FabricPlatformStates::ACTIVATED )
            {
                Pii::redirect( static::UNAVAILABLE_URI );
                exit( FabricPlatformStates::LOCKED );
            }
        }
    }

    /**
     * @param string $instanceName
     *
     * @return array|bool
     */
    protected static function _readDbConfig( $instanceName )
    {
        $_fileName =
            static::$_clusterStorage->getLocalConfigPath() .
            static::_makeFileName( static::DB_CONFIG_FILE_NAME_PATTERN, array('{instance_name}' => $instanceName) );

        if ( !file_exists( $_fileName ) )
        {
            return false;
        }

        return Includer::includeIfExists( $_fileName, true );
    }

    /**
     * @param \stdClass $instanceDetails
     * @param bool      $includeAfter If true, config is included and returned
     *
     * @return array
     */
    protected static function _writeDbConfig( $instanceDetails, $includeAfter = true )
    {
        $_fileName =
            static::$_clusterStorage->getLocalConfigPath() .
            static::_makeFileName( static::DB_CONFIG_FILE_NAME_PATTERN, array('{instance_name}' => $instanceDetails->instance->instance_name_text) );

        if ( file_exists( $_fileName ) )
        {
            //  Save a copy
            @copy( $_fileName, $_fileName . '.save' );
        }

        $_version = 'v' . Platform::getPlatformCoreVersion();
        $_date = date( 'c' );

        $_php = <<<PHP
<?php
/**
 * **** DO NOT MODIFY THIS FILE ****
 * **** CHANGES WILL BREAK YOUR DSP AND COULD BE OVERWRITTEN AT ANY TIME ****
 * @(#)\$Id: {$instanceDetails->instance->instance_name_text}.database.config.php; {$_version}-fabric {$_date} \$
 */
return array(
    'connectionString'      => 'mysql:host={$instanceDetails->db_host};port={$instanceDetails->db_port};dbname={$instanceDetails->db_name}',
    'username'              => '{$instanceDetails->db_user}',
    'password'              => '{$instanceDetails->db_password}',
    'emulatePrepare'        => true,
    'charset'               => 'utf8',
    'schemaCachingDuration' => 3600,
);
PHP;

        //  Write configs
        if ( false === file_put_contents( $_fileName, $_php ) )
        {
            throw new FileException( 'Unable to create configuration file: ' . $_fileName );
        }

        //	Stick this in persistent storage
        $_instanceDetails = [
            'dsp.credentials'                     => $instanceDetails,
            'dsp.db_name'                         => $instanceDetails->db_name,
            'platform.dsp_name'                   => $instanceDetails->instance->instance_name_text,
            'platform.private_path'               => static::$_clusterStorage->getPrivatePath(),
            'platform.storage_key'                => static::$_clusterStorage->getStorageKey( $instanceDetails->storage_key ),
            'platform.legacy_storage_key'         => $instanceDetails->storage_key,
            'platform.private_storage_key'        => static::$_clusterStorage->getPrivateStorageKey( $instanceDetails->private_storage_key ),
            'platform.legacy_private_storage_key' => $instanceDetails->private_storage_key,
            'platform.db_config_file'             => $_fileName,
            'platform.db_config_file_name'        => basename( $_fileName ),
            PlatformStates::STATE_KEY             => null,
        ];

        \Kisma::set( $_instanceDetails );

        //  Dogfood it if wanted...
        return $includeAfter ? Includer::includeIfExists( $_fileName ) : $_instanceDetails;
    }

    /**
     * @param string $instanceName
     *
     * @return \stdClass|bool
     */
    protected static function _readInstanceConfig( $instanceName )
    {
        $_fileName =
            static::$_clusterStorage->getLocalConfigPath() .
            static::_makeFileName(
                static::INSTANCE_CONFIG_FILE_NAME_PATTERN,
                array('{instance_name}' => $instanceName)
            );

        if ( !file_exists( $_fileName ) )
        {
            return false;
        }

        return JsonFile::decodeFile( $_fileName, false );
    }

    /**
     * @param \stdClass $instanceDetails
     *
     * @return bool
     */
    protected static function _writeInstanceConfig( $instanceDetails )
    {
        $_fileName =
            static::$_clusterStorage->getLocalConfigPath() .
            static::_makeFileName(
                static::INSTANCE_CONFIG_FILE_NAME_PATTERN,
                array('{instance_name}' => $instanceDetails->instance->instance_name_text)
            );

        if ( file_exists( $_fileName ) )
        {
            //  Save a copy
            @copy( $_fileName, $_fileName . '.save' );
        }

        JsonFile::encodeFile( $_fileName, $instanceDetails );

        return true;
    }

    /**
     * @param string $pattern
     * @param array  $values
     *
     * @return string
     */
    protected static function _makeFileName( $pattern, array $values = array() )
    {
        return str_ireplace( array_keys( $values ), array_values( $values ), $pattern );
    }

    /**
     * @param string $hostname
     * @param string $instanceName
     *
     * @return array|bool|\stdClass
     * @throws \CHttpException
     */
    protected static function _getInstanceCredentials( $hostname, $instanceName )
    {
        //	Get the credentials from the auth server...
        $_response = static::api( Request::METHOD_GET, '/instance/credentials/' . $instanceName . '/database' );

        if ( Response::HTTP_NOT_FOUND == Curl::getLastHttpCode() )
        {
            static::_errorLog( 'DB Credential pull failure. Redirecting to df.com: ' . $hostname );
            header( 'Location: https://www.dreamfactory.com/dsp-not-found?dn=' . urlencode( $instanceName ) );
            exit( 1 );
        }

        if ( is_object( $_response ) &&
            isset( $_response->details, $_response->details->code ) &&
            Response::HTTP_NOT_FOUND == $_response->details->code
        )
        {
            static::_errorLog( 'Instance "' . $instanceName . '" not found during web initialize.' );

            throw new \RuntimeException( 'Instance not available.' );
        }

        if ( !$_response || !is_object( $_response ) || false == $_response->success )
        {
            static::_errorLog( 'Error connecting to authentication service: ' . print_r( $_response, true ) );

            throw new \RuntimeException( 'Cannot connect to authentication service' );
        }

        return $_response;
    }

    /**
     * @return Request
     */
    public static function getRequest()
    {
        return static::$_request ?: static::$_request = Request::createFromGlobals();
    }

    /**
     * @return ClusterStorageProviderLike
     */
    public static function getClusterStorage()
    {
        return static::$_clusterStorage;
    }

    /**
     * @return string The hostname of the DSP servicing  this request
     */
    public static function getHostname()
    {
        return static::getRequest()->getHttpHost();
    }
}
