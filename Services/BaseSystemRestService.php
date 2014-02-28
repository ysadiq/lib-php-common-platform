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
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Interfaces\RestServiceLike;
use Kisma\Core\Utility\Option;

/**
 * BaseSystemRestService
 * A base class for all DSP system services. Service attributes are read-only
 */
abstract class BaseSystemRestService extends BasePlatformRestService
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param array $settings
	 */
	public function __construct( $settings = array() )
	{
		//	Pull out our settings before calling daddy
		$this->_name = $this->_name ? : Option::get( $settings, 'name', null, true );
		$this->_apiName = $this->_apiName ? : Option::get( $settings, 'api_name', null, true );
		$this->_type = $this->_type ? : Option::get( $settings, 'type', null, true );
		$this->_typeId = $this->_typeId ? : Option::get( $settings, 'type_id', PlatformServiceTypes::SYSTEM_SERVICE, true );
		$this->_description = $this->_description ? : Option::get( $settings, 'description', null, true );
		$this->_isActive = $this->_isActive ? : Option::getBool( $settings, 'is_active', true, true );
		$this->_nativeFormat = $this->_nativeFormat ? : Option::get( $settings, 'native_format', null, true );

		parent::__construct( $settings );
	}

	/**
	 * @param string $apiName
	 *
	 * @throws \InvalidArgumentException
	 * @return BasePlatformService
	 */
	public function setApiName( $apiName )
	{
		throw new \InvalidArgumentException( '"$apiName" is a read-only property.' );
	}

	/**
	 * @param string $description
	 *
	 * @throws \InvalidArgumentException
	 * @return BasePlatformService
	 */
	public function setDescription( $description )
	{
		throw new \InvalidArgumentException( '"$description" is a read-only property.' );
	}

	/**
	 * @param boolean $isActive
	 *
	 * @throws \InvalidArgumentException
	 * @return BasePlatformService
	 */
	public function setIsActive( $isActive = false )
	{
		throw new \InvalidArgumentException( '"$isActive" is a read-only property.' );
	}

	/**
	 * @param string $nativeFormat
	 *
	 * @throws \InvalidArgumentException
	 * @return BasePlatformService
	 */
	public function setNativeFormat( $nativeFormat )
	{
		throw new \InvalidArgumentException( '"$nativeFormat" is a read-only property.' );
	}

	/**
	 * @param string $type
	 *
	 * @throws \InvalidArgumentException
	 * @return BasePlatformService
	 */
	public function setType( $type )
	{
		throw new \InvalidArgumentException( '"$type" is a read-only property.' );
	}

	/**
	 * @param int $typeId
	 *
	 * @throws \InvalidArgumentException
	 * @return BasePlatformService
	 */
	public function setTypeId( $typeId )
	{
		throw new \InvalidArgumentException( '"$typeId" is a read-only property.' );
	}
}
