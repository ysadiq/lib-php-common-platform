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

use DreamFactory\Platform\Utility\Utilities;

/**
 * Represents an event involving a resource
 */
class ResourceEvent extends BasePlatformEvent
{
	//**************************************************************************
	//* Members
	//**************************************************************************

	/**
	 * @var string The HTTP method of the request
	 */
	protected $_action = null;
	/**
	 * @var string The resource that was requested
	 */
	protected $_resource = null;
	/**
	 * @var array The payload sent along with the request
	 */
	protected $_payload = false;
	/**
	 * @var mixed The response to the original resource request, as it stands right now
	 */
	protected $_response = null;
	/**
	 * @var bool Used to indicate that the response has been altered by the listener
	 */
	protected $_dirty = false;

	//**************************************************************************
	//* Methods
	//**************************************************************************

	/**
	 * @param string $action
	 * @param string $resource
	 * @param array  $payload
	 * @param array  $response
	 * @param mixed  $eventData
	 */
	public function __construct( $action, $resource, $payload = null, $response = null, $eventData = null )
	{
		$this->_action = $action;
		$this->_resource = $resource;
		$this->_payload = $payload;
		$this->_response = $response;

		parent::__construct( $eventData );
	}

	/**
	 * @return string
	 */
	public function getAction()
	{
		return $this->_action;
	}

	/**
	 * @return array
	 */
	public function getPayload()
	{
		return $this->_payload;
	}

	/**
	 * @return string
	 */
	public function getResource()
	{
		return $this->_resource;
	}

	/**
	 * @return mixed
	 */
	public function getResponse()
	{
		return $this->_response;
	}

	/**
	 * @param $response
	 *
	 * @return $this
	 */
	public function setResponse( $response )
	{
		//	If it hasn't changed, don't set it
		if ( null !== $response && null !== $this->_response )
		{
			$_diff = Utilities::array_diff_recursive( $response, $this->_response );

			if ( empty( $_diff ) )
			{
				return $this;
			}
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
