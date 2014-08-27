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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Components\PlatformStore;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\ResourceStore;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;

/**
 * Config
 * DSP system administration manager
 */
class Environment extends BaseSystemRestResource
{
    //*************************************************************************
    //  Constants
    //*************************************************************************

    /**
     * @type array The currently cached configuration for this DSP
     */
    const CACHE_KEY = 'platform.environment';
    /**
     * @type int The number of seconds at most to cache these resources
     */
    const CONFIG_CACHE_TTL = PlatformStore::DEFAULT_TTL;
    /**
     * @type string file containing linux system information
     */
    const LSB_RELEASE = '/etc/lsb-release';

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@InheritDoc}
     */
    public function __construct( $consumer = null, $resourceArray = array() )
    {
        parent::__construct(
            $consumer,
            array(
                'name'           => 'Environment',
                'type'           => 'System',
                'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
                'api_name'       => 'config',
                'description'    => 'Information about the environment in which the DSP is running',
                'is_active'      => true,
                'resource_array' => $resourceArray,
            )
        );
    }

    /**
     * Override for GET of public info
     *
     * @param string $operation
     * @param mixed  $resource
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
     * @throws \Exception
     * @return bool
     */
    public function checkPermission( $operation, $resource = null )
    {
        //  Clients use basic GET on global config to startup
        if ( static::GET == $operation )
        {
            return true;
        }

        return ResourceStore::checkPermission( $operation, $this->_serviceName, $resource );
    }

    /**
     * {@InheritDoc}
     * ENSURE THAT NOTHING IN THIS FUNCTION TRIGGERS AN EVENT FOR NOW!
     */
    protected function _handleGet()
    {
        $_release = null;
        $_phpInfo = $this->_getPhpInfo();

        if ( false !== ( $_raw = file( static::LSB_RELEASE ) ) && !empty( $_raw ) )
        {
            $_release = array();

            foreach ( $_raw as $_line )
            {
                $_fields = explode( '=', $_line );
                $_release[str_replace( 'distrib_', null, strtolower( $_fields[0] ) )] = trim( $_fields[1], PHP_EOL . '"' );
            }
        }

        $_response = array(
            'php_info' => $_phpInfo,
            'platform' => Config::getCurrentConfig(),
            'release'  => $_release,
            'server'   => array(
                'server_os' => strtolower( php_uname( 's' ) ),
                'uname'     => php_uname( 'a' ),
            ),
        );

        array_multisort( $_response );

        //	Cache configuration
        Platform::storeSet( static::CACHE_KEY, $_response, static::CONFIG_CACHE_TTL );

        $this->_response = $this->_response ? array_merge( $this->_response, $_response ) : $_response;
        unset( $_response );

        return $this->_response;
    }

    /**
     * Parses the data coming back from phpinfo() call and returns in an array
     *
     * @return array
     */
    protected function _getPhpInfo()
    {
        $_html = null;
        $_info = array();
        $_pattern =
            '#(?:<h2>(?:<a name=".*?">)?(.*?)(?:</a>)?</h2>)|(?:<tr(?: class=".*?")?><t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>)?)?</tr>)#s';

        \ob_start();
        @\phpinfo();
        $_html = \ob_get_contents();
        \ob_end_clean();

        if ( preg_match_all( $_pattern, $_html, $_matches, PREG_SET_ORDER ) )
        {
            foreach ( $_matches as $_match )
            {
                $_keys = array_keys( $_info );
                $_lastKey = end( $_keys );

                if ( strlen( $_match[1] ) )
                {
                    $_info[$_match[1]] = array();
                }
                elseif ( isset( $_match[3] ) )
                {
                    $_info[$_lastKey][$_match[2]] = isset( $_match[4] ) ? array($_match[3], $_match[4]) : $_match[3];
                }
                else
                {
                    $_info[$_lastKey][] = $_match[2];
                }

                unset( $_keys, $_match );
            }
        }

        return $this->_cleanPhpInfo( $_info );
    }

    /**
     * @param array $info
     *
     * @param bool  $recursive
     *
     * @return array
     */
    protected function _cleanPhpInfo( $info, $recursive = false )
    {
        static $_excludeKeys = array('directive', 'variable',);

        $_clean = array();

        //  Remove images and move nested args to root
        if ( !$recursive && isset( $info[0], $info[0][0] ) && is_array( $info[0] ) )
        {
            $info['general'] = array();

            foreach ( $info[0] as $_key => $_value )
            {
                if ( is_numeric( $_key ) || in_array( strtolower( $_key ), $_excludeKeys ) )
                {
                    continue;
                }

                $info['general'][$_key] = $_value;
                unset( $info[0][$_key] );
            }

            unset( $info[0] );
        }

        foreach ( $info as $_key => $_value )
        {
            if ( in_array( strtolower( $_key ), $_excludeKeys ) )
            {
                continue;
            }

            $_key = strtolower( str_replace( ' ', '_', $_key ) );

            if ( is_array( $_value ) && 2 == count( $_value ) && isset( $_value[0], $_value[1] ) )
            {
                $_v1 = Option::get( $_value, 0 );

                if ( $_v1 == '<i>no value</i>' )
                {
                    $_v1 = null;
                }

                if ( Scalar::in( strtolower( $_v1 ), 'on', 'off', '0', '1' ) )
                {
                    $_v1 = Option::getBool( $_value, 0 );
                }

                $_value = $_v1;
            }

            if ( is_array( $_value ) )
            {
                $_value = $this->_cleanPhpInfo( $_value, true );
            }

            $_clean[$_key] = $_value;
        }

        return $_clean;
    }
}
