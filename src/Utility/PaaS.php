<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
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

use Kisma\Core\Utility\Option;

/**
 * A PaaS helper
 */
class PaaS
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const CF_CONFIG_PATTERN = 'config.{type}.json';
    /**
     * @type string
     */
    const CF_CONFIG_TARGET = 'config.json';
    /**
     * @type string
     */
    const DB_CONFIG_PATTERN = 'database.{type}.config.php-dist';
    /**
     * @type string
     */
    const DB_CONFIG_TARGET = 'database.config.php';
    /**
     * @type string
     */
    const MANIFEST_PATTERN = 'manifest.{type}.yml-dist';
    /**
     * @type string
     */
    const MANIFEST_TARGET = 'manifest.yml';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array The supported systems
     */
    static protected $_systems = array(
        //  IBM Bluemix
        'bluemix' => array(
            'display_name' => 'IBM Bluemix',
            'path'         => '/bluemix/dsp-core-bluemix',
            'provider_url' => 'http://www.bluemix.net/',
        ),
        //  Pivotal CF
        'pivotal' => array(
            'display_name' => 'Pivotal CF',
            'path'         => '/bluemix/dsp-core-pivotal',
            'provider_url' => 'http://www.pivotal.io/',
        ),
    );
    /**
     * @var string
     */
    protected $_cfRoot = null;

    /**
     * @param string $target The target PaaS
     * @param string $cfRoot The cf command line root. Defaults to $HOME/.cf
     */
    public function __construct( $target, $cfRoot = null )
    {
        if ( !in_array( strtolower( $target ), array_keys( static::$_systems ) ) )
        {
            throw new InvalidArgumentException(
                'The target "' . $target . '" is invalid. Must be one of the following:' . PHP_EOL . implode( ', ', array_keys( static::$_systems ) )
            );
        }

        if ( !is_dir( $this->_cfRoot = $cfRoot ?: getenv( 'HOME' ) . '/.cf' ) )
        {
            throw new InvalidArgumentException( 'The specified cf root path is invalid.' );
        }
    }

    /**
     * @param string $target
     */
    public function swapConfigs( $target )
    {
        $_template = $this->_getTemplate( $target );
        $_basePath = dirname( __DIR__ );

        //  Do CF config
        chdir( $_basePath );
        $_target = $this->_getTarget( $target );
        $_source = $this->_getPattern( 'CF_CONFIG' );
        $this->_swapTarget( $_target, $_source );

        //  Database
        $_target = $this->_getTarget( 'DB_CONFIG' );
        $_source = $this->_getPattern( 'DB_CONFIG' );
        $this->_swapTarget( $_target, $_source );
    }

    /**
     * @param string $target
     * @param string $source
     *
     * @return bool
     */
    protected function _swapTarget( $target, $source )
    {
        //  Unlink current target
        if ( is_link( $target ) )
        {
            //  Unable to remove prior link
            if ( false === unlink( $target ) )
            {
                return false;
            }
        }

        //  Don't touch real files
        if ( file_exists( $target ) && !is_link( $target ) )
        {
            return false;
        }

        //  Link replaced pattern to the target
        echo 'Source: ' . $source . PHP_EOL;
        echo 'Target: ' . $target . PHP_EOL;

        return link( $source, $target );
    }

    /**
     * @param string $type The type of pattern to get: manifest, db_config, or cf_config
     *
     * @return string
     */
    protected function _getPattern( $type )
    {
        return str_replace( '{type}', $type, strtolower( constant( 'static::' . strtoupper( $type ) . '_PATTERN' ) ) );
    }

    /**
     * @param string $type The type of pattern to get: manifest, db_config, or cf_config
     *
     * @return string
     */
    protected function _getTarget( $type )
    {
        return strtolower( constant( 'static::' . strtoupper( $type ) . '_TARGET' ) );
    }

    /**
     * @param string $type
     *
     * @return array
     */
    protected function _getTemplate( $type )
    {
        if ( null === ( $_template = Option::get( static::$_systems, $type ) ) )
        {
            throw new InvalidArgumentException(
                'The type "' . $type . '" is invalid. Must be one of the following:' . PHP_EOL . implode( ', ', array_keys( static::$_systems ) )
            );
        }

        return $_template;
    }

    /**
     * @param bool $keysOnly
     *
     * @return array
     */
    public static function getSystems( $keysOnly = false )
    {
        if ( $keysOnly )
        {
            return array_keys( static::$_systems );
        }

        return static::$_systems;
    }

}

if ( empty( $argv ) || !isset( $argv[1] ) )
{
    echo 'usage: passwitch [' . implode( '|', PaaSwitch::getSystems( true ) ) . ']';
    exit( 1 );
}

$_switcher = new PaaSwitch( $argv[1] );
$_switcher->swapConfigs( $argv[1] );