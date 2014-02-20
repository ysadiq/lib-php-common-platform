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
namespace DreamFactory\Platform\Events;

use Kisma\Core\Events\SeedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A basic DSP event for the server-side DSP events
 *
 * This object is modeled after jQuery's event object for ease of client consumption.
 *
 * If an event handler calls an event's stopPropagation() method, no further
 * listeners will be called.
 *
 * PlatformEvent::preventDefault() and PlatformEvent::isDefaultPrevented()
 * are provided in stub form, and do nothing by default. You may implement the
 * response to a "preventDefault" in your services by overriding the methods.
 */
class PlatformEvent extends SeedEvent
{
	//**************************************************************************
	//* Members
	//**************************************************************************

	/**
	 * @var int The time of the event. Unix timestamp returned from time()
	 */
	protected $_timestamp = null;
	/**
	 * @var bool Set to true to stop the default action from being performed
	 */
	protected $_defaultPrevented = false;
	/**
	 * @var mixed The last value returned by an event handler that was triggered by this event, unless the value was null.
	 */
	protected $_lastHandlerResult = null;
	/**
	 * @var Request The inbound request associated with this event
	 */
	protected $_request = null;
	/**
	 * @var Response The response to the original resource request, as it stands right now
	 */
	protected $_response = null;
	/**
	 * @var bool Indicates that a listener in the chain has changed the response
	 */
	protected $_dirty = false;

	//**************************************************************************
	//* Methods
	//**************************************************************************

	/**
	 * @param Request         $request
	 * @param Response|string $response
	 */
	public function __construct( $request = null, $response = null )
	{
		$this->_timestamp = time();

		//	Build a request if we don't get one
		$this->_request = $request ? : Request::createFromGlobals();

		//	Build a response if one isn't given
		$this->_response = ( $response instanceof Response ) ? $response : new Response( is_string( $response ) ? $response : null );
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
	 * @return int
	 */
	public function getTimestamp()
	{
		return $this->_timestamp;
	}

	/**
	 * @param mixed $lastHandlerResult
	 *
	 * @return PlatformEvent
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

	/**
	 * @return \Symfony\Component\HttpFoundation\Request
	 */
	public function getRequest()
	{
		return $this->_request;
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function getResponse()
	{
		return $this->_response;
	}

	/**
	 * @param \Symfony\Component\HttpFoundation\Response $response
	 *
	 * @return $this
	 */
	public function setResponse( Response $response )
	{
		//	If it hasn't changed, don't set it
		if ( $response === $this->_response )
		{
			return $this;
		}

		$this->_response = $response;
		$this->_dirty = true;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isDirty()
	{
		return $this->_dirty;
	}
}