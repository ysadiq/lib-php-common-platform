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
namespace DreamFactory\Platform\Interfaces;

/**
 * Defines the various states in which a platform may exist
 */
interface PlatformStates
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var int
     */
    const ADMIN_REQUIRED = 0;
    /**
     * @var int
     */
    const DATA_REQUIRED = 1;
    /**
     * @var int
     */
    const INIT_REQUIRED = 2;
    /**
     * @var int
     */
    const READY = 3;
    /**
     * @var int
     */
    const SCHEMA_REQUIRED = 4;
    /**
     * @var int
     */
    const UPGRADE_REQUIRED = 5;
    /**
     * @var int
     */
    const WELCOME_REQUIRED = 6;
    /** @var int Indicates that the database is in place and the schema has been created */
    const DATABASE_READY = 7;
    /**
     * @type string The config key that holds this value
     */
    const STATE_KEY = 'platform.ready_state';
}
