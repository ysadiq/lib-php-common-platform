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
namespace DreamFactory\Platform\Services;

/**
 * NoSqlDbSvc.php
 * A service to handle NoSQL (schema-less) database services accessed through the REST API.
 *
 */
abstract class NoSqlDbSvc extends BaseDbSvc
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * General method for creating a pseudo-random identifier
	 *
	 * @param string $table Name of the table where the item will be stored
	 *
	 * @return string
	 */
	protected static function createItemId( $table )
	{
		$_randomTime = abs( time() );

		if ( $_randomTime == 0 )
		{
			$_randomTime = 1;
		}

		$_random1 = rand( 1, $_randomTime );
		$_random2 = rand( 1, 2000000000 );
		$_generateId = strtolower( md5( $_random1 . $table . $_randomTime . $_random2 ) );
		$_randSmall = rand( 10, 99 );

		return $_generateId . $_randSmall;
	}
}
