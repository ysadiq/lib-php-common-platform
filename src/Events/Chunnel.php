<?php
<<<<<<< HEAD
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
>>>>>>> event stream testing
>>>>>>> event stream testing
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
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
<<<<<<< HEAD
>>>>>>> event stream testing
namespace DreamFactory\Platform\Events;

use DreamFactory\Platform\Events\Enums\EventSourceHeaders;
use DreamFactory\Yii\Utility\Pii;
use Igorw\EventSource\Stream;
use Kisma\Core\Seed;
<<<<<<< HEAD
use Kisma\Core\Utility\Option;
=======
use Symfony\Component\HttpFoundation\Response;
=======
=======
>>>>>>> event stream testing
namespace DreamFactory\Platform\Events;

use DreamFactory\Platform\Events\Interfaces\StreamDispatcherLike;
use DreamFactory\Yii\Utility\Pii;
use Igorw\EventSource\Stream;
use Kisma\Core\Seed;
<<<<<<< HEAD
use Kisma\Core\Utility\Option;
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
=======
use Symfony\Component\HttpFoundation\Response;
>>>>>>> Eventstream testing
>>>>>>> Eventstream testing

/**
 * Chunnel
 * An event channel/tunnel for clients
 */
<<<<<<< HEAD
<<<<<<< HEAD
class Chunnel extends Seed
{
    //*************************************************************************
=======
=======
>>>>>>> Eventstream testing
<<<<<<< HEAD
class Chunnel extends Seed implements StreamDispatcherLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string The name of the encapsulating event sent down the chute
     */
    const CHUNNEL_EVENT_NAME = 'dsp.event';

    //*************************************************************************
=======
class Chunnel extends Seed
{
    //*************************************************************************
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
=======
class Chunnel extends Seed implements StreamDispatcherLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string The name of the encapsulating event sent down the chute
     */
    const CHUNNEL_EVENT_NAME = 'dsp.event';

    //*************************************************************************
>>>>>>> Eventstream testing
>>>>>>> Eventstream testing
    //	Members
    //*************************************************************************

    /**
<<<<<<< HEAD
<<<<<<< HEAD
     * @var BrainSocketServer[]
=======
     * @var Stream[]
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
     * @var BrainSocketServer[]
>>>>>>> Composer update
     */
    protected static $_streams = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
<<<<<<< HEAD
<<<<<<< HEAD
     * @param string          $streamId
=======
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
<<<<<<< HEAD
>>>>>>> Eventstream testing
     * @param string          $eventName
     * @param array           $data
     * @param EventDispatcher $dispatcher
     *
     * @return bool
     */
<<<<<<< HEAD
    public static function send( $streamId, $eventName, array $data = array(), $dispatcher = null )
=======
    public static function send( $streamId, $eventName, array $data = array() )
=======
     * @param string          $streamId
=======
>>>>>>> Eventstream testing
     * @param string          $eventName
     * @param array           $eventData
     * @param EventDispatcher $dispatcher
     *
     * @return int The number of streams to which the event was dispatched
     */
    public static function dispatchEventToStream( $eventName, array $eventData = array(), $dispatcher = null )
    {
        $_dispatched = 0;

        foreach ( static::$_streams as $_streamId => $_stream )
        {
            static::send( $_streamId, $eventName, $eventData );
            $_dispatched += 1;
        }

        return $_dispatched;
    }

    /**
     * @param string $streamId
     * @param string $eventName
     * @param array  $data
     *
     * @return bool
     */
<<<<<<< HEAD
    public static function send( $streamId, $eventName, array $data = array(), $dispatcher = null )
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
=======
    public static function send( $streamId, $eventName, array $data = array() )
>>>>>>> Eventstream testing
>>>>>>> Eventstream testing
    {
        if ( !static::isValidStreamId( $streamId ) )
        {
            return false;
        }

<<<<<<< HEAD
<<<<<<< HEAD
        $_data = json_encode(
=======
=======
>>>>>>> Eventstream testing
<<<<<<< HEAD
        $_data = static::_formatMessageForOutput(
            $streamId,
            true,
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
            array_merge(
                Option::clean( $data ),
                static::_streamStamp( $streamId, false )
            ),
            JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES
        );

        /** @noinspection PhpUndefinedMethodInspection */

        return static::$_streams[$streamId]
            ->event()
<<<<<<< HEAD
            ->setEvent( $eventName )
=======
            ->setEvent( static::CHUNNEL_EVENT_NAME )
=======
        $_data = json_encode(
=======
        $_data = static::_formatMessageForOutput(
            $streamId,
            true,
>>>>>>> Eventstream testing
            array_merge(
                array(
                    'type' => $eventName
                ),
                $data
            )
        );

        return static::$_streams[$streamId]
            ->event()
<<<<<<< HEAD
<<<<<<< HEAD
            ->setEvent( $eventName )
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
=======
            ->setEvent( 'dsp.event' )
>>>>>>> event stream testing
<<<<<<< HEAD
>>>>>>> event stream testing
=======
=======
            ->setEvent( static::CHUNNEL_EVENT_NAME )
>>>>>>> Eventstream testing
>>>>>>> Eventstream testing
            ->setData( $_data )
            ->end()
            ->flush();
    }

    /**
     * Create and return a stream
     *
<<<<<<< HEAD
<<<<<<< HEAD
     * @param string $id
=======
=======
>>>>>>> Eventstream testing
<<<<<<< HEAD
     * @param string $streamId
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
     *
     * @throws \InvalidArgumentException
     * @return Stream
     */
    public static function create( $id )
    {
        if ( empty( $id ) )
        {
            throw new \InvalidArgumentException( 'You must give this process an ID. $id cannot be blank.' );
        }

        //  Send the EventSource headers
<<<<<<< HEAD
        $_response = clone ( $_response = Pii::responseObject() );
        $_response->headers->add( EventSourceHeaders::all() );
=======
        /** @var Response $_response */
        $_response = clone ( $_response = Pii::response() );
        $_response->headers->add( Stream::getHeaders() );
=======
     * @param string $id
=======
     * @param string $streamId
>>>>>>> Eventstream testing
     *
     * @throws \CException
     * @throws \InvalidArgumentException
     * @return Stream
     */
    public static function create( $streamId )
    {
        if ( empty( $streamId ) )
        {
            throw new \InvalidArgumentException( 'You must give this process an ID. $streamId cannot be blank.' );
        }

        //  Send the EventSource headers
        /** @var Response $_response */
        $_response = clone ( $_response = Pii::response() );
<<<<<<< HEAD
        $_response->headers->add( EventSourceHeaders::all() );
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
=======
        $_response->headers->add( Stream::getHeaders() );
>>>>>>> Merge junk
>>>>>>> Merge junk
        $_response->sendHeaders();

        //  Keep PHP happy, never time out
        set_time_limit( 0 );

        //  We all scream NEW STREAM!
<<<<<<< HEAD
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
>>>>>>> Eventstream testing
>>>>>>> Eventstream testing
        $_stream =
            static::isValidStreamId( $streamId )
                ? static::$_streams[$streamId]
                : static::$_streams[$streamId] = new Stream();

        return $_stream;
<<<<<<< HEAD
=======
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
        return
            static::isValidStreamId( $id )
                ? static::$_streams[$id]
                : static::$_streams[$id] = new Stream();
<<<<<<< HEAD
=======
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
>>>>>>> Eventstream testing
    }

    /**
     * @param string $streamId
     *
     * @return bool
     */
    public static function isValidStreamId( $streamId )
    {
        return array_key_exists( $streamId, static::$_streams );
    }

    /**
     * @return bool
     */
    protected static function _startHeartBeat()
    {
        return false;
    }

    /**
<<<<<<< HEAD
<<<<<<< HEAD
     * Handles the output stream to the client
=======
=======
>>>>>>> Eventstream testing
<<<<<<< HEAD
     * Creates a common stamp for all streamed events
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
     *
     * @param string $streamData
     *
     * @throws \InvalidArgumentException
     */
    protected static function _streamHandler( $streamData )
    {
        echo $streamData;
        ob_flush();
        flush();
    }

    /**
     * Creates a common stamp for all streamed events
     *
     * @param string $id
     * @param bool   $asJson
     * @param int    $jsonOptions
     *
     * @return array|string
     */
    protected static function _streamStamp( $id, $asJson = true, $jsonOptions = 0 )
    {
        $_stamp = array( 'stream_id' => $id, 'timestamp' => microtime( true ) );

        return $asJson ? json_encode( $_stamp, $jsonOptions | ( JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES ) ) : $_stamp;
    }
<<<<<<< HEAD
=======

=======
     * Handles the output stream to the client
=======
     * Creates a common stamp for all streamed events
>>>>>>> Eventstream testing
     *
     * @param string $streamId
     * @param bool   $success
     * @param bool   $asJson
     * @param int    $jsonOptions
     *
     * @return array|string
     */
    protected static function _streamStamp( $streamId, $success = true, $asJson = true, $jsonOptions = 0 )
    {
        $_request = array(
            'stream_id' => $streamId,
            'timestamp' => $_time = date( 'c', time() ),
            'signature' => base64_encode( hash_hmac( 'sha256', $streamId, $_time, true ) ),
        );

        $_stamp = array( 'request' => $_request );

        return $asJson ? json_encode( $_stamp, $jsonOptions | ( JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES ) ) : $_stamp;
    }

    /**
     * @param string $streamId
     * @param bool   $success
     * @param array  $data Additional detail data to send to client
     * @param int    $jsonOptions
     *
     * @return string
     */
    protected static function _formatMessageForOutput( $streamId, $success = true, array $data = array(), $jsonOptions = 0 )
    {
        $_response = static::_streamStamp( $streamId, $success, false );
        $_response['success'] = $success;

        if ( !empty( $data ) )
        {
            $_response['details'] = $data;
        }

        return json_encode( $_response, $jsonOptions | ( JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES ) );
    }
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
=======

>>>>>>> Eventstream testing
>>>>>>> Eventstream testing
}
