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

use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\UnauthorizedException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Platform\Utility\DataFormatter;
use DreamFactory\Platform\Utility\Fabric;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Yii\Models\LookupKey;
use DreamFactory\Platform\Yii\Models\Provider;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpResponse;
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
    const CONFIG_CACHE_TTL = Platform::DEFAULT_CACHE_TTL;
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
    /**
     * @type string The cache key for remote login providers config
     */
    const GLOBAL_PROVIDERS_CACHE_KEY = 'config.global_remote_login_providers';

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
        //	Check for CORS changes...
        if ( null !== ( $_hostList = Ifset::get( $this->_requestPayload, 'allowed_hosts' ) ) )
        {
            SystemManager::setAllowedHosts( $_hostList );
            unset( $this->_requestPayload['allowed_hosts'] );
        }

        try
        {
            if ( Session::isSystemAdmin() && isset( $this->_requestPayload['lookup_keys'] ) )
            {
                LookupKey::assignLookupKeys( $this->_requestPayload['lookup_keys'] );
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

        //  Changing config, bust caches
        static::flushCachedConfig();

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

        $_data = DataFormatter::flattenArray( $this->_response );
        $_config = null; //static::getCurrentConfig( $_refresh );
        $_params = Pii::params();

        if ( $_params instanceof \CAttributeCollection )
        {
            $_params = $_params->toArray();
        }

        /**
         * Version and upgrade support
         */
        if ( empty( $_config ) || $_refresh )
        {
            $_config = array(
                //  General settings
                'allow_admin_remote_logins' => IfSet::getBool( $_params, 'dsp.allow_admin_remote_logins' ),
                'allow_remote_logins'       => ( IfSet::getBool( $_params, 'dsp.allow_remote_logins' ) &&
                    IfSet::getBool( $_data, 'allow_open_registration' ) ),
                'remote_login_providers'    => array(),
                'restricted_verbs'          => IfSet::get( $_params, 'dsp.restricted_verbs', array() ),
                'install_type'              => IfSet::get( $_params, 'dsp.install_type' ),
                'install_name'              => IfSet::get( $_params, 'dsp.install_name' ),
                'is_hosted'                 => $_fabricHosted = IfSet::getBool( $_params, 'dsp.fabric_hosted' ),
                'is_private'                => Fabric::isAllowedHost(),
                //  DSP version info
                'dsp_version'               => $_currentVersion = SystemManager::getCurrentVersion(),
                'server_os'                 => strtolower( php_uname( 's' ) ),
                'latest_version'            => $_latestVersion =
                    ( $_fabricHosted ? $_currentVersion : SystemManager::getLatestVersion() ),
                'upgrade_available'         => version_compare( $_currentVersion, $_latestVersion, '<' ),
                //  CORS Support
                'allowed_hosts'             => SystemManager::getAllowedHosts(),
                'states'                    => Platform::getPlatformStates(),
                'paths'                     => array(
                    'applications' => IfSet::get( $_params, 'applications_path' ),
                    'base'         => IfSet::get( $_params, 'app.base_path' ),
                    'plugins'      => IfSet::get( $_params, 'app.plugins_path' ),
                    'private'      => IfSet::get( $_params, 'app.private_path' ),
                    'storage'      => IfSet::get( $_params, 'storage_path' ),
                    'swagger'      => IfSet::get( $_params, 'swagger_path' ),
                ),
                'timestamp_format'          => IfSet::get( $_params, 'platform.timestamp_format' ),
            );

            //  Get the login provider array
            if ( $_config['allow_remote_logins'] )
            {
                $_config['remote_login_providers'] = $this->_getRemoteProviders();
            }
            else
            {
                //@todo suspect logic. shouldn't it be based on allow_remote_logins?

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

        $_config['is_guest'] = Pii::guest();

        //	Only return a single row, not in an array
        $this->_response = array_merge(
            $_data,
            $_config
        );

        @ksort( $this->_response );

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

        $flushCache && Platform::storeDelete( static::OPEN_REG_CACHE_KEY );

        if ( null === ( $_config = Platform::storeGet( static::OPEN_REG_CACHE_KEY ) ) || !is_array( $_config ) )
        {
            /** @var $_config \DreamFactory\Platform\Yii\Models\Config */
            if ( null === ( $_config = ResourceStore::model( 'config' )->find( array('select' => $COLUMNS) ) ) )
            {
                throw new InternalServerErrorException( 'Unable to load system configuration.' );
            }

            $_config = $_config->getAttributes();

            Platform::storeSet( static::OPEN_REG_CACHE_KEY, $_config );
        }

        return !$_config['allow_open_registration'] ? false : $_config;
    }

    /**
     * @param bool $flushCache
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array|mixed
     */
    protected function _getLookupKeys( $flushCache = false )
    {
        $flushCache && Platform::storeDelete( static::LOOKUP_CACHE_KEY );

        if ( null ===
            ( $_lookups = Platform::storeGet( static::LOOKUP_CACHE_KEY, null, false, static::CONFIG_CACHE_TTL ) )
        )
        {
            /** @var LookupKey[] $_models */
            $_models =
                ResourceStore::model( 'lookup_key' )->findAll(
                    array(
                        'select'    => 'name, value, private',
                        'condition' => 'user_id IS NULL AND role_id IS NULL',
                    )
                );

            $_lookups = array();

            if ( !empty( $_models ) )
            {
                foreach ( $_models as $_row )
                {
                    $_lookups[] = $_row->getAttributes();
                }
            }

            //  Keep these for 1 minute at the most
            Platform::storeSet( static::LOOKUP_CACHE_KEY, $_lookups, static::CONFIG_CACHE_TTL );
        }

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
        $flushCache && Platform::storeDelete( static::PROVIDERS_CACHE_KEY );

        if ( null !== ( $_remoteProviders = Platform::storeGet( static::PROVIDERS_CACHE_KEY ) ) )
        {
            return $_remoteProviders;
        }

        $_remoteProviders = array();

        //*************************************************************************
        //	Global Providers
        //*************************************************************************

        if ( null === ( $_providers = Platform::storeGet( static::GLOBAL_PROVIDERS_CACHE_KEY ) ) )
        {
            $_providers = Fabric::getProviderCredentials();
            Platform::storeSet( static::GLOBAL_PROVIDERS_CACHE_KEY, $_providers );
        }

        if ( !empty( $_providers ) )
        {
            foreach ( $_providers as $_row )
            {
                if ( $_row->login_provider_ind )
                {
                    $_config = $this->_sanitizeProviderConfig( $_row->config_text, true );

                    $_remoteProviders[] = array(
                        'id'                => $_row->id,
                        'provider_name'     => $_row->provider_name_text,
                        'api_name'          => $_row->endpoint_text,
                        'config_text'       => $_config,
                        'is_active'         => $_row->enable_ind,
                        'is_system'         => true,
                        'is_login_provider' => true,
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
        $_models =
            ResourceStore::model( 'provider' )->findAll(
                array(
                    'select' => 'id, provider_name, api_name, config_text, is_active, is_system, is_login_provider',
                    'order'  => 'provider_name',
                )
            );

        if ( !empty( $_models ) )
        {
            foreach ( $_models as $_row )
            {
                if ( $_row->is_login_provider )
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

//        Log::debug( 'Remote providers: ' . print_r( $_remoteProviders, true ) );

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
     * @param bool $flush If true, key is removed after retrieval. On the subsequent call the cache will be rebuilt
     *                    before return
     *
     * @return array
     */
    public static function getCurrentConfig( $flush = true )
    {
        return Platform::storeGet( static::CACHE_KEY, null, $flush, static::CONFIG_CACHE_TTL );
    }

    /**
     * Flushes all cached configuration values
     */
    public static function flushCachedConfig()
    {
        Platform::storeDelete( static::CACHE_KEY );
        Platform::storeDelete( static::LAST_RESPONSE_CACHE_KEY );
        Platform::storeDelete( static::LOOKUP_CACHE_KEY );
        Platform::storeDelete( static::OPEN_REG_CACHE_KEY );
        Platform::storeDelete( static::PROVIDERS_CACHE_KEY );
        Platform::storeDelete( static::GLOBAL_PROVIDERS_CACHE_KEY );
    }
}
