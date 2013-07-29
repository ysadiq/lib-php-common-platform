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

use DreamFactory\Platform\Services\Portal\OAuth\Enums\OAuthTokenTypes;

/**
 * Dropbox
 * A Dropbox portal
 */
class Dropbox extends BaseOAuthResource
{
	//**************************************************************************
	//* Methods
	//**************************************************************************

	/**
	 * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
	 * @param array|\stdClass                                     $options
	 *
	 * @return \DreamFactory\Platform\Services\Portal\Dropbox
	 */
	public function __construct( $consumer, $options = array() )
	{
		//	Set default values for this service
		$options = array_merge(
			array(
				 'api_name'           => 'dropbox',
				 'access_token_type'  => OAuthTokenTypes::BEARER,
				 'user_agent'         => 'dreamfactorysoftware/portal-dropbox',
				 'auth_endpoint'      => 'https://www.dropbox.com/1',
				 'service_endpoint'   => 'https://api.dropbox.com/1',
				 'resource_endpoint'  => 'https://api-content.dropbox.com/1',
				 'authorize_endpoint' => '/oauth2/authorize',
				 'token_endpoint'     => '/oauth2/token',
			),
			$options
		);

		parent::__construct( $consumer, $options );
	}
}
