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

/**
 * Methods for interfacing with DreamFactory Enterprise( DFE )
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
    const DFE_MARKER = '/var/www/.dfe-hosted';
    /**
     * @type string
     */
    const CLUSTER_ENV_FILE = '.env.cluster';

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
        if ( false === ( $_config = static::getConfig() ) || false === ( $_config = static::_checkClusterEnvironment( $_config ) ) )
        {
            Log::debug( 'Not a DFE hosted instance. Resistance is NOT futile.' );

            return false;
        }

        if ( false === ( $_cluster = static::_interrogateCluster( $_config ) ) )
        {
            static::_errorLog( 'Cluster interrogation failed. Suggest water-boarding.' );

            return array($_settings, $_metadata);
        }

        static::$_dfeInstance = true;
        static::$_config = $_config;

        //  it sucks, but yeah...
        Log::debug( 'Not a DFE hosted instance. Resistance is NOT futile.' );

        return false;
    }

    /**
     * @param array $config
     *
     * @return array
     */
    protected static function _checkClusterEnvironment( array $config )
    {
        try
        {
            //	If this isn't an enterprise instance, bail
            $config['host-name'] = $_host = static::getHostName();

            //  This request is not for us, log and bail...
            if ( false === strpos( $_host, $_defaultDomain = IfSet::get( $config, 'default-domain', static::DEFAULT_DOMAIN ) ) )
            {
                static::_errorLog( 'Request to non-provisioned instance: ' . $_host );

                //@todo handle differently? Redirect somewhere?
                return false;
            }

            //  Check for a cluster environment file
            return array_merge(
                $config,
                array(
                    'cluster'        => JsonFile::decodeFile( Pii::basePath() . DIRECTORY_SEPARATOR . static::CLUSTER_ENV_FILE ),
                    'default-domain' => $_defaultDomain,
                    'instance-name'  => str_replace( $_defaultDomain, null, $_host )
                )
            );
        }
        catch ( \InvalidArgumentException $_ex )
        {
            //  The file is bogus or not there
            return false;
        }
    }

    /**
     * @param array $config
     */
    protected static function _interrogateCluster( array $config )
    {
        $_json = <<<JSON
{
	"cluster-id":       "cluster-east-1",
	"signature-method": "sha256",
	"endpoint":         "https://console.enterprise.dreamfactory.com/api/v1",
	"port":             443,
	"client-key":       "%]3,]~&t,EOxL30[wKw3auju:[+L>eYEVWEP,@3n79Qy",
	"client-id":        "",
	"client-secret":    ""
}

JSON;

        //  Get cluster config from env
        $_id = $config['instance-name'];

        $_status = static::_api( 'status', array('id' => $_id) );

        //  Get my config from console
        //  Set my storage up according
    }

    /**
     * Writes the cache file out to disk
     *
     * @param string $key
     * @param array  $data
     *
     * @return array
     */
    protected static function _cache( $key, $data )
    {
        JsonFile::encodeFile( static::_cacheFileName( $key ), $data );

        return $data;
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
            DIRECTORY_SEPARATOR . 'dfe' .
            DIRECTORY_SEPARATOR . 'cache';

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
     * @param string $key
     * @param mixed  $default
     *
     * @return array|mixed
     */
    public static function getConfig( $key = null, $default = null )
    {
        if ( false === static::$_config )
        {
            static::$_config = Pii::getParam( 'dfe' );

            //  Nada
            if ( empty( $_config ) )
            {
                static::$_config = array();
                static::$_dfeInstance = false;

                return $default;
            }
        }

        if ( !$key )
        {
            return static::$_config;
        }

        return IfSet::get( static::$_config, $key, $default, true );
    }

    /**
     * @param array $payload
     *
     * @return array
     */
    private static function _signPayload( $userId, array $payload )
    {
        return array_merge(
            array(
                'user-id'      => $userId,
                'client-id'    => '$this->_clientId',
                'access-token' => '$this->_signature',
            ),
            $payload ?: []
        );

    }

    /**
     * @return string
     */
    public static function getHostName()
    {
        return Pii::request( false )->getHttpHost();
    }

    /**
     * @return bool True if this DSP is fabric-hosted
     */
    public static function isHostedInstance()
    {
        static $_hosted = null;

        return
            null !== $_hosted ? $_hosted : $_hosted = file_exists( static::DFE_MARKER );
    }

}

Enterprise::initialize();

//********************************************************************************
//* Check for maintenance mode...
//********************************************************************************

if ( is_file( Fabric::MAINTENANCE_MARKER ) && Fabric::MAINTENANCE_URI != Option::server( 'REQUEST_URI' ) )
{
    header( 'Location: ' . Fabric::MAINTENANCE_URI );
    die();
}
