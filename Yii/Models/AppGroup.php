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
 * AppGroup.php
 * This is the model for "df_sys_app_group".
 *
 * Columns:
 *
 * @property string  $name
 * @property string  $description
 *
 * Relations:
 *
 * @property App[]   $apps
 */
class AppGroup extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'app_group';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array_merge(
			parent::rules(),
			array(
				 array( 'name', 'required' ),
				 array( 'name', 'unique', 'allowEmpty' => false, 'caseSensitive' => false ),
				 array( 'name', 'length', 'max' => 64 ),
				 array( 'description', 'safe' ),
			)
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		$_relations = array(
			'apps' => array( self::MANY_MANY, __NAMESPACE__ . '\\App', 'df_sys_app_to_app_group(app_id, app_group_id)' ),
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
		$_labels = array(
			'name'        => 'Name',
			'description' => 'Description',
		);

		return parent::attributeLabels( array_merge( $_labels, $additionalLabels ) );
	}

	/**
	 * @param array $values
	 * @param int   $id
	 */
	public function setRelated( $values, $id )
	{
		if ( isset( $values['apps'] ) )
		{
			$this->assignManyToOneByMap( $id, 'app', 'app_to_app_group', 'app_group_id', 'app_id', $values['apps'] );
		}
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
					 'name',
					 'description',
				),
				$columns
			),
			$hidden
		);
	}
}