<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
use DreamFactory\Platform\Services\SystemManager;

/**
 * @var array $_base
 */
$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );

$_base['apis'] = array(
    array(
        'path'        => '/{api_name}',
        'operations'  => array(
            0 => array(
                'method'     => 'GET',
                'summary'    => 'getResources() - List resources available for system management.',
                'nickname'   => 'getResources',
                'type'       => 'Resources',
                'notes'      => 'See listed operations for each resource available.',
                'event_name' => 'resources.list',
            ),
        ),
        'description' => 'Operations available for system management.',
    ),
);

$_base['models'] = array(
    'Metadata' => array(
        'id'         => 'Metadata',
        'properties' => array(
            'schema' => array(
                'type'        => 'Array',
                'description' => 'Array of table schema.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
            'count'  => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Record count returned for GET requests.',
            ),
        ),
    ),
);

//  Load resources
$_namespaces = array( 'DreamFactory\\Platform\\Resources\\System' );

return SystemManager::discoverAvailableResources( $_namespaces, $_base );
