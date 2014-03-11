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
namespace DreamFactory\Platform\Events;

use DreamFactory\Platform\Services\BasePlatformRestService;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Yii\Utility\Pii;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\MultiTransferException;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EventDispatcher
 */
class EventDispatcher implements EventDispatcherInterface
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
	 * @var bool Will log events if true
	 */
	protected static $_logEvents = false;
	/**
	 * @var Client
	 */
	protected static $_client = null;
	/**
	 * @var BasePlatformRestService
	 */
	protected $_service;
	/**
	 * @var array
	 */
	protected $_listeners = array();
	/**
	 * @var array
	 */
	protected $_sorted = array();

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Load any stored events
	 */
	public function __construct()
	{
		try
		{
			/** @var \DreamFactory\Platform\Yii\Models\Event[] $_events */
			$_model = ResourceStore::model( 'event' );

			if ( is_object( $_model ) )
			{
				$_events = $_model->findAll();

				if ( !empty( $_events ) )
				{
					foreach ( $_events as $_event )
					{
						$this->_listeners[$_event->event_name] = $_event->listeners;
						unset( $_event );
					}

					unset( $_events );
				}
			}
		}
		catch ( \Exception $_ex )
		{
			Log::notice( 'Event system unavailable at this time.' );
		}
	}

	/**
	 * Destruction
	 */
	public function __destruct()
	{
		//	Store any events
		if ( !Pii::guest() )
		{
			foreach ( array_keys( $this->_listeners ) as $_eventName )
			{
				/** @var Event $_model */
				$_model = ResourceStore::model( 'event' )->byEventName( $_eventName )->find();

				if ( null === $_model )
				{
					/** @var \DreamFactory\Platform\Yii\Models\Event $_model */
					$_model = ResourceStore::model( 'event' );
					$_model->setIsNewRecord( true );
					$_model->event_name = $_eventName;
				}

				$_model->listeners = $this->_listeners[$_eventName];

				try
				{
					$_model->save();
				}
				catch ( \Exception $_ex )
				{
					Log::error( 'Exception saving event configuration: ' . $_ex->getMessage() );
				}
			}
		}
	}

	/**
	 * @param string $eventName
	 * @param Event  $event
	 *
	 * @return \Symfony\Component\EventDispatcher\Event|void
	 */
	public function dispatch( $eventName, Event $event = null )
	{
		$this->_doDispatch( $event ? : new PlatformEvent( $eventName ), $eventName, $this );
	}

	/**
	 * @param BasePlatformRestService                        $service
	 * @param string                                         $eventName
	 * @param \DreamFactory\Platform\Events\RestServiceEvent $event
	 */
	public function dispatchRestServiceEvent( $service, $eventName, RestServiceEvent $event = null )
	{
		$this->_doDispatch( $event ? : new RestServiceEvent( $eventName, $service->getApiName(), $service->getResource() ), $eventName, $this );
	}

	/**
	 * @param string                                 $eventName
	 * @param \DreamFactory\Platform\Events\DspEvent $event
	 */
	public function dispatchDspEvent( $eventName, DspEvent $event = null )
	{
		$this->_doDispatch( $event ? : new DspEvent(), $eventName, $this );
	}

	/**
	 * @param DspEvent|RestServiceEvent|\DreamFactory\Platform\Events\PlatformEvent $event
	 * @param string                                                                $eventName
	 * @param EventDispatcher                                                       $dispatcher
	 *
	 * @throws EventException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @return bool
	 */
	protected function _doDispatch( $event, $eventName, $dispatcher )
	{
		//	Queue up the posts
		$_posts = array();
		$_dispatched = true;

		if ( empty( $this->_listeners[$eventName] ) )
		{
			return false;
		}

		foreach ( $this->getListeners( $eventName ) as $_listener )
		{
			if ( !is_string( $_listener ) && is_callable( $_listener ) )
			{
				call_user_func( $_listener, $event, $eventName, $dispatcher );
			}
			elseif ( $this->isPhpScript( $_listener ) )
			{
				$_className = substr( $_listener, 0, strpos( $_listener, '::' ) );
				$_methodName = substr( $_listener, strpos( $_listener, '::' ) + 2 );

				if ( !class_exists( $_className ) )
				{
					Log::warning( 'Class ' . $_className . ' is not auto-loadable. Cannot call ' . $eventName . ' script' );
					continue;
				}

				if ( !is_callable( $_listener ) )
				{
					Log::warning( 'Method ' . $_listener . ' is not callable. Cannot call ' . $eventName . ' script' );
					continue;
				}

				try
				{
					$this->_executeEventPhpScript( $_className, $_methodName, $event, $eventName, $dispatcher );
				}
				catch ( \Exception $_ex )
				{
					Log::error( 'Exception running script "' . $_listener . '" handling the event "' . $eventName . '"' );
					throw $_ex;
				}
			}
			elseif ( is_string( $_listener ) && (bool)@parse_url( $_listener ) )
			{
				if ( !static::$_client )
				{
					static::$_client = static::$_client ? : new Client();
					static::$_client->setUserAgent( static::DEFAULT_USER_AGENT );
				}

				$_event = array_merge(
					$event->toArray(),
					array(
						'event_name'    => $eventName,
						'dispatcher_id' => spl_object_hash( $dispatcher )
					)
				);

				$_payload = $this->_envelope( $_event );

				$_posts[] = static::$_client->post(
					$_listener,
					array( 'content-type' => 'application/json' ),
					json_encode( $_payload, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT )
				);
			}
			else
			{
				$_dispatched = false;
			}

			if ( !empty( $_posts ) )
			{
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

			if ( $_dispatched && static::$_logEvents )
			{
				Log::debug(
					'/' . $event->getApiName() . '/' . $event->getResource() . ' triggered event: ' . $eventName
				);
			}

			if ( $event->isPropagationStopped() )
			{
				break;
			}
		}

		return $_dispatched;
	}

	/**
	 * @param string $className
	 * @param string $methodName
	 * @param Event  $event
	 */
	protected function _executeEventPhpScript( $className, $methodName, Event $event )
	{
		$className::{$methodName}( $event );
	}

	/**
	 * @param string $eventName
	 *
	 * @return array
	 */
	public function getListeners( $eventName = null )
	{
		if ( null !== $eventName )
		{
			if ( !isset( $this->_sorted[$eventName] ) )
			{
				$this->_sortListeners( $eventName );
			}

			return $this->_sorted[$eventName];
		}

		foreach ( array_keys( $this->_listeners ) as $eventName )
		{
			if ( !isset( $this->_sorted[$eventName] ) )
			{
				$this->_sortListeners( $eventName );
			}
		}

		return $this->_sorted;
	}

	/**
	 * @param $callable
	 *
	 * @return bool
	 */
	protected function isPhpScript( $callable )
	{
		return false === strpos( $callable, ' ' ) && false !== strpos( $callable, '::' );
	}

	/**
	 * @see EventDispatcherInterface::hasListeners
	 */
	public function hasListeners( $eventName = null )
	{
		return (Boolean)count( $this->getListeners( $eventName ) );
	}

	/**
	 * @see EventDispatcherInterface::addListener
	 */
	public function addListener( $eventName, $listener, $priority = 0 )
	{
		if ( !isset( $this->_listeners[$eventName] ) )
		{
			$this->_listeners[$eventName] = array();
		}

		foreach ( $this->_listeners[$eventName] as $priority => $listeners )
		{
			if ( false !== ( $_key = array_search( $listener, $listeners, true ) ) )
			{
				$this->_listeners[$eventName][$priority][$_key] = $listener;
				unset( $this->_sorted[$eventName] );

				return;
			}
		}

		$this->_listeners[$eventName][$priority][] = $listener;
		unset( $this->_sorted[$eventName] );
	}

	/**
	 * @see EventDispatcherInterface::removeListener
	 */
	public function removeListener( $eventName, $listener )
	{
		if ( !isset( $this->_listeners[$eventName] ) )
		{
			return;
		}

		foreach ( $this->_listeners[$eventName] as $priority => $listeners )
		{
			if ( false !== ( $key = array_search( $listener, $listeners, true ) ) )
			{
				unset( $this->_listeners[$eventName][$priority][$key], $this->_sorted[$eventName] );
			}
		}
	}

	/**
	 * @see EventDispatcherInterface::addSubscriber
	 */
	public function addSubscriber( EventSubscriberInterface $subscriber )
	{
		foreach ( $subscriber->getSubscribedEvents() as $_eventName => $_params )
		{
			if ( is_string( $_params ) )
			{
				$this->addListener( $_eventName, array( $subscriber, $_params ) );
			}
			elseif ( is_string( $_params[0] ) )
			{
				$this->addListener( $_eventName, array( $subscriber, $_params[0] ), isset( $_params[1] ) ? $_params[1] : 0 );
			}
			else
			{
				foreach ( $_params as $listener )
				{
					$this->addListener( $_eventName, array( $subscriber, $listener[0] ), isset( $listener[1] ) ? $listener[1] : 0 );
				}
			}
		}
	}

	/**
	 * @see EventDispatcherInterface::removeSubscriber
	 */
	public function removeSubscriber( EventSubscriberInterface $subscriber )
	{
		foreach ( $subscriber->getSubscribedEvents() as $_eventName => $_params )
		{
			if ( is_array( $_params ) && is_array( $_params[0] ) )
			{
				foreach ( $_params as $listener )
				{
					$this->removeListener( $_eventName, array( $subscriber, $listener[0] ) );
				}
			}
			else
			{
				$this->removeListener( $_eventName, array( $subscriber, is_string( $_params ) ? $_params : $_params[0] ) );
			}
		}
	}

	/**
	 * Sorts the internal list of listeners for the given event by priority.
	 *
	 * @param string $eventName The name of the event.
	 */
	protected function _sortListeners( $eventName )
	{
		$this->_sorted[$eventName] = array();

		if ( isset( $this->_listeners[$eventName] ) )
		{
			krsort( $this->_listeners[$eventName] );
			$this->_sorted[$eventName] = call_user_func_array( 'array_merge', $this->_listeners[$eventName] );
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
	 * @param boolean $logEvents
	 */
	public static function setLogEvents( $logEvents )
	{
		self::$_logEvents = $logEvents;
	}

	/**
	 * @return boolean
	 */
	public static function getLogEvents()
	{
		return self::$_logEvents;
	}

}
