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
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Services\BasePlatformService;

/**
 * AppGroup
 * DSP system administration manager
 *
 */
class AppGroup extends BaseSystemRestResource
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Constructor
	 *
	 * @param BasePlatformService $consumer
	 * @param array               $resources
	 *
	 * @return \DreamFactory\Platform\Resources\System\AppGroup
	 */
	public function __construct( $consumer, $resources = array() )
	{
		$_config = array(
			'service_name'   => 'system',
			'name'           => 'App Group',
			'api_name'       => 'app_group',
			'type'           => 'System',
			'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
			'description'    => 'System application grouping administration.',
			'is_active'      => true,
		);

		parent::__construct( $consumer, $_config, $resources );
	}
}
