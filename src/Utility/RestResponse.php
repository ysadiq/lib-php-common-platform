<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Utility;

use DreamFactory\Common\Enums\OutputFormats;
use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Oasys\Exceptions\RedirectRequiredException;
use DreamFactory\Platform\Enums\DataFormats;
use DreamFactory\Platform\Enums\ResponseFormats;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Option;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * RestResponse
 * REST Response Utilities
 */
class RestResponse extends HttpResponse
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var string The default character set
     */
    const DEFAULT_CHARSET = 'utf-8';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string The outbound character set
     */
    protected static $_charset = self::DEFAULT_CHARSET;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $requested Optional requested set format, FALSE for raw formatting
     * @param string $internal  Reference returned internal formatting (datatables, jtables, etc.)
     *
     * @return string output format, outer envelope
     */
    public static function detectResponseFormat( $requested, &$internal )
    {
        if ( false === $requested )
        {
            return $requested;
        }

        $internal = ResponseFormats::RAW;
        $_format = $requested;

        /** @var Request $_request */
        /** @noinspection PhpUndefinedMethodInspection */
        $_request = Pii::requestObject();

        if ( empty( $_format ) )
        {
            $_format = $_request->get( 'format' );

            if ( empty( $_format ) )
            {
                $_format = @current( $_request->getAcceptableContentTypes() );
            }
        }

        $_format = trim( strtolower( $_format ) );

        switch ( $_format )
        {
            case 'json':
            case 'application/json':
            case DataFormats::JSON:
                $_format = DataFormats::JSON;
                break;

            case 'xml':
            case 'application/xml':
            case 'text/xml':
            case DataFormats::XML:
                $_format = DataFormats::XML;
                break;

            case 'csv':
            case 'text/csv':
            case DataFormats::CSV:
                $_format = DataFormats::CSV;
                break;

            case 'psv':
            case 'text/psv':
            case DataFormats::PSV:
                $_format = DataFormats::PSV;
                break;

            case 'tsv':
            case 'text/tsv':
            case DataFormats::TSV:
                $_format = DataFormats::TSV;
                break;

            default:
                if ( ResponseFormats::contains( $_format ) )
                {
                    //	Set the response format here and in the store
                    ResourceStore::setResponseFormat( $internal = $_format );
                }

                //	Set envelope to JSON
                $_format = DataFormats::JSON;
                break;
        }

        return $_format;
    }

    /**
     * @param int $code
     *
     * @return string
     */
    public static function getHttpStatusCodeTitle( $code )
    {
        return implode( ' ', preg_split( '/(?=[A-Z])/', static::nameOf( $code ), -1, PREG_SPLIT_NO_EMPTY ) );
    }

    /**
     * @param int $code
     *
     * @return int
     */
    public static function getHttpStatusCode( $code )
    {
        //	If not valid code, return 500 - server error
        return !static::contains( $code ) ? static::InternalServerError : $code;
    }

    /**
     * @param \Exception $exception
     * @param string     $desired_format
     * @param string     $as_file
     * @param bool       $exitAfterSend
     *
     * @return bool
     */
    public static function sendErrors( $exception, $desired_format = DataFormats::JSON, $as_file = null, $exitAfterSend = true )
    {
        //  Default to internal error
        $_errorInfo = array();
        $_status = HttpResponse::InternalServerError;

        if ( $exception instanceof RestException )
        {
            $_status = $exception->getStatusCode();
            $_errorInfo['context'] = $exception->getContext();
        }
        elseif ( $exception instanceOf \CHttpException )
        {
            $_status = $exception->statusCode;
        }
        elseif ( $exception instanceof RedirectRequiredException )
        {
            $_errorInfo['location'] = $exception->getRedirectUri();
        }
        elseif ( 0 == ( $_status = $exception->getCode() ) )
        {
            $_status = HttpResponse::InternalServerError;
        }

        $_errorInfo = array(
            'message' => htmlentities( $exception->getMessage() ),
            'code'    => $_status,
        );

        $_result = array(
            'error' => array( $_errorInfo )
        );

        $_result = DataFormat::reformatData( $_result, null, $desired_format );

        return static::sendResults( $_result, $_status, $desired_format, $as_file, $exitAfterSend );
    }

    /**
     * @param mixed  $result
     * @param int    $code
     * @param string $format
     * @param string $as_file
     * @param bool   $exitAfterSend
     *
     * @return Response|bool
     */
    public static function sendResults( $result, $code = RestResponse::Ok, $format = 'json', $as_file = null, $exitAfterSend = true )
    {
        $_response = Pii::responseObject();
        $_response->setStatusCode( $code );
        $_response->headers->set( 'P3P', 'CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"' );

        $_contentType = 'application/json';
        $_content = null;

        //	Some REST services may handle the response, they just return null
        if ( is_null( $result ) )
        {
            return $exitAfterSend ? $_response : Pii::end();
        }

        switch ( $format )
        {
            case OutputFormats::JSON:
            case 'json':
                if ( !is_string( $result ) )
                {
                    /** @var JsonResponse $_response */
                    $_response = new JsonResponse( $result, $code );
                    $_response->setCallback( Option::get( $_GET, 'callback' ) );

                    if ( 'cli' !== PHP_SAPI )
                    {
                        Pii::responseObject( $_response );
                    }

                    $_content = false;
                }
                break;

            case OutputFormats::XML:
            case 'xml':
                $_contentType = 'application/xml';
                $_content = '<?xml version="1.0" ?><dfapi>' . $result . '</dfapi>';
                break;

            case OutputFormats::CSV:
                $_contentType = 'text/csv; application/csv;';
                break;

            case OutputFormats::TSV:
                $_contentType = 'text/tsv; application/tsv;';
                break;

            case OutputFormats::PSV:
                $_contentType = 'text/psv; application/psv;';
                break;

            default:
                $_contentType = 'application/octet-stream';
                break;
        }

        if ( !empty( $_contentType ) )
        {
            $_response->headers->set( 'Content-Type', $_contentType );
        }

        if ( !empty( $as_file ) )
        {
            $_response->headers->makeDisposition( ResponseHeaderBag::DISPOSITION_ATTACHMENT, $as_file );
        }

        if ( ( !$_response instanceof JsonResponse ) )
        {
            $_response->setContent( $_content ? : $result );
        }

        //	Send it out!
        $_response->setCharset( static::$_charset )->send();

        if ( $exitAfterSend )
        {
            return Pii::end();
        }

        return $_response;
    }

    /**
     * @param string $charset
     */
    public static function setCharset( $charset )
    {
        static::$_charset = $charset;
    }

    /**
     * @return string
     */
    public static function getCharset()
    {
        return static::$_charset;
    }

}
