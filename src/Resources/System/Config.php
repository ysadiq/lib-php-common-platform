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

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\UnauthorizedException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Services\BasePlatformService;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Platform\Utility\Fabric;
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
    //	Methods
    //*************************************************************************

    /**
     * Constructor
     *
     * @param BasePlatformService $consumer
     * @param array               $resourceArray
     *
     * @return Config
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
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     */
    public static function getOpenRegistration()
    {
        /** @var $_config \DreamFactory\Platform\Yii\Models\Config */
        $_fields = 'allow_open_registration, open_reg_role_id, open_reg_email_service_id, open_reg_email_template_id';

        $_config = ResourceStore::model( 'config' )->find( array( 'select' => $_fields ) );

        if ( null === $_config )
        {
            throw new InternalServerErrorException( 'Unable to load system configuration.' );
        }

        if ( !$_config->allow_open_registration )
        {
            return false;
        }

        return $_config->getAttributes( null );
    }

    /**
     * Override for GET of public info
     *
     * @param string $operation
     * @param null   $resource
     *
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
    protected function _determineRequestedResource( &$ids = null, &$records = null )
    {
        $_payload = parent::_determineRequestedResource( $ids, $records );

        if ( 'GET' != $this->_action )
        {
            //	Check for CORS changes...
            if ( null !== ( $_hostList = Option::get( $_payload, 'allowed_hosts', null, true ) ) )
            {
//			Log::debug( 'Allowed hosts given: ' . print_r( $_hostList, true ) );
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
        static $_fabricHosted;

        $_fabricHosted = $_fabricHosted ? : \Kisma::get( 'platform.fabric_hosted', Fabric::fabricHosted() );

        //	Only return a single row, not in an array
        if ( is_array( $this->_response ) && !Pii::isEmpty( $_record = Option::get( $this->_response, 'record' ) ) && count( $_record ) >= 1 )
        {
            $this->_response = current( $_record );
        }

        /**
         * Version and upgrade support
         */
        if ( null === ( $_versionInfo = \Kisma::get( 'platform.version_info' ) ) )
        {
            $_versionInfo = array(
                'dsp_version'       => $_currentVersion = SystemManager::getCurrentVersion(),
                'latest_version'    => $_latestVersion = ( $_fabricHosted ? $_currentVersion : SystemManager::getLatestVersion() ),
                'upgrade_available' => version_compare( $_currentVersion, $_latestVersion, '<' ),
            );

            \Kisma::set( 'platform.version_info', $_versionInfo );
        }

        $this->_response = array_merge( $this->_response, $_versionInfo );
        unset( $_versionInfo );

        /**
         * Remote login support
         */
        $this->_response['is_guest'] = Pii::guest();
        $this->_response['allow_admin_remote_logins'] = Pii::getParam( 'dsp.allow_admin_remote_logins', false );
        $this->_response['allow_remote_logins'] =
            ( Pii::getParam( 'dsp.allow_remote_logins', false ) && Option::getBool( $this->_response, 'allow_open_registration' ) );

        if ( false !== $this->_response['allow_remote_logins'] )
        {
            $_remoteProviders = $this->_getRemoteProviders();

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
     * @return array|mixed
     */
    protected function _getLookupKeys()
    {
        /** @var LookupKey[] $_models */
        $_models = ResourceStore::model( 'lookup_key' )->findAll( 'user_id IS NULL AND role_id IS NULL' );

        $_lookups = array();
        if ( !empty( $_models ) )
        {
            foreach ( $_models as $_row )
            {
                $_lookups[] = $_row->getAttributes( array( 'name', 'value', 'private' ) );
            }
        }

        return $_lookups;
    }

    /**
     * @return array|mixed
     */
    protected function _getRemoteProviders()
    {
        if ( null === ( $_remoteProviders = Pii::getState( 'platform.remote_login_providers' ) ) )
        {
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
                                unset( $_remoteProviders[$_index] );
                                break;
                            }
                        }

                        $_remoteProviders[] = array_merge( $_row->getAttributes(), array( 'config_text' => $_config ) );
                    }

                    unset( $_row );
                }

                unset( $_models );
            }

            Pii::setState( 'platform.remote_login_providers', $_remoteProviders );
        }

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
