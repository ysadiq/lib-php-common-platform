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
namespace DreamFactory\Platform\Events;

use DreamFactory\Platform\Events\Client\RemoteEvent;
use DreamFactory\Platform\Interfaces\StreamListenerLike;
use Kisma\Core\Seed;

/**
 * EventStream.php
 * Creates a stream suitable for pushing client events
 */
class EventStream extends Seed
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var StreamListenerLike
     */
    protected $_listener;
    /**
     * @var \SplQueue
     */
    protected $_outputBuffer;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param StreamListenerLike $listener
     */
    public function __construct( StreamListenerLike $listener = null )
    {
        $this->_outputBuffer = new \SplQueue();
        $this->_outputBuffer->setIteratorMode( \SplQueue::IT_MODE_DELETE );

        $this->_listener = $listener ? : new EchoListener();
    }

    /**
     * @param array $data
     *
     * @return EventStreamProxy
     */
    public function createProxy( $data = array() )
    {
        $_data = ( $data instanceof RemoteEvent ) ? $data->toArray() : $data;

        $this->_outputBuffer->enqueue(
            $_event = new RemoteEvent( $_data )
        );

        $_this = $this;

        return new static( $_event, function () use ( $_this )
        {
            return $_this;
        } );
    }

    /**
     * Flush the buffers
     */
    public function flush()
    {
        /** @var RemoteEvent $_event */
        foreach ( $this->_outputBuffer as $_event )
        {
            $this->_listener->processEvent( $_event );
        }
    }

    /**
     * @return StreamListenerLike
     */
    public function getListener()
    {
        return $this->_listener;
    }

}
