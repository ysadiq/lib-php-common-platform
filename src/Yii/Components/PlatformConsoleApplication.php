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
use Kisma\Core\Exceptions\FileSystemException;
use Kisma\Core\Utility\FileSystem;
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
     * @var string The HTTP Option method
     */
    const CORS_OPTION_METHOD = 'OPTIONS';
    /**
     * @var string The allowed HTTP methods
     */
    const CORS_DEFAULT_ALLOWED_METHODS = 'GET, POST, PUT, DELETE, PATCH, MERGE, COPY, OPTIONS';
    /**
     * @var string The allowed HTTP headers
     * Tunnelling verb overrides: X-HTTP-Method (Microsoft), X-HTTP-Method-Override (Google/GData), X-METHOD-OVERRIDE (IBM)
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
    protected static $_namespaceMap = array(NamespaceTypes::MODELS => array(), NamespaceTypes::SERVICES => array(), NamespaceTypes::RESOURCES => array());
    /**
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
     * @var bool If true, CORS info will be logged
     */
    protected $_logCorsInfo = false;

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
        static::$_enableProfiler = Pii::getParam( 'dsp.enable_profiler', false );

        //	Setup the request handler and events
        $this->onBeginRequest = array($this, '_onBeginRequest');
        $this->onEndRequest = array($this, '_onEndRequest');

        //  Load any user config files...
        $this->_loadLocalConfig();
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
        if ( null === ( $_autoloadPath = Platform::storeGet( 'dsp.plugin_autoload_path' ) ) )
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

            Platform::storeSet( 'dsp.plugin_autoload_path', $_autoloadPath );
        }

        /** @noinspection PhpIncludeInspection */
        if ( false === @require( $_autoloadPath ) )
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
     * @throws BadRequestException
     * @return bool
     */
    protected function _onBeginRequest( \CEvent $event )
    {
        //	Start the request-only profile
        $this->startProfiler( 'app.request' );

        $this->_requestObject = Request::createFromGlobals();

        //  A pristine copy of the request
        $this->_requestBody = ScriptEvent::buildRequestArray();

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

    /**
     * Loads any local configuration files
     */
    protected function _loadLocalConfig()
    {
        $_config = Platform::storeGet( 'platform.local_config' );

        if ( empty( $_config ) )
        {
            $_config = array();
            $_configPath = Platform::getPrivatePath( '/config' );

            $_files = FileSystem::glob( $_configPath . static::DEFAULT_LOCAL_CONFIG_PATTERN, GlobFlags::GLOB_NODIR | GlobFlags::GLOB_NODOTS );

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

            Platform::storeSet( 'platform.local_config', $_config );
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
     * @return DspEvents
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
    //	Accessors
    //*************************************************************************

    /**
     * @param array $corsWhitelist
     *
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @return PlatformWebApplication
     */
    public function setCorsWhitelist( $corsWhitelist )
    {
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
        if ( null === ( $_loader = Platform::storeGet( CoreSettings::AUTO_LOADER ) ) )
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
    }
}