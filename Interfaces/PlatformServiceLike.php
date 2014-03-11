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
namespace DreamFactory\Platform\Interfaces;

use DreamFactory\Common\Interfaces\ServiceProviderLike;

/**
 * PlatformServiceLike
 * An interface for the base platform service.
 */
interface PlatformServiceLike extends ServiceProviderLike
{
	/**
	 * @param string $apiName
	 *
	 * @return PlatformServiceLike
	 */
	public function setApiName( $apiName );

	/**
	 * @return string
	 */
	public function getApiName();

	/**
	 * @param string $type
	 *
	 * @return PlatformServiceLike
	 */
	public function setType( $type );

	/**
	 * @return string
	 */
	public function getType();

	/**
	 * @param string $description
	 *
	 * @return PlatformServiceLike
	 */
	public function setDescription( $description );

	/**
	 * @return string
	 */
	public function getDescription();

	/**
	 * @param boolean $isActive
	 *
	 * @return PlatformServiceLike
	 */
	public function setIsActive( $isActive = false );

	/**
	 * @return boolean
	 */
	public function getIsActive();

	/**
	 * @param string $name
	 *
	 * @return PlatformServiceLike
	 */
	public function setName( $name );

	/**
	 * @return string
	 */
	public function getName();

	/**
	 * @param string $nativeFormat
	 *
	 * @return PlatformServiceLike
	 */
	public function setNativeFormat( $nativeFormat );

	/**
	 * @return string
	 */
	public function getNativeFormat();
}
