<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
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
namespace DreamFactory\Platform\Yii\Stores;

use DreamFactory\Oasys\Stores\BaseOasysStore;
use DreamFactory\Platform\Yii\Models\ProviderUser;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * ProviderUserStore.php
 * An Oasys store fore providers
 */
class ProviderUserStore extends BaseOasysStore
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var int
	 */
	protected $_userId;
	/**
	 * @var int
	 */
	protected $_providerUserId;
	/**
	 * @var int
	 */
	protected $_providerId;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param int   $userId
	 * @param int   $providerId
	 * @param array $contents
	 *
	 * @return \DreamFactory\Platform\Yii\Stores\ProviderUserStore
	 */
	public function __construct( $userId, $providerId, $contents = array() )
	{
		$this->_providerId = $providerId;
		$this->_userId = $userId;
		$this->_providerUserId = $this->_providerUserId ? : Option::get( $contents, 'provider_user_id', null, true );

		$this->_load();

		parent::__construct( $contents );
	}

	/**
	 * Retrieves any previously stored data for this user
	 *
	 * @param bool $fill
	 *
	 * @return ProviderUser
	 */
	protected function _load( $fill = true )
	{
		$_criteria = new \CDbCriteria();
		$_criteria->order = 'id';
		$_criteria->limit = 1;
		$_criteria->condition = 'user_id = :user_id AND provider_id = :provider_id';
		$_criteria->params = array(
			'user_id'     => $this->_userId,
			'provider_id' => $this->_providerId,
		);

		if ( !empty( $this->_providerUserId ) )
		{
			$_criteria->condition .= ' AND provider_user_id = :provider_user_id';
			$_criteria->params[':provider_user_id'] = $this->_providerUserId;
		}

		/** @var ProviderUser $_pu */
		if ( null !== ( $_pu = ProviderUser::model()->find( $_criteria ) ) )
		{
			//	Load prior auth stuff...
			if ( !empty( $_pu->auth_text ) && true === $fill )
			{
				$this->merge( $_pu->auth_text );
			}
		}

		return $_pu;
	}

	/**
	 * Synchronize any in-memory data with the store itself
	 *
	 * @return bool True if work was done
	 */
	public function sync()
	{
		if ( null === ( $_creds = $this->_load( false ) ) )
		{
			$_creds = new ProviderUser();
			$_creds->user_id = $this->_userId;
			$_creds->provider_id = $this->_providerId;
			$_creds->auth_text = array();
		}

		$_creds->provider_user_id = $this->_providerUserId;
		$_creds->auth_text = array_merge( !is_array( $_creds->auth_text ) ? array() : $_creds->auth_text, $this->contents() );
		$_creds->last_use_date = date( 'c' );

		try
		{
			$_creds->save();
			Log::debug( 'User credentials stored' );

			return true;
		}
		catch ( \CDbException $_ex )
		{
			Log::error( $_ex->getMessage() );
		}

		return true;
	}

	/**
	 * @param bool $delete
	 *
	 * @return bool
	 */
	public function revoke( $delete = true )
	{
		try
		{
			if ( null === ( $_pu = $this->_load( false ) ) )
			{
				return true;
			}

			if ( true === $delete )
			{
				return $_pu->delete();
			}

			$_pu->auth_text = null;

			return $_pu->save();
		}
		catch ( \CDbException $_ex )
		{
			Log::error( 'Exception revoking provider user row: ' . $_ex->getMessage() );

			return false;
		}
	}

	/**
	 * @param int $providerId
	 *
	 * @return ProviderUserStore
	 */
	public function setProviderId( $providerId )
	{
		$this->_providerId = $providerId;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getProviderId()
	{
		return $this->_providerId;
	}

	/**
	 * @param int $providerUserId
	 *
	 * @return ProviderUserStore
	 */
	public function setProviderUserId( $providerUserId )
	{
		$this->_providerUserId = $providerUserId;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getProviderUserId()
	{
		return $this->_providerUserId;
	}

	/**
	 * @param int $userId
	 *
	 * @return ProviderUserStore
	 */
	public function setUserId( $userId )
	{
		$this->_userId = $userId;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getUserId()
	{
		return $this->_userId;
	}

}
