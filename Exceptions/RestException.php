<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Exceptions;

use Kisma\Core\Interfaces\HttpResponse;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;

/**
 * RestException
 * Represents an exception caused by REST API operations of end-users.
 *
 * The HTTP error code can be obtained via {@link statusCode}.
 */
class RestException extends PlatformServiceException implements HttpResponse
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var int HTTP status code, such as 403, 404, 500, etc.
	 */
	protected $_statusCode;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * Constructor.
	 *
	 * @param int    $status  HTTP status code, such as 404, 500, etc.
	 * @param string $message error message
	 * @param int    $code    error code
	 */
	public function __construct( $status, $message = null, $code = null )
	{
		$this->_statusCode = $status;
		$code = $code ? : $this->_statusCode;

		if ( empty( $message ) )
		{
			$_name = \Kisma\Core\Enums\HttpResponse::nameof( $code );
			$message = Inflector::camelize( Inflector::neutralize( $_name ), '_', true );
		}

		parent::__construct( $message, $code );

		Log::error( 'REST Exception #' . $code . ' > ' . $message );
	}

	/**
	 * @param int $statusCode
	 *
	 * @return RestException
	 */
	public function setStatusCode( $statusCode )
	{
		$this->_statusCode = $statusCode;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getStatusCode()
	{
		return $this->_statusCode;
	}

	/**
	 * @return int
	 */
	public function statusCode()
	{
		return $this->_statusCode;
	}

	/**
	 * This ONLY exists because Yii uses public variables ARGH!
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name )
	{
		$name = strtolower( $name );

		if ( method_exists( $this, 'get' . $name ) )
		{
			return $this->{'get' . $name}();
		}
	}
}
