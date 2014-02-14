<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
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
use DreamFactory\Yii\Utility\Pii;

/**
 * aliases.config.php
 * A single location for all your aliasing needs!
 */

//	Already loaded? we done...
if ( false !== Yii::getPathOfAlias( 'DreamFactory.Yii.*' ) )
{
	return true;
}

$_basePath = dirname( __DIR__ );
$_vendorPath = $_basePath . '/vendor';

Pii::setPathOfAlias( 'vendor', $_vendorPath );

//	lib-php-common-yii (psr-0 && psr-4 compatible)
$_libPath =
	$_vendorPath . '/dreamfactory/lib-php-common-yii' . ( is_dir( $_vendorPath . '/dreamfactory/lib-php-common-yii/src' ) ? '/src' : '/DreamFactory/Yii' );

Pii::alias( 'DreamFactory.Yii.*', $_libPath );
Pii::alias( 'DreamFactory.Yii.Components', $_libPath . '/Components' );
Pii::alias( 'DreamFactory.Yii.Behaviors', $_libPath . '/Behaviors' );
Pii::alias( 'DreamFactory.Yii.Utility', $_libPath . '/Utility' );
Pii::alias( 'DreamFactory.Yii.Logging', $_libPath . '/Logging' );

//	lib-php-common-platform (psr-0 && psr-4 compatible)
$_libPath =
	$_vendorPath .
	'/dreamfactory/lib-php-common-platform' .
	( is_dir( $_vendorPath . '/dreamfactory/lib-php-common-platform/src' ) ? '/src' : '/DreamFactory/Platform' );

Pii::alias( 'DreamFactory.Platform.*', $_libPath );
Pii::alias( 'DreamFactory.Platform.Services', $_libPath . '/Services' );
Pii::alias( 'DreamFactory.Platform.Services.Portal', $_libPath . '/Services/Portal' );
Pii::alias( 'DreamFactory.Platform.Yii.Behaviors', $_libPath . '/Yii/Behaviors' );
Pii::alias( 'DreamFactory.Platform.Yii.Models', $_libPath . '/Yii/Models' );

unset( $_libPath );

//	Vendors
Pii::alias( 'Swift', $_vendorPath . '/swiftmailer/swiftmailer/lib/classes' );
