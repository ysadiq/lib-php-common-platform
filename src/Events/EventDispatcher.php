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

use Composer\Util\ProcessExecutor;
use DreamFactory\Platform\Services\BasePlatformRestService;
use DreamFactory\Platform\Utility\ResourceStore;
use Guzzle\Http\Client;
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
	 * Destruction
	 */
	public function __destruct()
	{
		//	Store any events
		foreach ( $this->_listeners as $_eventName => $_listeners )
		{
			ResourceStore::model( 'event' )->upsert( array( 'event_name' => $_eventName, 'handlers' => $_listeners ) );
		}
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
	 * @param \DreamFactory\Platform\Events\PlatformEvent $event
	 * @param string                                      $eventName
	 * @param EventDispatcher                             $dispatcher
	 *
	 * @throws \Exception
	 */
	protected function _doDispatch( $event, $eventName, $dispatcher )
	{
		foreach ( $this->getListeners( $eventName ) as $_listener )
		{
			if ( !is_string( $_listener ) && is_callable( $_listener ) )
			{
				call_user_func( $_listener, $event );
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
					$this->_executeEventPhpScript( $_className, $_methodName, $event );
				}
				catch ( \Exception $_ex )
				{
					Log::error( 'Exception running script "' . $_listener . '" handling the event "' . $eventName . '"' );
					throw $_ex;
				}
			}
			else
			{

			}

			if ( $event->isPropagationStopped() )
			{
				break;
			}
		}
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
	 *
	 * @api
	 */
	public function addListener( $eventName, $listener, $priority = 0 )
	{
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
	 *
	 * @api
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
