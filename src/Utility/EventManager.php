<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 *
 * You may obtain a copy of the License at http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Utility;

use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * EventManager
 * Generic EventManager helpers
 */
class EventManager
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @var string The event name cache key
	 */
	const EVENT_NAME_CACHE_KEY = 'event_manager.event_name_cache';

	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var EventDispatcher
	 */
	protected static $_dispatcher = null;

	//*************************************************************************
	//	Methods
	//*************************************************************************

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
	public static function trigger( &$eventName, PlatformEvent $event = null, $values = array() )
	{
		return static::_getDispatcher()->dispatch( static::_normalizeEventName( $eventName, $values ), $event );
	}

	/**
	 * Adds an event listener that listens on the specified events.
	 *
	 * @param string   $eventName The event to listen on
	 * @param callable $listener  The listener
	 * @param integer  $priority  The higher this value, the earlier an event
	 *                            listener will be triggered in the chain (defaults to 0)
	 * @param array    $values    Optional replacement values for dynamic event names
	 *
	 * @throws InvalidArgumentException
	 */
	public static function on( $eventName, $listener, $priority = 0, $values = array() )
	{
		static::_getDispatcher()->addListener( static::_normalizeEventName( $eventName, $values ), $listener, $priority );
	}

	/**
	 * Removes an event listener from the specified events.
	 *
	 * @param string|array $eventName The event(s) to remove a listener from
	 * @param callable     $listener  The listener to remove
	 * @param array        $values    Optional replacement values for dynamic event names
	 */
	public static function off( $eventName, $listener, $values = array() )
	{
		static::_getDispatcher()->removeListener( static::_normalizeEventName( $eventName, $values ), $listener );
	}

	/**
	 * @param string        $eventName
	 * @param Request|array $values The values to use for replacements in the event name templates.
	 *                              If none specified, the $_REQUEST variables will be used.
	 *                              The current class's variables are also available for replacement.
	 *
	 * @return string
	 */
	protected static function _normalizeEventName( &$eventName, $values = null )
	{
		static $_requestValues = null, $_replacements;

		if ( false === strpos( $eventName, '{' ) )
		{
			return $eventName;
		}

		$_tag = Inflector::neutralize( $eventName );

		if ( null === $_requestValues )
		{
			$_requestValues = array();
			$_request = Pii::app()->getRequestObject();

			if ( !empty( $_request ) )
			{
				$_requestValues = array(
					'headers'    => $_request->headers,
					'attributes' => $_request->attributes,
					'cookie'     => $_request->cookies,
					'files'      => $_request->files,
					'query'      => $_request->query,
					'request'    => $_request->request,
					'server'     => $_request->server,
				);
			}
		}

		$_combinedValues = Option::merge(
			Option::clean( $_requestValues ),
			Inflector::neutralizeObject( $values )
		);

		if ( empty( $_replacements ) && !empty( $_combinedValues ) )
		{
			$_replacements = array();

			foreach ( $_combinedValues as $_key => $_value )
			{
				if ( is_scalar( $_value ) )
				{
					$_replacements['{' . $_key . '}'] = $_value;
				}
				else if ( $_value instanceof \IteratorAggregate && $_value instanceof \Countable )
				{
					foreach ( $_value as $_bagKey => $_bagValue )
					{
						$_bagKey = Inflector::neutralize( ltrim( $_bagKey, '_' ) );

						if ( is_array( $_bagValue ) )
						{
							if ( !empty( $_bagValue ) )
							{
								$_bagValue = current( $_bagValue );
							}
							else
							{
								$_bagValue = null;
							}
						}
						elseif ( !is_scalar( $_bagValue ) )
						{
							continue;
						}

						$_replacements['{' . $_key . '.' . $_bagKey . '}'] = $_bagValue;
					}
				}
			}
		}

		//	Construct and neutralize...
		$_tag = Inflector::neutralize( str_ireplace( array_keys( $_replacements ), array_values( $_replacements ), $_tag ) );

		return $eventName = $_tag;
	}

	/**
	 * @param EventSubscriberInterface $subscriber
	 */
	public static function addSubscriber( EventSubscriberInterface $subscriber )
	{
		static::_getDispatcher()->addSubscriber( $subscriber );
	}

	/**
	 * @param EventSubscriberInterface $subscriber
	 */
	public static function removeSubscriber( EventSubscriberInterface $subscriber )
	{
		static::_getDispatcher()->removeSubscriber( $subscriber );
	}

	/**
	 * @return \Symfony\Component\EventDispatcher\EventDispatcher
	 */
	protected static function _getDispatcher()
	{
		if ( empty( static::$_dispatcher ) )
		{
			static::$_dispatcher = new EventDispatcher();
		}

		return static::$_dispatcher;
	}
}
