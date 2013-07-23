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

/**
 * AccountProvider.php
 * Models a provider of service accounts
 *
 * Our columns are:
 *
 * @property int     $service_id
 * @property string  $api_name
 * @property string  $provider_name
 * @property string  $handler_class
 * @property string  $auth_endpoint
 * @property string  $service_endpoint
 * @property array   $provider_options
 * @property array   $master_auth_text
 * @property string  $last_use_date
 *
 * @property Service $service
 */
class AccountProvider extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var string
	 */
	public $api_name;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'account_provider';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		$_rules = array(
			array( 'service_id, api_name, provider_name, handler_class, auth_endpoint, service_endpoint, provider_options, master_auth_text, last_use_date', 'safe' ),
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
				 'service' => array( static::BELONGS_TO, __NAMESPACE__ . '\\Service', 'service_id' ),
			)
		);
	}

	/**
	 * @return array
	 */
	public function behaviors()
	{
		return array_merge(
			array(
				 //	Secure JSON
				 'base_platform_model.secure_json' => array(
					 'class'              => 'DreamFactory\\Platform\\Yii\\Behaviors\\SecureJson',
					 'salt'               => $this->getDb()->password,
					 'insecureAttributes' => array(
						 'provider_options',
					 ),
					 'secureAttributes'   => array(
						 'master_auth_text',
					 )
				 ),
			),
			parent::behaviors()
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
					 'service_id'       => 'Service Parent',
					 'api_name'         => 'Portal Endpoint',
					 'provider_name'    => 'Provider Name',
					 'handler_class'    => 'Request Handler Class',
					 'auth_endpoint'    => 'Authorization Endpoint',
					 'service_endpoint' => 'Service Endpoint',
					 'provider_options' => 'Provider Options',
					 'master_auth_text' => 'Tokens',
					 'last_use_date'    => 'Last Used',
				)
			)
		);
	}

	/**
	 * Set api_name
	 */
	protected function afterFind()
	{
		$this->api_name = $this->provider_name;
		parent::afterFind();
	}
}