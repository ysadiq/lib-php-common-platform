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
namespace DreamFactory\Platform\Components;

use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Option;

/**
 * A base class for responding to API calls
 */
class ApiResponse
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Creates a JSON encoded array (as a string) with a standard REST response. Override to provide
     * a different response format.
     *
     * @param array   $result The result of the call
     * @param boolean $isError
     * @param string  $errorMessage
     * @param integer $errorCode
     * @param array   $additionalInfo
     *
     * @return string JSON encoded array
     */
    public static function create( $result = null, $isError = false, $errorMessage = 'failure', $errorCode = 0, $additionalInfo = array() )
    {
        $_info = array();

        if ( $isError )
        {
            $_info = array(
                'error_code'    => $errorCode,
                'error_message' => $errorMessage,
            );
        }

        if ( !empty( $additionalInfo ) )
        {
            $_info = array_merge( $additionalInfo, $_info );
        }

        return static::_buildContainer( !$isError, $result, $_info );
    }

    /**
     * Builds a v2 response container
     *
     * @param bool  $success
     * @param mixed $result    The result of the call or details about the condition
     * @param array $extraInfo Additional data to add to the _info object
     *
     * @return array
     */
    protected static function _buildContainer( $success = true, $result = null, $extraInfo = null )
    {
        $_id = sha1(
            ( $_start = Option::server( 'REQUEST_TIME_FLOAT', microtime( true ) ) ) .
            Option::server( 'HTTP_HOST', $_host = gethostname() ) .
            Option::server( 'REMOTE_ADDR', gethostbyname( $_host ) )
        );

        $_ro = Pii::request( false );

        $_container = array(
            'success' => $success,
            'result'  => $result,
            '_info'   => array_merge(
                array(
                    'id'        => $_id,
                    'timestamp' => date( 'c', $_start ),
                    'elapsed'   => (float)number_format( microtime( true ) - $_start, 4 ),
                    'verb'      => $_ro->getMethod(),
                    'uri'       => $_ro->server->get( 'request-uri' ),
                    'signature' => base64_encode( hash_hmac( 'sha256', $_id, $_id, true ) ),
                ),
                Option::clean( $extraInfo )
            ),
        );

        return $_container;
    }

}