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

use DreamFactory\Platform\Interfaces\StreamListenerLike;

/**
 * EventStoreLike
 * Something that acts like an event store
 */
interface EventStoreLike extends StreamListenerLike
{
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
    public static function set( $key, $data, $ttl = null );

    /**
     * Gets an event from key "$key"
     *
     * @param string $key Identifier for this event
     * @param mixed  $defaultValue
     * @param bool   $unsetIfFound
     *
     * @return mixed
     */
    public static function get( $key, $defaultValue = null, $unsetIfFound = false );
}
