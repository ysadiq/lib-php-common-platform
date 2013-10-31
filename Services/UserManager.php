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
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Resources\User\CustomSettings;
use DreamFactory\Platform\Resources\User\Password;
use DreamFactory\Platform\Resources\User\Profile;
use DreamFactory\Platform\Resources\User\Register;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Yii\Models\User;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Hasher;

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
	}

	/**
	 * @return array
	 */
	protected function _listResources()
	{
		$resources = array(
			array( 'name' => 'custom' ),
			array( 'name' => 'password' ),
			array( 'name' => 'profile' ),
			array( 'name' => 'register' ),
			array( 'name' => 'session' ),
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

			case 'custom':
				$obj = new CustomSettings( $this, $this->_resourceArray );
				$result = $obj->processRequest( null, $this->_action );
				break;

			case 'profile':
				$obj = new Profile( $this );
				$result = $obj->processRequest( null, $this->_action );
				break;

			case 'challenge': // backward compatibility
			case 'password':
				$obj = new Password( $this );
				$result = $obj->processRequest( null, $this->_action );
				break;

			case 'confirm': // backward compatibility
			case 'register':
				$obj = new Register( $this );
				$result = $obj->processRequest( null, $this->_action );
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

		$_theUser = User::model()->find( 'email=:email', array( ':email' => $email ) );
		if ( empty( $_theUser ) )
		{
			throw new BadRequestException( "No user currently exists with the email '$email'." );
		}

		$_confirmCode = $_theUser->confirm_code;
		if ( 'y' == $_confirmCode )
		{
			throw new BadRequestException( "User with email '$email' has already confirmed registration in the system." );
		}

		try
		{
			if ( empty( $_confirmCode ) )
			{
				$_confirmCode = Hasher::generateUnique( $email, 32 );
				$_theUser->setAttribute( 'confirm_code', $_confirmCode );
				$_theUser->save();
			}

			// generate link
			$_link = Curl::currentUrl( false, false ) .'/public/launchpad/confirm_reset.html';
			$_link .= '<br/><br/>Confirmation Code: ' . $_confirmCode;

			return $_link;
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Failed to generate user invite!\n{$ex->getMessage()}", $ex->getCode() );
		}
	}
}
