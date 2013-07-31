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
 * PortalAccount.php
 * The user service registry model for the DSP
 *
 * Columns:
 *
 * @property int                 $user_id
 * @property string              $provider_user_id
 * @property string              $provider_name
 * @property int                 $account_type
 * @property mixed               $auth_text
 * @property string              $last_use_date
 *
 * @property User                $user
 */
class PortalAccount extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'portal_account';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		$_rules = array(
			array( 'user_id, provider_name, provider_user_id, account_type, auth_text, last_use_date', 'safe' ),
		);

		return array_merge( parent::rules(), $_rules );
	}

	/**
	 * @return array
	 */
	public function relations()
	{
		return array_merge(
			parent::relations(),
			array(
				 'user' => array( static::BELONGS_TO, __NAMESPACE__ . '\\User', 'user_id' ),
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
					 'user_id'          => 'User ID',
					 'account_type'     => 'Account Type',
					 'provider_name'    => 'Provider Name',
					 'provider_user_id' => 'Provider User ID',
					 'auth_text'        => 'Authorization',
					 'last_use_date'    => 'Last Used',
				)
			)
		);
	}

	/**
	 * Named scope that filters by user_id and provider_name
	 *
	 * @param int    $userId
	 * @param string $portalName
	 *
	 * @return $this
	 */
	public function byUserPortal( $userId, $portalName )
	{
		$this->getDbCriteria()->mergeWith(
			array(
				 'condition' => 'user_id = :user_id and provider_name = :provider_name',
				 'params'    => array(
					 ':user_id'       => $userId,
					 ':provider_name' => $portalName
				 ),
			)
		);

		return $this;
	}

	/**
	 * @param $providerName
	 * @param $providerUserId
	 *
	 * @return User
	 */
	public static function getUser( $providerName, $providerUserId )
	{
		$_model = static::model()->find(
			'provider_name = :provider_name and provider_user_id = :provider_user_id',
			array(
				 ':provider_name'    => $providerName,
				 ':provider_user_id' => $providerUserId,
			)
		);

		if ( empty( $_model ) )
		{
			return null;
		}

		return $_model->user;
	}

	/**
	 * @param int $userId
	 *
	 * @return PortalAccount[]
	 */
	public static function getLogins( $userId )
	{
		return static::model()->findAll(
			'user_id = :user_id',
			array(
				 ':user_id' => $userId,
			)
		);
	}

	/**
	 * @param int    $userId
	 * @param string $providerName
	 *
	 * @return PortalAccount
	 */
	public static function getLogin( $userId, $providerName )
	{
		return static::model()->find(
			'user_id = :user_id and provider_name = :provider_name',
			array(
				 ':user_id'       => $userId,
				 ':provider_name' => $providerName,
			)
		);
	}
}