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

use Kisma\Core\Enums\SeedEnum;

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
}