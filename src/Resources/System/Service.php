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

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Scripting\ScriptEngine;
use DreamFactory\Platform\Services\SwaggerManager;
use Kisma\Core\Enums\HttpMethod;
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
            if ( Option::getBool( $this->_requestPayload, 'include_components' ) )
            {
                $_response = $this->_response;
                $_services = Option::get( $_response, 'record' );
                foreach ( $_services as &$_item )
                {
                    $_apiName = Option::get( $_item, 'api_name' );
                    $_payload = array('as_access_components' => true);
                    $_result = ScriptEngine::inlineRequest( HttpMethod::GET, $_apiName, $_payload );
                    $_components = Option::clean( Option::get( $_result, 'resource' ) );
                    $_item['components'] = (!empty($_components)) ? $_components : array('','*');
                }
                $_response['record'] = $_services;
                $this->_response = $_response;
            }
        }

        parent::_postProcess();
    }
}
