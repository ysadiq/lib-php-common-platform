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

use Kisma\Core\Utility\Storage;

/**
 * Event.php
 * Model for table dreamfactory.df_sys_event
 *
 * Columns
 *
 * @property string $event_name
 * @property string $handlers
 */
class Event extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'event';
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
						'handlers',
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
			array( 'event_name', 'length', 'max' => 1024 ),
			array( 'handlers', 'safe' ),
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
			array(
				'event_name' => 'Event Name',
				'handlers'   => 'Callbacks',
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
				array_keys( $this->attributeLabels() ),
				// hide these from the general public
				$hidden
			)
		);
	}

	/**
	 *
	 */
	protected function beforeFind()
	{
		if ( is_array( $this->handlers ) )
		{
			$this->handlers = Storage::freeze( $this->handlers );
		}

		parent::beforeFind();
	}

}