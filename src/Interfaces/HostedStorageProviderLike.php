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
 * Something that can provide hosted storage
 *
 * @package DreamFactory\Platform\Utility
 */
interface HostedStorageProviderLike
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Create a unique storage ID for the given host name
     *
     * @param string $instanceName The name of the hosted instance
     *
     * @return string
     */
    public static function makeStorageId( $instanceName );

    /**
     * Returns the zone name and absolute path of its storage
     *
     * @param string $zone Pass a string to force a specific zone
     *
     * @return array Returns $zone and $zoneInfo in an array: array( $zone, $zonePath )
     */
    public static function getZoneInfo( $zone = null );

    /**
     * Returns all the storage paths for a storage ID
     *
     * @param string $instanceName The name of the instance for which to return storage info
     * @param bool   $autoCreate   Create mapped path if true
     *
     * @return array An array containing zone info and absolute storage and index paths for the given storage ID.
     *               array( $zone, $zonePath, $storagePath, $indexPath )
     */
    public static function getStorageInfo( $instanceName, $autoCreate = true );
}
