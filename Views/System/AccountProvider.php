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
namespace DreamFactory\Platform\ResourceViews\System;

use DreamFactory\Platform\Views\BasePlatformResourceView;
use DreamFactory\Platform\Resources\BasePlatformRestResource;

/**
 * AccountProvider
 * Resource view
 */
class AccountProvider extends BasePlatformResourceView
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @return array
	 */
	protected function _generateJTableSchema()
	{
		return array(
			'header'   => 'Portal Providers',
			'resource' => 'account_provider',
			'fields'   => array(
				'id'               => array( 'title' => 'ID', 'key' => true, 'list' => false, 'create' => false, 'edit' => false ),
				'provider_name'    => array( 'title' => 'Name', 'edit' => false ),
				'service_endpoint' => array( 'title' => 'Endpoint' ),
				'last_use_date'    => array( 'title' => 'Last Use', 'edit' => false, 'create' => false ),
			),
			'labels'   => array( 'ID', 'Name', 'Endpoint', 'Last Used' ),
		);
	}

}
