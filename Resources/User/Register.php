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
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\ServiceHandler;
use DreamFactory\Platform\Yii\Models\Config;
use DreamFactory\Platform\Yii\Models\User;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Option;

/**
 * Register
 * DSP user registration
 */
class Register extends BasePlatformRestResource
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
				 'name'           => 'User Registration',
				 'service_name'   => 'user',
				 'type'           => 'System',
				 'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
				 'api_name'       => 'register',
				 'description'    => 'Resource for a user registration.',
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
	 * @return array|bool|void
	 */
	protected function _handlePost()
	{
		$_data = RestData::getPostedData( false, true );
		$_result = $this->userRegister( $_data );

		return $_result;
	}

	//-------- User Operations ------------------------------------------------

	/**
	 * @param array $data
	 *
	 * @param bool  $login
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \Exception
	 * @return array
	 */
	public static function userRegister( $data, $login = true )
	{
		/** @var $_config Config */
		$_fields = 'allow_open_registration, open_reg_role_id, open_reg_email_service_id, open_reg_email_template_id';
		if ( null === ( $_config = Config::model()->find( array( 'select' => $_fields ) ) ) )
		{
			throw new InternalServerErrorException( 'Unable to load system configuration.' );
		}

		if ( !$_config->allow_open_registration )
		{
			throw new BadRequestException( "Open registration for users is not currently enabled for this system." );
		}

		$_email = Option::get( $data, 'email', FilterInput::request( 'email' ) );
		if ( empty( $_email ) )
		{
			throw new BadRequestException( "The email field for registering a user can not be empty." );
		}

		$_newPassword = Option::get( $data, 'new_password' );
		$_confirmCode = 'y'; // default
		$_roleId = $_config->open_reg_role_id;
		$_serviceId = $_config->open_reg_email_service_id;
		if ( !empty( $_serviceId ) )
		{
			// email confirmation required
			// see if this is the confirmation
			$_code = Option::get( $data, 'code', FilterInput::request( 'code' ) );
			if ( !empty( $_code ) )
			{
				return static::userConfirm( $_email, $_code, $_newPassword );
			}

			$_confirmCode = Hasher::generateUnique( $_email, 32 );
		}
		else
		{
			// no email confirmation required, registration should include password
			if ( empty( $_newPassword ) )
			{
				throw new BadRequestException( "Missing required fields 'new_password'." );
			}
		}

		// Registration, check for email validation required
		$_theUser = User::model()->find( 'email=:email', array( ':email' => $_email ) );
		if ( null !== $_theUser )
		{
			throw new BadRequestException( "A registered user already exists with the email '$_email'." );
		}

		$_firstName = Option::get( $data, 'first_name' );
		$_lastName = Option::get( $data, 'last_name' );
		$_displayName = Option::get( $data, 'display_name' );
		// fill out the user fields for creation
		$_temp = substr( $_email, 0, strrpos( $_email, '@' ) );
		$_fields = array(
			'email'        => $_email,
			'first_name'   => ( !empty( $_firstName ) ) ? $_firstName : $_temp,
			'last_name'    => ( !empty( $_lastName ) ) ? $_lastName : $_temp,
			'display_name' => ( !empty( $_displayName ) ) ? $_displayName
					: ( !empty( $_firstName ) && !empty( $_lastName ) ) ? $_firstName . ' ' . $_lastName : $_temp,
			'role_id'      => $_roleId,
			'confirm_code' => $_confirmCode
		);

		try
		{
			$_theUser = new User();
			$_theUser->setAttributes( $_fields );
			if ( empty( $_serviceId ) )
			{
				$_theUser->setAttribute( 'password', \CPasswordHelper::hashPassword( $_newPassword ) );
			}
			$_theUser->save();

		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Failed to register new user!\n{$ex->getMessage()}", $ex->getCode() );
		}

		if ( !empty( $_serviceId ) )
		{
			try
			{
				/** @var EmailSvc $_emailService */
				$_emailService = ServiceHandler::getServiceObject( $_serviceId );
				if ( !$_emailService )
				{
					throw new \Exception( "Bad service identifier '$_serviceId'." );
				}

				$_data = array();
				$_template = $_config->open_reg_email_template_id;
				if ( !empty( $_template ) )
				{
					$_data['template_id'] = $_template;
				}
				else
				{
					$_defaultPath = dirname( dirname( __DIR__ ) ) . '/Templates/Email/confirm_user_registration.json';
					if ( !file_exists( $_defaultPath ) )
					{
						throw new \Exception( "No default email template for user registration." );
					}

					$_data = file_get_contents( $_defaultPath );
					$_data = json_decode( $_data, true );
					if ( empty( $_data ) || !is_array( $_data ) )
					{
						throw new \Exception( "No data found in default email template for user registration." );
					}
				}

				$_data['to'] = $_email;
				$_userFields = array( 'first_name', 'last_name', 'display_name', 'confirm_code' );
				$_data = array_merge( $_data, $_theUser->getAttributes( $_userFields ) );
				$_emailService->sendEmail( $_data );
			}
			catch ( \Exception $ex )
			{
				throw new InternalServerErrorException( "Registration complete, but failed to send confirmation email.\n{$ex->getMessage()}", $ex->getCode() );
			}
		}
		else
		{
			if ( $login )
			{
				try
				{
					return Session::userLogin( $_theUser->email, $_newPassword );
				}
				catch ( \Exception $ex )
				{
					throw new InternalServerErrorException( "Registration complete, but failed to create a session.\n{$ex->getMessage()}", $ex->getCode() );
				}
			}
		}

		return array( 'success' => true );
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
	 *
	 * @return array
	 */
	public static function userConfirm( $email, $code, $new_password, $login = true )
	{
		if ( empty( $email ) )
		{
			throw new BadRequestException( "Missing required email for registration confirmation." );
		}

		if ( empty( $new_password ) )
		{
			throw new BadRequestException( "Missing required fields 'new_password'." );
		}

		if ( empty( $code ) || 'y' == $code )
		{
			throw new BadRequestException( "Missing or invalid confirmation code'." );
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
			throw new InternalServerErrorException( "Error processing user registration confirmation.\n{$ex->getMessage()}", $ex->getCode() );
		}

		if ( $login )
		{
			try
			{
				return Session::userLogin( $_theUser->email, $new_password );
			}
			catch ( \Exception $ex )
			{
				throw new InternalServerErrorException( "Registration complete, but failed to create a session.\n{$ex->getMessage()}", $ex->getCode() );
			}
		}

		return array( 'success' => true );
	}

}
