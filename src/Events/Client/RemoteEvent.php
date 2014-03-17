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
namespace DreamFactory\Platform\Events\Client;

use DreamFactory\Platform\Events\PlatformEvent;

/**
 * RemoteEvent
 */
class RemoteEvent extends PlatformEvent
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array
     */
    protected $_comments = array();
    /**
     * @var string
     */
    protected $_event;
    /**
     * @var int
     */
    protected $_retry;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $comment
     *
     * @return $this
     */
    public function addComment( $comment )
    {
        $this->_comments = array_merge(
            $this->_comments,
            $this->_parseData( $comment )
        );

        return $this;
    }

    /**
     * @param string|array $data
     *
     * @return $this
     */
    public function appendData( $data )
    {
        $this->_data = array_merge(
            $this->_data,
            is_array( $data ) ? $data : explode( PHP_EOL, $data )
        );

        return $this;
    }

    /**
     * @return string
     */
    public function dump()
    {
        return $this->_buildResponse();
    }

    /**
     * @return string
     */
    protected function _buildResponse()
    {
        $_response = trim(
            $this->_formatLines(
                array(
                    null    => $this->_comments,
                    'id'    => $this->_eventId,
                    'event' => $this->_event,
                    'retry' => $this->_retry,
                    'data'  => $this->_data,
                )
            )
        );

        if ( empty( $_response ) )
        {
            return null;
        }

        return $_response . PHP_EOL;

    }

    /**
     * @param string|array $key
     * @param string       $lines
     *
     * @return string
     */
    protected function _formatLines( $key, $lines = null )
    {
        $_lines = is_array( $key ) ? $key : array( $key => $lines );
        $_formatted = null;

        foreach ( $_lines as $_key => $_keyLine )
        {
            $_formatted .= $_key . ': ' . $_keyLine . PHP_EOL;
        }

        return $_formatted;
    }

    /**
     * @param string|array $data
     *
     * @return array
     */
    protected function _parseData( $data )
    {
        return is_array( $data ) ? $data : explode( PHP_EOL, $data );
    }

    /**
     * @param array $comments
     *
     * @return RemoteEvent
     */
    public function setComments( $comments )
    {
        $this->_comments = $comments;

        return $this;
    }

    /**
     * @return array
     */
    public function getComments()
    {
        return $this->_comments;
    }

    /**
     * @param mixed $data
     *
     * @return SeedEvent
     */
    public function setData( $data )
    {
        return parent::setData( $this->_parseData( $data ) );
    }

    /**
     * @param mixed $event
     *
     * @return RemoteEvent
     */
    public function setEvent( $event )
    {
        $this->_event = $event;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEvent()
    {
        return $this->_event;
    }

    /**
     * @param int $retry
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setRetry( $retry )
    {
        if ( !is_numeric( $retry ) )
        {
            throw new \InvalidArgumentException( 'Retry value must be numeric.' );
        }

        $this->_retry = $retry;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getRetry()
    {
        return $this->_retry;
    }
}