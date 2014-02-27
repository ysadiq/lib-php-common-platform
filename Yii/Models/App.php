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
namespace DreamFactory\Platform\Yii\Models;

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\Log;
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
 * @property int                  $storage_service_id
 * @property string               $storage_container
 * @property string               $launch_url
 * @property boolean              $requires_fullscreen
 * @property boolean              $allow_fullscreen_toggle
 * @property string               $toggle_location
 * @property boolean              $requires_plugin
 *
 * Relations:
 *
 * @property Role[]               $roles_default_app
 * @property User[]               $users_default_app
 * @property AppGroup[]           $app_groups
 * @property Role[]               $roles
 * @property AppServiceRelation[] $app_service_relations
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
	protected $launch_url = '';

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
		return array_merge(
			parent::rules(),
			array(
				array( 'name, api_name', 'required' ),
				array( 'name, api_name', 'unique', 'allowEmpty' => false, 'caseSensitive' => false ),
				array( 'storage_service_id', 'numerical', 'integerOnly' => true ),
				array( 'name, api_name', 'length', 'max' => 64 ),
				array( 'storage_container', 'length', 'max' => 255 ),
				array( 'is_active, is_url_external, requires_fullscreen, allow_fullscreen_toggle, requires_plugin', 'boolean' ),
				array( 'description, url, import_url, launch_url, storage_container, toggle_location', 'safe' )
			)
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		$_relations = array(
			'roles_default_app'     => array( static::HAS_MANY, __NAMESPACE__ . '\\Role', 'default_app_id' ),
			'users_default_app'     => array( static::HAS_MANY, __NAMESPACE__ . '\\User', 'default_app_id' ),
			'app_groups'            => array( static::MANY_MANY, __NAMESPACE__ . '\\AppGroup', 'df_sys_app_to_app_group(app_id, app_group_id)' ),
			'roles'                 => array( static::MANY_MANY, __NAMESPACE__ . '\\Role', 'df_sys_app_to_role(app_id, role_id)' ),
			'app_service_relations' => array( static::HAS_MANY, __NAMESPACE__ . '\\AppServiceRelation', 'app_id' ),
			'services'              => array( static::MANY_MANY, __NAMESPACE__ . '\\Service', 'df_sys_app_to_service(app_id, service_id)' ),
			'storage_service'       => array( static::BELONGS_TO, __NAMESPACE__ . '\\Service', 'storage_service_id' ),
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

	/** {@InheritDoc} */
	protected function beforeValidate()
	{
		if ( empty( $this->storage_service_id ) )
		{
			$this->storage_service_id = null;
		}

		return parent::beforeValidate();
	}

	/**
	 * {@InheritDoc}
	 */
	protected function beforeSave()
	{
		if ( empty( $this->url ) && !$this->is_url_external && !empty( $this->storage_service_id ) )
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
		if ( !$this->is_url_external )
		{
			if ( !empty( $this->storage_service_id ) )
			{
				$this->launch_url = Curl::currentUrl( false, false ) . '/';
				/** @var $_service Service */
				$_service = $this->getRelated( 'storage_service' );
				if ( !empty( $_service ) )
				{
					$this->launch_url .= $_service->api_name . '/';
				}
				if ( !empty( $this->storage_container ) )
				{
					$this->launch_url .= $this->storage_container . '/';
				}
				$this->launch_url .= $this->api_name . $this->url;
			}
			else
			{
				$this->launch_url = '';
			}
		}
		else
		{
			$this->launch_url = $this->url;
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

		if ( !$this->is_url_external )
		{
			if ( !empty( $this->storage_service_id ) )
			{
				$this->launch_url = Curl::currentUrl( false, false ) . '/';
				/** @var $_service Service */
				$_service = $this->getRelated( 'storage_service' );
				if ( !empty( $_service ) )
				{
					$this->launch_url .= $_service->api_name . '/';
				}
				if ( !empty( $this->storage_container ) )
				{
					$this->launch_url .= $this->storage_container . '/';
				}
				$this->launch_url .= $this->api_name . $this->url;
			}
		}
		else
		{
			$this->launch_url = $this->url;
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
							 'launch_url',
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
	 * @param int   $id The row ID
	 * @param array $relations
	 *
	 * @throws \Exception
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \CDbException
	 * @return void
	 */
	protected function assignAppServiceRelations( $id, $relations = array() )
	{
		if ( empty( $id ) )
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
				$_access = $relations[$_key1];
				$_serviceId = Option::get( $_access, 'service_id' );
				for ( $_key2 = $_key1 + 1; $_key2 < $_count; $_key2++ )
				{
					$_access2 = $relations[$_key2];
					$_serviceId2 = Option::get( $_access2, 'service_id' );
					if ( $_serviceId == $_serviceId2 )
					{
						throw new BadRequestException( "Duplicated service in app service relation." );
					}
				}
			}
			$_mapTable = static::tableNamePrefix() . 'app_to_service';
			$_mapPrimaryKey = 'id';
			// use query builder
			/** @var \CDbCommand $_command */
			$_command = Pii::db()->createCommand();
			$_command->select( 'id,service_id,component' );
			$_command->from( $_mapTable );
			$_command->where( 'app_id = :aid' );
			$_maps = $_command->queryAll( true, array( ':aid' => $id ) );
			$_deletes = array();
			$_updates = array();
			foreach ( $_maps as $_map )
			{
				$_manyId = Option::get( $_map, 'service_id' );
				$id = Option::get( $_map, $_mapPrimaryKey, '' );
				$_found = false;
				foreach ( $relations as $_key => $_item )
				{
					$_assignId = Option::get( $_item, 'service_id' );
					if ( $_assignId == $_manyId )
					{
						// found it, keeping it, so remove it from the list, as this becomes adds
						// update if need be
						$_oldComponent = Option::get( $_map, 'component' );
						$_newComponent = Option::get( $_item, 'component' );
						if ( !empty( $_newComponent ) )
						{
							$_newComponent = json_encode( $_newComponent );
						}
						else
						{
							$_newComponent = null; // no empty arrays here
						}
						// old should be encoded in the db
						if ( $_oldComponent != $_newComponent )
						{
							$_map['component'] = $_newComponent;
							$_updates[] = $_map;
						}
						// otherwise throw it out
						unset( $relations[$_key] );
						$_found = true;
						continue;
					}
				}
				if ( !$_found )
				{
					$_deletes[] = $id;
					continue;
				}
			}
			if ( !empty( $_deletes ) )
			{
				// simple delete request
				$_command->reset();
				$_command->delete( $_mapTable, array( 'in', $_mapPrimaryKey, $_deletes ) );
			}
			if ( !empty( $_updates ) )
			{
				foreach ( $_updates as $_item )
				{
					$_itemId = Option::get( $_item, 'id' );
					unset( $_item['id'] );
					// simple update request
					$_command->reset();
					$rows = $_command->update( $_mapTable, $_item, 'id = :id', array( ':id' => $_itemId ) );
					if ( 0 >= $rows )
					{
						throw new \CDbException( "Record update failed." );
					}
				}
			}
			if ( !empty( $relations ) )
			{
				foreach ( $relations as $_item )
				{
					// simple insert request
					$_newComponent = Option::get( $_item, 'component' );
					if ( !empty( $_newComponent ) )
					{
						$_newComponent = json_encode( $_newComponent );
					}
					else
					{
						$_newComponent = null; // no empty arrays here
					}
					$_record = array(
						'app_id'     => $id,
						'service_id' => Option::get( $_item, 'service_id' ),
						'component'  => $_newComponent
					);
					$_command->reset();
					$rows = $_command->insert( $_mapTable, $_record );
					if ( 0 >= $rows )
					{
						throw new \Exception( "Record insert failed." );
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
	protected function _mapRelations( $id, $relations = array(), $relationKey = 'service_id' )
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

			/** @var \CDbCommand $_command */
			$_mapRows = Sql::findAll(
						   <<<MYSQL
		SELECT
	id,
	{$relationKey},
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
				$_serviceId = Option::get( $_mapping, $relationKey );
				$_id = Option::get( $_mapping, $_mapPrimaryKey );

				$_found = false;

				foreach ( $relations as $_key => $_item )
				{
					// Found it, keeping it, so remove it from the list, as this becomes adds update if need be
					if ( $_serviceId == ( $_assignId = Option::get( $_item, $relationKey ) ) )
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