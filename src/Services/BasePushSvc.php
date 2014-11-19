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
use DreamFactory\Platform\Interfaces\ServiceOnlyResourceLike;
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

        switch ( $this->_resource )
        {
            case static::SCHEMA_RESOURCE:
                return $this->_handleSchema();
                break;

            default:
                return parent::_handleResource();
                break;
        }
    }

    /**
     * @return array
     */
    protected function _handleGet()
    {
        //	Single resource by ID
        if ( !empty( $this->_resourceId ) )
        {
            $_result = null;
            $this->_triggerActionEvent( $_result );

            return $_result;
        }

        $_ids = Option::get( $this->_requestPayload, 'ids' );

        //	Multiple resources by ID
        if ( !empty( $_ids ) )
        {
            $_result = $this->retrieveRecordsByIds( $this->_resource, $_ids, $this->_requestPayload );
        }
        else
        {
            $_records = Option::get( $this->_requestPayload, static::RECORD_WRAPPER );

            if ( !empty( $_records ) )
            {
                // passing records to have them updated with new or more values, id field required
                $_result = $this->retrieveRecords( $this->_resource, $_records, $this->_requestPayload );
            }
            else
            {
                $_filter = Option::get( $this->_requestPayload, 'filter' );
                $_params = Option::get( $this->_requestPayload, 'params', array() );

                $_result = $this->retrieveRecordsByFilter( $this->_resource, $_filter, $_params, $this->_requestPayload );
            }
        }

        $_meta = Option::get( $_result, 'meta', null, true );
        $_result = array(static::RECORD_WRAPPER => $_result);

        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        $this->_triggerActionEvent( $_result );

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handlePost()
    {
        if ( !empty( $this->_resourceId ) )
        {
            throw new BadRequestException( 'Create record by identifier not currently supported.' );
        }

        $_records = Option::get( $this->_requestPayload, static::RECORD_WRAPPER );
        if ( empty( $_records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->_triggerActionEvent( $this->_response );

        $_result = $this->createRecords( $this->_resource, $_records, $this->_requestPayload );

        if ( $this->_singleRecordAmnesty )
        {
            return Option::get( $_result, 0, $_result );
        }

        $_meta = Option::get( $_result, 'meta', null, true );
        $_result = array(static::RECORD_WRAPPER => $_result);
        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        return $_result;
    }
}
