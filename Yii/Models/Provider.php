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

use CEvent;
use CModelEvent;
use DreamFactory\Oasys\Oasys;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * Provider
 * Authentication provider model
 *
 * Columns:
 *
 * @property string              $api_name
 * @property string              $provider_name
 * @property array               $config_text
 * @property int                 $is_active
 * @property int                 $is_system
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
			array( 'api_name, provider_name, config_text, is_active, is_system', 'safe' ),
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
							  'is_active'     => 'Active',
							  'is_system'     => 'System Provider'
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

	/**
	 * Retrieves the complete configuration for this provider merging user credentials with the defaults and stored stuff.
	 *
	 * @param \stdClass|Provider $provider
	 * @param array              $baseConfig
	 * @param array              $stateConfig
	 *
	 * @return array
	 */
	public static function buildConfig( $provider, $baseConfig = array(), $stateConfig = array() )
	{
		$_userConfig = array();

		if ( !Pii::guest() )
		{
			if ( null !== ( $_auth = ProviderUser::model()->byUserProviderUserId( Session::getCurrentUserId(), $provider->id )->find( array( 'select' => 'auth_text' ) ) ) )
			{
				$_userConfig = $_auth->auth_text;
				unset( $_auth );
			}
		}

		//	If the user config is empty and there is no passed in state config, check the store...
		if ( empty( $stateConfig ) )
		{
			$stateConfig = array();

			//	See if we have any data to merge
			$_endpoint = Option::get( $provider, 'api_name', Option::get( $provider, 'endpoint_text' ) );
			$_temp = Oasys::getStore()->get( $_endpoint, array() );

			if ( null !== ( $_json = Option::get( $_temp, 'config', array() ) ) )
			{
				if ( is_array( $_json ) )
				{
					$stateConfig = $_json;
				}
				else if ( false === ( $stateConfig = json_decode( $_json, true ) ) )
				{
					$stateConfig = array();
				}

				unset( $_json );
			}

			unset( $_temp );
		}

		//	Now simmer...
		return array_merge(
		//	My configuration
			(array)$provider->config_text,
			//	Then base from consumer
			Option::clean( $baseConfig ),
			//	User creds
			Option::clean( $_userConfig ),
			//	Then state/stored user creds
			Option::clean( $stateConfig )
		);
	}

	/**
	 * Protect the system resources...
	 *
	 * @throws \CHttpException
	 * @return bool
	 */
	protected function beforeSave()
	{
		if ( $this->is_system )
		{
			throw new \CHttpException( HttpResponse::Forbidden, 'The system providers are read-only.' );
		}

		return parent::beforeSave();
	}

	/**
	 * Protect the config...
	 */
	protected function afterFind()
	{
		if ( $this->is_system )
		{
			$this->config_text = null;
		}

		parent::afterFind();
	}
}
