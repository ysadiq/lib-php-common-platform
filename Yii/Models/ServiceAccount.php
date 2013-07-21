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
namespace DreamFactory\Platform\Yii\Models;

use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Sql;

/**
 * ServiceAccount.php
 * The user service registry model for the DSP
 *
 * Columns:
 *
 * @property int                 $user_id
 * @property int                 $provider_id
 * @property int                 $account_type
 * @property array               $auth_text
 * @property string              $last_use_date
 *
 * @property AccountProvider     $provider
 * @property User                $user
 */
class ServiceAccount extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'service_account';
	}

	/**
	 * @return array
	 */
	public function relations()
	{
		return array_merge(
			parent::relations(),
			array(
				 'provider' => array( static::BELONGS_TO, __NAMESPACE__ . '\\AccountProvider', 'provider_id' ),
				 'user'     => array( static::BELONGS_TO, __NAMESPACE__ . '\\User', 'user_id' ),
			)
		);
	}

	/**
	 * @return array
	 */
	public function behaviors()
	{
		return array_merge(
			parent::behaviors(),
			array(
				 //	Secure JSON
				 'base_platform_model.secure_json' => array(
					 'class'            => 'DreamFactory\\Platform\\Yii\\Behaviors\\SecureJson',
					 'salt'             => $this->getDb()->password,
					 'secureAttributes' => array(
						 'auth_text',
					 )
				 ),
			)
		);
	}

	/**
	 * @param array $additionalLabels
	 *
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels( $additionalLabels = array() )
	{
		return parent::attributeLabels(
			array_merge(
				$additionalLabels,
				array(
					 'provider_id'   => 'Provider',
					 'user_id'       => 'User ID',
					 'account_type'  => 'Account Type',
					 'auth_text'     => 'Authorization',
					 'last_use_date' => 'Last Used',
				)
			)
		);
	}

	/**
	 * Named scope that filters by user_id and service_id
	 *
	 * @param int $userId
	 * @param int $providerId
	 *
	 * @return $this
	 */
	public function byUserService( $userId, $providerId )
	{
		$this->getDbCriteria()->mergeWith(
			array(
				 'condition' => 'user_id = :user_id and provider_id = :provider_id',
				 'params'    => array(
					 ':user_id'     => $userId,
					 ':provider_id' => $providerId
				 ),
			)
		);

		return $this;
	}
}