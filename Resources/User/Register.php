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
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Services\EmailSvc;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\ServiceHandler;
use DreamFactory\Platform\Yii\Models\Config;
use DreamFactory\Platform\Yii\Models\User;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * Register
 * DSP user registration
 */
class Register extends BasePlatformRestResource
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var string
	 */
	protected static $_randKey;

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

		//	For better security. "random" key is used when creating confirmation codes
		static::$_randKey = \sha1( Pii::db()->password );
	}

	// REST interface implementation

	/**
	 * @return array|bool|void
	 */
	protected function _handlePost()
	{
		$_data = RestData::getPostDataAsArray();
		$_result = $this->userRegister( $_data );

		return $_result;
	}

	//-------- User Operations ------------------------------------------------

	/**
	 * @param array $data
	 *
	 * @throws BadRequestException
	 * @throws \Exception
	 * @return array
	 */
	public static function userRegister( $data )
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

		$_theUser = User::model()->find( 'email=:email', array( ':email' => $_email ) );

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
				// registration confirmation by emailed code
				if ( empty( $_theUser ) )
				{
					throw new BadRequestException( "No registered user exists with the email '$_email'." );
				}

				if ( empty( $_newPassword ) )
				{
					throw new BadRequestException( "Missing required fields 'new_password'." );
				}

				try
				{
					$_theUser->setAttribute( 'confirm_code', $_confirmCode );
					$_theUser->setAttribute( 'password', \CPasswordHelper::hashPassword( $_newPassword ) );
					$_theUser->save();

					return array( 'success' => true );
				}
				catch ( \Exception $ex )
				{
					throw new InternalServerErrorException( "Error processing user registration confirmation.\n{$ex->getMessage()}", $ex->getCode() );
				}
			}
			else
			{
				$_confirmCode = static::_makeConfirmationMd5( $_email );
			}
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

			if ( !empty( $_serviceId ) )
			{
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
					$_data = array(
						'subject'   => 'Registration Confirmation',
						'to'        => $_email,
						'body_html' => "Hi {first_name},<br/>\nYou have registered to become a {dsp.name} user. ".
									   "Go to the following url, enter the code below, and set your password to confirm your account.<br/>\n<br/>\n".
									   "{dsp.host_url}/public/launchpad/confirm_reg.html<br/>\n<br/>\n".
									   "{confirm_code}<br/>\n<br/>\nThanks,<br/>\n{from_name}",
					);
				}

				$_userFields = array( 'first_name', 'last_name', 'display_name', 'confirm_code' );
				$_data = array_merge( $_data, $_theUser->getAttributes( $_userFields ) );
				$_emailService->sendEmail( $_data );
			}
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Failed to register new user!\n{$ex->getMessage()}", $ex->getCode() );
		}

		return array( 'success' => true );
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
