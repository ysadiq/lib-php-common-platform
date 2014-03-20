<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
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
namespace DreamFactory\Platform\Events\Stores;

use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\EventStoreLike;
use DreamFactory\Platform\Events\PlatformEvent;

class BaseEventStore implements EventStoreLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type int The default amount of time (in ms) that data items are allowed
     * to live in the store. 0, the default, causes data items to never expire.
     */
    const DEFAULT_EVENT_STORE_TTL = 0;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array The place where data is stored
     */
    protected $_defaultTtl = self::DEFAULT_EVENT_STORE_TTL;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Stores an event at key "$key"
     *
     * @param string $key  Identifier for this event
     * @param mixed  $data The event data
     * @param int    $ttl  The expiration TTL for this key
     *
     * @return mixed
     */
    public static function set( $key, $data, $ttl = null )
    {
        // TODO: Implement set() method.
    }

    /**
     * Gets an event from key "$key"
     *
     * @param string $key Identifier for this event
     * @param mixed  $defaultValue
     * @param bool   $unsetIfFound
     *
     * @return mixed
     */
    public static function get( $key, $defaultValue = null, $unsetIfFound = false )
    {
        // TODO: Implement get() method.
    }

    /**
     * Process data received
     *
     * @param PlatformEvent   $event
     * @param string          $eventName
     * @param EventDispatcher $dispatcher
     *
     * @return mixed
     */
    public function processEvent( $event, $eventName, $dispatcher )
    {
        // TODO: Implement processEvent() method.
    }
}