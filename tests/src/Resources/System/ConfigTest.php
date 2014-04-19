<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Events\BasePlatformEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Config
 * DSP system administration manager

 */
class ConfigTest extends \PHPUnit_Framework_TestCase
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	protected $_preProcess = false;
	protected $_postProcess = false;
	protected $_beforeDestruct = false;

	public function testResourceEvents()
	{
//		$_config = new Config();
//
//		//	Add an app_name to the headers
//		$_SERVER['HTTP_X_DREAMFACTORY_APPLICATION_NAME'] = 'config_test';
//
//		//	Set some event listeners
//		$_config->on( ResourceServiceEvents::PRE_PROCESS, array( $this, 'onPreProcess' ) );
//		$_config->on( ResourceServiceEvents::POST_PROCESS, array( $this, 'onPostProcess' ) );
//		$_config->on( ResourceServiceEvents::BEFORE_DESTRUCT, array( $this, 'onBeforeDestruct' ) );
//
////		$this->assertTrue( is_array( $_data = $_config->processRequest( 'app', HttpMethod::GET, false ) ) );
//
//		$_config->__destruct();
//
//		$this->assertTrue( $this->_preProcess );
//		$this->assertTrue( $this->_postProcess );
//		$this->assertTrue( $this->_beforeDestruct );
	}

	/**
	 * @param BasePlatformEvent $event
	 * @param string            $eventName
	 * @param EventDispatcher   $dispatcher
	 */
	public function onPreProcess( $event, $eventName, $dispatcher )
	{
		$this->_preProcess = true;
	}

	/**
	 * @param BasePlatformEvent $event
	 * @param string            $eventName
	 * @param EventDispatcher   $dispatcher
	 */
	public function onPostProcess( $event, $eventName, $dispatcher )
	{
		$this->_postProcess = true;
	}

	/**
	 * @param BasePlatformEvent $event
	 * @param string            $eventName
	 * @param EventDispatcher   $dispatcher
	 */
	public function onBeforeDestruct( $event, $eventName, $dispatcher )
	{
		$this->_beforeDestruct = true;
	}
}