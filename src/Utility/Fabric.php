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

use DreamFactory\Library\Utility\Exception\FileException;
use DreamFactory\Library\Utility\JsonFile;
use DreamFactory\Platform\Enums\FabricPlatformStates;
use DreamFactory\Platform\Interfaces\PlatformStates;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\DateTime;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\SeedUtility;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\FilterInput;
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

/**
 * Fabric.php
 * The configuration file for fabric-hosted DSPs
 */
class Fabric extends SeedUtility
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
    const INSTANCE_CONFIG_FILE_NAME = '/instance.json';
    /**
     * @var string
     */
    const INSTANCE_CONFIG_FILE_NAME_PATTERN = '/{instance_name}.instance.json';
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
     * @var string Private storage cookie
     */
    const PRIVATE_STORAGE_COOKIE = 'dsp.private';
    /**
     * @var string Public storage cookie
     */
    const PUBLIC_STORAGE_COOKIE = 'dsp.blob';

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type Request
     */
    protected static $_request = null;
    /**
     * @type HostedStorage
     */
    protected static $_storage = null;
    /**
     * @type string The instance host name
     */
    protected static $_hostname = null;

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
        static::$_request = Request::createFromGlobals();
        static::$_hostname = static::$_request->getHttpHost();
        static::$_storage = new HostedStorage( static::$_hostname );

        //	If this isn't a hosted instance, bail
        if ( !static::hostedPrivatePlatform() && false === stripos( static::$_hostname, static::DSP_DEFAULT_SUBDOMAIN ) )
        {
            throw new \CHttpException(
                Response::HTTP_FORBIDDEN,
                'You are not authorized to access this system you cheeky devil you. (' . static::$_hostname . ').'
            );
        }

        if ( false !== ( $_dbConfig = static::_getDatabaseConfig( static::$_hostname ) ) )
        {
            return $_dbConfig;
        }

        //  Wipe out the cookies
        setcookie( static::PRIVATE_STORAGE_COOKIE, '', 0, '/' );
        throw new \CHttpException( Response::HTTP_BAD_REQUEST, 'Unable to find database configuration' );
    }

    /**
     * @return bool True if this DSP is fabric-hosted (i.e. marker exists and doc root matches)
     */
    public static function fabricHosted()
    {
        static $_fabricHosted = null;

        if ( null === $_fabricHosted )
        {
            $_fabricHosted =
                ( static::DEFAULT_DOC_ROOT == static::$_request->server->get( 'document-root' ) && file_exists( static::FABRIC_MARKER ) );
        }

        return $_fabricHosted;
    }

    /**
     * @param bool $returnHost If true and the host is private, the host name is returned instead of TRUE. FALSE is
     *                         still returned if false
     *
     * @return bool
     */
    public static function hostedPrivatePlatform( $returnHost = false )
    {
        /**
         * Add host names to this list to white-list...
         */
        static $_allowedHosts = array(
            'launchpad-dev.dreamfactory.com',
            'launchpad-demo.dreamfactory.com',
            'next.cloud.dreamfactory.com',
        );

        $_host = static::$_hostname;

        return in_array( $_host, $_allowedHosts ) ? ( $returnHost ? $_host : true ) : false;
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
        if ( !$force && !static::fabricHosted() && !static::hostedPrivatePlatform() )
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
     * @param string    $host
     * @param \stdClass $instance
     * @param array     $dbConfig
     * @param string    $configFileName
     * @param string    $instanceFileName
     * @param array     $values
     *
     * @throws \Exception
     */
    protected static function _cacheSettings( $host, $instance, $dbConfig, $configFileName = null, $instanceFileName = null, array $values = array() )
    {
        $_values = $values ?: array('{instance_name}' => $instance->instance_name_text);

        JsonFile::encodeFile(
            $instanceFileName ?: static::$_storage->getPrivatePath() . static::_makeFileName( static::INSTANCE_CONFIG_FILE_NAME_PATTERN, $_values ),
            $instance
        );

        JsonFile::encodeFile(
            $configFileName ?: static::$_storage->getPrivatePath() . static::_makeFileName( static::DB_CONFIG_FILE_NAME_PATTERN, $_values ),
            $dbConfig
        );
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
     * @param string $host
     *
     * @return mixed|string
     * @throws \CHttpException
     */
    protected static function _getDatabaseConfig( $host )
    {
        $_config = null;
        $_dspName = str_ireplace( static::DSP_DEFAULT_SUBDOMAIN, null, $host );
        $_values = array('{instance_name}' => $_dspName);

        if ( false === ( $_config = static::_readDbConfig( $host ) ) )

        {
            $_dbConfigFileName = static::_makeFileName( static::DB_CONFIG_FILE_NAME_PATTERN, $_values );
        }
        $_dbConfigFile = static::$_storage->getLocalConfigPath() . $_dbConfigFileName;

        //	Try and get them from server...
        $_response = static::_getInstanceCredentials( $host, $_dspName );
        $_instance = $_cache = $_response->details;

        static::_writeDbConfig( $_instance );

        $_config = JsonFile::decodeFile( $_dbConfigFile );

        if ( !empty( $_config ) )
        {
            //	Save it for later (don't run away and let me down <== extra points if you get the reference)
            setcookie( static::PUBLIC_STORAGE_COOKIE, $_instance->storage_key, time() + DateTime::TheEnd, '/' );
            setcookie( static::PRIVATE_STORAGE_COOKIE, $_instance->private_key, time() + DateTime::TheEnd, '/' );

            static::_cacheSettings( $host, $_instance, $_config, $_dbConfigFile );
        }
        else
        {
            //  Clear cookies
            setcookie( static::PUBLIC_STORAGE_COOKIE, '', 0, '/' );
            setcookie( static::PRIVATE_STORAGE_COOKIE, '', 0, '/' );
        }

        //  Check for enterprise status
        static::_checkPlatformState( $_dspName );

        return $_config;
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
            static::$_storage->getLocalConfigPath() .
            static::_makeFileName( static::DB_CONFIG_FILE_NAME_PATTERN, array('{instance_name}' => $instanceName) );

        if ( !file_exists( $_fileName ) )
        {
            return false;
        }

        /** @noinspection PhpIncludeInspection */

        return include( $_fileName );
    }

    /**
     * @param \stdClass $instance
     *
     * @throws \Exception
     * @return array
     */
    protected static function _writeDbConfig( $instance )
    {
        $_fileName =
            static::$_storage->getLocalConfigPath() .
            static::_makeFileName( static::DB_CONFIG_FILE_NAME_PATTERN, array('{instance_name}' => $instance->instance_name_text) );

        if ( file_exists( $_fileName ) )
        {
            if ( false === @copy( $_fileName, $_fileName . '.save.' . date( 'YmdHis' ) ) )
            {
                static::_errorLog( 'Unable to make backup copy of database config file: ' . $_fileName );
            }
        }

        $_version = 'v' . Platform::getPlatformCoreVersion();
        $_date = date( 'c' );

        $_php = <<<PHP
<?php
/**
 * **** DO NOT MODIFY THIS FILE ****
 * **** CHANGES WILL BREAK YOUR DSP AND COULD BE OVERWRITTEN AT ANY TIME ****
 * @(#)\$Id: database.config.php; {$_version}-{$instance->instance_name_text} {$_date} \$
 */
return array(
    'connectionString'      => 'mysql:host={$instance->db_host};port={$instance->db_port};dbname={$instance->db_name}',
    'username'              => '{$instance->db_user}',
    'password'              => '{$instance->db_password}',
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
        $_instanceDetails = array(
            'dsp.credentials'              => $instance,
            'dsp.db_name'                  => $instance->db_name,
            'platform.dsp_name'            => $instance->instance_name_text,
            'platform.private_path'        => $instance->private_path,
            'platform.storage_key'         => $instance->storage_key,
            'platform.private_storage_key' => $instance->private_key,
            'platform.db_config_file'      => $_fileName,
            'platform.db_config_file_name' => basename( $_fileName ),
            PlatformStates::STATE_KEY      => null,
        );

        \Kisma::set( $_instanceDetails );

        return $_instanceDetails;
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

            throw new \CHttpException( Response::HTTP_NOT_FOUND, 'Instance not available.' );
        }

        if ( !$_response || !is_object( $_response ) || false == $_response->success )
        {
            static::_errorLog( 'Error connecting to authentication service: ' . print_r( $_response, true ) );

            throw new \CHttpException(
                HttpResponse::InternalServerError, 'Cannot connect to authentication service'
            );
        }

        return $_response;
    }

    /**
     * @return Request
     */
    public static function getRequest()
    {
        return static::$_request;
    }

    /**
     * @return HostedStorage
     */
    public static function getStorage()
    {
        return static::$_storage;
    }

}