<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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

use DreamFactory\Platform\Yii\Models\User;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\Option;

/**
 * Drupal
 * Drupal authentication
 */
class Drupal
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var string The registration marker
	 */
	const REGISTRATION_MARKER = '/.registration_complete';
	/**
	 * @var string Our registration endpoint
	 */
	const ENDPOINT = 'http://cerberus.fabric.dreamfactory.com/api/drupal';

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param string $url
	 * @param array  $payload
	 * @param array  $options
	 * @param string $method
	 *
	 * @return \stdClass|string
	 */
	protected static function _drupal( $url, array $payload = array(), array $options = array(), $method = Curl::Post )
	{
		$_url = '/' . ltrim( $url, '/' );

		if ( empty( $options ) )
		{
			$options = array();
		}

		if ( !isset( $options[CURLOPT_HTTPHEADER] ) )
		{
			$options[CURLOPT_HTTPHEADER] = array('Content-Type: application/json');
		}
		else
		{
			$options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
		}

		//	Add in a source block
		$payload['source'] = array(
			'host'    => gethostname(),
			'address' => gethostbynamel( gethostname() ),
		);

		$payload['dsp-auth-key'] = md5( microtime( true ) );

//		$payload = json_encode( $payload );

		return Curl::request( $method, static::ENDPOINT . $_url, json_encode( $payload ), $options );
	}

	/**
	 * @param string $userName
	 * @param string $password
	 *
	 * @return bool
	 */
	public static function authenticateUser( $userName, $password )
	{
		$_payload = array(
			'email'    => $userName,
			'password' => $password,
		);

		if ( false !== ( $_response = static::_drupal( 'drupalValidate', $_payload ) ) )
		{
			if ( $_response->success )
			{
				return $_response->details;
			}
		}

		return false;
	}

	/**
	 * @param User  $user
	 * @param array $paths
	 * @param array $payload
	 *
	 * @throws InternalServerErrorException
	 * @throws RestException
	 * @return bool
	 */
	public static function registerPlatform( $user, $paths, $payload = array() )
	{
		try
		{
			$_payload = array_merge(
				array(
					//	Requirements
					'user_id' => $user->id,
					'email'   => $user->email,
					'name'    => $user->display_name,
					'pass'    => $user->password,
				),
				Option::clean( $payload )
			);

			//	Re-key the attributes and settings and jam them in the payload
			foreach ( $user->getAttributes() as $_key => $_value )
			{
				$_payload['admin.' . $_key] = $_value;
			}

			foreach ( Pii::params() as $_key => $_value )
			{
				$_payload['dsp.' . $_key] = is_scalar( $_value ) ? $_value : json_encode( $_value );
			}

			if ( false !== ( $_response = static::_drupal( 'register', $_payload ) ) )
			{
				if ( $_response && $_response->success )
				{
					$_privatePath = $_marker = null;

					extract( $paths );

					//	Make directory if not there
					if ( !is_dir( $_privatePath ) && false === @mkdir( $_privatePath, 0777, true ) )
					{
						throw new InternalServerErrorException( 'Unable to create private path directory.' );
					}

					//	Get touchy
					if ( false === @file_put_contents( $_marker, null ) )
					{
						//	Kill any file there...
						@unlink( $_marker );

						throw new InternalServerErrorException( 'Error creating DSP registration marker "' . $_marker . '"' );
					}

					return true;
				}
			}

			throw new InternalServerErrorException( 'Unexpected response from registration server' );
		}
		catch ( \Exception $_ex )
		{
			Log::error( 'Exception while posting DSP registration: ' . $_ex->getMessage() );
		}

		return false;
	}

	/**
	 * @param int $userId
	 *
	 * @return stdClass|string
	 */
	public static function getUser( $userId )
	{
		$_payload = array(
			'id' => $userId,
		);

		$_response = static::_drupal( 'drupalUser', $_payload );

		return $_response->details;
	}
}
