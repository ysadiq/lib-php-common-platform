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
use DreamFactory\Platform\Yii\Models\User;
use Kisma\Core\Utility\Log;

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
	 * @var User
	 */
	protected $_userModel;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param \DreamFactory\Platform\Yii\Models\User $userModel
	 * @param array                                  $contents
	 *
	 * @throws \InvalidArgumentException
	 * @return \DreamFactory\Platform\Yii\Stores\ProviderUserStore
	 */
	public function __construct( $userModel, $contents = array() )
	{
		if ( empty( $userModel ) || !( $userModel instanceof User ) )
		{
			throw new \InvalidArgumentException( 'You must specify the "User" model of the store.' );
		}

		$this->_userModel = $userModel;

		parent::__construct( $userModel ? $this->_userModel->user_data : array() );
	}

	/**
	 * Synchronize any in-memory data with the store itself
	 *
	 * @return bool True if work was done
	 */
	public function sync()
	{
		try
		{
			$this->_userModel->user_data = $this->contents();
			$this->_userModel->save();

			return true;
		}
		catch ( \CDbException $_ex )
		{
			Log::error( 'Exception saving provider user row: ' . $_ex->getMessage() );

			return false;
		}
	}

	/**
	 * Revoke stored token
	 *
	 * @param bool $delete If true (default), row is deleted from storage
	 *
	 * @return bool
	 */
	public function revoke( $delete = true )
	{
		try
		{
			if ( empty( $this->_userModel ) )
			{
				return true;
			}

			if ( true === $delete )
			{
				return $this->_userModel->delete();
			}

			$this->_userModel->auth_text = null;

			return $this->_userModel->save();
		}
		catch ( \CDbException $_ex )
		{
			Log::error( 'Exception revoking provider user row: ' . $_ex->getMessage() );

			return false;
		}
	}
}
