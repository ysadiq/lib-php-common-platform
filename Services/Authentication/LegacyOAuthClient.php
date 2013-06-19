<?php
/**
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
namespace DreamFactory\Platform\Services\Authentication;

use DreamFactory\Common\Services\BaseProxyService;
use DreamFactory\Platform\Exceptions\AuthenticationException;
use Kisma\Core\Interfaces\ConsumerLike;
use Kisma\Core\Interfaces\HttpMethod;

/**
 * OAuthClient
 */
class LegacyOAuthClient extends BaseProxyService implements ConsumerLike, HttpMethod
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var string
	 */
	protected $_signatureMethod = OAUTH_SIG_METHOD_HMACSHA1;
	/**
	 * @var string
	 */
	protected $_authType = OAUTH_AUTH_TYPE_AUTHORIZATION;
	/**
	 * @var \OAuth Our OAuth object
	 */
	protected $_service = null;
	/**
	 * @var array The current token
	 */
	protected $_currentToken = null;
	/**
	 * @var string
	 */
	protected $_redirectUri;
	/**
	 * @var bool
	 */
	protected $_authorized = false;
	/**
	 * @var string
	 */
	protected $_accessTokenUrl = '/oauth/access_token';
	/**
	 * @var string
	 */
	protected $_authorizeUrl = '/oauth/authorize';
	/**
	 * @var string
	 */
	protected $_requestTokenUrl = '/oauth/request_token';
	/**
	 * @var string The OAuth public key
	 */
	protected $_publicKey = null;
	/**
	 * @var string The OAuth secret key
	 */
	protected $_privateKey = null;
	/**
	 * @var string The OAuth endpoint
	 */
	protected $_endpoint = null;
	/**
	 * @var callback An optional callback for when an authorization occurs
	 */
	protected $_authorizationCallback = null;
	/**
	 * @var string
	 */
	protected $_oauthToken = null;
	/**
	 * @var string
	 */
	protected $_oauthTokenSecret = null;
	/**
	 * @var bool
	 */
	protected $_returnArrays = false;

	//********************************************************************************
	//* Constructor
	//********************************************************************************

	/**
	 * @param array $options
	 *
	 * @throws \DreamFactory\Platform\Exceptions\AuthenticationException
	 */
	public function __construct( $options = array() )
	{
		//	No oauth? No run...
		if ( !extension_loaded( 'oauth' ) )
		{
			throw new AuthenticationException( 'The PECL "oauth" extension is not loaded. Please install and/or load the oath extension.' );
		}

		parent::__construct( $this, $options );
		$this->_initializeClient( $options );
	}

	//********************************************************************************
	//* Public Methods
	//********************************************************************************

	/**                                         I
	 * Appends the current token to the authorizeUrl option
	 *
	 * @return string
	 */
	public function getAuthorizeUrl()
	{
		$_token = $this->_service->getRequestToken(
			$this->_endpoint . $this->_requestTokenUrl,
			$this->_redirectUri
		);

		return $this->_endpoint . $this->_authorizeUrl . '?oauth_token=' . $_token['oauth_token'];
	}

	/**
	 * Stores the current token in a member variable and in the user state oAuthToken
	 *
	 * @param array $token
	 *
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function storeToken( $token = array() )
	{
		$_name = spl_object_hash( $this );

		try
		{
			\Kisma::set( $_name . '_oAuthToken', $token );
			\Kisma::set( $_name . '_authorized', $this->_authorized );
			$this->_currentToken = $token;
		}
		catch ( \Exception $_ex )
		{
			throw new AuthenticationException( 'Exception while storing OAuth token: ' . $_ex->getMessage(), $_ex->getCode() );
		}
	}

	/**
	 * Loads a token from the user state oAuthToken
	 *
	 * @return bool
	 */
	public function loadToken()
	{
		$_name = spl_object_hash( $this );

		if ( null !== ( $_token = \Kisma::get( $_name . '_OAuthToken' ) ) )
		{
			$this->_currentToken = $_token;
			$this->_authorized = \Kisma::get( $_name . '_authorized', false );

			return true;
		}

		if ( null !== $this->_oauthToken && null !== $this->_oauthTokenSecret )
		{
			$this->_service->setToken( $this->_oauthToken, $this->_oauthTokenSecret );
		}

		return false;
	}

	/**
	 * Given a path, build a full url
	 *
	 * @param null|string $path
	 *
	 * @return string
	 */
	public function buildEndpoint( $path = null )
	{
		return $this->_endpoint . '/' . ltrim( $path, '/' );
	}

	//********************************************************************************
	//* Private Methods
	//********************************************************************************

	/**
	 * Initialize client
	 *
	 * @param array $options
	 *
	 * @return void
	 */
	protected function _initializeClient( $options = array() )
	{
		//	Create our object...
		$this->_service = new \OAuth( $this->_publicKey, $this->_privateKey, $this->_signatureMethod, $this->_authType );

		//	Load any tokens we have...
		$this->loadToken();

		//	Have we been authenticated?
		if ( true !== $this->_authorized )
		{
			if ( null !== ( $_token = FilterInput::request( 'oauth_token' ) ) )
			{
				$_verifier = FilterInput::request( 'oauth_verifier' );

				if ( $this->_service->setToken( $_token, $_verifier ) )
				{
					$_token = $this->_service->getAccessToken(
						$this->_endpoint . $this->_accessTokenUrl,
						null,
						$_verifier
					);

					$this->storeToken( $_token );
					$this->_authorized = true;
				}

				//	Call callback
				if ( is_callable( $this->_authorizationCallback ) )
				{
					call_user_func( $this->_authorizationCallback, $this );
				}
			}
		}
	}

	/***
	 * Fetches a protected resource using the tokens stored
	 *
	 * @param string $action
	 * @param array  $payload
	 * @param string $method
	 * @param array  $headers
	 *
	 * @throws AuthenticationException
	 * @return \stdClass
	 */
	protected function makeRequest( $action, $payload = array(), $method = self::Get, $headers = array() )
	{
		//	Default...
		$_payload = $payload;

		//	Build the url...
		$_url = $this->buildEndpoint( $action );

		//	Make the call...
		try
		{
			$_token = $this->_currentToken;

			if ( $this->_service->setToken( $_token['oauth_token'], $_token['oauth_token_secret'] ) )
			{
				if ( $this->_service->fetch( $_url, $_payload, $method, $headers ) )
				{
					//	Return results...
					return json_decode( $this->_service->getLastResponse() );
				}
			}

			//	Boo
			return false;
		}
		catch ( \Exception $_ex )
		{
			$_response = null;
			throw new AuthenticationException( 'Exception while making OAuth fetch request: ' . $_ex->getMessage(), $_ex->getCode() );
		}
	}

	/**
	 * @param string $token
	 *
	 * @return OAuthClient
	 */
	public function setOAuthToken( $token )
	{
		$this->_currentToken['oauth_token'] = $this->_oauthToken = $token;

		return $this;
	}

	/**
	 * @param string $token
	 *
	 * @return OAuthClient
	 */
	public function setOAuthTokenSecret( $token )
	{
		$this->_currentToken['oauth_token_secret'] = $this->_oauthTokenSecret = $token;

		return $this;
	}

	/**
	 * @param string $accessTokenUrl
	 *
	 * @return OAuthClient
	 */
	public function setAccessTokenUrl( $accessTokenUrl )
	{
		$this->_accessTokenUrl = $accessTokenUrl;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getAccessTokenUrl()
	{
		return $this->_accessTokenUrl;
	}

	/**
	 * @param string $authType
	 *
	 * @return OAuthClient
	 */
	public function setAuthType( $authType )
	{
		$this->_authType = $authType;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getAuthType()
	{
		return $this->_authType;
	}

	/**
	 * @param callable $authorizationCallback
	 *
	 * @return OAuthClient
	 */
	public function setAuthorizationCallback( $authorizationCallback )
	{
		$this->_authorizationCallback = $authorizationCallback;

		return $this;
	}

	/**
	 * @return callable
	 */
	public function getAuthorizationCallback()
	{
		return $this->_authorizationCallback;
	}

	/**
	 * @param boolean $authorized
	 *
	 * @return OAuthClient
	 */
	public function setAuthorized( $authorized )
	{
		$this->_authorized = $authorized;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getAuthorized()
	{
		return $this->_authorized;
	}

	/**
	 * @param array $currentToken
	 *
	 * @return OAuthClient
	 */
	public function setCurrentToken( $currentToken )
	{
		$this->_currentToken = $currentToken;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getCurrentToken()
	{
		return $this->_currentToken;
	}

	/**
	 * @param string $endpoint
	 *
	 * @return OAuthClient
	 */
	public function setEndpoint( $endpoint )
	{
		$this->_endpoint = $endpoint;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getEndpoint()
	{
		return $this->_endpoint;
	}

	/**
	 * @return string
	 */
	public function getOAuthToken()
	{
		return $this->_oauthToken;
	}

	/**
	 * @return string
	 */
	public function getOAuthTokenSecret()
	{
		return $this->_oauthTokenSecret;
	}

	/**
	 * @param string $privateKey
	 *
	 * @return OAuthClient
	 */
	public function setPrivateKey( $privateKey )
	{
		$this->_privateKey = $privateKey;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getPrivateKey()
	{
		return $this->_privateKey;
	}

	/**
	 * @param string $publicKey
	 *
	 * @return OAuthClient
	 */
	public function setPublicKey( $publicKey )
	{
		$this->_publicKey = $publicKey;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getPublicKey()
	{
		return $this->_publicKey;
	}

	/**
	 * @param string $redirectUri
	 *
	 * @return OAuthClient
	 */
	public function setRedirectUri( $redirectUri )
	{
		$this->_redirectUri = $redirectUri;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRedirectUri()
	{
		return $this->_redirectUri;
	}

	/**
	 * @param string $requestTokenUrl
	 *
	 * @return OAuthClient
	 */
	public function setRequestTokenUrl( $requestTokenUrl )
	{
		$this->_requestTokenUrl = $requestTokenUrl;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRequestTokenUrl()
	{
		return $this->_requestTokenUrl;
	}

	/**
	 * @param boolean $returnArrays
	 *
	 * @return OAuthClient
	 */
	public function setReturnArrays( $returnArrays )
	{
		$this->_returnArrays = $returnArrays;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getReturnArrays()
	{
		return $this->_returnArrays;
	}

	/**
	 * @param \OAuth $service
	 *
	 * @return OAuthClient
	 */
	public function setService( $service )
	{
		$this->_service = $service;

		return $this;
	}

	/**
	 * @return \OAuth
	 */
	public function getService()
	{
		return $this->_service;
	}

	/**
	 * @param string $signatureMethod
	 *
	 * @return OAuthClient
	 */
	public function setSignatureMethod( $signatureMethod )
	{
		$this->_signatureMethod = $signatureMethod;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSignatureMethod()
	{
		return $this->_signatureMethod;
	}
}
