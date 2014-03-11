<?php
/**
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
namespace DreamFactory\Platform\Interfaces;

use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\PlatformEvent;

/**
 * StreamListenerLike
 */
interface StreamListenerLike
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Process data received
	 *
	 * @param PlatformEvent   $event
	 * @param string          $eventName
	 * @param EventDispatcher $dispatcher
	 *
	 * @return mixed
	 */
	public function processEvent( $event, $eventName, $dispatcher );
}
