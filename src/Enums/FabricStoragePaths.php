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
namespace DreamFactory\Platform\Enums;

use Kisma\Core\Enums\SeedEnum;

/**
 * Standard fabric storage paths & keys
 */
class FabricStoragePaths extends SeedEnum
{
    //*************************************************************************
    //* Path Construction Constants
    //*************************************************************************

    /**
     * @type string Absolute path where storage is mounted
     */
    const STORAGE_MOUNT_POINT = '/data';
    /**
     * @type string Relative path under storage mount
     */
    const STORAGE_PATH = '/storage';
    /**
     * @type string Relative path under storage base
     */
    const PRIVATE_STORAGE_PATH = '/.private';
    /**
     * @type string Name of the applications directory relative to storage base
     */
    const APPLICATIONS_PATH = '/applications';
    /**
     * @type string Name of the plugins directory relative to storage base
     */
    const PLUGINS_PATH = '/plugins';
    /**
     * @type string Name of the Swagger directory relative to storage base
     */
    const SWAGGER_PATH = '/swagger';
    /**
     * @type string Name of the config directory relative to storage and private base
     */
    const CONFIG_PATH = '/config';
    /**
     * @type string Name of the scripts directory relative to private base
     */
    const SCRIPTS_PATH = '/scripts';
    /**
     * @type string Name of the user scripts directory relative to private base
     */
    const USER_SCRIPTS_PATH = '/scripts.user';
    /**
     * @type string Name of the snapshot storage directory relative to private base
     */
    const SNAPSHOT_PATH = '/snapshots';

    //******************************************************************************
    //* Configuration Constants
    //******************************************************************************

    /**
     * @type string Our cache key
     */
    const CACHE_KEY = 'platform.hosted_storage';
    /**
     * @type string The hash algorithm to use for directory-creep
     */
    const DATA_STORAGE_HASH = 'sha256';
    /**
     * @type string Zone name to use when testing. Set to null in production
     */
    const DEBUG_ZONE_NAME = null;
    /**
     * @type string Zone url to use when testing. Set to null in production
     */
    const DEBUG_ZONE_URL = null;
}
