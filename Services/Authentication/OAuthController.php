<?php
/**
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
namespace DreamFactory\Platform\Services\Authentication;

use DreamFactory\Common\Services\BaseFactoryService;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Hasher;

/**
 * Example OAuth controller for Yii
 */
class OAuth extends BaseFactoryService implements Authenticator
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var BaseOAuthClient
	 */
	protected $_client = null;

	//********************************************************************************
	//* Methods
	//********************************************************************************

	/**
	 * Initialize the controller
	 *
	 * @return void
	 */
	public function init()
	{
		parent::init();

		//	Anyone can see the index
		$this->addUserActions(
			self::ACCESS_TO_ANY,
			array(
				 'authorize',
				 'accessToken',
				 'access_token',
			)
		);

		//	Everything else is auth-required
		$this->addUserActions(
			self::ACCESS_TO_AUTH,
			array(
				 'generateKeys',
			)
		);

		//	Initialize the server...
		$this->_client = new LegacyOAuthClient();
	}

	/**
	 * isAuthorized() callback.
	 * Allow anonymous access to all actions of this controller.
	 *
	 * @return bool
	 */
	public function isAuthorized()
	{
		return true;
	}

	/**
	 * Issue a new access_token to a formerly anonymous user.
	 * Used by apps to authenticate via RESTful APIs.
	 *
	 * @return string
	 */
	public function postAccessToken()
	{
		try
		{
			Log::debug( 'Access token request received: ' . print_r( $_POST, true ) );
			$this->_client->grant_access_token();
		}
		catch ( \Exception $_ex )
		{
			return $this->_createErrorResponse( $_ex );
		}

		return $this->_createErrorResponse();
	}

	/**
	 * Issue a new access_token to a formerly anonymous user.
	 * Used by third-party apps to authenticate via web browser. (Part 2 of 2)
	 */
	public function requestAuthorize()
	{
		try
		{
			$this->_client->finish_client_authorization(
				$this->_client->check_user_credentials(
					FilterInput::request( 'client_id' ),
					FilterInput::request( 'username' ),
					FilterInput::request( 'password' )
				),
				FilterInput::request( 'response_type' ),
				FilterInput::request( 'client_id' ),
				FilterInput::request( 'redirect_uri' ),
				FilterInput::request( 'state' ),
				FilterInput::request( 'scope' ),
				FilterInput::request( 'username' )
			);
		}
		catch ( Exception $_ex )
		{
			$this->_createErrorResponse( $_ex );
		}
	}

	/**
	 * Generates keys
	 *
	 * @return string
	 */
	public function postGenerateKeys()
	{
		return $this->_createResponse(
			array(
				 'client_id'     => Hasher::generateOAuthKey( true ),
				 'client_secret' => Hasher::generateOAuthKey(),
			)
		);
	}
}
