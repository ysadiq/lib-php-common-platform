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

use DreamFactory\Platform\Enums\FabricStoragePaths;

/**
 * A provider of storage to a hosted cluster
 */
interface StorageProviderLike
{
    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Given a host name and an optional mount point, derive the storage keys
     * and directory structure of the storage space for $hostname
     *
     * @param string $hostname   The storage owner's host name
     * @param string $mountPoint Optional storage mount point
     *
     * @return string
     */
    public function initialize( $hostname, $mountPoint = FabricStoragePaths::STORAGE_MOUNT_POINT );

    /**
     * Returns the owner's storage id
     *
     * @return string
     */
    public function getStorageId();

    /**
     * Returns the owner's storage space structure
     *
     * @return array An associative array of the paths that define the layout of the storage directory
     */
    public function getStorageStructure();

    /**
     * Returns the absolute storage path, sans trailing slash.
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string The instance's absolute storage path, sans trailing slash
     */
    public function getStoragePath( $append = null, $createIfMissing = true, $includesFile = false );

    /**
     * Returns the absolute private storage path, sans trailing slash.
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string The instance's absolute private path, sans trailing slash
     */
    public function getPrivatePath( $append = null, $createIfMissing = true, $includesFile = false );

    /**
     * @param string $legacyKey The instance's prior key, if one. Will be used as a default if
     *                          there is a problem deriving the storage id.
     *
     * @return bool|string The instance's storage key
     */
    public function getStorageKey( $legacyKey = null );

    /**
     * @param string $legacyKey The instance's prior key, if one. Will be used as a default if
     *                          there is a problem deriving the storage id.
     *
     * @return bool|string The instance's private storage key
     */
    public function getPrivateStorageKey( $legacyKey = null );
}
