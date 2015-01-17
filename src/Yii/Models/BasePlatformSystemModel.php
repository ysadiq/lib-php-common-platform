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
namespace DreamFactory\Platform\Yii\Models;

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Option;

/**
 * BasePlatformSystemModel.php
 * A base class for DSP system table models
 *
 * Base Columns:
 *
 * @property integer         $created_by_id
 * @property integer         $last_modified_by_id
 *
 * Base Relations:
 *
 * @property User    $created_by
 * @property User    $last_modified_by
 */
abstract class BasePlatformSystemModel extends BasePlatformModel
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const ALL_ATTRIBUTES = '*';

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @return string the system database table name prefix
	 */
	public static function tableNamePrefix()
	{
		return 'df_sys_';
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'created_by'       => array( static::BELONGS_TO, 'User', 'created_by_id' ),
			'last_modified_by' => array( static::BELONGS_TO, 'User', 'last_modified_by_id' ),
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * @param \CDbCriteria $criteria
	 *
	 * @return \CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search( $criteria = null )
	{
		$_criteria = $criteria ? : new \CDbCriteria;

		$_criteria->compare( 'created_by_id', $this->created_by_id );
		$_criteria->compare( 'last_modified_by_id', $this->last_modified_by_id );

		return parent::search( $criteria );
	}

	/**
	 * {@InheritDoc}
	 */
	public function restMap( $mappings = array() )
	{
		static $_map;

		if ( null === $_map )
		{
			$_map = array( 'created_by_id', 'last_modified_by_id' );
			$_map = array_combine( $_map, $_map );
		}

		//	Default to everything if none are specified
		if ( empty( $mappings ) )
		{
			$mappings = $this->getTableSchema()->getColumnNames();
			$mappings = array_combine( $mappings, $mappings );
		}

		return parent::restMap( $_map + $mappings );
	}

	/**
	 * {@InheritDoc}
	 */
	public function getRetrievableAttributes( $requested, $columns = array(), $hidden = array() )
	{
		if ( empty( $requested ) )
		{
			// primary keys only
			return array( 'id' );
		}

		if ( static::ALL_ATTRIBUTES == $requested )
		{
			return array_merge(
				array(
					'id',
					'created_date',
					'created_by_id',
					'last_modified_date',
					'last_modified_by_id'
				),
				!empty( $columns ) ? $columns : $this->getSafeAttributeNames()
			);
		}

		//	Remove the hidden fields
		$_columns = ( is_string( $requested ) ? explode( ',', $requested ) : $requested ? : array() );

		if ( !empty( $hidden ) )
		{
			$_compare = array_map( 'strtolower', $_columns );

			foreach ( $hidden as $_hide )
			{
				if ( false !== ( $_index = array_search( strtolower( $_hide ), $_compare ) ) )
				{
					unset( $_columns[$_index] );
				}
			}
		}

		return $_columns;
	}

	/**
	 * Add in our additional labels
	 *
	 * @param array $additionalLabels
	 *
	 * @return array
	 */
	public function attributeLabels( $additionalLabels = array() )
	{
		return parent::attributeLabels(
			array(
				'created_by_id'       => 'Created By',
				'last_modified_by_id' => 'Last Modified By',
			) + $additionalLabels
		);
	}

	/**
	 * @return array The model as a resource
	 */
	public function asResource()
	{
		return ResourceStore::buildResponsePayload( $this );
	}

	/**
	 * @param array $values
	 * @param int   $id
	 */
	public function setRelated( $values, $id )
	{
		//	Does nothing here
	}

	/**
	 * @param string $sourceId
	 * @param string $mapTable
	 * @param string $mapColumn
	 * @param array  $targetRows
	 *
	 * @throws \Exception
	 * @throws BadRequestException
	 * @return void
	 */
	protected function assignManyToOne( $sourceId, $mapTable, $mapColumn, $targetRows = array() )
	{
		if ( empty( $sourceId ) )
		{
			throw new BadRequestException( 'The id can not be empty.' );
		}

		/**
		 * Map tables have a
		 */

		try
		{
			$_manyModel = ResourceStore::model( $mapTable );
			$_primaryKey = $_manyModel->tableSchema->primaryKey;
			$mapTable = $_manyModel->tableName();

			// use query builder
			$command = Pii::db()->createCommand();
			$command->select( "$_primaryKey,$mapColumn" );
			$command->from( $mapTable );
			$command->where( "$mapColumn = :oid" );
			$maps = $command->queryAll( true, array( ':oid' => $sourceId ) );

			$toDelete = array();
			foreach ( $maps as $map )
			{
				$id = Option::get( $map, $_primaryKey, '' );
				$found = false;
				foreach ( $targetRows as $key => $item )
				{
					$assignId = Option::get( $item, $_primaryKey, '' );
					if ( $id == $assignId )
					{
						// found it, keeping it, so remove it from the list, as this becomes adds
						unset( $targetRows[$key] );
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
				// simple update to null request
				$command->reset();
				$rows = $command->update( $mapTable, array( $mapColumn => null ), array( 'in', $_primaryKey, $toDelete ) );
				if ( 0 >= $rows )
				{
//					throw new Exception( "Record update failed for table '$mapTable'." );
				}
			}
			if ( !empty( $targetRows ) )
			{
				$toAdd = array();
				foreach ( $targetRows as $item )
				{
					$itemId = Option::get( $item, $_primaryKey, '' );
					if ( !empty( $itemId ) )
					{
						$toAdd[] = $itemId;
					}
				}
				if ( !empty( $toAdd ) )
				{
					// simple update to null request
					$command->reset();
					$rows = $command->update( $mapTable, array( $mapColumn => $sourceId ), array( 'in', $_primaryKey, $toAdd ) );
					if ( 0 >= $rows )
					{
//						throw new Exception( "Record update failed for table '$mapTable'." );
					}
				}
			}
		}
		catch ( \Exception $ex )
		{
			throw new \Exception( "Error updating many to one assignment.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param        $sourceId
	 * @param string $mapTable
	 * @param string $entity The associative entity, or mapping table
	 * @param        $sourceColumn
	 * @param        $mapColumn
	 * @param array  $targetRows
	 *
	 * @throws \Exception
	 * @throws BadRequestException
	 * @return void
	 */
	protected function assignManyToOneByMap( $sourceId, $mapTable, $entity, $sourceColumn, $mapColumn, $targetRows = array() )
	{
		if ( empty( $sourceId ) )
		{
			throw new BadRequestException( "The id can not be empty." );
		}

		$entity = static::tableNamePrefix() . $entity;

		try
		{
			$_manyModel = ResourceStore::model( $mapTable );
			$pkManyField = $_manyModel->tableSchema->primaryKey;
			$pkMapField = 'id';
			//	Use query builder
			$command = Pii::db()->createCommand();
			$command->select( $pkMapField . ',' . $mapColumn );
			$command->from( $entity );
			$command->where( "$sourceColumn = :id" );
			$maps = $command->queryAll( true, array( ':id' => $sourceId ) );

			$toDelete = array();
			foreach ( $maps as $map )
			{
				$manyId = Option::get( $map, $mapColumn, '' );
				$id = Option::get( $map, $pkMapField, '' );
				$found = false;
				foreach ( $targetRows as $key => $item )
				{
					$assignId = Option::get( $item, $pkManyField, '' );
					if ( $assignId == $manyId )
					{
						// found it, keeping it, so remove it from the list, as this becomes adds
						unset( $targetRows[$key] );
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
				$rows = $command->delete( $entity, array( 'in', $pkMapField, $toDelete ) );
				if ( 0 >= $rows )
				{
//					throw new Exception( "Record delete failed for table '$entity'." );
				}
			}
			if ( !empty( $targetRows ) )
			{
				foreach ( $targetRows as $item )
				{
					$itemId = Option::get( $item, $pkManyField, '' );
					$record = array( $mapColumn => $itemId, $sourceColumn => $sourceId );
					// simple update request
					$command->reset();
					$rows = $command->insert( $entity, $record );
					if ( 0 >= $rows )
					{
						throw new \Exception( "Record insert failed for table '$entity'." );
					}
				}
			}
		}
		catch ( \Exception $ex )
		{
			throw new \Exception( "Error updating many to one map assignment.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * Named scope that filters by api_name
	 *
	 * @param string $name
	 *
	 * @return Service
	 */
	public function byApiName( $name )
	{
		if ( $this->hasAttribute( 'api_name' ) )
		{
			$this->getDbCriteria()->mergeWith(
				array(
					'condition' => 'api_name = :api_name',
					'params'    => array( ':api_name' => $name ),
				)
			);
		}

		return $this;
	}

	/**
	 * Checks a relationship request for duplicates
	 *
	 * @param int    $id
	 * @param string $mapColumn
	 * @param array  $relations
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \InvalidArgumentException
	 */
	protected function _checkForRequestDuplicates( $id, $mapColumn, $relations = array() )
	{
		if ( empty( $id ) )
		{
			throw new \InvalidArgumentException( 'No ID specified.' );
		}

		//	Reset indices if needed
		$_relations = array_values( $relations );

		//	Check for dupes before processing
		foreach ( $_relations as $_relation )
		{
			$_checkId = Option::get( $_relation, $mapColumn );

			if ( empty( $_checkId ) )
			{
				continue;
			}

			foreach ( $_relations as $_checkRelation )
			{
				if ( $_checkId != ( $_id = Option::get( $_checkRelation, $mapColumn ) ) )
				{
					continue;
				}
			}

			throw new BadRequestException( 'Duplicate mapping found in app-service relation.' );
		}
	}

}
