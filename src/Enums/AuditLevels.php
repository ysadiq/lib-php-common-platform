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

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * The "level" of an ELK message.
 *
 * Equal to the standard syslog levels
 */
class AuditLevels extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var int
     */
    const __default = self::ALERT;
    /**
     * @var int
     */
    const EMERGENCY = 0;
    /**
     * @var int
     */
    const ALERT = 1;
    /**
     * @var int
     */
    const CRITICAL = 2;
    /**
     * @var int
     */
    const ERROR = 3;
    /**
     * @var int
     */
    const WARNING = 4;
    /**
     * @var int
     */
    const NOTICE = 5;
    /**
     * @var int
     */
    const INFO = 6;
    /**
     * @var int
     */
    const DEBUG = 7;
}
