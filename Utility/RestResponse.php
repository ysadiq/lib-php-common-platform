<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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

use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Common\Enums\OutputFormats;
use DreamFactory\Oasys\Exceptions\RedirectRequiredException;
use DreamFactory\Platform\Enums\ResponseFormats;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

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
	 * @param string $requested Optional requested set format
	 * @param string $internal Reference returned internal formatting (jtables, etc.)
	 *
	 * @return string output format, outer envelope
	 */
	public static function detectResponseFormat( $requested, &$internal )
	{
		$internal = ResponseFormats::RAW;
		$_format = $requested;

		if ( empty( $_format ) )
		{
			$_format = FilterInput::request( 'format', null, FILTER_SANITIZE_STRING );

			if ( empty( $_format ) )
			{
				$_accepted = RestResponse::parseAcceptHeader(
					FilterInput::server( 'HTTP_ACCEPT', null, FILTER_SANITIZE_STRING )
				);
				$_accepted = array_values( $_accepted );
				$_format = Option::get( $_accepted, 0 );
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

	public static function parseAcceptHeader( $header )
	{
		$accept = array();
		foreach ( preg_split( '/\s*,\s*/', $header ) as $i => $term )
		{
			$o = new \stdclass;
			$o->pos = $i;
			if ( preg_match( ",^(\S+)\s*;\s*(?:q|level)=([0-9\.]+),i", $term, $M ) )
			{
				$o->type = $M[1];
				$o->q = (double)$M[2];
			}
			else
			{
				$o->type = $term;
				$o->q = 1;
			}
			$accept[] = $o;
		}
		usort(
			$accept,
			function ( $a, $b )
			{ /* first tier: highest q factor wins */
				$diff = $b->q - $a->q;
				if ( $diff > 0 )
				{
					$diff = 1;
				}
				else if ( $diff < 0 )
				{
					$diff = -1;
				}
				else
				{ /* tie-breaker: first listed item wins */
					$diff = $a->pos - $b->pos;
				}

				return $diff;
			}
		);

		$_result = array();
		foreach ( $accept as $a )
		{
			$_result[$a->type] = $a->type;
		}

		return $_result;
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

		if ( $ex instanceof RestException )
		{
			$_status = $ex->getStatusCode();
		}
		elseif ( $ex instanceOf \CHttpException )
		{
			$_status = $ex->statusCode;
		}

		$_errorInfo = array(
			'message' => htmlentities( $ex->getMessage() ),
			'code'    => $ex->getCode()
		);

		if ( $ex instanceof RedirectRequiredException )
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
				$_contentType = 'application/json; charset=utf-8';

				// JSON if no callback
				if ( isset( $_GET['callback'] ) )
				{
					// JSONP if valid callback
					if ( !static::is_valid_callback( $_GET['callback'] ) )
					{
						// Otherwise, bad request
						header( 'status: 400 Bad Request', true, static::BadRequest );
						Pii::end();

						return;
					}

					$result = "{$_GET['callback']}($result);";
				}
				break;

			case OutputFormats::XML:
			case 'xml':
				$_contentType = 'application/xml';
				$result = '<?xml version="1.0" ?>' . "<dfapi>$result</dfapi>";
				break;

			case 'csv':
				$_contentType = 'text/csv';
				break;

			default:
				$_contentType = 'application/octet-stream';
				break;
		}

		/* gzip handling output if necessary */
		ob_start();
		ob_implicit_flush( 0 );

		if ( !headers_sent() )
		{
			// headers
			$code = static::getHttpStatusCode( $code );
			$_title = static::getHttpStatusCodeTitle( $code );
			header( "HTTP/1.1 $code $_title" );
			header( "Content-Type: $_contentType" );
			//	IE 9 requires hoop for session cookies in iframes
			header( 'P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"' );

			if ( !empty( $as_file ) )
			{
				header( "Content-Disposition: attachment; filename=\"$as_file\";" );
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
		$identifier_syntax
			= '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';

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

		return preg_match( $identifier_syntax, $subject )
			   && !in_array( mb_strtolower( $subject, 'UTF-8' ), $reserved_words );
	}
}
