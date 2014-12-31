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

use DreamFactory\Platform\Exceptions\NotImplementedException;
use Kisma\Core\Enums\SeedEnum;

/**
 * Various service requestor types as bitmask-able values
 */
class ServiceRequestorTypes extends SeedEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var int No service requestor type is allowed
     */
    const NONE = 0;
    /**
     * @var int Service is being called from a client through the API
     */
    const API = 1; // 0b0001
    /**
     * @var int Service is being called from the scripting environment
     */
    const SCRIPT = 2; // 0b0010

    /**
     * @var int
     */
    const __default = self::NONE;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var array A hash of level names
     */
    protected static $_strings = array(
        'none'   => self::NONE,
        'api'    => self::API,
        'script' => self::SCRIPT,
    );

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $requestorType
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toNumeric( $requestorType = 'none' )
    {
        if ( !is_string( $requestorType ) )
        {
            throw new \InvalidArgumentException( 'The requestor type "' . $requestorType . '" is not a string.' );
        }

        if ( !in_array( strtolower( $requestorType ), array_keys( static::$_strings ) ) )
        {
            throw new NotImplementedException( 'The requestor type "' . $requestorType . '" is not supported.' );
        }

        return static::defines( strtoupper( $requestorType ), true );
    }

    /**
     * @param int $numericLevel
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toString( $numericLevel = self::NONE )
    {
        if ( !is_numeric( $numericLevel ) )
        {
            throw new \InvalidArgumentException( 'The requestor type "' . $numericLevel . '" is not numeric.' );
        }

        if ( !in_array( $numericLevel, static::$_strings ) )
        {
            throw new NotImplementedException( 'The requestor type "' . $numericLevel . '" is not supported.' );
        }

        return static::nameOf( $numericLevel );
    }
}
