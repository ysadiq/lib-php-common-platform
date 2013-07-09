<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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

/**
 * PlatformResourceLike
 * Something that acts like a platform resource
 */
interface PlatformResourceLike
{
	/**
	 * @param string $apiName
	 *
	 * @return PlatformResourceLike
	 */
	public function setApiName( $apiName );

	/**
	 * @return string
	 */
	public function getApiName();

	/**
	 * @param string $serviceName
	 *
	 * @return PlatformResourceLike
	 */
	public function setServiceName( $serviceName );

	/**
	 * @return string
	 */
	public function getServiceName();

	/**
	 * @param string $name
	 *
	 * @return PlatformResourceLike
	 */
	public function setName( $name );

	/**
	 * @return string
	 */
	public function getName();

	/**
	 * @param int $type
	 *
	 * @return PlatformResourceLike
	 */
	public function setType( $type );

	/**
	 * @return int
	 */
	public function getType();

	/**
	 * @param string $description
	 *
	 * @return PlatformResourceLike
	 */
	public function setDescription( $description );

	/**
	 * @return string
	 */
	public function getDescription();

	/**
	 * @param bool $isActive
	 *
	 * @return mixed
	 */
	public function setIsActive( $isActive = false );

	public function getIsActive();
}
