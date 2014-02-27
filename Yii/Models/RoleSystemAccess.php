<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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
 * RoleSystemAccess.php
 * The system access model for the DSP
 *
 * Columns:
 *
 * @property integer $id
 * @property integer $role_id
 * @property string  $component
 * @property string  $access
 *
 * Relations:
 *
 * @property Role    $role
 */
class RoleSystemAccess extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'role_system_access';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array( 'role_id', 'required' ),
			array( 'role_id', 'numerical', 'integerOnly' => true ),
			array( 'access', 'length', 'max' => 64 ),
			array( 'component', 'length', 'max' => 128 ),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'role'    => array( self::BELONGS_TO, __NAMESPACE__ . '\\Role', 'role_id' ),
		);
	}

	/**
	 * @param array $additionalLabels
	 *
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels( $additionalLabels = array() )
	{
		$_labels = array_merge(
			array(
				 'role_id'    => 'Role',
				 'component'  => 'Component',
				 'access'     => 'Access',
			),
			$additionalLabels
		);

		return parent::attributeLabels( $_labels );
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
					 'role_id',
					 'component',
					 'access',
				),
				$columns
			),
			$hidden
		);
	}
}