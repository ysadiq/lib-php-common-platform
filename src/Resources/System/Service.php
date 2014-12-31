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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\ApiDocFormatTypes;
use DreamFactory\Platform\Enums\DataFormats;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Platform\Utility\RestResponse;
use DreamFactory\Platform\Utility\ServiceHandler;
use Kisma\Core\Utility\Option;

/**
 * Service
 * DSP system administration manager
 *
 */
class Service extends BaseSystemRestResource
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Creates a new Service
     *
     * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
     * @param array                                               $resources
     */
    public function __construct( $consumer, $resources = array() )
    {
        $_config = array(
            'service_name' => 'system',
            'name'         => 'Service',
            'api_name'     => 'service',
            'type'         => 'System',
            'type_id'      => PlatformServiceTypes::SYSTEM_SERVICE,
            'description'  => 'System service administration.',
            'is_active'    => true,
        );

        parent::__construct( $consumer, $_config, $resources );
    }

    /**
     * @param mixed $results
     */
    protected function _postProcess()
    {
        if ( static::GET != $this->_action )
        {
            // clear swagger cache upon any service changes.
            SwaggerManager::clearCache();
        }
        else
        {
            $_response = $this->_response;
            $_includeComponents = Option::getBool( $this->_requestPayload, 'include_components' );
            if ( isset( $_response['record'] ) )
            {
                $_services = Option::clean( Option::get( $_response, 'record' ) );
                foreach ( $_services as &$_item )
                {
                    if ( $_includeComponents )
                    {
                        $_item['components'] = static::_getComponents( $_item );
                    }
                    if ( isset( $_item['docs'] ) && empty( $_item['docs'] ) )
                    {
                        $_item['docs'] = static::_getDocs( $_item );
                    }
                }
                if ( $_includeComponents )
                {
                    $_all = array('id' => null, 'name' => 'All', 'components' => array('', '*'));
                    array_unshift( $_services, $_all );
                }
                $_response['record'] = $_services;
            }
            else
            {
                if ( $_includeComponents )
                {
                    $_response['components'] = static::_getComponents( $_response );
                }
                if ( isset( $_response['docs'] ) && empty( $_response['docs'] ) )
                {
                    $_response['docs'] = static::_getDocs( $_response );
                }
            }
            $this->_response = $_response;
        }

        parent::_postProcess();
    }

    protected static function _getComponents( array $item )
    {
        $_apiName = Option::get( $item, 'api_name' );
//        $_payload = array('as_access_components' => true);
//        $_result = ScriptEngine::inlineRequest( HttpMethod::GET, $_apiName, $_payload );
//        $_components = Option::clean( Option::get( $_result, 'resource' ) );

        $_REQUEST['as_access_components'] = true;
        try
        {
            $_service = ServiceHandler::getService( $_apiName );
            $_result = $_service->processRequest( null, static::GET, false );
            $_components = Option::clean( Option::get( $_result, 'resource' ) );
        }
        catch ( \Exception $_ex )
        {
            $_result = RestResponse::sendErrors( $_ex, DataFormats::PHP_ARRAY, false, false, true );
            $_components = Option::getDeep( $_result, 'error', 0 );
            $_components = Option::get( $_components, 'message' );
        }

        return ( !empty( $_components ) ) ? $_components : array('', '*');
    }

    protected static function _getDocs( array $item )
    {
        $_content = SwaggerManager::getStoredContentForService( $item );
        if ( empty( $_content ) )
        {
            $_content =
                '{"resourcePath":"/{api_name}","produces":["application/json","application/xml"],"consumes":["application/json","application/xml"],"apis":[],"models":{}}';
        }
        else
        {
            $_content = json_encode( $_content, JSON_UNESCAPED_SLASHES );
        }

        return array(array('format' => ApiDocFormatTypes::SWAGGER, 'content' => $_content));
    }
}
