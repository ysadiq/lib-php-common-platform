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
 * RoleServiceAccess.php
 * The system access model for the DSP
 *
 * Columns:
 *
 * @property integer    $id
 * @property integer    $role_id
 * @property integer    $service_id
 * @property string     $component
 * @property string     $access
 * @property array      $filters
 * @property string     $filter_op
 *
 * Relations:
 *
 * @property Role       $role
 * @property Service    $service
 */
class RoleServiceAccess extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'role_service_access';
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
					 'class'              => 'DreamFactory\\Platform\\Yii\\Behaviors\\SecureJson',
					 'salt'               => $this->getDb()->password,
					 'insecureAttributes' => array(
						 'filters',
					 )
				 ),
			)
		);
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array( 'role_id', 'required' ),
			array( 'role_id, service_id', 'numerical', 'integerOnly' => true ),
			array( 'access, filter_op', 'length', 'max' => 64 ),
			array( 'component', 'length', 'max' => 128 ),
			array( 'filters', 'safe' ),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'role'    => array( self::BELONGS_TO, __NAMESPACE__ . '\\Role', 'role_id' ),
			'service' => array( self::BELONGS_TO, __NAMESPACE__ . '\\Service', 'service_id' ),
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
				 'service_id' => 'Service',
				 'component'  => 'Component',
				 'access'     => 'Access',
				 'filters'    => 'Filters',
				 'filter_op'  => 'Filter Operator',
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
					 'service_id',
					 'component',
					 'access',
					 'filters',
					 'filter_op',
				),
				$columns
			),
			$hidden
		);
	}
}