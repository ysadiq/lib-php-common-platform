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
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * BaseDbSvc
 *
 * A base service class to handle generic db services accessed through the REST API.
 */
abstract class BaseDbSvc extends BasePlatformRestService
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * Default record identifier field
	 */
	const DEFAULT_ID_FIELD = 'id';

	/**
	 * Default maximum records returned on filter request
	 */
	const DB_MAX_RECORDS_RETURNED = 1000;

	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var int|string
	 */
	protected $_resourceId = null;

	/**
	 * @var boolean
	 */
	protected $_useBlendFormat = true;

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
				static::Patch => static::Merge,
			);
		}

		parent::__construct( $settings );
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
		else
		{
			$this->checkPermission( PermissionMap::fromMethod( $this->getRequestedAction() ) );
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

				$_ids = FilterInput::request( 'names' );
				if ( empty( $_ids ) )
				{
					$_data = RestData::getPostDataAsArray();
					$_ids = Option::get( $_data, 'names' );
				}

				if ( !$_properties && empty( $_ids ) )
				{
					return $this->_listResources();
				}

				$_result = $this->getTables( $_ids );
				$_result = array( 'table' => $_result );
				break;

			case self::Post:
				$_data = RestData::getPostDataAsArray();
				$_tables = Option::get( $_data, 'table', null );
				if ( empty( $_tables ) )
				{
					// xml to array conversion leaves them in plural wrapper
					$_tables = Option::getDeep( $_data, 'tables', 'table' );
				}
				if ( empty( $_tables ) )
				{
					$_result = $this->createTable( $_data );
				}
				else
				{
					$_result = $this->createTables( $_tables );
					$_result = array( 'table' => $_result );
				}
				break;
			case self::Put:
			case self::Patch:
			case self::Merge:
				$_data = RestData::getPostDataAsArray();
				$_tables = Option::get( $_data, 'table', null );
				if ( empty( $_tables ) )
				{
					// xml to array conversion leaves them in plural wrapper
					$_tables = Option::getDeep( $_data, 'tables', 'table' );
				}
				if ( empty( $_tables ) )
				{
					$_result = $this->updateTable( $_data );
				}
				else
				{
					$_result = $this->updateTables( $_tables );
					$_result = array( 'table' => $_result );
				}
				break;
			case self::Delete:
				$_data = RestData::getPostDataAsArray();
				$_ids = FilterInput::request( 'names' );
				if ( empty( $_ids ) )
				{
					$_ids = Option::get( $_data, 'names', '' );
				}
				if ( !empty( $_ids ) )
				{
					$_result = $this->deleteTables( $_ids );
					$_result = array( 'table' => $_result );
				}
				else
				{
					$_tables = Option::get( $_data, 'table' );
					if ( empty( $_tables ) )
					{
						// xml to array conversion leaves them in plural wrapper
						$_tables = Option::getDeep( $_data, 'tables', 'table' );
					}
					if ( empty( $_tables ) )
					{
						$_name = Option::get( $_data, 'name' );
						if ( empty( $_name ) )
						{
							throw new BadRequestException( 'No table name in DELETE request.' );
						}
						$_result = $this->deleteTable( $_name );
					}
					else
					{
						$_result = $this->deleteTables( $_tables );
						$_result = array( 'table' => $_result );
					}
				}
				break;
			default:
				return false;
		}

		return $_result;
	}

	/**
	 * @return array
	 */
	protected function _handleGet()
	{
		$_data = RestData::getPostDataAsArray();
		// Most requests contain 'returned fields' parameter, all by default
		$_fields = FilterInput::request( 'fields', '*' );
		$_extras = $this->_gatherExtrasFromRequest( $_data );
		if ( empty( $this->_resourceId ) )
		{
			$_ids = FilterInput::request( 'ids' );
			if ( empty( $_ids ) )
			{
				$_ids = Option::get( $_data, 'ids' );
			}
			if ( !empty( $_ids ) )
			{
				$_result = $this->retrieveRecordsByIds( $this->_resource, $_ids, $_fields, $_extras );
				$_result = array( 'record' => $_result );
			}
			else
			{
				$_records = Option::get( $_data, 'record' );
				if ( empty( $_records ) )
				{
					// xml to array conversion leaves them in plural wrapper
					$_records = Option::getDeep( $_data, 'records', 'record' );
				}
				if ( !empty( $_records ) )
				{
					// passing records to have them updated with new or more values, id field required
					$_result = $this->retrieveRecords( $this->_resource, $_records, $_fields, $_extras );
					$_result = array( 'record' => $_result );
				}
				else
				{
					$_filter = FilterInput::request( 'filter' );
					if ( empty( $_filter ) )
					{
						$_filter = Option::get( $_data, 'filter' );
					}
					if ( empty( $_filter ) && !empty( $_data ) )
					{
						// query by record map
						$_result = $this->retrieveRecord( $this->_resource, $_data, $_fields, $_extras );
					}
					else
					{
						$_result = $this->retrieveRecordsByFilter( $this->_resource, $_filter, $_fields, $_extras );
						if ( isset( $_result['meta'] ) )
						{
							$_meta = $_result['meta'];
							unset( $_result['meta'] );
							$_result = array( 'record' => $_result, 'meta' => $_meta );
						}
						else
						{
							$_result = array( 'record' => $_result );
						}
					}
				}
			}
		}
		else
		{
			// single entity by id
			$_result = $this->retrieveRecordById( $this->_resource, $this->_resourceId, $_fields, $_extras );
		}

		return $_result;
	}

	/**
	 * @return array
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _handlePost()
	{
		$_data = RestData::getPostDataAsArray();
		if ( empty( $_data ) )
		{
			throw new BadRequestException( 'No record(s) in create request.' );
		}

		// Most requests contain 'returned fields' parameter
		$_fields = FilterInput::request( 'fields', '' );
		$_extras = $this->_gatherExtrasFromRequest( $_data );
		$_records = Option::get( $_data, 'record' );
		if ( empty( $_records ) )
		{
			// xml to array conversion leaves them in plural wrapper
			$_records = Option::getDeep( $_data, 'records', 'record' );
		}
		if ( empty( $_records ) )
		{
			$_result = $this->createRecord( $this->_resource, $_data, $_fields, $_extras );
		}
		else
		{
			$_result = $this->createRecords( $this->_resource, $_records, $_fields, $_extras );
			$_result = array( 'record' => $_result );
		}

		return $_result;
	}

	/**
	 * @return array
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _handlePut()
	{
		$_data = RestData::getPostDataAsArray();
		if ( empty( $_data ) )
		{
			throw new BadRequestException( 'No record(s) in update request.' );
		}

		// Most requests contain 'returned fields' parameter
		$_fields = FilterInput::request( 'fields', '' );
		$_extras = $this->_gatherExtrasFromRequest( $_data );
		if ( empty( $this->_resourceId ) )
		{
			$_ids = FilterInput::request( 'ids' );
			if ( empty( $_ids ) )
			{
				$_ids = Option::get( $_data, 'ids' );
			}
			if ( !empty( $_ids ) )
			{
				$_result = $this->updateRecordsByIds( $this->_resource, $_data, $_ids, $_fields, $_extras );
				$_result = array( 'record' => $_result );
			}
			else
			{
				$_filter = FilterInput::request( 'filter' );
				if ( empty( $_filter ) )
				{
					$_filter = Option::get( $_data, 'filter' );
				}
				if ( !empty( $_filter ) )
				{
					$_result = $this->updateRecordsByFilter( $this->_resource, $_data, $_filter, $_fields, $_extras );
					$_result = array( 'record' => $_result );
				}
				else
				{
					$_records = Option::get( $_data, 'record' );
					if ( empty( $_records ) )
					{
						// xml to array conversion leaves them in plural wrapper
						$_records = Option::getDeep( $_data, 'records', 'record' );
					}
					if ( empty( $_records ) )
					{
						$_result = $this->updateRecord( $this->_resource, $_data, $_fields, $_extras );
					}
					else
					{
						$_result = $this->updateRecords( $this->_resource, $_records, $_fields, $_extras );
						$_result = array( 'record' => $_result );
					}
				}
			}
		}
		else
		{
			$_result = $this->updateRecordById( $this->_resource, $_data, $this->_resourceId, $_fields, $_extras );
		}

		return $_result;
	}

	/**
	 * @return array
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _handleMerge()
	{
		$_data = RestData::getPostDataAsArray();
		if ( empty( $_data ) )
		{
			throw new BadRequestException( 'No record(s) in merge request.' );
		}

		// Most requests contain 'returned fields' parameter
		$_fields = FilterInput::request( 'fields', '' );
		$_extras = $this->_gatherExtrasFromRequest( $_data );
		if ( empty( $this->_resourceId ) )
		{
			$_ids = FilterInput::request( 'ids' );
			if ( empty( $_ids ) )
			{
				$_ids = Option::get( $_data, 'ids' );
			}
			if ( !empty( $_ids ) )
			{
				$_result = $this->mergeRecordsByIds( $this->_resource, $_data, $_ids, $_fields, $_extras );
				$_result = array( 'record' => $_result );
			}
			else
			{
				$_filter = FilterInput::request( 'filter' );
				if ( empty( $_filter ) )
				{
					$_filter = Option::get( $_data, 'filter' );
				}
				if ( !empty( $_filter ) )
				{
					$_result = $this->mergeRecordsByFilter( $this->_resource, $_data, $_filter, $_fields, $_extras );
					$_result = array( 'record' => $_result );
				}
				else
				{
					$_records = Option::get( $_data, 'record' );
					if ( empty( $_records ) )
					{
						// xml to array conversion leaves them in plural wrapper
						$_records = Option::getDeep( $_data, 'records', 'record' );
					}
					if ( empty( $_records ) )
					{
						$_result = $this->mergeRecord( $this->_resource, $_data, $_fields, $_extras );
					}
					else
					{
						$_result = $this->mergeRecords( $this->_resource, $_records, $_fields, $_extras );
						$_result = array( 'record' => $_result );
					}
				}
			}
		}
		else
		{
			$_result = $this->mergeRecordById( $this->_resource, $_data, $this->_resourceId, $_fields, $_extras );
		}

		return $_result;
	}

	/**
	 * @return array
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _handleDelete()
	{
		$_data = RestData::getPostDataAsArray();
		// Most requests contain 'returned fields' parameter
		$_fields = FilterInput::request( 'fields', '' );
		$_extras = $this->_gatherExtrasFromRequest( $_data );
		if ( empty( $this->_resourceId ) )
		{
			$_ids = FilterInput::request( 'ids' );
			if ( empty( $_ids ) )
			{
				$_ids = Option::get( $_data, 'ids', '' );
			}
			if ( !empty( $_ids ) )
			{
				$_result = $this->deleteRecordsByIds( $this->_resource, $_ids, $_fields, $_extras );
				$_result = array( 'record' => $_result );
			}
			else
			{
				$_filter = FilterInput::request( 'filter' );
				if ( empty( $_filter ) )
				{
					$_filter = Option::get( $_data, 'filter' );
				}
				if ( !empty( $_filter ) )
				{
					$_result = $this->deleteRecordsByFilter( $this->_resource, $_filter, $_fields, $_extras );
					$_result = array( 'record' => $_result );
				}
				else
				{
					$_records = Option::get( $_data, 'record' );
					if ( empty( $_records ) )
					{
						// xml to array conversion leaves them in plural wrapper
						$_records = Option::getDeep( $_data, 'records', 'record' );
					}
					if ( empty( $_records ) )
					{
						if ( empty( $_data ) )
						{
							if ( !FilterInput::request( 'force', false, FILTER_VALIDATE_BOOLEAN ) )
							{
								throw new BadRequestException( 'No filter or records given for delete request.' );
							}
							$_extras['force'] = true;
							$_result = $this->deleteRecords( $this->_resource, null, $_fields, $_extras );
						}
						else
						{
							$_result = $this->deleteRecord( $this->_resource, $_data, $_fields, $_extras );
						}
					}
					else
					{
						$_result = $this->deleteRecords( $this->_resource, $_records, $_fields, $_extras );
						$_result = array( 'record' => $_result );
					}
				}
			}
		}
		else
		{
			$_result = $this->deleteRecordById( $this->_resource, $this->_resourceId, $_fields, $_extras );
		}

		return $_result;
	}

	/**
	 *
	 */
	protected function _detectResourceMembers( $resourcePath = null )
	{
		parent::_detectResourceMembers( $resourcePath );

		$this->_resourceId = ( isset( $this->_resourceArray, $this->_resourceArray[1] ) ) ? $this->_resourceArray[1] : '';
	}

	/**
	 * @param null|array $post_data
	 *
	 * @return array
	 */
	protected function _gatherExtrasFromRequest( $post_data = null )
	{
		$_extras = array();

		// means to override the default identifier field for a table
		// or supply one when there is no default designated
		$_idField = FilterInput::request( 'id_field' );
		if ( empty( $_idField ) && !empty( $post_data ) )
		{
			$_idField = Option::get( $post_data, 'id_field' );
		}
		$_extras['id_field'] = $_idField;

		// most DBs support the following filter extras
		// accept top as well as limit
		$_limit = FilterInput::request( 'limit', FilterInput::request( 'top', 0, FILTER_VALIDATE_INT ), FILTER_VALIDATE_INT );
		if ( empty( $_limit ) && !empty( $post_data ) )
		{
			$_limit = Option::get( $post_data, 'limit' );
		}
		$_extras['limit'] = $_limit;

		// accept skip as well as offset
		$_offset = FilterInput::request( 'offset', FilterInput::request( 'skip', 0, FILTER_VALIDATE_INT ), FILTER_VALIDATE_INT );
		if ( empty( $_offset ) && !empty( $post_data ) )
		{
			$_offset = Option::get( $post_data, 'offset' );
		}
		$_extras['offset'] = $_offset;

		// accept sort as well as order
		$_order = FilterInput::request( 'order', FilterInput::request( 'sort' ) );
		if ( empty( $_order ) && !empty( $post_data ) )
		{
			$_order = Option::get( $post_data, 'order' );
		}
		$_extras['order'] = $_order;

		// include count in metadata tag
		$_count = FilterInput::request( 'include_count', FilterInput::request( 'count', false, FILTER_VALIDATE_BOOLEAN ), FILTER_VALIDATE_BOOLEAN );
		if ( empty( $_count ) && !empty( $post_data ) )
		{
			$_count = Option::getBool( $post_data, 'count' );
		}
		$_extras['include_count'] = $_count;

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

	// Helper function for record usage

	/**
	 * @return int
	 */
	protected static function getMaxRecordsReturnedLimit()
	{
		return intval( Pii::getParam( 'dsp.db_max_records_returned', static::DB_MAX_RECORDS_RETURNED ) );
	}

	/**
	 * @param array        $record
	 * @param string|array $include List of keys to include in the output record
	 * @param string       $id_field
	 *
	 * @return array
	 */
	protected static function cleanRecord( $record, $include = '*', $id_field = null )
	{
		if ( empty( $id_field ) )
		{
			$id_field = static::DEFAULT_ID_FIELD;
		}
		if ( '*' !== $include )
		{
			$_out = array();
			if ( empty( $include ) )
			{
				$include = $id_field;
			}
			if ( !is_array( $include ) )
			{
				$include = array_map( 'trim', explode( ',', trim( $include, ',' ) ) );
			}
			foreach ( $include as $_key )
			{
				$_out[$_key] = Option::get( $record, $_key );
			}

			return $_out;
		}

		return $record;
	}

	/**
	 * @param array $records
	 * @param mixed $include
	 * @param mixed $id_field
	 *
	 * @return array
	 */
	protected static function cleanRecords( $records, $include = '*', $id_field = null )
	{
		$_out = array();
		foreach ( $records as $_record )
		{
			$_out[] = static::cleanRecord( $_record, $include, $id_field );
		}

		return $_out;
	}

	/**
	 * @param array  $records
	 * @param string $id_field
	 *
	 * @return array
	 * @throws BadRequestException
	 */
	protected static function recordsAsIds( $records, $id_field = null )
	{
		if ( empty( $id_field ) )
		{
			$id_field = static::DEFAULT_ID_FIELD;
		}
		$_ids = array();
		foreach ( $records as $_key => $_record )
		{
			$_id = Option::get( $_record, $id_field );
			if ( empty( $_id ) )
			{
				throw new BadRequestException( "Identifying field '$id_field' can not be empty for retrieve record index '$_key' request." );
			}
			$_ids[] = $_id;
		}

		return $_ids;
	}

	/**
	 * @param        $ids
	 * @param string $id_field
	 *
	 * @return array
	 */
	protected static function idsAsRecords( $ids, $id_field = null )
	{
		if ( empty( $id_field ) )
		{
			$id_field = static::DEFAULT_ID_FIELD;
		}
		$_out = array();
		foreach ( $ids as $_id )
		{
			$_out[] = array( $id_field => $_id );
		}

		return $_out;
	}

	protected static function _containsIdFields( $record, $id_field = null )
	{
		if ( empty( $id_field ) )
		{
			$id_field = static::DEFAULT_ID_FIELD;
		}
		if ( !is_array( $id_field ) )
		{
			$id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
		}
		foreach ( $id_field as $_name )
		{
			$_temp = Option::get( $record, $_name );
			if ( empty( $_temp ) )
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @param        $fields
	 * @param string $id_field
	 *
	 * @return bool
	 */
	protected static function _requireMoreFields( $fields, $id_field = null )
	{
		if ( empty( $id_field ) )
		{
			$id_field = static::DEFAULT_ID_FIELD;
		}
		if ( empty( $fields ) )
		{
			return false;
		}
		if ( !is_array( $fields ) )
		{
			$fields = array_map( 'trim', explode( ',', trim( $fields, ',' ) ) );
		}
		if ( !is_array( $id_field ) )
		{
			$id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
		}
		foreach ( $id_field as $_key => $_name )
		{
			if ( false !== array_search( $_name, $fields ) )
			{
				unset( $fields[$_key] );
			}
		}

		return !empty( $fields );
	}

	/**
	 * @param        $first_array
	 * @param        $second_array
	 * @param string $id_field
	 *
	 * @return mixed
	 */
	protected static function recordArrayMerge( $first_array, $second_array, $id_field = null )
	{
		if ( empty( $id_field ) )
		{
			$id_field = static::DEFAULT_ID_FIELD;
		}
		foreach ( $first_array as $_key => $_first )
		{
			$_firstId = Option::get( $_first, $id_field );
			foreach ( $second_array as $_second )
			{
				$_secondId = Option::get( $_second, $id_field );
				if ( $_firstId == $_secondId )
				{
					$first_array[$_key] = array_merge( $_first, $_second );
				}
			}
		}

		return $first_array;
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
	public function getTables( $tables = array() )
	{
		if ( !is_array( $tables ) )
		{
			// may be comma-delimited list of names
			$tables = array_map( 'trim', explode( ',', trim( $tables, ',' ) ) );
		}
		$_out = array();
		foreach ( $tables as $_table )
		{
			try
			{
				$_out[] = $this->getTable( $_table );
			}
			catch ( \Exception $ex )
			{
				throw $ex;
			}
		}

		return $_out;
	}

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
	 * @return array
	 * @throws \Exception
	 */
	public function createTables( $tables = array() )
	{
		$_out = array();
		foreach ( $tables as $_table )
		{
			try
			{
				$_out[] = $this->createTable( $_table );
			}
			catch ( \Exception $ex )
			{
				throw $ex;
			}
		}

		return $_out;
	}

	/**
	 * Create a single table by name and additional properties
	 *
	 * @param array $properties
	 *
	 * @throws \Exception
	 */
	abstract public function createTable( $properties = array() );

	/**
	 * Update one or more tables by array of table properties
	 *
	 * @param array $tables
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function updateTables( $tables = array() )
	{
		$_out = array();
		foreach ( $tables as $_table )
		{
			try
			{
				$_out[] = $this->updateTable( $_table );
			}
			catch ( \Exception $ex )
			{
				throw $ex;
			}
		}

		return $_out;
	}

	/**
	 * Update properties related to the table
	 *
	 * @param array $properties
	 *
	 * @return array
	 * @throws \Exception
	 */
	abstract public function updateTable( $properties = array() );

	/**
	 * Delete multiple tables and all of their contents
	 *
	 * @param array $tables
	 * @param bool  $check_empty
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function deleteTables( $tables = array(), $check_empty = false )
	{
		if ( !is_array( $tables ) )
		{
			// may be comma-delimited list of names
			$tables = array_map( 'trim', explode( ',', trim( $tables, ',' ) ) );
		}
		$_out = array();
		foreach ( $tables as $_table )
		{
			try
			{
				$_out[] = $this->deleteTable( $_table, $check_empty );
			}
			catch ( \Exception $ex )
			{
				throw new $ex;
			}
		}

		return $_out;
	}

	/**
	 * Delete a table and all of its contents by name
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
	 * @param string $table
	 * @param array  $records
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function createRecords( $table, $records, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param array  $record
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function createRecord( $table, $record, $fields = null, $extras = array() )
	{
		if ( !isset( $record ) || empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record.' );
		}

		$results = $this->createRecords( $table, array( $record ), $fields, $extras );

		return $results[0];
	}

	/**
	 * @param string $table
	 * @param array  $records
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function updateRecords( $table, $records, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param array  $record
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function updateRecord( $table, $record, $fields = null, $extras = array() )
	{
		if ( !isset( $record ) || empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record.' );
		}

		$results = $this->updateRecords( $table, array( $record ), $fields, $extras );

		return $results[0];
	}

	/**
	 * @param string $table
	 * @param array  $record
	 * @param mixed  $filter
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function updateRecordsByFilter( $table, $record, $filter = null, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param array  $record
	 * @param mixed  $ids    - array or comma-delimited list of record identifiers
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @return array
	 */
	abstract public function updateRecordsByIds( $table, $record, $ids, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param array  $record
	 * @param string $id
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function updateRecordById( $table, $record, $id, $fields = null, $extras = array() )
	{
		$results = $this->updateRecordsByIds( $table, $record, $id, $fields, $extras );

		return $results[0];
	}

	/**
	 * @param string $table
	 * @param array  $records
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function mergeRecords( $table, $records, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param array  $record
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function mergeRecord( $table, $record, $fields = null, $extras = array() )
	{
		if ( !isset( $record ) || empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record.' );
		}

		$results = $this->mergeRecords( $table, array( $record ), $fields, $extras );

		return $results[0];
	}

	/**
	 * @param string $table
	 * @param  array $record
	 * @param mixed  $filter
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function mergeRecordsByFilter( $table, $record, $filter = null, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param array  $record
	 * @param mixed  $ids    - array or comma-delimited list of record identifiers
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function mergeRecordsByIds( $table, $record, $ids, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param array  $record
	 * @param string $id
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function mergeRecordById( $table, $record, $id, $fields = null, $extras = array() )
	{
		$results = $this->mergeRecordsByIds( $table, $record, $id, $fields, $extras );

		return $results[0];
	}

	/**
	 * @param string $table
	 * @param array  $records
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function deleteRecords( $table, $records, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param array  $record
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function deleteRecord( $table, $record, $fields = null, $extras = array() )
	{
		if ( !isset( $record ) || empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record.' );
		}

		$results = $this->deleteRecords( $table, array( $record ), $fields, $extras );

		return $results[0];
	}

	/**
	 * @param string $table
	 * @param mixed  $filter
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function deleteRecordsByFilter( $table, $filter, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param mixed  $ids    - array or comma-delimited list of record identifiers
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function deleteRecordsByIds( $table, $ids, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param string $id
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function deleteRecordById( $table, $id, $fields = null, $extras = array() )
	{
		$results = $this->deleteRecordsByIds( $table, $id, $fields, $extras );

		return $results[0];
	}

	/**
	 * @param string $table
	 * @param mixed  $filter
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function retrieveRecordsByFilter( $table, $filter = null, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param array  $records
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function retrieveRecords( $table, $records, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param array  $record
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function retrieveRecord( $table, $record, $fields = null, $extras = array() )
	{
		if ( !isset( $record ) || empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record.' );
		}

		$results = $this->retrieveRecords( $table, array( $record ), $fields, $extras );

		return $results[0];
	}

	/**
	 * @param string $table
	 * @param mixed  $ids    - array or comma-delimited list of record identifiers
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	abstract public function retrieveRecordsByIds( $table, $ids, $fields = null, $extras = array() );

	/**
	 * @param string $table
	 * @param mixed  $id
	 * @param mixed  $fields - array or comma-delimited list of record fields
	 * @param array  $extras
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function retrieveRecordById( $table, $id, $fields = null, $extras = array() )
	{
		$results = $this->retrieveRecordsByIds( $table, $id, $fields, $extras );

		return $results[0];
	}

}
