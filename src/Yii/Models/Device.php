<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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

/**
 * Device.php
 * The user device information for the DSP
 *
 * Columns:
 *
 * @property int                 $user_id
 * @property string              $uuid
 * @property string              $platform
 * @property string              $version
 * @property string              $model
 * @property string              $extra
 *
 * @property User                $user
 */
class Device extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'device';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		$_rules = array(
			array( 'user_id, uuid', 'required' ),
			array( 'platform, version, model, extra', 'safe' ),
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
							 'user_id'  => 'User ID',
							 'uuid'     => 'UUID',
							 'platform' => 'Platform',
							 'version'  => 'Version',
							 'model'    => 'Model',
							 'extra'    => 'Extra',
						 )
					 )
		);
	}

	/**
	 * @param int $userId
	 *
	 * @return Device[]
	 */
	public static function getDevicesByUser( $userId )
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
	 * @param string $uuid
	 *
	 * @return Device
	 */
	public static function getDeviceByUser( $userId, $uuid )
	{
		return static::model()->find(
					 'user_id = :user_id and uuid = :uuid',
					 array(
						 ':user_id' => $userId,
						 ':uuid'    => $uuid,
					 )
		);
	}
}
