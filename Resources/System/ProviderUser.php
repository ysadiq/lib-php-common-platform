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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Interfaces\RestServiceLike;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Sql;
use DreamFactory\Common\Utility\DataFormat;

/**
 * ProviderUser
 * DSP service/provider interface
 */
class ProviderUser extends BaseSystemRestResource
{
	/**
	 * Constructor
	 *
	 * @param RestServiceLike $consumer
	 * @param array           $resourceArray
	 *
	 * @return \DreamFactory\Platform\Resources\System\ProviderUser
	 */
	public function __construct( $consumer = null, $resourceArray = array() )
	{
		parent::__construct(
			$consumer,
			array(
				 'api_name'       => 'provider_user',
				 'is_active'      => true,
				 'name'           => 'Provider User',
				 'type'           => 'System',
				 'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
				 'resource_array' => $resourceArray,
			)
		);
	}
}
