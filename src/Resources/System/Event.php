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

use DreamFactory\Platform\Components\EventProxy;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Exceptions\StorageException;
use Kisma\Core\Utility\Option;

/**
 * Event
 * System service for event management
 *
 */
class Event extends BaseSystemRestResource
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
     * @param array                                               $resources
     */
    public function __construct( $consumer, $resources = array() )
    {
        $_config = array(
            'service_name' => 'system',
            'name'         => 'Event',
            'api_name'     => 'event',
            'type'         => 'System',
            'type_id'      => PlatformServiceTypes::SYSTEM_SERVICE,
            'description'  => 'System event manager',
            'is_active'    => true,
        );

        parent::__construct( $consumer, $_config, $resources );
    }

    /**
     * Default GET implementation
     *
     * @return bool
     */
    protected function _handleGet()
    {
        if ( empty( $this->_resourceId ) && Pii::requestObject()->get( 'all_events' ) )
        {
            return $this->_getAllEvents( Pii::requestObject()->get( 'as_cached', false ) );
        }

        return parent::_handleGet();
    }

    /**
     * Retrieves the event cache file detailing all registered events
     *
     * @param bool $as_cached If true, the event cache will be returned as stored on disk. Otherwise in a more consumable format for clients.
     *
     * @return array
     */
    protected function _getAllEvents( $as_cached = false )
    {
        //  Make sure the file exists.
        $_cacheFile = Platform::getSwaggerPath( SwaggerManager::SWAGGER_CACHE_DIR . SwaggerManager::SWAGGER_EVENT_CACHE_FILE, true, true );

        //  If not, rebuild the swagger cache
        if ( !file_exists( $_cacheFile ) )
        {
            SwaggerManager::clearCache();

            //  Still not here? No events then
            if ( !file_exists( $_cacheFile ) )
            {
                return array();
            }
        }

        $_json = json_decode( file_get_contents( $_cacheFile ), true );

        if ( false === $_json || JSON_ERROR_NONE !== json_last_error() )
        {
            Log::error( 'Error reading contents of "' . $_cacheFile . '"' );

            return array();
        }

        //  Original version?
        if ( 'true' == $as_cached )
        {
            $_rebuild = $_json;
        }
        else
        {
            /**
             * Rebuild the cached structure into a more consumable client version
             */
            $_rebuild = array();

            foreach ( $_json as $_domain => $_routes )
            {
                foreach ( $_routes as $_route => $_verbs )
                {
                    $_service = array( 'path' => $_route, 'name' => $_domain, 'verbs' => array() );

                    foreach ( $_verbs as $_verb => $_event )
                    {
                        $_service['verbs'][] = array(
                            'type'    => $_verb,
                            'event'   => Option::get( $_event, 'event' ),
                            'scripts' => Option::get( $_event, 'scripts', array() ),
                        );
                    }

                    $_rebuild[] = $_service;
                    unset( $_service );
                }
            }
        }

        return array( 'record' => $_rebuild );
    }

    /**
     * Post/create event handler
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array|bool
     */
    protected function _handlePost()
    {
        parent::_handlePost();

        $_request = Pii::app()->getRequestObject();

        $_body = @json_decode( $_request->getContent(), true );
        $_eventName = $_listeners = $_apiKey = $_priority = null;

        if ( !empty( $_body ) && JSON_ERROR_NONE == json_last_error() )
        {
            $_eventName = Option::get( $_body, 'event_name' );
            $_listeners = Option::get( $_body, 'listeners' );
            $_apiKey = Option::get( $_body, 'api_key', 'unknown' );
            $_priority = Option::get( $_body, 'priority', 0 );
        }

        if ( empty( $_eventName ) || ( !is_array( $_listeners ) || empty( $_listeners ) ) )
        {
            throw new BadRequestException( 'You must specify an "event_name", "listeners", and an "api_key" in your POST.' );
        }

        /** @var \DreamFactory\Platform\Yii\Models\Event $_model */
        $_model = ResourceStore::model( 'event' )->find(
            array(
                'condition' => 'event_name = :event_name',
                'params'    => array(
                    ':event_name' => $_eventName
                )
            )
        );

        if ( null === $_model )
        {
            $_model = ResourceStore::model( 'event' );
            $_model->setIsNewRecord( true );
            $_model->event_name = $_eventName;
        }

        //	Merge listeners
        $_model->listeners = array_merge( $_model->listeners, $_listeners );

        try
        {
            if ( !$_model->save() )
            {
                throw new StorageException( $_model->getErrorsForLogging() );
            }
        }
        catch ( \Exception $_ex )
        {
            //	Log error
            throw new InternalServerErrorException( $_ex->getMessage() );
        }

        Pii::app()->on( $_eventName, $_listeners, $_priority );

        return array( 'record' => $_model->getAttributes() );
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     */
    protected function _handleDelete()
    {
        $_request = Pii::app()->getRequestObject();

        $_body = @json_decode( $_request->getContent(), true );
        $_eventName = $_listeners = $_apiKey = $_priority = null;

        if ( !empty( $_body ) && JSON_ERROR_NONE == json_last_error() )
        {
            $_eventName = Option::get( $_body, 'event_name' );
            $_listeners = Option::get( $_body, 'listeners' );
            $_priority = Option::get( $_body, 'priority', 0 );
        }

        if ( empty( $_eventName ) || ( !is_array( $_listeners ) || empty( $_listeners ) ) || empty( $_apiKey ) )
        {
            throw new BadRequestException( 'You must specify an "event_name", "listeners", and an "api_key" in your POST.' );
        }

        /** @var \DreamFactory\Platform\Yii\Models\Event $_model */
        $_model = ResourceStore::model( 'event' )->find(
            array(
                'condition' => 'event_name = :event_name',
                'params'    => array(
                    ':event_name' => $_eventName
                )
            )
        );

        if ( null === $_model )
        {
            throw new NotFoundException( 'The requested event "' . $_eventName . '" could not be found.' );
        }

        //	Remove requested listener
        $_storedListeners = $_model->listeners;

        foreach ( $_storedListeners as $_key => $_listener )
        {
            foreach ( $_listeners as $_listenerToRemove )
            {
                if ( $_listener == $_listenerToRemove )
                {
                    unset( $_storedListeners[$_key] );
                }
            }
        }

        $_model->listeners = $_storedListeners;

        try
        {
            if ( !$_model->save() )
            {
                throw new StorageException( $_model->getErrorsForLogging() );
            }
        }
        catch ( \Exception $_ex )
        {
            //	Log error
            throw new InternalServerErrorException( $_ex->getMessage() );
        }

        Pii::app()->on( $_eventName, $_listeners, $_priority );

        return array( 'record' => $_model->getAttributes() );
    }

}
