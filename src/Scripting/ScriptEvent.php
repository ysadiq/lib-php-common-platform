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
namespace DreamFactory\Platform\Scripting;

use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Platform\Exceptions\NotImplementedException;
use DreamFactory\Platform\Resources\System\Config;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * Acts as a proxy between a DSP PHP $event and a server-side script
 */
class ScriptEvent
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Creates a generic, consistent event for scripting and notifications
     *
     * The returned array is as follows:
     *
     *  array(
     *      //  This contains information about the event itself (READ-ONLY)
     *      'event' => array(
     *          'id'                => 'A unique ID assigned to this event',
     *          'name'              => 'event.name',
     *          'trigger'           => '{api_name}/{resource}',
     *          'stop_propagation'  => [true|false],
     *          'dispatcher'        => array(
     *              'id'            => 'A unique ID assigned to the dispatcher of this event',
     *          ),
     *          //  Information about the triggering request
     *          'request'           => array(
     *              'timestamp'     => 'timestamp of the initial request',
     *              'api_name'      =>'The api_name of the called service',
     *              'resource'      => 'The name of the resource requested',
     *              'path'          => '/full/path/that/triggered/event',
     *          ),
     *      ),
     *      //  This contains the static configuration of the entire platform (READ-ONLY)
     *      'platform' => array(
     *          'api'               => [wormhole to inline-REST API],
     *          'config'            => [standard DSP configuration update],
     *      ),
     *      //  This contains any additional information the event sender wanted to convey (READ-ONLY)
     *      'details' => array(),
     *      //  THE MEAT! This contains the ACTUAL data received from the client, or what's being sent back to the client (READ-WRITE).
     *      'payload' => array(
     *          //  See recap above for formats
     *      ),
     *  );
     *
     * Please note that the format of the payload differs slightly on multi-row result sets. In the v1.0 REST API, if a single row of data
     * is to be returned from a request, it is merged into the root of the resultant array. If there are multiple rows, they are placed into
     * n key called 'record'. To make matter worse, if you make a multi-row request via XML, and wrap your input payload in a
     * <records><record></record>...</records> type wrapper, the resultant array will be placed a level deeper ($payload['records']['record'] = $results).
     *
     * Therefore the data exposed by the event system has been "normalized" to provide a reliable and consistent manner in which to process said data.
     * There should be no need for wasting time trying to determine if your data is "maybe here, or maybe there, or maybe over there even" when received by
     * your event handlers. If your payload contains record data, you will always receive it in an array container. Even for single rows.
     *
     * IMPORTANT: Don't expect this for ALL results. For non-record-like resultant data and/or result sets (i.e. NoSQL, other stuff), the data
     * may be placed in the payload verbatim.
     *
     * IMPORTANTER: The representation of the data will be placed back into the original location/position in the $payload from which it was "normalized".
     * This means that any client-side handlers will have to deal with the bogus determinations. Just be aware.
     *
     * To recap, below is a side-by-side comparison of record data as shown returned to the caller, and sent to an event handler.
     *
     *  REST API v1.0                           Event Representation
     *  -------------                           --------------------
     *  Single row...                           Add a 'record' key and make it look like a multi-row
     *
     *      array(                              array(
     *          'id' => 1,                          'record' => array(
     *      )                                           0 => array( 'id' => 1, ),
     *                                              ),
     *                                          ),
     *
     * Multi-row...                             Stays the same...
     *
     *      array(                              array(
     *          'record' => array(                  'record' =>  array(
     *              0 => array( 'id' => 1 ),            0 => array( 'id' => 1 ),
     *              1 => array( 'id' => 2 ),            1 => array( 'id' => 2 ),
     *              2 => array( 'id' => 3 ),            2 => array( 'id' => 3 ),
     *          ),                                  ),
     *      )                                   )
     *
     * XML multi-row                            The 'records' key is unwrapped, like regular multi-row
     *
     *  array(                                  array(
     *    'records' => array(                     'record' =>  array(
     *      'record' => array(                        0 => array( 'id' => 1 ),
     *        0 => array( 'id' => 1 ),                1 => array( 'id' => 2 ),
     *        1 => array( 'id' => 2 ),                2 => array( 'id' => 3 ),
     *        2 => array( 'id' => 3 ),            ),
     *      ),                                  )
     *    ),
     *  )
     *
     * @param string          $eventName         The event name
     * @param PlatformEvent   $event             The event
     * @param EventDispatcher $dispatcher        The dispatcher of the event
     * @param array           $additionalDetails Any additional data to put into the event structure
     * @param bool            $includeDspConfig  If true, the current DSP config is added to payload
     * @param bool            $returnJson        If true, the event will be returned as a JSON string, otherwise an array.
     *
     * @return array|string
     */
    public static function normalizeEvent( $eventName, PlatformEvent $event, $dispatcher, $additionalDetails = array(), $includeDspConfig = true, $returnJson = false )
    {
        static $_config = null;

        $_config = $includeDspConfig ? ( $_config ? : Config::getCurrentConfig() ) : false;

        $_event = array(
            'event'    => array(
                'id'               => $event->getEventId(),
                'name'             => $eventName,
                'trigger'          => $dispatcher->getPathInfo(),
                'stop_propagation' => $event->isPropagationStopped(),
                'dispatcher'       => array(
                    'id'   => spl_object_hash( $dispatcher ),
                    'type' => Inflector::neutralize( get_class( $dispatcher ) ),
                ),
            ),
            'request'  => array_merge(
                $event->toArray(),
                array(
                    'timestamp' => date( 'c', Option::server( 'REQUEST_TIME_FLOAT', Option::server( 'REQUEST_TIME', microtime( true ) ) ) ),
                    'path'      => $dispatcher->getPathInfo( true )
                )
            ),
            'platform' => array(
                'api'    => function ( $apiName, $resource, $resourceId, $parameters = array(), $payload = array() )
                {
                    throw new NotImplementedException( 'This feature is in development.' );
                },
                'config' => $_config,
            ),
            'details'  => Option::clean( $additionalDetails ),
            'payload'  => static::normalizeEventData( $event ),
        );

        return $returnJson ? json_encode( $_event, JSON_UNESCAPED_SLASHES ) : $_event;
    }

    /**
     * Sandboxes the event data into a normalized fashion
     *
     * @param PlatformEvent $event
     *
     * @return array
     */
    public static function normalizeEventData( PlatformEvent $event )
    {
        $_data = $event->getData();

        //  XML-wrapped
        if ( false !== ( $_records = Option::getDeep( $_data, 'records', 'record', false ) ) )
        {
            return array( 'record' => $_records );
        }

        //  Multi-row
        if ( false !== ( $_records = Option::get( $_data, 'record', false ) ) )
        {
            return array( 'record' => $_records );
        }

        //  Single row, or so we think...
        if ( is_array( $_data ) && !Pii::isEmpty( $_record = Option::get( $_data, 'record' ) ) && count( $_record ) >= 1 )
        {
            return array( 'record' => $_data );
        }

        //  Something completely different...
        return $_data;
    }

    /**
     * Determines and returns the data back into the location from whence it came
     *
     * @param PlatformEvent $event
     * @param array         $newData
     *
     * @return array|mixed
     */
    public static function denormalizeEventData( PlatformEvent $event, array $newData = array() )
    {
        $_currentData = $event->getData();

        //  XML-wrapped
        if ( false !== ( $_records = Option::getDeep( $_currentData, 'records', 'record', false ) ) )
        {
            //  Re-gift
            return array(
                'records' => array(
                    'record' => array(
                        $newData
                    )
                )
            );
        }

        //  Multi-row
        if ( false !== ( $_records = Option::get( $_currentData, 'record', false ) ) )
        {
            return array( 'record' => $newData );
        }

        //  Single row, or so we think...
        if ( is_array( $_currentData ) && !Pii::isEmpty( $_record = Option::get( $_currentData, 'record' ) ) && count( $_record ) >= 1 )
        {
            return array( 'record' => $newData );
        }

        //  A single row or something else...
        return $newData;
    }

    /**
     * Give a normalized event, put any changed data from the payload back into the event
     *
     * @param PlatformEvent $event
     * @param array         $data
     *
     * @return $this
     */
    public static function updateEventFromHandler( PlatformEvent &$event, array $data = array() )
    {
        //  Did propagation stop?
        if ( Option::getDeep( $data, 'event', 'stop_propagation', false ) )
        {
            $event->stopPropagation();
        }

        return $event->setData( static::denormalizeEventData( $event, empty( $data ) ? $event->getData() : $data ) );
    }

}