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

use DreamFactory\Platform\Components\PlatformStore;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\UnauthorizedException;
use DreamFactory\Platform\Interfaces\PermissionTypes;
use DreamFactory\Platform\Interfaces\RestServiceLike;
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Platform\Yii\Components\PlatformUserIdentity;
use DreamFactory\Platform\Yii\Models\App;
use DreamFactory\Platform\Yii\Models\AppGroup;
use DreamFactory\Platform\Yii\Models\Config;
use DreamFactory\Platform\Yii\Models\LookupKey;
use DreamFactory\Platform\Yii\Models\Role;
use DreamFactory\Platform\Yii\Models\User;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;

/**
 * Session
 * DSP user session
 */
class Session extends BasePlatformRestResource
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var int
     */
    protected static $_userId = null;
    /**
     * @var string
     */
    protected static $_ownerId = null;
    /**
     * @var array
     */
    protected static $_cache = null;
    /**
     * @var string
     */
    protected static $_ticket = null;

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
                'name'           => 'User Session',
                'service_name'   => 'user',
                'type'           => 'System',
                'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
                'api_name'       => 'session',
                'description'    => 'Resource for a user to manage their session.',
                'is_active'      => true,
                'resource_array' => $resources,
                'verb_aliases'   => array(static::PUT => static::POST,)
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

        return $this->_getSession( $_ticket );
    }

    /**
     * @return array|bool|void
     */
    protected function _handlePost()
    {
        $_data = RestData::getPostedData( false, true );

        $_result = $this->userLogin(
            Option::get( $_data, 'email' ),
            Option::get( $_data, 'password' ),
            Option::get( $_data, 'duration', 0 ),
            true
        );

        return $_result;
    }

    /**
     * @return array|bool|void
     */
    protected function _handleDelete()
    {
        $this->userLogout();

        return array('success' => true);
    }

    //-------- User Operations ------------------------------------------------

    /**
     * Refreshes an existing session or allows the SSO creation of a new session for external apps via timed ticket
     *
     * @param string $ticket
     *
     * @return mixed
     * @throws \Exception
     * @throws UnauthorizedException
     */
    protected static function _getSession( $ticket = null )
    {
        try
        {
            $_user = null;
            if ( !empty( $ticket ) )
            {
                //	Process ticket
                $_user = static::_validateTicket( $ticket );
                $_userId = $_user->id;
            }
            else
            {
                $_userId = static::validateSession();
            }

            $_result = static::generateSessionDataFromUser( $_userId, $_user );
            static::$_cache = Option::get( $_result, 'cached' );
            Pii::setState( 'cached', static::$_cache );

            //	Additional stuff for session - launchpad mainly
            return static::addSessionExtras( $_result, true );
        }
        catch ( \Exception $_ex )
        {
            //  Flush caches but don't touch session...
            static::userLogout( false );

            //	Special case for guest user
            $_config = ResourceStore::model( 'config' )->with(
                'guest_role.role_service_accesses',
                'guest_role.role_system_accesses',
                'guest_role.apps',
                'guest_role.services'
            )->find();

            /** @var Config $_config */
            if ( !empty( $_config ) )
            {
                if ( $_config->allow_guest_user )
                {
                    $_result = static::generateSessionDataFromRole( null, $_config->getRelated( 'guest_role' ) );

                    // additional stuff for session - launchpad mainly
                    return static::addSessionExtras( $_result, true );
                }
            }

            //	Otherwise throw original exception
            throw $_ex;
        }
    }

    protected static function _generateTicket()
    {
        $_timestamp = time();
        $_userId = static::getCurrentUserId();

        $_ticket = Utilities::encryptCreds( "$_userId,$_timestamp", "gorilla" );

        return $_ticket;
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
            throw new UnauthorizedException( 'Session authorization ticket can not be empty.' );
        }

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
            throw new UnauthorizedException( 'Session authorization ticket has expired.' );
        }

        /** @var User $_user */
        $_user = ResourceStore::model( 'user' )->with(
            'role.role_service_accesses',
            'role.role_system_accesses',
            'role.apps',
            'role.services'
        )->findByPk( $_userId );

        if ( empty( $_user ) )
        {
            if ( empty( $_userId ) )
            {
                throw new UnauthorizedException( 'The user is invalid.' );
            }

            throw new UnauthorizedException( 'The user id ' . $_userId . ' is invalid.' );
        }

        $_identity = new PlatformUserIdentity( $_user->email, null );

        if ( $_identity->logInUser( $_user ) )
        {
            if ( !Pii::user()->login( $_identity, 0 ) )
            {
                throw new UnauthorizedException( 'The user could not be logged in.' );
            }
        }

        return $_user;
    }

    /**
     * @param string  $email
     * @param string  $password
     * @param integer $duration
     * @param boolean $return_extras
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return boolean | array
     */
    public static function userLogin( $email, $password, $duration = 0, $return_extras = false )
    {
        /** @var User $_user */
        $_user = User::loginRequest( $email, $password, $duration );

        $_result = static::generateSessionDataFromUser( $_user->id, $_user );

        static::$_cache = Option::get( $_result, 'cached' );
        Pii::setState( 'cached', static::$_cache );

        static::$_userId = $_user->id;
        static::$_ownerId = sha1( $_user->email );

        // write back login datetime
        $_user->update( array('last_login_date' => date( 'c' )) );

        if ( $return_extras )
        {
            // 	Additional stuff for session - launchpad mainly
            return static::addSessionExtras( $_result, true );
        }

        return true;
    }

    /**
     * @param bool $deleteSession If true, any active session will be destroyed
     */
    public static function userLogout( $deleteSession = true )
    {
        // helper for non-browser-managed sessions
        $_sessionId = FilterInput::server( 'HTTP_X_DREAMFACTORY_SESSION_TOKEN' );

        if ( $deleteSession && !empty( $_sessionId ) )
        {
            session_write_close();
            session_id( $_sessionId );

            if ( session_start() )
            {
                if ( session_id() !== '' )
                {
                    @session_unset();
                    @session_destroy();
                }
            }
        }

        // And logout browser session
        Pii::user()->logout();

        //  Flush any stored configs
        Pii::flushConfig();
        Platform::storeDelete( PlatformStore::buildCacheKey() );
    }

    /**
     * @param int  $user_id
     * @param User $user
     *
     * @throws \DreamFactory\Platform\Exceptions\UnauthorizedException
     * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
     * @return array
     */
    public static function generateSessionDataFromUser( $user_id, $user = null )
    {
        // fields returned in session to client
        static $_fields = array(
            'id',
            'display_name',
            'first_name',
            'last_name',
            'email',
            'is_sys_admin',
            'last_login_date'
        );

        static $_appFields = array('id', 'api_name', 'is_active');

        /** @var User $_user */
        $_user = $user
            ? : ResourceStore::model( 'user' )->with(
                'role.role_service_accesses',
                'role.role_system_accesses',
                'role.apps',
                'role.services'
            )->findByPk( $user_id );

        if ( empty( $_user ) )
        {
            if ( empty( $user_id ) )
            {
                throw new UnauthorizedException( 'The user is invalid.' );
            }

            throw new UnauthorizedException( 'The user id ' . $user_id . ' is invalid.' );
        }

        if ( null !== $user_id && $_user->id != $user_id )
        {
            throw new ForbiddenException( 'Naughty, naughty... Not yours. ' .
                                          $_user->id .
                                          ' != ' .
                                          print_r( $user_id, true ) );
        }

        $_email = $_user->email;

        if ( !$_user->is_active )
        {
            throw new ForbiddenException( "The user '$_email' is not currently active." );
        }

        $_cached = $_user->getAttributes( $_fields );
        $_public = $_cached;
        $_defaultAppId = $_user->default_app_id;

        $_roleApps = $_allowedApps = array();
        $_roleId = $_user->role_id;
        if ( !$_user->is_sys_admin )
        {
            if ( !$_user->role )
            {
                throw new ForbiddenException( "The user '$_email' has not been assigned a role." );
            }

            $_roleName = $_user->role->name;
            if ( !$_user->role->is_active )
            {
                throw new ForbiddenException( "The role '$_roleName' is not currently active." );
            }

            if ( !isset( $_defaultAppId ) )
            {
                $_defaultAppId = $_user->role->default_app_id;
            }

            if ( $_user->role->apps )
            {
                /**
                 * @var App $_app
                 */
                foreach ( $_user->role->apps as $_app )
                {
                    $_roleApps[] = $_app->getAttributes( $_appFields );

                    if ( $_app->is_active )
                    {
                        $_allowedApps[] = $_app;
                    }
                }
            }

            $_role = array('name' => $_roleName, 'id' => $_roleId);
            $_role['apps'] = $_roleApps;
            $_role['services'] = $_user->getRoleServicePermissions();

            $_cached['role'] = $_role;
            $_public['role'] = $_roleName;
            $_public['role_id'] = $_roleId;
        }
        else
        {
            $_allowedApps = ResourceStore::model( 'app' )->findAll( 'is_active = :ia', array(':ia' => 1) );
        }

        $_public['dsp_name'] = Pii::getParam( 'dsp_name' );
        $_cached = array_merge( $_cached, LookupKey::getForSession( $_roleId, $_user->id ) );

        return array(
            'cached'         => $_cached,
            'public'         => $_public,
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
        static $_appFields = array('id', 'api_name', 'is_active');

        /** @var Role $_role */
        $_role = $role
            ? : ResourceStore::model( 'role' )->with(
                'role_service_accesses',
                'role_system_accesses',
                'apps',
                'services'
            )->findByPk( $roleId );

        if ( empty( $_role ) )
        {
            throw new UnauthorizedException( "The role id '$roleId' does not exist in the system." );
        }

        if ( !$_role->is_active )
        {
            throw new ForbiddenException( "The role '$_role->name' is not currently active." );
        }

        $_cached = array();
        $_public = array();
        $_allowedApps = array();
        $_defaultAppId = $_role->default_app_id;
        $_roleData = array('name' => $_role->name, 'id' => $_role->id);

        /**
         * @var App[] $_apps
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

        $_cached['role'] = $_roleData;
        $_cached = array_merge( $_cached, LookupKey::getForSession( $_role->id ) );

        return array(
            'cached'         => $_cached,
            'public'         => $_public,
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
        // helper for non-browser-managed sessions
        $_request = Pii::requestObject();
        $_sessionId = $_request->get( 'session_token', FilterInput::server( 'HTTP_X_DREAMFACTORY_SESSION_TOKEN' ) );

        $_oldSessionId = session_id();
        if ( !empty( $_sessionId ) && $_sessionId !== $_oldSessionId )
        {
            if ( !empty( $_oldSessionId ) )
            {
                @session_unset();
                @session_destroy();
            }

            session_id( $_sessionId );

            if ( !session_start() )
            {
                Log::error( 'Failed to start session "' . $_sessionId . '" from header: ' . print_r( $_SERVER, true ) );
            }
        }

        if ( !Pii::guest() && !Pii::getState( 'df_authenticated', false ) )
        {
            return Pii::user()->getId();
        }

        throw new UnauthorizedException( "There is no valid session for the current request." );
    }

    /**
     * @return bool
     */
    public static function isSystemAdmin()
    {
        static::_checkCache();

        return Option::getBool( static::$_cache, 'is_sys_admin' );
    }

    /**
     * @param string $app_name
     * @param bool   $session_required
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
     */
    public static function checkAppPermission( $app_name = null, $session_required = true )
    {
        if ( empty( $app_name ) )
        {
            $app_name = SystemManager::getCurrentAppName();
            if ( empty( $app_name ) )
            {
                throw new BadRequestException( 'A valid application name is required to access services.' );
            }
        }

        if ( !$session_required )
        {
            // check if the app_name (App.api_name) is in the current list of apps
            if ( false !== array_search( $app_name, App::availableNames() ) )
            {
                return;
            }
        }

        static::_checkCache();

        if ( false !== ( $_admin = Option::getBool( static::$_cache, 'is_sys_admin' ) ) )
        {
            return; // no need to check role
        }

        if ( null === ( $_roleInfo = Option::get( static::$_cache, 'role' ) ) )
        {
            // no role assigned, if not sys admin, denied service
            throw new ForbiddenException( "A valid user role or system administrator is required to access services." );
        }

        // check if app allowed in role
        /** @var App $_app */
        foreach ( Option::clean( Option::get( $_roleInfo, 'apps' ) ) as $_app )
        {
            if ( 0 == strcasecmp( $app_name, Option::get( $_app, 'api_name' ) ) )
            {
                return;
            }
        }

        throw new ForbiddenException( "Access to application '$app_name' is not provisioned for this user's role." );
    }

    /**
     * @param string $action    - REST API action name
     * @param string $service   - API name of the service
     * @param string $component - API component/resource name
     *
     * @throws ForbiddenException
     * @throws BadRequestException
     */
    public static function checkServicePermission( $action, $service, $component = null )
    {
        $action = static::cleanAction( $action );

        $_permissions = static::getServicePermissions( $service, $component );

        if ( false === array_search( $action, $_permissions ) )
        {
            $msg = ucfirst( $action ) . " access to ";
            if ( !empty( $component ) )
            {
                $msg .= "component '$component' of ";
            }

            $msg .= "service '$service' is not allowed by this user's role.";

            throw new ForbiddenException( $msg );
        }
    }

    /**
     * @param string $service
     * @param string $component
     *
     * @returns array
     */
    public static function getServicePermissions( $service, $component = null )
    {
        static::_checkCache();

        if ( Option::getBool( static::$_cache, 'is_sys_admin' ) )
        {
            return array(static::GET, static::POST, static::PUT, static::PATCH, static::MERGE, static::DELETE, 'ADMIN');
        }

        if ( null === ( $_roleInfo = Option::get( static::$_cache, 'role' ) ) )
        {
            // no role assigned
            return array();
        }

        $_services = Option::clean( Option::get( $_roleInfo, 'services' ) );

        $_allAllowed = array();
        $_allFound = false;
        $_serviceAllowed = array();
        $_serviceFound = false;
        $_componentAllowed = array();
        $_componentFound = false;

        foreach ( $_services as $_svcInfo )
        {
            $_tempService = Option::get( $_svcInfo, 'service' );
            if ( null === $_tempVerbs = Option::get( $_svcInfo, 'verbs' ) )
            {
                // see if upgrade from access string
                $_tempVerbs = static::convertAccessToVerbs( Option::get( $_svcInfo, 'access' ) );
            }

            if ( 0 == strcasecmp( $service, $_tempService ) )
            {
                $_tempComponent = Option::get( $_svcInfo, 'component' );
                if ( !empty( $component ) )
                {
                    if ( 0 == strcasecmp( $component, $_tempComponent ) )
                    {
                        $_componentAllowed = array_merge( $_componentAllowed, array_flip( $_tempVerbs ) );
                        $_componentFound = true;
                    }
                    elseif ( empty( $_tempComponent ) || ( '*' == $_tempComponent ) )
                    {
                        $_serviceAllowed = array_merge( $_serviceAllowed, array_flip( $_tempVerbs ) );
                        $_serviceFound = true;
                    }
                }
                else
                {
                    if ( empty( $_tempComponent ) || ( '*' == $_tempComponent ) )
                    {
                        $_serviceAllowed = array_merge( $_serviceAllowed, array_flip( $_tempVerbs ) );
                        $_serviceFound = true;
                    }
                }
            }
            elseif ( empty( $_tempService ) || ( '*' == $_tempService ) )
            {
                $_allAllowed = array_merge( $_allAllowed, array_flip( $_tempVerbs ) );
                $_allFound = true;
            }
        }

        if ( $_componentFound )
        {
            return array_keys( $_componentAllowed );
        }
        elseif ( $_serviceFound )
        {
            return array_keys( $_serviceAllowed );
        }
        elseif ( $_allFound )
        {
            return array_keys( $_allAllowed );
        }

        return array();
    }

    /**
     * @param string $action - requested REST action
     *
     * @return string
     */
    protected static function cleanAction( $action )
    {
        // check for non-conformists
        $action = strtoupper( $action );
        switch ( $action )
        {
            case 'READ':
                return static::GET;

            case 'CREATE':
                return static::POST;

            case 'UPDATE':
                return static::PUT;
        }

        return $action;
    }

    /**
     * @param string|int $access - UI permission string or enumeration
     *
     * @return array
     */
    public static function convertAccessToVerbs( $access )
    {
        switch ( $access )
        {
            case PermissionTypes::READ_ONLY:
            case 'Read Only':
                return array(static::GET);
            case PermissionTypes::WRITE_ONLY:
            case 'Write Only':
                return array(static::POST);
            case PermissionTypes::READ_WRITE:
            case 'Read and Write':
                return array(static::GET, static::POST, static::PUT, static::PATCH, static::MERGE);
            case PermissionTypes::FULL_ACCESS:
            case 'Full Access':
                return array(static::GET, static::POST, static::PUT, static::PATCH, static::MERGE, static::DELETE);
        }

        return array();
    }

    /**
     * @param string $action
     * @param string $service
     * @param string $component
     *
     * @returns bool
     */
    public static function getServiceFilters( $action, $service, $component = null )
    {
        static::_checkCache();

        if ( Option::getBool( static::$_cache, 'is_sys_admin' ) )
        {
            return array();
        }

        if ( null === ( $_roleInfo = Option::get( static::$_cache, 'role' ) ) )
        {
            // no role assigned
            return array();
        }

        $_services = Option::clean( Option::get( $_roleInfo, 'services' ) );

        $_serviceAllowed = null;
        $_serviceFound = false;
        $_componentFound = false;
        $action = static::cleanAction( $action );

        foreach ( $_services as $_svcInfo )
        {
            $_tempService = Option::get( $_svcInfo, 'service' );
            if ( null === $_tempVerbs = Option::get( $_svcInfo, 'verbs' ) )
            {
                // see if upgrade from access string
                $_tempVerbs = static::convertAccessToVerbs( Option::get( $_svcInfo, 'access' ) );
            }
            $_tempVerbs = array_flip( $_tempVerbs ); // make search easier

            if ( 0 == strcasecmp( $service, $_tempService ) )
            {
                $_serviceFound = true;
                $_tempComponent = Option::get( $_svcInfo, 'component' );
                if ( !empty( $component ) )
                {
                    if ( 0 == strcasecmp( $component, $_tempComponent ) )
                    {
                        $_componentFound = true;
                        if ( isset( $_tempVerbs[$action] ) )
                        {
                            $_filters = Option::get( $_svcInfo, 'filters' );
                            $_operator = Option::get( $_svcInfo, 'filter_op', 'AND' );
                            if ( empty( $_filters ) )
                            {
                                return null;
                            }

                            return array('filters' => $_filters, 'filter_op' => $_operator);
                        }
                    }
                    elseif ( empty( $_tempComponent ) || ( '*' == $_tempComponent ) )
                    {
                        if ( isset( $_tempVerbs[$action] ) )
                        {
                            $_filters = Option::get( $_svcInfo, 'filters' );
                            $_operator = Option::get( $_svcInfo, 'filter_op', 'AND' );
                            if ( empty( $_filters ) )
                            {
                                return null;
                            }

                            $_serviceAllowed = array('filters' => $_filters, 'filter_op' => $_operator);
                        }
                    }
                }
                else
                {
                    if ( empty( $_tempComponent ) || ( '*' == $_tempComponent ) )
                    {
                        if ( isset( $_tempVerbs[$action] ) )
                        {
                            $_filters = Option::get( $_svcInfo, 'filters' );
                            $_operator = Option::get( $_svcInfo, 'filter_op', 'AND' );
                            if ( empty( $_filters ) )
                            {
                                return null;
                            }

                            $_serviceAllowed = array('filters' => $_filters, 'filter_op' => $_operator);
                        }
                    }
                }
            }
        }

        if ( $_componentFound )
        {
            // at least one service and component match was found, but not the right verb

            return null;
        }
        elseif ( $_serviceFound )
        {
            return $_serviceAllowed;
        }

        return null;
    }

    /**
     * @param string $lookup
     * @param string $value
     * @param bool   $use_private
     *
     * @returns bool
     */
    public static function getLookupValue( $lookup, &$value, $use_private = false )
    {
        if ( empty( $lookup ) )
        {
            return false;
        }

        static::_checkCache();

        $_parts = explode( '.', $lookup );
        if ( count( $_parts ) > 1 )
        {
            $_section = array_shift( $_parts );
            $_lookup = implode( '.', $_parts );
            if ( !empty( $_section ) )
            {
                switch ( $_section )
                {
                    case 'session':
                        switch ( $_lookup )
                        {
                            case 'id':
                                $value = session_id();

                                return true;

                            case 'ticket':
                                $value = static::_generateTicket();

                                return true;
                        }
                        break;

                    case 'user':
                        // get fields here
                        if ( !empty( $_lookup ) )
                        {
                            if ( isset( static::$_cache, static::$_cache[$_lookup] ) )
                            {
                                $value = static::$_cache[$_lookup];

                                return true;
                            }
                        }
                        break;

                    case 'role':
                        // get fields here
                        if ( !empty( $_lookup ) )
                        {
                            if ( isset( static::$_cache, static::$_cache['role'], static::$_cache['role'][$_lookup] ) )
                            {
                                $value = static::$_cache['role'][$_lookup];

                                return true;
                            }
                        }
                        break;

                    case 'app':
                        switch ( $_lookup )
                        {
                            case 'id':
                                $value = SystemManager::getCurrentAppId();;

                                return true;

                            case 'api_name':
                                $value = SystemManager::getCurrentAppName();

                                return true;
                        }
                        break;

                    case 'dsp':
                        switch ( $_lookup )
                        {
                            case 'host_url':
                                $value = Curl::currentUrl( false, false );

                                return true;
                            case 'name':
                                $value = Pii::getParam( 'dsp_name' );

                                return true;
                            case 'version':
                            case 'confirm_invite_url':
                            case 'confirm_register_url':
                            case 'confirm_reset_url':
                                $value = Curl::currentUrl( false, false ) . Pii::getParam( 'dsp.' . $_lookup );

                                return true;
                        }
                        break;
                }
            }
        }

        $_control = $use_private ? 'secret' : 'lookup';

        if ( isset( static::$_cache, static::$_cache[$_control], static::$_cache[$_control][$lookup] ) )
        {
            $value = static::$_cache[$_control][$lookup];

            return true;
        }

        return false;
    }

    public static function replaceLookup( $lookup, $use_private = false )
    {
        // filter string values should be wrapped in curly braces
        if ( is_string( $lookup ) )
        {
            $_end = strlen( $lookup ) - 1;
            if ( ( 0 === strpos( $lookup, '{' ) ) && ( $_end === strrpos( $lookup, '}' ) ) )
            {
                if ( static::getLookupValue( substr( $lookup, 1, $_end - 1 ), $_value, $use_private ) )
                {
                    return $_value;
                }
            }
        }

        return $lookup;
    }

    public static function replaceLookupsInStrings( &$string, $use_private = false )
    {
        if ( false !== strpos( $string, '{' ) )
        {
            $_search = array();
            $_replace = array();
            // brute force, yeah this could be better
            $_exploded = explode( '{', $string );
            foreach ( $_exploded as $_word )
            {
                $_lookup = strstr( $_word, '}', true );
                if ( !empty( $_lookup ) )
                {
                    if ( Session::getLookupValue( $_lookup, $_value, $use_private ) )
                    {
                        $_search[] = '{' . $_lookup . '}';
                        $_replace[] = $_value;
                    }
                }
            }

            if ( !empty( $_search ) )
            {
                $string = str_replace( $_search, $_replace, $string );
            }
        }
    }

    /**
     * @param int $userId
     *
     * @return int
     */
    public static function setCurrentUserId( $userId )
    {
        if ( !Pii::guest() && false === Pii::getState( 'df_authenticated' ) )
        {
            static::$_userId = $userId;
        }

        return $userId;
    }

    /**
     * @param int $setToIfNull If not null, static::$_userId will be set to this value
     *
     * @return int|null
     */
    public static function getCurrentUserId( $setToIfNull = null )
    {
        if ( !empty( static::$_userId ) )
        {
            return static::$_userId;
        }

        if ( !Pii::cli() && !Pii::guest() && false === Pii::getState( 'df_authenticated' ) )
        {
            return static::$_userId = Pii::user()->getId();
        }

        return static::$_userId = $setToIfNull ? : null;
    }

    /**
     * @return string
     */
    public static function getCurrentOwnerId()
    {
        return static::$_ownerId;
    }

    /**
     * @return string|null
     */
    public static function getCurrentTicket()
    {
        return static::$_ticket;
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
                $_result = Pii::getState( 'cached' );
                if ( !empty( $_result ) )
                {
                    static::$_cache = $_result;
                }
                else
                {
                    $_result = static::generateSessionDataFromUser( $_userId );
                    static::$_cache = Option::get( $_result, 'cached' );
                    Pii::setState( 'cached', static::$_cache );
                }
            }
            catch ( \Exception $ex )
            {
                // special case for possible guest user
                $_config = ResourceStore::model( 'config' )->with(
                    'guest_role.role_service_accesses',
                    'guest_role.role_system_accesses',
                    'guest_role.apps',
                    'guest_role.services'
                )->find();

                /** @var Config $_config */
                if ( !empty( $_config ) )
                {
                    if ( Scalar::boolval( $_config->allow_guest_user ) )
                    {
                        $_result = static::generateSessionDataFromRole( null, $_config->getRelated( 'guest_role' ) );
                        static::$_cache = Option::get( $_result, 'cached' );

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
     * @param bool  $add_apps
     *
     * @return array
     */
    public static function addSessionExtras( $session, $add_apps = false )
    {
        $_data = Option::get( $session, 'public' );
        $_data['ticket'] = static::$_ticket = static::_generateTicket();
        $_data['ticket_expiry'] = time() + ( 5 * 60 );
        $_data['session_id'] = session_id();

        if ( $add_apps )
        {
            $appFields =
                'id,api_name,name,description,is_url_external,launch_url,requires_fullscreen,allow_fullscreen_toggle,toggle_location';
            /**
             * @var App[] $_apps
             */
            $_apps = Option::get( $session, 'allowed_apps', array() );
            /**
             * @var AppGroup[] $theGroups
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
                    $groupData = ( isset( $appGroups[$g_key] ) )
                        ? $appGroups[$g_key]
                        : $group->getAttributes(
                            array('id', 'name', 'description')
                        );
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
            $_data['app_groups'] = array_values( $appGroups ); // reset indexing
            $_data['no_group_apps'] = $noGroupApps;
        }

        return $_data;
    }

    /**
     * Generates a semi-unique hash suitable as filesystem store IDs
     *
     * @param string $salt
     *
     * @return string
     */
    public static function getUserIdentifier( $salt = null )
    {
        $_hash = Hasher::hash( static::getCurrentTicket() );

        if ( null !== $salt )
        {
            $_hash = Hasher::encryptString( $_hash, $salt );
        }

        return $_hash;
    }
}
