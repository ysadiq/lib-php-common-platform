<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
use DreamFactory\Platform\Interfaces\RestServiceLike;
use DreamFactory\Platform\Resources\BaseUserRestResource;
use DreamFactory\Platform\Resources\System\Config;
use DreamFactory\Platform\Services\EmailSvc;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\ServiceHandler;
use DreamFactory\Platform\Yii\Models\User;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Option;

/**
 * Register
 * DSP user registration
 */
class Register extends BaseUserRestResource
{
    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param RestServiceLike $consumer
     * @param array           $resources
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
                    static::PUT   => static::POST,
                    static::PATCH => static::POST,
                    static::MERGE => static::POST,
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
        $_login = Option::get( $this->_requestPayload, 'login', FilterInput::request( 'login', true, FILTER_VALIDATE_BOOLEAN ) );
        $_result = $this->userRegister( $this->_requestPayload, $_login, $_login );

        return $_result;
    }

    //-------- User Operations ------------------------------------------------

    /**
     * @param array $data
     * @param bool  $login
     * @param bool  $return_extras
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     * @return array
     */
    public static function userRegister( $data, $login = true, $return_extras = false )
    {
        /** @var $_config Config */
        if ( false === ( $_config = Config::getOpenRegistration() ) )
        {
            //@todo should this be a 403?
            throw new BadRequestException( "Open registration for users is not currently enabled for this system." );
        }

        $_email = Option::get( $data, 'email', FilterInput::request( 'email' ) );

        if ( empty( $_email ) )
        {
            throw new BadRequestException( "The email field for registering a user can not be empty." );
        }

        $_newPassword = Option::get( $data, 'new_password', Option::get( $data, 'password' ) );
        $_confirmCode = 'y'; // default
        $_roleId = Option::get( $_config, 'open_reg_role_id' );
        $_serviceId = Option::get( $_config, 'open_reg_email_service_id' );
        if ( !empty( $_serviceId ) )
        {
            // email confirmation required
            // see if this is the confirmation
            $_code = Option::get( $data, 'code', FilterInput::request( 'code' ) );
            if ( !empty( $_code ) )
            {
                return static::userConfirm( $_email, $_code, $_newPassword, $login, $return_extras );
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

        $_temp = substr( $_email, 0, strrpos( $_email, '@' ) );
        $_firstName = Option::get( $data, 'first_name', Option::get( $data, 'firstName' ) );
        $_lastName = Option::get( $data, 'last_name', Option::get( $data, 'lastName' ) );
        $_displayName = Option::get( $data, 'display_name', Option::get( $data, 'displayName' ) );
        if ( empty( $_displayName ) )
        {
            $_displayName = ( !empty( $_firstName ) && !empty( $_lastName ) ) ? $_firstName . ' ' . $_lastName : $_temp;
        }
        // fill out the user fields for creation
        $_fields = array(
            'email'        => $_email,
            'first_name'   => $_firstName,
            'last_name'    => $_lastName,
            'display_name' => $_displayName,
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
                $_template = Option::get( $_config, 'open_reg_email_template_id' );
                if ( !empty( $_template ) )
                {
                    $_data['template_id'] = $_template;
                }
                else
                {
                    $_defaultPath = Platform::getLibraryTemplatePath( '/email/confirm_user_registration.json' );

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
                    return Session::userLogin( $_theUser->email, $_newPassword, 0, $return_extras );
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
     * @param bool   $return_extras
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     *
     * @return array
     */
    public static function userConfirm( $email, $code, $new_password, $login = true, $return_extras = false )
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
                return Session::userLogin( $_theUser->email, $new_password, 0, $return_extras );
            }
            catch ( \Exception $ex )
            {
                throw new InternalServerErrorException( "Registration complete, but failed to create a session.\n{$ex->getMessage()}", $ex->getCode() );
            }
        }

        return array( 'success' => true );
    }

}
