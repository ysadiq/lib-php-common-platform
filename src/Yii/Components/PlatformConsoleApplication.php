<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
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
namespace DreamFactory\Platform\Yii\Components;

use Composer\Autoload\ClassLoader;
use DreamFactory\Platform\Components\Profiler;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Utility\CorsManager;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\CoreSettings;
use Kisma\Core\Utility\Log;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PlatformConsoleApplication
 */
class PlatformConsoleApplication extends \CConsoleApplication
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
<<<<<<< HEAD
=======
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
     * @var string The private CORS configuration file
     */
    const CORS_DEFAULT_CONFIG_FILE = '/cors.config.json';
    /**
     * @var string The session key for CORS configs
     */
    const CORS_WHITELIST_KEY = 'cors.config';
    /**
>>>>>>> Composer update
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
<<<<<<< HEAD
=======
     * @var EventDispatcher
     */
    protected static $_dispatcher;
    /**
>>>>>>> Composer update
     * @var bool If true, profiling information is output to the log
     */
    protected static $_enableProfiler = false;
    /**
     * @var array[] The namespaces in use by this system. Used by the routing engine
     */
    protected static $_namespaceMap = array( self::NS_MODELS => array(), self::NS_SERVICES => array(), self::NS_RESOURCES => array() );
    /**
<<<<<<< HEAD
=======
     * @var array An indexed array of white-listed hosts (ajax.example.com or foo.bar.com or just bar.com)
     */
    protected $_corsWhitelist = array();
    /**
     * @var bool    If true, the CORS headers will be sent automatically before dispatching the action.
     *              NOTE: "OPTIONS" calls will always get headers, regardless of the setting. All other requests respect the setting.
     */
    protected $_autoAddHeaders = true;
    /**
     * @var bool If true, adds some extended information about the request in the form of X-DreamFactory-* headers
     */
    protected $_extendedHeaders = true;
    /**
>>>>>>> Composer update
     * @var array The namespaces that contain resources. Used by the routing engine
     */
    protected $_resourceNamespaces = array();
    /**
     * @var array The namespaces that contain models. Used by the resource manager
     */
    protected $_modelNamespaces = array();
    /**
     * @var Request
     */
    protected $_requestObject;
    /**
     * @var  Response
     */
    protected $_responseObject;
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> New exceptions for event streaming. Command line commands added to start the server/stream.
    /**
     * @var bool If true, headers will be added to the response object instance of this run
     */
    protected $_useResponseObject = false;
<<<<<<< HEAD
=======
>>>>>>> Composer update
=======
>>>>>>> New exceptions for event streaming. Command line commands added to start the server/stream.

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Initialize
     */
    protected function init()
    {
        parent::init();

<<<<<<< HEAD
=======
        $this->_loadCorsConfig();

>>>>>>> Composer update
        //	Debug options
        static::$_enableProfiler = Pii::getParam( 'dsp.enable_profiler', false );

        //	Setup the request handler and events
<<<<<<< HEAD
<<<<<<< HEAD
        /** @noinspection PhpUndefinedFieldInspection */
        $this->onBeginRequest = array( $this, '_onBeginRequest' );
        /** @noinspection PhpUndefinedFieldInspection */
=======
        $this->onBeginRequest = array( $this, '_onBeginRequest' );
>>>>>>> Composer update
=======
        /** @noinspection PhpUndefinedFieldInspection */
        $this->onBeginRequest = array( $this, '_onBeginRequest' );
        /** @noinspection PhpUndefinedFieldInspection */
>>>>>>> New exceptions for event streaming. Command line commands added to start the server/stream.
        $this->onEndRequest = array( $this, '_onEndRequest' );
    }

    /**
     * Start a profiler
     *
     * @param string $id The id of the profiler
     *
     * @return $this
     */
    public function startProfiler( $id = __CLASS__ )
    {
        if ( static::$_enableProfiler )
        {
            Profiler::start( $id );
        }

        return $this;
    }

    /**
     * Stop a profiler
     *
     * @param string $id The id of the profiler
     * @param bool   $prettyPrint
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function stopProfiler( $id = __CLASS__, $prettyPrint = true )
    {
        if ( static::$_enableProfiler )
        {
            Log::debug( '~~ "' . $id . '" profile: ' . Profiler::stop( 'app.request', $prettyPrint ) );
        }

        return $this;
    }

    /**
     * Loads up any plug-ins configured
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @return bool
     */
    protected function _loadPlugins()
    {
        if ( null === ( $_autoloadPath = \Kisma::get( 'dsp.plugin_autoload_path' ) ) )
        {
            //	Locate plug-in directory...
            $_path = Pii::getParam( 'dsp.plugins_path', Pii::getParam( 'dsp.base_path' ) . static::DEFAULT_PLUGINS_PATH );

            if ( !is_dir( $_path ) )
            {
                // No plug-ins installed

                return false;
            }

            if ( file_exists( $_path . '/autoload.php' ) && is_readable( $_path . '/autoload.php' ) )
            {
                $_autoloadPath = $_path . '/autoload.php';
                Log::debug( 'Found plug-in autoload.php' );
            }
            else
            {
                Log::debug( 'No autoload.php file found for installed plug-ins.' );

                return false;
            }

            \Kisma::set( 'dsp.plugin_autoload_path', $_autoloadPath );
        }

        /** @noinspection PhpIncludeInspection */
        if ( false === @require( $_autoloadPath ) )
        {
<<<<<<< HEAD
            Log::error( 'Error reading plug-in configuration file. Some plug-ins may not function properly.' );
=======
            Log::error( 'Error reading plug-in autoload.php file. Some plug-ins may not function properly.' );
>>>>>>> Composer update

            return false;
        }

<<<<<<< HEAD
=======
        $this->trigger( DspEvents::PLUGINS_LOADED );

>>>>>>> Composer update
        return true;
    }

    //*************************************************************************
    //	Event Handlers
    //*************************************************************************

    /**
     * Handles an OPTIONS request to the server to allow CORS and optionally sends the CORS headers
     *
     * @param \CEvent $event
     *
     * @throws \UnexpectedValueException
     * @throws RestException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @return bool
     */
    protected function _onBeginRequest( \CEvent $event )
    {
        //	Start the request-only profile
        $this->startProfiler( 'app.request' );
<<<<<<< HEAD
        $this->_requestObject = Request::createFromGlobals();

=======

        $this->_requestObject = Request::createFromGlobals();

        //  Call getter to get CORS headers auto-added
        $_response = $this->_useResponseObject ? $this->getResponseObject() : null;

        switch ( $this->_requestObject->getMethod() )
        {
            case HttpMethod::TRACE:
                Log::error(
                    'HTTP TRACE received!',
                    array(
                        'server'  => $_SERVER,
                        'request' => $_REQUEST,
                    )
                );

                throw new BadRequestException();

            case HttpMethod::OPTIONS:
                if ( $this->_useResponseObject )
                {
                    $_response->setStatusCode( HttpResponse::NoContent )->setContent( '' )->headers->set( 'Content-Type', 'text/plain', true );
                    $_response->send();
                }
                else
                {
                    header( 'HTTP/1.1 204' );
                    header( 'content-length: 0' );
                    header( 'content-type: text/plain' );

                    $this->_useResponseObject = false;
                    $this->addCorsHeaders();
                }

                return Pii::end();
        }

        //	Auto-add the CORS headers...
        if ( $this->_autoAddHeaders )
        {
            $this->addCorsHeaders();
        }

>>>>>>> Composer update
        //	Load any plug-ins
        $this->_loadPlugins();
    }

    /**
     * @param \CEvent $event
     */
    protected function _onEndRequest( \CEvent $event )
    {
        $this->stopProfiler( 'app.request' );
    }

<<<<<<< HEAD
=======
    //*************************************************************************
    //  Server-Side Event Support
    //*************************************************************************

    /**
     * Triggers a DSP-level event
     *
     * @param string        $eventName
     * @param PlatformEvent $event
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \Exception
     * @return DspEvent
     */
    public function trigger( $eventName, $event = null )
    {
        return static::getDispatcher()->dispatch( $eventName, $event );
    }

    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string   $eventName            The event to listen on
     * @param callable $listener             The listener
     * @param integer  $priority             The higher this value, the earlier an event
     *                                       listener will be triggered in the chain (defaults to 0)
     *
     * @return void
     */
    public function on( $eventName, $listener, $priority = 0 )
    {
        static::getDispatcher()->addListener( $eventName, $listener, $priority );
    }

    /**
     * Turn off/unbind/remove $listener from an event
     *
     * @param string   $eventName
     * @param callable $listener
     *
     * @return void
     */
    public function off( $eventName, $listener )
    {
        static::getDispatcher()->removeListener( $eventName, $listener );
    }
    //*************************************************************************
    //	CORS Support
    //*************************************************************************

    /**
     * @param array|bool $whitelist     Set to "false" to reset the internal method cache.
     * @param bool       $returnHeaders If true, the headers are return in an array and NOT sent
     * @param bool       $sendHeaders   If false, the headers will NOT be sent. Defaults to true. $returnHeaders takes precedence
     *
     * @throws RestException
     * @throws \InvalidArgumentException
     * @return bool|array
     */
    public function addCorsHeaders( $whitelist = array(), $returnHeaders = false, $sendHeaders = true )
    {
        $_headers = $this->_buildCorsHeaders( $whitelist );

        if ( $returnHeaders )
        {
            return $_headers;
        }

        if ( $this->_useResponseObject )
        {
            //  Initialize the response object if not already
            $this->getResponseObject();

            foreach ( $_headers as $_key => $_value )
            {
                $this->_responseObject->headers->set( $_key, $_value, true );
            }

            if ( $sendHeaders )
            {
                $this->_responseObject->sendHeaders();
            }
        }
        elseif ( $sendHeaders )
        {
            //	Dump the headers
            foreach ( $_headers as $_key => $_value )
            {
                header( $_key . ': ' . $_value, true );
            }
        }

        return true;
    }

    /**
     * Builds a cache key for a request and returns the constituent parts
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    protected function _buildCacheKey()
    {
        $_origin = trim( Option::server( 'HTTP_ORIGIN' ) );
        $_requestSource = Option::server( 'SERVER_NAME', \Kisma::get( 'platform.host_name', gethostname() ) );

        if ( false === ( $_originParts = $this->_parseUri( $_origin ) ) )
        {
            //	Not parse-able, set to itself, check later (testing from local files - no session)?
            Log::warning( 'Unable to parse received origin: [' . $_origin . ']' );
            $_originParts = $_origin;
        }

        $_originUri = $this->_normalizeUri( $_originParts );
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
    protected function _buildCorsHeaders( $whitelist = array() )
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
            ) = $this->_buildCacheKey();

        //	Was an origin header passed? If not, don't do CORS.
        if ( !empty( $_origin ) )
        {
            //	Not in cache, check it out...
            if ( !in_array( $_key, $_cache ) )
            {
                if ( false === ( $_allowedMethods = $this->_allowedOrigin( $_originParts, $_requestSource ) ) )
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

        return $_headers + $this->_buildExtendedHeaders( $_requestSource, $_originUri, !empty( $_origin ) );
    }

    /**
     * @param string $requestSource
     * @param string $originUri
     * @param bool   $whitelisted If the origin is whitelisted
     *
     * @return array
     */
    protected function _buildExtendedHeaders( $requestSource, $originUri, $whitelisted = true )
    {
        $_headers = array();

        if ( $this->_extendedHeaders )
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
     * @throws \InvalidArgumentException
     * @return bool|array false if not allowed, otherwise array of verbs allowed
     */
    protected function _allowedOrigin( $origin, $additional = array() )
    {
        foreach ( array_merge( $this->_corsWhitelist, Option::clean( $additional ) ) as $_hostInfo )
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

            if ( false === ( $_whiteParts = $this->_parseUri( $_whiteGuy ) ) )
            {
                continue;
            }

            //	Check for un-parsed origin, 'null' sent when testing js files locally
            if ( is_array( $origin ) )
            {
                //	Is this origin on the whitelist?
                if ( $this->_compareUris( $origin, $_whiteParts ) )
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
    protected function _compareUris( $first, $second )
    {
        return ( $first['scheme'] == $second['scheme'] ) && ( $first['host'] == $second['host'] ) && ( $first['port'] == $second['port'] );
    }

    /**
     * @param string $uri
     * @param bool   $normalize
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    protected function _parseUri( $uri, $normalize = false )
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

        $_protocol = $this->_requestObject->isSecure() ? 'https' : 'http';

        $_uri = array(
            'scheme' => Option::get( $_parts, 'scheme', $_protocol ),
            'host'   => Option::get( $_parts, 'host' ),
            'port'   => Option::get( $_parts, 'port' ),
        );

        return $normalize ? $this->_normalizeUri( $_uri ) : $_uri;
    }

    /**
     * @param array $parts Return from \parse_url
     *
     * @return string
     */
    protected function _normalizeUri( $parts )
    {
        return is_array( $parts ) ?
            ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'http' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : null )
            : $parts;
    }

    /**
     * Loads the CORS whitelist from the session. If not there, it's loaded and stuffed in there.
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws RestException
     * @return $this
     */
    protected function _loadCorsConfig()
    {
        static $_whitelist = null;

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

        return $this->setCorsWhitelist( $_whitelist );
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
    public function setCorsWhitelist( $corsWhitelist )
    {
        $this->_corsWhitelist = $corsWhitelist;

        //	Reset the header cache
        $this->_buildCorsHeaders( false );

        return $this;
    }

    /**
     * @return array
     */
    public function getCorsWhitelist()
    {
        return $this->_corsWhitelist;
    }

    /**
     * @param boolean $autoAddHeaders
     *
     * @return PlatformWebApplication
     */
    public function setAutoAddHeaders( $autoAddHeaders = true )
    {
        $this->_autoAddHeaders = $autoAddHeaders;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAutoAddHeaders()
    {
        return $this->_autoAddHeaders;
    }

    /**
     * @param boolean $extendedHeaders
     *
     * @return PlatformWebApplication
     */
    public function setExtendedHeaders( $extendedHeaders = true )
    {
        $this->_extendedHeaders = $extendedHeaders;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getExtendedHeaders()
    {
        return $this->_extendedHeaders;
    }

>>>>>>> Composer update
    /**
     * @param bool $createIfNull If true, the default, the response object will be created if it hasn't already
     * @param bool $sendHeaders
     *
<<<<<<< HEAD
     * @throws \DreamFactory\Platform\Utility\RestException
=======
     * @throws \InvalidArgumentException
     * @throws RestException
>>>>>>> Composer update
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResponseObject( $createIfNull = true, $sendHeaders = true )
    {
        if ( null === $this->_responseObject && $createIfNull )
        {
<<<<<<< HEAD
            $this->setResponseObject( Response::create() );
=======
            $this->_responseObject = Response::create();

            if ( $this->_autoAddHeaders )
            {
                $this->addCorsHeaders( array(), false, $sendHeaders );
            }
>>>>>>> Composer update
        }

        return $this->_responseObject;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Response $responseObject
     *
<<<<<<< HEAD
     * @throws \DreamFactory\Platform\Utility\RestException
=======
>>>>>>> Composer update
     * @return PlatformWebApplication
     */
    public function setResponseObject( $responseObject )
    {
<<<<<<< HEAD
        CorsManager::setResponseObject( $this->_responseObject = $responseObject );
        CorsManager::autoSendHeaders();
=======
        $this->_responseObject = $responseObject;
>>>>>>> Composer update

        return $this;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $requestObject
     *
     * @return PlatformWebApplication
     */
    public function setRequestObject( $requestObject )
    {
        $this->_requestObject = $requestObject;

        return $this;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequestObject()
    {
        return $this->_requestObject;
    }

    /**
     * @param boolean $enableProfiler
     */
    public static function setEnableProfiler( $enableProfiler )
    {
        static::$_enableProfiler = $enableProfiler;
    }

    /**
     * @return boolean
     */
    public static function getEnableProfiler()
    {
        return static::$_enableProfiler;
    }

    /**
     * @param array $resourceNamespaces
     *
     * @return PlatformWebApplication
     */
    public function setResourceNamespaces( $resourceNamespaces )
    {
        static::$_namespaceMap[static::NS_RESOURCES] = $resourceNamespaces;

        return $this;
    }

    /**
     * @return array
     */
    public function getResourceNamespaces()
    {
        return static::$_namespaceMap[static::NS_RESOURCES];
    }

    /**
     * @param string $namespace
     * @param string $path
     * @param bool   $prepend If true, the namespace(s) will be placed at the beginning of the list
     *
     * @return PlatformWebApplication
     */
    public function addResourceNamespace( $namespace, $path, $prepend = false )
    {
        static::_mapNamespace( static::NS_RESOURCES, $namespace, $path, $prepend );
        array_unshift( $this->_modelNamespaces, $_entry );

        return $this;
    }

    /**
     * @param array $modelNamespaces
     *
     * @return PlatformWebApplication
     */
    public function setModelNamespaces( $modelNamespaces )
    {
        static::$_namespaceMap[static::NS_MODELS] = $modelNamespaces;

        return $this;
    }

    /**
     * @return array
     */
    public function getModelNamespaces()
    {
        return static::$_namespaceMap[static::NS_MODELS];
    }

    /**
     * @param string $namespace
     * @param string $path
     * @param bool   $prepend If true, the namespace(s) will be placed at the beginning of the list
     *
     * @return PlatformWebApplication
     */
    public function addModelNamespace( $namespace, $path, $prepend = false )
    {
        static::_mapNamespace( static::NS_MODELS, $namespace, $path, $prepend );

        return $this;
    }

    /**
     * @param int $which Which map to return or null for all
     *
     * @return \array[]
     */
    public static function getNamespaceMap( $which = null )
    {
        return $which ? static::$_namespaceMap[$which] : static::$_namespaceMap;
    }

    /**
     * Registers a set of PSR-4 directories for a given namespace, either
     * appending or prepending to the ones previously set for this namespace.
     *
     * @param string       $prefix  The prefix/namespace, with trailing '\\'
     * @param array|string $paths   The PSR-0 base directories
     * @param bool         $prepend Whether to prepend the directories
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return \Composer\Autoload\ClassLoader
     */
    public static function addNamespace( $prefix, $paths, $prepend = false )
    {
        /** @var ClassLoader $_loader */
        if ( null === ( $_loader = \Kisma::get( CoreSettings::AUTO_LOADER ) ) )
        {
            throw new InternalServerErrorException( 'Unable to find auto-loader. :(' );
        }

        $_loader->addPsr4( $prefix, $paths, $prepend );

        return $_loader;
    }

    /**
     * @param int    $which
     * @param string $namespace
     * @param string $path
     * @param bool   $prepend If true, the namespace(s) will be placed at the beginning of the list
     *
     * @return PlatformWebApplication
     */
    protected static function _mapNamespace( $which, $namespace, $path, $prepend = false )
    {
        if ( $prepend )
        {
            array_unshift( static::$_namespaceMap[$which], array( $namespace, $path ) );
        }
        else
        {
            static::$_namespaceMap[$which][$namespace] = $path;
        }
    }

    /**
<<<<<<< HEAD
     * @param array $corsWhitelist
     *
     * @throws \DreamFactory\Platform\Utility\RestException
     * @return PlatformWebApplication
     */
    public function setCorsWhitelist( $corsWhitelist )
    {
        CorsManager::setCorsWhitelist( $corsWhitelist );

        return $this;
    }

    /**
     * @return array
     */
    public function getCorsWhitelist()
    {
        return CorsManager::getCorsWhitelist();
    }

    /**
     * @param boolean $autoAddHeaders
     *
     * @return PlatformWebApplication
     */
    public function setAutoAddHeaders( $autoAddHeaders = true )
    {
        CorsManager::setAutoAddHeaders( $autoAddHeaders );

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAutoAddHeaders()
    {
        return CorsManager::getAutoAddHeaders();
    }

    /**
     * @param boolean $extendedHeaders
     *
     * @return PlatformWebApplication
     */
    public function setExtendedHeaders( $extendedHeaders = true )
    {
        CorsManager::setExtendedHeaders( $extendedHeaders );

        return $this;
    }

    /**
     * @return boolean
     */
    public function getExtendedHeaders()
    {
        return CorsManager::getExtendedHeaders();
=======
     * @param EventDispatcher $dispatcher
     */
    public static function setDispatcher( $dispatcher )
    {
        static::$_dispatcher = $dispatcher;
    }

    /**
     * @return EventDispatcher
     */
    public static function getDispatcher()
    {
        if ( empty( static::$_dispatcher ) )
        {
            static::$_dispatcher = new EventDispatcher();
        }

        return static::$_dispatcher;
>>>>>>> Composer update
    }

    /**
     * @param array $corsWhitelist
     *
     * @throws \DreamFactory\Platform\Utility\RestException
     * @return PlatformWebApplication
     */
    public function setCorsWhitelist( $corsWhitelist )
    {
        CorsManager::setCorsWhitelist( $corsWhitelist );

        return $this;
    }

    /**
     * @return array
     */
    public function getCorsWhitelist()
    {
        return CorsManager::getCorsWhitelist();
    }

    /**
     * @param boolean $autoAddHeaders
     *
     * @return PlatformWebApplication
     */
    public function setAutoAddHeaders( $autoAddHeaders = true )
    {
        CorsManager::setAutoAddHeaders( $autoAddHeaders );

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAutoAddHeaders()
    {
        return CorsManager::getAutoAddHeaders();
    }

    /**
     * @param boolean $extendedHeaders
     *
     * @return PlatformWebApplication
     */
    public function setExtendedHeaders( $extendedHeaders = true )
    {
        CorsManager::setExtendedHeaders( $extendedHeaders );

        return $this;
    }

    /**
     * @return boolean
     */
    public function getExtendedHeaders()
    {
        return CorsManager::getExtendedHeaders();
    }

    /**
     * @return boolean
     */
    public function getUseResponseObject()
    {
<<<<<<< HEAD
        return false;
=======
        return $this->_useResponseObject;
>>>>>>> Composer update
    }

    /**
     * @param boolean $useResponseObject
     *
     * @return PlatformWebApplication
     */
    public function setUseResponseObject( $useResponseObject )
    {
<<<<<<< HEAD
        return $this;
    }


=======
        $this->_useResponseObject = $useResponseObject;

        return $this;
    }

>>>>>>> Composer update
}