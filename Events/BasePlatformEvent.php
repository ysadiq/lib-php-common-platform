<?php
/**
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

use Symfony\Component\EventDispatcher\Event;

/**
 * The base event class for the server-side DSP events
 *
 * This object is modeled after jQuery's event object for
 * ease of client consumption.
 *
 * If an event handler calls an event's stopPropagation() method, no further
 * listeners will be called.
 *
 * BasePlatformEvent::preventDefault() and BasePlatformEvent::isDefaultPrevented()
 * are provided in stub form, and do nothing by default. You may implement the
 * response to a "preventDefault" in your services by overriding the methods.
 */
class BasePlatformEvent extends Event
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @type string The base of our event tree
	 */
	const EVENT_NAMESPACE = 'platform';

	//**************************************************************************
	//* Members
	//**************************************************************************

	/**
	 * @var int The time of the event. Unix timestamp returned from time()
	 */
	protected $_timestamp = null;
	/**
	 * @var mixed An optional object of data passed to an event handler
	 */
	protected $_data = null;
	/**
	 * @var bool Set to true to stop the default action from being performed
	 */
	protected $_defaultPrevented = false;
	/**
	 * @var mixed The last value returned by an event handler that was triggered by this event, unless the value was null.
	 */
	protected $_lastHandlerResult = null;

	//**************************************************************************
	//* Methods
	//**************************************************************************

	/**
	 * @param mixed $eventData
	 */
	public function __construct( $eventData = null )
	{
		$this->_data = $eventData;
		$this->_timestamp = time();
	}

	/**
	 * Tells the event manager to prevent the default action from being performed
	 */
	public function preventDefault()
	{
		$this->_defaultPrevented = true;
	}

	/**
	 * @return bool
	 */
	public function isDefaultPrevented()
	{
		return $this->_defaultPrevented;
	}

	/**
	 * @param mixed $data
	 *
	 * @return SeedEvent
	 */
	public function setData( $data )
	{
		$this->_data = $data;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getData()
	{
		return $this->_data;
	}

	/**
	 * @return int
	 */
	public function getTimestamp()
	{
		return $this->_timestamp;
	}

	/**
	 * @param mixed $lastHandlerResult
	 *
	 * @return BasePlatformEvent
	 */
	public function setLastHandlerResult( $lastHandlerResult )
	{
		if ( null !== $lastHandlerResult )
		{
			$this->_lastHandlerResult = $lastHandlerResult;
		}

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getLastHandlerResult()
	{
		return $this->_lastHandlerResult;
	}

}
