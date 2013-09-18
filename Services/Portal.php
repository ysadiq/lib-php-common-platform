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
use DreamFactory\Oasys\Stores\FileSystem;
use DreamFactory\Platform\Exceptions\BadRequestException;
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
	 * @throws BadRequestException
	 * @return Provider
	 */
	protected function _validateProvider( $portalName = null )
	{
		if ( null === ( $_provider = Provider::model()->byPortal( $portalName )->find() ) )
		{
			throw new BadRequestException( 'The provider "' . $portalName . '" does not exist.' );
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
	 * Handle a service request
	 *
	 * Comes in like this:
	 *
	 *                Resource        Action
	 * /rest/portal/{service_name}/{service request string}
	 *
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @throws BadRequestException
	 * @return bool
	 */
	protected function _handleResource()
	{
		if ( empty( $this->_resource ) && $this->_action == HttpMethod::Get )
		{
			$_providers = array();

			if ( null !== ( $_models = Provider::model()->findAll() ) )
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

		//	Find service auth record
		$_providerModel = $this->_validateProvider( $this->_resource );
		$_providerId = $_providerModel->api_name;
		$_flow = FilterInput::request( 'flow', Flows::CLIENT_SIDE, FILTER_SANITIZE_NUMBER_INT );

		//	Set our store...
		Oasys::setStore( $_store = new FileSystem( $_sid = session_id() ) );

		$_config = $_providerModel->buildConfig(
			array(
				 'flow_type'    => $_flow,
				 'redirect_uri' => Curl::currentUrl( false ) . '?pid=' . $_providerId,
			),
			Pii::getState( $_providerId . '.user_config', array() )
		);

		$_provider = Oasys::getProvider( $_providerId, $_config );

		if ( $_provider->handleRequest() )
		{
			//	Pass the request on...
			try
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

				$_response = $_provider->fetch(
					$_path,
					RestData::getPostDataAsArray(),
					$this->_action,
					$this->_headers ? : array()
				);

				if ( false === $_response )
				{
					throw new InternalServerErrorException( 'Network error', $_response['code'] );
				}

				/**
				 * Results from fetch always come back like this:
				 *
				 *  array(
				 *            'result'       => the actual result of the request from the provider
				 *            'code'         => the HTTP response code of the request
				 *            'content_type' => the content type returned
				 * )
				 *
				 * If the content type is json, the 'result' has already been decoded.
				 */
				if ( is_string( $_response['result'] ) && false !== stripos( $_response['content_type'], 'application/json', 0 ) )
				{
					return json_decode( $_response['result'] );
				}

				if ( HttpResponse::Ok != $_response['code'] )
				{
					throw new RestException( $_response['code'] );
				}

				return $_response['result'];
			}
			catch ( \Exception $_ex )
			{
				Log::error( 'Portal request exception: ' . $_ex->getMessage() );

				//	No soup for you!
				header( 'Location: /?error=' . urlencode( $_ex->getMessage() ) );
				exit();
			}
		}

		//	Shouldn't really get here...
		throw new BadRequestException( 'The request you submitted is confusing.' );
	}

}
