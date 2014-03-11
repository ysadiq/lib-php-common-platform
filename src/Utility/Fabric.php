<?php
/**
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
namespace DreamFactory\Platform\Utility;

use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\DateTime;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\SeedUtility;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Fabric.php
 * The configuration file for fabric-hosted DSPs
 */
class Fabric extends SeedUtility
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const DEFAULT_AUTH_ENDPOINT = 'http://cerberus.fabric.dreamfactory.com/api/instance/credentials';
	/**
	 * @var string
	 */
	const DEFAULT_PROVIDER_ENDPOINT = 'http://oasys.cloud.dreamfactory.com/oauth/providerCredentials';
	/**
	 * @var string
	 */
	const DSP_DB_CONFIG_FILE_NAME_PATTERN = '%%INSTANCE_NAME%%.database.config.php';
	/**
	 * @var string
	 */
	const DSP_DEFAULT_SUBDOMAIN = '.cloud.dreamfactory.com';
	/**
	 * @var string My favorite cookie
	 */
	const FigNewton = 'dsp.blob';
	/**
	 * @var string My favorite cookie
	 */
	const PrivateFigNewton = 'dsp.private';
	/**
	 * @var string
	 */
	const BaseStorage = '/data/storage';
	/**
	 * @var string
	 */
	const FABRIC_MARKER = '/var/www/.fabric_hosted';
	/**
	 * @var string
	 */
	const MAINTENANCE_MARKER = '/var/www/.fabric_maintenance';
	/**
	 * @var string
	 */
	const MAINTENANCE_URI = '/web/maintenance';
	/**
	 * @var int
	 */
	const EXPIRATION_THRESHOLD = 30;
	/**
	 * @var string
	 */
	const DEFAULT_DOC_ROOT = '/var/www/launchpad/web';

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string
	 */
	public static function getHostName()
	{
		return FilterInput::server( 'HTTP_HOST', gethostname() );
	}

	/**
	 * @return bool True if this DSP is fabric-hosted
	 */
	public static function fabricHosted()
	{
		return static::DEFAULT_DOC_ROOT == FilterInput::server( 'DOCUMENT_ROOT' ) && file_exists( static::FABRIC_MARKER );
	}

	/**
	 * @param bool $returnHost If true and the host is private, the host name is returned instead of TRUE. FALSE is still returned if false
	 *
	 * @return bool
	 */
	public static function hostedPrivatePlatform( $returnHost = false )
	{
		/**
		 * Add host names to this list to white-list...
		 */
		static $_allowedHosts = array(
			'launchpad-dev.dreamfactory.com',
			'launchpad-demo.dreamfactory.com',
			'next.cloud.dreamfactory.com',
		);

		$_host = static::getHostName();

		return in_array( $_host, $_allowedHosts ) ? ( $returnHost ? $_host : true ) : false;
	}

	/**
	 * @throws \CHttpException
	 * @return array|mixed
	 * @throws RuntimeException
	 * @throws CHttpException
	 */
	public static function initialize()
	{
		global $_dbName, $_instance, $_dspName;

		//	If this isn't a cloud request, bail
		$_host = static::getHostName();

		if ( !static::hostedPrivatePlatform() && false === strpos( $_host, static::DSP_DEFAULT_SUBDOMAIN ) )
		{
			static::_errorLog( 'Attempt to access system from non-provisioned host: ' . $_host );
			throw new \CHttpException( HttpResponse::Forbidden, 'You are not authorized to access this system you cheeky devil you. (' . $_host . ').' );
		}

		//	What has it gots in its pocketses? Cookies first, then session
		$_privateKey = FilterInput::cookie( static::PrivateFigNewton, FilterInput::session( static::PrivateFigNewton ), \Kisma::get( 'platform.user_key' ) );
		$_dspName = str_ireplace( static::DSP_DEFAULT_SUBDOMAIN, null, $_host );

		$_dbConfigFileName = str_ireplace(
			'%%INSTANCE_NAME%%',
			$_dspName,
			static::DSP_DB_CONFIG_FILE_NAME_PATTERN
		);

		//	Try and get them from server...
		if ( false === ( list( $_settings, $_instance ) = static::_checkCache( $_host ) ) )
		{
			//	Otherwise we need to build it.
			$_parts = explode( '.', $_host );
			$_dbName = str_replace( '-', '_', $_dspName = $_parts[0] );

			//	Otherwise, get the credentials from the auth server...
			$_response = Curl::get( static::DEFAULT_AUTH_ENDPOINT . '/' . $_dspName . '/database' );

			if ( HttpResponse::NotFound == Curl::getLastHttpCode() )
			{
				static::_errorLog( 'DB Credential pull failure. Redirecting to df.com: ' . $_host );
				header( 'Location: https://www.dreamfactory.com/dsp-not-found?dn=' . urlencode( $_dspName ) );
				exit();
			}

			if ( is_object(
					 $_response
				 ) && isset( $_response->details, $_response->details->code ) && HttpResponse::NotFound == $_response->details->code
			)
			{
				static::_errorLog( 'Instance "' . $_dspName . '" not found during web initialize.' );
				throw new \CHttpException( HttpResponse::NotFound, 'Instance not available.' );
			}

			if ( !$_response || !is_object( $_response ) || false == $_response->success )
			{
				static::_errorLog( 'Error connecting to authentication service: ' . print_r( $_response, true ) );
				throw new \CHttpException( HttpResponse::InternalServerError, 'Cannot connect to authentication service' );
			}

			$_instance = $_cache = $_response->details;
			$_dbName = $_instance->db_name;
			$_dspName = $_instance->instance->instance_name_text;

			$_privatePath = $_cache->private_path;
			$_privateKey = basename( dirname( $_privatePath ) );

			//	Stick this in persistent storage
			\Kisma::set(
				array(
					'dsp.credentials'              => $_cache,
					'platform.dsp_name'            => $_dspName,
					'platform.private_path'        => $_privatePath,
					'platform.storage_key'         => $_instance->storage_key,
					'platform.private_storage_key' => $_privateKey,
					'platform.db_config_file'      => $_privatePath . '/' . $_dbConfigFileName,
					'platform.db_config_file_name' => $_dbConfigFileName,
				)
			);

			/** @noinspection PhpIncludeInspection */
			//	File should be there from provisioning... If not, tenemos una problema!
			$_settings = require( $_privatePath . '/' . $_dbConfigFileName );

			if ( !empty( $_settings ) )
			{
				setcookie( static::FigNewton, $_instance->storage_key, time() + DateTime::TheEnd, '/' );
				$_settings = static::_cacheSettings( $_host, $_settings, $_instance );
			}
		}

		//	Save it for later (don't run away and let me down <== extra points if you get the reference)
		setcookie( static::PrivateFigNewton, $_privateKey, time() + DateTime::TheEnd, '/' );

		if ( !empty( $_settings ) )
		{
			return $_settings;
		}

		throw new \CHttpException( HttpResponse::BadRequest, 'Unable to find database configuration' );
	}

	/**
	 * @param string $host
	 *
	 * @return bool|mixed
	 */
	protected static function _checkCache( $host )
	{
		//	See if file is available and return it, or expire it...
		if ( file_exists( $_cacheFile = static::_cacheFileName( $host ) ) )
		{
			//	No session or expired?
			if ( Pii::isEmpty( session_id() ) || ( time() - fileatime( $_cacheFile ) ) > static::EXPIRATION_THRESHOLD )
			{
				@unlink( $_cacheFile );

				return false;
			}

			if ( false !== ( $_data = json_decode( file_get_contents( $_cacheFile ), true ) ) )
			{
				return array( $_data['settings'], $_data['instance'] );
			}
		}

		return false;
	}

	/**
	 * Writes the cache file out to disk
	 *
	 * @param string   $host
	 * @param array    $settings
	 * @param stdClass $instance
	 *
	 * @return mixed
	 */
	protected static function _cacheSettings( $host, $settings, $instance )
	{
		$_data = array(
			'settings' => $settings,
			'instance' => $instance,
		);

		file_put_contents( static::_cacheFileName( $host ), json_encode( $_data ) );

		return $settings;
	}

	/**
	 * Generates the file name for the configuration cache file
	 *
	 * @param string $host
	 *
	 * @return string
	 */
	protected static function _cacheFileName( $host )
	{
		return rtrim( sys_get_temp_dir(), '/' ) . '/.dsp-' . sha1( $host . $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * Retrieves a global login provider credential set
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	public static function getProviderCredentials( $id = null )
	{
		if ( !static::fabricHosted() && !static::hostedPrivatePlatform() )
		{
			Log::info( 'Global provider credential pull skipped: not hosted entity.' );

			return array();
		}

		//	Otherwise, get the credentials from the auth server...
		$_url = static::DEFAULT_PROVIDER_ENDPOINT . '/';

		if ( null !== $id )
		{
			$_url .= $id . '/';
		}

		$_response = Curl::get( $_url . '?oasys=' . urlencode( Pii::getParam( 'oauth.salt' ) ) );

		if ( HttpResponse::Ok != Curl::getLastHttpCode() || !$_response->success )
		{
			static::_errorLog( 'Global provider credential pull failed: ' . Curl::getLastHttpCode() . PHP_EOL . print_r( $_response, true ) );

			return array();
		}

		return Option::get( $_response, 'details', array() );
	}

	/**
	 * @param string $message
	 * @param array  $context
	 */
	protected static function _errorLog( $message, $context = array() )
	{
		Log::error( $message, $context );
	}
}

//********************************************************************************
//* Check for maintenance mode...
//********************************************************************************

if ( Fabric::MAINTENANCE_URI != Option::server( 'REQUEST_URI' ) && is_file( Fabric::FABRIC_MARKER ) && is_file( Fabric::MAINTENANCE_MARKER ) )
{
	header( 'Location: ' . Fabric::MAINTENANCE_URI );
	die();
}

