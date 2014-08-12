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
use Kisma\Core\Exceptions\NotImplementedException;

/**
 * Various API Documentation Format types
 */
class ApiDocFormatTypes extends SeedEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var int Swagger json format, default
     */
    const SWAGGER = 0;
    /**
     * @var int RAML, RESTful API modeling language
     */
    const RAML = 1;
    /**
     * @var int API Blueprint format
     */
    const API_BLUEPRINT = 2;
    /**
     * @var int Pipe-separated values
     */
    const IO_DOCS = 3;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var array A hash of level names against Monolog levels
     */
    protected static $_strings = array(
        'swagger'  => self::SWAGGER,
        'raml'  => self::RAML,
        'api_blueprint'  => self::API_BLUEPRINT,
        'io_docs' => self::IO_DOCS,
    );

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $formatType
     *
     * @throws \Kisma\Core\Exceptions\NotImplementedException
     * @throws \InvalidArgumentException
     * @return string
     */
    public static function toNumeric( $formatType = 'swagger' )
    {
        if ( !is_string( $formatType ) )
        {
            throw new \InvalidArgumentException( 'The format type "' . $formatType . '" is not a string.' );
        }

        if ( !in_array( strtolower( $formatType ), array_keys( static::$_strings ) ) )
        {
            throw new NotImplementedException( 'The format type "' . $formatType . '" is not supported.' );
        }

        return static::defines( strtoupper( $formatType ), true );
    }

    /**
     * @param int $numericLevel
     *
     * @throws \Kisma\Core\Exceptions\NotImplementedException
     * @throws \InvalidArgumentException
     * @return string
     */
    public static function toString( $numericLevel = self::SWAGGER )
    {
        if ( !is_numeric( $numericLevel ) )
        {
            throw new \InvalidArgumentException( 'The format type "' . $numericLevel . '" is not numeric.' );
        }

        if ( !in_array( $numericLevel, static::$_strings ) )
        {
            throw new NotImplementedException( 'The format type "' . $numericLevel . '" is not supported.' );
        }

        return static::nameOf( $numericLevel );
    }
}
