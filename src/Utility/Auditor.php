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
namespace DreamFactory\Platform\Utility;

use DreamFactory\Common\Enums\GelfLevels;
use DreamFactory\Common\Services\Graylog\GelfLogger;
use DreamFactory\Yii\Utility\Pii;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contains auditing methods for DFE
 */
class Auditor
{
    //********************************************************************************
    //* Public Methods
    //********************************************************************************

    /**
     * @param string $host
     */
    public static function setHost( $host = GelfLogger::DefaultHost )
    {
        GelfLogger::setHost( $host );
    }

    /**
     * Logs API requests to logging system
     *
     * @param Request $request
     *
     * @return bool
     */
    public static function logRequest( Request $request )
    {
        $_host = $request->getHost();

        $_contentType = $request->getContentType();
        $_content = $request->getContent();
        $_contentSize = sizeof( $_content );

        if ( is_string( $_content ) )
        {
            $_content = trim( $_content );

            if ( false !== stripos( $_contentType, 'application/json', 0 ) )
            {
                $_content = DataFormatter::jsonToArray( $_content );
            }
            else if ( false !== stripos( $_contentType, 'application/xml', 0 ) )
            {
                $_content = DataFormatter::xmlToArray( $_content );
            }
        }

        //	Get the additional data ready
        $_logInfo = array(
            'short_message'      => $request->getRequestUri(),
            'level'              => GelfLevels::Info,
            'facility'           => 'fabric-instance',
            '_instance_id'       => Pii::getParam( 'dsp.name', $_host ),
            '_app_name'          => $request->get( 'app_name' ),
            '_host'              => $_host,
            '_method'            => $request->getMethod(),
            '_source_ip'         => $request->getClientIps(),
            '_content_type'      => $request->getContentType(),
            '_content_size'      => $_contentSize,
            '_content'           => $_content,
            '_path_info'         => $request->getPathInfo(),
            '_query'             => $request->query->all(),
            '_path_translated'   => $request->server->get( 'path-translated' ),
            '_user_agent'        => $request->server->get( 'user-agent' ),
            '_request_timestamp' => $request->server->get( 'request-time-float' ),
        );

        GelfLogger::logMessage( $_logInfo );
    }
}