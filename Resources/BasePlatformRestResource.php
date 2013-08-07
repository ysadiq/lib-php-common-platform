<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Resources;

use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Interfaces\RestResourceLike;
use DreamFactory\Platform\Services\BasePlatformRestService;
use DreamFactory\Platform\Services\BasePlatformService;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\SqlDbUtilities;
use DreamFactory\Platform\Yii\Models\BasePlatformSystemModel;
use Kisma\Core\Seed;
use Kisma\Core\Utility\Option;

/**
 * BasePlatformRestResource
 * A base service resource class to handle service resources of various kinds.
 */
abstract class BasePlatformRestResource extends BasePlatformRestService implements RestResourceLike
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const DEFAULT_PASSTHRU_CLASS = 'DreamFactory\\Platform\\Utility\\ResourceStore';

	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var BasePlatformService
	 */
	protected $_consumer;
	/**
	 * @var string The name of this service
	 */
	protected $_serviceName;
	/**
	 * @var string The class to pass to from __callStatic()
	 */
	protected static $_passthruClass = self::DEFAULT_PASSTHRU_CLASS;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * Create a new service
	 *
	 * @param BasePlatformService $consumer
	 * @param array               $settings configuration array
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( $consumer, $settings = array() )
	{
		$this->_consumer = $consumer;
		$this->_serviceName = $this->_serviceName ? : Option::get( $settings, 'service_name', null, true );

		if ( empty( $this->_serviceName ) )
		{
			throw new \InvalidArgumentException( 'You must supply a value for "service_name".' );
		}

		parent::__construct( $settings );
	}

	/**
	 * A chance to format the response
	 */
	protected function _postProcess()
	{
		parent::_postProcess();

		$this->_formatResponse();
	}

	/**
	 * Format the response if necessary
	 */
	protected function _formatResponse()
	{
	}

	/**
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return mixed
	 */
	public static function __callStatic( $name, $arguments )
	{
		//	Passthru to store
		return call_user_func_array( array( static::$_passthruClass, $name ), $arguments );
	}

	/**
	 * @param BasePlatformSystemModel $resource
	 *
	 * @return mixed
	 */
	public function _getSchema( $resource )
	{
		return SqlDbUtilities::describeTable( $resource->getDb(), $resource->tableName(), $resource->tableNamePrefix() );
	}

	/**
	 * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
	 *
	 * @return BasePlatformResource
	 */
	public function setConsumer( $consumer )
	{
		$this->_consumer = $consumer;

		return $this;
	}

	/**
	 * @return \DreamFactory\Platform\Services\BasePlatformService
	 */
	public function getConsumer()
	{
		return $this->_consumer;
	}

	/**
	 * @param mixed $serviceName
	 *
	 * @return BasePlatformRestService
	 */
	public function setServiceName( $serviceName )
	{
		$this->_serviceName = $serviceName;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getServiceName()
	{
		return $this->_serviceName;
	}

	/**
	 * @param string $passthruClass
	 */
	public static function setPassthruClass( $passthruClass )
	{
		self::$_passthruClass = $passthruClass;
	}

	/**
	 * @return string
	 */
	public static function getPassthruClass()
	{
		return self::$_passthruClass;
	}
}
