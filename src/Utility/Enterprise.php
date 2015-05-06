<?php namespace DreamFactory\Platform\Utility;

use DreamFactory\Common\Exceptions\ProvisioningException;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Library\Utility\JsonFile;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Log;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Methods for interfacing with DreamFactory Enterprise( DFE )
 *
 * This class discovers if this instance is a DFE cluster participant. When the DFE console provisions an instance, it places a file called
 * ".env.cluster.json" into the root directory of the installation. This file contains the necessary information to operate and/or communicate with
 * its cluster console.
 *
 * Config File Format
 * ===============
 * The file is in JSON format and looks similar to this:
 *
 *  {
 *      "cluster-id":       "my-cluster-id",
 *      "default-domain":   ".pasture.farm.com"
 *      "signature-method": "sha256",
 *      "api-url":          "https://console.pasture.farm.com/api/v1",
 *      "api-key":          "@N(cUrwqU)!GNUMiB518,zHMDaq~76l,",
 *      "client-id":        "cb171a80999a8db5c72956006812bbb307af8e54185ab1f40308189dc4d3f601",
 *      "client-secret":    "b4f53df1d3ce84b9e8a0b55455c36ccfac8e0ed2dd9bf9941178559dd9d69c4a"
 *  }
 *
 */
final class Enterprise
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string
     */
    const DEFAULT_DOMAIN = '.pasture.farm.com';
    /**
     * @type string
     */
    const CLUSTER_ENV_FILE = '.env.cluster.json';

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type array
     */
    private static $_config = false;
    /**
     * @type string
     */
    private static $_cacheKey = null;
    /**
     * @type string Our API access token
     */
    private static $_token = null;
    /**
     * @type bool
     */
    protected static $_dfeInstance = false;
    /**
     * @type array The storage paths
     */
    protected static $_paths = array();
    /**
     * @type string The instance name
     */
    protected static $_instanceName = null;
    /**
     * @type bool Set to TRUE to allow updating of cluster environment file.
     */
    protected static $_writableConfig = false;
    /**
     * @type string The root storage directory
     */
    protected static $_storageRoot;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Initialization for hosted DSPs
     *
     * @return array
     * @throws \RuntimeException
     * @throws \CHttpException
     */
    public static function initialize()
    {
        static::$_cacheKey = 'dfe.config.' . static::_getHostName();

        if ( !static::_reloadCache() )
        {
            //  Discover where I am
            if ( false === ( $_config = static::_getClusterConfig() ) )
            {
                Log::debug( 'Not a DFE hosted instance. Resistance is NOT futile.' );

                return false;
            }
        }

        //  It's all good!
        static::$_dfeInstance = true;

        //  Generate a signature for signing payloads...
        static::$_token = static::_generateSignature();

        if ( !static::_interrogateCluster() )
        {
            Log::error( 'Cluster interrogation failed. Suggest water-boarding.' );

            return false;
        }

        return true;
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Common\Exceptions\ProvisioningException
     */
    protected static function _interrogateCluster()
    {
        //  Get my config from console
        $_status = static::_api( 'status', array('id' => static::getInstanceName()) );

        if ( false === $_status || false === $_status->success )
        {
            Log::error( 'Instance not found or unavailable.' );

            return false;
        }

        if ( !( $_status instanceof \stdClass ) || !isset( $_status->response, $_status->response->metadata, $_status->response->metadata->env ) )
        {
            Log::info( 'Unable to contact DFE console.' );

            return false;
        }

        $_map = (array)$_status->response->metadata->{'storage-map'};
        $_paths = (array)$_status->response->metadata->paths;
        $_root = rtrim( static::$_config['storage-root'] . static::_locateInstanceRootStorage( $_map, $_paths ), ' ' . DIRECTORY_SEPARATOR );

        if ( !is_dir( $_root ) || empty( $_paths ) )
        {
            throw new ProvisioningException( 'This storage configuration for this instance is not valid.' );
        }

        static::$_paths['storage-path'] = $_root . DIRECTORY_SEPARATOR . static::$_instanceName;
        static::$_paths['private-path'] = $_root . DIRECTORY_SEPARATOR . static::$_instanceName . DIRECTORY_SEPARATOR . $_paths['private-path'];
        static::$_paths['owner-private-path'] = $_root . DIRECTORY_SEPARATOR . $_paths['owner-private-path'];
        static::$_storageRoot = $_root;

        static::_refreshCache();

        return true;
    }

    /**
     * @return array
     */
    protected static function _validateClusterEnvironment()
    {
        try
        {
            //  Start out false
            static::$_dfeInstance = false;

            //	If this isn't an enterprise instance, bail
            $_host = static::_getHostName();

            //  And API url
            if ( !isset( static::$_config['console-api-url'], static::$_config['console-api-key'] ) )
            {
                Log::error( 'Invalid configuration: No "console-api-url" or "console-api-key" in cluster manifest.' );

                return false;
            }

            //  Make it ready for action...
            static::$_config['console-api-url'] = rtrim( static::$_config['console-api-url'], '/' ) . '/';

            //  And default domain
            $_defaultDomain = IfSet::get( static::$_config, 'default-domain' );

            if ( empty( $_defaultDomain ) || false === strpos( $_host, $_defaultDomain ) )
            {
                Log::error( 'Invalid "default-domain" for host "' . $_host . '"' );

                return false;
            }

            $_storageRoot = IfSet::get( static::$_config, 'storage-root' );

            if ( empty( $_storageRoot ) )
            {
                Log::error( 'No "storage-root" found.' );

                return false;
            }

            static::$_config['storage-root'] = rtrim( $_storageRoot, ' ' . DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
            static::$_config['default-domain'] = $_defaultDomain = '.' . ltrim( $_defaultDomain, '. ' );
            static::$_instanceName = str_replace( $_defaultDomain, null, $_host );

            //  It's all good!
            return true;
        }
        catch ( \InvalidArgumentException $_ex )
        {
            //  The file is bogus or not there
            return false;
        }
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

            $_response = static::_api( HttpMethod::GET, 'instance/metadata/' . $instance->instance_id_text );

            if ( !$_response || HttpResponse::Ok != Curl::getLastHttpCode() || !is_object( $_response ) || !$_response->success )
            {
                Log::error( 'Metadata pull failure.' );

                return false;
            }

            $_metadata = (array)$_response->details;

            JsonFile::encodeFile( $_filename, $_metadata );
        }

        return $_metadata;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array  $payload
     * @param array  $curlOptions
     *
     * @return bool|\stdClass|array
     */
    protected static function _api( $uri, $payload = array(), $curlOptions = array(), $method = Request::METHOD_POST )
    {
        if ( !HttpMethod::contains( strtoupper( $method ) ) )
        {
            throw new \InvalidArgumentException( 'The method "' . $method . '" is invalid.' );
        }

        try
        {
            //  Allow full URIs or manufacture one...
            if ( 'http' != substr( $uri, 0, 4 ) )
            {
                $uri = static::$_config['console-api-url'] . ltrim( $uri, '/ ' );
            }

            if ( false === ( $_result = Curl::request( $method, $uri, static::_signPayload( $payload ), $curlOptions ) ) )
            {
                throw new \RuntimeException( 'Failed to contact API server.' );
            }

            if ( !( $_result instanceof \stdClass ) )
            {
                if ( is_string( $_result ) && ( false === json_decode( $_result ) || JSON_ERROR_NONE !== json_last_error() ) )
                {
                    throw new \RuntimeException( 'Invalid response received from DFE console.' );
                }
            }

            return $_result;
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'api error: ' . $_ex->getMessage() );

            return false;
        }
    }

    /**
     * @param string $key
     * @param mixed  $default
     * @param bool   $emptyStringIsNull
     *
     * @return array|mixed
     */
    private static function _getClusterConfig( $key = null, $default = null, $emptyStringIsNull = true )
    {
        if ( false === static::$_config )
        {
            $_configFile = static::_locateClusterEnvironmentFile( static::CLUSTER_ENV_FILE );

            if ( !$_configFile || !file_exists( $_configFile ) )
            {
                return false;
            }

            try
            {
                static::$_config = JsonFile::decodeFile( $_configFile );

                if ( !static::_validateClusterEnvironment() )
                {
                    return false;
                }

                //  Re-write the cluster config
                static::isWritableConfig() && JsonFile::encodeFile( $_configFile, static::$_config );
            }
            catch ( \Exception $_ex )
            {
                Log::error( 'Cluster configuration file is not in a recognizable format.' );
                static::$_config = false;

                throw new \RuntimeException( 'This instance is not configured properly for your system environment.' );
            }
        }

        return null === $key ? static::$_config : IfSet::get( static::$_config, $key, $default, $emptyStringIsNull );
    }

    /**
     * @param array $payload
     *
     * @return array
     */
    private function _signPayload( array $payload )
    {
        return array_merge(
            array(
                'client-id'    => static::$_config['client-id'],
                'access-token' => static::$_token,
            ),
            $payload ?: array()
        );

    }

    /**
     * @return string
     */
    private function _generateSignature()
    {
        return
            hash_hmac(
                static::$_config['signature-method'],
                static::$_config['client-id'],
                static::$_config['client-secret']
            );
    }

    /**
     * @return boolean
     */
    public static function isManagedInstance()
    {
        return static::$_dfeInstance;
    }

    /**
     * @return string
     */
    public static function getInstanceName()
    {
        return static::$_instanceName;
    }

    /**
     * @return boolean
     */
    public static function isWritableConfig()
    {
        return static::$_writableConfig;
    }

    /**
     * @return string
     */
    public static function getStoragePath()
    {
        return static::$_paths['storage-path'];
    }

    /**
     * @return string
     */
    public static function getPrivatePath()
    {
        return static::$_paths['private-path'];
    }

    /**
     * @return string
     */
    public static function getLogPath()
    {
        return static::getPrivatePath() . DIRECTORY_SEPARATOR . 'log';
    }

    /**
     * @return string
     */
    public static function getOwnerPrivatePath()
    {
        return static::$_paths['owner-private-path'];
    }

    /**
     * Refreshes the cache with fresh values
     */
    protected static function _refreshCache()
    {
        Platform::storeSet( static::$_cacheKey, $_cache = array('paths' => static::$_paths, 'config' => static::$_config) );

        Log::debug( 'dfe: cache written ' . print_r( $_cache, true ) );
    }

    /**
     * Reload the cache
     */
    protected static function _reloadCache()
    {
        $_cache = Platform::storeGet( static::$_cacheKey );

        if ( !empty( $_cache ) && isset( $_cache['paths'], $_cache['config'] ) )
        {
            static::$_paths = $_cache['paths'];
            static::$_config = $_cache['config'];

            return true;
        }

        return false;
    }

    /**
     * Locate the configuration file for DFE, if any
     *
     * @param string $file
     *
     * @return bool|string
     */
    protected static function _locateClusterEnvironmentFile( $file )
    {
        $_path = getcwd();

        while ( true )
        {
            if ( file_exists( $_path . DIRECTORY_SEPARATOR . $file ) )
            {
                return $_path . DIRECTORY_SEPARATOR . $file;
            }

            $_parentPath = dirname( $_path );

            if ( $_parentPath == $_path || empty( $_parentPath ) || $_parentPath == DIRECTORY_SEPARATOR )
            {
                return false;
            }

            $_path = $_parentPath;
        }

        return false;
    }

    /**
     * Gets my host name
     *
     * @return string
     */
    protected static function _getHostName()
    {
        static $_hostname = null;

        return
            $_hostname
                ?:
                ( $_hostname = isset( $_SERVER )
                    ? IfSet::get( $_SERVER, 'HTTP_HOST', gethostname() )
                    : gethostname()
                );
    }

    /**
     * @param array $map
     * @param array $paths
     *
     * @return string
     */
    protected static function _locateInstanceRootStorage( array $map, array $paths )
    {
        $_zone = trim( IfSet::get( $map, 'zone' ), DIRECTORY_SEPARATOR );
        $_partition = trim( IfSet::get( $map, 'partition' ), DIRECTORY_SEPARATOR );
        $_rootHash = trim( IfSet::get( $map, 'root-hash' ), DIRECTORY_SEPARATOR );

        if ( empty( $_zone ) || empty( $_partition ) || empty( $_rootHash ) )
        {
            return dirname( Pii::basePath() ) . DIRECTORY_SEPARATOR . 'storage';
        }

        return implode( DIRECTORY_SEPARATOR, [$_zone, $_partition, $_rootHash] );
    }
}

//******************************************************************************
//* Initialize the DFE integration
//******************************************************************************

Enterprise::initialize();