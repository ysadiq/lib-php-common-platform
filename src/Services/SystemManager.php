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
namespace DreamFactory\Platform\Services;

use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Interfaces\PlatformStates;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\Drupal;
use DreamFactory\Platform\Utility\Fabric;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\SqlDbUtilities;
use DreamFactory\Platform\Yii\Components\PlatformUserIdentity;
use DreamFactory\Platform\Yii\Models\App;
use DreamFactory\Platform\Yii\Models\BasePlatformModel;
use DreamFactory\Platform\Yii\Models\Service;
use DreamFactory\Platform\Yii\Models\User;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;
use Kisma\Core\Utility\Storage;

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
	 * @var string The name of the table who's existence indicates that the database has been created
	 */
	const DSP_TABLE_MARKER = 'df_sys_config';
	/**
	 * @var string The private CORS configuration file
	 */
	const CORS_DEFAULT_CONFIG_FILE = '/cors.config.json';
	/**
	 * @var string The url to pull for DSP tag information
	 */
	const VERSION_TAGS_URL = 'https://api.github.com/repos/dreamfactorysoftware/dsp-core/tags';
	/**
	 * @var string The relative path to the system schema config directory
	 */
	const SCHEMA_PATH = '/schema';
	/**
	 * @var string The relative (to /config) path to the system schema file
	 */
	const SCHEMA_FILE_PATH = '/schema/system_schema.json';
	/**
	 * @var string The relative (to /config) path to the system schema data file
	 */
	const SCHEMA_DATA_FILE_PATH = '/schema/system_data.json';
	/**
	 * @var string The exception message for bogus JSON config files
	 */
	const BOGUS_INSTALL_MESSAGE = 'One or more of this DSP\'s configuration files cannot be loaded. Corrupt installation possible! :(';

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
	 *
	 * @return string
	 */
	public static function getSystemState()
	{
		static $_isReady = null;

		if ( PlatformStates::READY === $_isReady )
		{
			return $_isReady;
		}

		if ( !Pii::getState( 'dsp.init_check_complete', false ) )
		{
			if ( PlatformStates::DATABASE_READY != ( $_dbState = static::_validateDatabaseStructure() ) )
			{
				//	Something is needed for the database
				return $_dbState;
			}

			// Check for at least one system admin user
			if ( !static::activated() )
			{
				Log::debug( 'System administrator required.' );

				return PlatformStates::ADMIN_REQUIRED;
			}

			//	Need to check for the default services
			if ( 0 == Service::model()->count() )
			{
				Log::debug( 'Database data (default services) required.' );

				return PlatformStates::DATA_REQUIRED;
			}

			Pii::setState( 'dsp.init_check_complete', true );
			Log::debug( 'Platform state validation complete.' );
		}
		else
		{
//			Log::debug( 'Platform state previously validated.' );
		}

		//	And redirect to welcome screen
		if ( !Pii::guest() && !Fabric::fabricHosted() && !SystemManager::registrationComplete() )
		{
			Log::debug( 'Unregistered, non-hosted DSP detected.' );

			return PlatformStates::WELCOME_REQUIRED;
		}

		return $_isReady = PlatformStates::READY;
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
			$_jsonSchema = static::_loadSchema();
			$version = Option::get( $_jsonSchema, 'version' );
			$tables = Option::get( $_jsonSchema, 'table' );
			$command = $_db->createCommand();

			Log::debug( 'Checking database schema' );

			SqlDbUtilities::createTables( $_db, $tables, true, false );

			try
			{
				// initialize config table if not already
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
			if ( null !== ( $_scripts = Option::get( $_jsonSchema, 'scripts' ) ) )
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
			$_jsonSchema = static::_loadSchema();
			$version = Option::get( $_jsonSchema, 'version' );
			$tables = Option::get( $_jsonSchema, 'table' );
			$command = $_db->createCommand();
			$oldVersion = '';

			if ( SqlDbUtilities::doesTableExist( $_db, static::SYSTEM_TABLE_PREFIX . 'config' ) )
			{
				$command->reset();
				$oldVersion = $command->select( 'db_version' )->from( 'df_sys_config' )->queryScalar();
			}

			// create system tables
			Log::debug( 'Checking database schema' );

			SqlDbUtilities::createTables( $_db, $tables, true, false );

			if ( !empty( $oldVersion ) )
			{
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

				$_tableName = static::SYSTEM_TABLE_PREFIX . 'config';
				$_params = array( ':db_version' => $version );

				$_sql = <<<SQL
INSERT INTO {$_tableName}
(
	db_version
) VALUES(
	:db_version
)
ON DUPLICATE KEY UPDATE
	db_version = VALUES( db_version )
SQL;

				if ( 0 >= ( $_count = Sql::execute( $_sql, $_params ) ) && $oldVersion != $version )
				{
					Log::error( 'Error updating system config db_version.', array( 'from_version' => $oldVersion, 'to_version' => $version ) );

					throw new \Exception( 'Upsert failed. From v' . $oldVersion . ' to v' . $version );
				}
			}
			catch ( \Exception $_ex )
			{
				Log::error( 'Exception saving database version: ' . $_ex->getMessage() );
			}

			//	Any scripts to run?
			if ( null !== ( $_scripts = Option::get( $_jsonSchema, 'scripts' ) ) )
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
		$_jsonSchema = static::_loadSchema( static::SCHEMA_DATA_FILE_PATH, false );

		//	Create services
		static::_createSystemData(
			'service',
			Option::get( $_jsonSchema, static::SYSTEM_TABLE_PREFIX . 'service' ),
			'DreamFactory\\Platform\\Yii\\Models\\Service',
			'api_name'
		);

		//	Create templates
		static::_createSystemData(
			'email_template',
			Option::get( $_jsonSchema, static::SYSTEM_TABLE_PREFIX . 'email_template' ),
			'DreamFactory\\Platform\\Yii\\Models\\EmailTemplate',
			'name'
		);
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
		$_backupDir = Platform::getStoragePath( '/backups/' );
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
		$_installCommand = 'export COMPOSER_HOME=' . $_upgradeDir . '; /bin/bash ./scripts/installer.sh -cDf 2>&1';
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
		$_results = Curl::get(
			static::VERSION_TAGS_URL,
			array(),
			array(
				CURLOPT_HTTPHEADER => array( 'User-Agent: dreamfactory' )
			)
		);

		if ( HttpResponse::Ok != ( $_code = Curl::getLastHttpCode() ) )
		{
			//	log an error here, but don't stop config pull
			Log::error( 'Error retrieving DSP versions from GitHub: ' . $_code );

			return null;
		}

		if ( is_string( $_results ) && !empty( $_results ) )
		{
			$_results = json_decode( $_results, true );
		}

		return $_results;
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
	 * @throws PlatformServiceException
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
		//@todo Need to supply actual data from service table/config file. Maybe swagger?
		return array(
			'resource' => array(
				array( 'name' => 'app', 'label' => 'Application' ),
				array( 'name' => 'app_group', 'label' => 'Application Group' ),
				array( 'name' => 'config', 'label' => 'Configuration' ),
				array( 'name' => 'custom', 'label' => 'Custom Settings' ),
				array( 'name' => 'device', 'label' => 'Device' ),
				array( 'name' => 'email_template', 'label' => 'Email Template' ),
				array( 'name' => 'event', 'label' => 'Event' ),
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

		if ( 'custom' == ( $_resource = $this->_resource ) )
		{
			$_resource .= '_settings';
		}

		//	Let the resource handle it...
		$_resource = ResourceStore::resource( $_resource, $this->_resourceArray );

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
		//@todo should we consider a resource map somewhere in the config for things like this? And dev overriding?
		if ( 'custom' == $resource )
		{
			$resource .= '_settings';
		}

		return ResourceStore::model( $resource );
	}

	//-------- System Helper Operations -------------------------------------------------

	/**
	 * @param int $id
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getAppNameFromId( $id )
	{
		//@todo NULLs please!
		$_name = '';

		if ( !empty( $id ) )
		{
			if ( null !== ( $_app = App::model()->findByPk( $id, array( 'select' => 'name' ) ) ) )
			{
				$_name = $_app->getAttribute( 'name' );
			}
		}

		return $_name;
	}

	/**
	 * @param $name
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getAppIdFromName( $name )
	{
		//@todo NULLs please!
		$_name = '';

		if ( !empty( $name ) )
		{
			if ( null !== ( $_app = App::model()->byApiName( $name, 'name' )->find() ) )
			{
				$_name = $_app->getPrimaryKey();
			}
		}

		return $_name;
	}

	/**
	 * Returns true if this DSP has been activated
	 *
	 * @return bool|int Returns 2+ if # of admins is greater than 1
	 */
	public static function activated()
	{
		$_tableName = static::SYSTEM_TABLE_PREFIX . 'user';

		try
		{
			$_admins = Sql::scalar(
				<<<SQL
		SELECT
	COUNT(id)
FROM
	{$_tableName}
WHERE
	is_sys_admin = 1 AND
	is_deleted = 0
SQL
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

	/**
	 * @param string $schemaPath
	 * @param bool   $checkTables
	 *
	 * @throws \Exception
	 * @return string
	 */
	protected static function _loadSchema( $schemaPath = null, $checkTables = true )
	{
		$_schema = null;
		$_schemaFilePath = static::$_configPath . ( $schemaPath ? : static::SCHEMA_FILE_PATH );

		if ( false !== ( $_schema = Pii::getState( 'dsp.json_schema', false ) ) )
		{
			$_schema = Storage::defrost( $_schema );

			if ( isset( $_schema, $_schema[$_schemaFilePath] ) )
			{
				return $_schema[$_schemaFilePath];
			}
		}

		if ( empty( $_schema ) )
		{
			$_schema = array( $_schemaFilePath => null );
		}

		if ( false === ( $_jsonSchema = file_get_contents( $_schemaFilePath ) ) )
		{
			//	Just put it in the log for now...
			Log::error( 'File system error reading system schema file: ' . $_schemaFilePath );
		}

		if ( empty( $_jsonSchema ) )
		{
			throw new InternalServerErrorException( static::BOGUS_INSTALL_MESSAGE );
		}

		$_schema[$_schemaFilePath] = DataFormat::jsonToArray( $_jsonSchema );

		if ( false !== $checkTables )
		{
			$_tables = Option::get( $_schema[$_schemaFilePath], 'table' );

			if ( empty( $_tables ) )
			{
				throw new InternalServerErrorException( static::BOGUS_INSTALL_MESSAGE );
			}
		}

		Pii::setState(
			'dsp.json_schema',
			Storage::freeze( $_schema )
		);

		return $_schema[$_schemaFilePath];
	}

	/**
	 * Validates that:
	 *
	 *    1. The DSP DDL has been executed
	 *    2. The DSP schema has been created
	 *    3. The DSP schema is at the current version
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return int The current system state based on the validation. Returns PlatformStates::DATABASE_READY if all database validations pass.
	 */
	protected static function _validateDatabaseStructure()
	{
		//	Lad up the schema
		$_jsonSchema = static::_loadSchema();

		//	Refresh the schema that we just added
		$_db = Pii::db();
		Sql::setConnection( $_db->getPdoInstance() );

		$_schema = $_db->getSchema();
		$_tables = $_schema->getTableNames();

		//	If the marker is not in place, the database schema has not been initialized
		if ( empty( $_tables ) || false === in_array( static::DSP_TABLE_MARKER, $_tables ) )
		{
			Log::debug( 'Database required' );

			return PlatformStates::INIT_REQUIRED;
		}

		// check for any missing necessary tables
		$_neededTables = Option::get( $_jsonSchema, 'table', array() );

		foreach ( $_neededTables as $_table )
		{
			$_name = Option::get( $_table, 'name' );

			if ( !empty( $_name ) && !in_array( $_name, $_tables ) )
			{
				Log::debug( 'Database schema required' );

				return PlatformStates::SCHEMA_REQUIRED;
			}
		}

		//	Check for db upgrade, based on tables and/or version
		$_schemaVersion = Option::get( $_jsonSchema, 'version' );
		$_currentVersion = Sql::scalar( 'SELECT db_version FROM ' . static::SYSTEM_TABLE_PREFIX . 'config ORDER BY id DESC' );

		if ( static::doesDbVersionRequireUpgrade( $_currentVersion, $_schemaVersion ) )
		{
			Log::debug(
				'Database schema upgrade required.',
				array( 'from_version' => $_currentVersion, 'to_version' => $_schemaVersion )
			);

			return PlatformStates::SCHEMA_REQUIRED;
		}

		return PlatformStates::DATABASE_READY;
	}

	/**
	 * @param string $tableName
	 * @param array  $data
	 * @param string $modelClassName
	 * @param string $uniqueColumn
	 *
	 * @return int The number of rows added
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 */
	protected static function _createSystemData( $tableName, array $data, $modelClassName, $uniqueColumn = 'api_name' )
	{
		$_added = 0;
		$_tableName = static::SYSTEM_TABLE_PREFIX . $tableName;
		$_sql = 'SELECT COUNT(' . $uniqueColumn . ') FROM ' . $_tableName . ' WHERE ' . $uniqueColumn . ' = :' . $uniqueColumn;

		foreach ( $data as $_row )
		{
			$_count = Sql::scalar( $_sql, 0, array( ':' . $uniqueColumn => Option::get( $_row, $uniqueColumn ) ) );

			if ( empty( $_count ) )
			{
				try
				{
					/** @var BasePlatformModel $_model */
					$_model = new $modelClassName();
					$_model->setAttributes( $_row );
					$_model->save();

					$_added++;
				}
				catch ( \Exception $_ex )
				{
					throw new InternalServerErrorException( 'System data creation failure (' . $_tableName . '): ' . $_ex->getMessage(), array(
						'data'          => $data,
						'bogus_row'     => $_row,
						'unique_column' => $uniqueColumn
					) );
				}
			}
		}

		return $_added;
	}

	/**
	 * @param string $action
	 *
	 * @return void
	 */
	protected static function _stateRedirect( $action = null )
	{
		static $_map = array(
			PlatformStates::INIT_REQUIRED    => 'initSystem',
			PlatformStates::SCHEMA_REQUIRED  => 'upgradeSchema',
			PlatformStates::ADMIN_REQUIRED   => 'initAdmin',
			PlatformStates::DATA_REQUIRED    => 'initData',
			PlatformStates::WELCOME_REQUIRED => 'welcome',
			PlatformStates::UPGRADE_REQUIRED => 'upgrade',
		);

		if ( is_numeric( $action ) )
		{
			$action = Option::get( $_map, $action );
		}

		if ( !empty( $action ) )
		{
			//	Forward to that action page
			$_returnUrl = '/' . Pii::controller()->id . '/' . $action;
		}
		//	Redirect to back from which you came
		elseif ( null === ( $_returnUrl = Pii::user()->getReturnUrl() ) )
		{
			//	Or take me home
			$_returnUrl = Pii::url( Pii::controller()->id . '/index' );
		}

		Pii::redirect( $_returnUrl );
	}

	/**
	 * @return string
	 */
	public static function getConfigPath()
	{
		return static::$_configPath;
	}

	/**
	 * @param string $configPath
	 */
	public static function setConfigPath( $configPath )
	{
		static::$_configPath = $configPath;
	}

	/**
	 * @return string
	 */
	public static function getCurrentAppName()
	{
		return Option::get( $GLOBALS, 'app_name' );
	}

	/**
	 * @return string
	 */
	public static function getCurrentAppId()
	{
		return static::getAppIdFromName( static::getCurrentAppName() );
	}

}

//	Set the db connection
Sql::setConnection( Pii::pdo() );

//	Set the config path...
SystemManager::setConfigPath( dirname( dirname( __DIR__ ) ) . '/config' );