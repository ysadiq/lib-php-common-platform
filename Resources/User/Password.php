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
namespace DreamFactory\Platform\Resources\User;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Services\EmailSvc;
use DreamFactory\Platform\Utility\ServiceHandler;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Yii\Models\Config;
use DreamFactory\Platform\Yii\Models\User;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Option;

/**
 * Password
 * DSP user password
 */
class Password extends BasePlatformRestResource
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
	 * @param array                                               $resources
	 */
	public function __construct( $consumer, $resources = array() )
	{
		parent::__construct(
			$consumer,
			array(
				 'name'           => 'User Password',
				 'service_name'   => 'user',
				 'type'           => 'System',
				 'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
				 'api_name'       => 'password',
				 'description'    => 'Resource for a user to manage their password.',
				 'is_active'      => true,
				 'resource_array' => $resources,
				 'verb_aliases'   => array(
					 static::Put   => static::Post,
					 static::Patch => static::Post,
					 static::Merge => static::Post,
				 )
			)
		);
	}

	// REST interface implementation

	/**
	 * @return array|bool
	 */
	protected function _handlePost()
	{
		$_data = RestData::getPostDataAsArray();
		$_old = Option::get( $_data, 'old_password' );
		$_new = Option::get( $_data, 'new_password' );

		if ( !empty( $_old ) )
		{
			// check valid session, use userId from session
			$_userId = Session::validateSession();

			return $this->changePassword( $_userId, $_old, $_new );
		}

		$_email = Option::get( $_data, 'email', FilterInput::request( 'email' ) );
		$_reset = Option::getBool( $_data, 'reset', FilterInput::request( 'reset' ) );
		if ( $_reset )
		{
			return $this->passwordReset( $_email );
		}

		$_code = Option::get( $_data, 'code', FilterInput::request( 'code' ) );
		if ( !empty( $_code ) )
		{
			return $this->changePasswordByCode( $_email, $_code, $_new );
		}

		$_answer = Option::get( $_data, 'security_answer' );
		if ( !empty( $_answer ) )
		{
			return $this->changePasswordBySecurityAnswer( $_email, $_answer, $_new );
		}

		return false;
	}

	//-------- User Operations ------------------------------------------------

	/**
	 * @param int    $user_id
	 * @param string $old
	 * @param string $new
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return bool
	 */
	public static function changePassword( $user_id, $old, $new )
	{
		// query with check for old password
		// then update with new password
		if ( empty( $old ) || empty( $new ) )
		{
			throw new BadRequestException( 'Both old and new password are required to change the password.' );
		}

		$_theUser = User::model()->findByPk( $user_id );
		if ( null === $_theUser )
		{
			// bad session
			throw new NotFoundException( "The user for the current session was not found in the system." );
		}

		try
		{
			// validate answer
			$_isValid = \CPasswordHelper::verifyPassword( $old, $_theUser->password );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error validating old password.\n{$ex->getMessage()}", $ex->getCode() );
		}

		if ( !$_isValid )
		{
			throw new BadRequestException( "The password supplied does not match." );
		}

		try
		{
			$_theUser->setAttribute( 'password', \CPasswordHelper::hashPassword( $new ) );
			$_theUser->save();

			return array( 'success' => true );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error processing password change.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param string $email
	 * @param string $code
	 * @param string $new_password
	 * @param bool   $login
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return mixed
	 */
	public static function changePasswordByCode( $email, $code, $new_password, $login = true )
	{
		if ( empty( $email ) )
		{
			throw new BadRequestException( "Missing required email for password reset confirmation." );
		}

		if ( empty( $new_password ) )
		{
			throw new BadRequestException( "Missing new password for reset." );
		}

		if ( empty( $code ) || 'y' == $code )
		{
			throw new BadRequestException( "Invalid confirmation code." );
		}

		$_theUser = User::model()->find(
			'email=:email AND confirm_code=:cc',
			array( ':email' => $email, ':cc' => $code )
		);
		if ( null === $_theUser )
		{
			// bad code
			throw new NotFoundException( "The supplied email and/or confirmation code were not found in the system." );
		}

		try
		{
			$_theUser->setAttribute( 'confirm_code', 'y' );
			$_theUser->setAttribute( 'password', \CPasswordHelper::hashPassword( $new_password ) );
			$_theUser->save();
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error processing password reset.\n{$ex->getMessage()}", $ex->getCode() );
		}

		if ( $login )
		{
			try
			{
				return Session::userLogin( $_theUser->email, $new_password );
			}
			catch ( \Exception $ex )
			{
				throw new InternalServerErrorException( "Password set, but failed to create a session.\n{$ex->getMessage()}", $ex->getCode() );
			}
		}

		return array( 'success' => true );
	}

	/**
	 * @param string $email
	 * @param string $answer
	 * @param string $new_password
	 * @param bool   $login
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return mixed
	 */
	public static function changePasswordBySecurityAnswer( $email, $answer, $new_password, $login = true )
	{
		if ( empty( $email ) )
		{
			throw new BadRequestException( "Missing required email for password reset confirmation." );
		}

		if ( empty( $new_password ) )
		{
			throw new BadRequestException( "Missing new password for reset." );
		}

		if ( empty( $answer ) )
		{
			throw new BadRequestException( "Missing security answer." );
		}

		$_theUser = User::model()->find( 'email=:email', array( ':email' => $email ) );
		if ( null === $_theUser )
		{
			// bad code
			throw new NotFoundException( "The supplied email and confirmation code were not found in the system." );
		}

		try
		{
			// validate answer
			$_isValid = \CPasswordHelper::verifyPassword( $answer, $_theUser->security_answer );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error validating security answer.\n{$ex->getMessage()}", $ex->getCode() );
		}

		if ( !$_isValid )
		{
			throw new BadRequestException( "The answer supplied does not match." );
		}

		try
		{
			$_theUser->setAttribute( 'password', \CPasswordHelper::hashPassword( $new_password ) );
			$_theUser->save();
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error processing password change.\n{$ex->getMessage()}", $ex->getCode() );
		}

		if ( $login )
		{
			try
			{
				return Session::userLogin( $_theUser->email, $new_password );
			}
			catch ( \Exception $ex )
			{
				throw new InternalServerErrorException( "Password set, but failed to create a session.\n{$ex->getMessage()}", $ex->getCode() );
			}
		}

		return array( 'success' => true );
	}

	/**
	 * @param string $email
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return mixed
	 */
	public static function passwordReset( $email )
	{
		if ( empty( $email ) )
		{
			throw new BadRequestException( "Missing required email for password reset confirmation." );
		}

		/** @var User $_theUser */
		$_theUser = User::model()->find( 'email=:email', array( ':email' => $email ) );
		if ( null === $_theUser )
		{
			// bad code
			throw new NotFoundException( "The supplied email was not found in the system." );
		}

		// if security question and answer provisioned, start with that
		$_question = $_theUser->security_question;
		if ( !empty( $_question ) )
		{
			return array( 'security_question' => $_question );
		}

		// otherwise, is email confirmation required?
		/** @var $_config Config */
		$_fields = 'password_email_service_id, password_email_template_id';
		if ( null === ( $_config = Config::model()->find( array( 'select' => $_fields ) ) ) )
		{
			throw new InternalServerErrorException( 'Unable to load system configuration.' );
		}

		$_serviceId = $_config->password_email_service_id;
		if ( !empty( $_serviceId ) )
		{
			$_code = Hasher::generateUnique( $email, 32 );
			try
			{
				$_theUser->setAttribute( 'confirm_code', $_code );
				$_theUser->save();

				/** @var EmailSvc $_emailService */
				$_emailService = ServiceHandler::getServiceObject( $_serviceId );
				if ( !$_emailService )
				{
					throw new \Exception( "Bad service identifier '$_serviceId'." );
				}

				$_data = array();
				$_template = $_config->password_email_template_id;
				if ( !empty( $_template ) )
				{
					$_data['template_id'] = $_template;
				}
				else
				{
					$_defaultPath = Pii::getParam( 'base_path' ) . '/vendor/dreamfactory/lib-php-common-platform/DreamFactory/Platform';
					$_defaultPath .= '/Templates/Email/confirm_password_reset.json';
					if ( !file_exists( $_defaultPath ) )
					{
						throw new \Exception( "No default email template for password reset." );
					}

					$_data = file_get_contents( $_defaultPath );
					$_data = json_decode( $_data, true );
					if ( empty( $_data ) || !is_array( $_data ) )
					{
						throw new \Exception( "No data found in default email template for password reset." );
					}
				}

				$_data['to'] = $email;
				$_userFields = array( 'first_name', 'last_name', 'display_name', 'confirm_code' );
				$_data = array_merge( $_data, $_theUser->getAttributes( $_userFields ) );
				$_emailService->sendEmail( $_data );

				return array( 'success' => true );
			}
			catch ( \Exception $ex )
			{
				throw new InternalServerErrorException( "Error processing password reset.\n{$ex->getMessage()}", $ex->getCode() );
			}
		}

		throw new InternalServerErrorException( 'No security question found or email confirmation available for this user. ' .
												'Please contact your administrator.' );
	}
}
