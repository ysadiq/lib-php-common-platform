<?php namespace DreamFactory\Platform\Utility;

use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\Exceptions\FileSystemException;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Library\Utility\JsonFile;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Symfony\Component\HttpFoundation\Request;

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
class Enterprise
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
    protected static $_config = false;
    /**
     * @type bool
     */
    protected static $_dfeInstance = false;
    /**
     * @type string Our API access token
     */
    protected static $_token = null;
    /**
     * @type string The instance name
     */
    protected static $_instanceName = null;

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
        if ( static::$_dfeInstance )
        {
            return static::$_config;
        }

        $_settings = $_metadata = array();

        //  Discover where I am
        if ( false === ( $_config = static::_getClusterConfig() ) )
        {
            Log::debug( 'Not a DFE hosted instance. Resistance is NOT futile.' );

            return false;
        }

        if ( false === ( $_cluster = static::_interrogateCluster() ) )
        {
            Log::error( 'Cluster interrogation failed. Suggest water-boarding.' );

            return array($_settings, $_metadata);
        }

        static::$_dfeInstance = true;
        static::$_config = $_config;
        static::$_token = static::_generateSignature();

        //  it sucks, but yeah...
        Log::debug( 'Not a DFE hosted instance. Resistance is NOT futile.' );

        return false;
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
            $_host = Pii::request( false )->getHttpHost();

            //  Ensure keys exist...
            if ( !isset( static::$_config['client-id'], static::$_config['client-secret'] ) )
            {
                Log::error( 'No client-id/secret in cluster manifest.' );

                return false;
            }

            //  And API url
            if ( !isset( static::$_config['api-url'] ) )
            {
                Log::error( 'No "api-url" in cluster manifest.' );

                return false;
            }

            //  And default domain
            $_defaultDomain = IfSet::get( static::$_config, 'default-domain' );

            if ( empty( $_defaultDomain ) || false === strpos( $_host, $_defaultDomain ) )
            {
                Log::error( 'Invalid "default-domain" for host "' . $_host . '"' );

                return false;
            }

            static::$_config['default-domain'] = $_defaultDomain = '.' . ltrim( $_defaultDomain, '. ' );
            static::$_instanceName = str_replace( $_defaultDomain, null, $_host );

            //  It's all good!
            return static::$_dfeInstance = true;
        }
        catch ( \InvalidArgumentException $_ex )
        {
            //  The file is bogus or not there
            return false;
        }
    }

    /**
     * @param array $config
     *
     * @return bool
     */
    protected static function _interrogateCluster( array $config )
    {
        //  Get cluster config from env
        $_status = static::_api( 'status', array('id' => static::$_instanceName) );

        if ( false === $_status )
        {
            Log::error( 'Unable to contact DFE console.' );
        }

        //  Get my config from console
        //  Set my storage up according

        return true;
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
    protected static function _api( $method, $uri, $payload = [], $curlOptions = [] )
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
                $uri = rtrim( static::$_config['api-url'], '/' ) . '/' . ltrim( $uri, '/ ' );
            }

            if ( false === ( $_result = Curl::request( $method, $uri, static::_signPayload( $payload ), $curlOptions ) ) )
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
     * @param string $key
     * @param mixed  $default
     * @param bool   $emptyStringIsNull
     *
     * @return array|mixed
     */
    protected static function _getClusterConfig( $key = null, $default = null, $emptyStringIsNull = true )
    {
        if ( false === static::$_config )
        {
            $_configFile = Pii::basePath() . DIRECTORY_SEPARATOR . static::CLUSTER_ENV_FILE;

            if ( !file_exists( $_configFile ) )
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
                JsonFile::encodeFile( $_configFile, static::$_config );
            }
            catch ( \Exception $_ex )
            {
                Log::error( 'Cluster configuration file could not be decoded.' );

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
    protected function _signPayload( array $payload )
    {
        return array_merge(
            array(
                'client-id'    => static::$_config['client-id'],
                'access-token' => static::$_token,
            ),
            $payload ?: []
        );

    }

    /**
     * @return string
     */
    protected function _generateSignature()
    {
        return
            hash_hmac(
                static::$_config['signature-method'],
                static::$_config['client-id'],
                static::$_config['client-secret']
            );
    }
}

//******************************************************************************
//* Initialize the DFE integration
//******************************************************************************

Enterprise::initialize();