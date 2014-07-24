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
use Kisma\Core\Utility\Storage;

/**
 * SecureFrozenObject.php
 * If attached to a model, fields are decrypted/encrypted on load/save
 */
class SecureFrozenObject extends SecureString
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
                                $_value = null;
                            }
                            else
                            {
                                $_workData = $_value;

                                if ( $secure )
                                {
                                    $_workData = Hasher::decryptString( $_workData, $this->_salt );
                                }

                                // 	Try defrosting decrypted string
                                //	Make sure we can deserialize...
                                $_decoded = Storage::defrost( $_workData );

                                if ( empty( $_decoded ) )
                                {
                                    //	Try decoding raw string
                                    $_decoded = Storage::defrost( $_value );
                                }
                                elseif ( is_string( $_decoded) && ( $_decoded === $_value ) )
                                {
                                    $_decoded = @gzuncompress( @base64_decode( $_value ) );
                                }

                                $_value = $_decoded ? : $_value;
                            }

                            break;

                        case false:
                            //	Make sure we can serialize...
                            if ( is_string( $_value ) )
                            {
                                $_encoded = base64_encode( gzcompress( $_value ) );
                            }
                            else
                            {
                                $_encoded = Storage::freeze( $_value );
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
