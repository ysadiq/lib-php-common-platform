<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
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
namespace DreamFactory\Platform\Components;

use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Platform\Events\RestServiceEvent;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Yii\Utility\Pii;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\MultiTransferException;
use Kisma\Core\Exceptions\EventException;
use Kisma\Core\Utility\Convert;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * ActionEventManager
 * Dispatches action events
 */
class ActionEventManager extends EventDispatcher
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @type string
	 */
	const DEFAULT_USER_AGENT = 'DreamFactory/SSE_1.0';
	/**
	 * @type string The persistent storage key for subscribed events
	 */
	const SUBSCRIBED_EVENTS_KEY = 'platform.events.subscriber_map';

	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var EventDispatcher Our dispatcher instance
	 */
	protected static $_dispatcher = null;
	/**
	 * @var bool Will log events if true
	 */
	protected static $_logEvents = false;
	/**
	 * @var array The list of events to which we subscribe and their associated handlers.
	 */
	protected static $_subscribedEvents = array();
	/**
	 * @var array The map of real listeners
	 */
	protected static $_listenerMap = array();
	/**
	 * @var Client
	 */
	protected static $_client = null;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param array $subscribedEvents
	 */
	public function __construct( $subscribedEvents = null )
	{
		//	Load cached subscribers
		static::$_subscribedEvents = $subscribedEvents ? : \Kisma::get( static::SUBSCRIBED_EVENTS_KEY, array() );

		$_events = ResourceStore::model( 'event' )->findAll();

		if ( !empty( $_events ) )
		{
			foreach ( $_events as $_event )
			{
				static::$_subscribedEvents[$_event->event_name] = $_event->handlers;
				unset( $_event );
			}

			unset( $_events );
		}

		foreach ( static::$_subscribedEvents as $_eventName => $_listeners )
		{
			foreach ( $_listeners as $_listener )
			{
				$this->addListener( $_eventName, $_listener );
			}
		}
	}

	/**
	 * Destructor
	 */
	public function __destruct()
	{
		\Kisma::set( static::SUBSCRIBED_EVENTS_KEY, static::$_subscribedEvents );

		if ( !empty( static::$_subscribedEvents ) )
		{
			foreach ( static::$_subscribedEvents as $_eventName => $_handlers )
			{
				ResourceStore::model( 'event' )->upsert(
					array( 'event_name' => $_eventName, 'handlers' => $_handlers )
				);
			}
		}

	}

	/**
	 * @param string $eventName
	 * @param string $listener The URL to notify
	 * @param int    $priority
	 *
	 * @throws \InvalidArgumentException
	 * @return bool|void
	 */
	public function addListener( $eventName, $listener, $priority = 0 )
	{
		if ( !is_callable( $listener ) && ( is_string( $listener ) && !(bool)@parse_url( $listener ) ) )
		{
			throw new \InvalidArgumentException( 'The supplied $listener is not a valid URL or closure.' );
		}

		if ( static::$_logEvents )
		{
			Log::debug( 'Registration request for "' . $eventName . '": ' . ( is_callable( $listener ) ? '(callable)' : $listener ) );
		}

		parent::addListener( $eventName, $listener, $priority );
	}

	/**
	 * @param \callable[]                                    $listeners
	 * @param string                                         $eventName
	 * @param \DreamFactory\Platform\Events\RestServiceEvent $event
	 *
	 * @throws \Kisma\Core\Exceptions\EventException
	 * @throws \InvalidArgumentException
	 * @return bool|void
	 */
	protected function doDispatch( $listeners, $eventName, RestServiceEvent $event )
	{
		if ( static::$_logEvents )
		{
			Log::debug(
				'/' . $event->getApiName() . '/' . $event->getResource() . ' triggered event: ' . $eventName
			);
		}

		if ( empty( $listeners ) )
		{
			return false;
		}

		static::$_client = static::$_client ? : new Client();
		static::$_client->setUserAgent( static::DEFAULT_USER_AGENT );

		$_event = json_encode( Convert::toArray( $event ), JSON_UNESCAPED_SLASHES );

		if ( JSON_ERROR_NONE != json_last_error() )
		{
			throw new EventException( 'The event data appears corrupt.' );
		}

		$_payload = $this->_envelope( $_event );

		//	Queue up the posts
		$_posts = array();

		foreach ( $listeners as $_listener )
		{
			if ( is_callable( $_listener ) )
			{
				call_user_func( $_listener, $event, $eventName, $this );
			}
			else if ( is_string( $_listener ) )
			{
				$_post = static::$_client->post( $_listener );
				$_post->setResponseBody( $_payload, 'application/json' );

				$_posts[] = $_post;
			}
			else
			{
				throw new \InvalidArgumentException( 'Invalid listener for action event "' . $eventName . '"' );
			}

			if ( $event->isPropagationStopped() )
			{
				break;
			}
		}

		try
		{
			//	Send the posts all at once
			static::$_client->send( $_posts );
		}
		catch ( MultiTransferException $_exceptions )
		{
			/** @var \Exception $_exception */
			foreach ( $_exceptions as $_exception )
			{
				Log::error( '  * Action event exception: ' . $_exception->getMessage() );
			}

			foreach ( $_exceptions->getFailedRequests() as $_request )
			{
				Log::error( '  * Dispatch Failure: ' . $_request );
			}

			foreach ( $_exceptions->getSuccessfulRequests() as $_request )
			{
				Log::debug( '  * Dispatch success: ' . $_request );
			}
		}
	}

	/**
	 * Creates a JSON encoded array (as a string) with a standard REST response. Override to provide
	 * a different response format.
	 *
	 * @param array   $resultList
	 * @param boolean $isError
	 * @param string  $errorMessage
	 * @param integer $errorCode
	 * @param array   $additionalInfo
	 *
	 * @return string JSON encoded array
	 */
	protected function _envelope( $resultList = null, $isError = false, $errorMessage = 'failure', $errorCode = 0, $additionalInfo = array() )
	{
		if ( $isError )
		{
			$_info = array(
				'error_code'    => $errorCode,
				'error_message' => $errorMessage,
			);

			if ( !empty( $additionalInfo ) )
			{
				$_info = array_merge( $additionalInfo, $_info );
			}

			return $this->_buildContainer( false, $resultList, $_info );
		}

		return $this->_buildContainer( true, $resultList );
	}

	/**
	 * Builds a v2 response container
	 *
	 * @param bool  $success
	 * @param mixed $details   Additional details/data/payload
	 * @param array $extraInfo Additional data to add to the _info object
	 *
	 * @return array
	 */
	protected function _buildContainer( $success = true, $details = null, $extraInfo = null )
	{
		$_id = sha1(
			Option::server( 'REQUEST_TIME_FLOAT', microtime( true ) ) .
			Option::server( 'HTTP_HOST', $_host = gethostname() ) .
			Option::server( 'REMOTE_ADDR', gethostbyname( $_host ) )
		);

		$_ro = Pii::app()->getRequestObject();

		$_container = array(
			'success' => $success,
			'details' => $details,
			'_info'   => array_merge(
				array(
					'id'        => $_id,
					'timestamp' => date( 'c', $_start = $_SERVER['REQUEST_TIME'] ),
					'elapsed'   => (float)number_format( microtime( true ) - $_start, 4 ),
					'verb'      => $_ro->getMethod(),
					'uri'       => $_ro->server->get( 'request-uri' ),
					'signature' => base64_encode( hash_hmac( 'sha256', $_id, $_id, true ) ),
				),
				Option::clean( $extraInfo )
			),
		);

		return $_container;
	}

	/**
	 * @return $this
	 */
	protected static function _getDispatcher()
	{
		if ( empty( static::$_dispatcher ) )
		{
			static::setLogEvents( Pii::getParam( 'dsp.log_events' ) );
			static::$_dispatcher = new EventDispatcher();

			foreach ( static::$_subscribedEvents as $_eventName => $_handlers )
			{
				foreach ( $_handlers as $_listener )
				{
					static::$_dispatcher->addListener( $_eventName, $_listener );
				}
			}
		}

		return static::$_dispatcher;
	}

	/**
	 * Triggers an event to the listener chain
	 *
	 * It returns boolean value based on prevented default flag from the event. If default was prevented
	 * then (bool) false will be returned, otherwise (bool) true. This is so that you can do something like this:
	 *
	 *     if (!$eventManager->trigger(Event::getName())) {
	 *         echo 'breaking default behavior';
	 *         return;
	 *     }
	 *     echo 'continue with default behavior';
	 *
	 * @param string                                      $eventName Updated with actual event name used
	 * @param \DreamFactory\Platform\Events\PlatformEvent $event
	 * @param array                                       $values    Optional replacement values for dynamic event names
	 *
	 * @return \Symfony\Component\EventDispatcher\Event
	 */
	public static function trigger( $eventName, PlatformEvent $event = null, $values = array() )
	{
		if ( empty( $event ) )
		{
			$event = new PlatformEvent( $values );
		}

		return static::_getDispatcher()->dispatch( $eventName, $event );
	}

	/**
	 * Registers a new callback for an event
	 *
	 * @param string   $eventName The event to listen for
	 * @param callable $listener  The listener
	 * @param string   $apiKey    The API key of the requesting app
	 * @param integer  $priority  The higher this value, the earlier an event listener will be triggered in the chain (defaults to 0)
	 *
	 * @return
	 */
	public static function on( $eventName, $listener, $apiKey, $priority = 0 )
	{
		//@todo implement api key validation
//		static::_validateApiKey($apiKey);

		return static::_getDispatcher()->addListener(
			$eventName,
			$listener,
			$priority
		);
	}

	/**
	 * Removes an event listener from the specified events.
	 *
	 * @param string|array $eventName The event(s) to remove a listener from
	 * @param callable     $listener  The listener to remove
	 * @param string       $apiKey    The requesting
	 *
	 * @return
	 */
	public static function off( $eventName, $listener, $apiKey )
	{
		//@todo implement api key validation
//		static::_validateApiKey($apiKey);

		return static::_getDispatcher()->removeListener(
			$eventName,
			$listener
		);
	}

	/**
	 * @param boolean $logEvents
	 */
	public static function setLogEvents( $logEvents )
	{
		static::$_logEvents = $logEvents;
	}

	/**
	 * @return boolean
	 */
	public static function getLogEvents()
	{
		return static::$_logEvents;
	}

}
