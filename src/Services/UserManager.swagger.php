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
use Kisma\Core\Utility\Option;

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );

$_custom = require( __DIR__ . '/../Resources/User/CustomSettings.swagger.php' );
$_device = require( __DIR__ . '/../Resources/User/Device.swagger.php' );
$_password = require( __DIR__ . '/../Resources/User/Password.swagger.php' );
$_profile = require( __DIR__ . '/../Resources/User/Profile.swagger.php' );
$_register = require( __DIR__ . '/../Resources/User/Register.swagger.php' );
$_session = require( __DIR__ . '/../Resources/User/Session.swagger.php' );

$_base['apis'] = array_merge(
    array(
        array(
            'path'        => '/{api_name}',
            'operations'  => array(
                0 => array(
                    'method'     => 'GET',
                    'summary'    => 'getResources() - List resources available for user session management.',
                    'nickname'   => 'getResources',
                    'type'       => 'Resources',
                    'notes'      => 'See listed operations for each resource available.',
                    'event_name' => 'user.resources.list',
                ),
            ),
            'description' => 'Operations available for user session management.',
        ),
    ),
    Option::get( $_custom, 'apis' ),
    Option::get( $_device, 'apis' ),
    Option::get( $_password, 'apis' ),
    Option::get( $_profile, 'apis' ),
    Option::get( $_register, 'apis' ),
    Option::get( $_session, 'apis' )
);

$_base['models'] = array_merge(
    Option::get( $_base, 'models' ),
    Option::get( $_custom, 'models' ),
    Option::get( $_device, 'models' ),
    Option::get( $_password, 'models' ),
    Option::get( $_profile, 'models' ),
    Option::get( $_register, 'models' ),
    Option::get( $_session, 'models' )
);

return $_base;