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

use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;

/**
 * Config.php
 * The system configuration model for the DSP
 *
 * Columns
 *
 * @property string              $db_version
 * @property boolean             $allow_open_registration
 * @property integer             $open_reg_role_id
 * @property integer             $open_reg_email_service_id
 * @property integer             $open_reg_email_template_id
 * @property integer             $invite_email_service_id
 * @property integer             $invite_email_template_id
 * @property integer             $password_email_service_id
 * @property integer             $password_email_template_id
 * @property boolean             $allow_guest_user
 * @property integer             $guest_role_id
 * @property string              $editable_profile_fields
 * @property string              $custom_settings
 * @property array               $lookup_keys
 *
 * Relations
 *
 * @property Role                $open_reg_role
 * @property Service             $open_reg_email_service
 * @property EmailTemplate       $open_reg_email_template
 * @property Service             $invite_email_service
 * @property EmailTemplate       $invite_email_template
 * @property Service             $password_email_service
 * @property EmailTemplate       $password_email_template
 * @property Role                $guest_role
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
					 'secureAttributes'   => array(
						 'lookup_keys',
					 ),
					 'insecureAttributes' => array(
						 'custom_settings',
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
			array( 'db_version', 'length', 'max' => 32 ),
			array( 'editable_profile_fields', 'length', 'max' => 255 ),
			array( 'allow_open_registration, allow_guest_user', 'boolean' ),
			array(
				'open_reg_role_id, open_reg_email_service_id, open_reg_email_template_id, ' .
				'invite_email_service_id, invite_email_template_id, ' .
				'password_email_service_id, password_email_template_id, ' .
				'guest_role_id',
				'numerical',
				'integerOnly' => true
			),
			array( 'custom_settings', 'safe' ),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		$_relations = array(
			'open_reg_role'           => array( self::BELONGS_TO, __NAMESPACE__ . '\\Role', 'open_reg_role_id' ),
			'open_reg_email_service'  => array( self::BELONGS_TO, __NAMESPACE__ . '\\Service', 'open_reg_email_service_id' ),
			'open_reg_email_template' => array( self::BELONGS_TO, __NAMESPACE__ . '\\EmailTemplate', 'open_reg_email_template_id' ),
			'invite_email_service'    => array( self::BELONGS_TO, __NAMESPACE__ . '\\Service', 'invite_email_service_id' ),
			'invite_email_template'   => array( self::BELONGS_TO, __NAMESPACE__ . '\\EmailTemplate', 'invite_email_template_id' ),
			'password_email_service'  => array( self::BELONGS_TO, __NAMESPACE__ . '\\Service', 'password_email_service_id' ),
			'password_email_template' => array( self::BELONGS_TO, __NAMESPACE__ . '\\EmailTemplate', 'password_email_template_id' ),
			'guest_role'              => array( self::BELONGS_TO, __NAMESPACE__ . '\\Role', 'guest_role_id' ),
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
				'db_version'                 => 'Db Version',
				'allow_open_registration'    => 'Allow Open Registration',
				'open_reg_role_id'           => 'Open Registration Default Role Id',
				'open_reg_email_service_id'  => 'Open Registration Email Service',
				'open_reg_email_template_id' => 'Open Registration Email Template',
				'invite_email_service_id'    => 'Invitation Email Service',
				'invite_email_template_id'   => 'Invitation Email Template',
				'password_email_service_id'  => 'Password Reset Email Service',
				'password_email_template_id' => 'Password Reset Email Template',
				'allow_guest_user'           => 'Allow Guest User',
				'guest_role_id'              => 'Guest Role Id',
				'editable_profile_fields'    => 'Editable Profile Fields',
				'custom_settings'            => 'Custom System-Level Settings',
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
					 'open_reg_email_service_id',
					 'open_reg_email_template_id',
					 'invite_email_service_id',
					 'invite_email_template_id',
					 'password_email_service_id',
					 'password_email_template_id',
					 'allow_guest_user',
					 'guest_role_id',
					 'editable_profile_fields',
					 'custom_settings',
					 'lookup_keys',
				),
				$columns
			),
			// hide these from the general public
			$hidden
		);
	}

	/**
	 * @throws InternalServerErrorException
	 * @returns $this
	 */
	public static function load()
	{
		if ( null === ( $_config = Config::model()->find() ) )
		{
			throw new InternalServerErrorException( 'Unable to locate DSP configuration. Bailing ...' );
		}

		return $_config;
	}

	/** {@InheritDoc} */
	protected function beforeValidate()
	{
		if ( empty( $this->guest_role_id ) )
		{
			$this->guest_role_id = null;
		}
		if ( empty( $this->open_reg_role_id ) )
		{
			$this->open_reg_role_id = null;
		}
		if ( empty( $this->open_reg_email_service_id ) )
		{
			$this->open_reg_email_service_id = null;
		}
		if ( empty( $this->open_reg_email_template_id ) )
		{
			$this->open_reg_email_template_id = null;
		}
		if ( empty( $this->password_email_service_id ) )
		{
			$this->password_email_service_id = null;
		}
		if ( empty( $this->password_email_template_id ) )
		{
			$this->password_email_template_id = null;
		}
		if ( empty( $this->invite_email_service_id ) )
		{
			$this->invite_email_service_id = null;
		}
		if ( empty( $this->invite_email_template_id ) )
		{
			$this->invite_email_template_id = null;
		}

		return parent::beforeValidate();
	}
}