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

use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Resources\User\Session;
use Kisma\Core\Exceptions\StorageException;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Sql;

/**
 * User.php
 * The system user model for the DSP
 *
 * Columns
 *
 * @property string     $email
 * @property string     $password
 * @property string     $first_name
 * @property string     $last_name
 * @property string     $display_name
 * @property string     $phone
 * @property integer    $is_active
 * @property integer    $is_sys_admin
 * @property string     $confirm_code
 * @property integer    $default_app_id
 * @property integer    $role_id
 * @property string     $security_question
 * @property string     $security_answer
 * @property int        $user_source
 * @property array      $user_data
 * @property string     $last_login_date
 *
 * Relations
 *
 * @property App[]      $apps_created
 * @property App[]      $apps_modified
 * @property AppGroup[] $app_groups_created
 * @property AppGroup[] $app_groups_modified
 * @property Role[]     $roles_created
 * @property Role[]     $roles_modified
 * @property Service[]  $services_created
 * @property Service[]  $services_modified
 * @property User[]     $users_created
 * @property User[]     $users_modified
 * @property App        $default_app
 * @property Role       $role
 */
class User extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'user';
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
						 'user_data',
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
		$_rules = array(
			array( 'email, display_name', 'required' ),
			array( 'email, display_name', 'unique', 'allowEmpty' => false, 'caseSensitive' => false ),
			array( 'email', 'email' ),
			array( 'email', 'length', 'max' => 255 ),
			array( 'default_app_id, user_source, role_id', 'numerical', 'integerOnly' => true ),
			array( 'password, first_name, last_name, security_answer', 'length', 'max' => 64 ),
			array( 'phone', 'length', 'max' => 32 ),
			array( 'confirm_code, display_name, security_question', 'length', 'max' => 128 ),
			array( 'user_source, user_data, is_active, is_sys_admin, user_source', 'safe' ),
		);

		return array_merge( parent::rules(), $_rules );
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		$_relations = array(
			'apps_created'        => array( self::HAS_MANY, __NAMESPACE__ . '\\App', 'created_by_id' ),
			'apps_modified'       => array( self::HAS_MANY, __NAMESPACE__ . '\\App', 'last_modified_by_id' ),
			'app_groups_created'  => array( self::HAS_MANY, __NAMESPACE__ . '\\AppGroup', 'created_by_id' ),
			'app_groups_modified' => array( self::HAS_MANY, __NAMESPACE__ . '\\AppGroup', 'last_modified_by_id' ),
			'roles_created'       => array( self::HAS_MANY, __NAMESPACE__ . '\\Role', 'created_by_id' ),
			'roles_modified'      => array( self::HAS_MANY, __NAMESPACE__ . '\\Role', 'last_modified_by_id' ),
			'services_created'    => array( self::HAS_MANY, __NAMESPACE__ . '\\Service', 'created_by_id' ),
			'services_modified'   => array( self::HAS_MANY, __NAMESPACE__ . '\\Service', 'last_modified_by_id' ),
			'users_created'       => array( self::HAS_MANY, __NAMESPACE__ . '\\User', 'created_by_id' ),
			'users_modified'      => array( self::HAS_MANY, __NAMESPACE__ . '\\User', 'last_modified_by_id' ),
			'default_app'         => array( self::BELONGS_TO, __NAMESPACE__ . '\\App', 'default_app_id' ),
			'role'                => array( self::BELONGS_TO, __NAMESPACE__ . '\\Role', 'role_id' ),
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
		$_myLabels = array(
			'email'             => 'Email',
			'password'          => 'Password',
			'first_name'        => 'First Name',
			'last_name'         => 'Last Name',
			'display_name'      => 'Display Name',
			'phone'             => 'Phone',
			'is_active'         => 'Is Active',
			'is_sys_admin'      => 'Is System Admin',
			'confirm_code'      => 'Confirmation Code',
			'default_app_id'    => 'Default App',
			'role_id'           => 'Role',
			'security_question' => 'Security Question',
			'security_answer'   => 'Security Answer',
			'user_source'       => 'User Source',
			'user_data'         => 'User Data',
		);

		return parent::attributeLabels( array_merge( $_myLabels, $additionalLabels ) );
	}

	/** {@InheritDoc} */
	public function setAttributes( $values, $safeOnly = true )
	{
		if ( isset( $values['password'] ) )
		{
			if ( !empty( $values['password'] ) )
			{
				$this->password = \CPasswordHelper::hashPassword( $values['password'] );
			}

			unset( $values['password'] );
		}

		if ( isset( $values['security_answer'] ) )
		{
			if ( !empty( $values['security_answer'] ) )
			{
				$this->security_answer = \CPasswordHelper::hashPassword( $values['security_answer'] );
			}

			unset( $values['security_answer'] );
		}

		parent::setAttributes( $values, $safeOnly );
	}

	/** {@InheritDoc} */
	protected function beforeValidate()
	{
		if ( empty( $this->confirm_code ) && ( !empty( $this->password ) ) )
		{
			$this->confirm_code = 'y';
		}

		if ( $this->isNewRecord )
		{
			if ( empty( $this->first_name ) )
			{
				$this->first_name = strstr( $this->email, '@', true );
			}

			if ( empty( $this->display_name ) )
			{
				$this->display_name = trim( $this->first_name . ' ' . $this->last_name );
			}
		}

		return parent::beforeValidate();
	}

	/** {@InheritDoc} */
	protected function beforeDelete()
	{
		$_id = $this->getPrimaryKey();

		//	Make sure you don't delete yourself
		if ( $_id == Session::getCurrentUserId() )
		{
			throw new StorageException( 'The currently logged in user may not be deleted.' );
		}

		//	Check and make sure this is not the last admin user
		if ( $this->is_sys_admin && !static::model()->count( 'is_sys_admin = :is AND id != :id', array( ':is' => 1, ':id' => $_id ) ) )
		{
			throw new StorageException( 'There must be at least one administrative account. This one may not be deleted.' );
		}

		return parent::beforeDelete();
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
		//	Don't show these
		$hidden = array( 'password', 'security_question', 'security_answer' ) + $hidden;

		$_myColumns = array_merge(
			array(
				 'display_name',
				 'first_name',
				 'last_name',
				 'email',
				 'phone',
				 'is_active',
				 'is_sys_admin',
				 'role_id',
				 'default_app_id',
				 'user_source',
				 'user_data',
			),
			$columns
		);

		if ( Session::isSystemAdmin() && !in_array( 'confirm_code', $_myColumns ) )
		{
			$_myColumns[] = 'confirm_code';
		}

		return parent::getRetrievableAttributes(
			$requested,
			$_myColumns,
			$hidden
		);
	}

	/**
	 * @param string $userName
	 * @param string $password
	 *
	 * @return bool|\User
	 */
	public static function authenticate( $userName, $password )
	{
		$_user = static::model()
				 ->with( 'role.role_service_accesses', 'role.apps', 'role.services' )
				 ->findByAttributes( array( 'email' => $userName ) );

		if ( empty( $_user ) || !\CPasswordHelper::verifyPassword( $password, $_user->password ) )
		{
			return false;
		}

		Log::debug( 'Platform user auth: ' . $userName );

		return $_user;
	}
}
