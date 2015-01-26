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

/**
 * A GELF v1.1 message
 * v1.1 (11/2013)
 *
 * @link http://www.graylog2.org/resources/gelf/specification
 */
class GelfMessage
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string The GELF version of this message
     */
    const GELF_VERSION = '1.1';

    //**********************************************************************
    //* Members
    //**********************************************************************

    /**
     * @type string
     */
    protected $_version = self::GELF_VERSION;
    /**
     * @type string
     */
    protected $_host;
    /**
     * @type string
     */
    protected $_shortMessage;
    /**
     * @type string
     */
    protected $_fullMessage;
    /**
     * @type double
     */
    protected $_timestamp;
    /**
     * @type int
     */
    protected $_level = AuditLevels::INFO;
    /**
     * @type array Additional fields added to the message
     */
    protected $_additional = array();

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * @param array $additional Any additional fields data to send in the message
     */
    public function __construct( array $additional = array() )
    {
        $this
            ->reset()
            ->addAdditionalFields( $additional );
    }

    //**********************************************************************
    //* Public Methods
    //**********************************************************************

    /**
     * Resets the message to default values
     *
     * @return $this
     */
    public function reset()
    {
        /** @var array $argv */
        global $argv;

        $this->_host = gethostname();
        $this->_timestamp = microtime( true );
        $this->_level = AuditLevels::INFO;
        $this->_shortMessage = $this->_fullMessage = null;
        $this->_additional = array();

        $_file = null;

        switch ( $this->_additional['_php_sapi'] = PHP_SAPI )
        {
            case 'cli':
                isset( $argv[0] ) && ( $_file = $argv[0] );
                break;

            default:
                isset( $_SERVER, $_SERVER['SCRIPT_FILENAME'] ) && ( $_file = $_SERVER['SCRIPT_FILENAME'] );
                break;
        }

        $_file && ( $this->_additional['_file'] = $_file );

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $_message = array(
            'version'       => $this->_version,
            'host'          => $this->_host,
            'short_message' => $this->_shortMessage,
            'full_message'  => $this->_fullMessage,
            'timestamp'     => $this->_timestamp,
            'level'         => $this->_level,
        );

        if ( !empty( $this->_additional ) )
        {
            $_message = array_merge( $_message, $this->_additional );
        }

        return $_message;
    }

    /**
     * @param string $name The name of the additional field
     *
     * @throws \InvalidArgumentException
     * @return string
     */
    public function getAdditionalField( $name )
    {
        $_key = '_' . ltrim( $name, '_' );

        return array_key_exists( $_key, $this->_additional ) ? $this->_additional[$_key] : null;
    }

    /**
     * @param string $name  The name of the additional field
     * @param string $value The value of the additional field; null to unset
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function setAdditionalField( $name, $value )
    {
        $_key = '_' . ltrim( $name, '_' );

        if ( '_id' == $_key || '_key' == $_key )
        {
            throw new \InvalidArgumentException( 'Additional fields may not be called "id" or "key".' );
        };

        if ( null === $value && array_key_exists( $_key, $this->_additional ) )
        {
            unset( $this->_additional[$_key] );
        }
        else
        {
            $this->_additional[$_key] = $value;
        }

        return $this;
    }

    /**
     * Adds an array of additional fields at once
     *
     * @param array $fields Array of key value pairs
     *
     * @return $this
     */
    public function addAdditionalFields( array $fields = array() )
    {
        foreach ( $fields as $_key => $_value )
        {
            $this->setAdditionalField( $_key, $_value );
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->_host;
    }

    /**
     * @return string
     */
    public function getFullMessage()
    {
        return $this->_fullMessage;
    }

    /**
     * @param string $fullMessage
     *
     * @return Message
     */
    public function setFullMessage( $fullMessage )
    {
        $this->_fullMessage = $fullMessage;

        return $this;
    }

    /**
     * @return string
     */
    public function getShortMessage()
    {
        return $this->_shortMessage;
    }

    /**
     * @param string $shortMessage
     *
     * @return GelfMessage
     */
    public function setShortMessage( $shortMessage )
    {
        $this->_shortMessage = $shortMessage;

        return $this;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * @return double
     */
    public function getTimestamp()
    {
        return $this->_timestamp;
    }

    /**
     * @return integer
     */
    public function getLevel()
    {
        return $this->_level;
    }

    /**
     * @param int $level The message level; null for default
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function setLevel( $level = AuditLevels::INFO )
    {
        if ( !Levels::contains( $level ) )
        {
            throw new \InvalidArgumentException( 'The level "' . $level . '" is not valid.' );
        }

        $this->_level = $level;

        return $this;
    }
}
