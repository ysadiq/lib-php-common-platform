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
namespace DreamFactory\Platform\Tests;

use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * BaseLibraryTestCase
 */
class BaseLibraryTestCase extends \PHPUnit_Framework_TestCase
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var array
	 */
	protected $_args = array();

	/**
	 * @{InheritDoc}
	 */
	protected function setUp()
	{
		global $argv;
		parent::setUp();

		$this->_args[0] = 'index.php';

		Log::setDefaultLog( __DIR__ . '/log/tests.log' );

		$_SERVER['SCRIPT_NAME'] = $argv[0];
		$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
		$_SERVER['SCRIPT_FILENAME'] = $argv[0];
		$_SERVER['HTTP_HOST'] = 'localhost';
		$_SERVER['SERVER_NAME'] = 'localhost';
		$_SERVER['SERVER_PORT'] = 80;
	}
}