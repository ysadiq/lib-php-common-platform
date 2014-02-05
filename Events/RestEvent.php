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

use Kisma\Core\Interfaces\PublisherLike;
use Kisma\Core\Interfaces\SubscriberLike;
use Kisma\Core\Utility\Inflector;

/**
 * Event type triggered by REST calls
 */
class RestEvent extends PlatformEvent
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
	 * @var mixed The response to the call as it stands right now
	 */
	protected $_response = null;

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
	 * @param mixed $response
	 *
	 * @return RestEvent
	 */
	public function setResponse( $response )
	{
		$this->_response = $response;

		return $this;
	}

}
