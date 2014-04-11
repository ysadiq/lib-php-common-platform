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
use DreamFactory\Platform\Events\DspEvent;
use DreamFactory\Platform\Events\Enums\DspEvents;
use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\CoreSettings;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Interfaces\PublisherLike;
use Kisma\Core\Interfaces\SubscriberLike;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PlatformConsoleApplication
 */
class PlatformConsoleApplication extends \CConsoleApplication implements PublisherLike, SubscriberLike
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
	protected static $_namespaceMap = array( self::NS_MODELS => array(), self::NS_SERVICES => array(), self::NS_RESOURCES => array() );
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
	 * @var  Response
	 */
	protected $_responseObject;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Initialize
	 */
	protected function init()
	{
		parent::init();

		$this->_loadCorsConfig();

		//	Debug options
		static::$_enableProfiler = Pii::getParam( 'dsp.enable_profiler', false );

		//	Setup the request handler and events
		$this->onBeginRequest = array( $this, '_onBeginRequest' );
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
	 */
	public function stopProfiler( $id = __CLASS__, $prettyPrint = true )
	{
		if ( static::$_enableProfiler )
		{
			Log::debug( '~~ "' . $id . '" profile: ' . Profiler::stop( 'app.request', $prettyPrint ) );
		}
	}

	/**
	 * Triggers a DSP-level event
	 *
	 * @param string        $eventName
	 * @param PlatformEvent $event
	 *
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

	/**
	 * @param array|bool $whitelist Set to "false" to reset the internal method cache.
	 * @param bool       $returnHeaders
	 *
	 * @return bool|array
	 */
	public function addCorsHeaders( $whitelist = array(), $returnHeaders = false )
	{
		static $_cache = array();
		static $_cacheVerbs = array();

		$_headers = array();

		//	Reset the cache before processing...
		if ( false === $whitelist )
		{
			$_cache = array();
			$_cacheVerbs = array();

			return true;
		}

		$_originUri = null;
		$_origin = trim( $this->_requestObject->headers->get( 'origin' ) );

		//	Was an origin header passed? If not, don't do CORS.
		if ( empty( $_origin ) )
		{
			return $returnHeaders ? array() : true;
		}

		$_originUri = null;
		$_requestSource = Option::server( 'SERVER_NAME', gethostname() );

		if ( false === ( $_originParts = $this->_parseUri( $_origin ) ) )
		{
			//	Not parse-able, set to itself, check later (testing from local files - no session)?
			Log::warning( 'Unable to parse received origin: [' . $_origin . ']' );
			$_originParts = $_origin;
		}

		$_originUri = $this->_normalizeUri( $_originParts );
		$_key = sha1( $_requestSource . $_originUri );

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
				$this->_responseObject->setStatusCode( HttpResponse::Forbidden )->send();

				return Pii::end( HttpResponse::Forbidden );
			}

			// Commit origin to the CORS cache
			$_cache[$_key] = $_originUri;
			$_cacheVerbs[$_key] = $_allowedMethods;
		}
		else
		{
			$_originUri = $_cache[$_key];
			$_allowedMethods = Option::getDeep( $_cacheVerbs, $_key, 'allowed_methods' );
			$_headers = Option::getDeep( $_cacheVerbs, $_key, 'headers' );
		}

		if ( !empty( $_originUri ) )
		{
			$this->_responseObject->headers->set( 'Access-Control-Allow-Origin', $_originUri );
		}

		$this->_responseObject->headers->set( 'Access-Control-Allow-Credentials', 'true' );
		$this->_responseObject->headers->set( 'Access-Control-Allow-Headers', static::CORS_DEFAULT_ALLOWED_HEADERS );
		$this->_responseObject->headers->set( 'Access-Control-Allow-Methods', $_allowedMethods );
		$this->_responseObject->headers->set( 'Access-Control-Max-Age', static::CORS_DEFAULT_MAX_AGE );

		if ( $this->_extendedHeaders )
		{
			$this->_responseObject->headers->set( 'X-DreamFactory-Source', $_requestSource );

			if ( $_origin )
			{
				$this->_responseObject->headers->set(
					'X-DreamFactory-Origin-Whitelisted',
					preg_match( '/^([\w_-]+\.)*' . $_requestSource . '$/', $_originUri )
				);
			}
		}

		//	Store in cache...
		$_cache[$_key] = $_originUri;
		$_cacheVerbs[$_key] = array(
			'allowed_methods' => $_allowedMethods,
			'headers'         => $_headers
		);

		if ( $returnHeaders )
		{
			return $this->_responseObject->headers->all();
		}

		return true;
	}

	/**
	 * Handles an OPTIONS request to the server to allow CORS and optionally sends the CORS headers
	 *
	 * @param \CEvent $event
	 *
	 * @return bool
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _onBeginRequest( \CEvent $event )
	{
		//	Start the request-only profile
		if ( static::$_enableProfiler )
		{
			Profiler::start( 'app.request' );
		}

		$this->_requestObject = Request::createFromGlobals();
		$_response = Response::create();

		//	Load any plug-ins
		$this->_loadPlugins();

		switch ( $this->_requestObject->getMethod() )
		{
			//	OPTIONS goooooooooood!!!!!
			case HttpMethod::OPTIONS:
				$this->addCorsHeaders();
				$_response->setStatusCode( HttpResponse::NoContent )->send();

				return Pii::end( HttpResponse::NoContent );

			//	TRACE baaaaaadddddddddd!!!!!
			case HttpMethod::TRACE:
				Log::error(
					'HTTP TRACE received!',
					array(
						'server'  => $this->_requestObject->server->all(),
						'request' => $this->_requestObject->request->all()
					)
				);

				throw new BadRequestException();
		}

		//	Save to object and add headers
		$this->setResponseObject( $_response );

		//	Trigger request event
		$this->trigger( DspEvents::BEFORE_REQUEST );
	}

	/**
	 * @param \CEvent $event
	 */
	protected function _onEndRequest( \CEvent $event )
	{
		$this->trigger( DspEvents::AFTER_REQUEST );
		$this->stopProfiler( 'app.request' );
	}

	/**
	 * Loads up any plug-ins configured
	 *
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
			Log::error( 'Error reading plug-in autoload.php file. Some plug-ins may not function properly.' );

			return false;
		}

		$this->trigger( DspEvents::PLUGINS_LOADED );

		return true;
	}

	/**
	 * @param string|array $origin     The parse_url value of origin
	 * @param array        $additional Additional origins to allow
	 *
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
	 * @return array
	 */
	protected function _parseUri( $uri, $normalize = false )
	{
		$_uri = array(
			'scheme' => $this->_requestObject->getScheme(),
			'host'   => $this->_requestObject->getHttpHost(),
			'port'   => $this->_requestObject->getPort(),
			'path'   => $this->_requestObject->getPathInfo(),
		);

		if ( !( isset( $_uri['host'] ) || isset( $_uri['path'] ) ) )
		{
			return false;
		}

		if ( isset( $_uri['path'] ) && !isset( $_uri['host'] ) )
		{
			//	Special case, handle this generically later
			if ( 'null' == $_uri['path'] )
			{
				return 'null';
			}

			$_uri['host'] = $_uri['path'];

			unset( $_uri['path'] );
		}

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
	 * @return $this
	 */
	protected function _loadCorsConfig()
	{
		$_list = $this->_corsWhitelist;

		if ( false !== $_list && ( !is_array( $_list ) || null === ( $_list = \Kisma::get( static::CORS_WHITELIST_KEY ) ) ) )
		{
			//	Get CORS data from config file
			$_config = Pii::getParam( 'storage_base_path' ) . static::CORS_DEFAULT_CONFIG_FILE;

			if ( file_exists( $_config ) )
			{
				$_list = @json_decode( file_get_contents( $_config ), true );

				if ( empty( $_list ) )
				{
					\Kisma::set( static::CORS_WHITELIST_KEY, false );
					Log::error( 'Found CORS configuration, but contents invalid: ' . print_r( $_list, true ) );

					return $this;
				}
			}
			else
			{
				//	Check the old location
				$_oldConfig = Pii::getParam( 'private_path' ) . static::CORS_DEFAULT_CONFIG_FILE;

				//	Nada? Bail...
				if ( !file_exists( $_oldConfig ) )
				{
					\Kisma::set( static::CORS_WHITELIST_KEY, false );

					return $this;
				}

				if ( false === ( $_json = @json_decode( @file_get_contents( $_oldConfig ), true ) ) )
				{
					\Kisma::set( static::CORS_WHITELIST_KEY, false );
					Log::error( 'Found CORS configuration in old location, but contents invalid: ' . print_r( $_json, true ) );

					return $this;
				}

				if ( false === @file_put_contents( $_config, json_encode( $_json ) ) )
				{
					\Kisma::set( static::CORS_WHITELIST_KEY, false );
					Log::error( 'Error moving CORS configuration file to new location.' );

					return $this;
				}

				//	Final step, remove old configuration file...
				if ( false === @unlink( $_oldConfig ) )
				{
					\Kisma::set( static::CORS_WHITELIST_KEY, false );
					Log::error( 'File system error removing CORS configuration file from old location. Ignoring' );

					return $this;
				}

				//	Migration complete...
				Log::info( 'CORS configuration file migrated from old location.' );
				$_list = $_json;
			}
		}

		if ( $_list )
		{
			$this->setCorsWhitelist( $_list );
		}

		\Kisma::set( static::CORS_WHITELIST_KEY, $_list );

		return $this;
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
	 * @param array $corsWhitelist
	 *
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
	 * @return PlatformConsoleApplication
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
	 * @return PlatformConsoleApplication
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
	 * @return \Symfony\Component\HttpFoundation\Request
	 */
	public function getRequestObject()
	{
		return $this->_requestObject ? : $this->_requestObject = Request::createFromGlobals();
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function getResponseObject()
	{
		if ( null === $this->_responseObject )
		{
			$this->setResponseObject( Response::create() );
		}

		return $this->_responseObject;
	}

	/**
	 * @param \Symfony\Component\HttpFoundation\Response $responseObject
	 *
	 * @return PlatformWebApplication
	 */
	public function setResponseObject( Response $responseObject )
	{
		$this->_responseObject = $responseObject;

		//	Auto-add the CORS headers...
		if ( $this->_autoAddHeaders )
		{
			$this->addCorsHeaders();
		}

		return $this;
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
	 * @throws InternalServerErrorException
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
			static::$_dispatcher->setLogEvents( Pii::getParam( 'dsp.log_events', false ) );
            static::$_dispatcher->setLogAllEvents( Pii::getParam( 'dsp.log_all_events', false ) );
		}

		return static::$_dispatcher;
	}
}