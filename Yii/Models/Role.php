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

use Kisma\Core\Exceptions\StorageException;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Platform\Utility\Pii;

/**
 * Role.php
 * The system role model for the DSP
 *
 * Columns:
 *
 * @property string              $name
 * @property string              $description
 * @property integer             $is_active
 * @property integer             $default_app_id
 *
 * Relations:
 *
 * @property App                 $default_app
 * @property RoleServiceAccess[] $role_service_accesses
 * @property User[]              $users
 * @property App[]               $apps
 * @property Service[]           $services
 */
class Role extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'role';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		$_rules = array(
			array( 'name', 'required' ),
			array( 'name', 'unique', 'allowEmpty' => false, 'caseSensitive' => false ),
			array( 'is_active, default_app_id', 'numerical', 'integerOnly' => true ),
			array( 'name', 'length', 'max' => 64 ),
			array( 'description', 'safe' ),
			array( 'id, name, is_active, default_app_id', 'safe', 'on' => 'search' ),
		);

		return array_merge( parent::rules(), $_rules );
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		$_relations = array(
			'default_app'           => array( self::BELONGS_TO, __NAMESPACE__ . '\\App', 'default_app_id' ),
			'role_service_accesses' => array( self::HAS_MANY, __NAMESPACE__ . '\\RoleServiceAccess', 'role_id' ),
			'users'                 => array( self::HAS_MANY, __NAMESPACE__ . '\\User', 'role_id' ),
			'apps'                  => array( self::MANY_MANY, __NAMESPACE__ . '\\App', 'df_sys_app_to_role(app_id, role_id)' ),
			'services'              => array( self::MANY_MANY, __NAMESPACE__ . '\\Service', 'df_sys_role_service_access(role_id, service_id)' ),
		);

		return array_merge( parent::relations(), $_relations );
	}

	/**
	 * @param array $additionalLabels
	 *
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels( $additionalLabels = array() )
	{
		$_labels = array(
			'name'           => 'Name',
			'description'    => 'Description',
			'is_active'      => 'Is Active',
			'default_app_id' => 'Default App',
		);

		return parent::attributeLabels( array_merge( $_labels, $additionalLabels ) );
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * @param mixed $criteria
	 *
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search( $criteria = null )
	{
		$_criteria = $criteria ? : new \CDbCriteria();

		$_criteria->compare( 'name', $this->name, true );
		$_criteria->compare( 'is_active', $this->is_active );
		$_criteria->compare( 'default_app_id', $this->default_app_id );

		return parent::search( $_criteria );
	}

	/**
	 * @param array $values
	 * @param int   $id
	 */
	public function setRelated( $values, $id )
	{
		if ( isset( $values['role_service_accesses'] ) )
		{
			$this->assignRoleServiceAccesses( $id, $values['role_service_accesses'] );
		}

		if ( isset( $values['apps'] ) )
		{
			$this->assignManyToOneByMap( $id, 'app', 'app_to_role', 'role_id', 'app_id', $values['apps'] );
		}

		if ( isset( $values['users'] ) )
		{
			$this->assignManyToOne( $id, 'user', 'role_id', $values['users'] );
		}

		if ( isset( $values['services'] ) )
		{
			$this->assignManyToOneByMap( $id, 'service', 'role_service_access', 'role_id', 'service_id', $values['services'] );
		}
	}

	/**
	 * @param \CModelEvent $event
	 *
	 * @throws StorageException
	 */
	public function onBeforeDelete( $event )
	{
		if ( Pii::getState( 'role_id' ) == $this->id )
		{
			throw new StorageException( 'The current role may not be deleted.' );
		}

		parent::onBeforeDelete( $event );
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
					 'description',
					 'is_active',
					 'default_app_id',
				),
				$columns
			),
			$hidden
		);
	}

	/**
	 * @param       $role_id
	 * @param array $accesses
	 *
	 * @throws Exception
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return void
	 */
	protected function assignRoleServiceAccesses( $role_id, $accesses = array() )
	{
		if ( empty( $role_id ) )
		{
			throw new BadRequestException( 'Role ID can not be empty.' );
		}

		try
		{
			$accesses = array_values( $accesses ); // reset indices if needed
			$count = count( $accesses );

			// check for dupes before processing
			for ( $key1 = 0; $key1 < $count; $key1++ )
			{
				$access = $accesses[$key1];
				$serviceId = Utilities::getArrayValue( 'service_id', $access, null );
				$component = Utilities::getArrayValue( 'component', $access, '' );

				for ( $key2 = $key1 + 1; $key2 < $count; $key2++ )
				{
					$access2 = $accesses[$key2];
					$serviceId2 = Utilities::getArrayValue( 'service_id', $access2, null );
					$component2 = Utilities::getArrayValue( 'component', $access2, '' );
					if ( ( $serviceId == $serviceId2 ) && ( $component == $component2 ) )
					{
						throw new BadRequestException( "Duplicated service and component combination '$serviceId $component' in role service access." );
					}
				}
			}

			$map_table = static::tableNamePrefix() . 'role_service_access';
			$pkMapField = 'id';
			// use query builder
			$command = Pii::db()->createCommand();
			$command->select( 'id,service_id,component,access' );
			$command->from( $map_table );
			$command->where( 'role_id = :id' );
			$maps = $command->queryAll( true, array( ':id' => $role_id ) );
			$toDelete = array();
			$toUpdate = array();
			foreach ( $maps as $map )
			{
				$manyId = Utilities::getArrayValue( 'service_id', $map, null );
				$manyComponent = Utilities::getArrayValue( 'component', $map, '' );
				$id = Utilities::getArrayValue( $pkMapField, $map, '' );
				$found = false;
				foreach ( $accesses as $key => $item )
				{
					$assignId = Utilities::getArrayValue( 'service_id', $item, null );
					$assignComponent = Utilities::getArrayValue( 'component', $item, '' );
					if ( ( $assignId == $manyId ) && ( $assignComponent == $manyComponent ) )
					{
						// found it, make sure nothing needs to be updated
						$oldAccess = Utilities::getArrayValue( 'access', $map, '' );
						$newAccess = Utilities::getArrayValue( 'access', $item, '' );
						if ( ( $oldAccess != $newAccess ) )
						{
							$map['access'] = $newAccess;
							$toUpdate[] = $map;
						}
						// otherwise throw it out
						unset( $accesses[$key] );
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
				$command->reset();
				$rows = $command->delete( $map_table, array( 'in', $pkMapField, $toDelete ) );
			}
			if ( !empty( $toUpdate ) )
			{
				foreach ( $toUpdate as $item )
				{
					$itemId = Utilities::getArrayValue( 'id', $item, '' );
					unset( $item['id'] );
					// simple update request
					$command->reset();
					$rows = $command->update( $map_table, $item, 'id = :id', array( ':id' => $itemId ) );
					if ( 0 >= $rows )
					{
						throw new Exception( "Record update failed." );
					}
				}
			}
			if ( !empty( $accesses ) )
			{
				foreach ( $accesses as $item )
				{
					// simple insert request
					$record = array(
						'role_id'    => $role_id,
						'service_id' => Utilities::getArrayValue( 'service_id', $item, null ),
						'component'  => Utilities::getArrayValue( 'component', $item, '' ),
						'access'     => Utilities::getArrayValue( 'access', $item, '' )
					);
					$command->reset();
					$rows = $command->insert( $map_table, $record );
					if ( 0 >= $rows )
					{
						throw new Exception( "Record insert failed." );
					}
				}
			}
		}
		catch ( Exception $ex )
		{
			throw new Exception( "Error updating accesses to role assignment.\n{$ex->getMessage()}" );
		}
	}
}