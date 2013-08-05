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

use DreamFactory\Platform\Enums\PermissionMap;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\FilterInput;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Services\SystemManager;

/**
 * BaseDbSvc
 *
 * A base service class to handle generic db services accessed through the REST API.
 */
abstract class BaseDbSvc extends BasePlatformRestService
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var int|string
	 */
	protected $_resourceId;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param array $settings
	 */
	public function __construct( $settings = array() )
	{
		if ( null === Option::get( $settings, 'verb_aliases' ) )
		{
			//	Default verb aliases
			$settings['verb_aliases'] = array(
				static::Put   => static::Post,
				static::Merge => static::Post,
				static::Patch => static::Post,
			);
		}

		parent::__construct( $settings );
	}

	/**
	 * Ensures a table is not a system table
	 *
	 * @param string $table
	 *
	 * @throws NotFoundException
	 */
	protected static function _checkTable( $table )
	{
		static $_length;

		if ( !$_length )
		{
			$_length = SystemManager::SYSTEM_TABLE_PREFIX;
		}

		foreach ( Option::clean( $table ) as $_table )
		{
			if ( 0 === substr_compare( $_table, SystemManager::SYSTEM_TABLE_PREFIX, 0, $_length ) )
			{
				throw new NotFoundException( "Table '$_table' not found." );
			}
		}
	}

	/**
	 * {@InheritDoc}
	 */
	protected function _preProcess()
	{
		parent::_preProcess();

		//	Do validation here
		if ( !empty( $this->_resource ) )
		{
			$this->validateTableAccess(
				$this->_resource,
				PermissionMap::fromMethod( $this->getRequestedAction() )
			);
		}
	}

	/**
	 * @return array|bool
	 */
	protected function _handleResource()
	{
		if ( empty( $this->_resource ) )
		{
			return $this->_handleAdmin();
		}

		return parent::_handleResource();
	}

	/**
	 * @return array|bool
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _handleAdmin()
	{
		switch ( $this->_action )
		{
			case self::Get:
				$_properties = FilterInput::request( 'include_properties', false, FILTER_VALIDATE_BOOLEAN );

				if ( empty( $ids ) )
				{
					$_data = RestData::getPostDataAsArray();
					$ids = Option::get( $data, 'ids' );
				}

				if ( !$_properties && empty( $ids ) )
				{
					return $this->_listResources();
				}

				$result = $this->getTables( $ids );
				$result = array( 'table' => $result );
				break;

			case self::Post:
				$this->checkPermission( 'create' );
				$data = RestData::getPostDataAsArray();
				$tables = Option::get( $data, 'table', null );
				if ( empty( $tables ) )
				{
					// xml to array conversion leaves them in plural wrapper
					$tables = Option::getDeep( $data, 'tables', 'table' );
				}
				if ( empty( $tables ) )
				{
					$_name = Option::get( $data, 'name' );
					if ( empty( $_name ) )
					{
						throw new BadRequestException( 'No table name in POST create request.' );
					}
					$result = $this->createTable( $_name, $data );
				}
				else
				{
					$rollback = FilterInput::request( 'rollback', false, FILTER_VALIDATE_BOOLEAN );
					$result = $this->createTables( $tables, $rollback );
					$result = array( 'table' => $result );
				}
				break;
			case self::Put:
			case self::Patch:
			case self::Merge:
				$this->checkPermission( 'update' );
				$data = RestData::getPostDataAsArray();
				$tables = Option::get( $data, 'table', null );
				if ( empty( $tables ) )
				{
					// xml to array conversion leaves them in plural wrapper
					$tables = Option::getDeep( $data, 'tables', 'table' );
				}
				if ( empty( $tables ) )
				{
					$_name = Option::get( $data, 'name' );
					if ( empty( $_name ) )
					{
						throw new BadRequestException( 'No table name in POST create request.' );
					}
					$result = $this->updateTable( $_name, $data );
				}
				else
				{
					$rollback = FilterInput::request( 'rollback', false, FILTER_VALIDATE_BOOLEAN );
					$result = $this->updateTables( $tables, $rollback );
					$result = array( 'table' => $result );
				}
				break;
			case self::Delete:
				$this->checkPermission( 'delete' );
				$data = RestData::getPostDataAsArray();
				$rollback = FilterInput::request( 'rollback', false, FILTER_VALIDATE_BOOLEAN );
				$ids = FilterInput::request( 'ids' );
				if ( empty( $ids ) )
				{
					$ids = Option::get( $data, 'ids', '' );
				}
				if ( !empty( $ids ) )
				{
					$result = $this->deleteTables( $ids, $rollback );
					$result = array( 'table' => $result );
				}
				else
				{
					$tables = Option::get( $data, 'table' );
					if ( empty( $tables ) )
					{
						// xml to array conversion leaves them in plural wrapper
						$tables = Option::getDeep( $data, 'tables', 'table' );
					}
					if ( empty( $tables ) )
					{
						$_name = Option::get( $data, 'name' );
						if ( empty( $_name ) )
						{
							throw new BadRequestException( 'No table name in DELETE request.' );
						}
						$result = $this->deleteTable( $_name );
					}
					else
					{
						$result = $this->deleteTables( $tables, $rollback );
						$result = array( 'table' => $result );
					}
				}
				break;
			default:
				return false;
		}

		return $result;
	}

	/**
	 *
	 */
	protected function _detectResourceMembers()
	{
		parent::_detectResourceMembers();

		$this->_resourceId = ( isset( $this->_resourceArray, $this->_resourceArray[1] ) ) ? $this->_resourceArray[1] : '';
	}

	/**
	 * @return array
	 */
	protected function _gatherExtrasFromRequest()
	{
		$_extras = array();

		// means to override the default identifier field for a table
		$_extras['id_field'] = FilterInput::request( 'id_field' );
		// most DBs support the following filter extras
		// accept top as well as limit
		$_extras['limit'] = FilterInput::request( 'limit', FilterInput::request( 'top', 0 ), FILTER_VALIDATE_INT );
		// accept skip as well as offset
		$_extras['offset'] = FilterInput::request( 'offset', FilterInput::request( 'skip', 0 ), FILTER_VALIDATE_INT );
		// accept sort as well as order
		$_extras['order'] = FilterInput::request( 'order', FilterInput::request( 'sort' ) );
		$_extras['include_count'] = FilterInput::request( 'include_count', false, FILTER_VALIDATE_BOOLEAN );

		return $_extras;
	}

	/**
	 * @param string $table
	 * @param string $access
	 *
	 * @throws BadRequestException
	 */
	protected function validateTableAccess( $table, $access = 'read' )
	{
		if ( empty( $table ) )
		{
			throw new BadRequestException( 'Table name can not be empty.' );
		}

		// finally check that the current user has privileges to access this table
		$this->checkPermission( $access, $table );
	}

	// Handle administrative options, table add, delete, etc

	/**
	 * Get multiple tables and their properties
	 *
	 * @param array $tables Table names
	 *
	 * @return array
	 * @throws \Exception
	 */
	abstract public function getTables( $tables = array() );

	/**
	 * Get any properties related to the table
	 *
	 * @param string $table Table name
	 *
	 * @return array
	 * @throws \Exception
	 */
	abstract public function getTable( $table );

	/**
	 * Create one or more tables by array of table properties
	 *
	 * @param array $tables
	 *
	 * @throws \Exception
	 */
	abstract public function createTables( $tables = array() );

	/**
	 * Create a single table by name, additional properties
	 *
	 * @param string $table
	 * @param array  $properties
	 *
	 * @throws \Exception
	 */
	abstract public function createTable( $table, $properties = array() );

	/**
	 * Update properties related to the table
	 *
	 * @param array $tables
	 *
	 * @return array
	 * @throws \Exception
	 */
	abstract public function updateTables( $tables = array() );

	/**
	 * Update properties related to the table
	 *
	 * @param string $table Table name
	 * @param array  $properties
	 *
	 * @return array
	 * @throws \Exception
	 */
	abstract public function updateTable( $table, $properties = array() );

	/**
	 * Delete multiple tables and all of their contents
	 *
	 * @param array $tables
	 * @param bool  $check_empty
	 *
	 * @return array
	 * @throws \Exception
	 */
	abstract public function deleteTables( $tables = array(), $check_empty = false );

	/**
	 * Delete the table and all of its contents
	 *
	 * @param string $table
	 * @param bool   $check_empty
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function deleteTable( $table, $check_empty = false );

	// Handle table record operations

	/**
	 * @param        $table
	 * @param        $records
	 * @param bool   $rollback
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function createRecords( $table, $records, $rollback = false, $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function createRecord( $table, $record, $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $records
	 * @param string $id_field
	 * @param bool   $rollback
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function updateRecords( $table, $records, $id_field = '', $rollback = false, $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function updateRecord( $table, $record, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param string $filter
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function updateRecordsByFilter( $table, $record, $filter = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param        $id_list
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function updateRecordsByIds( $table, $record, $id_list, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param        $id
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function updateRecordById( $table, $record, $id, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $records
	 * @param string $id_field
	 * @param bool   $rollback
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function mergeRecords( $table, $records, $id_field = '', $rollback = false, $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function mergeRecord( $table, $record, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param string $filter
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function mergeRecordsByFilter( $table, $record, $filter = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param        $id_list
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function mergeRecordsByIds( $table, $record, $id_list, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param        $id
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function mergeRecordById( $table, $record, $id, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $records
	 * @param string $id_field
	 * @param bool   $rollback
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function deleteRecords( $table, $records, $id_field = '', $rollback = false, $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function deleteRecord( $table, $record, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $filter
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function deleteRecordsByFilter( $table, $filter, $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $id_list
	 * @param string $id_field
	 * @param bool   $rollback
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function deleteRecordsByIds( $table, $id_list, $id_field = '', $rollback = false, $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $id
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function deleteRecordById( $table, $id, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param mixed  $filter
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function retrieveRecordsByFilter( $table, $filter, $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $records
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function retrieveRecords( $table, $records, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $record
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function retrieveRecord( $table, $record, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $id_list
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function retrieveRecordsByIds( $table, $id_list, $id_field = '', $fields = '', $extras = array() );

	/**
	 * @param        $table
	 * @param        $id
	 * @param string $id_field
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function retrieveRecordById( $table, $id, $id_field = '', $fields = '', $extras = array() );
}
