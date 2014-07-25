<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
namespace DreamFactory\Platform\Yii\Behaviors;

use CModelEvent;
use Kisma\Core\Utility\Hasher;

/**
 * SecureJson.php
 * If attached to a model, fields are decrypted/encrypted on load/save
 */
class SecureJson extends SecureString
{
    //*************************************************************************
    //* Handlers
    //*************************************************************************

    /**
     * @param \CModelEvent $event
     * @param array        $attributes
     * @param bool         $from
     * @param bool         $secure
     */
    protected function _convertAttributes( $event, $attributes, $from = false, $secure = true )
    {
        if ( !empty( $attributes ) && is_array( $attributes ) )
        {
            foreach ( $attributes as $_attribute )
            {
                if ( $event->sender->hasAttribute( $_attribute ) )
                {
                    $_value = $event->sender->getAttribute( $_attribute );

                    switch ( $from )
                    {
                        case true:
                            if ( empty( $_value ) )
                            {
                                $_value = array();
                            }
                            else
                            {
                                $_workData = $_value;

                                if ( $secure )
                                {
                                    $_workData = Hasher::decryptString( $_workData, $this->_salt );
                                }

                                // 	Try decoding decrypted string
                                //	Make sure we can deserialize...
                                $_decoded = json_decode( $_workData, true );

                                if ( JSON_ERROR_NONE != json_last_error() )
                                {
                                    $event->handled = true;
                                    $event->sender->addError(
                                        $_attribute,
                                        'The column "' . $_attribute . '" is malformed or otherwise invalid.'
                                    );
                                    continue;
                                }

                                $_value = $_decoded ? : array();
                            }

                            break;

                        case false:
                            //	Make sure we can serialize...
                            if ( empty( $_value ) )
                            {
                                $_value = array();
                            }
                            elseif ( is_string( $_value ) )
                            {
                                // maybe it is already encoded json, check for original
                                if ( null !== ( $_decoded = json_decode( $_value, true ) ) )
                                {
                                    $_value = $_decoded;
                                }
                            }

                            $_encoded = json_encode( $_value );

                            if ( ( false === $_encoded ) || ( JSON_ERROR_NONE != json_last_error() ) )
                            {
                                $event->handled = true;
                                $event->sender->addError(
                                    $_attribute,
                                    'The column "' . $_attribute . '" is malformed or otherwise invalid.'
                                );
                                continue;
                            }

                            //	Encrypt it...
                            $_value = ( $secure ) ? Hasher::encryptString( $_encoded, $this->_salt ) : $_encoded;
                            break;
                    }

                    $event->sender->setAttribute( $_attribute, $_value );
                }
            }
        }
    }
}
