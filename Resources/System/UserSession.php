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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Yii\Models\App;
use DreamFactory\Platform\Yii\Models\Config;
use DreamFactory\Platform\Yii\Models\Role;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;
use Kisma\Core\Utility\Sql;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\UnauthorizedException;
use DreamFactory\Platform\Interfaces\PermissionTypes;
use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\Utilities;
use Swagger\Annotations as SWG;

/**
 * UserSession
 * DSP user session
 *
 * @package
 * @category
 *
 * @SWG\Resource(
 *   resourcePath="/user"
 * )
 *
 * @SWG\Model(id="Session",
 * @SWG\Property(name="id",type="string",description="Identifier for the current user."),
 * @SWG\Property(name="email",type="string",description="Email address of the current user."),
 * @SWG\Property(name="first_name",type="string",description="First name of the current user."),
 * @SWG\Property(name="last_name",type="string",description="Last name of the current user."),
 * @SWG\Property(name="display_name",type="string",description="Full display name of the current user."),
 * @SWG\Property(name="is_sys_admin",type="boolean",description="Is the current user a system administrator."),
 * @SWG\Property(name="last_login_date",type="string",description="Date and time of the last login for the current user."),
 * @SWG\Property(name="app_groups",type="Array",description="App groups and the containing apps."),
 * @SWG\Property(name="no_group_apps",type="Array",description="Apps that are not in any app groups."),
 * @SWG\Property(name="ticket",type="string",description="Timed ticket that can be used to start a separate session."),
 * @SWG\Property(name="ticket_expiry",type="string",description="Expiration time for the given ticket.")
 * )
 *
 * @SWG\Model(id="Login",
 * @SWG\Property(name="email",type="string"),
 * @SWG\Property(name="password",type="string")
 * )
 */
class UserSession extends BaseSystemRestResource
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var string
	 */
	protected static $_randKey;
	/**
	 * @var int
	 */
	protected static $_userId = null;
	/**
	 * @var array
	 */
	protected static $_cache = null;

	/**
	 * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
	 * @param array                                               $resources
	 */
	public function __construct( $consumer, $resources = array() )
	{
		//	For better security. Get a random string from this link: http://tinyurl.com/randstr and put it here
		static::$_randKey = static::$_randKey ? : Pii::db()->password;

		parent::__construct(
			$consumer,
			array(
				 'name'           => 'User Session',
				 'service_name'   => 'user',
				 'type'           => 'System',
				 'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
				 'api_name'       => 'session',
				 'description'    => 'Resource for a user to manage their session.',
				 'is_active'      => true,
				 'resource_array' => $resources,
				 'verb_aliases'   => array(
					 static::Put => static::Post,
				 )
			)
		);
	}

	// REST interface implementation

	/**
	 * @return bool
	 */
	protected function _handleGet()
	{
		$_ticket = FilterInput::request( 'ticket' );

		return $this->userSession( $_ticket );
	}

	/**
	 * @return array|bool|void
	 */
	protected function _handlePost()
	{
		$_data = RestData::getPostDataAsArray();

		//$password = Utilities::decryptPassword($password);

		return $this->userLogin( Option::get( $_data, 'email' ), Option::get( $_data, 'password' ) );
	}

	/**
	 * @return array|bool|void
	 */
	protected function _handleDelete()
	{
		$this->userLogout();

		return array( 'success' => true );
	}

	//-------- User Operations ------------------------------------------------

	/**
	 * userSession refreshes an existing session or
	 *     allows the SSO creation of a new session for external apps via timed ticket
	 *
	 * @SWG\Api(
	 *   path="/user/session", description="Operations on a user's session.",
	 * @SWG\Operations(
	 * @SWG\Operation(
	 *       httpMethod="GET", summary="Retrieve the current user session information.",
	 *       notes="Calling this refreshes the current session, or returns an error for timed-out or invalid sessions.",
	 *       responseClass="Session", nickname="getSession",
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *       )
	 *     )
	 *   )
	 * )
	 *
	 * @param string $ticket
	 *
	 * @return mixed
	 * @throws \Exception
	 * @throws UnauthorizedException
	 */
	protected static function userSession( $ticket = null )
	{
		//	Process ticket
		$_user = static::_validateTicket( $ticket );

		try
		{
			$_result = static::generateSessionDataFromUser( null, $_user );

			//	Additional stuff for session - launchpad mainly
			return static::addSessionExtras( $_result, $_user->is_sys_admin, true );
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * @param string $ticket
	 *
	 * @throws \DreamFactory\Platform\Exceptions\UnauthorizedException
	 * @throws \Exception
	 * @return User
	 */
	protected static function _validateTicket( $ticket )
	{
		if ( empty( $ticket ) )
		{
			try
			{
				$_userId = static::validateSession();
			}
			catch ( \Exception $ex )
			{
				static::userLogout();

				//	Special case for guest user
				$_config = ResourceStore::model( 'config' )->with(
							   'guest_role.role_service_accesses',
							   'guest_role.apps',
							   'guest_role.services'
						   )->find();

				if ( !empty( $_config ) )
				{
					if ( $_config->allow_guest_user )
					{
						$_result = static::generateSessionDataFromRole( null, $_config->getRelated( 'guest_role' ) );

						// additional stuff for session - launchpad mainly
						return static::addSessionExtras( $_result, false, true );
					}
				}

				//	Otherwise throw original exception
				throw $ex;
			}
		}
		else
		{
			$_creds = Utilities::decryptCreds( $ticket, "gorilla" );
			$_pieces = explode( ',', $_creds );
			$_userId = $_pieces[0];
			$_timestamp = $_pieces[1];
			$_curTime = time();
			$_lapse = $_curTime - $_timestamp;

			if ( empty( $_userId ) || ( $_lapse > 300 ) )
			{
				// only lasts 5 minutes
				static::userLogout();
				throw new UnauthorizedException( 'Ticket expired' );
			}
		}

		//	Load user...
		try
		{
			$_user = ResourceStore::model( 'user' )->with( 'role.role_service_accesses', 'role.apps', 'role.services' )->findByPk( $_userId );

			if ( empty( $_user ) )
			{
				throw new UnauthorizedException( 'Invalid credentials' );
			}

			return $_user;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 *
	 * @SWG\Api(
	 *           path="/user/session", description="Operations on a user's session.",
	 * @SWG\Operations(
	 * @SWG\Operation(
	 *           httpMethod="POST", summary="Login and create a new user session.",
	 *           notes="Calling this creates a new session and logs in the user.",
	 *           responseClass="Session", nickname="login",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *           name="credentials", description="Data containing name-value pairs used for logging into the system.",
	 *           paramType="body", required="true", allowMultiple=false, dataType="Login"
	 *         )
	 *       ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *       )
	 *     )
	 *   )
	 * )
	 *
	 *
	 * @param string $email
	 * @param string $password
	 *
	 * @throws UnauthorizedException
	 * @throws InternalServerErrorException
	 * @throws BadRequestException
	 * @return array
	 */
	public static function userLogin( $email, $password )
	{
		if ( empty( $email ) )
		{
			throw new BadRequestException( "Login request is missing required email." );
		}

		if ( empty( $password ) )
		{
			throw new BadRequestException( "Login request is missing required password." );
		}

		$_model = new \LoginForm();
		$_model->username = $email;
		$_model->password = $password;
		$_model->setDrupalAuth( false );

		if ( !$_model->authenticate( 'password', 'authenticate' ) || !$_model->login() )
		{
			throw new UnauthorizedException( 'The credentials supplied do not match system records.' );
		}

		if ( null === ( $_user = $_model->getIdentity()->getUser() ) )
		{
			// bad user object
			throw new InternalServerErrorException( 'The user session contains no data.' );
		}

		if ( 'y' !== $_user->confirm_code )
		{
			throw new BadRequestException( 'Login registration has not been confirmed.' );
		}

		$_result = static::generateSessionDataFromUser( $_user->id, $_user );

		// write back login datetime
		$_user->update( array( 'last_login_date' => date( 'c' ) ) );

		static::$_userId = $_user->id;

		// 	Additional stuff for session - launchpad mainly
		return static::addSessionExtras( $_result, $_user->is_sys_admin, true );
	}

	/**
	 * @SWG\Api(
	 *   path="/user/session", description="Operations on a user's session.",
	 * @SWG\Operations(
	 * @SWG\Operation(
	 *       httpMethod="DELETE", summary="Logout and destroy the current user session.",
	 *       notes="Calling this deletes the current session and logs out the user.",
	 *       responseClass="Success", nickname="logout",
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *       )
	 *     )
	 *   )
	 * )
	 *
	 */
	public static function userLogout()
	{
		Pii::user()->logout();
	}

	/**
	 * @param      $userId
	 * @param User $user
	 *
	 * @throws \DreamFactory\Platform\Exceptions\UnauthorizedException
	 * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
	 * @internal param int $user_id
	 * @return array
	 */
	public static function generateSessionDataFromUser( $userId, $user = null )
	{
		static $_fields = array( 'id', 'display_name', 'first_name', 'last_name', 'email', 'is_sys_admin', 'last_login_date' );
		static $_appFields = array( 'id', 'api_name', 'is_active' );

		/** @var User $_user */
		$_user = $user ? : ResourceStore::model( 'user' )->with( 'role.role_service_accesses', 'role.apps', 'role.services' )->findByPk( $userId );

		if ( empty( $_user ) )
		{
			throw new UnauthorizedException( 'The user with id ' . $userId . ' is invalid.' );
		}

		$_email = $_user->email;

		if ( !$_user->is_active )
		{
			throw new ForbiddenException( "The user with email '$_email' is not currently active." );
		}

		$_isAdmin = $_user->getAttribute( 'is_sys_admin' );
		$_defaultAppId = $_user->getAttribute( 'default_app_id' );
		$_data = $_userInfo = $_user->getAttributes( $_fields );

		$_perms = $_roleApps = $_allowedApps = array();

		if ( !$_isAdmin )
		{
			if ( !$_user->role )
			{
				throw new ForbiddenException( "The user '$_email' has not been assigned a role." );
			}

			if ( !$_user->role->is_active )
			{
				throw new ForbiddenException( "The role this user is assigned to is not currently active." );
			}

			if ( !isset( $_defaultAppId ) )
			{
				$_defaultAppId = $_user->role->default_app_id;
			}

			$_role = $_user->role->attributes;

			/**
			 * @var \App[] $_apps
			 */
			if ( $_user->role->apps )
			{
				foreach ( $_apps as $_app )
				{
					$_roleApps[] = $_app->getAttributes( $_appFields );

					if ( $_app->is_active )
					{
						$_allowedApps[] = $_app;
					}
				}
			}

			$_role['apps'] = $_roleApps;
			$_role['services'] = $_user->getRoleServicePermissions();
			$_userInfo['role'] = $_role;
		}

		return array(
			'public'         => $_userInfo,
			'data'           => $_data,
			'allowed_apps'   => $_allowedApps,
			'default_app_id' => $_defaultAppId
		);
	}

	/**
	 * @param int  $roleId
	 * @param Role $role
	 *
	 * @throws UnauthorizedException
	 * @throws ForbiddenException
	 * @return array
	 */
	public static function generateSessionDataFromRole( $roleId, $role = null )
	{
		static $_appFields = array( 'id', 'api_name', 'is_active' );

		/** @var Role $_role */
		$_role = $role ? : ResourceStore::model( 'role' )->with( 'role_service_accesses', 'apps', 'services' )->findByPk( $roleId );

		if ( empty( $_role ) )
		{
			throw new UnauthorizedException( "The role with id $roleId does not exist in the system." );
		}

		if ( !$_role->is_active )
		{
			throw new ForbiddenException( "The role '$role->name' is not currently active." );
		}

		$_allowedApps = $_data = $_userInfo = array();
		$_defaultAppId = $_role->default_app_id;
		$_roleData = $role->attributes;

		/**
		 * @var \App[] $_apps
		 */
		if ( $_role->apps )
		{
			$_roleApps = array();

			/** @var App $_app */
			foreach ( $_role->apps as $_app )
			{
				$_roleApps[] = $_app->getAttributes( $_appFields );

				if ( $_app->is_active )
				{
					$_allowedApps[] = $_app;
				}
			}

			$_roleData['apps'] = $_roleApps;
		}

		$_roleData['services'] = $_role->getRoleServicePermissions();
		$_userInfo['role'] = $_roleData;

		return array(
			'public'         => $_userInfo,
			'data'           => $_data,
			'allowed_apps'   => $_allowedApps,
			'default_app_id' => $_defaultAppId
		);
	}

	/**
	 * @throws UnauthorizedException
	 * @return string
	 */
	public static function validateSession()
	{
		if ( !Pii::guest() && !Pii::getState( 'df_authenticated', false ) )
		{
			return Pii::user()->getId();
		}

		// helper for non-browser-managed sessions
		$_sessionId = FilterInput::server( 'HTTP_X_DREAMFACTORY_SESSION_TOKEN' );
		//Log::debug('passed in session ' . $_sessionId);

		if ( !empty( $_sessionId ) )
		{
			session_write_close();
			session_id( $_sessionId );

			if ( session_start() )
			{
				if ( !Pii::guest() && false === Pii::getState( 'df_authenticated', false ) )
				{
					return Pii::user()->getId();
				}
			}
			else
			{
				Log::error( 'Failed to start session from header ' . $_sessionId );
			}
		}

		throw new UnauthorizedException( "There is no valid session for the current request." );
	}

	/**
	 * @return bool
	 */
	public static function isSystemAdmin()
	{
		static::_checkCache();

		return Scalar::boolval( Option::getDeep( static::$_cache, 'public', 'is_sys_admin' ) );
	}

	/**
	 * @param        $request
	 * @param        $service
	 * @param string $component
	 *
	 * @throws ForbiddenException
	 * @throws BadRequestException
	 */
	public static function checkSessionPermission( $request, $service, $component = null )
	{
		static::_checkCache();

		$_public = Option::get( static::$_cache, 'public' );

		if ( false !== ( $_admin = Option::getBool( $_public, 'is_sys_admin' ) ) )
		{
			return; // no need to check role
		}

		if ( null === ( $_roleInfo = Option::get( $_public, 'role' ) ) )
		{
			// no role assigned, if not sys admin, denied service
			throw new ForbiddenException( "A valid user role or system administrator is required to access services." );
		}

		// check if app allowed in role
		if ( null !== ( $_appName = Option::get( $GLOBALS, 'app_name' ) ) )
		{
			throw new BadRequestException( 'A valid application name is required to access services.' );
		}

		$_found = false;

		/** @var App $_app */
		foreach ( Option::clean( Option::get( $_roleInfo, 'apps' ) ) as $_app )
		{
			if ( 0 == strcasecmp( $_appName, Option::get( $_app, 'api_name' ) ) )
			{
				$_found = true;
				break;
			}
		}

		if ( !$_found )
		{
			throw new ForbiddenException( "Access to application '$_appName' is not provisioned for this user's role." );
		}

		$_services = Option::clean( Option::get( $_roleInfo, 'services' ) );

		if ( !is_array( $_services ) || empty( $services ) )
		{
			throw new ForbiddenException( "Access to service '$service' is not provisioned for this user's role." );
		}

		$allAllowed = false;
		$allFound = false;
		$serviceAllowed = false;
		$serviceFound = false;

		foreach ( $_services as $svcInfo )
		{
			$theService = Option::get( $svcInfo, 'service', '' );
			$theAccess = Option::get( $svcInfo, 'access', '' );

			if ( 0 == strcasecmp( $service, $theService ) )
			{
				$theComponent = Option::get( $svcInfo, 'component' );
				if ( !empty( $component ) )
				{
					if ( 0 == strcasecmp( $component, $theComponent ) )
					{
						if ( !static::isAllowed( $request, $theAccess ) )
						{
							$msg = ucfirst( $request ) . " access to component '$component' of service '$service' ";
							$msg .= "is not allowed by this user's role.";
							throw new ForbiddenException( $msg );
						}

						return; // component specific found and allowed, so bail
					}
					elseif ( empty( $theComponent ) || ( '*' == $theComponent ) )
					{
						$serviceAllowed = static::isAllowed( $request, $theAccess );
						$serviceFound = true;
					}
				}
				else
				{
					if ( empty( $theComponent ) || ( '*' == $theComponent ) )
					{
						if ( !static::isAllowed( $request, $theAccess ) )
						{
							$msg = ucfirst( $request ) . " access to service '$service' ";
							$msg .= "is not allowed by this user's role.";
							throw new ForbiddenException( $msg );
						}

						return; // service specific found and allowed, so bail
					}
				}
			}
			elseif ( empty( $theService ) || ( '*' == $theService ) )
			{
				$allAllowed = static::isAllowed( $request, $theAccess );
				$allFound = true;
			}
		}

		if ( $serviceFound )
		{
			if ( $serviceAllowed )
			{
				return; // service found and allowed, so bail
			}
		}
		elseif ( $allFound )
		{
			if ( $allAllowed )
			{
				return; // all services found and allowed, so bail
			}
		}

		$msg = ucfirst( $request ) . " access to ";
		if ( !empty( $component ) )
		{
			$msg .= "component '$component' of ";
		}

		$msg .= "service '$service' is not allowed by this user's role.";

		throw new ForbiddenException( $msg );
	}

	/**
	 * @param $request
	 * @param $access
	 *
	 * @return bool
	 */
	protected static function isAllowed( $request, $access )
	{
		switch ( $request )
		{
			case 'read':
				switch ( $access )
				{
					case PermissionTypes::READ_ONLY:
					case PermissionTypes::READ_WRITE:
					case PermissionTypes::FULL_ACCESS:
						return true;
				}
				break;

			case 'create':
			case 'update':
				switch ( $access )
				{
					case PermissionTypes::WRITE_ONLY:
					case PermissionTypes::READ_WRITE:
					case PermissionTypes::FULL_ACCESS:
						return true;
				}
				break;

			case 'delete':
				switch ( $access )
				{
					case PermissionTypes::FULL_ACCESS:
						return true;
				}
				break;
		}

		return false;
	}

	/**
	 * @param $_userId
	 */
	public static function setCurrentUserId( $_userId )
	{
		if ( !Pii::guest() && false === Pii::getState( 'df_authenticated' ) )
		{
			static::$_userId = $_userId;
		}

		return $_userId;
	}

	/**
	 * @param mixed $inquirer For future use
	 *
	 * @return int|null
	 */
	public static function getCurrentUserId( $inquirer = null )
	{
		if ( !empty( static::$_userId ) )
		{
			return static::$_userId;
		}

		if ( !Pii::guest() && false === Pii::getState( 'df_authenticated' ) )
		{
			return static::$_userId = Pii::user()->getId();
		}

		return static::$_userId = null;
	}

	/**
	 * @throws \Exception
	 */
	protected static function _checkCache()
	{
		if ( empty( static::$_cache ) )
		{
			try
			{
				$_userId = static::validateSession();
				static::$_cache = static::generateSessionDataFromUser( $_userId );
			}
			catch ( \Exception $ex )
			{
				// special case for possible guest user
				$_config = ResourceStore::model( 'config' )->with(
							   'guest_role.role_service_accesses',
							   'guest_role.apps',
							   'guest_role.services'
						   )->find();

				if ( !empty( $_config ) )
				{
					if ( DataFormat::boolval( $_config->allow_guest_user ) )
					{
						static::$_cache = static::generateSessionDataFromRole( null, $_config->getRelated( 'guest_role' ) );

						return;
					}
				}

				// otherwise throw original exception
				throw $ex;
			}
		}
	}

	/**
	 * @param array $session
	 * @param bool  $is_sys_admin
	 * @param bool  $add_apps
	 *
	 * @return array
	 */
	public static function addSessionExtras( $session, $is_sys_admin = false, $add_apps = false )
	{
		$data = Option::get( $session, 'data' );
		$_userId = Option::get( $data, 'id', '' );
		$_timestamp = time();
		$ticket = Utilities::encryptCreds( "$_userId,$_timestamp", "gorilla" );
		$data['ticket'] = $ticket;
		$data['ticket_expiry'] = time() + ( 5 * 60 );
		$data['session_id'] = session_id();

		if ( $add_apps )
		{
			$appFields = 'id,api_name,name,description,is_url_external,launch_url,requires_fullscreen,allow_fullscreen_toggle,toggle_location';
			/**
			 * @var \App[] $_apps
			 */
			$_apps = Option::get( $session, 'allowed_apps', array() );
			if ( $is_sys_admin )
			{
				$_apps = ResourceStore::model( 'app' )->findAll( 'is_active = :ia', array( ':ia' => 1 ) );
			}
			/**
			 * @var \AppGroup[] $theGroups
			 */
			$theGroups = ResourceStore::model( 'app_group' )->with( 'apps' )->findAll();
			$appGroups = array();
			$noGroupApps = array();
			$_defaultAppId = Option::get( $session, 'default_app_id' );
			foreach ( $_apps as $app )
			{
				$appId = $app->id;
				$tempGroups = $app->getRelated( 'app_groups' );
				$appData = $app->getAttributes( explode( ',', $appFields ) );
				$appData['is_default'] = ( $_defaultAppId === $appId );
				$found = false;
				foreach ( $theGroups as $g_key => $group )
				{
					$groupId = $group->id;
					$groupData = ( isset( $appGroups[$g_key] ) ) ? $appGroups[$g_key] : $group->getAttributes( array( 'id', 'name', 'description' ) );
					foreach ( $tempGroups as $tempGroup )
					{
						if ( $tempGroup->id === $groupId )
						{
							$found = true;
							$temp = Option::get( $groupData, 'apps', array() );
							$temp[] = $appData;
							$groupData['apps'] = $temp;
						}
					}
					$appGroups[$g_key] = $groupData;
				}
				if ( !$found )
				{
					$noGroupApps[] = $appData;
				}
			}
			// clean out any empty groups
			foreach ( $appGroups as $g_key => $group )
			{
				if ( !isset( $group['apps'] ) )
				{
					unset( $appGroups[$g_key] );
				}
			}
			$data['app_groups'] = array_values( $appGroups ); // reset indexing
			$data['no_group_apps'] = $noGroupApps;
		}

		return $data;
	}
}