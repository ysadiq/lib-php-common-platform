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
            '_content_length'    => $request->headers->get( 'Content-Length' ),
            '_path_info'         => $request->getPathInfo(),
            '_query'             => $request->query->all(),
            '_path_translated'   => $request->server->get( 'PATH_TRANSLATED' ),
            '_request_timestamp' => $request->server->get( 'REQUEST_TIME_FLOAT' ),
            '_user_agent'        => $request->headers->get( 'User-Agent' ),
        );

        GelfLogger::logMessage( $_logInfo );
    }
}