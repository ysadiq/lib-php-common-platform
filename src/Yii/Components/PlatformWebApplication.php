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
use DreamFactory\Library\Utility\Exceptions\FileSystemException;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Library\Utility\Includer;
use DreamFactory\Library\Utility\JsonFile;
use DreamFactory\Platform\Components\Profiler;
use DreamFactory\Platform\Enums\NamespaceTypes;
use DreamFactory\Platform\Events\Enums\DspEvents;
use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Scripting\ScriptEvent;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\CoreSettings;
use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Interfaces\PublisherLike;
use Kisma\Core\Interfaces\SubscriberLike;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PlatformWebApplication
 *
 * @property callable onEndRequest
 * @property callable onBeginRequest
 */
class PlatformWebApplication extends \CWebApplication implements PublisherLike, SubscriberLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string Indicates all is allowed
     */
    const CORS_STAR = '*';
    /**
     * @var string The HTTP Option method
     */
    const CORS_OPTION_METHOD = Request::METHOD_OPTIONS;
    /**
     * @var string The allowed HTTP methods
     */
    const CORS_DEFAULT_ALLOWED_METHODS = 'GET, POST, PUT, DELETE, PATCH, MERGE, COPY, OPTIONS';
    /**
     * @var string The allowed HTTP headers
     * Tunnelling verb overrides: X-HTTP-Method (Microsoft), X-HTTP-Method-Override (Google/GData), X-METHOD-OVERRIDE
     * (IBM)
     */
    const CORS_DEFAULT_ALLOWED_HEADERS = 'Content-Type,X-Requested-With,X-DreamFactory-Application-Name,X-Application-Name,X-DreamFactory-Session-Token,X-HTTP-Method,X-HTTP-Method-Override,X-METHOD-OVERRIDE';
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
     * @var string The pattern of for local configuration files
     */
    const DEFAULT_LOCAL_CONFIG_PATTERN = '/*.config.php';
    /**
     * @var string The default path (sub-path) of installed plug-ins
     */
    const DEFAULT_PLUGINS_PATH = '/storage/plugins';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var EventDispatcher
     */
    protected static $_dispatcher;
    /**
     * @var bool If true, profiling information is output to the log
     */
    protected static $_enableProfiler = false;
    /**
     * @var array[] The namespaces in use by this system. Used by the routing engine
     */
    protected static $_namespaceMap = array(
        NamespaceTypes::MODELS    => array(),
        NamespaceTypes::SERVICES  => array(),
        NamespaceTypes::RESOURCES => array()
    );
    /**
     * @var array An indexed array of white-listed hosts (ajax.example.com or foo.bar.com or just bar.com)
     */
    protected $_corsWhitelist = array();
    /**
     * @var bool    If true, the CORS headers will be sent automatically before dispatching the action.
     *              NOTE: "OPTIONS" calls will always get headers, regardless of the setting. All other requests
     *              respect the setting.
     */
    protected $_autoAddHeaders = true;
    /**
     * @var bool If true, adds some extended information about the request in the form of X-DreamFactory-* headers
     */
    protected $_extendedHeaders = true;
    /**
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
     * @var array Normalized array of inbound request body
     */
    protected $_requestBody;
    /**
     * @var  Response
     */
    protected $_responseObject;
    /**
     * @var bool If true, headers will be added to the response object instance of this run
     */
    protected $_useResponseObject = false;
    /**
     * @var bool If true, CORS info will be logged
     */
    protected $_logCorsInfo = false;
    /**
     * @type \CDbCache
     */
    protected $_cache = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Initialize
     */
    protected function init()
    {
        parent::init();

        //	Debug options
        $this->_logCorsInfo = Pii::getParam( 'dsp.log_cors_info', false );
        static::$_enableProfiler = Pii::getParam( 'dsp.enable_profiler', false );

        $this->_localInit();

        //	Setup the request handler and events
        $this->onBeginRequest = array($this, '_onBeginRequest');
        $this->onEndRequest = array($this, '_onEndRequest');
    }

    /**
     * Initializes all instance-specific/locally-added modules/configs
     *
     * @return mixed
     */
    protected function _localInit()
    {
        //  Load the CORS config file
        if ( 'cli' != PHP_SAPI )
        {
            $this->_loadCorsConfig();
        }

        //  Load any user config files...
        $this->_loadLocalConfig();

        //	Load any plug-ins
        $this->_loadPlugins();

        return true;
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
        if ( false === ( $_autoloadPath = $this->_appCache()->get( 'dsp.plugin_autoload_path' ) ) )
        {
            //	Locate plug-in directory...
            $_path = Platform::getPluginsPath();

            if ( !is_dir( $_path ) )
            {
                // No plug-ins installed

                return false;
            }

            if ( file_exists( $_path . '/autoload.php' ) && is_readable( $_path . '/autoload.php' ) )
            {
                $_autoloadPath = $_path . '/autoload.php';
                //Log::debug( 'Found plug-in autoload.php' );
            }
            else
            {
                // No autoload.php file found for installed plug-ins
                return false;
            }

            $this->_appCache()->set( 'dsp.plugin_autoload_path', $_autoloadPath, Platform::DEFAULT_CACHE_TTL );
        }

        /** @noinspection PhpIncludeInspection */
        if ( false === Includer::includeIfExists( $_autoloadPath, true ) )
        {
            Log::error( 'Error reading plug-in autoload.php file. Some plug-ins may not function properly.' );

            return false;
        }

        $this->trigger( DspEvents::PLUGINS_LOADED );

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
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @return bool
     */
    protected function _onBeginRequest( \CEvent $event )
    {
        //	Start the request-only profile
        $this->startProfiler( 'app.request' );

        //  A pristine copy of the request
        $this->_requestBody = ScriptEvent::buildRequestArray();

        //	Answer an options call...
        switch ( FilterInput::server( 'REQUEST_METHOD' ) )
        {
            case HttpMethod::OPTIONS:
                header( 'HTTP/1.1 204' );
                header( 'content-length: 0' );
                header( 'content-type: text/plain' );

                $this->addCorsHeaders();

                return Pii::end();

            case HttpMethod::TRACE:
                Log::error(
                    'HTTP TRACE received!',
                    array(
                        'server'  => $_SERVER,
                        'request' => $_REQUEST,
                    )
                );

                throw new BadRequestException();
        }

        //	Auto-add the CORS headers...
        if ( $this->_autoAddHeaders )
        {
            $this->addCorsHeaders();
        }
    }

    /**
     * @param \CEvent $event
     */
    protected function _onEndRequest( \CEvent $event )
    {
        $this->stopProfiler( 'app.request' );
    }

    /**
     * Loads any local configuration files
     */
    protected function _loadLocalConfig()
    {
        if ( false === ( $_config = $this->_appCache()->get( 'platform.local_config' ) ) )
        {
            $_config = array();
            $_configPath = Platform::getLocalConfigPath();

            $_files = FileSystem::glob(
                $_configPath . static::DEFAULT_LOCAL_CONFIG_PATTERN,
                GlobFlags::GLOB_NODIR | GlobFlags::GLOB_NODOTS
            );

            if ( empty( $_files ) )
            {
                $_files = array();
            }

            sort( $_files );

            foreach ( $_files as $_file )
            {
                try
                {
                    /** @noinspection PhpIncludeInspection */
                    if ( false === ( $_data = @include( $_configPath . '/' . $_file ) ) )
                    {
                        throw new FileSystemException( 'File system error reading local config file "' . $_file . '"' );
                    }

                    if ( !is_array( $_data ) )
                    {
                        Log::notice( 'Config file "' . $_file . '" did not return an array. Skipping.' );
                    }
                    else
                    {
                        $_config = array_merge( $_config, $_data );

                        $this->trigger(
                            DspEvents::LOCAL_CONFIG_LOADED,
                            new PlatformEvent(
                                array(
                                    'file' => $_file,
                                    'data' => $_data
                                )
                            )
                        );
                    }
                }
                catch ( FileSystemException $_ex )
                {
                    Log::error( $_ex->getMessage() );
                }
            }

            $this->_appCache()->set( 'platform.local_config', $_config, Platform::DEFAULT_CACHE_TTL );
        }

        //  Merge config with our params...
        if ( !empty( $_config ) )
        {
            $this->getParams()->mergeWith( $_config );
        }
    }

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
     * @return PlatformEvent
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
     * @param bool       $sendHeaders   If false, the headers will NOT be sent. Defaults to true. $returnHeaders takes
     *                                  precedence
     *
     * @return bool|array
     */
    public function addCorsHeaders( $whitelist = array(), $returnHeaders = false, $sendHeaders = true )
    {
        //	Reset the cache before processing...
        if ( false === $whitelist )
        {
            $this->_appCache()->delete( 'dsp.cors_config' );
            $this->_logCorsInfo && Log::debug( 'CORS: internal cache reset.' );

            return true;
        }

        //	Find out if we actually received an origin header
        if ( !isset( $_SERVER, $_SERVER['HTTP_ORIGIN'] ) || !array_key_exists( 'HTTP_ORIGIN', $_SERVER ) )
        {
            //  No origin header, no CORS...
            $this->_logCorsInfo && Log::debug( 'CORS: no origin received.' );

            return $returnHeaders ? array() : false;
        }

        if ( false === ( $_cache = $this->_appCache()->get( 'dsp.cors_whitelist' ) ) )
        {
            $_cache = array();
        }

        $_origin = trim( strtolower( $_SERVER['HTTP_ORIGIN'] ) );

        //  Bail if origin is 'file://', 'null', or empty.
        if ( 'file://' == $_origin )
        {
            $this->_logCorsInfo && Log::debug( 'CORS: local file/empty resource origin received: ' . $_origin );
        }
        else
        {
            //  empty origin received we do nothing
            if ( empty( $_origin ) || 'null' == $_origin )
            {
                return $returnHeaders ? array() : false;
            }
        }

        $_isStar = false;
        $_allowedMethods = $_headers = array();
        $_requestUri = $this->getRequestObject()->getSchemeAndHttpHost();

        $this->_logCorsInfo && Log::debug( 'CORS: origin received: ' . $_origin );

        if ( false === ( $_originParts = $this->_parseUri( $_origin ) ) )
        {
            //	Not parse-able, set to itself, check later (testing from local files - no session)?
            Log::warning( 'CORS: unable to parse received origin: [' . $_origin . ']' );
            $_originParts = $_origin;
        }

        $_originUri = ( is_array( $_originParts ) ? trim( $this->_normalizeUri( $_originParts ) ) : static::CORS_STAR );
        $_key = sha1( $_requestUri . $_originUri );

        $this->_logCorsInfo && Log::debug( 'CORS: origin URI "' . $_originUri . '" assigned key "' . $_key . '"' );

        //	Not in cache, check it out...
        if ( in_array( $_key, $_cache ) )
        {
            if ( null !== ( $_uri = IfSet::get( $_cache[$_key], 'origin_uri' ) ) )
            {
                $_originUri = $_uri;
            }

            $_allowedMethods = IfSet::get( $_cache[$_key], 'allowed_methods', array() );
            $_headers = IfSet::get( $_cache[$_key], 'headers', array() );
        }

        if ( empty( $_originUri ) || empty( $_allowedMethods ) || empty( $_headers ) )
        {
            if ( false === ( $_allowedMethods = $this->_allowedOrigin( $_originParts, $_requestUri, $_isStar ) ) )
            {
                Log::error( 'CORS: unauthorized origin rejected > Source: ' . $_requestUri . ' > Origin: ' . $_originUri );

                /** No sir, I didn't like it. @link http://www.youtube.com/watch?v=VRaoHi_xcWk */
                header( 'HTTP/1.1 403 Forbidden' );

                return Pii::end();
            }

            $_headers = array(
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Allow-Headers'     => static::CORS_DEFAULT_ALLOWED_HEADERS,
                'Access-Control-Allow-Methods'     => $_allowedMethods,
                'Access-Control-Allow-Origin'      => $_isStar ? static::CORS_STAR : $_originUri,
                'Access-Control-Max-Age'           => static::CORS_DEFAULT_MAX_AGE,
            );

            if ( $this->_extendedHeaders )
            {
                $_headers['X-DreamFactory-Source'] = $_requestUri;
                $_headers['X-DreamFactory-Origin-Whitelisted'] =
                    preg_match( '#^([\w_-]+\.)*' . preg_quote( $_requestUri ) . '$#', $_originUri );
            }
        }

        //	Store in cache...
        $_cache[$_key] = array(
            'origin_uri'      => $_originUri,
            'allowed_methods' => $_allowedMethods,
            'headers'         => $_headers
        );

        $this->_appCache()->set( 'dsp.cors_config', $_cache, Platform::DEFAULT_CACHE_TTL );

        if ( $returnHeaders )
        {
            return $_headers;
        }

        //  Send all the headers
        if ( $sendHeaders )
        {
            $_out = null;

            foreach ( $_headers as $_key => $_value )
            {
                header( $_key . ': ' . $_value );
                $_out .= $_key . ': ' . $_value . PHP_EOL;
            }

            $this->_logCorsInfo &&
            Log::debug(
                'CORS: headers sent' . PHP_EOL .
                '*=== Headers Start ===*' . PHP_EOL .
                $_out .
                '*=== Headers End ===*' . PHP_EOL
            );
        }

        return true;
    }

    /**
     * @param string|array $origin     The parse_url value of origin
     * @param array        $additional Additional origins to allow
     * @param bool         $isStar     Set to true if the allowed origin is "*"
     *
     * @return bool|array false if not allowed, otherwise array of verbs allowed
     */
    protected function _allowedOrigin( $origin, $additional = array(), &$isStar = false )
    {
        foreach ( array_merge( $this->_corsWhitelist, Option::clean( $additional ) ) as $_hostInfo )
        {
            $_allowedMethods = static::CORS_DEFAULT_ALLOWED_METHODS;

            if ( is_array( $_hostInfo ) )
            {
                //	If is_enabled prop not there, assuming enabled.
                if ( !IfSet::getBool( $_hostInfo, 'is_enabled', true ) )
                {
                    continue;
                }

                if ( null === ( $_whiteGuy = IfSet::get( $_hostInfo, 'host' ) ) )
                {
                    Log::error( 'CORS: whitelist info does not contain a "host" parameter!' );
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
            if ( static::CORS_STAR == $_whiteGuy )
            {
                $isStar = true;

                return $_allowedMethods;
            }

            if ( false === ( $_whiteParts = $this->_parseUri( $_whiteGuy ) ) )
            {
                Log::error( 'CORS: unable to parse "' . $_whiteGuy . '" whitelist entry' );
                continue;
            }

            $this->_logCorsInfo && Log::debug( 'CORS: whitelist "' . $_whiteGuy . '" > parts: ' . print_r( $_whiteParts, true ) );

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
        $_match =
            ( ( $first['scheme'] == $second['scheme'] ) &&
                ( $first['host'] == $second['host'] ) &&
                ( $first['port'] == $second['port'] ) );

        if ( $this->_logCorsInfo )
        {
            Log::debug(
                'CORS: compare inbound origin to whitelisted host: ' . ( $_match ? 'Success' : 'FAIL' ) . PHP_EOL .
                '  * ORIGIN: ' . print_r( $first, true ) . PHP_EOL .
                '  *  WHITE: ' . print_r( $second, true ) . PHP_EOL
            );
        }

        return $_match;
    }

    /**
     * @param string $uri
     * @param bool   $normalize
     *
     * @return array
     */
    protected function _parseUri( $uri, $normalize = false )
    {
        //  Don't parse empty uris, nor should it get here...
        if ( empty( $uri ) )
        {
            return true;
        }

        $_parts = parse_url( $uri );

        if ( false === $_parts || !( isset( $_parts['host'] ) || isset( $_parts['path'] ) ) )
        {
            return false;
        }

        $_parts['scheme'] = IfSet::get(
            $_parts,
            'scheme',
            'http' . ( IfSet::getBool( $_SERVER, 'HTTPS', false ) ? 's' : null )
        );

        $_parts['port'] = IfSet::get( $_parts, 'port' );

        //  Set ports to defaults for scheme if empty
        if ( empty( $_parts['port'] ) )
        {
            switch ( $_parts['scheme'] )
            {
                case 'http':
                    $_parts['port'] = 80;
                    break;

                case 'https':
                    $_parts['port'] = 443;
                    break;

                default:
                    $_parts['port'] = null;
                    break;
            }
        }

        //  If standard port 80 or 443 and there is no port in uri, clear from parse...
        if ( !empty( $_parts['port'] ) && ( $_parts['port'] == 80 || $_parts['port'] == 443 ) &&
            false === strpos( $uri, ':' . $_parts['port'] )
        )
        {
            $_parts['port'] = null;
        }

        $this->_logCorsInfo && Log::debug( 'CORS: parsed inbound URI "' . $uri . '": ' . print_r( $_parts, true ) );

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

        $_uri = array(
            'scheme' => IfSet::get( $_parts, 'scheme' ),
            'host'   => IfSet::get( $_parts, 'host' ),
            'port'   => IfSet::get( $_parts, 'port' ),
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
        return !is_array( $parts )
            ? $parts
            :
            ( !empty( $parts['host'] ) && isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : 'http://' ) .
            $parts['host'] .
            ( !empty( $parts['host'] ) && isset( $parts['port'] ) ? ':' . $parts['port'] : null );
    }

    /**
     * Loads the CORS whitelist from the session. If not there, it's loaded and stuffed in there.
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return $this
     */
    protected function _loadCorsConfig()
    {
        static $_whitelist = null, $_locations = null;

        if ( null === $_whitelist )
        {
            //  Empty whitelist...
            $_config = false;
            $_whitelist = array();
            $_locations = $_locations
                ?: array(
                    Platform::getLocalConfigPath( static::CORS_DEFAULT_CONFIG_FILE, true, true ),
                    Platform::getPrivatePath( static::CORS_DEFAULT_CONFIG_FILE, true, true ),
                    Platform::getStoragePath( static::CORS_DEFAULT_CONFIG_FILE, true, true ),
                );

            //	Find cors config file location
            foreach ( $_locations as $_path )
            {
                if ( file_exists( $_path ) )
                {
                    $_config = $_path;
                    break;
                }
            }

            if ( !$_config )
            {
                $this->_logCorsInfo && Log::debug( 'CORS: no configuration file found.' );

                return null;
            }

            try
            {
                $_whitelist = JsonFile::decodeFile( $_config );
                $this->_logCorsInfo && Log::debug( 'CORS: configuration loaded: ' . print_r( $_whitelist, true ) );
            }
            catch ( \Exception $_ex )
            {
                $_whitelist = null;
                Log::error( 'CORS: configuration file "' . $_config . '" is corrupt or unreadable. Cannot be used and ignoring' );
            }
        }

        //  Don't reset if they're the same.
        return $this->_corsWhitelist === $_whitelist ? null : $this->setCorsWhitelist( $_whitelist );
    }

    /**
     * @param array $corsWhitelist
     *
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @return PlatformWebApplication
     */
    public function setCorsWhitelist( $corsWhitelist )
    {
        $this->_corsWhitelist = $corsWhitelist;

        //	Reset the header cache
        $this->addCorsHeaders( false );

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

    /**
     * @param bool $createIfNull If true, the default, the response object will be created if it hasn't already
     * @param bool $sendHeaders
     *
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResponseObject( $createIfNull = true, $sendHeaders = true )
    {
        if ( null === $this->_responseObject && $createIfNull )
        {
            $this->_responseObject = Response::create();

            if ( $this->_autoAddHeaders )
            {
                $this->addCorsHeaders( array(), false, $sendHeaders );
            }
        }

        return $this->_responseObject;
    }

    /**
     * @param Request $requestObject
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
        return $this->_requestObject = $this->_requestObject ?: Request::createFromGlobals();
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
        static::$_namespaceMap[NamespaceTypes::RESOURCES] = $resourceNamespaces;

        return $this;
    }

    /**
     * @return array
     */
    public function getResourceNamespaces()
    {
        return static::$_namespaceMap[NamespaceTypes::RESOURCES];
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
        static::_mapNamespace( NamespaceTypes::RESOURCES, $namespace, $path, $prepend );
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
        static::$_namespaceMap[NamespaceTypes::MODELS] = $modelNamespaces;

        return $this;
    }

    /**
     * @return array
     */
    public function getModelNamespaces()
    {
        return static::$_namespaceMap[NamespaceTypes::MODELS];
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
        static::_mapNamespace( NamespaceTypes::MODELS, $namespace, $path, $prepend );

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
        if ( null === ( $_loader = Pii::appStoreGet( CoreSettings::AUTO_LOADER ) ) )
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
            array_unshift( static::$_namespaceMap[$which], array($namespace, $path) );
        }
        else
        {
            static::$_namespaceMap[$which][$namespace] = $path;
        }
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
    }

    /**
     * @return boolean
     */
    public function getUseResponseObject()
    {
        return $this->_useResponseObject;
    }

    /**
     * @param boolean $useResponseObject
     *
     * @return PlatformWebApplication
     */
    public function setUseResponseObject( $useResponseObject )
    {
        $this->_useResponseObject = $useResponseObject;

        return $this;
    }

    /**
     * @return array
     */
    public function getRequestBody()
    {
        return $this->_requestBody;
    }

    /**
     * @return \CDbCache
     */
    protected function _appCache()
    {
        if ( !$this->_cache )
        {
            //  Set up our cache...
            $this->_cache = Pii::getAppStore();
        }

        return $this->_cache;
    }
}
