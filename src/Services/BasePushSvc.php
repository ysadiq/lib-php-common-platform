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

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Interfaces\ServiceOnlyResourceLike;
use DreamFactory\Platform\Utility\RestData;
use Kisma\Core\Utility\Option;

/**
 * BasePushSvc
 * Base Push Notification Service giving REST access to push notification services.
 *
 */
abstract class BasePushSvc extends BasePlatformRestService implements ServiceOnlyResourceLike
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct( $settings = array() )
    {
        if ( null === Option::get( $settings, 'verb_aliases' ) )
        {
            //	Default verb aliases
            $settings['verb_aliases'] = array(
                static::PUT   => static::POST,
                static::MERGE => static::PATCH,
            );
        }

        parent::__construct( $settings );
    }

    /**
     * Setup container and paths
     *
     * @param string $resourcePath
     *
     * @return $this
     */
    protected function _detectResourceMembers( $resourcePath = null )
    {
        parent::_detectResourceMembers( $resourcePath );

        return $this;
    }

    protected function _detectRequestMembers()
    {
        // override - don't call parent class here
        $this->_requestPayload = Option::clean( RestData::getPostedData( true, true ) );
    }

    /**
     * List all possible resources accessible via this service,
     * return false if this is not applicable
     *
     * @return array|boolean
     */
    protected function _listResources()
    {
        return array('resource' => array());
    }

    /**
     * @return mixed
     */
    protected function _preProcess()
    {
        parent::_preProcess();

        $this->checkPermission( $this->_action );
    }

    /**
     * @return array|bool
     */
    protected function _handleResource()
    {
        if ( empty( $this->_resource ) )
        {
            switch ( $this->_action )
            {
                case static::GET:
                    $_result = $this->retrieveResources( $this->_requestPayload );

                    $this->_triggerActionEvent( $_result );

                    return array('resource' => $_result);
                default:
                    throw new BadRequestException( 'Currently only GET is supported for this API resource.' );
                    break;
            }
        }

        return parent::_handleResource();
    }

    /**
     * @return array
     */
    protected function _handleGet()
    {
        $_result = $this->_retrieveTopic( $this->_resource, $this->_requestPayload );

        $this->_triggerActionEvent( $_result );

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handlePost()
    {
        if ( empty( $this->_requestPayload ) )
        {
            throw new BadRequestException( 'No post detected in request.' );
        }

        $this->_triggerActionEvent( $this->_response );

        $_result = $this->_pushMessage( $this->_resource, $this->_requestPayload );

        return $_result;
    }

    /**
     * @param array|null $options
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public function retrieveResources( $options = null )
    {
        $_refresh = Option::getBool( $options, 'refresh' );
        $_namesOnly = Option::getBool( $options, 'names_only' );
        $_asComponents = Option::getBool( $options, 'as_access_components' );
        $_resources = array();

        if ( $_asComponents )
        {
            $_resources = array('', '*');
        }
        try
        {
            $_result = static::_listTopics( $_refresh );
            foreach ( $_result as $_topics )
            {
                if ( null != $_name = Option::get( $_topics, 'name' ) )
                {
                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        if ( $_asComponents || $_namesOnly )
                        {
                            $_resources[] = $_name;
                        }
                        else
                        {
                            $_topics['access'] = $_access;
                            $_resources[] = $_topics;
                        }
                    }
                }
            }

            return $_resources;
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to list resources for this service.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * @param bool $refresh
     *
     * @return array
     */
    protected function _listTopics( /** @noinspection PhpUnusedParameterInspection */
        $refresh = true )
    {
        return array();
    }

    /**
     * @param string       $resource
     *
     * @return array
     */
    protected function _retrieveTopic( $resource )
    {
        return array();
    }

    /**
     * @param string       $resource
     * @param string|array $request
     *
     * @return array
     */
    protected function _pushMessage( $resource, $request )
    {
        return array();
    }
}
