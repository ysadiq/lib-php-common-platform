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

/**
 * Facebook
 * A Facebook portal
 */
class Facebook extends BaseOAuthResource
{
	//**************************************************************************
	//* Methods
	//**************************************************************************

	/**
	 * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
	 * @param array|\stdClass                                     $options
	 *
	 * @return \DreamFactory\Platform\Services\Portal\Facebook
	 */
	public function __construct( $consumer, $options = array() )
	{
		//	Set default values for this service
		$options = array_merge(
			array(
				 'api_name'          => 'facebook',
				 'user_agent'        => 'dreamfactorysoftware/portal-facebook',
				 'service_endpoint'  => 'https://graph.facebook.com',
				 'resource_endpoint' => 'https://graph.facebook.com',
				 'scope'             => array( 'user_about_me', 'email', 'user_birthday', 'user_groups' ),
			),
			$options
		);

		parent::__construct( $consumer, $options );
	}
}
