<?php
/**
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Events\BasePlatformEvent;
use DreamFactory\Platform\Interfaces\PlatformServiceLike;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\ServiceHandler;
use Kisma\Core\Exceptions\NotImplementedException;
use Kisma\Core\Interfaces\ConsumerLike;
use Kisma\Core\Interfaces\PublisherLike;
use Kisma\Core\Interfaces\SubscriberLike;
use Kisma\Core\Seed;
use Kisma\Core\Utility\EventManager;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BasePlatformService
 * The base class for all DSP services
 */
abstract class BasePlatformService extends Seed implements PlatformServiceLike, ConsumerLike, PublisherLike, SubscriberLike
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @type string The base of our event tree
	 */
	const EVENT_NAMESPACE = 'platform.';

	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var string Name to be used in an API
	 */
	protected $_apiName;
	/**
	 * @var int current user ID
	 */
	protected $_currentUserId;
	/**
	 * @var string Description of this service
	 */
	protected $_description;
	/**
	 * @var boolean Is this service activated for use?
	 */
	protected $_isActive = false;
	/**
	 * @var string Native format of output of service, null for php, otherwise json, xml, etc.
	 */
	protected $_nativeFormat = null;
	/**
	 * @var mixed The local service client for proxying
	 */
	protected $_proxyClient;
	/**
	 * @var string Designated type of this service
	 */
	protected $_type;
	/**
	 * @var int Designated type ID of this service
	 */
	protected $_typeId;
	/**
	 * @var Response The response to the request
	 */
	protected $_responseObject = null;
	/**
	 * @var Request The inbound request
	 */
	protected $_requestObject = null;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * Create a new service
	 *
	 * @param array $settings configuration array
	 *
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function __construct( $settings = array() )
	{
		parent::__construct( $settings );

		// Validate basic settings
		if ( null === Option::get( $settings, 'api_name', $this->_apiName ) )
		{
			if ( null !== ( $_name = Option::get( $settings, 'name', $this->_name ) ) )
			{
				$this->_apiName = Inflector::neutralize( $_name );
			}
		}

		if ( empty( $this->_apiName ) )
		{
			throw new \InvalidArgumentException( '"api_name" can not be empty.' );
		}

		if ( null === $this->_typeId )
		{
			if ( false !== ( $_typeId = $this->_determineTypeId() ) )
			{
				$this->_typeId = $_typeId;

				//	Set type from ID
				if ( null === $this->_type )
				{
					$this->_type = PlatformServiceTypes::nameOf( $this->_typeId );
				}
			}
		}

		if ( empty( $this->_type ) || null === $this->_typeId )
		{
			throw new \InvalidArgumentException( '"type" and/or "type_id" cannot be empty.' );
		}

		//	Set description from name...
		if ( empty( $this->_description ) )
		{
			$this->_description = $this->_name;
		}

		//	Get the current user ID if one...
		$this->_currentUserId = $this->_currentUserId ? : Session::getCurrentUserId();
	}

	/**
	 * Destructor
	 */
	public function __destruct()
	{
		//	Save myself!
		ServiceHandler::cacheService( $this->_apiName, $this );

		parent::__destruct();
	}

	/**
	 * {@InheritDoc}
	 */
	public function on( $eventName, $listener, $priority = 0 )
	{
		EventManager::on( $this->_normalizeEventName( $eventName ), $listener, $priority );
	}

	/**
	 * {@InheritDoc}
	 */
	public function off( $eventName, $callback )
	{
		EventManager::off( $this->_normalizeEventName( $eventName ), $callback );
	}

	/**
	 * {@InheritDoc}
	 */
	public function trigger( $eventName, $event = null )
	{
		return EventManager::trigger( $this->_normalizeEventName( $eventName ), $event );
	}

	/**
	 * Given an old string-based TYPE, determine new integer identifier
	 *
	 * @param string $type
	 *
	 * @return bool|int
	 */
	protected function _determineTypeId( $type = null )
	{
		$_type = str_replace( ' ', '_', trim( strtoupper( $type ? : $this->_type ) ) );

		if ( 'LOCAL_EMAIL_SERVICE' == $_type )
		{
			$_type = 'EMAIL_SERVICE';
		}

		try
		{
			//	Throws exception if type not defined...
			return PlatformServiceTypes::defines( $_type, true );
		}
		catch ( \InvalidArgumentException $_ex )
		{
			if ( empty( $_type ) )
			{
				Log::notice( '  * Empty "type", assuming this is a system resource ( type_id == 0 )' );

				return PlatformServiceTypes::SYSTEM_SERVICE;
			}

			Log::error( '  * Unknown service type ID request for "' . $type . '".' );

			return false;
		}
	}

	/**
	 * @param string        $eventName
	 * @param Request|array $values The values to use for replacements in the event name templates.
	 *                              If none specified, the $_REQUEST variables will be used.
	 *                              The current class's variables are also available for replacement.
	 *
	 * @return string
	 */
	protected function _normalizeEventName( $eventName, $values = null )
	{
		static $_cache = array(), $_replacements = null;

		if ( null !== ( $_name = Option::get( $_cache, $_tag = Inflector::neutralize( $eventName ) ) ) )
		{
			return $_name;
		}

		if ( null === $values )
		{
			$values = get_object_vars( Request::createFromGlobals() );
		}

		if ( null === $_replacements )
		{
			$_replacements = array();

			foreach ( array_merge( get_object_vars( $this ), Option::clean( $values ) ) as $_key => $_value )
			{
				$_key = Inflector::neutralize( ltrim( $_key, '_' ) );

				if ( !is_scalar( $_value ) )
				{
					if ( $_value instanceof ParameterBag )
					{
						foreach ( $_value as $_bagKey => $_bagValue )
						{
							$_bagKey = Inflector::neutralize( ltrim( $_bagKey, '_' ) );

							if ( !is_scalar( $_bagValue ) )
							{
								continue;
							}

							$_replacements['{' . $_key . '.' . $_bagKey . '}'] = $_bagValue;
						}
					}

					continue;
				}

				$_replacements['{' . $_key . '}'] = $_value;
			}
		}

		//	Construct and neutralize...
		$_tag = Inflector::neutralize(
						 str_ireplace(
							 array_keys( $_replacements ),
							 array_values( $_replacements ),
							 $_tag
						 )
		);

		return $_cache[$eventName] = $_tag;
	}

	/**
	 * @param string $request
	 * @param string $component
	 *
	 * @throws \Kisma\Core\Exceptions\NotImplementedException
	 */
	protected function _checkPermission( $request, $component )
	{
		throw new NotImplementedException();
	}

	/**
	 * @param string $apiName
	 *
	 * @return BasePlatformService
	 */
	public function setApiName( $apiName )
	{
		$this->_apiName = $apiName;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getApiName()
	{
		return $this->_apiName;
	}

	/**
	 * @param string $description
	 *
	 * @return BasePlatformService
	 */
	public function setDescription( $description )
	{
		$this->_description = $description;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getDescription()
	{
		return $this->_description;
	}

	/**
	 * @param boolean $isActive
	 *
	 * @return BasePlatformService
	 */
	public function setIsActive( $isActive = false )
	{
		$this->_isActive = $isActive;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getIsActive()
	{
		return $this->_isActive;
	}

	/**
	 * @param string $nativeFormat
	 *
	 * @return BasePlatformService
	 */
	public function setNativeFormat( $nativeFormat )
	{
		$this->_nativeFormat = $nativeFormat;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getNativeFormat()
	{
		return $this->_nativeFormat;
	}

	/**
	 * @param string $type
	 *
	 * @return BasePlatformService
	 */
	public function setType( $type )
	{
		$this->_type = $type;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getType()
	{
		return $this->_type;
	}

	/**
	 * @param mixed $proxyClient
	 *
	 * @return BasePlatformService
	 */
	public function setProxyClient( $proxyClient )
	{
		$this->_proxyClient = $proxyClient;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getProxyClient()
	{
		return $this->_proxyClient;
	}

	/**
	 * @param int $typeId
	 *
	 * @return BasePlatformService
	 */
	public function setTypeId( $typeId )
	{
		$this->_typeId = $typeId;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getTypeId()
	{
		return $this->_typeId;
	}

	/**
	 * @return int
	 */
	public function getUserId()
	{
		return $this->_currentUserId;
	}

	/**
	 * @param \Symfony\Component\HttpFoundation\Request $requestObject
	 *
	 * @return BasePlatformRestService
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
	 * @return BasePlatformRestService
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
}
