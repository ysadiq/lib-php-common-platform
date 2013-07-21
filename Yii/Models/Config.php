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
namespace DreamFactory\Platform\Yii\Models;

use DreamFactory\Common\Utility\DataFormat;

/**
 * Config.php
 * The system configuration model for the DSP
 *
 * Columns
 *
 * @property string     $db_version
 * @property integer    $allow_open_registration
 * @property integer    $open_reg_role_id
 * @property integer    $allow_guest_user
 * @property integer    $guest_role_id
 * @property string     $editable_profile_fields
 *
 * Relations
 *
 * @property Role       $open_reg_role
 * @property Role       $guest_role
 */
class Config extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'config';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array( 'db_version', 'length', 'max' => 32 ),
			array( 'editable_profile_fields', 'length', 'max' => 255 ),
			array( 'allow_open_registration, allow_guest_user, open_reg_role_id, guest_role_id', 'numerical', 'integerOnly' => true ),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		$_relations = array(
			'open_reg_role' => array( self::BELONGS_TO, __NAMESPACE__ . '\\Role', 'open_reg_role_id' ),
			'guest_role'    => array( self::BELONGS_TO, __NAMESPACE__ . '\\Role', 'guest_role_id' ),
		);

		return array_merge( parent::relations(), $_relations );
	}

	/**
	 * @param array $additionalLabels
	 *
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels( $additionalLabels = array() )
	{
		return parent::attributeLabels(
			array(
				'db_version'              => 'Db Version',
				'allow_open_registration' => 'Allow Open Registration',
				'open_reg_role_id'        => 'Open Registration Default Role Id',
				'allow_guest_user'        => 'Allow Guest User',
				'guest_role_id'           => 'Guest Role Id',
				'editable_profile_fields' => 'Editable Profile Fields',
			) + $additionalLabels
		);
	}

	/**
	 * @param string $requested
	 * @param array  $columns
	 * @param array  $hidden
	 *
	 * @return array
	 */
	public function getRetrievableAttributes( $requested, $columns = array(), $hidden = array() )
	{
		return parent::getRetrievableAttributes(
			$requested,
			array_merge(
				array(
					 'db_version',
					 'allow_open_registration',
					 'open_reg_role_id',
					 'allow_guest_user',
					 'guest_role_id',
					 'editable_profile_fields',
				),
				$columns
			),
			// hide these from the general public
			$hidden
		);
	}
}