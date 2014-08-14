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
use DreamFactory\Platform\Exceptions\BadRequestException;
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
     * @type array The currently cached configuration for this DSP
     */
    const CACHE_KEY = 'platform.config';
    /**
     * @type array The currently cached configuration for this DSP
     */
    const LAST_RESPONSE_CACHE_KEY = 'config.last_response';
    /**
     * @type int The number of seconds at most to cache these resources
     */
    const CONFIG_CACHE_TTL = PlatformStore::DEFAULT_TTL;
    /**
     * @type string The cache key for lookups config
     */
    const LOOKUP_CACHE_KEY = 'config.lookup_keys';
    /**
     * @type string The cache key for open registration config
     */
    const OPEN_REG_CACHE_KEY = 'config.open_registration';
    /**
     * @type string The cache key for remote login providers config
     */
    const PROVIDERS_CACHE_KEY = 'config.remote_login_providers';

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
                    static::PATCH => static::PUT,
                    static::MERGE => static::PUT,
                )
            )
        );
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
        //  Clients use basic GET on global config to startup
        if ( static::GET == $operation )
        {
            return true;
        }

        return ResourceStore::checkPermission( $operation, $this->_serviceName, $resource );
    }

    /**
     * {@InheritDoc}
     */
    protected function _handlePut()
    {
        // changing config, bust caches
        if ( false === Platform::storeDelete( static::OPEN_REG_CACHE_KEY ) )
        {
            Log::notice( 'Unable to delete configuration cache key "' . static::OPEN_REG_CACHE_KEY . '"' );
            Platform::storeSet( static::OPEN_REG_CACHE_KEY, null, static::CONFIG_CACHE_TTL );
        }
        if ( false === Platform::storeDelete( static::PROVIDERS_CACHE_KEY ) )
        {
            Log::notice( 'Unable to delete configuration cache key "' . static::PROVIDERS_CACHE_KEY . '"' );
            Platform::storeSet( static::PROVIDERS_CACHE_KEY, null, static::CONFIG_CACHE_TTL );
        }

        //	Check for CORS changes...
        if ( null !== ( $_hostList = Option::get( $this->_requestPayload, 'allowed_hosts', null, true ) ) )
        {
            SystemManager::setAllowedHosts( $_hostList );
        }

        try
        {
            if ( Session::isSystemAdmin() )
            {
                if ( isset( $this->_requestPayload['lookup_keys'] ) )
                {
                    LookupKey::assignLookupKeys( $this->_requestPayload['lookup_keys'] );

                    // changing config, bust cache
                    if ( false === Platform::storeDelete( static::LOOKUP_CACHE_KEY ) )
                    {
                        Log::notice( 'Unable to delete configuration cache key "' . static::LOOKUP_CACHE_KEY . '"' );
                        Platform::storeSet( static::LOOKUP_CACHE_KEY, null, static::CONFIG_CACHE_TTL );
                    }
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

        return parent::_handlePut();
    }

    /**
     * {@InheritDoc}
     */
    protected function _handlePost()
    {
        // should never create new config
        throw new BadRequestException();
    }

    /**
     * {@InheritDoc}
     */
    protected function _handleDelete()
    {
        // should never delete config
        throw new BadRequestException();
    }

    /**
     * {@InheritDoc}
     * ENSURE THAT NOTHING IN THIS FUNCTION TRIGGERS AN EVENT FOR NOW!
     */
    protected function _postProcess()
    {
        //  Indicator to rebuild the config cache if the inbound request was NOT a "GET"
        $_refresh = ( static::GET != $this->_action );
        $_config = static::getCurrentConfig( $_refresh );

        /**
         * Version and upgrade support
         */
        if ( empty( $_config ) || $_refresh )
        {
            $_config = array(
                //  General settings
                'allow_admin_remote_logins' => Pii::getParam( 'dsp.allow_admin_remote_logins', false ),
                'allow_remote_logins'       => ( Pii::getParam( 'dsp.allow_remote_logins', false ) && Option::getBool( $this->_response, 'allow_open_registration' ) ),
                'remote_login_providers'    => null,
                'restricted_verbs'          => Pii::getParam( 'dsp.restricted_verbs', array() ),
                'is_hosted'                 => $_fabricHosted = Pii::getParam( 'dsp.fabric_hosted', false ),
                'is_private'                => Fabric::hostedPrivatePlatform(),
                //  DSP version info
                'dsp_version'               => $_currentVersion = SystemManager::getCurrentVersion(),
                'server_os'                 => strtolower( php_uname( 's' ) ),
                'latest_version'            => $_latestVersion = ( $_fabricHosted ? $_currentVersion : SystemManager::getLatestVersion() ),
                'upgrade_available'         => version_compare( $_currentVersion, $_latestVersion, '<' ),
                //  CORS Support
                'allowed_hosts'             => SystemManager::getAllowedHosts(),
            );

            //  Get the login provider array
            if ( $_config['allow_remote_logins'] )
            {
                $_remoteProviders = $this->_getRemoteProviders();
                $_config['remote_login_providers'] = array();
                $_config['allow_remote_logins'] = ( empty( $_remoteProviders ) ? false : array_values( $_remoteProviders ) );
                unset( $_remoteProviders );
            }
            else
            {
                //	No providers, no admin remote logins
                $_config['allow_admin_remote_logins'] = false;
            }

            try
            {
                if ( Session::isSystemAdmin() )
                {
                    $_config['lookup_keys'] = $this->_getLookupKeys( $_refresh );
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

        //	Only return a single row, not in an array
        if ( is_array( $this->_response ) && !Pii::isEmpty( $_record = Option::get( $this->_response, 'record' ) ) && count( $_record ) >= 1
        )
        {
            $this->_response = current( $_record );
        }

        $this->_response = array_merge( $this->_response, $_config );
        $this->_response['is_guest'] = Pii::guest();

        //	Cache configuration
        Platform::storeSet( static::CACHE_KEY, $_config, static::CONFIG_CACHE_TTL );
        Platform::storeSet( static::LAST_RESPONSE_CACHE_KEY, $this->_response, static::CONFIG_CACHE_TTL );

        unset( $_config );
        parent::_postProcess();
    }

    /**
     * @param bool $flushCache If true, any cached data will be removed
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array|bool
     */
    public static function getOpenRegistration( $flushCache = false )
    {
        static $COLUMNS = array(
            'allow_open_registration',
            'open_reg_role_id',
            'open_reg_email_service_id',
            'open_reg_email_template_id'
        );

        if ( $flushCache )
        {
            if ( false === Platform::storeDelete( static::OPEN_REG_CACHE_KEY ) )
            {
                Log::notice( 'Unable to delete configuration cache key "' . static::OPEN_REG_CACHE_KEY . '"' );
                Platform::storeSet( static::OPEN_REG_CACHE_KEY, null, static::CONFIG_CACHE_TTL );
            }
        }

        if ( null !== ( $_values = Platform::storeGet( static::OPEN_REG_CACHE_KEY, null, false, static::CONFIG_CACHE_TTL ) )
        )
        {
            return $_values;
        }

        /** @var $_config \DreamFactory\Platform\Yii\Models\Config */
        $_config = ResourceStore::model( 'config' )->find( array('select' => $COLUMNS) );

        if ( null === $_config )
        {
            throw new InternalServerErrorException( 'Unable to load system configuration.' );
        }

        if ( !$_config->allow_open_registration )
        {
            Platform::storeSet( static::OPEN_REG_CACHE_KEY, null, static::CONFIG_CACHE_TTL );

            return false;
        }

        $_values = $_config->getAttributes( null );
        Platform::storeSet( static::OPEN_REG_CACHE_KEY, $_values, static::CONFIG_CACHE_TTL );

        return $_values;
    }

    /**
     * @param bool $flushCache
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array|mixed
     */
    protected function _getLookupKeys( $flushCache = false )
    {
        if ( $flushCache )
        {
            if ( false === Platform::storeDelete( static::LOOKUP_CACHE_KEY ) )
            {
                Log::notice( 'Unable to delete configuration cache key "' . static::LOOKUP_CACHE_KEY . '"' );
                Platform::storeSet( static::LOOKUP_CACHE_KEY, null, static::CONFIG_CACHE_TTL );
            }
        }

        if ( null !== ( $_lookups = Platform::storeGet( static::LOOKUP_CACHE_KEY, null, false, static::CONFIG_CACHE_TTL ) )
        )
        {
            return $_lookups;
        }

        /** @var LookupKey[] $_models */
        $_models = ResourceStore::model( 'lookup_key' )->findAll( 'user_id IS NULL AND role_id IS NULL' );

        $_lookups = array();
        if ( !empty( $_models ) )
        {
            $_template = array('name', 'value', 'private');

            foreach ( $_models as $_row )
            {
                $_lookups[] = $_row->getAttributes( $_template );
            }
        }

        //  Keep these for 1 minute at the most
        Platform::storeSet( static::LOOKUP_CACHE_KEY, $_lookups, static::CONFIG_CACHE_TTL );

        return $_lookups;
    }

    /**
     * @param bool $flushCache
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \Exception
     * @return array|mixed
     */
    protected function _getRemoteProviders( $flushCache = false )
    {
        if ( $flushCache )
        {
            if ( false === Platform::storeDelete( static::PROVIDERS_CACHE_KEY ) )
            {
                Log::notice( 'Unable to delete configuration cache key "' . static::PROVIDERS_CACHE_KEY . '"' );
                Platform::storeSet( static::PROVIDERS_CACHE_KEY, null, static::CONFIG_CACHE_TTL );
            }
        }

        if ( null !== ( $_remoteProviders = Platform::storeGet( static::PROVIDERS_CACHE_KEY, null, false, static::CONFIG_CACHE_TTL ) )
        )
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
        $_models = ResourceStore::model( 'provider' )->findAll( array('order' => 'provider_name') );

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
                            unset( $_remoteProviders[$_index] );
                            break;
                        }
                    }

                    $_remoteProviders[] = array_merge( $_row->getAttributes(), array('config_text' => $_config) );
                }

                unset( $_row );
            }

            unset( $_models );
        }

        Platform::storeSet( static::PROVIDERS_CACHE_KEY, $_remoteProviders, static::CONFIG_CACHE_TTL );

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
            if ( false === $force && !Pii::guest() && Session::isSystemAdmin() )
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

    /**
     * @param bool $flush If true, key is removed after retrieval. On the subsequent call the cache will be rebuilt before return
     *
     * @return array
     */
    public static function getCurrentConfig( $flush = false )
    {
        return Platform::storeGet( static::CACHE_KEY, null, $flush, static::CONFIG_CACHE_TTL );
    }
}
