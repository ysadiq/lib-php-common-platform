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

use DreamFactory\Library\Utility\Enums\Verbs;
use Kisma\Core\Enums\SeedEnum;

/**
 * Various CORS constants
 */
class CorsDefaults extends SeedEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string Indicates all is allowed
     */
    const CORS_STAR = '*';
    /**
     * @var string The HTTP Option method
     */
    const CORS_OPTION_METHOD = Verbs::OPTIONS;
    /**
     * @var string The allowed HTTP methods
     */
    const CORS_DEFAULT_ALLOWED_METHODS = 'GET,POST,PUT,DELETE,PATCH,MERGE,COPY,OPTIONS';
    /**
     * @var string The allowed HTTP headers. Tunnelling verb overrides: X-HTTP-Method (Microsoft), X-HTTP-Method-Override (Google/GData),
     *      X-METHOD-OVERRIDE (IBM)
     */
    const CORS_DEFAULT_ALLOWED_HEADERS = 'Content-Type,X-Requested-With,X-DreamFactory-Application-Name,X-Application-Name,X-DreamFactory-Session-Token,X-HTTP-Method,X-HTTP-Method-Override,X-METHOD-OVERRIDE';
    /**
     * @var int The default number of seconds to allow this to be cached. Default is 15 minutes.
     */
    const CORS_DEFAULT_MAX_AGE = 900;
    /**
     * @var string The private CORS configuration file
     */
    const CORS_DEFAULT_CONFIG_FILE = 'cors.config.json';
    /**
     * @var string The session key for CORS configs
     */
    const CORS_WHITELIST_KEY = 'cors.config';
    /**
     * @var string The default DSP resource namespace
     */
    const DEFAULT_SERVICE_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Services';
    /**
     * @var string The default DSP resource namespace
     */
    const DEFAULT_RESOURCE_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Resources';
    /**
     * @var string The default DSP model namespace
     */
    const DEFAULT_MODEL_NAMESPACE_ROOT = 'DreamFactory\\Platform\\Yii\\Models';
    /**
     * @var string The pattern of for local configuration files
     */
    const DEFAULT_LOCAL_CONFIG_PATTERN = '*.config.php';
    /**
     * @var string The default path (sub-path) of installed plug-ins
     */
    const DEFAULT_PLUGINS_PATH = '/storage/plugins';
    /**
     * @var string The origin received when running from a locally loaded file
     */
    const FILE_ORIGIN = 'file://';
}
