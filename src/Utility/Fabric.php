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

use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Exceptions\FileSystemException;
use DreamFactory\Library\Utility\IfSet;
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

/**
 * Hosted DSP system utilities
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
    const METADATA_ENDPOINT = 'http://dfe-beta.fabric.dreamfactory.com/host/environment';
    /**
     * @var string
     */
    const DEFAULT_PROVIDER_ENDPOINT = 'http://oasys.cloud.dreamfactory.com/oauth/providerCredentials';
    /**
     * @var string
     */
    const DSP_DB_CONFIG_FILE_NAME_PATTERN = '%%INSTANCE_NAME%%.database.config.php';
    /**
     * @var string
     */
    const DSP_DEFAULT_SUBDOMAIN = '.cloud.dreamfactory.com';
    /**
     * @var string My favorite cookie
     */
    const FigNewton = 'dsp.blob';
    /**
     * @var string My favorite cookie
     */
    const PrivateFigNewton = 'dsp.private';
    /**
     * @var string
     */
    const BaseStorage = '/data/storage';
    /**
     * @var string
     */
    const FABRIC_MARKER = '/var/www/.fabric_hosted';
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
     * @var string
     */
    const DEFAULT_DOC_ROOT = '/var/www/launchpad/web';
    /**
     * @var string
     */
    const DEFAULT_DEV_DOC_ROOT = '/opt/dreamfactory/dsp/dsp-core/web';

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @return string
     */
    public static function getHostName()
    {
        return FilterInput::server( 'HTTP_HOST', gethostname() );
    }

    /**
     * @return bool True if this DSP is fabric-hosted
     */
    public static function fabricHosted()
    {
        static $_validRoots = [self::DEFAULT_DOC_ROOT, self::DEFAULT_DEV_DOC_ROOT];
        static $_fabricHosted = null;

        return
            $_fabricHosted
                ?: $_fabricHosted = in_array( FilterInput::server( 'DOCUMENT_ROOT' ), $_validRoots ) && file_exists( static::FABRIC_MARKER );
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
        static $_allowedHosts = null;
        static $_localHosts = [
            'launchpad-dev.dreamfactory.com',
            'launchpad-demo.dreamfactory.com',
            'next.cloud.dreamfactory.com',
        ];

        if ( empty( $_allowedHosts ) )
        {
            $_allowedHosts = array_merge( $_localHosts, Pii::getParam( 'dsp.hpp_hosts', array() ) );
        }

        $_host = static::getHostName();

        return in_array( $_host, $_allowedHosts ) ? ( $returnHost ? $_host : true ) : false;
    }

    /**
     * Initialization for hosted DSPs
     *
     * @return array
     * @throws \RuntimeException
     * @throws \CHttpException
     */
    public static function initialize()
    {
        static $_settings = null, $_metadata = array();

        if ( $_settings )
        {
            return array($_settings, $_metadata);
        }

        //	If this isn't a cloud request, bail
        $_host = static::getHostName();

        if ( !static::hostedPrivatePlatform() && false === strpos( $_host, static::DSP_DEFAULT_SUBDOMAIN ) )
        {
            static::_errorLog( 'Attempt to access system from non-provisioned host: ' . $_host );

            throw new \CHttpException(
                HttpResponse::Forbidden,
                'You are not authorized to access this system you cheeky devil you. (' . $_host . ').'
            );
        }

        list( $_settings, $_metadata ) = static::_getDatabaseConfig( $_host );

        if ( !empty( $_settings ) )
        {
            return array($_settings, $_metadata);
        }

        throw new \CHttpException( HttpResponse::BadRequest, 'Unable to find database configuration' );
    }

    /**
     * Writes the cache file out to disk
     *
     * @param string          $host
     * @param array           $settings
     * @param \stdClass       $instance
     * @param array|\stdClass $metadata
     *
     * @return mixed
     */
    protected static function _cacheSettings( $host, $settings, $instance, $metadata )
    {
        $_data = [
            'settings' => $settings,
            'instance' => $instance,
            'metadata' => $metadata,
        ];

        JsonFile::encodeFile( static::_cacheFileName( $host ), $_data );

        return $settings;
    }

    /**
     * Generates the file name for the configuration cache file
     *
     * @param string $host
     *
     * @return string
     */
    protected static function _cacheFileName( $host )
    {
        $_path = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) .
            DIRECTORY_SEPARATOR . '.dreamfactory' .
            DIRECTORY_SEPARATOR . '.fabric';

        if ( !is_dir( $_path ) && false === @mkdir( $_path, 0777, true ) )
        {
            throw new FileSystemException( 'Unable to create cache directory' );
        }

        return $_path . DIRECTORY_SEPARATOR . sha1( $host . $_SERVER['REMOTE_ADDR'] );
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

            return [];
        }

        //	Otherwise, get the credentials from the auth server...
        $_url = static::DEFAULT_PROVIDER_ENDPOINT . '/';

        if ( null !== $id )
        {
            $_url .= $id . '/';
        }

        $_response = Curl::get( $_url . '?oasys=' . urlencode( Pii::getParam( 'oauth.salt' ) ) );

        if ( !$_response || HttpResponse::Ok != Curl::getLastHttpCode() || !is_object( $_response ) || !$_response->success )
        {
            static::_errorLog(
                'Global provider credential pull failed: ' .
                Curl::getLastHttpCode() .
                PHP_EOL .
                print_r( $_response, true )
            );

            return [];
        }

        return Option::get( $_response, 'details', [] );
    }

    /**
     * @param \stdClass $instance
     * @param string    $privatePath
     *
     * @return mixed|string
     * @throws \CHttpException
     */
    protected static function _getMetadata( $instance, $privatePath )
    {
        static $_metadata = null;

        if ( !$_metadata )
        {
            $_filename = $privatePath . DIRECTORY_SEPARATOR . $instance->instance_id_text . '.json';

            if ( file_exists( $_filename ) )
            {
                $_metadata = JsonFile::decodeFile( $_filename, true );

                return $_metadata;
            }

            $_response = static::api( HttpMethod::GET, '/instance/metadata/' . $instance->instance_id_text );

            if ( !$_response || HttpResponse::Ok != Curl::getLastHttpCode() || !is_object( $_response ) || !$_response->success )
            {
                static::_errorLog( 'Metadata pull failure.' );

                return false;
            }

            $_metadata = (array)$_response->details;

            JsonFile::encodeFile( $_filename, $_metadata );
        }

        return $_metadata;
    }

    /**
     * @param string $message
     * @param array  $context
     */
    protected static function _errorLog( $message, $context = [] )
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
        $_dspName = str_ireplace( static::DSP_DEFAULT_SUBDOMAIN, null, $host );

        $_dbConfigFileName = str_ireplace(
            '%%INSTANCE_NAME%%',
            $_dspName,
            static::DSP_DB_CONFIG_FILE_NAME_PATTERN
        );

        //	Try and get them from server...
        if ( false === ( list( $_settings, $_instance, $_metadata ) = static::_checkCache( $host ) ) )
        {
            //	Get the credentials from the auth server...
            $_response = static::api( HttpMethod::GET, '/instance/credentials/' . $_dspName . '/database' );

            if ( HttpResponse::NotFound == Curl::getLastHttpCode() )
            {
                static::_errorLog( 'DB Credential pull failure. Redirecting to df.com: ' . $host );
                header( 'Location: https://www.dreamfactory.com/dsp-not-found?dn=' . urlencode( $_dspName ) );
                exit( 1 );
            }

            if ( is_object( $_response ) &&
                isset( $_response->details, $_response->details->code ) &&
                HttpResponse::NotFound == $_response->details->code
            )
            {
                static::_errorLog( 'Instance "' . $_dspName . '" not found during web initialize.' );
                throw new \CHttpException( HttpResponse::NotFound, 'Instance not available.' );
            }

            if ( !$_response || !is_object( $_response ) || false == $_response->success )
            {
                static::_errorLog( 'Error connecting to authentication service: ' . print_r( $_response, true ) );
                throw new \CHttpException(
                    HttpResponse::InternalServerError, 'Cannot connect to authentication service'
                );
            }

            $_instance = $_cache = $_response->details;
            $_dbName = $_instance->db_name;
            $_dspName = $_instance->instance->instance_name_text;

            $_privatePath = $_cache->private_path;
            $_privateKey = basename( dirname( $_privatePath ) );
            $_dbConfigFile = $_privatePath . '/' . $_dbConfigFileName;

            //	Stick this in persistent storage
            $_systemOptions = [
                'dsp.credentials'              => $_cache,
                'dsp.db_name'                  => $_dbName,
                'platform.dsp_name'            => $_dspName,
                'platform.private_path'        => $_privatePath,
                'platform.storage_key'         => $_instance->storage_key,
                'platform.private_storage_key' => $_privateKey,
                'platform.db_config_file'      => $_dbConfigFile,
                'platform.db_config_file_name' => $_dbConfigFileName,
                PlatformStates::STATE_KEY      => null,
            ];

            \Kisma::set( $_systemOptions );

            //	File should be there from provisioning... If not, tenemos una problema!
            if ( !file_exists( $_dbConfigFile ) )
            {
                $_file = basename( $_dbConfigFile );
                $_version = ( defined( 'DSP_VERSION' ) ? 'v' . DSP_VERSION : 'fabric' );
                $_timestamp = date( 'c' );

                $_dbConfig = <<<PHP
<?php
/**
 * **** DO NOT MODIFY THIS FILE ****
 * **** CHANGES WILL BREAK YOUR DSP AND COULD BE OVERWRITTEN AT ANY TIME ****
 * @(#)\$Id: {$_file}, {$_version}-{$_dspName} {$_timestamp} \$
 */
return array(
    'connectionString'      => 'mysql:host={$_instance->db_host};port={$_instance->db_port};dbname={$_dbName}',
    'username'              => '{$_instance->db_user}',
    'password'              => '{$_instance->db_password}',
    'emulatePrepare'        => true,
    'charset'               => 'utf8',
    'schemaCachingDuration' => 3600,
);
PHP;

                if ( !is_dir( dirname( $_dbConfigFile ) ) )
                {
                    @mkdir( dirname( $_dbConfigFile ), 0777, true );
                }

                //Log::debug( 'Writing config "' . $_dbConfigFile . '": ' . json_encode( $_instance, JSON_PRETTY_PRINT ) . PHP_EOL . $_dbConfig );

                if ( false === file_put_contents( $_dbConfigFile, $_dbConfig ) )
                {
                    static::_errorLog( 'Cannot create database config file.' );
                }

                //  Try and read again
                if ( !file_exists( $_dbConfigFile ) )
                {
                    static::_errorLog( 'DB Credential READ failure. Redirecting to df.com: ' . $host );
                    header( 'Location: https://www.dreamfactory.com/dsp-not-found?dn=' . urlencode( $_dspName ) );
                    exit( 1 );
                }
            }

            /** @noinspection PhpIncludeInspection */
            $_settings = require( $_dbConfigFile );

            //Log::debug( 'Reading config: ' . $_settings );

            if ( !empty( $_settings ) )
            {
                //	Save it for later (don't run away and let me down <== extra points if you get the reference)
                setcookie( static::FigNewton, $_instance->storage_key, time() + DateTime::TheEnd, '/' );
                setcookie( static::PrivateFigNewton, $_privateKey, time() + DateTime::TheEnd, '/' );
            }
            else
            {
                //  Clear cookies
                setcookie( static::FigNewton, '', 0, '/' );
                setcookie( static::PrivateFigNewton, '', 0, '/' );
            }

            $_metadata = (array)static::_getMetadata( $_instance->instance, $_privatePath );

            static::_cacheSettings( $host, $_settings, $_instance, $_metadata );
        }

        //  Check for enterprise status
        static::_checkPlatformState( $_dspName );

        return array($_settings, $_metadata);
    }

    /**
     * @param string $host
     *
     * @return bool|mixed
     */
    protected static function _checkCache( $host )
    {
        $_cacheFile = static::_cacheFileName( $host );

        //	See if file is available and return it, or expire it...
        if ( file_exists( $_cacheFile ) )
        {
            //	No session or expired?
            if ( Pii::isEmpty( session_id() ) || ( time() - fileatime( $_cacheFile ) ) > static::EXPIRATION_THRESHOLD )
            {
                @unlink( $_cacheFile );

                return false;
            }

            try
            {
                $_data = JsonFile::decodeFile( $_cacheFile );
            }
            catch ( \InvalidArgumentException $_ex )
            {
                //  File can't be read
                return false;
            }

            return [
                IfSet::get( $_data, 'settings', array() ),
                IfSet::get( $_data, 'instance', array() ),
                IfSet::get( $_data, 'metadata', array() ),
            ];
        }

        return false;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array  $payload
     * @param array  $curlOptions
     *
     * @return bool|\stdClass|array
     */
    public static function api( $method, $uri, $payload = [], $curlOptions = [] )
    {
        if ( !HttpMethod::contains( strtoupper( $method ) ) )
        {
            throw new \InvalidArgumentException( 'The method "' . $method . '" is invalid.' );
        }

        try
        {
            //  Allow full URIs
            if ( 'http' != substr( $uri, 0, 4 ) )
            {
                $uri = static::FABRIC_API_ENDPOINT . '/' . ltrim( $uri, '/ ' );
            }

            if ( false === ( $_result = Curl::request( $method, $uri, $payload, $curlOptions ) ) )
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
}

//********************************************************************************
//* Check for maintenance mode...
//********************************************************************************

if ( is_file( Fabric::MAINTENANCE_MARKER ) && Fabric::MAINTENANCE_URI != Option::server( 'REQUEST_URI' ) )
{
    header( 'Location: ' . Fabric::MAINTENANCE_URI );
    die();
}
