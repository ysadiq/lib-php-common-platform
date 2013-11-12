<?php
use DreamFactory\Platform\Yii\Models\ProviderUser;

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

use DreamFactory\Oasys\Components\GenericUser;
use DreamFactory\Oasys\Interfaces\ProviderLike;
use DreamFactory\Oasys\Oasys;
use DreamFactory\Platform\Enums\ProviderUserTypes;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Yii\Components\PlatformUserIdentity;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Exceptions\StorageException;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Log;

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
	//	Members
	//*************************************************************************

	/**
	 * @var Config This DSP's configuration
	 */
	protected static $_dspConfig;

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
			array( 'user_source, user_data, is_active, is_sys_admin', 'safe' ),
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
			'authorizations'      => array( self::HAS_MANY, __NAMESPACE__ . '\\ProviderUser', 'user_id' ),
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

		if ( !$this->isNewRecord )
		{
			$_id = $this->getPrimaryKey();
			if ( $_id == Session::getCurrentUserId() )
			{
				//	Make sure you don't remove yourself from admin, role, or deactivate yourself
				if ( isset( $values['is_sys_admin'] ) && ( $values['is_sys_admin'] != $this->is_sys_admin ) )
				{
					throw new StorageException( 'You can not change your own admin status.' );
				}
				if ( isset( $values['role_id'] ) && ( $values['role_id'] != $this->role_id ) )
				{
					throw new StorageException( 'You can not change your own role status.' );
				}
				if ( isset( $values['is_active'] ) && ( $values['is_active'] != $this->is_active ) )
				{
					throw new StorageException( 'You can not change your own active status.' );
				}
			}
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

	public static function getProfileAttributes( $writable = false )
	{
		$_fields = array(
			'first_name',
			'last_name',
			'display_name',
			'email',
			'phone',
			'security_question',
			'default_app_id',
		);

		if ( $writable )
		{
			$_fields[] = 'security_answer';
		}

		return $_fields;
	}

	/**
	 * @param string $userName
	 * @param string $password
	 *
	 * @return User
	 */
	public static function authenticate( $userName, $password )
	{
		$_user = static::model()
			->with( 'role.role_service_accesses', 'role.role_system_accesses', 'role.apps', 'role.services' )
			->findByAttributes( array( 'email' => $userName ) );

		if ( empty( $_user ) )
		{
			Log::error( 'Platform login fail: ' . $userName . ' NOT FOUND' );

			return false;
		}

		if ( !\CPasswordHelper::verifyPassword( $password, $_user->password ) )
		{
			if ( $password == sha1( $_user->email ) )
			{
				Log::info( 'Platform remote user auth (via email): ' . $userName );

				return $_user;
			}

			Log::error( 'Platform password verify fail: ' . $userName );

			return false;
		}

		Log::info( 'Platform local user auth (via password): ' . $userName );

		return $_user;
	}

	/**
	 * @param array $columns The columns to return in the permissions array
	 *
	 * @return array|null
	 */
	public function getRoleServicePermissions( $columns = null )
	{
		$_perms = null;

		if ( $this->hasRelated( 'role' ) && $this->role )
		{
			/**
			 * @var RoleServiceAccess[] $_permissions
			 * @var Service[]           $_services
			 */
			if ( $this->role->role_service_accesses )
			{
				/** @var Role $_perm */
				foreach ( $this->role->role_service_accesses as $_perm )
				{
					$_permServiceId = $_perm->service_id;
					$_temp = $_perm->getAttributes( $columns ? : array( 'service_id', 'component', 'access' ) );

					if ( $this->role->services )
					{
						foreach ( $this->role->services as $_service )
						{
							if ( $_permServiceId == $_service->id )
							{
								$_temp['service'] = $_service->api_name;
							}
						}
					}

					$_perms[] = $_temp;
				}
			}

			/**
			 * @var RoleSystemAccess[] $_permissions
			 */
			if ( $this->role->role_system_accesses )
			{
				/** @var Role $_perm */
				foreach ( $this->role->role_system_accesses as $_perm )
				{
					$_temp = $_perm->getAttributes( $columns ? : array( 'component', 'access' ) );
					$_temp['service'] = 'system';
					$_perms[] = $_temp;
				}
			}
		}

		return $_perms;
	}

	/**
	 * @param string $email
	 *
	 * @return User
	 */
	public static function getByEmail( $email )
	{
		return static::model()->find( 'email = :email',	array( ':email' => $email )	);
	}

	/**
	 * @param string       $email
	 * @param GenericUser  $profile
	 * @param string       $providerId
	 * @param ProviderLike $provider
	 * @param Provider     $providerModel
	 *
	 * @return \DreamFactory\Platform\Yii\Models\User
	 * @throws \CDbException
	 * @throws \Exception
	 * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
	 */
	protected static function _createRemoteLoginUser( $email, $profile, $providerId, $provider, $providerModel )
	{
		if ( null !== ( $_user = static::getByEmail( $email ) ) )
		{
			// Ensure that if user is admin that we allow remote admin logins
			if ( $_user->is_sys_admin && false === Pii::getParam( 'dsp.allow_admin_remote_logins', false ) )
			{
				throw new ForbiddenException( 'System administrators are not allowed to login from remote sources.' );
			}
		}
		else
		{
			//	New user!
			$_user = new self();

			$_userName = $profile->getPreferredUsername() ? : $profile->getDisplayName();

			$_user->is_active = true;
			$_user->is_sys_admin = false;
			$_user->user_source = $providerModel->id;
			$_user->display_name = $_userName . '@' . $providerId;
			$_user->email = $email;
			$_user->password = \CPasswordHelper::hashPassword( sha1( $email ) );
			$_user->confirm_code = Hasher::generateUnique( $email );
			$_user->phone = $profile->getPhoneNumber();
			$_user->first_name = $profile->getFirstName();
			$_user->last_name = $profile->getLastName();
		}

		//	Set the default role, if one isn't assigned .
		if ( empty( $_user->role_id ) )
		{
			$_user->role_id = static::$_dspConfig->open_reg_role_id;
		}

		$_data = $_user->user_data;

		if ( empty( $_data ) )
		{
			$_data = array();
		}

		$_data[$providerId . '.profile'] = $profile->toArray();

		//	Save the remote profile info... and then the row
		$_user->user_data = $_data;

		//	Stamp it
		$_user->last_login_date = date( 'c' );

		try
		{
			$_user->save();
		}
		catch ( \Exception $_ex )
		{
			Log::error( 'Exception siring remote login user > ' . $_ex->getMessage() );
			throw $_ex;
		}

		$_user->refresh();

		return $_user;
	}

	/**
	 * @param User         $user
	 * @param GenericUser  $profile
	 * @param ProviderLike $provider
	 * @param Provider     $providerModel
	 *
	 *
	 * @return \DreamFactory\Platform\Yii\Models\ProviderUser
	 * @throws \CDbException
	 * @throws \Exception
	 */
	protected static function _createRemoteLoginAuthorization( $user, $profile, $provider, $providerModel )
	{
		//	Create an authorization row for this dude...
		if ( null === ( $_providerUser = ProviderUser::model()->byUserProviderUserId( $user->id, $profile->getUserId() )->find() ) )
		{
			//	Create new authorization
			$_providerUser = new ProviderUser();
			$_providerUser->user_id = $user->id;
			$_providerUser->provider_user_id = $profile->getUserId();
			$_providerUser->provider_id = $providerModel->id;
			$_providerUser->account_type = ProviderUserTypes::REMOTE_LOGIN;
		}

		$_providerUser->last_use_date = date( 'c' );
		$_providerUser->auth_text = $provider->getConfig()->toArray();

		try
		{
			$_providerUser->save();
		}
		catch ( \CDbException $_ex )
		{
			Log::error( 'Exception saving remote login authorization > ' . $_ex->getMessage() );
			throw $_ex;
		}

		$_providerUser->refresh();

		return $_providerUser;
	}

	/**
	 * @param string       $providerId
	 * @param ProviderLike $provider
	 * @param Provider     $providerModel
	 *
	 * @return \DreamFactory\Platform\Yii\Models\User
	 * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
	 * @throws \Exception
	 */
	public static function remoteLoginRequest( $providerId, $provider, $providerModel )
	{
		if ( null === static::$_dspConfig )
		{
			static::$_dspConfig = Config::load();
		}

		//	Homogenize the provider's user profile
		$_profile = $provider->getUserData();

		//	Make sure this cat has an email address
		if ( null === ( $_email = $_profile->getEmailAddress() ) )
		{
			throw new ForbiddenException( 'Remote logins are not allowed without an email address.' );
		}

		//	Let's get retarded!
		$_user = $_providerUser = null;

		try
		{
			//	Step 1: Create new user or load existing
			$_user = static::_createRemoteLoginUser( $_email, $_profile, $providerId, $provider, $providerModel );

			/**
			 * Step 2: Create new authorization or update existing
			 *
			 * @var ProviderUser $_providerUser
			 */
			$_providerUser = static::_createRemoteLoginAuthorization( $_user, $_profile, $provider, $providerModel );
		}
		catch ( \Exception $_ex )
		{
			Log::error( 'Authorization exception > ' . $_ex->getMessage() );
			throw $_ex;
		}

		//	Step 3: Do the doo
		Pii::setState( $providerId . '.user_config', $_providerUser->auth_text );

		$_data = Oasys::getStore()->get( $providerId, array() );
		$_data['config'] = json_encode( $_providerUser->auth_text );
		Oasys::getStore()->set( $providerId, $_data );
		Oasys::getStore()->sync();

		$_identity = new PlatformUserIdentity( $_user->email, sha1( $_user->email ) );

		if ( !$_identity->authenticate() )
		{
			throw new ForbiddenException( 'Invalid credentials' );
		}

		if ( !Pii::user()->login( $_identity ) )
		{
			throw new ForbiddenException( 'User login failed.' );
		}

		return $_user;
	}
}
