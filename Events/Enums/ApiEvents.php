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
namespace DreamFactory\Platform\Events\Enums;

use Kisma\Core\Events\Enums\LifeEvents;

/**
 * The base events raised by API controllers
 */
class ApiEvents extends LifeEvents
{
	/**
	 * @var string Called before the request is dispatched
	 */
	const BEFORE_REQUEST = '{api_name}.{method}.before_request';
	/**
	 * @var string Called after the dispatch request returns
	 */
	const AFTER_REQUEST = '{api_name}.{method}.after_request';
}
