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
namespace DreamFactory\Platform\Services\Auditing;

use DreamFactory\Platform\Enums\AuditLevels;
use DreamFactory\Yii\Utility\Pii;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contains auditing methods for DFE
 */
class AuditService implements LoggerAwareInterface
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string
     */
    const DEFAULT_FACILITY = 'fabric-instance';

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type GelfLogger
     */
    protected static $_logger = null;

    //********************************************************************************
    //* Public Methods
    //********************************************************************************

    /**
     * @param string $host
     */
    public static function setHost( $host = GelfLogger::DEFAULT_HOST )
    {
        static::getLogger()->setHost( $host );
    }

    /**
     * Logs API requests to logging system
     *
     * @param Request $request
     * @param int     $level
     * @param string  $facility
     *
     * @return bool
     */
    public static function logRequest( Request $request, $level = AuditLevels::INFO, $facility = self::DEFAULT_FACILITY )
    {
        $_host = $request->getHost();

        $_data = array(
            '_facility'          => $facility,
            '_instance_id'       => Pii::getParam( 'dsp.name', $_host ),
            '_app_name'          => $request->get(
                'app_name',
                //	No app_name, look for headers...
                $request->server->get( 'X_DREAMFACTORY_APPLICATION_NAME', $request->server->get( 'X_APPLICATION_NAME' ) )
            ),
            '_host'              => $_host,
            '_method'            => $request->getMethod(),
            '_source_ip'         => $request->getClientIps(),
            '_content_type'      => $request->getContentType(),
            '_content_length'    => $request->headers->get( 'Content-Length' ),
            '_path_info'         => $request->getPathInfo(),
            '_path_translated'   => $request->server->get( 'PATH_TRANSLATED' ),
            '_query'             => $request->query->all(),
            '_request_timestamp' => $request->server->get( 'REQUEST_TIME_FLOAT' ),
            '_user_agent'        => $request->headers->get( 'User-Agent' ),
        );

        $_message = new GelfMessage( $_data );
        $_message->setLevel( $level );
        $_message->setShortMessage( $request->getMethod() . ' ' . $request->getRequestUri() );

        static::getLogger()->send( $_message );
    }

    /**
     * @return \DreamFactory\Platform\Services\Auditing\GelfLogger
     */
    public static function getLogger()
    {
        return static::$_logger ?: static::$_logger = new GelfLogger();
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     *
     * @return $this
     */
    public function setLogger( LoggerInterface $logger )
    {
        static::$_logger = $logger;

        return $this;
    }
}
