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
namespace DreamFactory\Platform\Resources;

use DreamFactory\Platform\Interfaces\RestResourceLike;
use DreamFactory\Platform\Interfaces\RestServiceLike;
use DreamFactory\Platform\Utility\RestData;
use Kisma\Core\Utility\Option;

/**
 * BaseUserRestResource
 * A base service resource class to handle service resources of various kinds.
 */
abstract class BaseUserRestResource extends BasePlatformRestResource
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string
     */
    const DEFAULT_SERVICE_NAME = 'user';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var int|string
     */
    protected $_resourceId = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new service
     *
     * @param RestServiceLike|RestResourceLike $consumer
     * @param array                            $settings      configuration array
     * @param array                            $resourceArray Or you can pass in through $settings['resource_array'] = array(...)
     *
     * @throws \InvalidArgumentException
     */
    public function __construct( $consumer, $settings = array(), $resourceArray = array() )
    {
        $this->_resourceArray = $resourceArray ?: Option::get( $settings, 'resource_array', array(), true );

        //	Default service name if not supplied. Should work for subclasses by defining the constant in your class
        $settings['service_name'] = $this->_serviceName ?: Option::get( $settings, 'service_name', static::DEFAULT_SERVICE_NAME, true );

        //	Default verb aliases for all system resources
        $settings['verb_aliases'] = $this->_verbAliases
            ?: array_merge(
                array(
                    static::PATCH => static::PUT,
                    static::MERGE => static::PUT,
                ),
                Option::get( $settings, 'verb_aliases', array(), true )
            );

        parent::__construct( $consumer, $settings );
    }

    /**
     * Apply the commonly used REST path members to the class
     *
     * @param string $resourcePath
     *
     * @return $this
     */
    protected function _detectResourceMembers( $resourcePath = null )
    {
        $_result = parent::_detectResourceMembers( $resourcePath );

        $this->_resourceId = Option::get( $this->_resourceArray, 1 );

        return $_result;
    }

    /**
     * Apply the commonly used REST path members to the class
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return $this
     */
    protected function _detectRequestMembers()
    {
        //  Override - don't call parent class here
        $this->_requestPayload = Option::clean( RestData::getPostedData( true, true ) );

        return $this;
    }

    /**
     * @param int $resourceId
     *
     * @return BaseSystemRestResource
     */
    public function setResourceId( $resourceId )
    {
        $this->_resourceId = $resourceId;

        return $this;
    }

    /**
     * @return int
     */
    public function getResourceId()
    {
        return $this->_resourceId;
    }
}