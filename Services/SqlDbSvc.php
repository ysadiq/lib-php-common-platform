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

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Services\BaseDbSvc;
use DreamFactory\Platform\Utility\SqlDbUtilities;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * SqlDbSvc.php
 * A service to handle SQL database services accessed through the REST API.
 *
 */
class SqlDbSvc extends BaseDbSvc
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var \CDbConnection
	 */
	protected $_sqlConn;
	/**
	 * @var boolean
	 */
	protected $_isNative = false;
	/**
	 * @var array
	 */
	protected $_fieldCache;
	/**
	 * @var array
	 */
	protected $_relatedCache;
	/**
	 * @var integer
	 */
	protected $_driverType = SqlDbUtilities::DRV_OTHER;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Create a new SqlDbSvc
	 *
	 * @param array $config
	 * @param bool  $native
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( $config, $native = false )
	{
		if ( null === Option::get( $config, 'verb_aliases' ) )
		{
			//	Default verb aliases
			$config['verb_aliases'] = array(
				static::Patch => static::Put,
				static::Merge => static::Put,
			);
		}

		parent::__construct( $config );

		$this->_fieldCache = array();
		$this->_relatedCache = array();

		if ( false !== ( $this->_isNative = $native ) )
		{
			$this->_sqlConn = Pii::db();
		}
		else
		{
			$_credentials = Option::get( $config, 'credentials' );

			if ( null === ( $dsn = Option::get( $_credentials, 'dsn' ) ) )
			{
				throw new InternalServerErrorException( 'DB connection string (DSN) can not be empty.' );
			}

			if ( null === ( $user = Option::get( $_credentials, 'user' ) ) )
			{
				throw new InternalServerErrorException( 'DB admin name can not be empty.' );
			}

			if ( null === ( $password = Option::get( $_credentials, 'pwd' ) ) )
			{
				throw new InternalServerErrorException( 'DB admin password can not be empty.' );
			}

			// 	Create pdo connection, activate later
			$this->_sqlConn = new \CDbConnection( $dsn, $user, $password );
		}

		switch ( $this->_driverType = SqlDbUtilities::getDbDriverType( $this->_sqlConn ) )
		{
			case SqlDbUtilities::DRV_MYSQL:
				$this->_sqlConn->setAttribute( \PDO::ATTR_EMULATE_PREPARES, true );
//				$this->_sqlConn->setAttribute( 'charset', 'utf8' );
				break;

			case SqlDbUtilities::DRV_SQLSRV:
				$this->_sqlConn->setAttribute( constant( '\\PDO::SQLSRV_ATTR_DIRECT_QUERY' ), true );

				//	These need to be on the dsn
//				$this->_sqlConn->setAttribute( 'MultipleActiveResultSets', false );
//				$this->_sqlConn->setAttribute( 'ReturnDatesAsStrings', true );
//				$this->_sqlConn->setAttribute( 'CharacterSet', 'UTF-8' );
				break;
		}

		$_attributes = Option::clean( Option::get( $config, 'parameters' ) );

		if ( !empty( $_attributes ) )
		{
			$this->_sqlConn->setAttributes( $_attributes );
		}
	}

	/**
	 * Object destructor
	 */
	public function __destruct()
	{
		if ( !$this->_isNative && isset( $this->_sqlConn ) )
		{
			try
			{
				$this->_sqlConn->active = false;
				$this->_sqlConn = null;
			}
			catch ( \PDOException $ex )
			{
				error_log( "Failed to disconnect from database.\n{$ex->getMessage()}" );
			}
			catch ( \Exception $ex )
			{
				error_log( "Failed to disconnect from database.\n{$ex->getMessage()}" );
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function checkConnection()
	{
		if ( !isset( $this->_sqlConn ) )
		{
			throw new InternalServerErrorException( 'Database driver has not been initialized.' );
		}
		try
		{
			if ( !$this->_sqlConn->active )
			{
				$this->_sqlConn->active = true;
			}
		}
		catch ( \PDOException $ex )
		{
			throw new InternalServerErrorException( "Failed to connect to database.\n{$ex->getMessage()}" );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Failed to connect to database.\n{$ex->getMessage()}" );
		}
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
			$_length = strlen( SystemManager::SYSTEM_TABLE_PREFIX );
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
	 * @param $name
	 *
	 * @return string
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function correctTableName( $name )
	{
		return SqlDbUtilities::correctTableName( $this->_sqlConn, $name );
	}

	/**
	 * @param string $table
	 * @param string $access
	 *
	 * @throws \Exception
	 */
	protected function validateTableAccess( $table, $access = 'read' )
	{
		parent::validateTableAccess( $table, $access );

		if ( $this->_isNative )
		{
			static::_checkTable( $table );
		}
	}

	/**
	 * @param null|array $post_data
	 *
	 * @return array
	 */
	protected function _gatherExtrasFromRequest( $post_data = null )
	{
		$_extras = parent::_gatherExtrasFromRequest( $post_data );

		$_relations = array();
		$_related = FilterInput::request( 'related' );

		if ( !empty( $_related ) )
		{
			$_related = array_map( 'trim', explode( ',', $_related ) );
			foreach ( $_related as $_relative )
			{
				$_extraFields = FilterInput::request( $_relative . '_fields', '*' );
				$_extraOrder = FilterInput::request( $_relative . '_order', '' );
				$_relations[] = array( 'name' => $_relative, 'fields' => $_extraFields, 'order' => $_extraOrder );
			}
		}

		$_extras['related'] = $_relations;
		$_extras['include_schema'] = FilterInput::request( 'include_schema', false, FILTER_VALIDATE_BOOLEAN );

		// rollback all db changes in a transaction, if applicable
		$_rollback = FilterInput::request( 'rollback', false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( empty( $_rollback ) && !empty( $post_data ) )
		{
			$_rollback = Option::getBool( $post_data, 'rollback' );
		}
		$_extras['rollback'] = $_rollback;

		// continue batch processing if an error occurs, if applicable
		$_continue = FilterInput::request( 'continue', false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( empty( $_continue ) && !empty( $post_data ) )
		{
			$_continue = Option::getBool( $post_data, 'continue' );
		}
		$_extras['continue'] = $_continue;

		return $_extras;
	}

	// REST service implementation

	/**
	 * @throws \Exception
	 * @return array
	 */
	protected function _listResources()
	{
		$_exclude = '';
		if ( $this->_isNative )
		{
			// check for system tables
			$_exclude = SystemManager::SYSTEM_TABLE_PREFIX;
		}
		try
		{
			$_result = SqlDbUtilities::describeDatabase( $this->_sqlConn, '', $_exclude );

			return array( 'resource' => $_result );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error describing database tables.\n{$ex->getMessage()}" );
		}
	}

	//-------- Table Records Operations ---------------------
	// records is an array of field arrays

	/**
	 * {@inheritdoc}
	 */
	public function createRecords( $table, $records, $fields = null, $extras = array() )
	{
		if ( empty( $records ) || !is_array( $records ) )
		{
			throw new BadRequestException( 'There are no record sets in the request.' );
		}
		if ( !isset( $records[0] ) )
		{
			// single record possibly passed in without wrapper array
			$records = array( $records );
		}

		$table = $this->correctTableName( $table );
		$_isSingle = ( 1 == count( $records ) );
		$_rollback = Option::getBool( $extras, 'rollback', false );
		$_continue = Option::getBool( $extras, 'continue', false );
		$_idFields = Option::get( $extras, 'id_field' );
		try
		{
			$_fieldInfo = $this->describeTableFields( $table );
			$_relatedInfo = $this->describeTableRelated( $table );
			$_idFieldsInfo = array();
			if ( empty( $_idFields ) )
			{
				$_idFieldsInfo = SqlDbUtilities::getPrimaryKeys( $_fieldInfo );
				$_idFields = array();
				foreach ( $_idFieldsInfo as $_temp )
				{
					$_idFields[] = $_temp['name'];
				}
			}
			else
			{
				if ( !is_array( $_idFields ) )
				{
					$_idFields = array_map( 'trim', explode( ',', trim( $_idFields, ',' ) ) );
				}
				foreach ( $_idFields as $_temp )
				{
					$_idFieldsInfo[] = SqlDbUtilities::getFieldFromDescribe( $_temp, $_fieldInfo );
				}
			}

			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand();
			$_ids = array();
			$_errors = array();
			$_transaction = null;

			if ( $_rollback && !$_isSingle )
			{
				$_transaction = $this->_sqlConn->beginTransaction();
			}

			foreach ( $records as $_key => $_record )
			{
				try
				{
					$_parsed = $this->parseRecord( $_record, $_fieldInfo );
					if ( 0 >= count( $_parsed ) )
					{
						throw new BadRequestException( "No valid fields were passed in the record [$_key] request." );
					}
					// simple insert request
					$command->reset();
					$rows = $command->insert( $table, $_parsed );
					if ( 0 >= $rows )
					{
						throw new InternalServerErrorException( "Record [$_key] insert failed for table '$table'." );
					}

					$_id = null;
					if ( !empty( $_idFieldsInfo ) )
					{
						foreach ( $_idFieldsInfo as $_info )
						{
							// todo support multi-field keys
							if ( Option::getBool( $_info, 'auto_increment' ) )
							{
								$_id = $this->_sqlConn->lastInsertID;
							}
							else
							{
								// must have been passed in with request
								$_id = Option::get( $_record, Option::get( $_info, 'name' ) );
							}
						}

						$this->updateRelations( $table, $_record, $_id, $_relatedInfo );
					}

					$_ids[$_key] = $_id;
				}
				catch ( \Exception $ex )
				{
					if ( $_isSingle )
					{
						throw $ex;
					}

					if ( $_rollback && $_transaction )
					{
						$_transaction->rollBack();
						throw $ex;
					}

					$_errors[] = $_key;
					$_ids[$_key] = $ex->getMessage();
					if ( !$_continue )
					{
						break;
					}
				}
			}

			if ( $_rollback && $_transaction )
			{
				$_transaction->commit();
			}

			if ( !empty( $_errors ) )
			{
				$_msg = array( 'errors' => $_errors, 'ids' => $_ids );
				throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
			}

			$_results = array();
			if ( !static::_requireMoreFields( $fields, $_idFields ) )
			{
				$_temp = array();
				foreach ( $_ids as $_id )
				{
					foreach ( $_idFields as $_field )
					{
						$_temp[] = array( $_field => $_id );
					}
				}
				$_results[] = $_temp;
			}
			else
			{
				$_results = $this->retrieveRecordsByIds( $table, $_ids, $fields, $extras );
			}

			return $_results;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateRecords( $table, $records, $fields = null, $extras = array() )
	{
		if ( empty( $records ) || !is_array( $records ) )
		{
			throw new BadRequestException( 'There are no record sets in the request.' );
		}
		if ( !isset( $records[0] ) )
		{
			// single record possibly passed in without wrapper array
			$records = array( $records );
		}

		$table = $this->correctTableName( $table );
		$_isSingle = ( 1 == count( $records ) );
		$_rollback = Option::getBool( $extras, 'rollback', false );
		$_continue = Option::getBool( $extras, 'continue', false );
		$_idField = Option::get( $extras, 'id_field' );
		try
		{
			$_fieldInfo = $this->describeTableFields( $table );
			$_relatedInfo = $this->describeTableRelated( $table );

			if ( empty( $_idField ) )
			{
				$_idField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $_fieldInfo );
				if ( empty( $_idField ) )
				{
					throw new BadRequestException( "Identifying field can not be empty." );
				}
			}

			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand();
			$_ids = array();
			$_errors = array();
			$_transaction = null;

			if ( $_rollback && !$_isSingle )
			{
				$_transaction = $this->_sqlConn->beginTransaction();
			}

			foreach ( $records as $_key => $_record )
			{
				try
				{
					$_id = Option::get( $_record, $_idField );
					if ( empty( $_id ) )
					{
						throw new BadRequestException( "Identifying field '$_idField' can not be empty for update record [$_key] request." );
					}

					$_record = Utilities::removeOneFromArray( $_idField, $_record );
					$_parsed = $this->parseRecord( $_record, $_fieldInfo, true );
					if ( 0 >= count( $_parsed ) )
					{
						throw new BadRequestException( "No valid fields were passed in the record [$_key] request." );
					}

					// simple update request
					$command->reset();
					$rows = $command->update( $table, $_parsed, array( 'in', $_idField, $_id ) );
					$_ids[$_key] = $_id;
					$this->updateRelations( $table, $_record, $_id, $_relatedInfo );
				}
				catch ( \Exception $ex )
				{
					if ( $_isSingle )
					{
						throw $ex;
					}

					if ( $_rollback && $_transaction )
					{
						$_transaction->rollBack();
						throw $ex;
					}

					$_errors[] = $_key;
					$_ids[$_key] = $ex->getMessage();
					if ( !$_continue )
					{
						break;
					}
				}
			}

			if ( $_rollback && $_transaction )
			{
				$_transaction->commit();
			}

			if ( !empty( $_errors ) )
			{
				$_msg = array( 'errors' => $_errors, 'ids' => $_ids );
				throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
			}

			$_results = array();
			if ( !static::_requireMoreFields( $fields, $_idField ) )
			{
				foreach ( $_ids as $_id )
				{
					$_results[] = array( $_idField => $_id );
				}
			}
			else
			{
				$_results = $this->retrieveRecordsByIds( $table, $_ids, $fields, $extras );
			}

			return $_results;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateRecordsByFilter( $table, $record, $filter = null, $fields = null, $extras = array() )
	{
		if ( !is_array( $record ) || empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record.' );
		}
		$table = $this->correctTableName( $table );
		try
		{
			$fieldInfo = $this->describeTableFields( $table );
			$relatedInfo = $this->describeTableRelated( $table );
			// simple update request
			$parsed = $this->parseRecord( $record, $fieldInfo, true );
			if ( empty( $parsed ) )
			{
				throw new BadRequestException( "No valid field values were passed in the request." );
			}
			// parse filter
			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand();
			$rows = $command->update( $table, $parsed, $filter );
			// todo how to update relations here?

			$results = array();
			if ( !empty( $fields ) )
			{
				$results = $this->retrieveRecordsByFilter( $table, $filter, $fields, $extras );
			}

			return $results;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateRecordsByIds( $table, $record, $ids, $fields = null, $extras = array() )
	{
		if ( !is_array( $record ) || empty( $record ) )
		{
			throw new BadRequestException( "No record fields were passed in the request." );
		}

		$table = $this->correctTableName( $table );
		$_rollback = Option::getBool( $extras, 'rollback', false );
		$_continue = Option::getBool( $extras, 'continue', false );
		$_idField = Option::get( $extras, 'id_field' );
		$_isSingle = ( 1 == count( $ids ) );
		if ( !is_array( $ids ) )
		{
			$ids = array_map( 'trim', explode( ',', trim( $ids, ',' ) ) );
		}
		if ( empty( $ids ) )
		{
			throw new BadRequestException( "Identifying values for '$_idField' can not be empty for update request." );
		}

		try
		{
			$_fieldInfo = $this->describeTableFields( $table );
			$_relatedInfo = $this->describeTableRelated( $table );

			if ( empty( $_idField ) )
			{
				$_idField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $_fieldInfo );
				if ( empty( $_idField ) )
				{
					throw new BadRequestException( "Identifying field can not be empty." );
				}
			}

			$record = Utilities::removeOneFromArray( $_idField, $record );
			// simple update request
			$_parsed = $this->parseRecord( $record, $_fieldInfo, true );
			if ( empty( $_parsed ) )
			{
				throw new BadRequestException( "No valid field values were passed in the request." );
			}

			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand();
			$_errors = array();
			$_transaction = null;

			if ( $_rollback && !$_isSingle )
			{
				$_transaction = $this->_sqlConn->beginTransaction();
			}

			foreach ( $ids as $_key => $_id )
			{
				try
				{
					if ( empty( $_id ) )
					{
						throw new BadRequestException( "Identifying field '$_idField' can not be empty for update record request." );
					}
					// simple update request
					$command->reset();
					$rows = $command->update( $table, $_parsed, array( 'in', $_idField, $_id ) );
					$this->updateRelations( $table, $record, $_id, $_relatedInfo );
				}
				catch ( \Exception $ex )
				{
					if ( $_isSingle )
					{
						throw $ex;
					}

					if ( $_rollback && $_transaction )
					{
						$_transaction->rollBack();
						throw $ex;
					}

					$_errors[] = $_key;
					$ids[$_key] = $ex->getMessage();
					if ( !$_continue )
					{
						break;
					}
				}
			}

			if ( $_rollback && $_transaction )
			{
				$_transaction->commit();
			}

			if ( !empty( $_errors ) )
			{
				$_msg = array( 'errors' => $_errors, 'ids' => $ids );
				throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
			}

			$_results = array();
			if ( !static::_requireMoreFields( $fields, $_idField ) )
			{
				foreach ( $ids as $_id )
				{
					$_results[] = array( $_idField => $_id );
				}
			}
			else
			{
				$_results = $this->retrieveRecordsByIds( $table, $ids, $fields, $extras );
			}

			return $_results;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function mergeRecords( $table, $records, $fields = null, $extras = array() )
	{
		// currently the same as update here
		return $this->updateRecords( $table, $records, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function mergeRecordsByFilter( $table, $record, $filter = null, $fields = null, $extras = array() )
	{
		// currently the same as update here
		return $this->updateRecordsByFilter( $table, $record, $filter, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function mergeRecordsByIds( $table, $record, $ids, $fields = null, $extras = array() )
	{
		// currently the same as update here
		return $this->updateRecordsByIds( $table, $record, $ids, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteRecords( $table, $records, $fields = null, $extras = array() )
	{
		if ( !is_array( $records ) || empty( $records ) )
		{
			if ( Option::getBool( $extras, 'force', false ) )
			{
				// truncate the table, return success
				$table = $this->correctTableName( $table );
				try
				{
					/** @var \CDbCommand $command */
					$command = $this->_sqlConn->createCommand();
					$results = array();
					$rows = $command->truncateTable( $table );

					return $results;
				}
				catch ( \Exception $ex )
				{
					throw $ex;
				}
			}
			throw new BadRequestException( 'There are no record sets in the request.' );
		}
		if ( !isset( $records[0] ) )
		{
			// single record
			$records = array( $records );
		}

		$table = $this->correctTableName( $table );
		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$field_info = $this->describeTableFields( $table );
			$_idField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $field_info );
			if ( empty( $_idField ) )
			{
				throw new BadRequestException( "Identifying field can not be empty." );
			}
		}

		$_ids = array();
		foreach ( $records as $_key => $_record )
		{
			$_id = Option::get( $_record, $_idField );
			if ( empty( $_id ) )
			{
				throw new BadRequestException( "Identifying field '$_idField' can not be empty for retrieve record [$_key] request." );
			}
			$_ids[] = $_id;
		}

		return $this->deleteRecordsByIds( $table, $_ids, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteRecordsByFilter( $table, $filter, $fields = null, $extras = array() )
	{
		if ( empty( $filter ) )
		{
			throw new BadRequestException( "Filter for delete request can not be empty." );
		}
		$table = $this->correctTableName( $table );
		try
		{
			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand();
			$results = array();
			// get the returnable fields first, then issue delete
			if ( !empty( $fields ) )
			{
				$results = $this->retrieveRecordsByFilter( $table, $filter, $fields, $extras );
			}

			// parse filter
			$command->reset();
			$rows = $command->delete( $table, $filter );

			return $results;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteRecordsByIds( $table, $ids, $fields = null, $extras = array() )
	{
		$table = $this->correctTableName( $table );
		$_rollback = Option::getBool( $extras, 'rollback', false );
		$_continue = Option::getBool( $extras, 'continue', false );
		$_idField = Option::get( $extras, 'id_field' );
		try
		{
			if ( empty( $_idField ) )
			{
				$_fieldInfo = $this->describeTableFields( $table );
				$_idField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $_fieldInfo );
				if ( empty( $_idField ) )
				{
					throw new BadRequestException( "Identifying field can not be empty." );
				}
			}
			if ( empty( $ids ) )
			{
				throw new BadRequestException( "Identifying values for '$_idField' can not be empty for delete request." );
			}

			if ( !is_array( $ids ) )
			{
				$ids = array_map( 'trim', explode( ',', $ids ) );
			}
			$_isSingle = ( 1 == count( $ids ) );

			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand();
			$_errors = array();
			$_transaction = null;

			// get the returnable fields first, then issue delete
			$_outResults = array();
			if ( static::_requireMoreFields( $fields, $_idField ) )
			{
				$_outResults = $this->retrieveRecordsByIds( $table, $ids, $fields, $extras );
			}

			if ( $_rollback && !$_isSingle )
			{
				$_transaction = $this->_sqlConn->beginTransaction();
			}

			foreach ( $ids as $_key => $_id )
			{
				try
				{
					if ( empty( $_id ) )
					{
						throw new BadRequestException( "Identifying field '$_idField' can not be empty for delete record request." );
					}

					// simple delete request
					$command->reset();
					$rows = $command->delete( $table, array( 'in', $_idField, $_id ) );
					if ( 0 >= $rows )
					{
						throw new NotFoundException( "Record with $_idField '$_id' not found in table '$table'." );
					}
				}
				catch ( \Exception $ex )
				{
					if ( $_isSingle )
					{
						throw $ex;
					}

					if ( $_rollback && $_transaction )
					{
						$_transaction->rollBack();
						throw $ex;
					}

					$_errors[] = $_key;
					$ids[$_key] = $ex->getMessage();
					if ( !$_continue )
					{
						break;
					}
				}
			}

			if ( $_rollback && $_transaction )
			{
				$_transaction->commit();
			}

			if ( !empty( $_errors ) )
			{
				$_msg = array( 'errors' => $_errors, 'ids' => $ids );
				throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
			}

			$_results = array();
			if ( !static::_requireMoreFields( $fields, $_idField ) )
			{
				foreach ( $ids as $_id )
				{
					$_results[] = array( $_idField => $_id );
				}
			}
			else
			{
				$_results = $_outResults;
			}

			return $_results;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function retrieveRecordsByFilter( $table, $filter = null, $fields = null, $extras = array() )
	{
		$table = $this->correctTableName( $table );
		try
		{
			// parse filter
			$availFields = $this->describeTableFields( $table );
			$relations = $this->describeTableRelated( $table );
			$related = Option::get( $extras, 'related' );
			$result = $this->parseFieldsForSqlSelect( $fields, $availFields );
			$bindings = $result['bindings'];
			$fields = $result['fields'];
			if ( empty( $fields ) )
			{
				$fields = '*';
			}
			$order = Option::get( $extras, 'order' );
			$limit = intval( Option::get( $extras, 'limit', 0 ) );
			$offset = intval( Option::get( $extras, 'offset', 0 ) );
			$maxAllowed = static::getMaxRecordsReturnedLimit();
			$_needLimit = false;

			// use query builder
			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand();
			$command->select( $fields );
			$command->from( $table );
			if ( !empty( $filter ) )
			{
				$command->where( $filter );
			}
			if ( !empty( $order ) )
			{
				$command->order( $order );
			}
			if ( $offset > 0 )
			{
				$command->offset( $offset );
			}
			if ( ( $limit < 1 ) || ( $limit > $maxAllowed ) )
			{
				// impose a limit to protect server
				$limit = $maxAllowed;
				$_needLimit = true;
			}
			$command->limit( $limit );

			$this->checkConnection();
			$reader = $command->query();
			$data = array();
			$dummy = array();
			foreach ( $bindings as $binding )
			{
				$reader->bindColumn( $binding['name'], $dummy[$binding['name']], $binding['pdo_type'] );
			}
			$reader->setFetchMode( \PDO::FETCH_BOUND );
			$count = 0;
			while ( false !== $reader->read() )
			{
				$temp = array();
				foreach ( $bindings as $binding )
				{
					$_name = $binding['name'];
					$_value = $dummy[$_name];
					if ( 'float' == $binding['php_type'] )
					{
						$_value = floatval( $_value );
					}
					$temp[$_name] = $_value;
				}

				if ( !empty( $related ) )
				{
					$temp = $this->retrieveRelatedRecords( $relations, $temp, $related );
				}
				$data[$count++] = $temp;
			}

			$_includeCount = Option::getBool( $extras, 'include_count', false );
			$_includeSchema = Option::getBool( $extras, 'include_schema', false );
			if ( $_includeCount || $_needLimit || $_includeSchema )
			{
				// count total records
				if ( $_includeCount || $_needLimit )
				{
					$command->reset();
					$command->select( '(COUNT(*)) as ' . $this->_sqlConn->quoteColumnName( 'count' ) );
					$command->from( $table );
					if ( !empty( $filter ) )
					{
						$command->where( $filter );
					}
					$_count = intval( $command->queryScalar() );
					$data['meta']['count'] = $_count;
					if ( ( $_count - $offset ) > $maxAllowed )
					{
						$data['meta']['next'] = $offset + $limit + 1;
					}
				}
				// count total records
				if ( $_includeSchema )
				{
					$data['meta']['schema'] = SqlDbUtilities::describeTable( $this->_sqlConn, $table );
				}
			}

//            error_log('retrievefilter: ' . PHP_EOL . print_r($data, true));

			return $data;
		}
		catch ( \Exception $ex )
		{
			error_log( 'retrievefilter: ' . $ex->getMessage() . PHP_EOL . $filter );
			/*
            $msg = '[QUERYFAILED]: ' . implode(':', $this->_sqlConn->errorInfo()) . "\n";
            if (isset($GLOBALS['DB_DEBUG'])) {
                error_log($msg . "\n$query");
            }
            */
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function retrieveRecords( $table, $records, $fields = null, $extras = array() )
	{
		if ( empty( $records ) || !is_array( $records ) )
		{
			throw new BadRequestException( 'There are no record sets in the request.' );
		}
		if ( !isset( $records[0] ) )
		{
			// single record
			$records = array( $records );
		}

		$table = $this->correctTableName( $table );
		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$field_info = $this->describeTableFields( $table );
			$_idField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $field_info );
			if ( empty( $_idField ) )
			{
				throw new BadRequestException( "Identifying field can not be empty." );
			}
		}
		$ids = array();
		foreach ( $records as $key => $record )
		{
			$id = Option::get( $record, $_idField );
			if ( empty( $id ) )
			{
				throw new BadRequestException( "Identifying field '$_idField' can not be empty for retrieve record [$key] request." );
			}
			$ids[] = $id;
		}
		$idList = implode( ',', $ids );

		return $this->retrieveRecordsByIds( $table, $idList, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function retrieveRecordsByIds( $table, $ids, $fields = null, $extras = array() )
	{
		if ( empty( $ids ) )
		{
			return array();
		}
		if ( !is_array( $ids ) )
		{
			$ids = array_map( 'trim', explode( ',', $ids ) );
		}
		$table = $this->correctTableName( $table );
		$_idField = Option::get( $extras, 'id_field' );
		try
		{
			$availFields = $this->describeTableFields( $table );
			$relations = $this->describeTableRelated( $table );
			$related = Option::get( $extras, 'related' );
			if ( empty( $_idField ) )
			{
				$_idField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $availFields );
				if ( empty( $_idField ) )
				{
					throw new BadRequestException( "Identifying field can not be empty." );
				}
			}
			if ( !empty( $fields ) && ( '*' !== $fields ) )
			{
				// add id field to field list
				$fields = Utilities::addOnceToList( $fields, $_idField, ',' );
			}
			$result = $this->parseFieldsForSqlSelect( $fields, $availFields );
			$bindings = $result['bindings'];
			$fields = $result['fields'];
			// use query builder
			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand();
			$command->select( $fields );
			$command->from( $table );
			$command->where( array( 'in', $_idField, $ids ) );

			$this->checkConnection();
			$reader = $command->query();
			$data = array();
			$dummy = array();
			foreach ( $bindings as $binding )
			{
				$reader->bindColumn( $binding['name'], $dummy[$binding['name']], $binding['pdo_type'] );
			}
			$reader->setFetchMode( \PDO::FETCH_BOUND );
			$count = 0;
			while ( false !== $reader->read() )
			{
				$temp = array();
				foreach ( $bindings as $binding )
				{
					$_name = $binding['name'];
					$_value = $dummy[$_name];
					if ( 'float' == $binding['php_type'] )
					{
						$_value = floatval( $_value );
					}
					$temp[$_name] = $_value;
				}

				if ( !empty( $related ) )
				{
					$temp = $this->retrieveRelatedRecords( $relations, $temp, $related );
				}
				$data[$count++] = $temp;
			}

			// order returned data by received ids, fill in error for those not found
			$results = array();
			foreach ( $ids as $id )
			{
				$foundRecord = null;
				foreach ( $data as $record )
				{
					if ( isset( $record[$_idField] ) && ( $record[$_idField] == $id ) )
					{
						$foundRecord = $record;
						break;
					}
				}
				$results[] = ( isset( $foundRecord )
					? $foundRecord
					:
					( "Could not find record for id = '$id'" ) );
			}

			return $results;
		}
		catch ( \Exception $ex )
		{
			/*
            $msg = '[QUERYFAILED]: ' . implode(':', $this->_sqlConn->errorInfo()) . "\n";
            if (isset($GLOBALS['DB_DEBUG'])) {
                error_log($msg . "\n$query");
            }
            */
			throw $ex;
		}
	}

	// Helper methods

	/**
	 * @param $name
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function describeTableFields( $name )
	{
		if ( isset( $this->_fieldCache[$name] ) )
		{
			return $this->_fieldCache[$name];
		}

		$fields = SqlDbUtilities::describeTableFields( $this->_sqlConn, $name );
		$this->_fieldCache[$name] = $fields;

		return $fields;
	}

	/**
	 * @param $name
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function describeTableRelated( $name )
	{
		if ( isset( $this->_relatedCache[$name] ) )
		{
			return $this->_relatedCache[$name];
		}

		$relations = SqlDbUtilities::describeTableRelated( $this->_sqlConn, $name );
		$relatives = array();
		foreach ( $relations as $relation )
		{
			$how = Option::get( $relation, 'name', '' );
			$relatives[$how] = $relation;
		}
		$this->_relatedCache[$name] = $relatives;

		return $relatives;
	}

	/**
	 * @param      $record
	 * @param      $avail_fields
	 * @param bool $for_update
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function parseRecord( $record, $avail_fields, $for_update = false )
	{
		$parsed = array();
		$record = Utilities::array_key_lower( $record );
		$keys = array_keys( $record );
		$values = array_values( $record );
		foreach ( $avail_fields as $field_info )
		{
			$name = mb_strtolower( $field_info['name'] );
			$type = $field_info['type'];
			$dbType = $field_info['db_type'];
			$pos = array_search( $name, $keys );
			if ( false !== $pos )
			{
				$fieldVal = $values[$pos];
				// due to conversion from XML to array, null or empty xml elements have the array value of an empty array
				if ( is_array( $fieldVal ) && empty( $fieldVal ) )
				{
					$fieldVal = null;
				}
				// overwrite some undercover fields
				if ( Option::getBool( $field_info, 'auto_increment', false ) )
				{
					unset( $keys[$pos] );
					unset( $values[$pos] );
					continue; // should I error this?
				}
				if ( Utilities::isInList( Option::get( $field_info, 'validation', '' ), 'api_read_only', ',' ) )
				{
					unset( $keys[$pos] );
					unset( $values[$pos] );
					continue; // should I error this?
				}
				if ( is_null( $fieldVal ) && !$field_info['allow_null'] )
				{
					if ( $for_update )
					{
						continue;
					} // todo throw away nulls for now
					throw new BadRequestException( "Field '$name' can not be NULL." );
				}
				else
				{
					if ( !is_null( $fieldVal ) )
					{
						switch ( $this->_driverType )
						{
							case SqlDbUtilities::DRV_SQLSRV:
								switch ( $dbType )
								{
									case 'bit':
										$fieldVal = ( Utilities::boolval( $fieldVal ) ? 1 : 0 );
										break;
								}
								break;
							case SqlDbUtilities::DRV_MYSQL:
								switch ( $dbType )
								{
									case 'tinyint(1)':
										$fieldVal = ( Utilities::boolval( $fieldVal ) ? 1 : 0 );
										break;
								}
								break;
						}
						switch ( $type )
						{
							case 'integer':
								if ( !is_int( $fieldVal ) )
								{
									if ( ( '' === $fieldVal ) && $field_info['allow_null'] )
									{
										$fieldVal = null;
									}
									elseif ( !( ctype_digit( $fieldVal ) ) )
									{
										throw new BadRequestException( "Field '$name' must be a valid integer." );
									}
									else
									{
										$fieldVal = intval( $fieldVal );
									}
								}
								break;
							default:
						}
					}
				}
				$parsed[$name] = $fieldVal;
				unset( $keys[$pos] );
				unset( $values[$pos] );
			}
			else
			{
				// check specific fields
				switch ( $type )
				{
					case 'timestamp_on_create':
					case 'timestamp_on_update':
					case 'user_id_on_create':
					case 'user_id_on_update':
						break;
					default:
						// if field is required, kick back error
						if ( $field_info['required'] && !$for_update )
						{
							throw new BadRequestException( "Required field '$name' can not be NULL." );
						}
						break;
				}
			}
			// add or override for specific fields
			switch ( $type )
			{
				case 'timestamp_on_create':
					if ( !$for_update )
					{
						switch ( $this->_driverType )
						{
							case SqlDbUtilities::DRV_SQLSRV:
								$parsed[$name] = new \CDbExpression( '(SYSDATETIMEOFFSET())' );
								break;
							case SqlDbUtilities::DRV_MYSQL:
								$parsed[$name] = new \CDbExpression( '(NOW())' );
								break;
						}
					}
					break;
				case 'timestamp_on_update':
					switch ( $this->_driverType )
					{
						case SqlDbUtilities::DRV_SQLSRV:
							$parsed[$name] = new \CDbExpression( '(SYSDATETIMEOFFSET())' );
							break;
						case SqlDbUtilities::DRV_MYSQL:
							$parsed[$name] = new \CDbExpression( '(NOW())' );
							break;
					}
					break;
				case 'user_id_on_create':
					if ( !$for_update )
					{
						$userId = Session::getCurrentUserId();
						if ( isset( $userId ) )
						{
							$parsed[$name] = $userId;
						}
					}
					break;
				case 'user_id_on_update':
					$userId = Session::getCurrentUserId();
					if ( isset( $userId ) )
					{
						$parsed[$name] = $userId;
					}
					break;
			}
		}

		return $parsed;
	}

	/**
	 * @param $table
	 * @param $record
	 * @param $id
	 * @param $avail_relations
	 *
	 * @throws \Exception
	 * @return void
	 */
	protected function updateRelations( $table, $record, $id, $avail_relations )
	{
		$record = Utilities::array_key_lower( $record );
		$keys = array_keys( $record );
		$values = array_values( $record );
		foreach ( $avail_relations as $relationInfo )
		{
			$name = mb_strtolower( $relationInfo['name'] );
			$pos = array_search( $name, $keys );
			if ( false !== $pos )
			{
				$relations = $values[$pos];
				$relationType = $relationInfo['type'];
				switch ( $relationType )
				{
					case 'belongs_to':
						/*
                    "name": "role_by_role_id",
                    "type": "belongs_to",
                    "ref_table": "role",
                    "ref_field": "id",
                    "field": "role_id"
                    */
						// todo handle this?
						break;
					case 'has_many':
						/*
                    "name": "users_by_last_modified_by_id",
                    "type": "has_many",
                    "ref_table": "user",
                    "ref_field": "last_modified_by_id",
                    "field": "id"
                    */
						$relatedTable = $relationInfo['ref_table'];
						$relatedField = $relationInfo['ref_field'];
						$this->assignManyToOne( $table, $id, $relatedTable, $relatedField, $relations );
						break;
					case 'many_many':
						/*
                    "name": "roles_by_user",
                    "type": "many_many",
                    "ref_table": "role",
                    "ref_field": "id",
                    "join": "user(default_app_id,role_id)"
                    */
						$relatedTable = $relationInfo['ref_table'];
						$join = $relationInfo['join'];
						$joinTable = substr( $join, 0, strpos( $join, '(' ) );
						$other = explode( ',', substr( $join, strpos( $join, '(' ) + 1, -1 ) );
						$joinLeftField = trim( $other[0] );
						$joinRightField = trim( $other[1] );
						$this->assignManyToOneByMap(
							$table,
							$id,
							$relatedTable,
							$joinTable,
							$joinLeftField,
							$joinRightField,
							$relations
						);
						break;
					default:
						throw new InternalServerErrorException( 'Invalid relationship type detected.' );
						break;
				}
				unset( $keys[$pos] );
				unset( $values[$pos] );
			}
		}
	}

	/**
	 * @param array $record
	 *
	 * @return string
	 */
	protected function parseRecordForSqlInsert( $record )
	{
		$values = '';
		foreach ( $record as $key => $value )
		{
			$fieldVal = ( is_null( $value ) ) ? "NULL" : $this->_sqlConn->quoteValue( $value );
			$values .= ( !empty( $values ) ) ? ',' : '';
			$values .= $fieldVal;
		}

		return $values;
	}

	/**
	 * @param array $record
	 *
	 * @return string
	 */
	protected function parseRecordForSqlUpdate( $record )
	{
		$out = '';
		foreach ( $record as $key => $value )
		{
			$fieldVal = ( is_null( $value ) ) ? "NULL" : $this->_sqlConn->quoteValue( $value );
			$out .= ( !empty( $values ) ) ? ',' : '';
			$out .= "$key = $fieldVal";
		}

		return $out;
	}

	/**
	 * @param        $fields
	 * @param        $avail_fields
	 * @param bool   $as_quoted_string
	 * @param string $prefix
	 * @param string $fields_as
	 *
	 * @return string
	 */
	protected function parseFieldsForSqlSelect( $fields, $avail_fields, $as_quoted_string = false, $prefix = '', $fields_as = '' )
	{
		if ( empty( $fields ) || ( '*' === $fields ) )
		{
			$fields = SqlDbUtilities::listAllFieldsFromDescribe( $avail_fields );
		}
		$field_arr = array_map( 'trim', explode( ',', $fields ) );
		$as_arr = array_map( 'trim', explode( ',', $fields_as ) );
		if ( !$as_quoted_string )
		{
			// yii will not quote anything if any of the fields are expressions
		}
		$outString = '';
		$outArray = array();
		$bindArray = array();
		for ( $i = 0, $size = sizeof( $field_arr ); $i < $size; $i++ )
		{
			$field = $field_arr[$i];
			$as = ( isset( $as_arr[$i] ) ? $as_arr[$i] : '' );
			$context = ( empty( $prefix ) ? $field : $prefix . '.' . $field );
			$out_as = ( empty( $as ) ? $field : $as );
			if ( $as_quoted_string )
			{
				$context = $this->_sqlConn->quoteColumnName( $context );
				$out_as = $this->_sqlConn->quoteColumnName( $out_as );
			}
			// find the type
			$field_info = SqlDbUtilities::getFieldFromDescribe( $field, $avail_fields );
			$dbType = Option::get( $field_info, 'db_type', '' );
			$type = Option::get( $field_info, 'type', '' );

			$bindArray[] = array(
				'name'     => $field,
				'pdo_type' => SqlDbUtilities::determinePdoBindingType( $type, $dbType ),
				'php_type' => SqlDbUtilities::determinePhpConversionType( $type, $dbType ),
			);

			// todo fix special cases - maybe after retrieve
			switch ( $dbType )
			{
				case 'datetime':
				case 'datetimeoffset':
					switch ( $this->_driverType )
					{
						case SqlDbUtilities::DRV_SQLSRV:
							if ( !$as_quoted_string )
							{
								$context = $this->_sqlConn->quoteColumnName( $context );
								$out_as = $this->_sqlConn->quoteColumnName( $out_as );
							}
							$out = "(CONVERT(nvarchar(30), $context, 127)) AS $out_as";
							break;
						default:
							$out = $context;
							break;
					}
					break;
				default :
					$out = $context;
					if ( !empty( $as ) )
					{
						$out .= ' AS ' . $out_as;
					}
					break;
			}

			$outArray[] = $out;
		}

		return array( 'fields' => $outArray, 'bindings' => $bindArray );
	}

	/**
	 * @param        $fields
	 * @param        $avail_fields
	 * @param string $prefix
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return string
	 */
	public function parseOutFields( $fields, $avail_fields, $prefix = 'INSERTED' )
	{
		if ( empty( $fields ) )
		{
			return '';
		}

		$out_str = '';
		$field_arr = array_map( 'trim', explode( ',', $fields ) );
		foreach ( $field_arr as $field )
		{
			// find the type
			if ( false === SqlDbUtilities::findFieldFromDescribe( $field, $avail_fields ) )
			{
				throw new BadRequestException( "Invalid field '$field' selected for output." );
			}
			if ( !empty( $out_str ) )
			{
				$out_str .= ', ';
			}
			$out_str .= $prefix . '.' . $this->_sqlConn->quoteColumnName( $field );
		}

		return $out_str;
	}

	// generic assignments

	/**
	 * @param $relations
	 * @param $data
	 * @param $extras
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	protected function retrieveRelatedRecords( $relations, $data, $extras )
	{
		if ( !empty( $extras ) )
		{
			$relatedData = array();
			foreach ( $extras as $extra )
			{
				$extraName = $extra['name'];
				if ( !isset( $relations[$extraName] ) )
				{
					throw new BadRequestException( "Invalid relation '$extraName' requested." );
				}
				$relation = $relations[$extraName];
				$relationType = $relation['type'];
				$relatedTable = $relation['ref_table'];
				$relatedField = $relation['ref_field'];
				$field = $relation['field'];
				$extraFields = $extra['fields'];
				switch ( $relationType )
				{
					case 'belongs_to':
						$fieldVal = Option::get( $data, $field );
						$relatedRecords = $this->retrieveRecordsByFilter( $relatedTable, "$relatedField = '$fieldVal'", $extraFields );
						if ( !empty( $relatedRecords ) )
						{
							$tempData = $relatedRecords[0];
						}
						else
						{
							$tempData = null;
						}
						break;
					case 'has_many':
						$fieldVal = Option::get( $data, $field );
						$tempData = $this->retrieveRecordsByFilter( $relatedTable, "$relatedField = '$fieldVal'", $extraFields );
						break;
					case 'many_many':
						$fieldVal = Option::get( $data, $field );
						$join = $relation['join'];
						$joinTable = substr( $join, 0, strpos( $join, '(' ) );
						$other = explode( ',', substr( $join, strpos( $join, '(' ) + 1, -1 ) );
						$joinLeftField = trim( $other[0] );
						$joinRightField = trim( $other[1] );
						$joinData = $this->retrieveRecordsByFilter( $joinTable, "$joinLeftField = '$fieldVal'", $joinRightField );
						$tempData = array();
						if ( !empty( $joinData ) )
						{
							$relatedIds = array();
							foreach ( $joinData as $record )
							{
								$relatedIds[] = $record[$joinRightField];
							}
							if ( !empty( $relatedIds ) )
							{
								$relatedIds = implode( ',', $relatedIds );
								$tempData = $this->retrieveRecordsByIds( $relatedTable, $relatedIds, $relatedField, $extraFields );
							}
						}
						break;
					default:
						throw new InternalServerErrorException( 'Invalid relationship type detected.' );
						break;
				}
				$relatedData[$extraName] = $tempData;
			}
			if ( !empty( $relatedData ) )
			{
				$data = array_merge( $data, $relatedData );
			}
		}

		return $data;
	}

	/**
	 * @param string $one_table
	 * @param string $one_id
	 * @param string $many_table
	 * @param string $many_field
	 * @param array  $many_records
	 *
	 * @throws \Exception
	 * @return void
	 */
	protected function assignManyToOne( $one_table, $one_id, $many_table, $many_field, $many_records = array() )
	{
		if ( empty( $one_id ) )
		{
			throw new BadRequestException( "The $one_table id can not be empty." );
		}
		try
		{
			$manyFields = $this->describeTableFields( $many_table );
			$pkField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $manyFields );
			$oldMany = $this->retrieveRecordsByFilter( $many_table, $many_field . " = '$one_id'", "$pkField,$many_field" );
			foreach ( $oldMany as $oldKey => $old )
			{
				$oldId = Option::get( $old, $pkField );
				foreach ( $many_records as $key => $item )
				{
					$id = Option::get( $item, $pkField, '' );
					if ( $id == $oldId )
					{
						// found it, keeping it, so remove it from the list, as this becomes adds
						unset( $many_records[$key] );
						unset( $oldMany[$oldKey] );
						continue;
					}
				}
			}
			// reset arrays
			$many_records = array_values( $many_records );
			$oldMany = array_values( $oldMany );
			if ( !empty( $oldMany ) )
			{
				// un-assign any left over old ones
				$ids = array();
				foreach ( $oldMany as $item )
				{
					$ids[] = Option::get( $item, $pkField );
				}
				if ( !empty( $ids ) )
				{
					$ids = implode( ',', $ids );
					$this->updateRecordsByIds( $many_table, array( $many_field => null ), $ids, $pkField );
				}
			}
			if ( !empty( $many_records ) )
			{
				// assign what is leftover
				$ids = array();
				foreach ( $many_records as $item )
				{
					$ids[] = Option::get( $item, $pkField );
				}
				if ( !empty( $ids ) )
				{
					$ids = implode( ',', $ids );
					$this->updateRecordsByIds( $many_table, array( $many_field => $one_id ), $ids, $pkField );
				}
			}
		}
		catch ( \Exception $ex )
		{
			throw new BadRequestException( "Error updating many to one assignment.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param       $one_table
	 * @param       $one_id
	 * @param       $many_table
	 * @param       $map_table
	 * @param       $one_field
	 * @param       $many_field
	 * @param array $many_records
	 *
	 * @throws \Exception
	 * @return void
	 */
	protected function assignManyToOneByMap( $one_table, $one_id, $many_table, $map_table, $one_field, $many_field, $many_records = array() )
	{
		if ( empty( $one_id ) )
		{
			throw new BadRequestException( "The $one_table id can not be empty." );
		}
		try
		{
			$manyFields = $this->describeTableFields( $many_table );
			$pkManyField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $manyFields );
			$mapFields = $this->describeTableFields( $map_table );
			$pkMapField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $mapFields );
			$maps = $this->retrieveRecordsByFilter( $map_table, "$one_field = '$one_id'", $pkMapField . ',' . $many_field );
			$toDelete = array();
			foreach ( $maps as $map )
			{
				$manyId = Option::get( $map, $many_field, '' );
				$id = Option::get( $map, $pkMapField, '' );
				$found = false;
				foreach ( $many_records as $key => $item )
				{
					$assignId = Option::get( $item, $pkManyField, '' );
					if ( $assignId == $manyId )
					{
						// found it, keeping it, so remove it from the list, as this becomes adds
						unset( $many_records[$key] );
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
				$this->deleteRecordsByIds( $map_table, implode( ',', $toDelete ), $pkMapField );
			}
			if ( !empty( $many_records ) )
			{
				$maps = array();
				foreach ( $many_records as $item )
				{
					$itemId = Option::get( $item, $pkManyField, '' );
					$maps[] = array( $many_field => $itemId, $one_field => $one_id );
				}
				$this->createRecords( $map_table, $maps );
			}
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error updating many to one map assignment.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * Handle raw SQL Azure requests
	 */
	protected function batchSqlQuery( $query, $bindings = array() )
	{
		if ( empty( $query ) )
		{
			throw new BadRequestException( '[NOQUERY]: No query string present in request.' );
		}
		$this->checkConnection();
		try
		{
			Utilities::markTimeStart( 'DB_TIME' );

			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand( $query );
			$reader = $command->query();
			$dummy = null;
			foreach ( $bindings as $binding )
			{
				$reader->bindColumn( $binding['name'], $dummy, $binding['pdo_type'] );
			}

			$data = array();
			$rowData = array();
			while ( $row = $reader->read() )
			{
				$rowData[] = $row;
			}
			if ( 1 == count( $rowData ) )
			{
				$rowData = $rowData[0];
			}
			$data[] = $rowData;

			// Move to the next result and get results
			while ( $reader->nextResult() )
			{
				$rowData = array();
				while ( $row = $reader->read() )
				{
					$rowData[] = $row;
				}
				if ( 1 == count( $rowData ) )
				{
					$rowData = $rowData[0];
				}
				$data[] = $rowData;
			}

			Utilities::markTimeStop( 'DB_TIME' );

			return $data;
		}
		catch ( \Exception $ex )
		{
			error_log( 'batchquery: ' . $ex->getMessage() . PHP_EOL . $query );
			Utilities::markTimeStop( 'DB_TIME' );
			/*
                $msg = '[QUERYFAILED]: ' . implode(':', $this->_sqlConn->errorInfo()) . "\n";
                if (isset($GLOBALS['DB_DEBUG'])) {
                    error_log($msg . "\n$query");
                }
*/
			throw $ex;
		}
	}

	/**
	 * Handle SQL Db requests with output as array
	 */
	public function singleSqlQuery( $query, $params = null )
	{
		if ( empty( $query ) )
		{
			throw new BadRequestException( '[NOQUERY]: No query string present in request.' );
		}
		$this->checkConnection();
		try
		{
			Utilities::markTimeStart( 'DB_TIME' );

			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand( $query );
			if ( isset( $params ) && !empty( $params ) )
			{
				$data = $command->queryAll( true, $params );
			}
			else
			{
				$data = $command->queryAll();
			}

			Utilities::markTimeStop( 'DB_TIME' );

			return $data;
		}
		catch ( \Exception $ex )
		{
			error_log( 'singlequery: ' . $ex->getMessage() . PHP_EOL . $query . PHP_EOL . print_r( $params, true ) );
			Utilities::markTimeStop( 'DB_TIME' );
			/*
                    $msg = '[QUERYFAILED]: ' . implode(':', $this->_sqlConn->errorInfo()) . "\n";
                    if (isset($GLOBALS['DB_DEBUG'])) {
                        error_log($msg . "\n$query");
                    }
*/
			throw $ex;
		}
	}

	/**
	 * Handle SQL Db requests with output as array
	 */
	public function singleSqlExecute( $query, $params = null )
	{
		if ( empty( $query ) )
		{
			throw new BadRequestException( '[NOQUERY]: No query string present in request.' );
		}
		$this->checkConnection();
		try
		{
			Utilities::markTimeStart( 'DB_TIME' );

			/** @var \CDbCommand $command */
			$command = $this->_sqlConn->createCommand( $query );
			if ( isset( $params ) && !empty( $params ) )
			{
				$data = $command->execute( $params );
			}
			else
			{
				$data = $command->execute();
			}

			Utilities::markTimeStop( 'DB_TIME' );

			return $data;
		}
		catch ( \Exception $ex )
		{
			error_log( 'singleexecute: ' . $ex->getMessage() . PHP_EOL . $query . PHP_EOL . print_r( $params, true ) );
			Utilities::markTimeStop( 'DB_TIME' );
			/*
                    $msg = '[QUERYFAILED]: ' . implode(':', $this->_sqlConn->errorInfo()) . "\n";
                    if (isset($GLOBALS['DB_DEBUG'])) {
                        error_log($msg . "\n$query");
                    }
*/
			throw $ex;
		}
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
	}

	/**
	 * Get any properties related to the table
	 *
	 * @param string $table Table name
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getTable( $table )
	{
	}

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
	}

	/**
	 * Create a single table by name, additional properties
	 *
	 * @param array $properties
	 *
	 * @throws \Exception
	 */
	public function createTable( $properties = array() )
	{
	}

	/**
	 * Update properties related to the table
	 *
	 * @param array $tables
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function updateTables( $tables = array() )
	{
	}

	/**
	 * Update properties related to the table
	 *
	 * @param array $properties
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function updateTable( $properties = array() )
	{
	}

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
	}

	/**
	 * Delete the table and all of its contents
	 *
	 * @param string $table
	 * @param bool   $check_empty
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function deleteTable( $table, $check_empty = false )
	{
	}

	/**
	 * @return int
	 */
	public function getDriverType()
	{
		return $this->_driverType;
	}
}
