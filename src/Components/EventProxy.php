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
namespace DreamFactory\Platform\Components;

use DreamFactory\Platform\Utility\EventManager;

/**
 * EventProxy
 * A proxy for the client to register events
 */
class EventProxy implements EventSubscriberInterface
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var array The map of client listeners to events
	 */
	protected $_subscriberMap = array();

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param array $subscriberMap
	 */
	public function __construct( $subscriberMap = null )
	{
		$this->_subscriberMap = $subscriberMap ? : \Kisma::get( 'event_proxy.subscriber_map' );

		$this->_registerListeners();
	}

	/**
	 * Destructor
	 */
	public function __destruct()
	{
		\Kisma::set( 'event_proxy.subscriber_map', $this->_subscriberMap );
	}

	protected function _registerListeners()
	{
		foreach ( $this->_subscriberMap as $_eventName => $_listener )
		{
			EventManager::addSubscriber( $this );
		}
	}
}
