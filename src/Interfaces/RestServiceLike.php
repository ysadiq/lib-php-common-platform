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
namespace DreamFactory\Platform\Interfaces;

use DreamFactory\Platform\Enums\ServiceRequestorTypes;
use Kisma\Core\Interfaces\HttpMethod;

/**
 * RestServiceLike
 *
 * @package DreamFactory\Platform\Interfaces
 */
interface RestServiceLike extends HttpMethod
{
    /**
     * @param mixed                 $resource
     * @param string                $action        Http method for request
     * @param string                $output_format Output format for request, null = native array
     * @param ServiceRequestorTypes $requestor     The requestor type making the request
     *
     * @return mixed
     */
    public function processRequest( $resource = null, $action = self::GET, $output_format = null, $requestor = ServiceRequestorTypes::API );
}
