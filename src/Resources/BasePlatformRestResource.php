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

use DreamFactory\Platform\Enums\ResponseFormats;
use DreamFactory\Platform\Events\Enums\ResourceServiceEvents;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Interfaces\RestResourceLike;
use DreamFactory\Platform\Interfaces\RestServiceLike;
use DreamFactory\Platform\Services\BasePlatformRestService;
use Kisma\Core\Utility\Option;

/**
 * BasePlatformRestResource
 * A base service resource class to handle service resources of various kinds.
 */
abstract class BasePlatformRestResource extends BasePlatformRestService implements RestResourceLike
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var string
     */
    const DEFAULT_PASSTHRU_CLASS = 'DreamFactory\\Platform\\Utility\\ResourceStore';
    /**
     * @var string Our event namespace
     */
    const EVENT_NAMESPACE = '{resource}.';

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var RestServiceLike
     */
    protected $_consumer;
    /**
     * @var string The name of this service
     */
    protected $_serviceName;
    /**
     * @var int The way to format the response data, not the envelope.
     */
    protected $_responseFormat = ResponseFormats::RAW;
    /**
     * @var string The class to pass to from __callStatic()
     */
    protected static $_passthruClass = self::DEFAULT_PASSTHRU_CLASS;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Create a new service
     *
     * @param RestServiceLike|RestResourceLike $consumer
     * @param array                            $settings configuration array
     *
     * @throws \InvalidArgumentException
     */
    public function __construct( $consumer, $settings = array() )
    {
        $this->_consumer = $consumer;
        $this->_serviceName = $this->_serviceName ? : Option::get( $settings, 'service_name', null, true );

        if ( empty( $this->_serviceName ) )
        {
            throw new \InvalidArgumentException( 'You must supply a value for "service_name".' );
        }

        parent::__construct( $settings );
    }

    /**
     * @param string     $resource
     * @param string     $action
     * @param string|int $output_format
     *
     * @throws \InvalidArgumentException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\MisconfigurationException
     * @return mixed
     */
    public function processRequest( $resource = null, $action = self::GET, $output_format = null )
    {
        $this->_setAction( $action );

        //	Require app name for security check
        $this->_detectAppName();
        $this->_detectResourceMembers( $resource );
        $this->_detectResponseMembers( $output_format );

        $this->_preProcess();

        //	Inherent failure?
        if ( false === ( $this->_response = $this->_handleResource() ) )
        {
            $_message =
                $this->_action .
                ' requests' .
                ( !empty( $this->_resource ) ? ' for resource "' . $this->_resourcePath . '"' : ' without a resource' ) .
                ' are not currently supported by the "' .
                $this->_apiName .
                '" service.';

            throw new BadRequestException( $_message );
        }

        $this->_postProcess();

        return $this->_response;
    }

    /**
     * @return mixed
     */
    protected function _preProcess()
    {
        $this->trigger( ResourceServiceEvents::PRE_PROCESS );

        parent::_preProcess();
    }

    /**
     * A chance to format the response
     */
    protected function _postProcess()
    {
        $this->_formatResponse();

        parent::_postProcess();

        $this->trigger( ResourceServiceEvents::POST_PROCESS );
    }

    /**
     * Format the response if necessary
     */
    protected function _formatResponse()
    {
        //	Default implementation does nothing. Like the goggles.
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public static function __callStatic( $name, $arguments )
    {
        //	Passthru to store
        return call_user_func_array( array( static::$_passthruClass, $name ), $arguments );
    }

    /**
     * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
     *
     * @return BasePlatformRestResource
     */
    public function setConsumer( $consumer )
    {
        $this->_consumer = $consumer;

        return $this;
    }

    /**
     * @return \DreamFactory\Platform\Services\BasePlatformService
     */
    public function getConsumer()
    {
        return $this->_consumer;
    }

    /**
     * @param mixed $serviceName
     *
     * @return BasePlatformRestResource
     */
    public function setServiceName( $serviceName )
    {
        $this->_serviceName = $serviceName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getServiceName()
    {
        return $this->_serviceName;
    }

    /**
     * @param string $passthruClass
     */
    public static function setPassthruClass( $passthruClass )
    {
        self::$_passthruClass = $passthruClass;
    }

    /**
     * @return string
     */
    public static function getPassthruClass()
    {
        return self::$_passthruClass;
    }

    /**
     * @param int $responseFormat
     *
     * @return BasePlatformRestResource
     */
    public function setResponseFormat( $responseFormat )
    {
        $this->_responseFormat = $responseFormat;

        return $this;
    }

    /**
     * @return int
     */
    public function getResponseFormat()
    {
        return $this->_responseFormat;
    }
}
