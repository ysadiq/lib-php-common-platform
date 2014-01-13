<?php
use DreamFactory\Yii\Utility\Pii;

/**
 * bootstrap.php
 * Bootstrap script for Platform PHPUnit tests
 */

//	Composer
$_loader = require_once __DIR__ . '/../vendor/autoload.php';

\Kisma::set( 'app.autoloader', $_loader );

//	Testing keys
if ( file_exists( __DIR__ . '/config/keys.php' ) )
{
	/** @noinspection PhpIncludeInspection */
	require_once __DIR__ . '/config/keys.php';
}

//	Load up Yii
require_once __DIR__ . '/../vendor/dreamfactory/yii/framework/yii.php';

//	Yii debug settings
defined( 'YII_DEBUG' ) or define( 'YII_DEBUG', true );
defined( 'YII_TRACE_LEVEL' ) or define( 'YII_TRACE_LEVEL', 3 );

\Kisma::set( 'app.config', $_config = require_once __DIR__ . '/config/test.config.php' );

//	Create the application but don't run (false at the end)
DreamFactory\Yii\Utility\Pii::run(
							__DIR__,
							$_loader,
							'DreamFactory\\Platform\\Yii\\Components\\PlatformConsoleApplication',
							$_config,
							false
);
