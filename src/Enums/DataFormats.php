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

use Kisma\Core\Enums\SeedEnum;
use Symfony\Component\HttpFoundation\Request;

/**
 * DataFormats
 * THe data formats of which we are aware
 */
class DataFormats extends SeedEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var int native/original/unadulterated
     */
    const NATIVE = 0;
    /**
     * @var int
     */
    const JSON = 1;
    /**
     * @var int
     */
    const XML = 2;
    /**
     * @var int
     */
    const HTTP = 3;
    /**
     * @var int Comma-separated values
     */
    const CSV = 4;
    /**
     * @var int Pipe-separated values
     */
    const PSV = 5;
    /**
     * @var int Tab-separated values
     */
    const TSV = 6;
    /**
     * @var int A regular ol' array
     */
    const PHP_ARRAY = 7;
    /**
     * @var int A regular ol' stdClass
     */
    const PHP_OBJECT = 8;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var array A hash of level names against Monolog levels
     */
    protected static $_strings = array(
        'tsv'        => self::TSV,
        'psv'        => self::PSV,
        'csv'        => self::CSV,
        'http'       => self::HTTP,
        'xml'        => self::XML,
        'json'       => self::JSON,
        'native'     => self::NATIVE,
        'php'        => self::PHP_ARRAY,
        'array'      => self::PHP_ARRAY,
        'php_array'  => self::PHP_ARRAY,
        'php_object' => self::PHP_OBJECT,
        'object'     => self::PHP_OBJECT,
    );

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $stringLevel
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function toNumeric( $stringLevel )
    {
        if ( !is_string( $stringLevel ) )
        {
            throw new \InvalidArgumentException( 'The data format "' . $stringLevel . '" is a string.' );
        }

        if ( !in_array( strtolower( $stringLevel ), array_keys( static::$_strings ) ) )
        {
            throw new \InvalidArgumentException( 'The data format "' . $stringLevel . '" is invalid.' );
        }

        return static::defines( strtoupper( $stringLevel ), true );
    }

    /**
     * @param int $numericLevel
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function toString( $numericLevel )
    {
        if ( !is_numeric( $numericLevel ) )
        {
            throw new \InvalidArgumentException( 'The data format "' . $numericLevel . '" is not numeric.' );
        }

        if ( !in_array( $numericLevel, static::$_strings ) )
        {
            throw new \InvalidArgumentException( 'The data format "' . $numericLevel . '" is invalid.' );
        }

        return static::nameOf( $numericLevel, true, false );
    }
}
