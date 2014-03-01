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
namespace DreamFactory\Platform\Enums;

use Kisma\Core\Enums\SeedEnum;
use Kisma\Core\Utility\Option;

/**
 * PlatformServiceTypes
 */
class PlatformServiceTypes extends SeedEnum
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var int
	 */
	const SYSTEM_SERVICE = 0x0000;
	/**
	 * @var int
	 */
	const EMAIL_SERVICE = 0x0001;
	/**
	 * @var int
	 */
	const LOCAL_FILE_STORAGE = 0x0002;
	/**
	 * @var int
	 */
	const LOCAL_SQL_DB = 0x0004;
	/**
	 * @var int
	 */
	const LOCAL_SQL_DB_SCHEMA = 0x0008;
	/**
	 * @var int
	 */
	const NOSQL_DB = 0x0010;
	/**
	 * @var int
	 */
	const SALESFORCE = 0x0020;
	/**
	 * @var int
	 */
	const LOCAL_PORTAL_SERVICE = 0x0040;
	/**
	 * @var int
	 */
	const REMOTE_FILE_STORAGE = 0x1002;
	/**
	 * @var int
	 */
	const REMOTE_SQL_DB = 0x1004;
	/**
	 * @var int
	 */
	const REMOTE_SQL_DB_SCHEMA = 0x1008;
	/**
	 * @var int
	 */
	const REMOTE_WEB_SERVICE = 0x1020;
	/**
	 * @var string The system manager endpoint
	 */
	const SYSTEM_MANAGER_SERVICE = 'system';
	/**
	 * @var string The system manager endpoint
	 */
	const USER_MANAGER_SERVICE = 'user';

	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @param int    $value        enumerated type value
	 * @param string $service_name given name of the service, also returned as default
	 *
	 * @var array A map of classes for services
	 */
	protected static $_classMap
		= array(
			self::SYSTEM_MANAGER_SERVICE => 'SystemManager',
			self::USER_MANAGER_SERVICE   => 'UserManager',
			self::LOCAL_PORTAL_SERVICE   => 'Portal',
			self::LOCAL_FILE_STORAGE     => 'LocalFileSvc',
			self::REMOTE_FILE_STORAGE    => 'RemoteFileSvc',
			self::LOCAL_SQL_DB           => 'SqlDbSvc',
			self::REMOTE_SQL_DB          => 'SqlDbSvc',
			self::LOCAL_SQL_DB_SCHEMA    => 'SchemaSvc',
			self::REMOTE_SQL_DB_SCHEMA   => 'SchemaSvc',
			self::EMAIL_SERVICE          => 'EmailSvc',
			self::NOSQL_DB               => 'NoSqlDbSvc',
			self::SALESFORCE             => 'SalesforceDbSvc',
		);

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param int    $value       enumerated type value
	 * @param string $serviceName given name of the service, also returned as default
	 *
	 * @return string - associated file name of native service
	 */
	public static function getFileName( $value, $serviceName )
	{
		$_serviceName = $serviceName ? : null;

		if ( null === ( $_fileName = Option::get( static::$_classMap, $value ) ) )
		{
			if ( static::SYSTEM_SERVICE == $value && !empty( $_serviceName ) )
			{
				if ( null !== ( $_fileName = Option::get( static::$_classMap, strtolower( $_serviceName ) ) ) )
				{
					return $_fileName;
				}

				return static::$_classMap[static::SYSTEM_MANAGER_SERVICE];
			}
		}

		return $_serviceName;
	}
}
