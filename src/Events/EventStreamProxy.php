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
namespace DreamFactory\Platform\Events;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * EventStreamProxy.php
 * A proxy for event requests
 */
class EventStreamProxy
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var PlatformEvent
	 */
	protected $_event;
	/**
	 * @var callable
	 */
	protected $_source;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param PlatformEvent $event
	 * @param callable      $source
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( PlatformEvent $event, $source = null )
	{
		if ( $source && !is_callable( $source ) )
		{
			throw new \InvalidArgumentException( 'The value for $source must be callable.' );
		}

		$this->_event = $event;
		$this->_source = $source;
	}

	/**
	 * @return PlatformEvent
	 */
	public function getEvent()
	{
		return $this->_event;
	}

	/**
	 * @return mixed
	 */
	public function end()
	{
		if ( $this->_source && is_callable( $this->_source ) )
		{
			return call_user_func( $this->_source );
		}
	}

	/**
	 * @param $name
	 * @param $args
	 *
	 * @throws \BadMethodCallException
	 * @return $this|mixed
	 */
	public function __call( $name, $args )
	{
		if ( !method_exists( $this->_event, $name ) )
		{
			throw new \BadMethodCallException();
		}

		$_value = call_user_func_array( array( $this->_event, $name ), $args );

		if ( $this->_event === $_value )
		{
			return $this;
		}

		return $_value;
	}
}
