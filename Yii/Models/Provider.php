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
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;

/**
 * Provider
 * Authentication provider model
 *
 * Columns:
 *
 * @property string              $provider_name
 * @property array               $config_text
 */
class Provider extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'provider';
	}

	/**
	 * @return array validation rules for model attributes.
	 * @return array
	 */
	public function rules()
	{
		$_rules = array(
			array( 'api_name, provider_name, config_text', 'safe' ),
		);

		return array_merge( parent::rules(), $_rules );
	}

	/**
	 * @return array
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
						 'config_text',
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
					 'provider_name' => 'Name',
					 'api_name'      => 'API Name',
					 'config_text'   => 'Configuration',
				)
			)
		);
	}

	/**
	 * @param string $portal
	 *
	 * @return $this
	 */
	public function byPortal( $portal )
	{
		$this->getDbCriteria()->mergeWith(
			array(
				 'condition' => 'provider_name = :provider_name or api_name = :api_name',
				 'params'    => array( ':provider_name' => $portal, ':api_name' => Inflector::neutralize( $portal ) ),
			)
		);

		return $this;
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
					 'api_name',
					 'provider_name',
					 'config_text',
				),
				$columns
			),
			$hidden
		);
	}

	/**
	 * Returns an array of the row attributes merged with the config array
	 *
	 * @param string $columnName
	 *
	 * @return array
	 */
	public function getMergedAttributes( $columnName = 'config_text' )
	{
		$_merge = array_merge(
			$this->getAttributes(),
			Option::clean( $this->getAttribute( $columnName ) )
		);

		unset( $_merge[$columnName] );

		return $_merge;
	}
}
