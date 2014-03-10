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
 * SecureJson.php
 * If attached to a model, fields are decrypted/encrypted on load/save
 */
class SecureJson extends BaseModelBehavior
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
	 * @var array The attributes which will be converted to json by not encrypted
	 */
	protected $_insecureAttributes;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param array $secureAttributes
	 *
	 * @return SecureJson
	 */
	public function setSecureAttributes( $secureAttributes )
	{
		$this->_secureAttributes = $secureAttributes;

		return $this;
	}

	/**
	 * @param array $secureAttributes
	 *
	 * @return SecureJson
	 */
	public function addSecureAttributes( $secureAttributes )
	{
		$this->_secureAttributes = array_merge( $this->_secureAttributes, $secureAttributes );

		return $this;
	}

	/**
	 * @param string $secureAttribute
	 *
	 * @return SecureJson
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
	 * @return SecureJson
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
	 * @param bool         $fromJson
	 * @param bool         $secure
	 */
	protected function _convertAttributes( $event, $attributes, $fromJson = false, $secure = true )
	{
		if ( !empty( $attributes ) && is_array( $attributes ) )
		{
			foreach ( $attributes as $_attribute )
			{
				if ( $event->sender->hasAttribute( $_attribute ) )
				{
					$_value = $event->sender->getAttribute( $_attribute );

					switch ( $fromJson )
					{
						case true:
							$_value = $this->_fromSecureJson( $_value, $secure ? null : false );
							break;

						case false:
							$_value = $this->_toSecureJson( $_value, $secure ? null : false );
							break;
					}

					if ( JSON_ERROR_NONE == json_last_error() )
					{
						$event->sender->setAttribute( $_attribute, $_value );
						continue;
					}

					$event->handled = true;
					$event->sender->addError( $_attribute, 'The column "' . $_attribute . '" is malformed or otherwise invalid.' );
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
	 * @return SecureJson
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

	/**
	 * @param mixed  $data
	 * @param string $salt The encrypting salt
	 * @param array  $defaultValue
	 *
	 * @return bool|string Returns FALSE on error if $data cannot be serialized by json_encode
	 */
	protected function _toSecureJson( $data, $salt = null, $defaultValue = array() )
	{
		//	Make sure we can serialize...
		if ( empty( $data ) )
		{
			$data = $defaultValue;
		}

		if ( is_string( $data ) )
		{
			if ( false !== ( $_decoded = json_decode( $data, true ) ) )
			{
				$data = $_decoded;
			}
		}

		if ( false === ( $_encoded = json_encode( $data ) ) )
		{
			return false;
		}

		//	Encrypt it...
		return false === $salt ? $_encoded : Hasher::encryptString( $_encoded, $salt ? : $this->_salt );
	}

	/**
	 * @param string $data         The encrypted data
	 * @param string $salt         The salt used to encrypt the data
	 * @param array  $defaultValue The value to return when $data is empty
	 * @param bool   $asArray      If false, you'll get back a \stdClass
	 *
	 * @return bool|string Returns FALSE on error if $data cannot be deserialized by json_decode
	 */
	protected function _fromSecureJson( $data, $salt = null, $defaultValue = array(), $asArray = true )
	{
		if ( empty( $data ) )
		{
			return $defaultValue;
		}

		$_workData = $data;

		if ( false !== $salt )
		{
			$_workData = Hasher::decryptString( $_workData, $salt ? : $this->_salt );
		}

		// 	Try decoding decrypted string
		//	Make sure we can deserialize...
		if ( false === ( $_decoded = json_decode( $_workData, $asArray ) ) )
		{
			return false;
		}

		if ( empty( $_decoded ) )
		{
			//	Try decoding raw string
			if ( false === ( $_decoded = json_decode( $data, $asArray ) ) )
			{
				return false;
			}
		}

		return $_decoded ? : $defaultValue;
	}
}
