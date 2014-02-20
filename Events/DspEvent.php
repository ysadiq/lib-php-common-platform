<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
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
namespace DreamFactory\Platform\Events;

use DreamFactory\Platform\Yii\Components\PlatformConsoleApplication;
use DreamFactory\Platform\Yii\Components\PlatformWebApplication;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contains additional information about the REST service call being made
 */
class DspEvent extends PlatformEvent
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @type string The base of our event tree
	 */
	const EVENT_NAMESPACE = 'dsp';

	//**************************************************************************
	//* Members
	//**************************************************************************

	/**
	 * @var PlatformWebApplication|PlatformConsoleApplication The application
	 */
	protected $_app = null;

	//**************************************************************************
	//* Methods
	//**************************************************************************

	/**
	 * @param PlatformWebApplication|PlatformConsoleApplication $app
	 * @param \Symfony\Component\HttpFoundation\Request         $request
	 * @param string                                            $response
	 */
	public function __construct( $app, Request $request = null, $response = null )
	{
		parent::__construct( $request, $response );

		$this->_app = $app;
	}

	/**
	 * @param \DreamFactory\Platform\Yii\Components\PlatformConsoleApplication|\DreamFactory\Platform\Yii\Components\PlatformWebApplication $app
	 *
	 * @return DspEvent
	 */
	public function setApp( $app )
	{
		$this->_app = $app;

		return $this;
	}

	/**
	 * @return \DreamFactory\Platform\Yii\Components\PlatformConsoleApplication|\DreamFactory\Platform\Yii\Components\PlatformWebApplication
	 */
	public function getApp()
	{
		return $this->_app;
	}

}