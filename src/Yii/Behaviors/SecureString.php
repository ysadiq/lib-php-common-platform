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
use DreamFactory\Yii\Behaviors\BaseModelBehavior;
use Kisma\Core\Utility\Hasher;

/**
 * SecureString.php
 * If attached to a model, fields are decrypted/encrypted on load/save
 */
class SecureString extends BaseModelBehavior
{
    //********************************************************************************
    //* Member
    //********************************************************************************

    /**
     * @var array The attributes which will be en/decrypted
     */
    protected $_secureAttributes;
    /**
     * @var string The salt/password to use to encrypt/decrypt the data
     */
    protected $_salt;
    /**
     * @var array The attributes which will be converted but not encrypted
     */
    protected $_insecureAttributes;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param array $secureAttributes
     *
     * @return SecureString
     */
    public function setSecureAttributes( $secureAttributes )
    {
        $this->_secureAttributes = $secureAttributes;

        return $this;
    }

    /**
     * @param array $secureAttributes
     *
     * @return SecureString
     */
    public function addSecureAttributes( $secureAttributes )
    {
        $this->_secureAttributes = array_merge( $this->_secureAttributes, $secureAttributes );

        return $this;
    }

    /**
     * @param string $secureAttribute
     *
     * @return SecureString
     */
    public function addSecureAttribute( $secureAttribute )
    {
        $this->_secureAttributes[] = $secureAttribute;

        return $this;
    }

    /**
     * @return array
     */
    public function getSecureAttributes()
    {
        return $this->_secureAttributes;
    }

    /**
     * @param string $salt
     *
     * @return SecureString
     */
    public function setSalt( $salt )
    {
        $this->_salt = $salt;

        return $this;
    }

    /**
     * @return string
     */
    public function getSalt()
    {
        return $this->_salt;
    }

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
                    if ( !isset( $_value ) )
                    {
                        $_value = '';
                    }

                    switch ( $from )
                    {
                        case true:
                            if ( $secure )
                            {
                                $_value = Hasher::decryptString( $_value, $this->_salt );
                            }
                            break;

                        case false:
                            //	Encrypt it...
                            if ( $secure )
                            {
                                $_value = Hasher::encryptString( $_value, $this->_salt );
                            }
                            break;
                    }

                    $event->sender->setAttribute( $_attribute, $_value );
                }
            }
        }
    }

    /**
     * @param \CModelEvent $event
     */
    protected function _convertTo( $event )
    {
        $this->_convertAttributes( $event, $this->_secureAttributes, false, true );
        $this->_convertAttributes( $event, $this->_insecureAttributes, false, false );
    }

    /**
     * @param \CModelEvent $event
     */
    protected function _convertFrom( $event )
    {
        $this->_convertAttributes( $event, $this->_secureAttributes, true, true );
        $this->_convertAttributes( $event, $this->_insecureAttributes, true, false );
    }

    /**
     * @param \CModelEvent $event
     */
    protected function beforeFind( $event )
    {
        $this->_convertTo( $event );
        parent::beforeFind( $event );
    }

    /**
     * Apply any formats
     *
     * @param \CModelEvent event parameter
     *
     * @return bool|void
     */
    public function beforeValidate( $event )
    {
        $this->_convertTo( $event );
        parent::beforeValidate( $event );
    }

    /**
     * @param \CModelEvent $event
     */
    protected function afterSave( $event )
    {
        $this->_convertFrom( $event );
        parent::afterSave( $event );
    }

    /**
     * @param \CModelEvent $event
     *
     * @return bool|void
     */
    public function afterFind( $event )
    {
        $this->_convertFrom( $event );
        parent::afterFind( $event );
    }

    /**
     * @param array $insecureAttributes
     *
     * @return SecureString
     */
    public function setInsecureAttributes( $insecureAttributes )
    {
        $this->_insecureAttributes = $insecureAttributes;

        return $this;
    }

    /**
     * @return array
     */
    public function getInsecureAttributes()
    {
        return $this->_insecureAttributes;
    }
}
