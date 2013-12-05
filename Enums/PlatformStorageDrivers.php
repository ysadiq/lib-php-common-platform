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

use DreamFactory\Platform\Interfaces\SqlDbDriverTypes;
use Kisma\Core\Enums\SeedEnum;

/**
 * PlatformStorageDrivers
 * Storage driver string constants
 */
class PlatformStorageDrivers extends SeedEnum implements SqlDbDriverTypes
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const MS_SQL = 'mssql';
	/**
	 * @var string
	 */
	const SYBASE = 'dblib';
	/**
	 * @var string
	 */
	const SQL_SERVER = 'sqlsrv';
	/**
	 * @var string
	 */
	const MYSQL = 'mysql';
	/**
	 * @var string
	 */
	const MYSQLI = 'mysqli';
	/**
	 * @var string
	 */
	const SQLITE = 'sqlite';
	/**
	 * @var string
	 */
	const SQLITE2 = 'sqlite2';
	/**
	 * @var string
	 */
	const ORACLE = 'oci';
	/**
	 * @var string
	 */
	const POSTGRESQL = 'pgsql';

	/**
	 * Returns the PDO driver type for the given connection's driver name
	 *
	 * @param string $driverType
	 *
	 * @return int
	 */
	public static function driverType( $driverType )
	{
		switch ( $driverType )
		{
			case static::MS_SQL:
			case static::SQL_SERVER:
				return static::DRV_SQLSRV;

			case static::SYBASE:
				return static::DRV_DBLIB;

			case static::MYSQL:
			case static::MYSQLI:
				return static::DRV_MYSQL;

			case static::SQLITE:
			case static::SQLITE2:
				return static::DRV_SQLITE;

			case static::ORACLE:
				return static::DRV_OCSQL;

			case static::POSTGRESQL:
				return static::DRV_PGSQL;

			default:
				return static::DRV_OTHER;
		}
	}

}
