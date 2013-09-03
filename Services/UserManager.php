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
namespace DreamFactory\Platform\Services;

use DreamFactory\Oasys\Enums\Flows;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\UnauthorizedException;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Platform\Yii\Models\Config;
use DreamFactory\Platform\Yii\Models\User;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Sql;

/**
 * UserManager
 * DSP user manager
 *
 */
class UserManager extends BaseSystemRestService
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var string
	 */
	protected static $_randKey;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Create a new UserManager
	 *
	 */
	public function __construct()
	{
		parent::__construct(
			array(
				 'name'        => 'User Session Management',
				 'apiName'     => 'user',
				 'type'        => 'User',
				 'type_id'     => PlatformServiceTypes::SYSTEM_SERVICE,
				 'description' => 'Service for a user to manage their session, profile and password.',
				 'is_active'   => true,
			)
		);

		//	For better security. Get a random string from this link: http://tinyurl.com/randstr and put it here
		static::$_randKey = \sha1( Pii::db()->password );
	}

	/**
	 * @return array
	 */
	protected function _listResources()
	{
		$resources = array(
			array( 'name' => 'session' ),
			array( 'name' => 'profile' ),
			array( 'name' => 'password' ),
			array( 'name' => 'challenge' ),
			array( 'name' => 'register' ),
			array( 'name' => 'confirm' ),
			array( 'name' => 'ticket' )
		);

		return array( 'resource' => $resources );
	}

	/**
	 *
	 * @return array|bool
	 * @throws BadRequestException
	 */
	protected function _handleResource()
	{
		switch ( $this->_resource )
		{
			case '':
				switch ( $this->_action )
				{
					case self::Get:
						return $this->_listResources();
						break;
					default:
						return false;
				}
				break;

			case 'session':
				//	Handle remote login
				if ( HttpMethod::Post == $this->_action && Pii::getParam( 'dsp.allow_remote_logins' ) )
				{
					$_provider = FilterInput::post( 'provider', null, FILTER_SANITIZE_STRING );

					if ( !empty( $_provider ) )
					{
						Pii::redirect( '/web/remoteLogin?pid=' . $_provider . '&flow=' . Flows::SERVER_SIDE );
					}
				}

				$obj = new Session( $this );
				$result = $obj->processRequest( null, $this->_action );
				break;

			case 'profile':
				switch ( $this->_action )
				{
					case self::Get:
						$result = $this->getProfile();
						break;
					case self::Post:
					case self::Put:
					case self::Patch:
					case self::Merge:
						$data = RestData::getPostDataAsArray();
						$result = $this->changeProfile( $data );
						break;
					default:
						return false;
				}
				break;

			case 'password':
				switch ( $this->_action )
				{
					case self::Post:
					case self::Put:
					case self::Patch:
					case self::Merge:
						$data = RestData::getPostDataAsArray();
						$oldPassword = Utilities::getArrayValue( 'old_password', $data, '' );
						//$oldPassword = Utilities::decryptPassword($oldPassword);
						$newPassword = Utilities::getArrayValue( 'new_password', $data, '' );
						//$newPassword = Utilities::decryptPassword($newPassword);
						$result = $this->changePassword( $oldPassword, $newPassword );
						break;
					default:
						return false;
				}
				break;

			case 'register':
				switch ( $this->_action )
				{
					case self::Post:
						$data = RestData::getPostDataAsArray();
						$firstName = Utilities::getArrayValue( 'first_name', $data, '' );
						$lastName = Utilities::getArrayValue( 'last_name', $data, '' );
						$displayName = Utilities::getArrayValue( 'display_name', $data, '' );
						$email = Utilities::getArrayValue( 'email', $data, '' );
						$result = $this->userRegister( $email, $firstName, $lastName, $displayName );
						break;
					default:
						return false;
				}
				break;

			case 'confirm':
				switch ( $this->_action )
				{
					case self::Post:
						$data = RestData::getPostDataAsArray();
						$code = Utilities::getArrayValue( 'code', $_REQUEST, '' );
						if ( empty( $code ) )
						{
							$code = Utilities::getArrayValue( 'code', $data, '' );
						}
						$email = Utilities::getArrayValue( 'email', $_REQUEST, '' );
						if ( empty( $email ) )
						{
							$email = Utilities::getArrayValue( 'email', $data, '' );
						}
						if ( empty( $email ) && !empty( $code ) )
						{
							throw new BadRequestException( "Missing required email or code for invitation." );
						}
						$newPassword = Utilities::getArrayValue( 'new_password', $data, '' );
						if ( empty( $newPassword ) )
						{
							throw new BadRequestException( "Missing required fields 'new_password'." );
						}
						if ( !empty( $code ) )
						{
							$result = $this->passwordResetByCode( $code, $newPassword );
						}
						else
						{
							$result = $this->passwordResetByEmail( $email, $newPassword );
						}
						break;
					default:
						return false;
				}
				break;

			case 'challenge':
				switch ( $this->_action )
				{
					case self::Get:
						$email = Utilities::getArrayValue( 'email', $_REQUEST, '' );
						$result = $this->getChallenge( $email );
						break;
					case self::Post:
					case self::Put:
					case self::Patch:
					case self::Merge:
						$data = RestData::getPostDataAsArray();
						$email = Utilities::getArrayValue( 'email', $_REQUEST, '' );
						if ( empty( $email ) )
						{
							$email = Utilities::getArrayValue( 'email', $data, '' );
						}
						$answer = Utilities::getArrayValue( 'security_answer', $data, '' );
						if ( !empty( $email ) && !empty( $answer ) )
						{
							$result = $this->userSecurityAnswer( $email, $answer );
						}
						else
						{
							throw new BadRequestException( "Missing required fields 'email' and 'security_answer'." );
						}
						break;
					default:
						return false;
				}
				break;
			case 'ticket':
				switch ( $this->_action )
				{
					case self::Get:
						$result = $this->userTicket();
						break;
					default:
						return false;
				}
				break;

			default:
				return false;
				break;
		}

		return $result;
	}

	//-------- User Operations ------------------------------------------------

	/**
	 * @param $email
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function userInvite( $email )
	{
		if ( empty( $email ) )
		{
			throw new BadRequestException( "The email field for invitation can not be empty." );
		}
		$theUser = User::model()->find( 'email=:email', array( ':email' => $email ) );
		if ( empty( $theUser ) )
		{
			throw new BadRequestException( "No user currently exists with the email '$email'." );
		}
		$confirmCode = $theUser->confirm_code;
		if ( 'y' == $confirmCode )
		{
			throw new BadRequestException( "User with email '$email' has already confirmed registration in the system." );
		}
		try
		{
			if ( empty( $confirmCode ) )
			{
				$confirmCode = static::_makeConfirmationMd5( $email );
				$record = array( 'confirm_code' => $confirmCode );
				$theUser->setAttributes( $record );
				$theUser->save();
			}

			// generate link
			$link = Pii::app()->createAbsoluteUrl( 'public/launchpad/confirm.html' );
			$link .= '?email=' . urlencode( $email ) . '&code=' . urlencode( $confirmCode );

			return $link;
		}
		catch ( \CDbException $ex )
		{
			throw new InternalServerErrorException( "Failed to store generated user invite!" );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Failed to generate user invite!\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param string $email
	 * @param string $first_name
	 * @param string $last_name
	 * @param string $display_name
	 *
	 * @throws BadRequestException
	 * @throws \Exception
	 * @return array
	 */
	public static function userRegister( $email, $first_name = '', $last_name = '', $display_name = '' )
	{
		if ( empty( $email ) )
		{
			throw new BadRequestException( "The email field for User can not be empty." );
		}

		$theUser = User::model()->find( 'email=:email', array( ':email' => $email ) );

		if ( null !== $theUser )
		{
			throw new BadRequestException( "A User already exists with the email '$email'." );
		}

		/** @var $config Config */
		if ( null === ( $config = Config::model()->find( array( 'select' => 'allow_open_registration, open_reg_role_id' ) ) ) )
		{
			throw new InternalServerErrorException( 'Unable to load configuration.' );
		}

		if ( $config->allow_open_registration )
		{
			throw new BadRequestException( "Open registration for user accounts is not currently active for this system." );
		}

		$roleId = $config->open_reg_role_id;
		$confirmCode = static::_makeConfirmationMd5( $email );

		// fill out the user fields for creation
		$temp = substr( $email, 0, strrpos( $email, '@' ) );
		$fields = array(
			'email'        => $email,
			'first_name'   => ( !empty( $first_name ) ) ? $first_name : $temp,
			'last_name'    => ( !empty( $last_name ) ) ? $last_name : $temp,
			'display_name' => ( !empty( $display_name ) )
				? $display_name
				: ( !empty( $first_name ) && !empty( $last_name ) ) ? $first_name . ' ' . $last_name : $temp,
			'role_id'      => $roleId,
			'confirm_code' => $confirmCode
		);
		try
		{
			$user = new User();
			$user->setAttributes( $fields );
			$user->save();
		}
		catch ( \CDbException $ex )
		{
			throw new InternalServerErrorException( "Failed to store new user!" );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Failed to register new user!\n{$ex->getMessage()}", $ex->getCode() );
		}

		return array( 'success' => true );
	}

	/**
	 * @param $code
	 *
	 * @throws BadRequestException
	 * @throws \Exception
	 * @return mixed
	 */
	public static function userConfirm( $code )
	{
		$theUser = User::model()->find( 'confirm_code=:cc', array( ':cc' => $code ) );
		if ( null === $theUser )
		{
			throw new BadRequestException( "Invalid confirm code." );
		}

		try
		{
			$theUser->setAttribute( 'confirm_code', 'y' );
			$theUser->save();
		}
		catch ( \CDbException $ex )
		{
			throw new InternalServerErrorException( "Failed to update user storage!" );
		}

		return array( 'success' => true );
	}

	/**
	 * @param $email
	 *
	 * @throws NotFoundException
	 * @throws ForbiddenException
	 * @return string
	 */
	public static function getChallenge( $email )
	{
		$theUser = User::model()->find( 'email=:email', array( ':email' => $email ) );
		if ( null === $theUser )
		{
			// bad email
			throw new NotFoundException( "The supplied email was not found in the system." );
		}
		if ( 'y' !== $theUser->getAttribute( 'confirm_code' ) )
		{
			throw new ForbiddenException( "Login registration has not been confirmed." );
		}
		$question = $theUser->getAttribute( 'security_question' );
		if ( !empty( $question ) )
		{
			return array( 'security_question' => $question );
		}
		else
		{
			throw new NotFoundException( 'No valid security question provisioned for this user.' );
		}
	}

	/**
	 * userTicket generates a SSO timed ticket for current valid session
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function userTicket()
	{
		try
		{
			$userId = Session::validateSession();
		}
		catch ( \Exception $ex )
		{
			Session::userLogout();
			throw $ex;
		}
		// regenerate new timed ticket
		$timestamp = time();
		$ticket = Utilities::encryptCreds( "$userId,$timestamp", "gorilla" );

		return array( 'ticket' => $ticket, 'ticket_expiry' => time() + ( 5 * 60 ) );
	}

	/**
	 * @param      $email
	 * @param bool $send_email
	 *
	 * @throws \Exception
	 * @return string
	 */
	public static function forgotPassword( $email, $send_email = false )
	{
		$theUser = User::model()->find( 'email=:email', array( ':email' => $email ) );
		if ( null === $theUser )
		{
			// bad email
			throw new NotFoundException( "The supplied email was not found in the system." );
		}
		if ( 'y' !== $theUser->confirm_code )
		{
			throw new BadRequestException( "Login registration has not been confirmed." );
		}
		if ( $send_email )
		{
			$email = $theUser->email;
			$fullName = $theUser->display_name;
			if ( !empty( $email ) && !empty( $fullName ) )
			{
//					static::sendResetPasswordLink( $email, $fullName );

				return array( 'success' => true );
			}
			else
			{
				throw new InternalServerErrorException( 'No valid email provisioned for this user.' );
			}
		}
		else
		{
			$question = $theUser->security_question;
			if ( !empty( $question ) )
			{
				return array( 'security_question' => $question );
			}
			else
			{
				throw new InternalServerErrorException( 'No valid security question provisioned for this user.' );
			}
		}
	}

	/**
	 * @param $email
	 * @param $answer
	 *
	 * @throws UnauthorizedException
	 * @throws \Exception
	 * @return mixed
	 */
	public static function userSecurityAnswer( $email, $answer )
	{
		$theUser = User::model()->find( 'email=:email', array( ':email' => $email ) );
		if ( null === $theUser )
		{
			// bad email
			throw new NotFoundException( "The supplied email was not found in the system." );
		}
		if ( 'y' !== $theUser->confirm_code )
		{
			throw new BadRequestException( "Login registration has not been confirmed." );
		}
		// validate answer
		if ( !\CPasswordHelper::verifyPassword( $answer, $theUser->security_answer ) )
		{
			throw new UnauthorizedException( "The challenge response supplied does not match system records." );
		}

		Pii::user()->setId( $theUser->id );
		$isSysAdmin = $theUser->is_sys_admin;
		$result = Session::generateSessionDataFromUser( null, $theUser );

		// write back login datetime
		$theUser->last_login_date = date( 'c' );
		$theUser->save();

		Session::setCurrentUserId( $theUser->id );

		// additional stuff for session - launchpad mainly
		return Session::addSessionExtras( $result, $isSysAdmin, true );
	}

	/**
	 * @param string $code
	 * @param string $new_password
	 *
	 * @throws \Exception
	 * @return mixed
	 */
	public static function passwordResetByCode( $code, $new_password )
	{
		$theUser = User::model()->find( 'confirm_code=:cc', array( ':cc' => $code ) );
		if ( null === $theUser )
		{
			// bad code
			throw new \Exception( "The supplied confirmation was not found in the system." );
		}

		try
		{
			$theUser->setAttribute( 'confirm_code', 'y' );
			$theUser->setAttribute( 'password', \CPasswordHelper::hashPassword( $new_password ) );
			$theUser->save();
		}
		catch ( \CDbException $ex )
		{
			throw new InternalServerErrorException( "Error storing new password." );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error processing password reset.\n{$ex->getMessage()}", $ex->getCode() );
		}

		try
		{
			return Session::userLogin( $theUser->email, $new_password );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Password set, but failed to create a session.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param string $email
	 * @param string $new_password
	 *
	 * @throws \Exception
	 * @return mixed
	 */
	public static function passwordResetByEmail( $email, $new_password )
	{
		/** @var User $theUser */
		$theUser = User::model()->find( 'email=:email', array( ':email' => $email ) );
		if ( null === $theUser )
		{
			// bad code
			throw new NotFoundException( "The supplied email was not found in the system." );
		}

		$confirmCode = $theUser->confirm_code;
		if ( empty( $confirmCode ) || ( 'y' == $confirmCode ) )
		{
			throw new NotFoundException( "No invitation was found for the supplied email." );
		}

		try
		{
			$theUser = User::model()->find( 'email=:email', array( ':email' => $email ) );
			if ( null === $theUser )
			{
				// bad code
				throw new \Exception( "The supplied email was not found in the system." );
			}
			$confirmCode = $theUser->confirm_code;
			if ( empty( $confirmCode ) || ( 'y' == $confirmCode ) )
			{
				throw new \Exception( "No invitation was found for the supplied email." );
			}
			$theUser->setAttribute( 'confirm_code', 'y' );
			$theUser->setAttribute( 'password', \CPasswordHelper::hashPassword( $new_password ) );
			$theUser->save();
		}
		catch ( \Exception $ex )
		{
			throw new \Exception( "Error processing password reset.\n{$ex->getMessage()}", $ex->getCode() );
		}

		try
		{
			return Session::userLogin( $email, $new_password );
		}
		catch ( \Exception $ex )
		{
			throw new \Exception( "Password set, but failed to create a session.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param $old_password
	 * @param $new_password
	 *
	 * @throws BadRequestException
	 * @throws \Exception
	 * @return bool
	 */
	public static function changePassword( $old_password, $new_password )
	{
		// check valid session,
		// using userId from session, query with check for old password
		// then update with new password
		$userId = Session::validateSession();

		try
		{
			$theUser = User::model()->findByPk( $userId );
			if ( null === $theUser )
			{
				// bad session
				throw new \Exception( "The user for the current session was not found in the system." );
			}
			// validate answer
			if ( !\CPasswordHelper::verifyPassword( $old_password, $theUser->password ) )
			{
				throw new BadRequestException( "The password supplied does not match." );
			}
			$theUser->setAttribute( 'password', \CPasswordHelper::hashPassword( $new_password ) );
			$theUser->save();

			return array( 'success' => true );
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public static function getProfile()
	{
		// check valid session,
		// using userId from session, update with new profile elements
		$userId = Session::validateSession();

		try
		{
			$theUser = User::model()->findByPk( $userId );
			if ( null === $theUser )
			{
				// bad session
				throw new \Exception( "The user for the current session was not found in the system." );
			}
			// todo protect certain attributes here
			$fields = $theUser->getAttributes(
				array(
					 'first_name',
					 'last_name',
					 'display_name',
					 'email',
					 'phone',
					 'security_question',
					 'default_app_id',
					 'user_data',
				)
			);

			return $fields;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * @param array $record
	 *
	 * @throws InternalServerErrorException
	 * @throws \Exception
	 * @return bool
	 */
	public static function changeProfile( $record )
	{
		// check valid session,
		// using userId from session, update with new profile elements
		$userId = Session::validateSession();

		try
		{
			$theUser = User::model()->findByPk( $userId );
			if ( null === $theUser )
			{
				// bad session
				throw new \Exception( "The user for the current session was not found in the system." );
			}
			$allow = array(
				'first_name',
				'last_name',
				'display_name',
				'email',
				'phone',
				'security_question',
				'security_answer',
				'default_app_id'
			);
			foreach ( $record as $key => $value )
			{
				if ( false === array_search( $key, $allow ) )
				{
					throw new InternalServerErrorException( "Attribute '$key' can not be updated through profile change." );
				}
			}
			$theUser->setAttributes( $record );
			$theUser->save();

			return array( 'success' => true );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Failed to update the user profile." );
		}
	}

	/**
	 * @param $email
	 *
	 * @return string
	 */
	protected function _getResetPasswordCode( $email )
	{
		return substr( md5( $email . static::$_randKey ), 0, 10 );
	}

	/**
	 * @param $conf_key
	 *
	 * @return string
	 */
	protected function _makeConfirmationMd5( $conf_key )
	{
		$randNo1 = rand();
		$randNo2 = rand();

		return md5( $conf_key . static::$_randKey . $randNo1 . '' . $randNo2 );
	}
}
