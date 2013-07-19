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

/**
 * AppServiceRelation.php
 * The system application to service relationship model for the DSP
 *
 * Columns:
 *
 * @property integer $id
 * @property integer $app_id
 * @property integer $service_id
 * @property string  $component
 *
 * Relations:
 *
 * @property App     $app
 * @property Service $service
 */
class AppServiceRelation extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'df_sys_app_to_service';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array( 'app_id', 'required' ),
			array( 'app_id, service_id', 'numerical', 'integerOnly' => true ),
			array( 'component', 'length', 'max' => 128 ),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'app'     => array( self::BELONGS_TO, __NAMESPACE__ . '\\App', 'app_id' ),
			'service' => array( self::BELONGS_TO, __NAMESPACE__ . '\\Service', 'service_id' ),
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
						 'component',
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
				array(
					 'id'         => 'Id',
					 'app_id'     => 'App',
					 'service_id' => 'Service',
					 'component'  => 'Component',
				),
				$additionalLabels
			)
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * @param mixed $criteria
	 *
	 * @return \CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search( $criteria = null )
	{
		$_criteria = $criteria ? : new \CDbCriteria;

		$_criteria->compare( 'app_id', $this->app_id );
		$_criteria->compare( 'service_id', $this->service_id );
		$_criteria->compare( 'component', $this->component );

		return parent::search( $criteria );
	}

	/**
	 * @param string $requested
	 *
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
					 'app_id',
					 'service_id',
					 'component',
				),
				$columns
			),
			$hidden
		);
	}
}