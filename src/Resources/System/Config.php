<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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

use DreamFactory\Platform\Components\PlatformStore;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\UnauthorizedException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Platform\Utility\Fabric;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Yii\Models\LookupKey;
use DreamFactory\Platform\Yii\Models\Provider;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Config
 * DSP system administration manager
 *
 */
class Config extends BaseSystemRestResource
{
    //*************************************************************************
    //  Constants
    //*************************************************************************

    /**
     * @type int The number of seconds at most to cache these resources
     */
    const CONFIG_CACHE_TTL = 60;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@InheritDoc}
     */
    public function __construct( $consumer = null, $resourceArray = array() )
    {
        parent::__construct(
            $consumer,
            array(
                'name'           => 'Configuration',
                'type'           => 'System',
                'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
                'api_name'       => 'config',
                'description'    => 'Service general configuration',
                'is_active'      => true,
                'resource_array' => $resourceArray,
                'verb_aliases'   => array(
                    static::PATCH => static::POST,
                    static::MERGE => static::POST,
                )
            )
        );
    }

    /**
     * @param bool $flushCache If true, any cached data will be removed
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array|bool
     */
    public static function getOpenRegistration( $flushCache = false )
    {
        static $CACHE_KEY = 'config.open_registration';
        static $COLUMNS = array( 'allow_open_registration', 'open_reg_role_id', 'open_reg_email_service_id', 'open_reg_email_template_id' );

        if ( $flushCache )
        {
            if ( false === Platform::storeDelete( $CACHE_KEY ) )
            {
                Log::error( 'Unable to delete configuration cache key "' . $CACHE_KEY . '"' );
                Platform::storeSet( $CACHE_KEY, null, static::CONFIG_CACHE_TTL );
            }
        }

        if ( null !== ( $_values = Platform::storeGet( $CACHE_KEY, null, false, static::CONFIG_CACHE_TTL ) ) )
        {
            return $_values;
        }

        /** @var $_config \DreamFactory\Platform\Yii\Models\Config */
        $_config = ResourceStore::model( 'config' )->find( array( 'select' => $COLUMNS ) );

        if ( null === $_config )
        {
            throw new InternalServerErrorException( 'Unable to load system configuration.' );
        }

        if ( !$_config->allow_open_registration )
        {
            Platform::storeSet( $CACHE_KEY, null, PlatformStore::TTL_FOREVER );

            return false;
        }

        Platform::storeSet( $CACHE_KEY, $_values = $_config->getAttributes( null ), static::CONFIG_CACHE_TTL );

        return $_values;
    }

    /**
     * Override for GET of public info
     *
     * @param string $operation
     * @param mixed  $resource
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
     * @throws \Exception
     * @return bool
     */
    public function checkPermission( $operation, $resource = null )
    {
        // clients use basic GET on global config to startup
        if ( static::GET == $operation )
        {
            return true;
        }

        return ResourceStore::checkPermission( $operation, $this->_serviceName, $resource );
    }

    /**
     * {@InheritDoc}
     */
    protected function _determineRequestedResource( &$ids = null, &$records = null, $triggerEvent = true )
    {
        $_payload = parent::_determineRequestedResource( $ids, $records, $triggerEvent );

        if ( HttpMethod::GET != $this->_action )
        {
            //	Check for CORS changes...
            if ( null !== ( $_hostList = Option::get( $_payload, 'allowed_hosts', null, true ) ) )
            {
                SystemManager::setAllowedHosts( $_hostList );
            }

            try
            {
                if ( Session::isSystemAdmin() )
                {
                    if ( isset( $_payload['lookup_keys'] ) )
                    {
                        LookupKey::assignLookupKeys( $_payload['lookup_keys'] );
                    }
                }
            }
            catch ( ForbiddenException $_ex )
            {
                // do nothing
            }
            catch ( UnauthorizedException $_ex )
            {
                // do nothing
            }
        }

        return $_payload;
    }

    /**
     * {@InheritDoc}
     */
    protected function _postProcess()
    {
        static $CACHE_KEY = 'platform.fabric_hosted';
        static $VERSION_CACHE_KEY = 'platform.version_info';

        static $_fabricHosted, $_fabricPrivate;

        $_refresh = ( HttpMethod::GET != $this->_action );
        $_fabricHosted = $_fabricHosted ? : Platform::storeGet( $CACHE_KEY, Fabric::fabricHosted(), static::CONFIG_CACHE_TTL );
        $_fabricPrivate = $_fabricPrivate ? : Fabric::hostedPrivatePlatform();

        //	Only return a single row, not in an array
        if ( is_array( $this->_response ) && !Pii::isEmpty( $_record = Option::get( $this->_response, 'record' ) ) && count( $_record ) >= 1 )
        {
            $this->_response = current( $_record );
        }

        /**
         * Version and upgrade support
         */
        if ( $_refresh || null === ( $_versionInfo = Platform::storeGet( $VERSION_CACHE_KEY, null, false, static::CONFIG_CACHE_TTL ) ) )
        {
            $_versionInfo = array(
                'dsp_version'       => $_currentVersion = SystemManager::getCurrentVersion(),
                'latest_version'    => $_latestVersion = ( $_fabricHosted ? $_currentVersion : SystemManager::getLatestVersion() ),
                'upgrade_available' => version_compare( $_currentVersion, $_latestVersion, '<' ),
            );

            Platform::storeSet( $VERSION_CACHE_KEY, $_versionInfo, static::CONFIG_CACHE_TTL );
        }

        $this->_response = array_merge( $this->_response, $_versionInfo );
        unset( $_versionInfo );

        /**
         * Remote login support
         */
        $this->_response['is_guest'] = Pii::guest();
        $this->_response['is_hosted'] = $_fabricHosted;
        $this->_response['is_private'] = $_fabricPrivate;
        $this->_response['allow_admin_remote_logins'] = Pii::getParam( 'dsp.allow_admin_remote_logins', false );
        $this->_response['allow_remote_logins'] =
            ( Pii::getParam( 'dsp.allow_remote_logins', false ) && Option::getBool( $this->_response, 'allow_open_registration' ) );

        if ( $this->_response['allow_remote_logins'] )
        {
            $_remoteProviders = $this->_getRemoteProviders();
            $this->_response['remote_login_providers'] = array();

            if ( empty( $_remoteProviders ) )
            {
                $this->_response['allow_remote_logins'] = false;
            }
            else
            {
                $this->_response['remote_login_providers'] = array_values( $_remoteProviders );
            }

            unset( $_remoteProviders );
        }
        else
        {
            //	No providers, no admin remote logins
            $this->_response['allow_admin_remote_logins'] = false;
        }

        /** CORS support **/
        $this->_response['allowed_hosts'] = SystemManager::getAllowedHosts();

        try
        {
            if ( Session::isSystemAdmin() )
            {
                $this->_response['lookup_keys'] = $this->_getLookupKeys();
            }
        }
        catch ( ForbiddenException $_ex )
        {
            // do nothing
        }
        catch ( UnauthorizedException $_ex )
        {
            // do nothing
        }

        parent::_postProcess();
    }

    /**
     * @param bool $flushCache
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array|mixed
     */
    protected function _getLookupKeys( $flushCache = false )
    {
        static $CACHE_KEY = 'config.lookup_keys';
        static $CACHE_TTL = 60;

        if ( $flushCache )
        {
            Platform::storeDelete( $CACHE_KEY );
        }

        if ( null !== ( $_lookups = Platform::storeGet( $CACHE_KEY, null, false, $CACHE_TTL ) ) )
        {
            return $_lookups;
        }

        $_lookups = array();

        /** @var LookupKey[] $_models */
        $_models = ResourceStore::model( 'lookup_key' )->findAll( 'user_id IS NULL AND role_id IS NULL' );

        if ( !empty( $_models ) )
        {
            foreach ( $_models as $_row )
            {
                $_lookups[] = $_row->getAttributes( array( 'name', 'value', 'private' ) );
            }
        }

        //  Keep these for 1 minute at the most
        Platform::storeSet( $CACHE_KEY, $_lookups, $CACHE_TTL );

        return $_lookups;
    }

    /**
     * @param bool $refresh
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \Exception
     * @return array|mixed
     */
    protected function _getRemoteProviders( $refresh = false )
    {
        static $CACHE_KEY = 'config.remote_login_providers';

        if ( !$refresh && null !== ( $_remoteProviders = Platform::storeGet( $CACHE_KEY, null, false, static::CONFIG_CACHE_TTL ) ) )
        {
            return $_remoteProviders;
        }

        $_remoteProviders = array();

        //*************************************************************************
        //	Global Providers
        //*************************************************************************

        if ( null === ( $_providers = Pii::getState( 'platform.global_providers' ) ) )
        {
            Pii::setState( 'platform.global_providers', $_providers = Fabric::getProviderCredentials() );
        }

        if ( !empty( $_providers ) )
        {
            foreach ( $_providers as $_row )
            {
                if ( 1 == $_row->login_provider_ind )
                {
                    $_config = $this->_sanitizeProviderConfig( $_row->config_text, true );

                    $_remoteProviders[] = array(
                        'id'            => $_row->id,
                        'provider_name' => $_row->provider_name_text,
                        'api_name'      => $_row->endpoint_text,
                        'config_text'   => $_config,
                        'is_active'     => $_row->enable_ind,
                        'is_system'     => true,
                    );
                }

                unset( $_row );
            }
        }

        unset( $_providers );

        //*************************************************************************
        //	Local Providers
        //*************************************************************************

        /** @var Provider[] $_models */
        $_models = ResourceStore::model( 'provider' )->findAll( array( 'order' => 'provider_name' ) );

        if ( !empty( $_models ) )
        {
            foreach ( $_models as $_row )
            {
                if ( 1 == $_row->is_login_provider )
                {
                    $_config = $this->_sanitizeProviderConfig( $_row->config_text );

                    //	Local providers take precedent over global...
                    foreach ( $_remoteProviders as $_index => $_priorRow )
                    {
                        if ( $_priorRow['api_name'] == $_row->api_name )
                        {
                            unset( $_remoteProviders[ $_index ] );
                            break;
                        }
                    }

                    $_remoteProviders[] = array_merge( $_row->getAttributes(), array( 'config_text' => $_config ) );
                }

                unset( $_row );
            }

            unset( $_models );
        }

        Platform::storeSet( $CACHE_KEY, $_remoteProviders, static::CONFIG_CACHE_TTL );

        return $_remoteProviders;
    }

    /**
     * Strictly for your protection!
     *
     * @param array $config
     * @param bool  $force
     *
     * @throws \Exception
     * @return array
     */
    protected function _sanitizeProviderConfig( $config, $force = false )
    {
        try
        {
            if ( false === $force && Session::isSystemAdmin() )
            {
                return $config;
            }
        }
        catch ( \Exception $_ex )
        {
            //	Ignored 401
            if ( HttpResponse::Unauthorized != $_ex->getCode() )
            {
                throw $_ex;
            }
        }

        $_config = Option::clean( $config );

        //	Remove sensitive information before returning for non-admins
        Option::remove( $_config, 'client_secret' );
        Option::remove( $_config, 'access_token' );
        Option::remove( $_config, 'refresh_token' );

        return $_config;
    }
}
