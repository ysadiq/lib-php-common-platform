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

/**
 * ResponseFormats
 * Supported DSP response formats
 */
class ResponseFormats extends SeedEnum
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var int No formatting. The default
	 */
	const RAW = 0;
	/**
	 * @var int DataTables formatting {@ink http://datatables.net/}
	 */
	const DATATABLES = 100;
	/**
	 * @var int jTable formatting {@link http://www.jtable.org/}
	 */
	const JTABLE = 101;
	/**
	 * @var int aciTree formatting {@link http://plugins.jquery.com/aciTree/}
	 */
	const ACITREE = 102;
}