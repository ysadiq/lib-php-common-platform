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

use DreamFactory\Oasys\Enums\Flows;
use DreamFactory\Oasys\Oasys;
use DreamFactory\Oasys\Providers\BaseOAuthProvider;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Services\Portal\BasePortalClient;
use DreamFactory\Platform\Services\Portal\OAuthResource;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Yii\Models\Provider;
use DreamFactory\Platform\Yii\Models\ProviderUser;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\FilterInput;
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
	 * @var BaseOAuthProvider
	 */
	protected $_client;
	/**
	 * @var bool
	 */
	protected $_interactive = false;
	/**
	 * @var array The parameters we don't want to proxy
	 */
	protected $_ignoredParameters
		= array(
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
		$this->_interactive = Option::getBool( $_REQUEST, 'interactive', false, true );
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
	 * @param string $portalName
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 *
	 * @return Provider
	 */
	protected function _validateProvider( $portalName = null )
	{
		if ( null === ( $_provider = Provider::model()->byPortal( $portalName )->find() ) )
		{
			throw new NotFoundException( 'Invalid portal' );
		}

		return $_provider;
	}

	/**
	 * @param string $portalName
	 *
	 * @return ProviderUser
	 */
	protected function _getAuthorization( $portalName )
	{
		return ProviderUser::model()->byUserPortal( $this->_currentUserId, $portalName )->find();
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
			throw new InternalServerErrorException( 'Error registering authorization request.' );
		}

		if ( !$_result->success || !$_result->details )
		{
			throw new InternalServerErrorException( 'Error registering authorization request: ' . print_r( $_result, true ) );
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

		if ( null === ( $_account = ProviderUser::model()->byUserPortal( $this->_currentUserId, $providerId )->find() ) )
		{
			$_account = new ProviderUser();
			$_account->user_id = $this->_currentUserId;
			$_account->provider_id = $providerId;
			$_account->account_type = ProviderUserTypes::INDIVIDUAL_USER;
		}

		$_data = $_account->auth_text;

		if ( empty( $_data ) )
		{
			$_data = array();
		}

		if ( !isset( $_data[$providerId] ) )
		{
			$_data[$providerId] = array();
		}

		$_data[$providerId]['registered_auth_token'] = $_result->details->token;

		$_account->auth_text = $_data;
		$_account->save();

		return $_redirectUri;
	}

	/**
	 * @param string $state
	 * @param string $portalName
	 *
	 * @return string
	 */
	protected function _checkPriorAuthorization( $state, $portalName )
	{
		//	See if there's an entry in the service auth table...
		$_account = $this->_getAuthorization( $portalName );

		if ( empty( $_account ) )
		{
			return false;
		}

		$_data = $_account->auth_text;

		return Option::getDeep( $_data, $portalName, 'register_auth_token' );
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
		if ( empty( $this->_resource ) && $this->_action == HttpMethod::Get )
		{
			$_providers = array();

			if ( null !== ( $_models = Provider::model()->findAll( array( 'select' => 'id,api_name,provider_name' ) ) ) )
			{
				/** @var Provider $_row */
				foreach ( $_models as $_row )
				{
					$_providers[] = array(
						'id'            => $_row->id,
						'api_name'      => $_row->api_name,
						'provider_name' => $_row->provider_name,
						'config_text'   => $_row->config_text
					);
				}
			}

			return array( 'resource' => $_providers );
		}

		$_host = \Kisma::get( 'app.host_name' );

		//	Find service auth record
		$_provider = $this->_validateProvider( $this->_resource );
		$_providerId = $_provider->api_name;

		//	Build a config...
		$_baseConfig = array(
			'flow_type' => Flows::CLIENT_SIDE,
		);

		$_stateConfig = array();

		if ( null !== ( $_json = Pii::getState( $_providerId . '.config' ) ) )
		{
			$_stateConfig = json_decode( $_json, true );
			unset( $_json );
		}

		$_fullConfig = array_merge(
			$_provider->config_text,
			$_baseConfig,
			$_stateConfig
		);

		$this->_client = Oasys::getProvider( $_providerId, $_fullConfig );
		$this->_client->setInteractive( $this->_interactive );

		$_state = sha1( $this->_currentUserId . '_' . $this->_resource . '_' . $this->_client->getClientId() );
		$this->_client->setPayload( array( 'state' => $_state ) );

		$_token = $this->_checkPriorAuthorization( $_state, $_providerId );

		if ( !empty( $_token ) )
		{
			$this->_client->setAccessToken( $_token );
		}
		else
		{
			if ( !$this->_client->authorized() )
			{
				$_config
					= array(
					'api_name'               => $_provider,
					'user_id'                => $this->_currentUserId,
					'host_name'              => $_host,
					'client'                 => serialize( $this->_client ),
					'resource'               => $this->_resourcePath,
					'authorize_redirect_uri' => 'http://' . Option::server( 'HTTP_HOST', $_host ) . Option::server( 'REQUEST_URI', '/' ),
				);

				if ( false !== ( $_redirectUri = $this->_registerAuthorization( $_state, $_config, $_provider->id ) ) )
				{
					$this->_client->getConfig()->setRedirectUri( $_redirectUri );
				}

				return $this->_client->authorized( true );
			}
		}

		if ( $this->_client->authorized( true ) )
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
