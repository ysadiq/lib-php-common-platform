<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
namespace DreamFactory\Platform\Services;

use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Enums\InstallationTypes;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\PlatformServiceException;
use DreamFactory\Platform\Interfaces\PlatformStates;
use DreamFactory\Platform\Resources\System\CustomSettings;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\Drupal;
use DreamFactory\Platform\Utility\Fabric;
use DreamFactory\Platform\Utility\FileUtilities;
use DreamFactory\Platform\Utility\Packager;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\SqlDbUtilities;
use DreamFactory\Platform\Yii\Components\PlatformUserIdentity;
use DreamFactory\Platform\Yii\Models\App;
use DreamFactory\Platform\Yii\Models\BasePlatformSystemModel;
use DreamFactory\Platform\Yii\Models\EmailTemplate;
use DreamFactory\Platform\Yii\Models\Service;
use DreamFactory\Platform\Yii\Models\User;
use DreamFactory\Yii\Utility\Pii;
use Guzzle\Http\Client;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;

/**
 * SystemManager
 * DSP system administration manager
 *
 */
class SystemManager extends BaseSystemRestService
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const SYSTEM_TABLE_PREFIX = 'df_sys_';
	/**
	 * @var string The private CORS configuration file
	 */
	const CORS_DEFAULT_CONFIG_FILE = '/cors.config.json';
	/**
	 * @var string The url to pull for DSP tag information
	 */
	const VERSION_TAGS_URL = 'https://api.github.com/repos/dreamfactorysoftware/dsp-core/tags';

	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var string Where the configuration information is stored
	 */
	protected static $_configPath = null;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Creates a new SystemManager instance
	 *
	 */
	public function __construct( $settings = array() )
	{
		static::$_configPath = \Kisma::get( 'app.config_path' );

		parent::__construct(
			array_merge(
				array(
					 'name'        => 'System Configuration Management',
					 'api_name'    => 'system',
					 'type'        => 'System',
					 'type_id'     => PlatformServiceTypes::SYSTEM_SERVICE,
					 'description' => 'Service for system administration.',
					 'is_active'   => true,
				),
				$settings
			)
		);
	}

	// Service interface implementation

	/**
	 * @param string $old
	 * @param string $new
	 * @param bool   $useVersionCompare If true, built-in "version_compare" will be used
	 * @param null   $operator          Operator to pass to version_compare
	 *
	 * @return bool|mixed
	 */
	public static function doesDbVersionRequireUpgrade( $old, $new, $useVersionCompare = false, $operator = null )
	{
		if ( false !== $useVersionCompare )
		{
			return version_compare( $old, $new, $operator );
		}

		return ( 0 !== strcasecmp( $old, $new ) );
	}

	/**
	 * Determines the current state of the system
	 */
	public static function getSystemState()
	{
		static $_isReady = false;

		if ( !$_isReady )
		{
			if ( !Pii::getState( 'dsp.init_check_complete', false ) )
			{
				//	Refresh the schema that we just added
				$_db = Pii::db();
				$_schema = $_db->getSchema();

				Sql::setConnection( $_db->pdoInstance );

				$tables = $_schema->getTableNames();

				// if there is no config table, we have to initialize
				if ( empty( $tables ) || ( false === array_search( 'df_sys_config', $tables ) ) )
				{
					return PlatformStates::INIT_REQUIRED;
				}

				// need to check for db upgrade, based on tables or version
				$contents = file_get_contents( static::$_configPath . '/schema/system_schema.json' );

				if ( !empty( $contents ) )
				{
					$contents = DataFormat::jsonToArray( $contents );

					// check for any missing necessary tables
					$needed = Option::get( $contents, 'table', array() );

					foreach ( $needed as $table )
					{
						$name = Option::get( $table, 'name' );
						if ( !empty( $name ) && !in_array( $name, $tables ) )
						{
							return PlatformStates::SCHEMA_REQUIRED;
						}
					}

					$_version = Option::get( $contents, 'version' );
					$_oldVersion = Sql::scalar( 'SELECT db_version FROM df_sys_config ORDER BY id DESC' );

					if ( static::doesDbVersionRequireUpgrade( $_oldVersion, $_version ) )
					{
						return PlatformStates::SCHEMA_REQUIRED;
					}
				}

				Pii::setState( 'dsp.init_check_complete', true );
			}

			// Check for at least one system admin user
			if ( !static::activated() )
			{
				return PlatformStates::ADMIN_REQUIRED;
			}

			//	Need to check for the default services
			if ( 0 == Service::model()->count() )
			{
				return PlatformStates::DATA_REQUIRED;
			}
		}

		//	And redirect to welcome screen
		if ( !Pii::guest() && !Fabric::fabricHosted() && !SystemManager::registrationComplete() )
		{
			return PlatformStates::WELCOME_REQUIRED;
		}

		$_isReady = true;

		return PlatformStates::READY;
	}

	/**
	 * Configures the system.
	 *
	 * @return null
	 */
	public static function initSystem()
	{
		static::initSchema();
	}

	/**
	 * Configures the system schema.
	 *
	 * @throws \Exception
	 * @return null
	 */
	public static function initSchema()
	{
		$_db = Pii::db();

		try
		{
			$contents = file_get_contents( static::$_configPath . '/schema/system_schema.json' );

			if ( empty( $contents ) )
			{
				throw new \Exception( "Empty or no system schema file found." );
			}

			$contents = DataFormat::jsonToArray( $contents );
			$version = Option::get( $contents, 'version' );

			$command = $_db->createCommand();

			// create system tables
			$tables = Option::get( $contents, 'table' );
			if ( empty( $tables ) )
			{
				throw new \Exception( "No default system schema found." );
			}

			Log::debug( 'Checking database schema' );

			SqlDbUtilities::createTables( $_db, $tables, true, false );

			// initialize config table if not already
			try
			{
				$command->reset();
				// first time is troublesome with session user id
				$rows = $command->insert( 'df_sys_config', array( 'db_version' => $version ) );

				if ( 0 >= $rows )
				{
					Log::error( 'Exception saving database version: ' . $version );
				}
			}
			catch ( \Exception $_ex )
			{
				Log::error( 'Exception saving database version: ' . $_ex->getMessage() );
			}

			//	Any scripts to run?
			if ( null !== ( $_scripts = Option::get( $contents, 'scripts' ) ) )
			{
				if ( isset( $_scripts['install'] ) )
				{
					static::_runScript( static::$_configPath . '/schema/' . $_scripts['install'] );
				}
			}

			//	Refresh the schema that we just added
			\Yii::app()->getCache()->flush();
			$_db->getSchema()->refresh();
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * Configures the system schema.
	 *
	 * @throws \Exception
	 * @return null
	 */
	public static function upgradeSchema()
	{
		$_db = Pii::db();

		try
		{
			$contents = file_get_contents( static::$_configPath . '/schema/system_schema.json' );

			if ( empty( $contents ) )
			{
				throw new \Exception( "Empty or no system schema file found." );
			}

			$contents = DataFormat::jsonToArray( $contents );
			$version = Option::get( $contents, 'version' );

			$command = $_db->createCommand();
			$oldVersion = '';
			if ( SqlDbUtilities::doesTableExist( $_db, static::SYSTEM_TABLE_PREFIX . 'config' ) )
			{
				$command->reset();
				$oldVersion = $command->select( 'db_version' )->from( 'df_sys_config' )->queryScalar();
			}

			// create system tables
			$tables = Option::get( $contents, 'table' );
			if ( empty( $tables ) )
			{
				throw new \Exception( "No default system schema found." );
			}

			Log::debug( 'Checking database schema' );

			SqlDbUtilities::createTables( $_db, $tables, true, false );

			if ( !empty( $oldVersion ) )
			{
				// clean up old unique index, temporary for upgrade
				try
				{
					$command->reset();

					try
					{
						$command->dropIndex( 'undx_df_sys_user_username', 'df_sys_user' );
					}
					catch ( \Exception $_ex )
					{
						//	Ignore missing index error
						if ( false === stripos( $_ex->getMessage(), '1091 Can\'t drop' ) )
						{
							throw $_ex;
						}
					}

					try
					{
						$command->dropindex( 'ndx_df_sys_user_email', 'df_sys_user' );
					}
					catch ( \Exception $_ex )
					{
						//	Ignore missing index error
						if ( false === stripos( $_ex->getMessage(), '1091 Can\'t drop' ) )
						{
							throw $_ex;
						}
					}
				}
				catch ( \Exception $_ex )
				{
					Log::error( 'Exception removing prior indexes: ' . $_ex->getMessage() );
				}

				// Need upgrade path from <1.0.6 for apps
				if ( version_compare( $oldVersion, '1.0.6', '<' ) )
				{
					try
					{
						$command->reset();
						$serviceId = $command->select( 'id' )->from( 'df_sys_service' )->where( 'api_name = :name', array( ':name' => 'app' ) )->queryScalar();
						if ( false === $serviceId )
						{
							throw new \Exception( 'Could not find local file storage service id.' );
						}

						$command->reset();
						$attributes = array( 'storage_service_id' => $serviceId, 'storage_container' => 'applications' );
						$condition = 'is_url_external = :external and storage_service_id is null';
						$params = array( ':external' => 0 );
						$command->update( 'df_sys_app', $attributes, $condition, $params );
					}
					catch ( \Exception $_ex )
					{
						Log::error( 'Exception upgrading apps to 1.0.6+ version: ' . $_ex->getMessage() );
					}
				}
			}

			// initialize config table if not already
			try
			{
				$command->reset();
				if ( empty( $oldVersion ) )
				{
					// first time is troublesome with session user id
					$rows = $command->insert( 'df_sys_config', array( 'db_version' => $version ) );
				}
				else
				{
					$rows = $command->update( 'df_sys_config', array( 'db_version' => $version ) );
				}

				if ( 0 >= $rows )
				{
					if ( $oldVersion != $version )
					{
						throw new \Exception( "old_version: $oldVersion new_version: $version" );
					}
				}
			}
			catch ( \Exception $_ex )
			{
				Log::error( 'Exception saving database version: ' . $_ex->getMessage() );
			}

			//	Any scripts to run?
			if ( null !== ( $_scripts = Option::get( $contents, 'scripts' ) ) )
			{
				if ( isset( $_scripts['update'] ) )
				{
					static::_runScript( static::$_configPath . '/schema/' . $_scripts['update'] );
				}
			}

			//	Refresh the schema that we just added
			\Yii::app()->getCache()->flush();
			$_db->getSchema()->refresh();
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}

		// clear out swagger cache, easiest place to catch it
		SwaggerManager::clearCache();
	}

	/**
	 * @param string $script
	 *
	 * @return array|bool
	 */
	protected static function _runScript( $script )
	{
		Log::info( 'Running database script: ' . $script );

		$_commands = @file_get_contents( $script );

		if ( empty( $_commands ) )
		{
			Log::error( '  * Script empty or not found!' );

			return false;
		}

		Sql::setConnection( Pii::pdo() );

		//	Delete comments
		$_lines = explode( PHP_EOL, $_commands );
		$_commands = null;

		foreach ( $_lines as $_line )
		{
			$_line = trim( $_line );

			if ( $_line && '--' != trim( substr( $_line, 0, 2 ) ) )
			{
				$_commands .= $_line . PHP_EOL;
			}
		}

		$_commands = explode( ';', $_commands );

		//	Run!
		$_total = $_success = 0;

		foreach ( $_commands as $_command )
		{
			if ( trim( $_command ) )
			{
				try
				{
					$_success += ( false === Sql::execute( $_command ) ? 0 : 1 );
					$_total += 1;
				}
				catch ( \Exception $_ex )
				{
					Log::error( 'Exception executing script: ' . $_ex->getMessage() );
				}
			}
		}

		Log::info( '  * Results: ' . $_success . ' of ' . $_total . ' lines successful.' );

		//	Return number of successful queries and total number of queries found
		return array(
			'success' => $_success,
			'total'   => $_total
		);
	}

	/**
	 * Configures the system.
	 *
	 * @param array|null $attributes
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return null
	 */
	public static function initAdmin( $attributes = null )
	{
		// Create and login first admin user
		// Use the model attributes, or check system state variables
		$_email = Pii::getState( 'email', Option::get( $attributes, 'email' ) );
		$_password = Pii::getState( 'password', Option::get( $attributes, 'password' ) );

		if ( empty( $_email ) || empty( $_password ) )
		{
			throw new BadRequestException( 'Valid email address and password required to create a user.' );
		}

		try
		{
			/** @var User $_user */
			$_user = User::getByEmail( $_email );

			if ( empty( $_user ) )
			{
				$_user = new User();
				$_firstName = Pii::getState( 'first_name', Option::get( $attributes, 'firstName' ) );
				$_lastName = Pii::getState( 'last_name', Option::get( $attributes, 'lastName' ) );
				$_displayName = Pii::getState(
					'display_name',
					Option::get( $attributes, 'displayName', $_firstName . ( $_lastName ? : ' ' . $_lastName ) )
				);

				$_fields = array(
					'email'        => $_email,
					'password'     => $_password,
					'first_name'   => $_firstName,
					'last_name'    => $_lastName,
					'display_name' => $_displayName,
					'is_active'    => true,
					'is_sys_admin' => true,
					'confirm_code' => 'y'
				);
			}
			else
			{
				//	in case something is messed up
				$_fields = array(
					'is_active'    => true,
					'is_sys_admin' => true,
					'confirm_code' => 'y'
				);
			}

			$_user->setAttributes( $_fields );

			// write back login datetime
			$_user->last_login_date = date( 'c' );
			$_user->save();

			// update session with current real user
			$_identity = Pii::user();
			$_identity->setId( $_user->primaryKey );
			$_identity->setState( 'email', $_email );
			$_identity->setState( 'df_authenticated', false ); // removes catch
			$_identity->setState( 'password', $_password, $_password ); // removes password
		}
		catch ( \Exception $_ex )
		{
			throw new BadRequestException( 'Failed to create a new user: ' . $_ex->getMessage() );
		}
	}

	/**
	 * Configures the default system data.
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @throws \Exception
	 * @return boolean whether configuration is successful
	 */
	public static function initData()
	{
		// init with system required data
		$contents = file_get_contents( static::$_configPath . '/schema/system_data.json' );
		if ( empty( $contents ) )
		{
			throw new \Exception( "Empty or no system data file found." );
		}

		$contents = DataFormat::jsonToArray( $contents );
		foreach ( $contents as $table => $content )
		{
			switch ( $table )
			{
				case 'df_sys_service':
					if ( !empty( $content ) )
					{
						foreach ( $content as $service )
						{
							$_apiName = Option::get( $service, 'api_name' );
							if ( !Service::model()->exists( 'api_name = :name', array( ':name' => $_apiName ) ) )
							{
								try
								{
									$obj = new Service();
									$obj->setAttributes( $service );
									$obj->save();
								}
								catch ( \Exception $ex )
								{
									throw new InternalServerErrorException( "Failed to create services.\n{$ex->getMessage()}" );
								}
							}
						}
					}
					break;
				case 'df_sys_email_template':
					if ( !empty( $content ) )
					{
						foreach ( $content as $template )
						{
							$_name = Option::get( $template, 'name' );
							if ( !EmailTemplate::model()->exists( 'name = :name', array( ':name' => $_name ) ) )
							{
								try
								{
									$obj = new EmailTemplate();
									$obj->setAttributes( $template );
									$obj->save();
								}
								catch ( \Exception $ex )
								{
									throw new InternalServerErrorException( "Failed to create email template.\n{$ex->getMessage()}" );
								}
							}
						}
					}
					break;
			}
		}
		// init system with sample setup
		$contents = file_get_contents( \Kisma::get( 'app.config_path' ) . '/schema/sample_data.json' );
		if ( !empty( $contents ) )
		{
			$contents = DataFormat::jsonToArray( $contents );
			foreach ( $contents as $table => $content )
			{
				switch ( $table )
				{
					case 'df_sys_service':
						if ( !empty( $content ) )
						{
							foreach ( $content as $service )
							{
								Log::debug( 'Importing service: ' . $service['api_name'] );

								try
								{
									$obj = new Service();
									$obj->setAttributes( $service );
									$obj->save();
								}
								catch ( \Exception $ex )
								{
									Log::error( "Failed to create sample services.\n{$ex->getMessage()}" );
								}
							}
						}
						break;
					case 'app_package':
						$result = App::model()->findAll();
						if ( empty( $result ) )
						{
							if ( !empty( $content ) )
							{
								foreach ( $content as $package )
								{
									if ( null !== ( $fileUrl = Option::get( $package, 'url' ) ) )
									{
										if ( 0 === strcasecmp( 'dfpkg', FileUtilities::getFileExtension( $fileUrl ) ) )
										{
											Log::debug( 'Importing application: ' . $fileUrl );
											$filename = null;
											try
											{
												// need to download and extract zip file and move contents to storage
												$filename = FileUtilities::importUrlFileToTemp( $fileUrl );
												Packager::importAppFromPackage( $filename, $fileUrl );
											}
											catch ( \Exception $ex )
											{
												Log::error( "Failed to import application package $fileUrl.\n{$ex->getMessage()}" );
											}

											if ( !empty( $filename ) && false === @unlink( $filename ) )
											{
												Log::error( 'Unable to remove package file "' . $filename . '"' );
											}
										}
									}
								}
							}
						}
						break;
				}
			}
		}
	}

	/**
	 * Upgrades the DSP code base and runs the installer.
	 *
	 * @param string $version Version to upgrade to, should be a github tag identifier
	 *
	 * @throws \Exception
	 * @return void
	 */
	public static function upgradeDsp( $version )
	{
		if ( empty( $version ) )
		{
			throw new \Exception( 'No version information in upgrade load.' );
		}
		$_versionUrl = 'https://github.com/dreamfactorysoftware/dsp-core/archive/' . $version . '.zip';

		// copy current directory to backup
		$_upgradeDir = Pii::getParam( 'base_path' ) . '/';
		$_backupDir = Pii::getParam( 'storage_base_path' ) . '/backups/';
		if ( !file_exists( $_backupDir ) )
		{
			@\mkdir( $_backupDir, 0777, true );
		}
		$_backupZipFile = $_backupDir . 'dsp_' . Pii::getParam( 'dsp.version' ) . '-' . time() . '.zip';
		$_backupZip = new \ZipArchive();
		if ( true !== $_backupZip->open( $_backupZipFile, \ZIPARCHIVE::CREATE ) )
		{
			throw new \Exception( 'Error opening zip file.' );
		}
		$_skip = array( '.', '..', '.git', '.idea', 'log', 'vendor', 'shared', 'storage' );
		try
		{
			FileUtilities::addTreeToZip( $_backupZip, $_upgradeDir, '', $_skip );
		}
		catch ( \Exception $ex )
		{
			throw new \Exception( "Error zipping contents to backup file - $_backupDir\n.{$ex->getMessage()}" );
		}
		if ( !$_backupZip->close() )
		{
			throw new \Exception( "Error writing backup file - $_backupZipFile." );
		}

		// need to download and extract zip file of latest version
		$_tempDir = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		$_tempZip = null;
		try
		{
			$_tempZip = FileUtilities::importUrlFileToTemp( $_versionUrl );
			$zip = new \ZipArchive();
			if ( true !== $zip->open( $_tempZip ) )
			{
				throw new \Exception( 'Error opening zip file.' );
			}
			if ( !$zip->extractTo( $_tempDir ) )
			{
				throw new \Exception( "Error extracting zip contents to temp directory - $_tempDir." );
			}

			if ( false === @unlink( $_tempZip ) )
			{
				Log::error( 'Unable to remove zip file "' . $_tempZip . '"' );
			}
		}
		catch ( \Exception $ex )
		{
			if ( !empty( $_tempZip ) && false === @unlink( $_tempZip ) )
			{
				Log::error( 'Unable to remove zip file "' . $_tempZip . '"' );
			}

			throw new \Exception( "Failed to import dsp package $_versionUrl.\n{$ex->getMessage()}" );
		}

		// now copy over
		$_tempDir .= 'dsp-core-' . $version;
		if ( !file_exists( $_tempDir ) )
		{
			throw new \Exception( "Failed to find new dsp package $_tempDir." );
		}
		// blindly, or are there things we shouldn't touch here?
		FileUtilities::copyTree( $_tempDir, $_upgradeDir, false, $_skip );

		// now run installer script
		$_oldWorkingDir = getcwd();
		chdir( $_upgradeDir );
		$_installCommand = 'export COMPOSER_HOME=' . $_upgradeDir . '; /bin/bash ./scripts/installer.sh -cD 2>&1';
		exec( $_installCommand, $_installOut );
		Log::info( implode( PHP_EOL, $_installOut ) );

		// back to normal
		chdir( $_oldWorkingDir );

		// clear out swagger cache
		SwaggerManager::clearCache();
	}

	/**
	 * @return array|mixed|string
	 */
	public static function getDspVersions()
	{
		static $_client;

		if ( null === $_client )
		{
			$_client = new Client( static::VERSION_TAGS_URL );
			$_client->setUserAgent( 'dreamfactory' );
		}

		$_request = $_client->createRequest();
		$_response = $_request->send();

		if ( !$_response->isSuccessful() )
		{
			//	log an error here, but don't stop config pull
			Log::error( 'Error retrieving DSP versions from GitHub: ' . $_response->getReasonPhrase() );

			return null;
		}

		return (array)$_response->json();
	}

	/**
	 * @return string
	 */
	public static function getLatestVersion()
	{
		$_versions = static::getDspVersions();

		if ( isset( $_versions[0] ) )
		{
			return Option::get( $_versions[0], 'name', '' );
		}

		return '';
	}

	/**
	 * @return string
	 */
	public static function getCurrentVersion()
	{

		return Pii::getParam( 'dsp.version' );
	}

	/**
	 * @return array|mixed
	 */
	public static function getAllowedHosts()
	{
		$_allowedHosts = array();
		$_file = Pii::getParam( 'storage_base_path' ) . static::CORS_DEFAULT_CONFIG_FILE;
		if ( !file_exists( $_file ) )
		{
			// old location
			$_file = Pii::getParam( 'private_path' ) . static::CORS_DEFAULT_CONFIG_FILE;
		}
		if ( file_exists( $_file ) )
		{
			$_content = file_get_contents( $_file );
			if ( !empty( $_content ) )
			{
				$_allowedHosts = json_decode( $_content, true );
			}
		}

		return $_allowedHosts;
	}

	/**
	 * @param array $allowed_hosts
	 *
	 * @throws \Exception
	 */
	public static function setAllowedHosts( $allowed_hosts = array() )
	{
		static::validateHosts( $allowed_hosts );

		$allowed_hosts = DataFormat::jsonEncode( $allowed_hosts, true );
		$_path = Pii::getParam( 'storage_base_path' );
		$_config = $_path . static::CORS_DEFAULT_CONFIG_FILE;

		//	Create directory if it doesn't exists
		if ( !is_dir( $_path ) )
		{
			@\mkdir( $_path, 0777, true );
		}

		//	Write new cors config
		if ( false === file_put_contents( $_config, $allowed_hosts ) )
		{
			throw new PlatformServiceException( 'Failed to update CORS configuration.' );
		}
	}

	/**
	 * @param array $allowed_hosts
	 *
	 * @throws BadRequestException
	 * @return bool
	 */
	protected static function validateHosts( $allowed_hosts )
	{
		foreach ( Option::clean( $allowed_hosts ) as $_hostInfo )
		{
			$_host = Option::get( $_hostInfo, 'host' );

			if ( empty( $_host ) )
			{
				throw new BadRequestException( 'Allowed hosts contains an empty host name.' );
			}
		}

		return true;
	}

	/**
	 * @param array  $paths   If specified, paths will be returned in this variable
	 * @param string $userTag The user tag
	 *
	 * @return bool
	 */
	public static function registrationComplete( &$paths = null, $userTag = null )
	{
		$_privatePath = Pii::getParam( 'private_path' );
		$_tag = $userTag;
		$_userId = Session::getCurrentUserId();

		/** @var User $_user */
		if ( empty( $_userId ) || null === ( $_user = User::model()->findByPk( $_userId ) ) )
		{
			return false;
		}

		//	Not an admin? Ignore...
		if ( !$_user->is_sys_admin )
		{
			return true;
		}

		//	Make sure we have a tag
		if ( null === $_tag )
		{
			$_tag = $_user->email;
		}

		//	Make sure the private path is there
		if ( !is_dir( $_privatePath ) && false === @mkdir( $_privatePath ) )
		{
			Log::error( 'System error creating private storage directory: ' . $_privatePath );

			return false;
		}

		$_marker = $_privatePath . Drupal::REGISTRATION_MARKER . '.' . sha1( $_tag );
		$paths = array( '_privatePath' => $_privatePath, '_marker' => $_marker );

		if ( !file_exists( $_marker ) )
		{
			//	Test if directory is not writeable
			if ( false === @file_put_contents( $_marker . '.test', null ) )
			{
				Log::error( 'Unable to write marker file. Ignoring.' );

				return true;
			}
			else
			{
				if ( false === @unlink( $_marker . '.test' ) )
				{
					Log::error( 'Unable to remove test file created for check.' );
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * Queues a registration record
	 *
	 * @param User|\CActiveRecord $user
	 * @param bool                $skipped
	 * @param bool                $forceRemove Set to true to remove the registration marker
	 *
	 * @return bool TRUE if registration was queued (i.e. first-time used), FALSE otherwise
	 */
	public static function registerPlatform( $user, $skipped = true, $forceRemove = false )
	{
		$_paths = $_privatePath = $_marker = null;

		$_complete = static::registrationComplete( $_paths, $user->email );
		Pii::setState( 'app.registration_skipped', $skipped );

		extract( $_paths );

		if ( $_complete )
		{
			if ( false !== $forceRemove )
			{
				//	Remove registration file
				if ( false === @unlink( $_marker ) )
				{
					//	Log it
					Log::error( 'System error removing registration marker: ' . $_marker );
					//	But do nothing. Like the goggles...
				}
				else
				{
					Log::info( 'Forced removal of registration marker: ' . $_marker );
				}
			}

			return true;
		}

		//	Call the API
		return Drupal::registerPlatform(
			$user,
			$_paths,
			array(
				 'field_first_name'           => $user->first_name,
				 'field_last_name'            => $user->last_name,
				 'field_installation_type'    => InstallationTypes::determineType( true ),
				 'field_registration_skipped' => ( $skipped ? 1 : 0 ),
			)
		);
	}

	//.........................................................................
	//. REST interface implementation
	//.........................................................................

	/**
	 * @return array
	 */
	protected function _listResources()
	{
		return array(
			'resource' => array(
				array( 'name' => 'app', 'label' => 'Application' ),
				array( 'name' => 'app_group', 'label' => 'Application Group' ),
				array( 'name' => 'config', 'label' => 'Configuration' ),
				array( 'name' => 'custom', 'label' => 'Custom Settings' ),
				array( 'name' => 'email_template', 'label' => 'Email Template' ),
				array( 'name' => 'provider', 'label' => 'Provider' ),
				array( 'name' => 'provider_user', 'label' => 'Provider User' ),
				array( 'name' => 'role', 'label' => 'Role' ),
				array( 'name' => 'service', 'label' => 'Service' ),
				array( 'name' => 'user', 'label' => 'User' ),
			)
		);
	}

	/**
	 *
	 * @return array|bool
	 * @throws \Exception
	 */
	protected function _handleResource()
	{
		if ( empty( $this->_resource ) )
		{
			if ( static::Get == $this->_action )
			{
				return $this->_listResources();
			}

			return false;
		}

		if ( 'custom' == $this->_resource )
		{
			$_obj = new CustomSettings( $this, $this->_resourceArray );

			return $_obj->processRequest( null, $this->_action );
		}

		$_resource = ResourceStore::resource( $this->_resource, $this->_resourceArray );

		return $_resource->processRequest( $this->_resourcePath, $this->_action );
	}

	/**
	 * @param $resource
	 *
	 * @throws BadRequestException
	 * @return BasePlatformSystemModel
	 */
	public static function getResourceModel( $resource )
	{
		if ( 'custom' == $resource )
		{
			$resource = 'custom_settings';
		}

		return ResourceStore::model( $resource );
	}

	//-------- System Helper Operations -------------------------------------------------

	/**
	 * @param $id
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getAppNameFromId( $id )
	{
		if ( !empty( $id ) )
		{
			try
			{
				$app = App::model()->findByPk( $id );
				if ( isset( $app ) )
				{
					return $app->getAttribute( 'name' );
				}
			}
			catch ( \Exception $ex )
			{
				throw $ex;
			}
		}

		return '';
	}

	/**
	 * @param $name
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getAppIdFromName( $name )
	{
		if ( !empty( $name ) )
		{
			try
			{
				$app = App::model()->find( 'name=:name', array( ':name' => $name ) );
				if ( isset( $app ) )
				{
					return $app->getPrimaryKey();
				}
			}
			catch ( \Exception $ex )
			{
				throw $ex;
			}
		}

		return '';
	}

	/**
	 * @return string
	 */
	public static function getCurrentAppName()
	{
		return ( isset( $GLOBALS['app_name'] ) ) ? $GLOBALS['app_name'] : '';
	}

	/**
	 * @return string
	 */
	public static function getCurrentAppId()
	{
		return static::getAppIdFromName( static::getCurrentAppName() );
	}

	/**
	 * Returns true if this DSP has been activated
	 *
	 * @return bool|int Returns 2+ if # of admins is greater than 1
	 */
	public static function activated()
	{
		try
		{
			$_admins = Sql::scalar(
				<<<SQL
		SELECT
	COUNT(id)
FROM
	df_sys_user
WHERE
	is_sys_admin = 1 AND
	is_deleted = 0
SQL
				,
				0,
				array(),
				Pii::pdo()
			);

			return ( 0 == $_admins ? false : ( $_admins > 1 ? $_admins : true ) );
		}
		catch ( \Exception $_ex )
		{
			return false;
		}
	}

	/**
	 * Automatically logs in the first admin user
	 *
	 * @param User $user
	 *
	 * @return bool
	 */
	public static function autoLoginAdmin( $user = null )
	{
		try
		{
			/** @var User $_user */
			$_user = $user
				? : User::model()->find(
					'is_sys_admin = :is_sys_admin and is_deleted = :is_deleted',
					array( ':is_sys_admin' => 1, ':is_deleted' => 0 )
				);

			if ( !empty( $_user ) )
			{
				$_identity = new PlatformUserIdentity( $_user->email, null );

				if ( $_identity->logInUser( $_user ) )
				{
					return Pii::user()->login( $_identity, 0 );
				}
			}

			return false;
		}
		catch ( \CDbException $_ex )
		{
			return false;
		}
	}

//	/**
//	 * @param string $apiName
//	 *
//	 * @return BasePlatformService|void
//	 * @throws \Exception
//	 */
//	public function setApiName( $apiName )
//	{
//		throw new \Exception( 'SystemManager API name can not be changed.' );
//	}
//
//	/**
//	 * @param string $type
//	 *
//	 * @return BasePlatformService|void
//	 * @throws \Exception
//	 */
//	public function setType( $type )
//	{
//		throw new \Exception( 'SystemManager type can not be changed.' );
//	}
//
//	/**
//	 * @param string $description
//	 *
//	 * @return BasePlatformService
//	 * @throws \Exception
//	 */
//	public function setDescription( $description )
//	{
//		throw new \Exception( 'SystemManager description can not be changed.' );
//	}
//
//	/**
//	 * @param boolean $isActive
//	 *
//	 * @return BasePlatformService|void
//	 * @throws \Exception
//	 */
//	public function setIsActive( $isActive = false )
//	{
//		throw new \Exception( 'SystemManager active flag can not be changed.' );
//	}
//
//	/**
//	 * @return boolean
//	 */
//	public function getIsActive()
//	{
//		return $this->_isActive;
//	}
//
//	/**
//	 * @param string $name
//	 *
//	 * @return BasePlatformService|void
//	 * @throws \Exception
//	 */
//	public function setName( $name )
//	{
//		throw new \Exception( 'SystemManager name can not be changed.' );
//	}
//
//	/**
//	 * @param string $nativeFormat
//	 *
//	 * @return BasePlatformService|void
//	 * @throws \Exception
//	 */
//	public function setNativeFormat( $nativeFormat )
//	{
//		throw new \Exception( 'SystemManager native format can not be changed.' );
//	}

	/**
	 * @return string
	 */
	public static function getConfigPath()
	{
		return self::$_configPath;
	}

	/**
	 * @param string $configPath
	 */
	public static function setConfigPath( $configPath )
	{
		self::$_configPath = $configPath;
	}
}

//	Set the config path...
SystemManager::setConfigPath( \Kisma::get( 'app.config_path' ) );
