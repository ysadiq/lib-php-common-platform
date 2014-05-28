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
namespace DreamFactory\Platform\Scripting;

use DreamFactory\Platform\Resources\System\Config;
use DreamFactory\Platform\Resources\System\User;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Platform\Utility\Platform;
use Jeremeamia\SuperClosure\SerializableClosure;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Converts a Swagger configuration file into an API object consumable by server-side scripts
 */
class SwaggerParser
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string The Swagger cache
     */
    protected $_cachePath;

    /**
     * @param string $swaggerPath If specified, used as Swagger base path
     */
    public function __construct( $swaggerPath = null )
    {
        $swaggerPath = $swaggerPath ? : Platform::getSwaggerPath();

        $this->_cachePath = $swaggerPath . '/cache';
    }

    /**
     * Builds a tree of the API in HTML
     *
     * @param bool $returnHtml
     *
     * @return array|string
     */
    public static function apiTree( $returnHtml = true )
    {
        $_apiObject = json_decode( json_encode( static::getApiObject(), JSON_UNESCAPED_SLASHES ), true );

        if ( !$returnHtml )
        {
            return $_apiObject;
        }

        $_html = null;

        ksort( $_apiObject );

        foreach ( $_apiObject as $_service => $_operations )
        {
            ksort( $_operations );

            $_html .= '<li class="list-header">' . $_service . '</li><ul>';

            foreach ( array_keys( $_operations ) as $_operation )
            {
                $_html .= '<li>' . $_service . '.' . $_operation . '</li>';
            }

            $_html .= '</ul>';
        }

        $_html = '<ul>' . $_html . '</ul>';

        return $_html;
    }

    /**
     * Convenience method to instantiate and return an API object
     *
     * @param bool $force If true, bust cache and rebuild
     *
     * @return \stdClass
     */
    public static function getApiObject( $force = false )
    {
        $_parser = new static();

        return $_parser->buildApi( $force );
    }

    /**
     * Reads the Swagger configuration and rebuilds the server-side scripting API
     *
     * @param bool $force If true, rebuild regardless of cached state
     *
     * @return \stdClass
     */
    public function buildApi( $force = false )
    {
        $_apiObject = Platform::storeGet( 'scripting.swagger_api', null, false, 60 );

        if ( !$force && !empty( $_apiObject ) )
        {
            return $_apiObject;
        }

        if ( false === ( $_base = $this->_loadCacheFile() ) )
        {
            return false;
        }

        $_apiObject = new \stdClass();

        if ( isset( $_base['apis'] ) )
        {
            foreach ( $_base['apis'] as $_service )
            {
                $_apiObject->{$_resourcePath} = $this->_buildServiceApi( $_service, $_resourcePath );
            }
        }
        else
        {
            Log::error( 'Error parsing swagger, no APIs defined: ' . print_r( $_base, true ) );
        }

        //	Store it
        Platform::storeSet( 'scripting.swagger_api', $_apiObject, 60 );

        return $_apiObject;
    }

    /**
     * @param array  $service
     * @param string $resourcePath Will return the resource_path of the parsed service
     *
     * @return \stdClass
     */
    protected function _buildServiceApi( $service, &$resourcePath )
    {
        $_path = str_replace( '/', null, $service['path'] );

        if ( false === ( $_cacheFile = $this->_loadCacheFile( $_path ) ) )
        {
            return false;
        }

        if ( false !== ( strpos( $resourcePath = Option::get( $_cacheFile, 'resourcePath', $_path ), '/', 0 ) ) )
        {
            $resourcePath = ltrim( $resourcePath, '/' );
        }

        return $this->_buildServiceOperations( $_cacheFile['apis'] );
    }

    /**
     * Parses the operations of a service
     *
     * @param array $serviceApis The list of APIs with operations to parse
     *
     * @return \stdClass
     */
    protected function _buildServiceOperations( array $serviceApis = array() )
    {
        $_service = new \stdClass();

        foreach ( $serviceApis as $_api )
        {
            if ( !isset( $_api['operations'] ) )
            {
                continue;
            }

            foreach ( $_api['operations'] as $_operation )
            {
                if ( !isset( $_operation['nickname'] ) )
                {
                    continue;
                }

                $_service->{$_operation['nickname']} = new SerializableClosure(
                    function ( $payload = null ) use ( $_operation, $_api )
                    {
                        return ScriptEngine::inlineRequest(
                            $_operation['method'],
                            $_operation['nickname'],
                            ltrim( $_api['path'], '/' ),
                            $payload
                        );
                    }
                );
            }
        }

        return $_service;
    }

    /**
     * Loads and returns the Swagger cache
     *
     * @param string $cacheFile The name of the cache to load, or null for the base
     *
     * @return bool
     */
    protected function _loadCacheFile( $cacheFile = null )
    {
        $_json = null;
        $_file = $this->_cachePath . ( '/' . $cacheFile . '.json' ? : SwaggerManager::SWAGGER_CACHE_FILE );

        if ( !file_exists( $_file ) )
        {
            SwaggerManager::getSwagger();
        }

        if ( false === ( $_json = @file_get_contents( $_file ) || empty( $_json ) ) )
        {
            Log::error( 'Unable to open Swagger cache file: ' . $_file );
        }

        $_cache = json_decode( $_json, true );

        if ( empty( $_cache ) || JSON_ERROR_NONE !== json_last_error() )
        {
            Log::error( 'No Swagger cache or invalid JSON detected.' );

            return false;
        }

        return $_cache;
    }

}
