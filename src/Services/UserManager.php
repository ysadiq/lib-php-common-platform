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
namespace DreamFactory\Platform\Services;

use DreamFactory\Oasys\Enums\Flows;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Resources\User\CustomSettings;
use DreamFactory\Platform\Resources\User\Device;
use DreamFactory\Platform\Resources\User\Password;
use DreamFactory\Platform\Resources\User\Profile;
use DreamFactory\Platform\Resources\User\Register;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\FilterInput;

/**
 * UserManager
 * DSP user manager
 *
 */
class UserManager extends BaseSystemRestService
{
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
        static $_resources = array(
            'resource' => array(
                array( 'name' => 'custom' ),
                array( 'name' => 'device' ),
                array( 'name' => 'password' ),
                array( 'name' => 'profile' ),
                array( 'name' => 'register' ),
                array( 'name' => 'session' ),
                array( 'name' => 'ticket' )
            )
        );

        return $_resources;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\MisconfigurationException
     * @throws \Exception
     * @return array|bool
     */
    protected function _handleResource()
    {
        if ( empty( $this->_resource ) )
        {
            if ( static::GET == $this->_action )
            {
                return $this->_listResources();
            }

            return false;
        }

        switch ( $this->_resource )
        {
            case 'session':
                //	Handle remote login
                if ( HttpMethod::POST == $this->_action && Pii::getParam( 'dsp.allow_remote_logins' ) )
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

            case 'device':
                $obj = new Device( $this, $this->_resourceArray );
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
                    case static::GET:
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

        //  Send out an event
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
        $ticket = Utilities::encryptCreds( $userId . ',' . $timestamp, 'gorilla' );

        return array( 'ticket' => $ticket, 'ticket_expiry' => time() + ( 5 * 60 ) );
    }
}
