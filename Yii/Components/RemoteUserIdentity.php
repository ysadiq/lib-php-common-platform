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
namespace DreamFactory\Platform\Yii\Components;

use DreamFactory\Platform\Services\Portal\OAuth\Exceptions\AuthenticationException;
use DreamFactory\Platform\Yii\Models\ProviderUser;
use Kisma\Core\Utility\FilterInput;

/**
 * RemoteUserIdentity
 */
class RemoteUserIdentity extends \CBaseUserIdentity
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var int
	 */
	protected $_id;
	/**
	 * @var array
	 */
	protected $_userData;
	/**
	 * @var string
	 */
	protected $_userName;
	/**
	 * @var string
	 */
	protected $_providerName;
	/**
	 * @var string
	 */
	protected $_providerUserId;
	/**
	 * @var \Hybrid_Provider_Adapter
	 */
	protected $_adapter;
	/**
	 * @var \Hybrid_Auth
	 */
	protected $_authClient;

	/**
	 * @param string       $providerName
	 * @param \Hybrid_Auth $hybridAuth
	 */
	public function __construct( $providerName, \Hybrid_Auth $hybridAuth )
	{
		$this->_providerName = strtolower( trim( $providerName ) );
		$this->_hybridAuth = $hybridAuth;
	}

	/**
	 * Authenticates a user.
	 *
	 * @throws \Exception
	 * @return boolean whether authentication succeeds.
	 */
	public function authenticate()
	{
		$_params = null;

		if ( 'openid' == $this->providerName )
		{
			if ( null === ( $_openIdId = FilterInput::get( INPUT_GET, 'openid_identifier' ) ) )
			{
				throw new AuthenticationException( 'You chose OpenID but did not provide an OpenID identifier' );
			}

			$_params = array( 'openid_identifier' => $_openIdId );
		}

		$_adapter = $this->_hybridAuth->authenticate( $this->_providerName, $_params );

		if ( $_adapter->isUserConnected() )
		{
			$this->_adapter = $_adapter;
			$this->_providerUserId = $this->_adapter->getUserProfile()->identifier;

			if ( null === ( $_user = ProviderUser::getUser( $this->providerName, $this->providerUserId ) ) )
			{
				$this->errorCode = self::ERROR_USERNAME_INVALID;

				return false;
			}

			//	All good
			$this->_id = $_user->id;
			$this->_userName = $_user->email;
			$this->errorCode = self::ERROR_NONE;

			return true;
		}
	}

	/**
	 * @return \Hybrid_Provider_Adapter
	 */
	public function getAdapter()
	{
		return $this->_adapter;
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->_id;
	}

	/**
	 * @return string
	 */
	public function getUserName()
	{
		return $this->_userName;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->getUserName();
	}

	/**
	 * @return \Hybrid_Auth
	 */
	public function getAuthClient()
	{
		return $this->_authClient;
	}

	/**
	 * @return string
	 */
	public function getProviderName()
	{
		return $this->_providerName;
	}

	/**
	 * @return string
	 */
	public function getProviderUserId()
	{
		return $this->_providerUserId;
	}

	/**
	 * @return array
	 */
	public function getUserData()
	{
		return $this->_userData;
	}
}