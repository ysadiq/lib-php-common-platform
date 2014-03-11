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

use DreamFactory\Platform\Utility\Fabric;
use Kisma\Core\Enums\SeedEnum;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * InstallationTypes
 * The different types of DSP installations
 */
class InstallationTypes extends SeedEnum
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @var string All packages have this doc root
	 */
	const PACKAGE_DOCUMENT_ROOT = '/opt/dreamfactory/platform/var/www/launchpad';

	/**
	 * Package Types
	 */

	/**
	 * @var int
	 */
	const FABRIC_HOSTED = 0;
	/**
	 * @var int
	 */
	const STANDALONE_PACKAGE = 1;
	/**
	 * @var int
	 */
	const BITNAMI_PACKAGE = 2;
	/**
	 * @var int
	 */
	const DEB_PACKAGE = 3;
	/**
	 * @var int
	 */
	const RPM_PACKAGE = 4;

	/**
	 * Package Markers
	 */

	/**
	 * @var string
	 */
	const BITNAMI_PACKAGE_MARKER = '/apps/dreamfactory/htdocs/web';
	/**
	 * @var string
	 */
	const DEB_PACKAGE_MARKER = '/opt/dreamfactory/platform/etc/apache2';
	/**
	 * @var string
	 */
	const RPM_PACKAGE_MARKER = '/opt/dreamfactory/platform/etc/httpd';

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Determine the type of installation this is
	 *
	 * @param bool $prettyPrint
	 *
	 * @return int
	 */
	public static function determineType( $prettyPrint = false )
	{
		//	Default to stand-alone
		$_type = static::STANDALONE_PACKAGE;

		//	Hosted?
		if ( Fabric::fabricHosted() )
		{
			$_type = static::FABRIC_HOSTED;
		}
		//	BitNami?
		else if ( false !== stripos( Option::server( 'DOCUMENT_ROOT' ), static::BITNAMI_PACKAGE_MARKER ) )
		{
			$_type = static::BITNAMI_PACKAGE;
		}
		//	Packaged?
		else if ( false !== stripos( Option::server( 'DOCUMENT_ROOT' ), static::PACKAGE_DOCUMENT_ROOT ) )
		{
			//	DEB?
			if ( is_dir( static::DEB_PACKAGE_MARKER ) && Option::server( 'DOCUMENT_ROOT' ) )
			{
				$_type = static::DEB_PACKAGE;
			}

			//	RPM?
			if ( is_dir( static::RPM_PACKAGE_MARKER ) )
			{
				$_type = static::RPM_PACKAGE;
			}
		}

		//	Kajigger the name if wanted...
		if ( $prettyPrint )
		{
			$_type = Inflector::display( strtolower( static::nameOf( $_type ) ) );
		}

		return $_type;
	}
}