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
use DreamFactory\Yii\Utility\Pii;
use Jeremeamia\SuperClosure\SerializableClosure;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Converts a Swagger configuration file into an API object consumable by server-side scripts
 */
class Api
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
     * Convenience method to instantiate and return an API object
     *
     * @param bool $force If true, bust cache and rebuild
     *
     * @return \stdClass
     */
    public static function getScriptingObject( $force = false )
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
        $_apiObject = Platform::storeGet( 'scripting.swagger_api', null, false, 360 );

        if ( !$force && count( (array)$_apiObject ) )
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
                unset( $_service );
            }
        }
        else
        {
            Log::error( 'Error parsing swagger, no APIs defined: ' . print_r( $_base, true ) );
        }

        //	Store it
        Platform::storeSet( 'scripting.swagger_api', $_apiObject, 360 );

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

        if ( 'db' == $resourcePath )
        {
            $_tables = $this->_getLocalTables();
            $_services = new \stdClass();

            if ( $_tables )
            {
                foreach ( Option::get( $_tables, 'resource', array() ) as $_table )
                {
                    $_services->{$_table['name']} = $this->_buildServiceOperations( $_cacheFile['apis'], 'db/' . $_table['name'] );
                }

                $_services->db = $this->_buildServiceOperations( $_cacheFile['apis'], $resourcePath );
            }

            return $_services;
        }

        return $this->_buildServiceOperations( $_cacheFile['apis'], $resourcePath );
    }

    /**
     * Parses the operations of a service
     *
     * @param array  $serviceApis The list of APIs with operations to parse
     * @param string $resourcePath
     *
     * @return \stdClass
     */
    protected function _buildServiceOperations( array $serviceApis = array(), $resourcePath )
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
                if ( !isset( $_operation['nickname'] ) || !isset( $_operation['method'] ) || !isset( $_api['path'] ) )
                {
                    continue;
                }

                //	Function data
                $_nickname = $_operation['nickname'];

                $_arguments = array(
                    'method'   => $_operation['method'],
                    'nickname' => $_nickname,
                    'path'     => ltrim( $_api['path'], '/' ),
                );

                $_service->{$_nickname} = new SerializableClosure(
                    function ( $payload = null ) use ( $_arguments )
                    {
                        return ScriptEngine::inlineRequest(
                            $_arguments['method'],
                            $_arguments['nickname'],
                            $_arguments['path'],
                            $payload
                        );
                    }
                );

                unset( $_operation );
            }

            unset( $_api );
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
        $_file = $this->_cachePath . ( $cacheFile ? '/' . $cacheFile . '.json' : SwaggerManager::SWAGGER_CACHE_FILE );

        if ( !file_exists( $_file ) )
        {
            SwaggerManager::getSwagger();
        }

        if ( false === ( $_json = file_get_contents( $_file ) ) || empty( $_json ) )
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

    /**
     * @return array
     */
    protected function _getLocalTables()
    {
        if ( Pii::guest() )
        {
            return false;
        }

        if ( null !== ( $_tables = Platform::storeGet( 'scripting.table_cache' ) ) )
        {
            return $_tables;
        }

        try
        {
            Platform::storeSet(
                'scripting.table_cache',
                $_tables = ScriptEngine::inlineRequest( HttpMethod::GET, 'getTables', 'db', array() )
            );
        }
        catch ( \Exception $_ex )
        {

            Log::error( 'Unable to get list of tables in local database: ' . $_ex->getMessage() );

            return false;
        }
    }
}
