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

use DreamFactory\Platform\Events\Enums\EventSourceHeaders;
use DreamFactory\Yii\Utility\Pii;
use Igorw\EventSource\Stream;
use Kisma\Core\Seed;
use Kisma\Core\Utility\Option;
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.

/**
 * Chunnel
 * An event channel/tunnel for clients
 */
<<<<<<< HEAD
class Chunnel extends Seed
{
    //*************************************************************************
=======
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
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
    //	Members
    //*************************************************************************

    /**
<<<<<<< HEAD
     * @var BrainSocketServer[]
=======
     * @var Stream[]
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
     */
    protected static $_streams = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
<<<<<<< HEAD
     * @param string          $streamId
=======
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
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
     * @param string          $eventName
     * @param array           $data
     * @param EventDispatcher $dispatcher
     *
     * @return bool
     */
    public static function send( $streamId, $eventName, array $data = array(), $dispatcher = null )
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
    {
        if ( !static::isValidStreamId( $streamId ) )
        {
            return false;
        }

<<<<<<< HEAD
        $_data = json_encode(
=======
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
            array_merge(
                Option::clean( $data ),
                static::_streamStamp( $streamId, false ),
                array( 'type' => $eventName )
            ),
            JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES
        );

        /** @noinspection PhpUndefinedMethodInspection */

        return static::$_streams[$streamId]
            ->event()
<<<<<<< HEAD
            ->setEvent( $eventName )
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
<<<<<<< HEAD
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
=======
=======
            ->setEvent( 'dsp.event' )
>>>>>>> event stream testing
>>>>>>> event stream testing
            ->setData( $_data )
            ->end()
            ->flush();
    }

    /**
     * Create and return a stream
     *
<<<<<<< HEAD
     * @param string $id
=======
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
        $_response = clone ( $_response = Pii::responseObject() );
        $_response->headers->add( EventSourceHeaders::all() );
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
        $_response->sendHeaders();

        //  Keep PHP happy, never time out
        set_time_limit( 0 );

        //  We all scream NEW STREAM!
<<<<<<< HEAD
=======
<<<<<<< HEAD
        $_stream =
            static::isValidStreamId( $streamId )
                ? static::$_streams[$streamId]
                : static::$_streams[$streamId] = new Stream();

        return $_stream;
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
     * Handles the output stream to the client
=======
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
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
>>>>>>> EventStream resource class added. Swagger doc created for event stream. New event stream events. New "Chunnel" class to coordinate stream communications.
}
