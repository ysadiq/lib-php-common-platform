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
namespace DreamFactory\Platform\Interfaces;

use Doctrine\Common\Cache\Cache;

/**
 * StoreLike
 * An object that can hold stuff
 */
interface StoreLike extends Cache
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Stores a value for $key
     *
     * @param string $key  The key to under which to store the data
     * @param mixed  $data The data to store
     * @param int    $ttl  The number of seconds for this value to live. Defaults to 0, meaning forever.
     *
     * @return bool True if the value was successfully stored
     */
    public function set( $key, $data, $ttl = null );

    /**
     * Gets an event from key "$key"
     *
     * @param string $key          The key to retrieve
     * @param mixed  $defaultValue The value to return if the $key is not found in the cache
     * @param bool   $remove       If true, remove the item after it has been retrieved
     *
     * @return mixed The value stored under $key
     */
    public function get( $key, $defaultValue = null, $remove = false );

    /**
     * Deletes all items from the store
     *
     * @return mixed
     */
    public function deleteAll();
}
