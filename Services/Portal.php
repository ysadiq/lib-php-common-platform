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
use DreamFactory\Oasys\Providers\BaseProvider;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Yii\Models\Provider;
use DreamFactory\Platform\Yii\Models\User;
use DreamFactory\Platform\Yii\Stores\ProviderUserStore;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\HttpResponse;
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
	 * @var bool If true (the default), the client will be redirected automatically to authorize
	 */
	protected $_interactive = true;
	/**
	 * @var array The known services
	 */
	protected $_serviceMap = array();
	/**
	 * @var array Alias map
	 */
	protected $_aliases = array();
	/**
	 * @var User
	 */
	protected $_portalUser = null;
	/**
	 * @var string
	 */
	protected $_parsedUrl;
	/**
	 * @var string
	 */
	protected $_requestedUrl;
	/**
	 * @var array
	 */
	protected $_uriPath;
	/**
	 * @var array
	 */
	protected $_urlParameters;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param array $settings
	 */
	public function __construct( $settings = array() )
	{
		parent::__construct( $settings );

		$this->_mapServices();
	}

	/**
	 * {@InheritDoc}
	 */
	protected function _preProcess()
	{
		parent::_preProcess();

		//	Clean up the resource path
		$this->_resourcePath = trim( str_replace( $this->_apiName, null, $this->_resourcePath ), ' /' );
		$this->_interactive = Option::getBool( $_REQUEST, 'interactive', $this->_interactive, true );
		$this->_urlParameters = $this->_parseRequest();
	}

	/**
	 * @return string
	 */
	protected function _parseRequest()
	{
		$_uri = Option::server( 'REQUEST_URI' );
		$_options = $_urlParameters = array();

		//	Parse url
		$this->_parsedUrl = \parse_url(
			$this->_requestedUrl = 'http' . ( 'on' == Option::server( 'HTTPS' ) ? 's' : null ) . '://' . Option::server( 'SERVER_NAME' ) . $_uri
		);

		//	Parse the path...
		if ( isset( $this->_parsedUrl['path'] ) )
		{
			$this->_uriPath = explode( '/', trim( $this->_parsedUrl['path'], '/' ) );

			foreach ( $this->_uriPath as $_key => $_value )
			{
				if ( false !== strpos( $_value, '=' ) )
				{
					if ( null != ( $_list = explode( '=', $_value ) ) )
					{
						$_options[$_list[0]] = $_list[1];
					}

					unset( $_options[$_key] );
				}
			}
		}

		//	Any query string? (?x=y&...)
		if ( isset( $this->_parsedUrl['query'] ) )
		{
			$_queryOptions = array();

			\parse_str( $this->_parsedUrl['query'], $_queryOptions );

			$_options = \array_merge( $_queryOptions, $_options );

			//	Remove Yii route variable
			if ( isset( $_options['r'] ) )
			{
				unset( $_options['r'] );
			}
		}

		//	load into url params
		foreach ( $_options as $_key => $_value )
		{
			if ( !isset( $_urlParameters[$_key] ) )
			{
				$_urlParameters[$_key] = $_value;
			}
		}

		//	If the inbound request is JSON data, convert to an array and merge with params
		if ( false !== stripos( Option::server( 'CONTENT_TYPE' ), 'application/json' ) && isset( $GLOBALS, $GLOBALS['HTTP_RAW_POST_DATA'] ) )
		{
			//	Merging madness!
			$_urlParameters = array_merge(
				$_urlParameters,
				json_decode( $GLOBALS['HTTP_RAW_POST_DATA'], true )
			);
		}

		//	Clean up relayed parameters
		$_params = array();

		foreach ( $_urlParameters as $_key => $_value )
		{
			if ( is_numeric( $_key ) && false !== strpos( $_value, '=' ) )
			{
				$_parts = explode( '=', $_value );

				if ( 2 == sizeof( $_parts ) )
				{
					$_params[$_parts[0]] = urldecode( $_parts[1] );
					unset( $_urlParameters[$_key] );

					$_key = $_parts[0];
				}
			}
		}

		return !empty( $_params ) ? $_params : $_urlParameters;
	}

	/**
	 * Validates that the requested portal is available
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 */
	protected function _validatePortal()
	{
		if ( in_array( $this->_resource, array_keys( $this->_aliases ) ) )
		{
			Log::debug( 'Portal alias "' . $this->_resource . '" used.' );
			$this->_resource = $this->_aliases[$this->_resource];
		}

		if ( !in_array( $this->_resource, array_keys( $this->_serviceMap ) ) )
		{
			Log::error( 'Portal service "' . $this->_resource . '" not found' );
			throw new NotFoundException(
				'Portal "' . $this->_resource . '" not found. Acceptable portals are: ' . implode( ', ', array_keys( $this->_serviceMap ) )
			);
		}

		Log::debug( 'Portal service "' . $this->_resource . '" called' );
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
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
	 * @return bool
	 */
	protected function _handleResource()
	{
		$_config = array();

		if ( empty( $this->_resource ) && $this->_action == HttpMethod::Get )
		{
			$_providers = array();

			foreach ( $this->_serviceMap as $_row )
			{
				$_providers[] = array(
					'id'            => $_row['id'],
					'api_name'      => $_row['api_name'],
					'provider_name' => $_row['provider_name'],
					'config_text'   => $_row['config_text'],
				);
			}

			return array( 'resource' => $_providers );
		}

		//	1. Validate portal
		$this->_validatePortal();

		//	2. Authorize user
		$this->_validateRequest();

		//	Load any local configuration files for this provider...
		$_configPath = \Kisma::get( 'app.config_path' ) . '/portal/' . $this->_resource . '.config.php';

		if ( file_exists( $_configPath ) )
		{
			/** @noinspection PhpIncludeInspection */
			$_config = @include( $_configPath );
		}

		$_config = array_merge(
			$_config,
			Option::getDeep(
				$this->_serviceMap,
				$this->_resource,
				'config_text',
				array()
			)
		);

		if ( $this->_interactive )
		{
			$_config['flow_type'] = Flows::CLIENT_SIDE;
		}

		$_config['payload'] = $_payload = array_merge( $this->_urlParameters, Option::clean( RestData::getPostDataAsArray() ) );

		//	2. Get provider and store
		Oasys::setStore( new ProviderUserStore( $this->_portalUser->id, $this->_serviceMap[$this->_resource]['id'], $_config ), true );

		/** @var BaseProvider $_provider */
		$_provider = Oasys::getProvider( $this->_resource, Oasys::getStore()->get() );

		//	3. Relay call
		$_resource = '/' . implode( '/', array_slice( $this->_uriPath, 3 ) );

		if ( $_provider->authorized( true ) )
		{
			/** @var ProviderUserStore $_store */
			$_store = Oasys::getStore();
			if ( null === $_store->getProviderUserId() )
			{
				$_payload = $_provider->getConfig()->getPayload();
				$_profile = $_provider->getUserData();

				if ( !empty( $_profile ) )
				{
					$_store->getProviderUserId( $_profile->getUserId() );
					$_store->sync();
				}

				$_provider->getConfig()->setPayload( $_payload );
			}

			Log::debug( 'Requesting portal resource "' . $_resource . '"' );

			try
			{
				$_response = $_provider->fetch( $_resource, $_payload, $this->_action );

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
				if ( isset( $_response, $_response['result'] ) && is_string( $_response['result'] ) &&
					 false !== stripos( $_response['content_type'], 'application/json', 0 )
				)
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
				Pii::end();
			}
		}

		throw new BadRequestException( 'The request you submitted is confusing.' );
	}

	/**
	 * @throws ForbiddenException
	 */
	protected function _validateRequest()
	{
		/** @var User $_user */
		if ( empty( $this->_currentUserId ) || null === ( $_user = User::model()->findByPk( $this->_currentUserId ) ) )
		{
			throw new ForbiddenException( 'No valid session for user.', 401 );
		}

		Log::info( 'Portal request validated: ' . $_user->email );

		$this->_portalUser = $_user;
	}

	/**
	 * Maps the services available to their appropriate namespaces.
	 * Caches to session by default.
	 */
	protected function _mapServices()
	{
		//	Service cache
		if ( null === ( $_services = Pii::getState( 'portal.services' ) ) )
		{
			$_providers = Provider::model()->findAll();

			if ( empty( $_providers ) )
			{
				return;
			}

			$_services = array();

			/** @var Provider[] $_providers */
			foreach ( $_providers as $_provider )
			{
				$_services[] = $_provider->getAttributes();
				unset( $_provider );
			}

			unset( $_providers );

			Pii::setState( 'portal.services', $_services );
//			Log::debug( 'Portal services set: ' . print_r( $_services, true ) );
		}

		//	Alias cache
		if ( null === ( $_aliases = Pii::getState( 'portal.aliases' ) ) )
		{
			$_aliases = Pii::getParam( 'portal.aliases', array() );

			if ( !empty( $this->_aliases ) )
			{
				$_aliases = array_merge(
					$_aliases,
					$this->_aliases
				);
			}

			Pii::setState( 'portal.aliases', $_aliases );
//			Log::debug( 'Portal aliases set: ' . print_r( $_aliases, true ) );
		}

		foreach ( $_services as $_service )
		{
			$this->_serviceMap[$_service['api_name']] = $_service;
		}

		$this->_aliases = $_aliases;
	}

	/**
	 * @param array $aliases
	 *
	 * @return Portal
	 */
	public function setAliases( $aliases )
	{
		$this->_aliases = $aliases;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getAliases()
	{
		return $this->_aliases;
	}

	/**
	 * @param boolean $interactive
	 *
	 * @return Portal
	 */
	public function setInteractive( $interactive )
	{
		$this->_interactive = $interactive;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getInteractive()
	{
		return $this->_interactive;
	}

	/**
	 * @return string
	 */
	public function getParsedUrl()
	{
		return $this->_parsedUrl;
	}

	/**
	 * @param \DreamFactory\Platform\Yii\Models\User $portalUser
	 *
	 * @return Portal
	 */
	public function setPortalUser( $portalUser )
	{
		$this->_portalUser = $portalUser;

		return $this;
	}

	/**
	 * @return \DreamFactory\Platform\Yii\Models\User
	 */
	public function getPortalUser()
	{
		return $this->_portalUser;
	}

	/**
	 * @return string
	 */
	public function getRequestedUrl()
	{
		return $this->_requestedUrl;
	}

	/**
	 * @return array
	 */
	public function getServiceMap()
	{
		return $this->_serviceMap;
	}

	/**
	 * @return array
	 */
	public function getUriPath()
	{
		return $this->_uriPath;
	}

	/**
	 * @return array
	 */
	public function getUrlParameters()
	{
		return $this->_urlParameters;
	}

}
