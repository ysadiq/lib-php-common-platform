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

/**
 * EventStreamProxy.php
 * A proxy for event requests
 */
class EventStreamProxy
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var PlatformEvent
     */
    protected $_event;
    /**
     * @var callable
     */
    protected $_source;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param RemoteEvent $event
     * @param callable    $source
     *
     * @throws \InvalidArgumentException
     */
    public function __construct( RemoteEvent $event, $source = null )
    {
        if ( $source && !is_callable( $source ) )
        {
            throw new \InvalidArgumentException( 'The value for $source must be callable.' );
        }

        $this->_event = $event;
        $this->_source = $source;
    }

    /**
     * @return PlatformEvent
     */
    public function getEvent()
    {
        return $this->_event;
    }

    /**
     * @return mixed
     */
    public function end()
    {
        if ( $this->_source && is_callable( $this->_source ) )
        {
            return call_user_func( $this->_source );
        }
    }

    /**
     * @param string $name
     * @param array  $args
     *
     * @throws \BadMethodCallException
     * @return RemoteEvent
     */
    public function __call( $name, $args )
    {
        if ( !method_exists( $this->_event, $name ) && !is_callable( $name ) )
        {
            throw new \BadMethodCallException( 'The method "' . get_class( $this->_event ) . '::' . $name . '" could not be found.' );
        }

        return call_user_func_array( is_callable( $name ) ? $name : array( $this->_event, $name ), $args );
    }
}
