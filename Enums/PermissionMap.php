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
namespace DreamFactory\Platform\Enums;

use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\SeedEnum;
use Kisma\Core\Utility\Option;

/**
 * PermissionMap
 */
class PermissionMap extends HttpMethod
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var array
	 */
	protected static $_map = array(
		self::Get     => 'read',
		self::Head    => 'read',
		self::Options => 'read',
		self::Post    => 'create',
		self::Copy    => 'create',
		self::Put     => 'update',
		self::Patch   => 'update',
		self::Merge   => 'update',
		self::Delete  => 'delete',
	);

	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * Given an inbound HTTP method, convert to a corresponding access permission
	 *
	 * @param string $method
	 *
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public static function fromMethod( $method )
	{
		if ( null === ( $_permission = Option::get( static::$_map, $method ) ) )
		{
			throw new \InvalidArgumentException( 'No mapping exists for method "' . $method . '"' );
		}

		return $_permission;
	}
}