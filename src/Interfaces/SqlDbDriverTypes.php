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

/**
 * SqlDbDriverTypes.php
 * The various supported SQL database drivers
 */
interface SqlDbDriverTypes
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @var int
	 */
	const DRV_OTHER = 0;
	/**
	 * @var int
	 */
	const DRV_SQLSRV = 1;
	/**
	 * @var int
	 */
	const DRV_MYSQL = 2;
	/**
	 * @var int
	 */
	const DRV_SQLITE = 3;
	/**
	 * @var int
	 */
	const DRV_PGSQL = 4;
	/**
	 * @var int
	 */
	const DRV_OCSQL = 5;
	/**
	 * @var int
	 */
	const DRV_DBLIB = 6;
    /**
     * @var int
     */
    const DRV_IBMDB2 = 7;
}
