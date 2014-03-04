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
use DreamFactory\Platform\Enums\ResponseFormats;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Option;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
	 * @var int
	 */
	const GZIP_THRESHOLD = 2048;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param string $requested Optional requested set format, FALSE for raw formatting
	 * @param string $internal  Reference returned internal formatting (jtables, etc.)
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

		if ( empty( $_format ) )
		{
			$_format = Pii::app()->getRequestObject()->query->get( 'format' );

			if ( empty( $_format ) )
			{
				$_format = @current( Pii::app()->getRequestObject()->getAcceptableContentTypes() );
			}
		}

		$_format = trim( strtolower( $_format ) );

		switch ( $_format )
		{
			case 'json':
			case 'application/json':
				$_format = 'json';
				break;

			case 'xml':
			case 'application/xml':
			case 'text/xml':
				$_format = 'xml';
				break;

			case 'csv':
			case 'text/csv':
				$_format = 'csv';
				break;

			default:
				if ( ResponseFormats::contains( $_format ) )
				{
					//	Set the response format here and in the store
					ResourceStore::setResponseFormat( $internal = $_format );
				}

				//	Set envelope to JSON
				$_format = 'json';
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
	 * @param \Exception $ex
	 * @param string     $desired_format
	 */
	public static function sendErrors( $ex, $desired_format = 'json' )
	{
		$_status = $ex->getCode();
		$_errorInfo = array(
			'message' => htmlentities( $ex->getMessage() ),
			'code'    => $ex->getCode()
		);

		if ( $ex instanceof RestException )
		{
			$_status = $ex->getStatusCode();
			$_errorInfo['context'] = $ex->getContext();
		}
		elseif ( $ex instanceOf \CHttpException )
		{
			$_status = $ex->statusCode;
		}
		elseif ( $ex instanceof RedirectRequiredException )
		{
			$_errorInfo['location'] = $ex->getRedirectUri();
		}

		$_result = array(
			'error' => array( $_errorInfo )
		);

		if ( static::Ok != $_status )
		{
			if ( $_status == static::InternalServerError || $_status == static::BadRequest )
			{
//				Log::error( 'Error ' . $_status . ': ' . $ex->getMessage() );
			}
			else
			{
//				Log::info( 'Non-Error ' . $_status . ': ' . $ex->getMessage() );
			}
		}

		$_result = DataFormat::reformatData( $_result, null, $desired_format );
		static::sendResults( $_result, $_status, $desired_format );
	}

	/**
	 * @param mixed  $result
	 * @param int    $code
	 * @param string $format
	 * @param string $as_file
	 */
	public static function sendResults( $result, $code = RestResponse::Ok, $format = 'json', $as_file = null )
	{
		//	Some REST services may handle the response, they just return null
		if ( is_null( $result ) )
		{
			Pii::end();

			return;
		}

		switch ( $format )
		{
			case OutputFormats::JSON:
			case 'json':
				$_response = new JsonResponse( $result, $code );
				$_response->setCallback( Option::get( $_GET, 'callback' ) );
				break;

			case OutputFormats::XML:
			case 'xml':
				$_response = new Response( '<?xml version="1.0" ?><dfapi>' . $result . '</dfapi>', $code );
				$_response->headers->set( 'Content-Type', 'application/xml' );
				break;

			case OutputFormats::CSV:
				$_response = new Response( null, $code );
				$_response->headers->set( 'Content-Type', 'text/csv' );
				break;

			default:
				$_response = new Response( null, $code );
				$_response->headers->set( 'Content-Type', 'application/octet-stream' );
				break;
		}

		$_response->headers->set( 'P3P', 'CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"' );

		/* gzip handling output if necessary */
		ob_start();
		ob_implicit_flush( 0 );

		if ( !headers_sent() )
		{
			if ( !empty( $as_file ) )
			{
				$_response->headers->set( 'Content-Disposition', 'attachment; filename="' . $as_file . '";' );
			}

			//	Add additional headers for CORS support
			Pii::app()->addCorsHeaders();
		}

		// send it out
		echo $result;

		if ( false !== strpos( Option::server( 'HTTP_ACCEPT_ENCODING' ), 'gzip' ) )
		{
			$_output = ob_get_clean();

			if ( strlen( $_output ) >= static::GZIP_THRESHOLD )
			{
				header( 'Content-Encoding: gzip' );
				$_output = gzencode( $_output, 9 );
			}

			// compressed or not, dump it out as the buffer is destroyed already
			echo $_output;
		}
		else
		{
			// flush output and destroy buffer
			ob_end_flush();
		}

		Pii::end();
	}

	/**
	 * @param $subject
	 *
	 * @return bool
	 */
	public static function is_valid_callback( $subject )
	{
		$identifier_syntax = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';

		$reserved_words = array(
			'break',
			'do',
			'instanceof',
			'typeof',
			'case',
			'else',
			'new',
			'var',
			'catch',
			'finally',
			'return',
			'void',
			'continue',
			'for',
			'switch',
			'while',
			'debugger',
			'function',
			'this',
			'with',
			'default',
			'if',
			'throw',
			'delete',
			'in',
			'try',
			'class',
			'enum',
			'extends',
			'super',
			'const',
			'export',
			'import',
			'implements',
			'let',
			'private',
			'public',
			'yield',
			'interface',
			'package',
			'protected',
			'static',
			'null',
			'true',
			'false'
		);

		return preg_match( $identifier_syntax, $subject ) && !in_array( mb_strtolower( $subject, 'UTF-8' ), $reserved_words );
	}
}
