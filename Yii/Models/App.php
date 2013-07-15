<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Yii\Models;

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Utility\ServiceHandler;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;

/**
 * App.php
 * This is the model for "df_sys_app".
 *
 * Columns:
 *
 * @property string               $name
 * @property string               $api_name
 * @property string               $description
 * @property boolean              $is_active
 * @property string               $url
 * @property boolean              $is_url_external
 * @property string               $import_url
 * @property string               $storage_service_id
 * @property string               $storage_container
 * @property string               $_launchUrl
 * @property boolean              $requires_fullscreen
 * @property boolean              $allow_fullscreen_toggle
 * @property boolean              $toggle_location
 * @property boolean              $requires_plugin
 *
 * Relations:
 *
 * @property Role[]               $roles_default_app
 * @property User[]               $users_default_app
 * @property AppGroup[]           $app_groups
 * @property Role[]               $roles
 * @property AppServiceRelation[] $app_service_relation
 * @property Service[]            $services
 * @property Service              $storage_service
 */
class App extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var string The url to launch the app from, comprised of its storage and starting file.
	 */
	protected $_launchUrl;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'app';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return
			array(
				array( 'name, api_name', 'required' ),
				array( 'name, api_name', 'unique', 'allowEmpty' => false, 'caseSensitive' => false ),
				array(
					'is_active, is_url_external, requires_fullscreen, requires_plugin, allow_fullscreen_toggle, storage_service_id',
					'numerical',
					'integerOnly' => true
				),
				array( 'name, api_name', 'length', 'max' => 64 ),
				array( 'storage_container', 'length', 'max' => 255 ),
				array( 'description, url, import_url, toggle_location', 'safe' ),
			);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		$_relations = array(
			'roles_default_app'     => array( static::HAS_MANY, 'Role', 'default_app_id' ),
			'users_default_app'     => array( static::HAS_MANY, 'User', 'default_app_id' ),
			'app_groups'            => array( static::MANY_MANY, 'AppGroup', 'df_sys_app_to_app_group(app_id, app_group_id)' ),
			'roles'                 => array( static::MANY_MANY, 'Role', 'df_sys_app_to_role(app_id, role_id)' ),
			'app_service_relations' => array( static::HAS_MANY, 'AppServiceRelation', 'app_id' ),
			'services'              => array( static::MANY_MANY, 'Service', 'df_sys_app_to_service(app_id, service_id)' ),
			'storage_service'       => array( static::BELONGS_TO, 'Service', 'storage_service_id' ),
		);

		return array_merge( parent::relations(), $_relations );
	}

	/**
	 * {@InheritDoc}
	 */
	public function attributeLabels( $additionalLabels = array() )
	{
		return parent::attributeLabels(
			array(
				'name'                    => 'Name',
				'api_name'                => 'API Name',
				'description'             => 'Description',
				'is_active'               => 'Is Active',
				'url'                     => 'Url',
				'is_url_external'         => 'Is Url External',
				'import_url'              => 'Import Url',
				'storage_service_id'      => 'Storage Service',
				'storage_container'       => 'Storage Container',
				'requires_fullscreen'     => 'Requires Fullscreen',
				'allow_fullscreen_toggle' => 'Allow Fullscreen Toggle',
				'toggle_location'         => 'Toggle Location',
				'requires_plugin'         => 'Requires Plugin',
			) + $additionalLabels
		);
	}

	/**
	 * {@InheritDoc}
	 */
	public function search( $criteria = null )
	{
		$_criteria = $criteria ? : new \CDbCriteria();

		$_criteria->compare( 'name', $this->name, true );
		$_criteria->compare( 'api_name', $this->api_name, true );
		$_criteria->compare( 'is_active', $this->is_active );
		$_criteria->compare( 'is_url_external', $this->is_url_external );
		$_criteria->compare( 'import_url', $this->import_url );
		$_criteria->compare( 'storage_service_id', $this->storage_service_id );
		$_criteria->compare( 'storage_container', $this->storage_container );
		$_criteria->compare( 'requires_fullscreen', $this->requires_fullscreen );
		$_criteria->compare( 'allow_fullscreen_toggle', $this->allow_fullscreen_toggle );
		$_criteria->compare( 'toggle_location', $this->toggle_location );
		$_criteria->compare( 'requires_plugin', $this->requires_plugin );

		return parent::search( $_criteria );
	}

	/**
	 * @param array $values
	 * @param int   $id
	 */
	public function setRelated( $values, $id )
	{
		if ( null !== ( $_groups = Option::get( $values, 'app_groups' ) ) )
		{
			$this->assignManyToOneByMap( $id, 'app_group', 'app_to_app_group', 'app_id', 'app_group_id', $_groups );
		}

		if ( null !== ( $_roles = Option::get( $values, 'roles' ) ) )
		{
			$this->assignManyToOneByMap( $id, 'role', 'app_to_role', 'app_id', 'role_id', $_roles );
		}

		if ( null !== ( $_relations = Option::get( $values, 'app_service_relations' ) ) )
		{
			$this->assignAppServiceRelations( $id, $_relations );
		}
	}

	/**
	 * {@InheritDoc}
	 */
	protected function beforeValidate()
	{
		$this->is_active = ( $this->is_active ? 1 : 0 );
		$this->is_url_external = ( $this->is_url_external ? 1 : 0 );
		$this->requires_fullscreen = ( $this->requires_fullscreen ? 1 : 0 );
		$this->allow_fullscreen_toggle = ( $this->allow_fullscreen_toggle ? 1 : 0 );
		$this->requires_plugin = ( $this->requires_plugin ? 1 : 0 );

		return parent::beforeValidate();
	}

	/**
	 * {@InheritDoc}
	 */
	protected function beforeSave()
	{
		if ( !$this->is_url_external && empty( $this->url ) )
		{
			$this->url = '/index.html';
		}

		return parent::beforeSave();
	}

	/**
	 * {@InheritDoc}
	 */
	protected function afterSave()
	{
		//	Make sure we have an app in the folder
		if ( !$this->is_url_external )
		{
			$_local = empty( $this->storage_service_id );
			$_serviceId = $_local ? 'app' : $this->storage_service_id;
			$_container = $_local ? 'applications' : $this->storage_container;

			/** @var BaseFileService $_service */
			$_service = ServiceHandler::getServiceObject( $_serviceId );

			if ( empty( $_container ) )
			{
				if ( !$_service->containerExists( $this->api_name ) )
				{
					// create in permanent storage
					$_service->createContainer( array( 'name' => $this->api_name ) );
					$name = ( !empty( $this->name ) ) ? $this->name : $this->api_name;
					$content = "<!DOCTYPE html>\n<html>\n<head>\n<title>" . $name . "</title>\n</head>\n";
					$content .= "<body>\nYour app " . $name . " now lives here.</body>\n</html>";
					$path = ( !empty( $this->url ) ) ? ltrim( $this->url, '/' ) : 'index.html';

					if ( !$_service->fileExists( $this->api_name, $path ) )
					{
						$_service->writeFile( $this->api_name, $path, $content );
					}
				}
			}
			else
			{
				if ( !$_service->containerExists( $_container ) )
				{
					$_service->createContainer( array( 'name' => $_container ) );
				}
				if ( !$_service->folderExists( $_container, $this->api_name ) )
				{
					// create in permanent storage
					$_service->createFolder( $_container, $this->api_name );
					$name = ( !empty( $this->name ) ) ? $this->name : $this->api_name;
					$content = "<!DOCTYPE html>\n<html>\n<head>\n<title>" . $name . "</title>\n</head>\n";
					$content .= "<body>\nYour app " . $name . " now lives here.</body>\n</html>";
					$path = $this->api_name . '/';
					$path .= ( !empty( $this->url ) ) ? ltrim( $this->url, '/' ) : 'index.html';
					if ( !$_service->fileExists( $_container, $path ) )
					{
						$_service->writeFile( $_container, $path, $content );
					}
				}
			}
		}

		parent::afterSave();
	}

	/**
	 * {@InheritDoc}
	 */
	protected function beforeDelete()
	{
		//	Make sure you don't delete yourself
		if ( $this->api_name == SystemManager::getCurrentAppName() )
		{
			throw new \CDbException( 'The currently running application can not be deleted.' );
		}

		return parent::beforeDelete();
	}

	/**
	 * {@InheritDoc}
	 */
	public function afterFind()
	{
		parent::afterFind();

		//	Correct data types
		$this->is_active = ( 0 != $this->is_active );
		$this->is_url_external = ( 0 != $this->is_url_external );
		$this->requires_fullscreen = ( 0 != $this->requires_fullscreen );
		$this->allow_fullscreen_toggle = ( 0 != $this->allow_fullscreen_toggle );
		$this->requires_plugin = ( 0 != $this->requires_plugin );

		if ( !$this->is_url_external )
		{
			$_protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ) ? 'https' : 'http';
			$this->_launchUrl = $_protocol . '://' . FilterInput::server( 'HTTP_HOST' ) . '/';

			if ( !empty( $this->storage_service_id ) )
			{
				/** @var $service Service */
				$_service = $this->getRelated( 'storage_service' );

				if ( !empty( $_service ) )
				{
					$this->_launchUrl .= $_service->api_name . '/';
				}

				if ( !empty( $this->storage_container ) )
				{
					$this->_launchUrl .= $this->storage_container . '/';
				}
			}
			else
			{
				$this->_launchUrl .= 'app/applications/';
			}
			$this->_launchUrl .= $this->api_name . $this->url;
		}
		else
		{
			$this->_launchUrl = $this->url;
		}
	}

	/**
	 * @param string $requested
	 * @param array  $columns
	 * @param array  $hidden
	 *
	 * @return array
	 */
	public function getRetrievableAttributes( $requested, $columns = array(), $hidden = array() )
	{
		return parent::getRetrievableAttributes(
			$requested,
			array_merge(
				array(
					 'name',
					 'api_name',
					 'description',
					 'is_active',
					 'url',
					 'is_url_external',
					 'import_url',
					 'storage_service_id',
					 'storage_container',
					 '_launchUrl',
					 'requires_fullscreen',
					 'allow_fullscreen_toggle',
					 'toggle_location',
					 'requires_plugin',
				),
				$columns
			),
			$hidden
		);
	}

	/**
	 * @param  int  $id The row ID
	 * @param array $relations
	 *
	 * @throws Exception
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \CDbException
	 * @return void
	 */
	protected function assignAppServiceRelations( $id, $relations = array() )
	{
		if ( empty( $app_id ) )
		{
			throw new BadRequestException( 'App id can not be empty.' );
		}

		try
		{
			$relations = array_values( $relations ); // reset indices if needed
			$_count = count( $relations );

			// check for dupes before processing
			for ( $_key1 = 0; $_key1 < $_count; $_key1++ )
			{
				$access = $relations[$_key1];
				$_serviceId = Utilities::getArrayValue( 'service_id', $access, null );
				for ( $key2 = $_key1 + 1; $key2 < $_count; $key2++ )
				{
					$access2 = $relations[$key2];
					$_serviceId2 = Utilities::getArrayValue( 'service_id', $access2, null );
					if ( $_serviceId == $_serviceId2 )
					{
						throw new BadRequestException( "Duplicated service in app service relation." );
					}
				}
			}
			$_mapTable = static::tableNamePrefix() . 'app_to_service';
			$_mapPrimaryKey = 'id';
			// use query builder
			/** @var CDbCommand $_command */
			$_command = Pii::db()->createCommand();
			$_command->select( 'id,service_id,component' );
			$_command->from( $_mapTable );
			$_command->where( 'app_id = :aid' );
			$maps = $_command->queryAll( true, array( ':aid' => $app_id ) );
			$toDelete = array();
			$_updates = array();
			foreach ( $maps as $map )
			{
				$manyId = Utilities::getArrayValue( 'service_id', $map, null );
				$id = Utilities::getArrayValue( $_mapPrimaryKey, $map, '' );
				$found = false;
				foreach ( $relations as $_key => $item )
				{
					$assignId = Utilities::getArrayValue( 'service_id', $item, null );
					if ( $assignId == $manyId )
					{
						// found it, keeping it, so remove it from the list, as this becomes adds
						// update if need be
						$oldComponent = Utilities::getArrayValue( 'component', $map, null );
						$newComponent = Utilities::getArrayValue( 'component', $item, null );
						if ( !empty( $newComponent ) )
						{
							$newComponent = json_encode( $newComponent );
						}
						else
						{
							$newComponent = null; // no empty arrays here
						}
						// old should be encoded in the db
						if ( $oldComponent != $newComponent )
						{
							$map['component'] = $newComponent;
							$_updates[] = $map;
						}
						// otherwise throw it out
						unset( $relations[$_key] );
						$found = true;
						continue;
					}
				}
				if ( !$found )
				{
					$toDelete[] = $id;
					continue;
				}
			}
			if ( !empty( $toDelete ) )
			{
				// simple delete request
				$_command->reset();
				$rows = $_command->delete( $_mapTable, array( 'in', $_mapPrimaryKey, $toDelete ) );
			}
			if ( !empty( $_updates ) )
			{
				foreach ( $_updates as $item )
				{
					$itemId = Utilities::getArrayValue( 'id', $item, '' );
					unset( $item['id'] );
					// simple update request
					$_command->reset();
					$rows = $_command->update( $_mapTable, $item, 'id = :id', array( ':id' => $itemId ) );
					if ( 0 >= $rows )
					{
						throw new \CDbException( "Record update failed." );
					}
				}
			}
			if ( !empty( $relations ) )
			{
				foreach ( $relations as $item )
				{
					// simple insert request
					$newComponent = Utilities::getArrayValue( 'component', $item, null );
					if ( !empty( $newComponent ) )
					{
						$newComponent = json_encode( $newComponent );
					}
					else
					{
						$newComponent = null; // no empty arrays here
					}
					$record = array(
						'app_id'     => $app_id,
						'service_id' => Utilities::getArrayValue( 'service_id', $item, null ),
						'component'  => $newComponent
					);
					$_command->reset();
					$rows = $_command->insert( $_mapTable, $record );
					if ( 0 >= $rows )
					{
						throw new Exception( "Record insert failed." );
					}
				}
			}
		}
		catch ( \Exception $_ex )
		{
			throw new \CDbException( 'Error updating application service assignment: ' . $_ex->getMessage(), $_ex->getCode() );
		}
	}

	/**
	 * @param  int   $id          The row ID
	 * @param array  $relations   Array of relational data for this relation
	 * @param string $relationKey The name of the column in the map table
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \CDbException
	 *
	 * @return void
	 */
	protected function _mapRelations( $id, $relations = array(), $relationKey = null )
	{
		if ( empty( $id ) )
		{
			throw new BadRequestException( 'App id can not be empty.' );
		}

		try
		{
			$_relations = array_values( $relations ); // reset indices if needed

			//	Check for dupes before processing
			$_counts = array_count_values( $_relations );

			foreach ( $_counts as $_key => $_count )
			{
				if ( $_count > 1 )
				{
					throw new BadRequestException( 'Duplicated service "' . $_key . '" in app service relation.' );
				}
			}

			$_model = static::model();
			$_mapTable = $_model->tableName();
			$_mapPrimaryKey = 'id';
			$_relationKey = $relationKey ? : 'service_id';

			/** @var CDbCommand $_command */
			$_mapRows = Sql::findAll(
				<<<MYSQL
SELECT
	id,
	{$_relationKey},
	component
FROM
	{$_mapTable}
WHERE
	app_id = :app_id
MYSQL
				,
				array(
					 ':app_id' => $id,
				),
				Pii::pdo()
			);

			$_deletes = array();
			$_updates = array();

			foreach ( $_mapRows as $_mapping )
			{
				$_serviceId = Option::get( $_mapping, $_relationKey );
				$_id = Option::get( $_mapping, $_mapPrimaryKey );

				$_found = false;

				foreach ( $relations as $_key => $_item )
				{
					// Found it, keeping it, so remove it from the list, as this becomes adds update if need be
					if ( $_serviceId == ( $_assignId = Option::get( $_item, $_relationKey ) ) )
					{
						// old should be encoded in the db
						if ( Option::get( $_mapping, 'component' ) != ( $_newComponent = json_encode( Option::get( $_item, 'component' ) ) ) )
						{
							$_mapping['component'] = $_newComponent;
							$_updates[] = $_mapping;
						}

						// otherwise throw it out
						unset( $relations[$_key] );

						$_found = true;
						continue;
					}
				}

				if ( !$_found )
				{
					$_deletes[] = $_id;
					continue;
				}

				unset( $_mapping );
			}

			if ( !empty( $_deletes ) )
			{
				// simple delete request
				$_sql = 'delete from ' . $_mapTable . ' where ' . $_mapPrimaryKey . ' in (' . implode( ', ', $_deletes ) . ')';

				if ( !Sql::execute( $_sql ) )
				{
					Log::notice( 'Error deleting rows from ' . $_mapTable . ': ' . $_sql );
				}
			}

			if ( !empty( $_updates ) )
			{
				foreach ( $_updates as $_item )
				{
					// simple update request
					$_itemId = Option::get( $_item, 'id', null, true );

					if ( !static::model()->update( $_mapTable, $_item, 'id = :id', array( ':id' => $_itemId ) ) )
					{
						throw new \CDbException( 'Record update failed' );
					}
				}
			}

			if ( !empty( $relations ) )
			{
				foreach ( $relations as $_item )
				{
					// simple insert request
					$_newComponent = json_encode( Option::get( $_item, 'component' ) );

					$rows = static::model()->insert(
						array(
							 'app_id'     => $id,
							 'service_id' => Option::get( $_item, 'service_id' ),
							 'component'  => $_newComponent
						)
					);

					if ( 0 >= $rows )
					{
						throw new \CDbException( 'Record insert failed.' );
					}
				}
			}
		}
		catch ( \Exception $_ex )
		{
			throw new \CDbException( 'Error updating application service assignment: ' . $_ex->getMessage(), $_ex->getCode() );
		}
	}
}