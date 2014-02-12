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
namespace DreamFactory\EventPlatform\Utility;

use DreamFactory\Platform\Events\BaseEventManagerEvent;
use DreamFactory\Platform\Events\PlatformEvent;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * EventManager
 * Generic EventManager helpers
 */
class EventManager
{
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
	 * @param string                                      $eventName
	 * @param \DreamFactory\Platform\Events\PlatformEvent $event
	 *
	 * @return \Symfony\Component\EventDispatcher\Event
	 */
	public static function trigger( $eventName, PlatformEvent $event = null )
	{
		return static::getDispatcher()->dispatch(
					 static::_normalizeEventName( $eventName ),
					 $event
		);
	}

	/**
	 * Adds an event listener that listens on the specified events.
	 *
	 * @param string   $eventName The event to listen on
	 * @param callable $listener  The listener
	 * @param integer  $priority  The higher this value, the earlier an event
	 *                            listener will be triggered in the chain (defaults to 0)
	 *
	 * @throws InvalidArgumentException
	 */
	public static function on( $eventName, $listener, $priority = 0 )
	{
		static::getDispatcher()->addListener(
			  static::_normalizeEventName( $eventName ),
			  $listener,
			  $priority
		);
	}

	/**
	 * Removes an event listener from the specified events.
	 *
	 * @param string|array $eventName The event(s) to remove a listener from
	 * @param callable     $listener  The listener to remove
	 *
	 * @throws InvalidArgumentException
	 */
	public static function off( $eventName, $listener )
	{
		static::getDispatcher()->removeListener(
			  static::_normalizeEventName( $eventName ),
			  $listener
		);
	}

	/**
	 * @param string                                    $eventName
	 * @param \Symfony\Component\HttpFoundation\Request $request
	 *
	 * @return string
	 */
	protected static function _normalizeEventName( $eventName, Request $request = null )
	{
		static $_cache = array();

		$_tag = Inflector::neutralize( $eventName );

		if ( null !== ( $_name = Option::get( $_cache, $_tag ) ) )
		{
			return $_name;
		}

		$_request = $request ? : Request::createFromGlobals();
		$_replacements = get_class_vars( get_class( $_request ) );

		foreach ( $_replacements as $_key )
		{
			$_tag = str_ireplace(
				'{' . $_key . '}',
				Option::get( $_request, $_key ),
				$_tag
			);
		}

		return $_cache[$eventName] = $_tag;
	}

	/**
	 * @return \Symfony\Component\EventDispatcher\EventDispatcher
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
