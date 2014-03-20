<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
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
namespace DreamFactory\Platform\Events;

use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Seed;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * EventStream
 */
class EventStream extends Seed
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type double The maximum number of times to retry sending
     */
    const DEFAULT_MAX_RETRIES = 6;
    /**
     * @type int The time in ms for retries
     */
    const DEFAULT_RETRY_TIMEOUT = 2000;
    /**
     * @type string
     */
    const EVENT_STREAM_CONTENT_TYPE = 'text/event-stream';
    /**
     * @type string
     */
    const DEFAULT_EVENT_NAME = 'dsp.event';
    /**
     * @type string
     */
    const HEART_BEAT_EVENT_NAME = 'dsp.heart_beat';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var double The last known event ID
     */
    protected $_lastEventId;
    /**
     * @var Response The response object
     */
    protected $_response;
    /**
     * @var int The maximum number of times to retry sending
     */
    protected $_maxRetries = self::DEFAULT_MAX_RETRIES;
    /**
     * @var int The retry time in ms
     */
    protected $_retryTimeout = self::DEFAULT_RETRY_TIMEOUT;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array|object $options
     *
     * @throws InvalidArgumentException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     */
    public function __construct( $options = array() )
    {
        parent::__construct( $options );

        $this->_initializeStream();
    }

    /**
     * Dispatch an event to client(s)
     *
     * @param array           $event
     * @param string          $eventName
     * @param EventDispatcher $dispatcher
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \InvalidArgumentException
     */
    public function dispatch( $event, $eventName = null, $dispatcher = null )
    {
        $_serializedEvent = @json_encode( $event, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT );

        if ( false === $_serializedEvent || JSON_ERROR_NONE != json_last_error() )
        {
            throw new \InvalidArgumentException( 'The value of "$event" is invalid.' );
        }

        return $this->_sendMessage( $eventName ? : static::DEFAULT_EVENT_NAME, $_serializedEvent );
    }

    /**
     * @param string $eventName
     * @param string $data
     * @param bool   $extraLineFeed
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function _sendMessage( $eventName, $data, $extraLineFeed = true )
    {
        return $this->_response->setContent(
            $this->_buildPacket(
                array(
                    'event' => $eventName,
                    'data'  => $data,
                ),
                $extraLineFeed
            )
        )->send();
    }

    /**
     * Sends a heart-beat message to the client. Acts as a keep-alive as well.
     *
     * @return Response
     */
    protected function _sendHeartBeat()
    {
        return $this->_sendMessage( static::HEART_BEAT_EVENT_NAME, microtime( true ) );
    }

    /**
     * Sends a 2kb chunk of blanks in the form of a comment to fix some
     * problem with IE < 10 and Chrome < 13. Client should interpret as
     * a reset.
     */
    protected function _resetStream()
    {
        return $this->_response->setContent(
            $this->_buildPacket(
                array(
                    ''      => str_repeat( ' ', 2048 ),
                    'retry' => $this->_retryTimeout,
                ),
                false
            )
        )->send();
    }

    /**
     * Builds a nice little packet to send to the client
     *
     * @param array $message
     * @param bool  $extraLineFeed If true, an extra line feed will be added to the end of the string
     *
     * @return string
     */
    protected function _buildPacket( array $message, $extraLineFeed = true )
    {
        $_result = null;

        if ( !isset( $message['id'] ) )
        {
            $message['id'] = ++$this->_lastEventId;
        }

        foreach ( $message as $_key => $_line )
        {
            //  Format:   <key>:<space><data><line-feed>
            $_result .= $_key . ( ':' !== $_key ? ':' : null ) . ' ' . $_line . PHP_EOL;
        }

        //  Add an extra line feed for good luck!
        return $_result . ( $extraLineFeed ? PHP_EOL : null );
    }

    /**
     * Initializes the event stream
     *
     * @return Response
     * @throws RestException
     */
    protected function _initializeStream()
    {
        //  Get the last event ID if available
        $_contentType = null;
        $_response = new StreamedResponse();

        //  Add in the CORS headers from the main thread, if any
        $_response->headers->add( Pii::app()->getResponseObject()->headers->allPreserveCase() );

        //  Pull out the last event ID from the request
        $this->_lastEventId = (double)Pii::app()->getRequestObject()->get( 'last-event-id', 0 );

        //  Check the accept headers
        foreach ( Pii::app()->getRequestObject()->getAcceptableContentTypes() as $_contentType )
        {
            if ( false !== stripos( static::EVENT_STREAM_CONTENT_TYPE, $_contentType ) )
            {
                $_contentType = false;
                break;
            }
        }

        if ( false !== $_contentType )
        {
            throw new RestException( HttpResponse::NotAcceptable, 'Invalid content type.' );
        }

        //  Set our content type and cache settings.
        $_response->headers->set( 'Content-Type', static::EVENT_STREAM_CONTENT_TYPE );
        $_response->headers->set( 'Cache-Control', 'no-cache' );

        /** @noinspection PhpUnusedLocalVariableInspection */
        $_eventStream = $this;

        //  Streamed responses require a callback...
        $_response->setCallback(
            function ( $_eventStream )
            {
                ob_start();

                /** @noinspection PhpUndefinedMethodInspection */
                echo $_eventStream->getResponse()->getContent();

                ob_end_flush();
                flush();

                /** @noinspection PhpUndefinedMethodInspection */
                $_eventStream->getResponse()->setContent( null );
            }
        );

        //  Set up our response object
        $this->_response = $_response;

        return $this->_resetStream();
    }

    /**
     * @param int $maxRetries
     *
     * @return EventStream
     */
    public function setMaxRetries( $maxRetries )
    {
        $this->_maxRetries = $maxRetries;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxRetries()
    {
        return $this->_maxRetries;
    }

    /**
     * @param int $retryTimeout
     *
     * @return EventStream
     */
    public function setRetryTimeout( $retryTimeout )
    {
        $this->_retryTimeout = $retryTimeout;

        return $this;
    }

    /**
     * @return int
     */
    public function getRetryTimeout()
    {
        return $this->_retryTimeout;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * @param float $lastEventId
     *
     * @return EventStream
     */
    public function setLastEventId( $lastEventId )
    {
        $this->_lastEventId = $lastEventId;

        return $this;
    }

    /**
     * @return float
     */
    public function getLastEventId()
    {
        return $this->_lastEventId;
    }

}