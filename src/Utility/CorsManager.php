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
namespace DreamFactory\Platform\Utility;

use Composer\Autoload\ClassLoader;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CorsManager
 * Helper functions for CORS management
 */
class CorsManager
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string The HTTP Option method
     */
    const CORS_OPTION_METHOD = 'OPTIONS';
    /**
     * @var string The allowed HTTP methods
     */
    const CORS_DEFAULT_ALLOWED_METHODS = 'GET, POST, PUT, DELETE, PATCH, MERGE, COPY, OPTIONS';
    /**
     * @var string The allowed HTTP headers
     */
    const CORS_DEFAULT_ALLOWED_HEADERS = 'Content-Type, X-Requested-With, X-DreamFactory-Application-Name, X-Application-Name, X-DreamFactory-Session-Token';
    /**
     * @var int The default number of seconds to allow this to be cached. Default is 15 minutes.
     */
    const CORS_DEFAULT_MAX_AGE = 900;
    /**
     * @var string The session key for CORS configs
     */
    const CORS_WHITELIST_KEY = 'cors.config';
    /**
     * @var string The private CORS configuration file
     */
    const CORS_DEFAULT_CONFIG_FILE = '/cors.config.json';
    /**
     * @var string The default DSP resource namespace
     */
    const DEFAULT_SERVICE_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Services';
    /**
     * @var string The default DSP resource namespace
     */
    const DEFAULT_RESOURCE_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Resources';
    /**
     * @var string The default DSP model namespace
     */
    const DEFAULT_MODEL_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Yii\\Models';
    /**
     * @var string The default path (sub-path) of installed plug-ins
     */
    const DEFAULT_PLUGINS_PATH = '/storage/plugins';
    /**
     * @const int The services namespace map index
     */
    const NS_SERVICES = 0;
    /**
     * @const int The resources namespace map index
     */
    const NS_RESOURCES = 1;
    /**
     * @const int The models namespace map index
     */
    const NS_MODELS = 2;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array An indexed array of white-listed hosts (ajax.example.com or foo.bar.com or just bar.com)
     */
    protected static $_corsWhitelist = array();
    /**
     * @var bool    If true, the CORS headers will be sent automatically before dispatching the action.
     *              NOTE: "OPTIONS" calls will always get headers, regardless of the setting. All other requests respect the setting.
     */
    protected static $_autoAddHeaders = true;
    /**
     * @var bool If true, adds some extended information about the request in the form of X-DreamFactory-* headers
     */
    protected static $_extendedHeaders = true;
    /**
     * @var  Response
     */
    protected static $_responseObject;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Initialize
     *
     * @param Response $response
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \InvalidArgumentException
     * @throws \Kisma\Core\Exceptions\FileSystemException
     * @throws RestException
     */
    public static function initialize( $response )
    {
        static $_whitelist = null;

        if ( !static::_corsNeeded() )
        {
            return;
        }

        if ( null === $_whitelist && null === ( $_whitelist = \Kisma::get( 'cors.whitelist' ) ) )
        {
            //	Get CORS data from config file
            $_config = Platform::getStorageBasePath( static::CORS_DEFAULT_CONFIG_FILE, true, true );

            if ( !file_exists( $_config ) )
            {
                //  In old location?
                $_config = Platform::getPrivatePath( static::CORS_DEFAULT_CONFIG_FILE, true, true );
            }

            $_whitelist = array();

            if ( file_exists( $_config ) )
            {
                if ( false !== ( $_content = @file_get_contents( $_config ) ) && !empty( $_content ) )
                {
                    $_whitelist = json_decode( $_content, true );

                    if ( JSON_ERROR_NONE != json_last_error() )
                    {
                        throw new InternalServerErrorException( 'The CORS configuration file is corrupt. Cannot continue.' );
                    }
                }
            }

            \Kisma::set( 'cors.whitelist', $_whitelist );
        }

        static::$_responseObject = $response;

        static::setCorsWhitelist( $_whitelist );
    }

    /**
     * Will automatically add CORS headers to response or live output (depending on construction)
     *
     * @param array $whitelist
     * @param bool  $returnHeaders
     * @param bool  $sendHeaders
     *
     * @throws RestException
     */
    public static function autoSendHeaders( $whitelist = array(), $returnHeaders = false, $sendHeaders = true )
    {
        if ( static::_corsNeeded() && static::$_autoAddHeaders )
        {
            static::sendHeaders( $whitelist, $returnHeaders, $sendHeaders );
        }
    }

    /**
     * @param array|bool $whitelist     Set to "false" to reset the internal method cache.
     * @param bool       $returnHeaders If true, the headers are return in an array and NOT sent
     * @param bool       $sendHeaders   If false, the headers will NOT be sent. Defaults to true. $returnHeaders takes precedence
     *
     * @throws RestException
     * @throws \InvalidArgumentException
     * @return bool|array
     */
    public static function sendHeaders( $whitelist = array(), $returnHeaders = false, $sendHeaders = true )
    {
        if ( !static::_corsNeeded() )
        {
            return;
        }

        $_headers = static::_buildCorsHeaders( $whitelist );

        if ( $returnHeaders )
        {
            return $_headers;
        }

        //	Dump the headers
        if ( $sendHeaders )
        {
            if ( empty( static::$_responseObject ) )
            {
                foreach ( $_headers as $_key => $_value )
                {
                    header( $_key . ': ' . $_value, true );
                }
            }
            else
            {

            }
        }

        return true;
    }

    /**
     * Builds a cache key for a request and returns the constituent parts
     *
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @return array
     */
    protected static function _buildCacheKey()
    {
        $_origin = trim( Option::server( 'HTTP_ORIGIN' ) );
        $_requestSource = Option::server( 'SERVER_NAME', \Kisma::get( 'platform.host_name', gethostname() ) );

        if ( false === ( $_originParts = static::_parseUri( $_origin ) ) )
        {
            //	Not parse-able, set to itself, check later (testing from local files - no session)?
            Log::warning( 'Unable to parse received origin: [' . $_origin . ']' );
            $_originParts = $_origin;
        }

        $_originUri = static::_normalizeUri( $_originParts );
        $_key = sha1( $_requestSource . $_originUri );

        return array(
            $_key,
            $_origin,
            $_requestSource,
            $_originParts,
            $_originUri,
        );
    }

    /**
     * @param array|bool $whitelist Set to "false" to reset the internal method cache.
     *
     * @throws \InvalidArgumentException
     * @throws RestException
     * @return array
     */
    protected static function _buildCorsHeaders( $whitelist = array() )
    {
        static $_cache = array();
        static $_cacheVerbs = array();

        //	Reset the cache before processing...
        if ( false === $whitelist )
        {
            $_cache = array();
            $_cacheVerbs = array();

            return true;
        }

        $_originUri = null;
        $_headers = array();

        //  Deal with CORS headers
        list(
            $_key,
            $_origin,
            $_requestSource,
            $_originParts,
            $_originUri,
            ) = static::_buildCacheKey();

        //	Was an origin header passed? If not, don't do CORS.
        if ( !empty( $_origin ) )
        {
            //	Not in cache, check it out...
            if ( !in_array( $_key, $_cache ) )
            {
                if ( false === ( $_allowedMethods = static::_allowedOrigin( $_originParts, $_requestSource ) ) )
                {
                    Log::error( 'Unauthorized origin rejected via CORS > Source: ' . $_requestSource . ' > Origin: ' . $_originUri );

                    /**
                     * No sir, I didn't like it.
                     *
                     * @link http://www.youtube.com/watch?v=VRaoHi_xcWk
                     */
                    throw new RestException( HttpResponse::Forbidden );
                }
            }
            else
            {
                $_originUri = $_cache[$_key];
                $_allowedMethods = Option::getDeep( $_cacheVerbs, $_key, 'allowed_methods' );
                $_headers = Option::getDeep( $_cacheVerbs, $_key, 'headers' );
            }

            if ( !empty( $_originUri ) )
            {
                $_headers['Access-Control-Allow-Origin'] = $_originUri;
            }

            $_headers['Access-Control-Allow-Credentials'] = 'true';
            $_headers['Access-Control-Allow-Headers'] = static::CORS_DEFAULT_ALLOWED_HEADERS;
            $_headers['Access-Control-Allow-Methods'] = $_allowedMethods;
            $_headers['Access-Control-Max-Age'] = static::CORS_DEFAULT_MAX_AGE;

            //	Store in cache...
            $_cache[$_key] = $_originUri;
            $_cacheVerbs[$_key] = array(
                'allowed_methods' => $_allowedMethods,
                'headers'         => $_headers
            );
        }

        return $_headers + static::_buildExtendedHeaders( $_requestSource, $_originUri, !empty( $_origin ) );
    }

    /**
     * @param string $requestSource
     * @param string $originUri
     * @param bool   $whitelisted If the origin is whitelisted
     *
     * @return array
     */
    protected static function _buildExtendedHeaders( $requestSource, $originUri, $whitelisted = true )
    {
        $_headers = array();

        if ( static::$_extendedHeaders )
        {
            $_headers['X-DreamFactory-Source'] = $requestSource;

            if ( $whitelisted )
            {
                $_headers['X-DreamFactory-Origin-Whitelisted'] = preg_match( '/^([\w_-]+\.)*' . $requestSource . '$/', $originUri );
            }
        }

        return $_headers;
    }

    /**
     * @param string|array $origin     The parse_url value of origin
     * @param array        $additional Additional origins to allow
     *
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @return bool|array false if not allowed, otherwise array of verbs allowed
     */
    protected static function _allowedOrigin( $origin, $additional = array() )
    {
        foreach ( array_merge( static::$_corsWhitelist, Option::clean( $additional ) ) as $_hostInfo )
        {
            $_allowedMethods = static::CORS_DEFAULT_ALLOWED_METHODS;

            if ( is_array( $_hostInfo ) )
            {
                //	If is_enabled prop not there, assuming enabled.
                if ( !Scalar::boolval( Option::get( $_hostInfo, 'is_enabled', true ) ) )
                {
                    continue;
                }

                if ( null === ( $_whiteGuy = Option::get( $_hostInfo, 'host' ) ) )
                {
                    Log::error( 'CORS whitelist info does not contain a "host" parameter!' );
                    continue;
                }

                if ( isset( $_hostInfo['verbs'] ) )
                {
                    if ( false === array_search( static::CORS_OPTION_METHOD, $_hostInfo['verbs'] ) )
                    {
                        // add OPTION to allowed list
                        $_hostInfo['verbs'][] = static::CORS_OPTION_METHOD;
                    }
                    $_allowedMethods = implode( ', ', $_hostInfo['verbs'] );
                }
            }
            else
            {
                $_whiteGuy = $_hostInfo;
            }

            //	All allowed?
            if ( '*' == $_whiteGuy )
            {
                return $_allowedMethods;
            }

            if ( false === ( $_whiteParts = static::_parseUri( $_whiteGuy ) ) )
            {
                continue;
            }

            //	Check for un-parsed origin, 'null' sent when testing js files locally
            if ( is_array( $origin ) )
            {
                //	Is this origin on the whitelist?
                if ( static::_compareUris( $origin, $_whiteParts ) )
                {
                    return $_allowedMethods;
                }
            }
        }

        return false;
    }

    /**
     * @param array $first
     * @param array $second
     *
     * @return bool
     */
    protected static function _compareUris( $first, $second )
    {
        return ( $first['scheme'] == $second['scheme'] ) && ( $first['host'] == $second['host'] ) && ( $first['port'] == $second['port'] );
    }

    /**
     * @param string $uri
     * @param bool   $normalize
     *
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @return array
     */
    protected static function _parseUri( $uri, $normalize = false )
    {
        if ( false === ( $_parts = parse_url( $uri ) ) || !( isset( $_parts['host'] ) || isset( $_parts['path'] ) ) )
        {
            return false;
        }

        if ( isset( $_parts['path'] ) && !isset( $_parts['host'] ) )
        {
            //	Special case, handle this generically later
            if ( 'null' == $_parts['path'] )
            {
                return 'null';
            }

            $_parts['host'] = $_parts['path'];
            unset( $_parts['path'] );
        }

        $_protocol = Request::createFromGlobals()->isSecure() ? 'https' : 'http';

        $_uri = array(
            'scheme' => Option::get( $_parts, 'scheme', $_protocol ),
            'host'   => Option::get( $_parts, 'host' ),
            'port'   => Option::get( $_parts, 'port' ),
        );

        return $normalize ? static::_normalizeUri( $_uri ) : $_uri;
    }

    /**
     * @param array $parts Return from \parse_url
     *
     * @return string
     */
    protected static function _normalizeUri( $parts )
    {
        return is_array( $parts ) ?
            ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'http' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : null )
            : $parts;
    }

    /**
     * @return bool Returns true if CORS can be used/is needed, false otherwise
     */
    protected static function _corsNeeded()
    {
        return ( 'cli' != PHP_SAPI );
    }

    //*************************************************************************
    //	Accessors
    //*************************************************************************

    /**
     * @param array $corsWhitelist
     *
     * @throws RestException
     * @return PlatformWebApplication
     */
    public static function setCorsWhitelist( $corsWhitelist )
    {
        static::$_corsWhitelist = $corsWhitelist;

        //	Reset the header cache
        static::_buildCorsHeaders( false );
    }

    /**
     * @return array
     */
    public static function getCorsWhitelist()
    {
        return static::$_corsWhitelist;
    }

    /**
     * @param boolean $autoAddHeaders
     *
     * @return PlatformWebApplication
     */
    public static function setAutoAddHeaders( $autoAddHeaders = true )
    {
        static::$_autoAddHeaders = $autoAddHeaders;
    }

    /**
     * @return boolean
     */
    public static function getAutoAddHeaders()
    {
        return static::$_autoAddHeaders;
    }

    /**
     * @param boolean $extendedHeaders
     */
    public static function setExtendedHeaders( $extendedHeaders = true )
    {
        static::$_extendedHeaders = $extendedHeaders;
    }

    /**
     * @return boolean
     */
    public static function getExtendedHeaders()
    {
        return static::$_extendedHeaders;
    }

    /**
     * @param Response $responseObject
     */
    public static function setResponseObject( $responseObject )
    {
        self::$_responseObject = $responseObject;
    }

}