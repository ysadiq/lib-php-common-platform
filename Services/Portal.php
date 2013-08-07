<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Enums\ServiceAccountTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Services\Portal\BasePortalClient;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Yii\Models\AccountProvider;
use DreamFactory\Platform\Yii\Models\ServiceAccount;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Portal
 * A service to that proxies remote web service requests
 */
class Portal extends BaseSystemRestService
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var string
	 */
	protected $_baseUrl;
	/**
	 * @var array
	 */
	protected $_credentials;
	/**
	 * @var array
	 */
	protected $_headers;
	/**
	 * @var array
	 */
	protected $_parameters;
	/**
	 * @var BasePortalClient
	 */
	protected $_client;
	/**
	 * @var array The parameters we don't want to proxy
	 */
	protected $_ignoredParameters = array(
		'_', // timestamp added by jquery
		'app_name', // app_name required by our api
		'method', // method option for our api
		'format',
		'path',
	);

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * {@InheritDoc}
	 */
	protected function _preProcess()
	{
		parent::_preProcess();

		//	Clean up the resource path
		$this->_resourcePath = trim( str_replace( $this->_apiName, null, $this->_resourcePath ), ' /' );
	}

	/**
	 * @param string $action
	 *
	 * @return string
	 */
	protected function buildParameterString( $action )
	{
		$_query = null;
		$_params = array();

		foreach ( $_REQUEST as $_key => $_value )
		{
			if ( !in_array( strtolower( $_key ), $this->_ignoredParameters ) )
			{
				$_params[$_key] = $_value;
			}
		}

		foreach ( Option::clean( $this->_parameters ) as $_parameter )
		{
			$_paramAction = strtolower( Option::get( $_parameter, 'action' ) );

			if ( 'all' != $_paramAction && $action == $_paramAction )
			{
				continue;
			}

			$_params[Option::get( $_parameter, 'name' )] = Option::get( $_parameter, 'value' );
		}

		return empty( $_params ) ? null : http_build_query( $_params );
	}

	/**
	 * @param string $providerName
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 *
	 * @return ServiceAccount
	 */
	protected function _validateProvider( $providerName = null )
	{
		if ( null === ( $_provider = ResourceStore::model( 'account_provider' )->byApiName( $providerName ? : $this->_resource )->find() ) )
		{
			throw new NotFoundException( 'Invalid account provider' );
		}

		return $_provider;
	}

	/**
	 * @param AccountProvider $provider
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return BaseClient
	 */
	protected function _createProviderClient( $provider )
	{
		if ( !class_exists( $provider->handler_class, false ) && !\Kisma::get( 'app.autoloader' )->loadClass( $provider->handler_class ) )
		{
			Log::error( 'Cannot find handler class: ' . $provider->handler_class );
		}

		$_mirror = new \ReflectionClass( $provider->handler_class );

		return $_mirror->newInstance( $provider->provider_options );
	}

	/**
	 * @param int $providerId
	 *
	 * @return ServiceAccount
	 */
	protected function _getAuthorization( $providerId )
	{
		return ResourceStore::model( 'service_account' )->byUserService( $this->_currentUserId, $providerId )->find();
	}

	/**
	 * @param string $state
	 * @param array  $config
	 * @param int    $providerId
	 *
	 * @throws \DreamFactory\Platform\Exceptions\RestException
	 * @return string
	 */
	protected function _registerAuthorization( $state, $config, $providerId )
	{
		$_payload = array(
			'state'  => $state,
			'config' => json_encode( $config ),
		);

		$_endpoint = Pii::getParam( 'cloud.endpoint' ) . '/oauth/register';
		$_redirectUri = Pii::getParam( 'cloud.endpoint' ) . '/oauth/authorize';

		$_result = Curl::post( $_endpoint, $_payload );

		if ( false === $_result || !is_object( $_result ) )
		{
			throw new RestException( 'Error registering authorization request.', HttpResponse::InternalServerError );
		}

		if ( !$_result->success || !$_result->details )
		{
			throw new RestException( 'Error registering authorization request.', HttpResponse::InternalServerError );
		}

		Log::info( 'Registering auth request: ' . $state );

		$_endpoint = Pii::getParam( 'cloud.endpoint' ) . '/oauth/register?state=' . $state;
		$_result = Curl::get( $_endpoint );

		if ( false === $_result || !is_object( $_result ) )
		{
			Log::error( 'Error checking authorization request.', HttpResponse::InternalServerError );

			return false;
		}

		if ( !$_result->success || !$_result->details )
		{
			return false;
		}

		if ( null === ( $_account = ServiceAccount::model()->byUserService( $this->_currentUserId, $providerId )->find() ) )
		{
			$_account = new ServiceAccount();
			$_account->user_id = $this->_currentUserId;
			$_account->provider_id = $providerId;
			$_account->account_type = ServiceAccountTypes::INDIVIDUAL_USER;
		}

		$_account->auth_text = $_result->details->token;
		$_account->save();

		return $_redirectUri;
	}

	/**
	 * @param string $state
	 * @param int    $providerId
	 *
	 * @return string
	 */
	protected function _checkPriorAuthorization( $state, $providerId )
	{
		//	See if there's an entry in the service auth table...
		$_account = $this->_getAuthorization( $providerId );

		if ( empty( $_account ) )
		{
			return false;
		}

		return $_account->auth_text;
	}

	/**
	 * Handle a service request
	 *
	 * Comes in like this:
	 *
	 *                Resource        Action
	 * /rest/portal/{service_name}/{service request string}
	 *
	 *
	 * @return bool
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \Exception
	 */
	protected function _handleResource()
	{
		$_host = \Kisma::get( 'app.host_name' );

		//	Find service auth record
		$_provider = $this->_validateProvider();

		$this->_client = $this->_createProviderClient( $_provider );
		$this->_client->setInteractive( false );

		$_state = $this->_currentUserId . '_' . $this->_resource . '_' . $this->_client->getClientId();
		$_token = $this->_checkPriorAuthorization( $_state, $_provider->id );

		if ( false !== $_token )
		{
			$this->_client->setAccessToken( $_token );
		}
		else
		{
			if ( !$this->_client->authorized( false ) )
			{
				$_config =
					array(
						'provider_id'            => $_provider->id,
						'user_id'                => $this->_currentUserId,
						'host_name'              => $_host,
						'client'                 => serialize( $this->_client ),
						'resource'               => $this->_resourcePath,
						'authorize_redirect_uri' => 'http://' . Option::server( 'HTTP_HOST', $_host ) . Option::server( 'REQUEST_URI', '/' ),
					);

				if ( false !== ( $_redirectUri = $this->_registerAuthorization( $_state, $_config, $_provider->id ) ) )
				{
					$this->_client->setRedirectUri( $_redirectUri );
				}

				if ( !$this->_client->getInteractive() )
				{
					return array( 'redirect_uri' => $this->_client->getAuthorizationUrl( array( 'state' => $_state ) ) );
				}
			}
		}

		if ( $this->_client->authorized( true, array( 'state' => $_state ) ) )
		{
			//	Recreate the request...
			$_params = $this->_resourceArray;

			//	Shift off the service name
			array_shift( $_params );
			$_path = '/' . implode( '/', $_params );

			if ( null !== ( $_queryString = $this->buildParameterString( $this->_action ) ) )
			{
				$_path .= '?' . $_queryString;
			}

			$_response = $this->_client->fetch(
				$_path,
				RestData::getPostDataAsArray(),
				$this->_action,
				$this->_headers ? : array()
			);

			if ( false === $_response )
			{
				throw new InternalServerErrorException( 'Network error', $_response['code'] );
			}

			if ( false !== stripos( $_response['content_type'], 'application/json', 0 ) )
			{
				return json_decode( $_response['result'] );
			}

			return $_response['result'];
		}
	}
}
