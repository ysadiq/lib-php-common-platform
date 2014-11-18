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
namespace DreamFactory\Platform\Enums;

/**
 * Constants that correspond with the keys in the app config
 */
class LocalStorageTypes extends LocalStoragePaths
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @type string Installation path of the system
     */
    const PLATFORM_ROOT = 'platform_root';
    /**
     * @type string
     */
    const STORAGE_ROOT = 'storage_root';
    /**
     * @type string
     */
    const STORAGE_PATH = 'storage_path';
    /**
     * @type string
     */
    const PRIVATE_STORAGE_PATH = 'private_storage_path';
    /**
     * @type string
     */
    const PRIVATE_PATH = 'private_path';
    /**
     * @type string
     */
    const LIBRARY_PATH = 'plugins_path';
    /**
     * @type string
     */
    const PLUGINS_PATH = 'plugins_path';
    /**
     * @type string
     */
    const APPLICATIONS_PATH = 'applications_path';
    /**
     * @type string
     */
    const SNAPSHOT_PATH = 'snapshot_path';
    /**
     * @type string
     */
    const SWAGGER_PATH = 'swagger_path';
    /**
     * @type string
     */
    const TEMPLATE_PATH = 'template_path';
	/**
	 * @type string The relative path to any user-specified configuration files
	 */
	const PRIVATE_CONFIG_PATH = 'private_config_path';
	/**
     * @type string The relative path to any user-specified configuration files
     */
    const LOCAL_CONFIG_PATH = 'local_config_path';
    /**
     * @type string The relative path to any script files
     */
    const SCRIPTS_PATH = 'scripts_path';
    /**
     * @type string The relative path to any user-specified script files
     */
    const USER_SCRIPTS_PATH = 'user_scripts_path';
}
