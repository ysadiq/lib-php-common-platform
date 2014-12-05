<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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

use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\DbUtilities;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\RestData;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * RemoteWebSvc
 * A service to handle remote web services accessed through the REST API.
 */
class RemoteWebSvc extends BasePlatformRestService
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
     * @var array
     */
    protected $_excludedHeaders;
    /**
     * @var array
     */
    protected $_excludedParameters;
    /**
     * @var bool
     */
    protected $_cacheEnabled;
    /**
     * @var int
     */
    protected $_cacheTTL;
    /**
     * @var bool
     */
    protected $_cacheMatchBody;
    /**
     * @var string
     */
    protected $_cacheQuery;
    /**
     * @var string
     */
    protected $_query;
    /**
     * @var string
     */
    protected $_url;
    /**
     * @var array
     */
    protected $_curlOptions = array();

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Create a new RemoteWebSvc
     *
     * @param array $config configuration array
     *
     * @throws \InvalidArgumentException
     */
    public function __construct( $config )
    {
        parent::__construct( $config );

        $this->setAutoDispatch( false );

        // Validate url setup
        if ( empty( $this->_baseUrl ) )
        {
            throw new \InvalidArgumentException( 'Remote Web Service base url can not be empty.' );
        }

        $_clientExclusions = Option::get( $this->_credentials, 'client_exclusions' );
        $this->_excludedHeaders = Option::get( $_clientExclusions, 'headers', array() );
        $this->_excludedParameters = Option::get( $_clientExclusions, 'parameters', array() );

        $_cacheConfig = Option::get( $this->_credentials, 'cache_config' );
        $this->_cacheEnabled = Option::getBool( $_cacheConfig, 'enabled' );
        $this->_cacheTTL = intval( Option::get( $_cacheConfig, 'ttl', Platform::DEFAULT_CACHE_TTL ) );
        $this->_cacheMatchBody = Option::getBool( $_cacheConfig, 'match_body' );

        $this->_query = '';
        $this->_cacheQuery = '';
    }

    protected static function parseArrayParameter( &$query, &$key, $name, $value, $add_to_query = true, $add_to_key = true )
    {
        if ( is_array( $value ) )
        {
            foreach ( $value as $sub => $subValue )
            {
                static::parseArrayParameter( $query, $cache_key, $name . '[' . $sub . ']', $subValue, $add_to_query, $add_to_key );
            }
        }
        else
        {
            Session::replaceLookups( $value, true );
            $_part = urlencode( $name );
            if ( !empty( $value ) )
            {
                $_part .= '=' . urlencode( $value );
            }
            if ( $add_to_query )
            {
                if ( !empty( $query ) )
                {
                    $query .= '&';
                }
                $query .= $_part;
            }
            if ( $add_to_key )
            {
                if ( !empty( $key ) )
                {
                    $key .= '&';
                }
                $key .= $_part;
            }
        }
    }

    protected static function doesActionApply( $config, $action )
    {
        if ( null !== $_excludeActions = Option::get( $config, 'action' ) )
        {
            if ( !( is_string( $_excludeActions ) && 0 === strcasecmp( 'all', $_excludeActions ) ) )
            {
                $_excludeActions = DbUtilities::validateAsArray( $_excludeActions, ',', true, 'Exclusion action config is invalid.' );
                if ( false === array_search( $action, $_excludeActions ) )
                {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param $action
     *
     * @return string
     */
    protected static function buildParameterString( $parameters, $exclusions, $action, &$query, &$cache_key )
    {
        // inbound parameters from request to be passed on
        foreach ( $_REQUEST as $_name => $_value )
        {
            $_outbound = true;
            $_addToCacheKey = true;
            // unless excluded
            foreach ( $exclusions as $_exclusion )
            {
                if ( 0 === strcasecmp( $_name, strval( Option::get( $_exclusion, 'name' ) ) ) )
                {
                    if ( static::doesActionApply( $_exclusion, $action ) )
                    {
                        $_outbound = !Option::getBool( $_exclusion, 'outbound', true );
                        $_addToCacheKey = !Option::getBool( $_exclusion, 'cache_key', true );
                    }
                }
            }

            static::parseArrayParameter( $query, $cache_key, $_name, $_value, $_outbound, $_addToCacheKey );
        }

        // DSP additional outbound parameters
        if ( !empty( $parameters ) )
        {
            foreach ( $parameters as $_param )
            {
                if ( static::doesActionApply( $_param, $action ) )
                {
                    $_name = Option::get( $_param, 'name' );
                    $_value = Option::get( $_param, 'value' );
                    $_outbound = Option::getBool( $_param, 'outbound', true );
                    $_addToCacheKey = Option::getBool( $_param, 'cache_key', true );

                    static::parseArrayParameter( $query, $cache_key, $_name, $_value, $_outbound, $_addToCacheKey );
                }
            }
        }
    }

    /**
     * @param string $action
     * @param array  $options
     *
     * @return array
     */
    protected function addHeaders( $action, $options = array() )
    {
        if ( !empty( $this->_headers ) )
        {
            foreach ( $this->_headers as $_header )
            {
                if ( static::doesActionApply( $_header, $action ) )
                {
                    $_name = Option::get( $_header, 'name' );
                    $_value = Option::get( $_header, 'value' );
                    Session::replaceLookups( $_value, true );

                    if ( null === Option::get( $options, CURLOPT_HTTPHEADER ) )
                    {
                        $options[CURLOPT_HTTPHEADER] = array();
                    }

                    $options[CURLOPT_HTTPHEADER][] = $_name . ': ' . $_value;
                }
            }
        }

        return $options;
    }

    /**
     * A chance to pre-process the data.
     *
     * @return mixed|void
     */
    protected function _preProcess()
    {
        parent::_preProcess();

        $this->checkPermission( $this->_action, $this->_apiName );

        //  set outbound parameters
        $this->buildParameterString( $this->_parameters, $this->_excludedParameters, $this->_action, $this->_query, $this->_cacheQuery );

        //	set additional headers
        $this->_curlOptions = $this->addHeaders( $this->_action, $this->_curlOptions );
    }

    /**
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @return bool
     */
    protected function _handleResource()
    {
        $_data = RestData::getPostedData() ?: array();

        $_resource = ( !empty( $this->_resourcePath ) ? '/' . ltrim( $this->_resourcePath, '/' ) : null );
        $this->_url = rtrim( $this->_baseUrl, '/' ) . $_resource;

        if ( !empty( $this->_query ) )
        {
            $_splicer = ( false === strpos( $this->_baseUrl, '?' ) ) ? '?' : '&';
            $this->_url .= $_splicer . $this->_query;
        }

        // build cache_key
        $_cacheKey = $this->_action . ':' . $this->_apiName . $_resource;
        if ( !empty( $this->_cacheQuery ) )
        {
            $_splicer = ( false === strpos( $_cacheKey, '?' ) ) ? '?' : '&';
            $_cacheKey .= $_splicer . $this->_cacheQuery;
        }

        if ( $this->_cacheEnabled )
        {
            switch ( $this->_action )
            {
                case static::GET:
                    if ( null !== $_result = Platform::storeGet( $_cacheKey ) )
                    {
                        return $_result;
                    }
                    break;
            }
        }

        Log::debug( 'Outbound HTTP request: ' . $this->_action . ': ' . $this->_url );

        $_result = Curl::request(
            $this->_action,
            $this->_url,
            $_data,
            $this->_curlOptions
        );

        if ( false === $_result )
        {
            $_error = Curl::getError();
            throw new RestException( Option::get( $_error, 'code', 500 ), Option::get( $_error, 'message' ) );
        }

        if ( $this->_cacheEnabled )
        {
            switch ( $this->_action )
            {
                case static::GET:
                    Platform::storeSet( $_cacheKey, $_result, $this->_cacheTTL );
                    break;
            }
        }

        return $_result;
    }

    /**
     * @param string $baseUrl
     *
     * @return RemoteWebSvc
     */
    public function setBaseUrl( $baseUrl )
    {
        $this->_baseUrl = $baseUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->_baseUrl;
    }

    /**
     * @param array $credentials
     *
     * @return RemoteWebSvc
     */
    public function setCredentials( $credentials )
    {
        $this->_credentials = $credentials;

        return $this;
    }

    /**
     * @return array
     */
    public function getCredentials()
    {
        return $this->_credentials;
    }

    /**
     * @param array $headers
     *
     * @return RemoteWebSvc
     */
    public function setHeaders( $headers )
    {
        $this->_headers = $headers;

        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * @param array $parameters
     *
     * @return RemoteWebSvc
     */
    public function setParameters( $parameters )
    {
        $this->_parameters = $parameters;

        return $this;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->_parameters;
    }
}
