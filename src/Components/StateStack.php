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

/**
 * A simple state stack
 */
class StateStack
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array
     */
    protected static $_states = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param bool $clear
     *
     * @return int
     */
    public static function push( $clear = true )
    {
        $_state = static::current();

        if ( $clear )
        {
            static::clear();
        }

        return array_push( static::$_states, $_state );
    }

    /**
     * @return array
     */
    public static function current()
    {
        $_state = array(
            'GET'             => isset( $_GET ) && !empty( $_GET ) ? $_GET : null,
            'POST'            => isset( $_POST ) && !empty( $_POST ) ? $_POST : null,
            'REQUEST'         => isset( $_REQUEST ) && !empty( $_REQUEST ) ? $_REQUEST : null,
            'FILES'           => isset( $_FILES ) && !empty( $_FILES ) ? $_FILES : null,
            'COOKIE'          => isset( $_COOKIE ) && !empty( $_COOKIE ) ? $_COOKIE : null,
            'SERVER'          => isset( $_SERVER ) ? $_SERVER : null,
            //  Store request object
            '_REQUEST_OBJECT' => Pii::requestObject(),
        );

        return $_state;
    }

    /**
     * @param bool $restore
     *
     * @return mixed
     */
    public static function pop( $restore = true )
    {
        $_state = array_pop( static::$_states );

        if ( !empty( $_state ) && $restore )
        {
            static::clear();

            foreach ( $_state as $_key => $_value )
            {
                if ( '_REQUEST_OBJECT' == $_key )
                {
                    //  Restore request object
                    Pii::app()->setRequestObject( $_value );
                }
                elseif ( null !== $_value && $GLOBALS[ '_' . $_key ] !== $_value )
                {
                    $GLOBALS[ '_' . $_key ] = $_value;
                }
            }
        }

        return $_state;
    }

    /**
     * @param bool $keepServerStuff
     */
    public static function clear( $keepServerStuff = true )
    {
        if ( isset( $GLOBALS['_GET'] ) )
        {
            $GLOBALS['_GET'] = array();
        }

        if ( isset( $GLOBALS['_POST'] ) )
        {
            $GLOBALS['_POST'] = array();
        }

        if ( isset( $GLOBALS['_REQUEST'] ) )
        {
            $GLOBALS['_REQUEST'] = array();
        }

        if ( isset( $GLOBALS['_FILES'] ) )
        {
            $GLOBALS['_FILES'] = array();
        }

        if ( isset( $GLOBALS['_COOKIE'] ) )
        {
            $GLOBALS['_COOKIE'] = array();
        }

        if ( isset( $GLOBALS['_SERVER'] ) )
        {
            $_server = array();

            if ( $keepServerStuff )
            {
                foreach ( $_SERVER as $_key => $_value )
                {
                    if ( 'HTTP_' == substr( $_key, 0, 5 ) )
                    {
                        $_server[ $_key ] = $_value;
                    }
                }
            }

            $GLOBALS['_SERVER'] = $_server;
        }
    }
}