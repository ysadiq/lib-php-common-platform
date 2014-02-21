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

use DreamFactory\Platform\Events\DspEvent;
use DreamFactory\Platform\Events\Enums\DspEvents;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Interfaces\EventPublisherLike;
use DreamFactory\Platform\Utility\EventManager;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\CoreSettings;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Events\SeedEvent;
use Kisma\Core\Interfaces\PublisherLike;
use Kisma\Core\Interfaces\SubscriberLike;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PlatformWebApplication
 */
class PlatformWebApplication extends \CWebApplication implements PublisherLike, SubscriberLike, EventSubscriberInterface
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
	 * @var string The default DSP resource namespace
	 */
	const DEFAULT_RESOURCE_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Resource';
	/**
	 * @var string The default DSP model namespace
	 */
	const DEFAULT_MODEL_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Yii\\Models';
	/**
	 * @var string The default path (sub-path) of installed plug-ins
	 */
	const DEFAULT_PLUGINS_PATH = '/storage/plugins';


	//*************************************************************************
	//	Members
	//*************************************************************************

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
	/**
	 * @var bool If true, profiling information is output to the log
	 */
	protected static $_profilerEnabled = null;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param array $config
	 */
	public function __construct( $config = null )
	{
		static::$_profilerEnabled = static::$_profilerEnabled ? : \Kisma::get( CoreSettings::DEBUG );

		//	Start the full-cycle timer
		$this->startProfiler();

		parent::__construct( $config );
	}

	/**
	 * Destruction
	 */
	public function __destruct()
	{
		Log::debug( 'DSP [web-app] elapsed time: ' . $this->stopProfiler() );
	}

	/**
	 * Start a timer
	 *
	 * @return $this
	 */
	public function startProfiler()
	{
		if ( static::$_profilerEnabled )
		{
			\PHP_Timer::start();
		}

		return $this;
	}

	/**
	 * Stop last timer
	 *
	 * @param bool $returnTimeString
	 *
	 * @return float
	 */
	public function stopProfiler( $returnTimeString = true )
	{
		$_time = 0;

		if ( static::$_profilerEnabled )
		{
			$_time = \PHP_Timer::stop();
		}

		return $returnTimeString ? \PHP_Timer::secondsToTimeString( $_time ) : $_time;
	}

	/**
	 * Initialize
	 */
	protected function init()
	{
		parent::init();

		//	Get CORS data from config file
		$_config = Pii::getParam( 'storage_base_path' ) . static::CORS_DEFAULT_CONFIG_FILE;

		if ( !file_exists( $_config ) )
		{
			// old location
			$_config = Pii::getParam( 'private_path' ) . static::CORS_DEFAULT_CONFIG_FILE;
		}

		if ( file_exists( $_config ) )
		{
			$_allowedHosts = array();
			$_content = @file_get_contents( $_config );

			if ( !empty( $_content ) )
			{
				$_allowedHosts = json_decode( $_content, true );
			}

			$this->setCorsWhitelist( $_allowedHosts );
		}

		//	Setup the request handler and events
		$this->onBeginRequest = array( $this, '_onBeginRequest' );
		$this->onEndRequest = array( $this, '_onEndRequest' );

		//	Create our HTTP objects
		$this->_requestObject = Request::createFromGlobals();
		$this->_responseObject = Response::create();
	}

	/**
	 * Returns an array of event names this subscriber wants to listen to.
	 *
	 * The array keys are event names and the value can be:
	 *
	 *  * The method name to call (priority defaults to 0)
	 *  * An array composed of the method name to call and the priority
	 *  * An array of arrays composed of the method names to call and respective
	 *    priorities, or 0 if unset
	 *
	 * For instance:
	 *
	 *  * array('eventName' => 'methodName')
	 *  * array('eventName' => array('methodName', $priority))
	 *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
	 *
	 * @return array The event names to listen to
	 *
	 * @api
	 */
	public static function getSubscribedEvents()
	{
		return array();
	}

	/**
	 * Handles an OPTIONS request to the server to allow CORS and optionally sends the CORS headers
	 *
	 * @param \CEvent $event
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _onBeginRequest( \CEvent $event )
	{
		//	Start the request-only profile
		$this->startProfiler();

		switch ( $this->_requestObject->getMethod() )
		{
			case HttpMethod::Trace:
				Log::error(
				   'HTTP TRACE received!',
				   array(
					   'server'  => $this->_requestObject->server->all(),
					   'request' => $this->_requestObject->request->all()
				   )
				);
				throw new BadRequestException();

			case HttpMethod::Options:
				$this->_responseObject->setStatusCode( HttpResponse::NoContent );
				$this->_responseObject->headers->add( array( 'content-type' => 'text/plain' ) );
				$this->_responseObject->headers->add( $this->addCorsHeaders( null, true ) );
				$this->_responseObject->send();
				Pii::end( HttpResponse::NoContent );

				return;

			default:
				//	Auto-add the CORS headers...
				if ( $this->_autoAddHeaders )
				{
					$this->_responseObject->headers->add( $this->addCorsHeaders( null, true ) );
				}
				break;
		}

		//	Load any plug-ins
		$this->_loadPlugins();

		//	Trigger request event
		$this->trigger( DspEvents::BEFORE_REQUEST );
	}

	/**
	 * @param \CEvent $event
	 */
	protected function _onEndRequest( \CEvent $event )
	{
		$this->trigger( DspEvents::AFTER_REQUEST );

		//	Send the response
		if ( !headers_sent() && $this->_responseObject )
		{
			if ( strlen( $this->_responseObject->getContent() ) )
			{
				$this->_responseObject->send();
			}
		}

		if ( static::$_profilerEnabled )
		{
			Log::debug( 'DSP [request] elapsed time: ' . $this->stopProfiler() );
		}
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

		$_origin = trim( Option::server( 'HTTP_ORIGIN' ) );

		//	Was an origin header passed? If not, don't do CORS.
		if ( empty( $_origin ) )
		{
			return $returnHeaders ? array() : true;
		}

		$_originUri = null;
		$_requestSource = $_SERVER['SERVER_NAME'];

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
				$this->_responseObject->setStatusCode( HttpResponse::Forbidden );
				$this->_responseObject->send();
				Pii::end( HttpResponse::Forbidden );

				//	If end fails for some unknown impossible reason...
				return false;
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

		if ( $this->_extendedHeaders )
		{
			$_headers['X-DreamFactory-Source'] = $_requestSource;

			if ( $_origin )
			{
				$_headers['X-DreamFactory-Origin-Whitelisted'] = preg_match( '/^([\w_-]+\.)*' . $_requestSource . '$/', $_originUri );
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
			return $_headers;
		}

		//	Dump the headers
		foreach ( $_headers as $_key => $_value )
		{
			header( $_key . ': ' . $_value );
		}

		return true;
	}

	/**
	 * Loads up any plug-ins configured
	 *
	 * @return bool
	 */
	protected function _loadPlugins()
	{
		if ( null === ( $_autoloadPath = Pii::getState( 'dsp.plugin_autoload_path' ) ) )
		{
			//	Locate plug-in directory...
			$_path = Pii::getParam( 'dsp.plugins_path', Pii::getParam( 'dsp.base_path' ) . static::DEFAULT_PLUGINS_PATH );

			if ( !is_dir( $_path ) )
			{
//				Log::debug( 'No plug-ins installed.' );

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

			Pii::setState( 'dsp.plugin_autoload_path', $_autoloadPath );
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

		$_protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ) ? 'https' : 'http';

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
	 * @param array $resourceNamespaces
	 *
	 * @return PlatformWebApplication
	 */
	public function setResourceNamespaces( $resourceNamespaces )
	{
		$this->_resourceNamespaces = $resourceNamespaces;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getResourceNamespaces()
	{
		return $this->_resourceNamespaces;
	}

	/**
	 * @param string|array $namespace
	 * @param bool         $prepend If true, the namespace(s) will be placed at the beginning of the list
	 *
	 * @return PlatformWebApplication
	 */
	public function addResourceNamespace( $namespace, $prepend = false )
	{
		foreach ( Option::clean( $namespace ) as $_entry )
		{
			if ( !in_array( $_entry, $this->_resourceNamespaces ) )
			{
				if ( false === $prepend )
				{
					$this->_resourceNamespaces[] = $_entry;
					continue;
				}

				array_unshift( $this->_resourceNamespaces, $_entry );
			}
		}

		return $this;
	}

	/**
	 * @param array $modelNamespaces
	 *
	 * @return PlatformWebApplication
	 */
	public function setModelNamespaces( $modelNamespaces )
	{
		$this->_modelNamespaces = $modelNamespaces;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getModelNamespaces()
	{
		return $this->_modelNamespaces;
	}

	/**
	 * @param string|array $namespace
	 * @param bool         $prepend If true, the namespace(s) will be placed at the beginning of the list
	 *
	 * @return PlatformWebApplication
	 */
	public function addModelNamespace( $namespace, $prepend = false )
	{
		foreach ( Option::clean( $namespace ) as $_entry )
		{
			if ( !in_array( $_entry, $this->_modelNamespaces ) )
			{
				if ( false === $prepend )
				{
					$this->_modelNamespaces[] = $_entry;
					continue;
				}

				array_unshift( $this->_modelNamespaces, $_entry );
			}
		}

		return $this;
	}

	/**
	 * @param string    $eventName
	 * @param SeedEvent $event
	 *
	 * @return DspEvent
	 */
	public function trigger( $eventName, $event = null )
	{
		$_event = $event ? : new DspEvent( $this, $this->_requestObject, $this->_responseObject );

		return EventManager::trigger( $eventName, $_event );
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
		EventManager::on( $eventName, $listener, $priority );
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
		EventManager::off( $eventName, $listener );
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
	 * @param \Symfony\Component\HttpFoundation\Response $responseObject
	 *
	 * @return PlatformWebApplication
	 */
	public function setResponseObject( $responseObject )
	{
		$this->_responseObject = $responseObject;

		return $this;
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function getResponseObject()
	{
		return $this->_responseObject;
	}

	/**
	 * @param boolean $profilerEnabled
	 */
	public static function setProfilerEnabled( $profilerEnabled )
	{
		static::$_profilerEnabled = $profilerEnabled;
	}

	/**
	 * @return boolean
	 */
	public static function getProfilerEnabled()
	{
		return static::$_profilerEnabled;
	}
}