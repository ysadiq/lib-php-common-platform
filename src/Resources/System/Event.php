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
use DreamFactory\Platform\Events\Enums\PlatformServiceEvents;
use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Exceptions\StorageException;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * Event
 * System service for event management
 *
 */
class Event extends BaseSystemRestResource
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var Request The inbound request
     */
    protected $_requestObject;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param \DreamFactory\Platform\Interfaces\RestResourceLike|\DreamFactory\Platform\Interfaces\RestServiceLike $consumer
     * @param array                                                                                                $resources
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

        $this->_requestObject = Pii::request( false );

        parent::__construct( $consumer, $_config, $resources );
    }

    /**
     * Default GET implementation
     *
     * @return bool
     */
    protected function _handleGet()
    {
        if ( empty( $this->_resourceId ) && $this->_requestObject->get( 'all_events' ) )
        {
            return $this->_getAllEvents( $this->_requestObject->get( 'as_cached', false ) );
        }

        return Platform::getDispatcher()->getListeners( $this->_resourceId );
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
        $_cacheFile =
            Platform::getSwaggerPath(
                SwaggerManager::SWAGGER_CACHE_DIR . SwaggerManager::SWAGGER_EVENT_CACHE_FILE,
                true,
                true
            );

        //  If not, rebuild the swagger cache
        if ( !file_exists( $_cacheFile ) )
        {
            //  This will trigger the event dispatcher to flush as well
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
            $_template = array(
                'name'  => '{domain}',
                'paths' => array(),
            );

            $_pathTemplate = array(
                'path'  => '{route}',
                'verbs' => array(),
            );

            $_rebuild = array();

            foreach ( $_json as $_domain => $_routes )
            {
                $_service = $this->_fromTemplate( $_template, get_defined_vars() );

                foreach ( $_routes as $_route => $_verbs )
                {
                    $_path = $this->_fromTemplate( $_pathTemplate, get_defined_vars() );

                    // translate to slashes for client
                    $_pathName = Option::get( $_path, 'path', '' );
                    $_pathName = '/' . str_replace( '.', '/', $_pathName );
                    $_path['path'] = $_pathName;

                    foreach ( $_verbs as $_verb => $_event )
                    {
                        $_path['verbs'][] = array(
                            'type'    => $_verb,
                            'event'   => Option::get( $_event, 'event' ),
                            'scripts' => Option::get( $_event, 'scripts', array() ),
                        );
                    }

                    $_service['paths'][] = $_path;
                    unset( $_path );
                }

                $_rebuild[] = $_service;
                unset( $_service );
            }
        }

        return array('record' => $_rebuild);
    }

    /**
     *
     * Post/create event handler
     *
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws StorageException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array|bool
     */
    protected function _handlePost()
    {
        $_dispatcher = Platform::getDispatcher();
        $_records = Option::get( $this->_requestPayload, 'record' );
        $_response = array();

        if ( empty( $_records ) )
        {
            $_records = array($this->_requestPayload);
        }

        foreach ( $_records as $_record )
        {
            $_eventName = Option::get( $_record, 'event_name' );
            $_priority = Option::get( $_record, 'priority', 0 );
            $_listeners = Option::get( $_record, 'listeners', array() );

            if ( empty( $_eventName ) || empty( $_listeners ) )
            {
                throw new BadRequestException( 'No "event_name" or "listeners" in request.' );
            }

            if ( !is_array( $_listeners ) )
            {
                $_listeners = array($_listeners);
            }

            //  Add the listener
            foreach ( $_listeners as $_listener )
            {
                $_dispatcher->addListener( $_eventName, $_listener, $_priority );
            }

            //  Add to response
            $_response[] = array('event_name' => $_eventName, 'listeners' => $_dispatcher->getListeners( $_eventName ));
        }

        return array('record' => $_response);
    }

    /**
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     * @throws StorageException
     * @return array
     */
    protected function _handleDelete()
    {
        $_dispatcher = Platform::getDispatcher();
        $_records = Option::get( $this->_requestPayload, 'record' );
        $_response = array();

        if ( empty( $_records ) )
        {
            $_records = array($this->_requestPayload);
        }

        foreach ( $_records as $_record )
        {
            $_listeners = Option::get( $_record, 'listeners', array() );
            $_eventName = Option::get( $_record, 'event_name' );

            if ( empty( $_eventName ) || empty( $_listeners ) )
            {
                throw new BadRequestException( 'No "event_name" or "listeners" in request.' );
            }

            if ( !is_array( $_listeners ) )
            {
                $_listeners = array($_listeners);
            }

            foreach ( $_listeners as $_listener )
            {
                $_dispatcher->removeListener( $_eventName, $_listener );
            }

            //  Add to response
            $_response[] = array('event_name' => $_eventName, 'listeners' => $_dispatcher->getListeners( $_eventName ));
        }

        return array('record' => $_response);
    }

    /**
     * @param string $template
     * @param array  $variables
     *
     * @return mixed|string
     */
    protected function _fromTemplate( $template, array $variables = array() )
    {
        $_wasArray = false;
        $_template = $template;

        if ( is_array( $_template ) )
        {
            $_template = json_encode( $_template, true );

            if ( JSON_ERROR_NONE != json_last_error() )
            {
                return $template;
            }

            $_wasArray = true;
        }

        foreach ( $variables as $_key => $_value )
        {
            $_tag = '{' . ltrim( $_key, '_' ) . '}';

            if ( false !== stripos( $_template, $_tag, 0 ) )
            {
                $_template = str_ireplace( $_tag, $_value, $_template );
            }
        }

        if ( $_wasArray )
        {
            $_template = json_decode( $_template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        }

        return $_template;
    }

    /**
     * @param PlatformEvent $event            The event
     * @param string        $eventName        The event containing placeholders (i.e. {api_name})
     * @param Request|array $values           The values to use for replacements in the event name templates.
     *
     * @param bool          $addRequestValues If true, the $_REQUEST variables will be used.
     *                                        The current class's variables are also available for replacement.
     *
     * @return string
     */
    public static function normalizeEventName( $event, &$eventName, $values = null, $addRequestValues = false )
    {
        $_requestValues = $_replacements = array();

        if ( false === strpos( $eventName, '{' ) )
        {
            return $eventName;
        }

        if ( $addRequestValues && empty( $_requestValues ) )
        {
            $_request = Pii::request( true );

            if ( !empty( $_request ) )
            {
                $_requestValues = array(
                    'headers'    => $_request->headers,
                    'attributes' => $_request->attributes,
                    'cookie'     => $_request->cookies,
                    'files'      => $_request->files,
                    'query'      => $_request->query,
                    'request'    => $_request->request,
                    'server'     => $_request->server,
                    'action'     => $_request->getMethod(),
                );
            }
        }

        $_tag = $eventName;

        $_combinedValues = Option::merge(
            $_requestValues,
            is_object( $values ) ? Inflector::neutralizeObject( $values ) : Option::clean( $values ),
            $event->toArray()
        );

        if ( empty( $_replacements ) && !empty( $_combinedValues ) )
        {
            $_replacements = array();

            foreach ( $_combinedValues as $_key => $_value )
            {
                if ( is_scalar( $_value ) )
                {
                    $_replacements['{' . $_key . '}'] = $_value;
                }

                else if ( $_value instanceof \IteratorAggregate && $_value instanceof \Countable )
                {
                    foreach ( $_value as $_bagKey => $_bagValue )
                    {
                        $_bagKey = Inflector::neutralize( ltrim( $_bagKey, '_' ) );

                        if ( is_array( $_bagValue ) )
                        {
                            if ( !empty( $_bagValue ) )
                            {
                                $_bagValue = current( $_bagValue );
                            }
                            else
                            {
                                $_bagValue = null;
                            }
                        }
                        elseif ( !is_scalar( $_bagValue ) )
                        {
                            continue;
                        }

                        $_replacements['{' . $_key . '.' . $_bagKey . '}'] = $_bagValue;
                    }
                }
            }
        }

        if ( PlatformServiceEvents::contains( $_tag ) && false !== strpos( $_tag, '{api_name}.{action}' ) )
        {
            $_first = Option::getDeep( $_combinedValues, 'request_uri', 0 );
            $_second = Option::getDeep( $_combinedValues, 'request_uri', 1 );

            if ( empty( $_second ) )
            {
                $_value = $_first;
            }
            else
            {
                $_value = $_first . '.' . $_second;
            }

            $_replacements['{api_name}'] = $_value;
        }

        //	Construct and neutralize...
        $_tag = str_ireplace( array_keys( $_replacements ), array_values( $_replacements ), $_tag );

        return $eventName = $_tag;
    }

    /**
     * Converts the current event to an array merging $additions
     *
     * @param \DreamFactory\Platform\Events\PlatformEvent $event
     * @param array                                       $additions An additional data to merge
     *
     * @return array
     */
    public static function toJson( PlatformEvent $event, array $additions = array() )
    {
        return json_encode(
            array_merge(
                $event->toArray(),
                $additions
            ),
            JSON_UNESCAPED_SLASHES
        );
    }
}
