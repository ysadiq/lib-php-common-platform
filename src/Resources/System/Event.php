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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Components\EventProxy;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;

/**
 * Event
 * System service for event management
 *
 */
class Event extends BaseSystemRestResource
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
	 * @param array                                               $resources
	 */
	public function __construct( $consumer, $resources = array() )
	{
		$_config = array(
			'service_name' => 'system',
			'name'         => 'Event',
			'api_name'     => 'event',
			'type'         => 'System',
			'type_id'      => PlatformServiceTypes::SYSTEM_SERVICE,
			'description'  => 'System event management',
			'is_active'    => true,
		);

		parent::__construct( $consumer, $_config, $resources );
	}

	/**
	 * Post/create event handler
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array|bool
	 */
	protected function _handlePost()
	{
		$_eventName = $this->_resourceId;
		$_callback = $this->_requestObject->get( 'callback' );

		if ( empty( $_eventName ) || empty( $_callback ) || !is_callable( $_callback ) )
		{
			throw new BadRequestException( 'You must specify both "event_name" and "callback" values in your POST.' );
		}

		return EventProxy::registerCallback( $_eventName, $_callback );
	}

	/**
	 * Default POST implementation
	 *
	 * @return array|bool
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _handleGet()
	{
		$_result = parent::_handleGet();

		return $_result;
	}
}
