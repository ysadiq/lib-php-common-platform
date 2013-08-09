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
			array( 'provider_name, config_text', 'safe' ),
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
				 'condition' => 'provider_name = :provider_name',
				 'params'    => array( ':provider_name' => $portal ),
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
	 * Returns the deneutralized name for the provider or the full path to the provider class
	 */
	public static function getHybridClassName( $name, $returnClassPath = false )
	{
		//	Neutral => HA names
		static $_nameMap
		= array(
			'linkedin' => 'LinkedIn',
			'github'   => 'GitHub',
			'myspace'  => 'MySpace',
			'openid'   => 'OpenID',
			'twitchtv' => 'TwitchTV',
			'xing'     => 'XING',
			'qq'       => 'QQ',
			'lastfm'   => 'LastFM',
			'500px'    => 'px500',

		);

		//	Location of these
		static $_sources
		= array(
			'/hybridauth/hybridauth/hybridauth/Hybrid/Providers',
			'/hybridauth/hybridauth/additional-providers'
		);

		$_name = strtolower( trim( $name ) );

		if ( null === ( $_provider = Option::get( $_nameMap, $_name ) ) )
		{
			$_provider = ucfirst( $_name );
		}

		if ( false === $returnClassPath )
		{
			return $_provider;
		}

		$_base = \Kisma::get( 'app.vendor_path' );

		//	Find the class
		foreach ( $_sources as $_path )
		{
			$_sourcePath = $_base . $_path;
			$_fileName = $_provider . '.php';
			$_checkPath = $_sourcePath . '/' . $_fileName;

			//	/opt/dreamfactory/web/web-csp/vendor/hybridauth/hybridauth/hybridauth/Hybrid/Providers/Facebook.php
			//	/opt/dreamfactory/web/web-csp/vendor/hybridauth/hybridauth/Hybrid/Providers/Facebook.php
			if ( file_exists( $_checkPath ) )
			{
				return $_checkPath;
			}

			$_checkPath = $_sourcePath . '/hybridauth-' . $_name . '/Providers/' . $_fileName;

			if ( file_exists( $_checkPath ) )
			{
				return $_checkPath;
			}
		}

		return false;
	}

	/**
	 * @return array
	 */
	public static function getHybridAuthConfig()
	{
		$_providers = Provider::model()->findAll();

		if ( empty( $_providers ) )
		{
			return array();
		}

		$_auth = array();

		foreach ( $_providers as $_provider )
		{
			$_config = $_provider->config_text;
			$_name = $_provider->provider_name;

			$_auth['providers'][$_name] = array(
				'provider_id' => $_provider->id,
				'api_name'    => $_provider->api_name,
				'enabled'     => true,
				'keys'        => array(
					'id'     => Option::get( $_config, 'client_id' ),
					'secret' => Option::get( $_config, 'client_secret' ),
				)
			);

			if ( isset( $_config['consumer_key'] ) )
			{
				$_auth['providers'][$_name]['key'] = Option::get( $_config, 'consumer_key' );
			}

			if ( !empty( $_config['scope'] ) )
			{
				$_auth['providers'][$_name]['scope'] = $_config['scope'];
			}

			unset( $_provider, $_config );
		}

		unset( $_providers );

		return $_auth;
	}
}