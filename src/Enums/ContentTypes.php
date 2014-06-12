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
use Symfony\Component\HttpFoundation\Request;

/**
 * Various HTTP content types
 */
class ContentTypes extends SeedEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var int
     */
    const JSON = 0;
    /**
     * @var int
     */
    const XML = 1;
    /**
     * @var int Comma-separated values
     */
    const CSV = 3;
    /**
     * @var int Pipe-separated values
     */
    const PSV = 4;
    /**
     * @var int Tab-separated values
     */
    const TSV = 5;
    /**
     * @var int Straight-up HTML or XHTML
     */
    const HTML = 6;
    /**
     * @var int Plain text
     */
    const TEXT = 7;
    /**
     * @var int Javascript text
     */
    const JAVASCRIPT = 8;
    /**
     * @var int CSS styles
     */
    const CSS = 9;
    /**
     * @var int RDF
     */
    const RDF = 10;
    /**
     * @var int RDF
     */
    const PDF = 11;
    /**
     * @var int ATOM
     */
    const ATOM = 12;
    /**
     * @var int RSS
     */
    const RSS = 13;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var array A hash of level names against Monolog levels
     */
    protected static $_strings = array(
        'tsv'  => self::TSV,
        'psv'  => self::PSV,
        'csv'  => self::CSV,
        'html' => self::HTML,
        'txt'  => self::TEXT,
        'xml'  => self::XML,
        'json' => self::JSON,
        'atom' => self::ATOM,
        'rss'  => self::RSS,
        'rdf'  => self::RDF,
        'pdf'  => self::PDF,
        'js'   => self::JAVASCRIPT,
    );

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $contentType
     *
     * @throws \Kisma\Core\Exceptions\NotImplementedException
     * @return string
     */
    public static function toNumeric( $contentType )
    {
        if ( !is_string( $contentType ) )
        {
            throw new \InvalidArgumentException( 'The content type "' . $contentType . '" is not a string.' );
        }

        if ( !in_array( strtolower( $contentType ), array_keys( static::$_strings ) ) )
        {
            throw new NotImplementedException( 'The content type "' . $contentType . '" is not supported.' );
        }

        return static::defines( strtoupper( $contentType ), true );
    }

    /**
     * @param int $numericLevel
     *
     * @throws \Kisma\Core\Exceptions\NotImplementedException
     * @return string
     */
    public static function toString( $numericLevel )
    {
        if ( !is_numeric( $numericLevel ) )
        {
            throw new \InvalidArgumentException( 'The content type "' . $numericLevel . '" is not numeric.' );
        }

        if ( !in_array( $numericLevel, static::$_strings ) )
        {
            throw new NotImplementedException( 'The content type "' . $numericLevel . '" is not supported.' );
        }

        return static::nameOf( $numericLevel );
    }

    /**
     * Translates/converts an inbound HTTP content-type's MIME type to a class enum value
     *
     * @param string $mimeType
     *
     * @throws \Kisma\Core\Exceptions\NotImplementedException
     * @return int
     */
    public static function fromMimeType( $mimeType )
    {
        try
        {
            return static::toNumeric( $mimeType );
        }
        catch ( NotImplementedException $_ex )
        {
            //  Defaults to HTML when not supported
            return static::toNumeric( static::HTML );
        }
    }
}
