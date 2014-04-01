<?php
/**
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

/**
 * PersistentStoreLike
 */
interface PersistentStoreLike
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Open/initialize the store
     *
     * @return bool
     */
    public function open();

    /**
     * Closes the store if open
     *
     * @return bool
     */
    public function close();

    /**
     * Returns a parameter by name.
     *
     * @param string  $path    The key or path of key(s)
     * @param mixed   $default The default value if the parameter key does not exist
     * @param boolean $deep    If true, a path like foo[bar] will find deeper items
     *
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public function get( $path, $default = null, $deep = false );

    /**
     * Sets a parameter by name.
     *
     * @param string $key   The key
     * @param mixed  $value The value
     *
     * @api
     */
    public function set( $key, $value = null );

    /**
     * Returns true if the parameter is defined.
     *
     * @param string $key The key
     *
     * @return bool true if the parameter exists, false otherwise
     */
    public function has( $key );

    /**
     * Removes a parameter.
     *
     * @param string $key The key
     */
    public function remove( $key );

    /**
     * Flush any cached data to the store
     *
     * @return void
     */
    public function flush();

    /**
     * Empties the store of all keys
     *
     * @param bool $valuesOnly If true, the keys will remain, but their values will be nulled out. This is NOT recursive. Top level keys only.
     *
     * @return void
     */
    public function reset( $valuesOnly = false );
}