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
namespace DreamFactory\Platform\Interfaces;

/**
 * PlatformStates
 * Defines the various states in which a platform may exist
 */
interface PlatformStates
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const ADMIN_REQUIRED = 0;
	/**
	 * @var string
	 */
	const DATA_REQUIRED = 1;
	/**
	 * @var string
	 */
	const INIT_REQUIRED = 2;
	/**
	 * @var string
	 */
	const READY = 3;
	/**
	 * @var string
	 */
	const SCHEMA_REQUIRED = 4;
	/**
	 * @var string
	 */
	const UPGRADE_REQUIRED = 5;
	/**
	 * @var string
	 */
	const WELCOME_REQUIRED = 6;
}
