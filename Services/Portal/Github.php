<?php
/**
 * Copyright 2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Services\Portal;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Services\Portal\OAuth\Enums\OAuthTokenTypes;

/**
 * Github
 * An Github specific portal client
 */
class Github extends BasePortalClient
{
	//**************************************************************************
	//* Methods
	//**************************************************************************

	/**
	 * @param array|\stdClass $options
	 *
	 * @return \DreamFactory\Platform\Services\Portal\Github
	 */
	public function __construct( $options = array() )
	{
		//	Set default values for this service
		parent::__construct(
			array_merge(
				array(
					 'api_name'          => 'github',
					 //					 'type'              => PlatformServiceTypes::LOCAL_PORTAL_SERVICE,
					 'service_endpoint'  => 'https://github.com/login',
					 'resource_endpoint' => 'https://api.github.com',
					 'scope'             => array( 'user', 'user:email', 'user:follow', 'public_repo', 'repo', 'repo:status', 'notifications', 'gist' ),
					 'authHeaderName'    => 'token',
					 'userAgent'         => 'dreamfactorysoftware/portal-github',
					 'access_token_type' => OAuthTokenTypes::BEARER,
				),
				$options
			)
		);
	}
}
