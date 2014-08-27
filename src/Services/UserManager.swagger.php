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
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Option;

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );

$_base['apis'] = array(
    array(
        'path'        => '/{api_name}',
        'operations'  => array(
            array(
                'method'     => 'GET',
                'summary'    => 'getResources() - List resources available for user session management.',
                'nickname'   => 'getResources',
                'type'       => 'Resources',
                'notes'      => 'See listed operations for each resource available.',
                'event_name' => '{api_name}.list',
            ),
        ),
        'description' => 'Operations available for user session management.',
    ),
);

//  Load resources
$_resourcePath = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'User';

if ( false !== ( $_files = FileSystem::glob( $_resourcePath . DIRECTORY_SEPARATOR . '*.swagger.php' ) ) && !empty( $_files ) )
{
    foreach ( $_files as $_file )
    {
        $_load = array();
        $_key = strtolower( str_replace( '.swagger.php', null, $_file ) );

        /** @noinspection PhpIncludeInspection */
        $_load[$_key] = require( $_resourcePath . DIRECTORY_SEPARATOR . $_file );
        $_base['apis'] = array_merge( $_base['apis'], Option::get( $_load[$_key], 'apis', array() ) );
        $_base['models'] = array_merge( $_base['models'], Option::get( $_load[$_key], 'models', array() ) );

//        Log::debug( '    * Found ' . $_file );
        unset( $_load );
    }
}

unset( $_resourcePath, $_namespaces );

return $_base;
