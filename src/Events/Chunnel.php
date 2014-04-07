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
namespace DreamFactory\Platform\Events;

use DreamFactory\Platform\Events\Interfaces\StreamDispatcherLike;
use DreamFactory\Yii\Utility\Pii;
use Igorw\EventSource\Stream;
use Kisma\Core\Seed;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chunnel
 * An event channel/tunnel for clients
 */
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
    //	Members
    //*************************************************************************

    /**
     * @var Stream[]
     */
    protected static $_streams = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
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
    public static function send( $streamId, $eventName, array $data = array() )
    {
        if ( !static::isValidStreamId( $streamId ) )
        {
            return false;
        }

        $_data = static::_formatMessageForOutput(
            $streamId,
            true,
            array_merge(
                array(
                    'type' => $eventName
                ),
                $data
            )
        );

        return static::$_streams[$streamId]
            ->event()
            ->setEvent( static::CHUNNEL_EVENT_NAME )
            ->setData( $_data )
            ->end()
            ->flush();
    }

    /**
     * Create and return a stream
     *
     * @param string $streamId
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
        $_response->headers->add( Stream::getHeaders() );
        $_response->sendHeaders();

        //  Keep PHP happy, never time out
        set_time_limit( 0 );

        //  We all scream NEW STREAM!
        $_stream =
            static::isValidStreamId( $streamId )
                ? static::$_streams[$streamId]
                : static::$_streams[$streamId] = new Stream();

        return $_stream;
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
     * Creates a common stamp for all streamed events
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

}
