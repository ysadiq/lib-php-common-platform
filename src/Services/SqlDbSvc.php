<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
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
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\SqlDbUtilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;

/**
 * SqlDbSvc.php
 * A service to handle SQL database services accessed through the REST API.
 *
 */
class SqlDbSvc extends BaseDbSvc
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default record identifier field, there is none, see schema for keys
     */
    const DEFAULT_ID_FIELD = null;
    /**
     * @var bool If true, a database cache will be created for remote databases
     */
    const ENABLE_REMOTE_CACHE = true;
    /**
     * @var string The name of the remote cache component
     */
    const REMOTE_CACHE_ID = 'cache.remote';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var \CDbConnection
     */
    protected $_dbConn;
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
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     */
    public function __construct( $config, $native = false )
    {
        if ( null === Option::get( $config, 'verb_aliases' ) )
        {
            //	Default verb aliases
            $config['verb_aliases'] = array(
                static::PATCH => static::PUT,
                static::MERGE => static::PUT,
            );
        }

        parent::__construct( $config );

        $this->_fieldCache = array();
        $this->_relatedCache = array();

        if ( false !== ( $this->_isNative = $native ) )
        {
            $this->_dbConn = Pii::db();
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

            /** @var \CDbConnection $_db */
            $_db = Pii::createComponent(
                array(
                    'class'                 => 'CDbConnection',
                    'connectionString'      => $dsn,
                    'username'              => $user,
                    'password'              => $password,
                    'charset'               => 'utf8',
                    'enableProfiling'       => defined( YII_DEBUG ),
                    'enableParamLogging'    => defined( YII_DEBUG ),
                    'schemaCachingDuration' => 3600,
                    'schemaCacheID'         => ( !$this->_isNative && static::ENABLE_REMOTE_CACHE ) ? static::REMOTE_CACHE_ID : 'cache',
                )
            );

            Pii::app()->setComponent( 'db.' . $this->_apiName, $_db );

            // 	Create pdo connection, activate later
            if ( !$this->_isNative && static::ENABLE_REMOTE_CACHE )
            {
                $_cache = Pii::createComponent(
                    array(
                        'class'                => 'CDbCache',
                        'connectionID'         => 'db' /* . $this->_apiName*/,
                        'cacheTableName'       => 'df_sys_cache_remote',
                        'autoCreateCacheTable' => true,
                        'keyPrefix'            => $this->_apiName,
                    )
                );

                try
                {
                    Pii::app()->setComponent( static::REMOTE_CACHE_ID, $_cache );
                }
                catch ( \CDbException $_ex )
                {
                    Log::error( 'Exception setting cache: ' . $_ex->getMessage() );

                    //	Disable caching...
                    $_db->schemaCachingDuration = 0;
                    $_db->schemaCacheID = null;

                    unset( $_cache );
                }

                //	Save
                $this->_dbConn = $_db;
            }
        }

        switch ( $this->_driverType = SqlDbUtilities::getDbDriverType( $this->_dbConn ) )
        {
            case SqlDbUtilities::DRV_MYSQL:
                $this->_dbConn->setAttribute( \PDO::ATTR_EMULATE_PREPARES, true );
//				$this->_sqlConn->setAttribute( 'charset', 'utf8' );
                break;

            case SqlDbUtilities::DRV_SQLSRV:
//				$this->_sqlConn->setAttribute( constant( '\\PDO::SQLSRV_ATTR_DIRECT_QUERY' ), true );
                //	These need to be on the dsn
//				$this->_sqlConn->setAttribute( 'MultipleActiveResultSets', false );
//				$this->_sqlConn->setAttribute( 'ReturnDatesAsStrings', true );
//				$this->_sqlConn->setAttribute( 'CharacterSet', 'UTF-8' );
                break;

            case SqlDbUtilities::DRV_DBLIB:
                $this->_dbConn->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
                break;
        }

        $_attributes = Option::clean( Option::get( $config, 'parameters' ) );

        if ( !empty( $_attributes ) )
        {
            $this->_dbConn->setAttributes( $_attributes );
        }
    }

    /**
     * Object destructor
     */
    public function __destruct()
    {
        if ( !$this->_isNative && isset( $this->_dbConn ) )
        {
            try
            {
                $this->_dbConn->active = false;
                $this->_dbConn = null;
            }
            catch ( \PDOException $_ex )
            {
                error_log( "Failed to disconnect from database.\n{$_ex->getMessage()}" );
            }
            catch ( \Exception $_ex )
            {
                error_log( "Failed to disconnect from database.\n{$_ex->getMessage()}" );
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function checkConnection()
    {
        if ( !isset( $this->_dbConn ) )
        {
            throw new InternalServerErrorException( 'Database connection has not been initialized.' );
        }

        try
        {
            $this->_dbConn->setActive( true );
        }
        catch ( \PDOException $_ex )
        {
            throw new InternalServerErrorException( "Failed to connect to database.\n{$_ex->getMessage()}" );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to connect to database.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * Corrects capitalization, etc. on table names
     *
     * @param $name
     *
     * @return string
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function correctTableName( $name )
    {
        return SqlDbUtilities::correctTableName( $this->_dbConn, $name );
    }

    /**
     * Ensures a table is not a system table and that you have permission to access it
     *
     * @param string $table
     * @param string $action
     *
     * @throws \Exception
     */
    protected function validateTableAccess( $table, $action = null )
    {
        if ( $this->_isNative )
        {
            static $_length;

            if ( !$_length )
            {
                $_length = strlen( SystemManager::SYSTEM_TABLE_PREFIX );
            }

            if ( 0 === substr_compare( $table, SystemManager::SYSTEM_TABLE_PREFIX, 0, $_length ) )
            {
                throw new NotFoundException( "Table '$table' not found." );
            }
        }

        parent::validateTableAccess( $table, $action );
    }

    /**
     * @param null|array $post_data
     *
     * @return array
     */
    protected function _gatherExtrasFromRequest( $post_data = null )
    {
        $_extras = parent::_gatherExtrasFromRequest( $post_data );

        // All calls can request related data to be returned
        $_relations = array();
        $_related = FilterInput::request( 'related', Option::get( $post_data, 'related' ) );
        if ( !empty( $_related ) )
        {
            if ( '*' == $_related )
            {
                $_relations = '*';
            }
            else
            {
                if ( !is_array( $_related ) )
                {
                    $_related = array_map( 'trim', explode( ',', $_related ) );
                }
                foreach ( $_related as $_relative )
                {
                    $_extraFields = FilterInput::request( $_relative . '_fields', '*' );
                    $_extraOrder = FilterInput::request( $_relative . '_order', '' );
                    $_relations[] = array( 'name' => $_relative, 'fields' => $_extraFields, 'order' => $_extraOrder );
                }
            }
        }
        $_extras['related'] = $_relations;

        $_extras['include_schema'] = FilterInput::request(
            'include_schema',
            Option::getBool( $post_data, 'include_schema' ),
            FILTER_VALIDATE_BOOLEAN
        );

        // rollback all db changes in a transaction, if applicable
        $_extras['rollback'] = FilterInput::request(
            'rollback',
            Option::getBool( $post_data, 'rollback' ),
            FILTER_VALIDATE_BOOLEAN
        );

        // continue batch processing if an error occurs, if applicable
        $_extras['continue'] = FilterInput::request(
            'continue',
            Option::getBool( $post_data, 'continue' ),
            FILTER_VALIDATE_BOOLEAN
        );

        // allow deleting related records in update requests, if applicable
        $_extras['allow_related_delete'] = FilterInput::request(
            'allow_related_delete',
            Option::getBool( $post_data, 'allow_related_delete' ),
            FILTER_VALIDATE_BOOLEAN
        );

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
            $_result = SqlDbUtilities::describeDatabase( $this->_dbConn, '', $_exclude );

            $_resources = array();
            foreach ( $_result as $_table )
            {
                if ( null != $_name = Option::get( $_table, 'name' ) )
                {
                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        $_table['access'] = $_access;
                        $_resources[] = $_table;
                    }
                }
            }

            return array( 'resource' => $_resources );
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to list resources for this service.\n{$_ex->getMessage()}" );
        }
    }

    // Handle administrative options, table add, delete, etc

    /**
     * {@inheritdoc}
     */
    public function getTable( $table )
    {
        $_name = ( is_array( $table ) ) ? Option::get( $table, 'name' ) : $table;
        if ( empty( $_name ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        try
        {
            $_result = SqlDbUtilities::describeTable( $this->_dbConn, $_name );
            $_result['access'] = $this->getPermissions( $_name );

            return $_result;
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to get table properties for table '$_name'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTables( $tables = array() )
    {
        throw new BadRequestException( 'Editing table properties is only allowed through a SQL DB Schema service.' );
    }

    /**
     * {@inheritdoc}
     */
    public function createTable( $properties = array() )
    {
        throw new BadRequestException( 'Editing table properties is only allowed through a SQL DB Schema service.' );
    }

    /**
     * {@inheritdoc}
     */
    public function updateTables( $tables = array() )
    {
        throw new BadRequestException( 'Editing table properties is only allowed through a SQL DB Schema service.' );
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable( $properties = array() )
    {
        throw new BadRequestException( 'Creating table properties is only allowed through a SQL DB Schema service.' );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTables( $tables = array(), $check_empty = false )
    {
        throw new BadRequestException( 'Editing table properties is only allowed through a SQL DB Schema service.' );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTable( $table, $check_empty = false )
    {
        throw new BadRequestException( 'Editing table properties is only allowed through a SQL DB Schema service.' );
    }

    //-------- Table Records Operations ---------------------
    // records is an array of field arrays

    /**
     * {@inheritdoc}
     */
    public function createRecords( $table, $records, $extras = array() )
    {
        $records = static::checkIncomingData( $records, null, true, 'There are no record sets in the request.' );
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $records ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_idFields = Option::get( $extras, 'id_field' );
        $_fields = Option::get( $extras, 'fields' );
        $_related = Option::get( $extras, 'related' );
        $_allowRelatedDelete = Option::getBool( $extras, 'allow_related_delete', false );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        try
        {
            $_out = array();
            $_errors = array();
            $_fieldInfo = $this->describeTableFields( $table );
            $_relatedInfo = $this->describeTableRelated( $table );
            $_idFieldsInfo = static::_getIdFieldsInfo( $_idFields, $_fieldInfo );

            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand();
            $_transaction = null;
            if ( $_rollback && !$_isSingle )
            {
                $_transaction = $this->_dbConn->beginTransaction();
            }

            foreach ( $records as $_key => $_record )
            {
                try
                {
                    $_parsed = $this->parseRecord( $_record, $_fieldInfo, $_ssFilters );
                    if ( 0 >= count( $_parsed ) )
                    {
                        throw new BadRequestException( 'No valid fields found in request: ' . print_r( $_record, true ) );
                    }

                    // simple insert request
                    $command->reset();
                    $rows = $command->insert( $table, $_parsed );
                    if ( 0 >= $rows )
                    {
                        throw new InternalServerErrorException( "Record [$_key] insert failed for table '$table'." );
                    }

                    $_id = array();
                    if ( !empty( $_idFieldsInfo ) )
                    {
                        foreach ( $_idFieldsInfo as $_info )
                        {
                            $_idName = Option::get( $_info, 'name' );
                            if ( Option::getBool( $_info, 'auto_increment' ) )
                            {
                                $_id[$_idName] = (int)$this->_dbConn->lastInsertID;
                            }
                            else
                            {
                                // must have been passed in with request
                                $_id[$_idName] = Option::get( $_record, $_idName );
                            }
                        }

                        if ( !empty( $_relatedInfo ) )
                        {
                            $this->updateRelations( $table, $_record, $_id, $_relatedInfo, $_allowRelatedDelete );
                        }
                    }

                    $_out[$_key] = $_id;
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback && $_transaction )
                    {
                        $_transaction->rollBack();
                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( 0 === $_key )
                        {
                            // first error, don't worry about batch just throw it
                            throw $_ex;
                        }

                        // mark last error and index for batch results
                        $_errors[] = $_key;
                        $_out[$_key] = $_ex->getMessage();
                        break;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_key;
                    $_out[$_key] = $_ex->getMessage();
                }
            }

            if ( $_rollback && $_transaction )
            {
                $_transaction->commit();
            }

            if ( !empty( $_errors ) )
            {
                $_msg = array( 'error' => $_errors, 'record' => $_out );
                throw new BadRequestException( 'Batch Error: Not all records could be created.', null, null, $_msg );
            }

            if ( static::_requireMoreFields( $_fields, $_idFields ) || !empty( $_related ) )
            {
                // ids array are now more like records
                return $this->retrieveRecords( $table, $_out, $extras );
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            if ( $_ex instanceof RestException )
            {
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to create records in '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecords( $table, $records, $extras = array() )
    {
        $records = static::checkIncomingData( $records, null, true, 'There are no record sets in the request.' );
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $records ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_idField = Option::get( $extras, 'id_field' );
        $_fields = Option::get( $extras, 'fields' );
        $_related = Option::get( $extras, 'related' );
        $_allowRelatedDelete = Option::getBool( $extras, 'allow_related_delete', false );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        try
        {
            $_fieldInfo = $this->describeTableFields( $table );
            $_relatedInfo = $this->describeTableRelated( $table );
            $_idFieldsInfo = static::_getIdFieldsInfo( $_idField, $_fieldInfo );
            if ( empty( $_idFieldsInfo ) )
            {
                throw new BadRequestException( 'Updating by ids requires at least one identifying field.' );
            }

            $_idFieldNames = array();
            $_where = array();
            $_params = array();
            if ( !empty( $_idFieldsInfo ) )
            {
                foreach ( $_idFieldsInfo as $_info )
                {
                    $_idName = Option::get( $_info, 'name' );
                    $_idFieldNames[] = $_idName;
                    $_where[] = "$_idName = :$_idName";
                }
            }

            $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
            if ( !empty( $_serverFilter ) )
            {
                $_where[] = $_serverFilter['filter'];
                $_params = $_serverFilter['params'];
            }

            if ( count( $_where ) > 1 )
            {
                array_unshift( $_where, 'AND' );
            }
            else
            {
                $_where = $_where[0];
            }

            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();
            $_errors = array();
            $_transaction = null;

            if ( $_rollback && !$_isSingle )
            {
                $_transaction = $this->_dbConn->beginTransaction();
            }

            $_out = array();
            foreach ( $records as $_key => $_record )
            {
                try
                {
                    $_id = array();
                    if ( !empty( $_idFieldsInfo ) )
                    {
                        foreach ( $_idFieldsInfo as $_info )
                        {
                            $_idName = Option::get( $_info, 'name' );
                            // must have been passed in with request
                            $_temp = Option::get( $_record, $_idName, null, true );
                            if ( empty( $_temp ) )
                            {
                                throw new BadRequestException( "Identifying field '$_idName' can not be empty for update record [$_key] request." );
                            }

                            $_id[$_idName] = $_temp;
                            $_params[":$_idName"] = $_temp;
                        }
                    }

                    $_parsed = $this->parseRecord( $_record, $_fieldInfo, $_ssFilters, true );
                    if ( !empty( $_parsed ) )
                    {
                        // simple update request
                        $_command->reset();
                        $rows = $_command->update( $table, $_parsed, $_where, $_params );
                        if ( 0 >= $rows )
                        {
                            throw new NotFoundException( "Record with id '" . print_r( $_id, true ) . "' not found in table '$table'." );
                        }
                    }

                    if ( !empty( $_relatedInfo ) )
                    {
                        $this->updateRelations( $table, $_record, $_id, $_relatedInfo, $_allowRelatedDelete );
                    }

                    $_out[$_key] = $_id;
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback && $_transaction )
                    {
                        $_transaction->rollBack();
                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( 0 === $_key )
                        {
                            // first error, don't worry about batch just throw it
                            throw $_ex;
                        }

                        // mark last error and index for batch results
                        $_errors[] = $_key;
                        $_out[$_key] = $_ex->getMessage();
                        break;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_key;
                    $_out[$_key] = $_ex->getMessage();
                }
            }

            if ( $_rollback && $_transaction )
            {
                $_transaction->commit();
            }

            if ( !empty( $_errors ) )
            {
                $_msg = array( 'error' => $_errors, 'record' => $_out );
                throw new BadRequestException( 'Batch Error: Not all records could be updated.', null, null, $_msg );
            }

            if ( static::_requireMoreFields( $_fields, $_idFieldNames ) || !empty( $_related ) )
            {
                // ids array are now more like records
                return $this->retrieveRecords( $table, $_out, $extras );
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            if ( $_ex instanceof RestException )
            {
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to update records in '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = static::checkIncomingData( $record, null, false, 'There are no fields in the record.' );
        $table = $this->correctTableName( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_related = Option::get( $extras, 'related' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        try
        {
            $_fieldInfo = $this->describeTableFields( $table );
            $_relatedInfo = $this->describeTableRelated( $table );
            $_idFieldNames = SqlDbUtilities::getPrimaryKeys( $_fieldInfo, true );
            $_fields = ( empty( $_fields ) ) ? $_idFieldNames : $_fields;
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldInfo );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );
            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

            $_parsed = $this->parseRecord( $record, $_fieldInfo, $_ssFilters, true );

            // build filter string if necessary, add server-side filters if necessary
            $_ssFilters = Option::get( $extras, 'ss_filters' );
            $_criteria = $this->_convertFilterToNative( $filter, $params, $_ssFilters );
            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );

            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand();
            if ( !empty( $_parsed ) )
            {
                $command->update( $table, $_parsed, $_where, $_params );
            }

            $_results = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );

            // update related info
            if ( !empty( $_relatedInfo ) )
            {

                // get latest with related changes if requested
                if ( !empty( $_related ) )
                {
                    $_results = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
                }
            }

            return $_results;
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to update records in '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        $record = static::checkIncomingData( $record, null, false, 'There are no fields in the record.' );
        $ids = static::checkIncomingData( $ids, ',', true, "There are no identifiers in the update request." );
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_idField = Option::get( $extras, 'id_field' );
        $_fields = Option::get( $extras, 'fields' );
        $_related = Option::get( $extras, 'related' );
        $_allowRelatedDelete = Option::getBool( $extras, 'allow_related_delete', false );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        try
        {
            $_fieldInfo = $this->describeTableFields( $table );
            $_relatedInfo = $this->describeTableRelated( $table );
            $_idFieldsInfo = static::_getIdFieldsInfo( $_idField, $_fieldInfo );
            if ( empty( $_idFieldsInfo ) )
            {
                throw new BadRequestException( 'Updating by ids requires at least one identifying field.' );
            }

            $_multipleIdsRequired = ( 1 < count( $_idFieldsInfo ) );
            $_arrayIdsGiven = ( is_array( $ids[0] ) );
            if ( $_multipleIdsRequired && !$_arrayIdsGiven )
            {
                throw new BadRequestException( 'Updating by ids requires multiple identifying fields.' );
            }

            $_needToIterate = ( $_multipleIdsRequired || $_arrayIdsGiven );

            $_parsed = $this->parseRecord( $record, $_fieldInfo, $_ssFilters, true );

            $_idFieldNames = array();
            $_where = array();
            $_params = array();
            if ( $_needToIterate )
            {
                foreach ( $_idFieldsInfo as $_info )
                {
                    $_idName = Option::get( $_info, 'name' );
                    $_idFieldNames[] = $_idName;
                    $_where[] = "$_idName = :$_idName";
                }
            }
            else
            {
                // single id field, let's use the quicker 'in' clause
                $_idName = Option::get( $_idFieldsInfo[0], 'name' );
                $_idFieldNames[] = $_idName;
                $_where[] = array( 'in', $_idName, $ids );
            }

            $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
            if ( !empty( $_serverFilter ) )
            {
                $_where[] = $_serverFilter['filter'];
                $_params = $_serverFilter['params'];
            }

            if ( count( $_where ) > 1 )
            {
                array_unshift( $_where, 'AND' );
            }
            else
            {
                $_where = $_where[0];
            }

            $_requireMore = ( static::_requireMoreFields( $_fields, $_idFieldNames ) || !empty( $_related ) );
            $_fields = ( empty( $_fields ) ) ? $_idFieldNames : $_fields;
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldInfo );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );
            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

            $_outResults = array();

            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();
            $_errors = array();
            $_transaction = null;

            if ( $_rollback && !$_isSingle )
            {
                $_transaction = $this->_dbConn->beginTransaction();
            }

            if ( !$_needToIterate && !empty( $_parsed ) )
            {
                // simple update request
                $_rows = $_command->update( $table, $_parsed, $_where, $_params );
                if ( $_rows != count( $ids ) )
                {
                    if ( 0 === $_rows )
                    {
                        throw new NotFoundException( 'Records requested were not found.' );
                    }
                    throw new BadRequestException( 'Records changed and ids requested don\'t match' );
                }
            }

            $_out = array();
            foreach ( $ids as $_key => $_value )
            {
                try
                {
                    $_id = array();
                    if ( $_arrayIdsGiven )
                    {
                        foreach ( $_idFieldsInfo as $_info )
                        {
                            $_idName = Option::get( $_info, 'name' );
                            // must have been passed in with request
                            $_temp = Option::get( $_value, $_idName, null, true );
                            if ( empty( $_temp ) )
                            {
                                throw new BadRequestException( "Identifying field '$_idName' can not be empty for update record [$_key] request." );
                            }

                            $_id[$_idName] = $_temp;
                            $_params[":$_idName"] = $_temp;
                        }
                    }
                    else
                    {
                        $_info = $_idFieldsInfo[0];
                        $_idName = Option::get( $_info, 'name' );
                        if ( empty( $_value ) )
                        {
                            throw new BadRequestException( "Identifying field '$_idName' can not be empty for update record [$_key] request." );
                        }

                        $_id[$_idName] = $_value;
                        $_params[":$_idName"] = $_value;
                    }

                    if ( $_needToIterate && !empty( $_parsed ) )
                    {
                        // simple update request
                        $_command->reset();
                        $rows = $_command->update( $table, $_parsed, $_where, $_params );
                        if ( 0 >= $rows )
                        {
                            throw new NotFoundException( "Record with id '" . print_r( $_id, true ) . "' not found in table '$table'." );
                        }
                    }

                    if ( !empty( $_relatedInfo ) )
                    {
                        $this->updateRelations( $table, $record, $_id, $_relatedInfo, $_allowRelatedDelete );
                    }

                    if ( !$_needToIterate && $_requireMore )
                    {
                        $_outResults[] = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
                    }

                    $_out[$_key] = $_id;
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback && $_transaction )
                    {
                        $_transaction->rollBack();
                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( 0 === $_key )
                        {
                            // first error, don't worry about batch just throw it
                            throw $_ex;
                        }

                        // mark last error and index for batch results
                        $_errors[] = $_key;
                        $_out[$_key] = $_ex->getMessage();
                        break;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_key;
                    $_out[$_key] = $_ex->getMessage();
                }
            }

            if ( $_rollback && $_transaction )
            {
                $_transaction->commit();
            }

            if ( !empty( $_errors ) )
            {
                $_msg = array( 'error' => $_errors, 'record' => $_out );
                throw new BadRequestException( 'Batch Error: Not all records could be updated.', null, null, $_msg );
            }

            if ( $_needToIterate && $_requireMore )
            {
                $_outResults = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
            }

            if ( !empty( $_outResults ) )
            {
                return $_outResults;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            if ( $_ex instanceof RestException )
            {
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to update records in '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecords( $table, $records, $extras = array() )
    {
        // currently the same as update here
        return $this->updateRecords( $table, $records, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        // currently the same as update here
        return $this->updateRecordsByFilter( $table, $record, $filter, $params, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        // currently the same as update here
        return $this->updateRecordsByIds( $table, $record, $ids, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function truncateTable( $table, $extras = array() )
    {
        // truncate the table, return success
        $table = $this->correctTableName( $table );
        try
        {
            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand();

            // build filter string if necessary, add server-side filters if necessary
            $_ssFilters = Option::get( $extras, 'ss_filters' );
            $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
            if ( !empty( $_serverFilter ) )
            {
                $command->delete( $table, $_serverFilter['filter'], $_serverFilter['params'] );
            }
            else
            {
                $command->truncateTable( $table );
            }

            return array( 'success' => true );
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecords( $table, $records, $extras = array() )
    {
        $records = static::checkIncomingData( $records, null, true, 'There are no record sets in the request.' );
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $records ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_idField = Option::get( $extras, 'id_field' );
        $_fields = Option::get( $extras, 'fields' );
        $_related = Option::get( $extras, 'related' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        try
        {
            $_fieldInfo = $this->describeTableFields( $table );
            $_idFieldsInfo = static::_getIdFieldsInfo( $_idField, $_fieldInfo );
            if ( empty( $_idFieldsInfo ) )
            {
                throw new BadRequestException( 'Deleting by ids requires at least one identifying field.' );
            }

            $_needToIterate = ( 1 < count( $_idFieldsInfo ) );

            $_idFieldNames = array();
            $_where = array();
            $_params = array();
            if ( $_needToIterate )
            {
                foreach ( $_idFieldsInfo as $_info )
                {
                    $_idName = Option::get( $_info, 'name' );
                    $_idFieldNames[] = $_idName;
                    $_where[] = "$_idName = :$_idName";
                }
            }
            else
            {
                // single id field, let's use the quicker 'in' clause
                $_idName = Option::get( $_idFieldsInfo[0], 'name' );
                $_idFieldNames[] = $_idName;
                $_out = array();
                foreach ( $records as $_key => $_record )
                {
                    $_id = Option::get( $_record, $_idName );
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '$_idName' can not be empty for delete record [$_key] request." );
                    }

                    $_out[] = $_id;
                }

                $_where[] = array( 'in', $_idName, $_out );
            }

            $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
            if ( !empty( $_serverFilter ) )
            {
                $_where[] = $_serverFilter['filter'];
                $_params = $_serverFilter['params'];
            }

            if ( count( $_where ) > 1 )
            {
                array_unshift( $_where, 'AND' );
            }
            else
            {
                $_where = $_where[0];
            }

            $_requireMore = ( static::_requireMoreFields( $_fields, $_idFieldNames ) || !empty( $_related ) );
            $_fields = ( empty( $_fields ) ) ? $_idFieldNames : $_fields;
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldInfo );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );
            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

            $_outResults = array();

            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();
            $_errors = array();
            $_transaction = null;

            if ( $_rollback && !$_isSingle )
            {
                $_transaction = $this->_dbConn->beginTransaction();
            }

            if ( !$_needToIterate )
            {
                if ( $_requireMore )
                {
                    // get the returnable fields first, then issue delete
                    $_outResults = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
                }

                // simple delete request
                $_rows = $_command->delete( $table, $_where, $_params );
                if ( $_rows != count( $records ) )
                {
                    if ( 0 === $_rows )
                    {
                        throw new NotFoundException( 'Records requested were not found.' );
                    }
                    throw new BadRequestException( 'Records deleted and ids requested don\'t match' );
                }
            }

            $_out = array();
            foreach ( $records as $_key => $_record )
            {
                try
                {
                    $_id = array();
                    if ( !empty( $_idFieldsInfo ) )
                    {
                        foreach ( $_idFieldsInfo as $_info )
                        {
                            $_idName = Option::get( $_info, 'name' );
                            // must have been passed in with request
                            $_temp = Option::get( $_record, $_idName, null, true );
                            if ( empty( $_temp ) )
                            {
                                throw new BadRequestException( "Identifying field '$_idName' can not be empty for delete record [$_key] request." );
                            }

                            $_id[$_idName] = $_temp;
                            $_params[":$_idName"] = $_temp;
                        }
                    }

                    if ( $_needToIterate )
                    {
                        if ( $_requireMore )
                        {
                            $_outResults[] = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
                        }
                        // simple update request
                        $_command->reset();
                        $rows = $_command->delete( $table, $_where, $_params );
                        if ( 0 >= $rows )
                        {
                            throw new NotFoundException( "Record with id '" . print_r( $_id, true ) . "' not found in table '$table'." );
                        }
                    }

                    $_out[$_key] = $_id;
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback && $_transaction )
                    {
                        $_transaction->rollBack();
                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( 0 == $_key )
                        {
                            // first error, don't worry about batch just throw it
                            throw $_ex;
                        }

                        // mark last error and index for batch results
                        $_errors[] = $_key;
                        $_out[$_key] = $_ex->getMessage();
                        break;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_key;
                    $_out[$_key] = $_ex->getMessage();
                }
            }

            if ( $_rollback && $_transaction )
            {
                $_transaction->commit();
            }

            if ( !empty( $_errors ) )
            {
                $_msg = array( 'error' => $_errors, 'record' => $_out );
                throw new BadRequestException( 'Batch Error: Not all records could be deleted.', null, null, $_msg );
            }

            if ( !empty( $_outResults ) )
            {
                return $_outResults;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            if ( $_ex instanceof RestException )
            {
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecordsByFilter( $table, $filter, $params = array(), $extras = array() )
    {
        if ( empty( $filter ) )
        {
            throw new BadRequestException( "Filter for delete request can not be empty." );
        }

        $table = $this->correctTableName( $table );
        try
        {
            // get the returnable fields first, then issue delete
            $_results = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );

            // build filter string if necessary, add server-side filters if necessary
            $_ssFilters = Option::get( $extras, 'ss_filters' );
            $_criteria = $this->_convertFilterToNative( $filter, $params, $_ssFilters );
            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );

            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();
            $_command->delete( $table, $_where, $_params );

            return $_results;
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecordsByIds( $table, $ids, $extras = array() )
    {
        $ids = static::checkIncomingData( $ids, ',', true, "There are no identifiers in the request." );
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_idField = Option::get( $extras, 'id_field' );
        $_fields = Option::get( $extras, 'fields' );
        $_related = Option::get( $extras, 'related' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        try
        {
            $_fieldInfo = $this->describeTableFields( $table );
            $_idFieldsInfo = static::_getIdFieldsInfo( $_idField, $_fieldInfo );
            if ( empty( $_idFieldsInfo ) )
            {
                throw new BadRequestException( 'Deleting by ids requires at least one identifying field.' );
            }

            $_multipleIdsRequired = ( 1 < count( $_idFieldsInfo ) );
            $_arrayIdsGiven = ( is_array( $ids[0] ) );
            if ( $_multipleIdsRequired && !$_arrayIdsGiven )
            {
                throw new BadRequestException( 'Deleting by ids requires multiple identifying fields.' );
            }

            $_needToIterate = ( $_multipleIdsRequired || $_arrayIdsGiven );

            $_idFieldNames = array();
            $_where = array();
            $_params = array();
            if ( $_needToIterate )
            {
                foreach ( $_idFieldsInfo as $_info )
                {
                    $_idName = Option::get( $_info, 'name' );
                    $_idFieldNames[] = $_idName;
                    $_where[] = "$_idName = :$_idName";
                }
            }
            else
            {
                // single id field, let's use the quicker 'in' clause
                $_idName = Option::get( $_idFieldsInfo[0], 'name' );
                $_idFieldNames[] = $_idName;
                $_where[] = array( 'in', $_idName, $ids );
            }

            $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
            if ( !empty( $_serverFilter ) )
            {
                $_where[] = $_serverFilter['filter'];
                $_params = $_serverFilter['params'];
            }

            if ( count( $_where ) > 1 )
            {
                array_unshift( $_where, 'AND' );
            }
            else
            {
                $_where = $_where[0];
            }

            $_requireMore = ( static::_requireMoreFields( $_fields, $_idFieldNames ) || !empty( $_related ) );
            $_fields = ( empty( $_fields ) ) ? $_idFieldNames : $_fields;
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldInfo );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );
            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

            $_outResults = array();

            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();
            $_errors = array();
            $_transaction = null;

            if ( $_rollback && !$_isSingle )
            {
                $_transaction = $this->_dbConn->beginTransaction();
            }

            if ( !$_needToIterate )
            {
                if ( $_requireMore )
                {
                    // get the returnable fields first, then issue delete
                    $_outResults = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
                }

                // simple delete request
                $_rows = $_command->delete( $table, $_where, $_params );
                if ( $_rows != count( $ids ) )
                {
                    if ( 0 === $_rows )
                    {
                        throw new NotFoundException( 'Records requested were not found.' );
                    }
                    throw new BadRequestException( 'Records deleted and ids requested don\'t match' );
                }
            }

            $_out = array();
            foreach ( $ids as $_key => $_value )
            {
                try
                {
                    $_id = array();
                    if ( $_arrayIdsGiven )
                    {
                        foreach ( $_idFieldsInfo as $_info )
                        {
                            $_idName = Option::get( $_info, 'name' );
                            // must have been passed in with request
                            $_temp = Option::get( $_value, $_idName, null, true );
                            if ( empty( $_temp ) )
                            {
                                throw new BadRequestException( "Identifying field '$_idName' can not be empty for delete record [$_key] request." );
                            }

                            $_id[$_idName] = $_temp;
                            $_params[":$_idName"] = $_temp;
                        }
                    }
                    else
                    {
                        $_info = $_idFieldsInfo[0];
                        $_idName = Option::get( $_info, 'name' );
                        if ( empty( $_value ) )
                        {
                            throw new BadRequestException( "Identifying field '$_idName' can not be empty for delete record [$_key] request." );
                        }

                        $_id[$_idName] = $_value;
                        $_params[":$_idName"] = $_value;
                    }

                    if ( $_needToIterate )
                    {
                        if ( $_requireMore )
                        {
                            $_outResults[] = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
                        }
                        // simple update request
                        $_command->reset();
                        $rows = $_command->delete( $table, $_where, $_params );
                        if ( 0 >= $rows )
                        {
                            throw new NotFoundException( "Record with identifier '" . print_r( $_id, true ) . "' not found in table '$table'." );
                        }
                    }

                    $_out[$_key] = $_id;
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback && $_transaction )
                    {
                        $_transaction->rollBack();
                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( empty( $_errors ) )
                        {
                            // first error, don't worry about batch just throw it
                            throw $_ex;
                        }

                        // mark last error and index for batch results
                        $_errors[] = $_key;
                        $_out[$_key] = $_ex->getMessage();
                        break;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_key;
                    $_out[$_key] = $_ex->getMessage();
                }
            }

            if ( $_rollback && $_transaction )
            {
                $_transaction->commit();
            }

            if ( !empty( $_errors ) )
            {
                $_msg = array( 'error' => $_errors, 'record' => $_out );
                throw new BadRequestException( 'Batch Error: Not all records could be deleted.', null, null, $_msg );
            }

            if ( !empty( $_outResults ) )
            {
                return $_outResults;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            if ( $_ex instanceof RestException )
            {
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() )
    {
        $table = $this->correctTableName( $table );

        try
        {
            $_availFields = $this->describeTableFields( $table );
            $_fields = Option::get( $extras, 'fields' );
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_availFields );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );
            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

            // build filter string if necessary, add server-side filters if necessary
            $_ssFilters = Option::get( $extras, 'ss_filters' );
            $_criteria = $this->_convertFilterToNative( $filter, $params, $_ssFilters );
            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );

            return $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to retrieve records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecords( $table, $records, $extras = array() )
    {
        $records = static::checkIncomingData( $records, null, true, 'There are no record sets in the request.' );
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

        return $this->retrieveRecordsByIds( $table, $ids, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByIds( $table, $ids, $extras = array() )
    {
        $ids = static::checkIncomingData( $ids, ',', true, "There are no identifiers in the request." );
        $table = $this->correctTableName( $table );

        $_continue = Option::getBool( $extras, 'continue', false );
        $_idField = Option::get( $extras, 'id_field' );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_isSingle = ( 1 == count( $ids ) );
        try
        {
            $_fieldInfo = $this->describeTableFields( $table );
            $_idFieldsInfo = static::_getIdFieldsInfo( $_idField, $_fieldInfo );
            if ( empty( $_idFieldsInfo ) )
            {
                throw new BadRequestException( 'Retrieving by ids requires at least one identifying field.' );
            }

            $_multipleIdsRequired = ( 1 < count( $_idFieldsInfo ) );
            $_arrayIdsGiven = ( is_array( $ids[0] ) );
            if ( $_multipleIdsRequired && !$_arrayIdsGiven )
            {
                throw new BadRequestException( 'Retrieving by ids requires multiple identifying fields.' );
            }

            $_needToIterate = ( $_multipleIdsRequired || $_arrayIdsGiven );

            $_idFieldNames = array();
            $_where = array();
            $_params = array();
            if ( $_needToIterate )
            {
                foreach ( $_idFieldsInfo as $_info )
                {
                    $_idName = Option::get( $_info, 'name' );
                    $_idFieldNames[] = $_idName;
                    $_where[] = "$_idName = :$_idName";
                }
            }
            else
            {
                // single id field, let's use the quicker 'in' clause
                $_idName = Option::get( $_idFieldsInfo[0], 'name' );
                $_idFieldNames[] = $_idName;
                $_where[] = array( 'in', $_idName, $ids );
            }

            $_serverFilter = $this->buildQueryStringFromData( $_ssFilters, true );
            if ( !empty( $_serverFilter ) )
            {
                $_where[] = $_serverFilter['filter'];
                $_params = $_serverFilter['params'];
            }

            if ( count( $_where ) > 1 )
            {
                array_unshift( $_where, 'AND' );
            }
            else
            {
                $_where = $_where[0];
            }

            if ( !empty( $_fields ) && ( '*' !== $_fields ) )
            {
                foreach ( $_idFieldNames as $_idName )
                {
                    // add id field to field list
                    $_fields = DataFormat::addOnceToList( $_fields, $_idName, ',' );
                }
            }
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_fieldInfo );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );

            $_data = array();
            $_results = array();
            $_errors = array();
            $_out = array();
            if ( !$_needToIterate )
            {
                // retrieve by id list
                $_data = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
                $_meta = Option::get( $_data, 'meta', null, true );
            }

            foreach ( $ids as $_key => $_value )
            {
                try
                {
                    $_id = array();
                    if ( $_arrayIdsGiven )
                    {
                        foreach ( $_idFieldsInfo as $_info )
                        {
                            $_idName = Option::get( $_info, 'name' );
                            // must have been passed in with request
                            $_temp = Option::get( $_value, $_idName, null, true );
                            if ( empty( $_temp ) )
                            {
                                throw new BadRequestException( "Identifying field '$_idName' can not be empty for delete record [$_key] request." );
                            }

                            $_id[$_idName] = $_temp;
                            $_params[":$_idName"] = $_temp;
                        }
                    }
                    else
                    {
                        $_info = $_idFieldsInfo[0];
                        $_idName = Option::get( $_info, 'name' );
                        if ( empty( $_value ) )
                        {
                            throw new BadRequestException( "Identifying field '$_idName' can not be empty for delete record [$_key] request." );
                        }

                        $_id[$_idName] = $_value;
                        $_params[":$_idName"] = $_value;
                    }

                    if ( $_needToIterate )
                    {
                        // single retrieve
                        $_temp = $this->_recordQuery( $table, $_fields, $_where, $_params, $_bindings, $extras );
                        if ( empty( $_temp ) )
                        {
                            throw new NotFoundException( "Record with identifier '" . print_r( $_id, true ) . "' not found in table '$table'." );
                        }

                        $_meta = Option::get( $_data, 'meta', null, true );
                        $_results[] = $_temp;
                    }
                    else
                    {
                        // order returned data by received ids, fill in error for those not found
                        $_idName = $_idFieldNames[0];
                        $_foundRecord = null;
                        foreach ( $_data as $_record )
                        {
                            if ( isset( $_record[$_idName] ) && ( $_record[$_idName] == $_value ) )
                            {
                                $_foundRecord = $_record;
                                break;
                            }
                        }
                        if ( isset( $_foundRecord ) )
                        {
                            $_results[] = $_foundRecord;
                        }
                        else
                        {
                            throw new NotFoundException( "Record not found for id '$_value'" );
                        }
                    }

                    $_out[$_key] = $_id;
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( empty( $_errors ) )
                        {
                            // first error, don't worry about batch just throw it
                            throw $_ex;
                        }

                        // mark last error and index for batch results
                        $_errors[] = $_key;
                        $_out[$_key] = $_ex->getMessage();
                        break;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_key;
                    $_out[$_key] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                $_msg = array( 'error' => $_errors, 'record' => $_out );
                throw new BadRequestException( 'Batch Error: Not all records could be retrieved.', null, null, $_msg );
            }

            if ( !empty( $_meta ) )
            {
                $_results['meta'] = $_meta;
            }

            return $_results;
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to retrieve records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    // Helper methods

    protected function _recordQuery( $from, $select, $where, $bind_values, $bind_columns, $extras )
    {
        $_order = Option::get( $extras, 'order' );
        $_limit = intval( Option::get( $extras, 'limit', 0 ) );
        $_offset = intval( Option::get( $extras, 'offset', 0 ) );
        $_maxAllowed = static::getMaxRecordsReturnedLimit();
        $_needLimit = false;

        // use query builder
        /** @var \CDbCommand $_command */
        $_command = $this->_dbConn->createCommand();
        $_command->select( $select );
        $_command->from( $from );

        if ( !empty( $where ) )
        {
            $_command->where( $where );
        }
        if ( !empty( $bind_values ) )
        {
            if ( isset( $bind_values[0] ) )
            {
                // using PDO ? prepare statements, requires 1-based array...yeah crazy, I know!
                $bind_values = static::one_index_array( $bind_values );
            }
            $_command->bindValues( $bind_values );
        }

        if ( !empty( $_order ) )
        {
            $_command->order( $_order );
        }
        if ( $_offset > 0 )
        {
            $_command->offset( $_offset );
        }
        if ( ( $_limit < 1 ) || ( $_limit > $_maxAllowed ) )
        {
            // impose a limit to protect server
            $_limit = $_maxAllowed;
            $_needLimit = true;
        }
        $_command->limit( $_limit );

        $this->checkConnection();
        $_reader = $_command->query();
        $_data = array();
        $_dummy = array();
        foreach ( $bind_columns as $_binding )
        {
            $_name = Option::get( $_binding, 'name' );
            $_type = Option::get( $_binding, 'pdo_type' );
            $_reader->bindColumn( $_name, $_dummy[$_name], $_type );
        }
        $_reader->setFetchMode( \PDO::FETCH_BOUND );
        $_row = 0;
        while ( false !== $_reader->read() )
        {
            $_temp = array();
            foreach ( $bind_columns as $_binding )
            {
                $_name = Option::get( $_binding, 'name' );
                $_type = Option::get( $_binding, 'php_type' );
                $_value = Option::get( $_dummy, $_name );
                if ( 'float' == $_type )
                {
                    $_value = floatval( $_value );
                }
                $_temp[$_name] = $_value;
            }

            $_data[$_row++] = $_temp;
        }

        $_meta = array();
        $_includeCount = Option::getBool( $extras, 'include_count', false );
        // count total records
        if ( $_includeCount || $_needLimit )
        {
            $_command->reset();
            $_command->select( '(COUNT(*)) as ' . $this->_dbConn->quoteColumnName( 'count' ) );
            $_command->from( $from );
            if ( !empty( $where ) )
            {
                $_command->where( $where );
            }
            if ( !empty( $bind_values ) )
            {
                $_command->bindValues( $bind_values );
            }

            $_count = intval( $_command->queryScalar() );

            if ( $_includeCount || $_count > $_maxAllowed )
            {
                $_meta['count'] = $_count;
            }
            if ( ( $_count - $_offset ) > $_limit )
            {
                $_meta['next'] = $_offset + $_limit + 1;
            }
        }

        if ( Option::getBool( $extras, 'include_schema', false ) )
        {
            $_meta['schema'] = SqlDbUtilities::describeTable( $this->_dbConn, $from );
        }

        $_related = Option::get( $extras, 'related' );
        if ( !empty( $_related ) )
        {
            $_relations = $this->describeTableRelated( $from );
            foreach ( $_data as $_key => $_temp )
            {
                $_data[$_key] = $this->retrieveRelatedRecords( $_temp, $_relations, $_related );
            }
        }

        if ( !empty( $_meta ) )
        {
            $_data['meta'] = $_meta;
        }

        return $_data;
    }

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

        $fields = SqlDbUtilities::describeTableFields( $this->_dbConn, $name );
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

        $relations = SqlDbUtilities::describeTableRelated( $this->_dbConn, $name );
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
     * Take in a ANSI SQL filter string (WHERE clause)
     * or our generic NoSQL filter array or partial record
     * and parse it to the service's native filter criteria.
     * The filter string can have substitution parameters such as '?',
     * in which case a numeric array is expected in $params, or
     * ':name', in which case an associative array is expected,
     * for value substitution. The two types can not be mixed.
     *
     * @param string | array $filter     SQL WHERE clause filter string
     * @param array          $params     Array of substitution values
     * @param array          $ss_filters Server-side filters to apply
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return mixed
     */
    protected function _convertFilterToNative( $filter, $params = array(), $ss_filters = array() )
    {
        if ( !is_array( $filter ) )
        {
            // todo parse client filter?
            $_filterString = $filter;
            // search filter for index substitution
            $_indexSub = ( false != strpos( $filter, '?' ) );
            $_serverFilter = $this->buildQueryStringFromData( $ss_filters, true, $_indexSub );
            if ( !empty( $_serverFilter ) )
            {
                if ( empty( $filter ) )
                {
                    $_filterString = $_serverFilter['filter'];
                }
                else
                {
                    $_filterString = '(' . $_filterString . ') AND (' . $_serverFilter['filter'] . ')';
                }
                $params = array_merge( $params, $_serverFilter['params'] );
            }

            return array( 'where' => $_filterString, 'params' => $params );
        }
        else
        {
            // todo parse client filter?
            $_filterArray = $filter;
            // implode filter into string and search for index substitution
            $_indexSub = ( false != strpos(
                    implode(
                        array_map(
                            function ( $a )
                            {
                                if ( is_array( $a ) )
                                {
                                    return implode( $a, ' ' );
                                }

                                return $a;
                            },
                            $filter
                        ),
                        ' '
                    ),
                    '?'
                ) );
            $_serverFilter = $this->buildQueryStringFromData( $ss_filters, true, $_indexSub );
            if ( !empty( $_serverFilter ) )
            {
                if ( empty( $filter ) )
                {
                    $_filterArray = $_serverFilter['filter'];
                }
                else
                {
                    $_filterArray = array( 'AND', $_filterArray, $_serverFilter['filter'] );
                }
                $params = array_merge( $params, $_serverFilter['params'] );
            }

            return array( 'where' => $_filterArray, 'params' => $params );
        }
    }

    /**
     * @param array $record
     * @param array $avail_fields
     * @param array $filter_info
     * @param bool  $for_update
     * @param array $old_record
     *
     * @return array
     * @throws \Exception
     */
    protected function parseRecord( $record, $avail_fields, $filter_info = null, $for_update = false, $old_record = null )
    {
        $parsed = array();
//        $record = DataFormat::arrayKeyLower( $record );
        $keys = array_keys( $record );
        $values = array_values( $record );
        foreach ( $avail_fields as $field_info )
        {
//            $name = strtolower( Option::get( $field_info, 'name', '' ) );
            $name = Option::get( $field_info, 'name', '' );
            $type = Option::get( $field_info, 'type' );
            $dbType = Option::get( $field_info, 'db_type' );
            $pos = array_search( $name, $keys );
            if ( false !== $pos )
            {
                $fieldVal = Option::get( $values, $pos );
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

                /** validations **/
                $validations = array_map( 'trim', explode( ',', Option::get( $field_info, 'validation', '' ) ) );

                if ( false !== $valPos = array_search( 'api_read_only', $validations, true ) )
                {
                    unset( $keys[$pos] );
                    unset( $values[$pos] );
                    continue; // should I error this?
                }
                if ( false !== $valPos = array_search( 'create_only', $validations, true ) )
                {
                    unset( $keys[$pos] );
                    unset( $values[$pos] );
                    continue; // should I error this?
                }
                if ( is_null( $fieldVal ) )
                {
                    if ( !Option::getBool( $field_info, 'allow_null' ) )
                    {
                        throw new BadRequestException( "Field '$name' can not be NULL." );
                    }
                    if ( false !== $valPos = array_search( 'not_empty', $validations, true ) && empty( $fieldVal ) )
                    {
                        throw new BadRequestException( "Field '$name' can not be empty." );
                    }
                }
                else
                {
                    if ( false !== $valPos = array_search( 'not_empty', $validations, true ) && empty( $fieldVal ) )
                    {
                        throw new BadRequestException( "Field '$name' can not be empty." );
                    }

                    switch ( $type )
                    {
                        case 'string':
                            if ( false !== $valPos = array_search( 'email', $validations, true ) && !filter_var( $fieldVal, FILTER_VALIDATE_EMAIL ) )
                            {
                                throw new BadRequestException( "Field '$name' must be a valid email." );
                            }
                            if ( false !== $valPos = array_search( 'url', $validations, true ) )
                            {
                                $_filter = trim( stristr( $validations[$valPos], '(' ), '()' );
                                $_options = null;
//                                    FILTER_FLAG_HOST_REQUIRED
                                if ( !filter_var( $fieldVal, FILTER_VALIDATE_URL, $_options ) )
                                {
                                    throw new BadRequestException( "Field '$name' must be a valid url." );
                                }
                            }
                            if ( false !== $valPos = array_search( 'match', $validations, true ) )
                            {
                                $b =
                                    "^(([^<>()[].,;:s@\"]+(.[^<>()[].,;:s@\"]+)*)|(\".+\"))@(([[0-9]{1,3}.[0-9]{1,3}‌​.[0-9]{1,3}.[0-9]{1,3}])|(([a-zA-Z-0-9]+.)+[a-zA-Z]{2,}))$";
                                $_filter = base64_decode( trim( stristr( $validations[$valPos], '(' ), '()' ) );
                                $_options = array( 'regexp' => $_filter );
//                                regexp
                                if ( !filter_var( $fieldVal, FILTER_VALIDATE_REGEXP, $_options ) )
                                {
                                    throw new BadRequestException( "Field '$name' must be a valid url." );
                                }
                            }
                            break;
                        case 'integer':
                            if ( false !== $valPos = array_search( 'range', $validations, true ) )
                            {
                                $_filter = trim( stristr( $validations[$valPos], '(' ), '()' );
                                $_options = null;
//                                min_range, max_range
                                if ( !filter_var( $fieldVal, FILTER_VALIDATE_INT, $_options ) )
                                {
                                    throw new BadRequestException( "Field '$name' must be a valid url." );
                                }
                            }
                            break;
                        case 'decimal':
                        case 'float':
                            break;
                    }

                    switch ( $this->_driverType )
                    {
                        case SqlDbUtilities::DRV_DBLIB:
                        case SqlDbUtilities::DRV_SQLSRV:
                            switch ( $dbType )
                            {
                                case 'bit':
                                    $fieldVal = ( Scalar::boolval( $fieldVal ) ? 1 : 0 );
                                    break;
                            }
                            break;
                        case SqlDbUtilities::DRV_MYSQL:
                            switch ( $dbType )
                            {
                                case 'tinyint(1)':
                                    $fieldVal = ( Scalar::boolval( $fieldVal ) ? 1 : 0 );
                                    break;
                            }
                            break;
                    }
                    switch ( SqlDbUtilities::determinePhpConversionType( $type, $dbType ) )
                    {
                        case 'int':
                            if ( !is_int( $fieldVal ) )
                            {
                                if ( ( '' === $fieldVal ) && Option::getBool( $field_info, 'allow_null' ) )
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
                        if ( Option::getBool( $field_info, 'required' ) && !$for_update )
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
                            case SqlDbUtilities::DRV_DBLIB:
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
                        case SqlDbUtilities::DRV_DBLIB:
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

        if ( !empty( $filter_info ) )
        {
            $this->validateRecord( $record, $filter_info, $for_update, $old_record );
        }

        return $parsed;
    }

    /**
     * @param string $table
     * @param array  $record Record containing relationships by name if any
     * @param array  $id     Array of id field and value, only one supported currently
     * @param array  $avail_relations
     * @param bool   $allow_delete
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return void
     */
    protected function updateRelations( $table, $record, $id, $avail_relations, $allow_delete = false )
    {
        // update currently only supports one id field
        $id = @current( reset( $id ) );
        $keys = array_keys( $record );
        $values = array_values( $record );
        foreach ( $avail_relations as $relationInfo )
        {
            $name = Option::get( $relationInfo, 'name' );
            $pos = array_search( $name, $keys );
            if ( false !== $pos )
            {
                $relations = Option::get( $values, $pos );
                $relationType = Option::get( $relationInfo, 'type' );
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
                        $relatedTable = Option::get( $relationInfo, 'ref_table' );
                        $relatedField = Option::get( $relationInfo, 'ref_field' );
                        $this->assignManyToOne(
                            $table,
                            $id,
                            $relatedTable,
                            $relatedField,
                            $relations,
                            $allow_delete
                        );
                        break;
                    case 'many_many':
                        /*
                    "name": "roles_by_user",
                    "type": "many_many",
                    "ref_table": "role",
                    "ref_field": "id",
                    "join": "user(default_app_id,role_id)"
                    */
                        $relatedTable = Option::get( $relationInfo, 'ref_table' );
                        $join = Option::get( $relationInfo, 'join', '' );
                        $joinTable = substr( $join, 0, strpos( $join, '(' ) );
                        $other = explode( ',', substr( $join, strpos( $join, '(' ) + 1, -1 ) );
                        $joinLeftField = trim( Option::get( $other, 0, '' ) );
                        $joinRightField = trim( Option::get( $other, 1, '' ) );
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
        foreach ( $record as $value )
        {
            $fieldVal = ( is_null( $value ) ) ? "NULL" : $this->_dbConn->quoteValue( $value );
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
            $fieldVal = ( is_null( $value ) ) ? "NULL" : $this->_dbConn->quoteValue( $value );
            $out .= ( !empty( $out ) ) ? ',' : '';
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

        $field_arr = ( !is_array( $fields ) ) ? array_map( 'trim', explode( ',', $fields ) ) : $fields;
        $as_arr = ( !is_array( $fields_as ) ) ? array_map( 'trim', explode( ',', $fields_as ) ) : $fields_as;
        if ( !$as_quoted_string )
        {
            // yii will not quote anything if any of the fields are expressions
        }
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
                $context = $this->_dbConn->quoteColumnName( $context );
                $out_as = $this->_dbConn->quoteColumnName( $out_as );
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
                        case SqlDbUtilities::DRV_DBLIB:
                        case SqlDbUtilities::DRV_SQLSRV:
                            if ( !$as_quoted_string )
                            {
                                $context = $this->_dbConn->quoteColumnName( $context );
                                $out_as = $this->_dbConn->quoteColumnName( $out_as );
                            }
                            $out = "(CONVERT(nvarchar(30), $context, 127)) AS $out_as";
                            break;
                        default:
                            $out = $context;
                            break;
                    }
                    break;
                case 'geometry':
                case 'geography':
                case 'hierarchyid':
                    switch ( $this->_driverType )
                    {
                        case SqlDbUtilities::DRV_DBLIB:
                        case SqlDbUtilities::DRV_SQLSRV:
                            if ( !$as_quoted_string )
                            {
                                $context = $this->_dbConn->quoteColumnName( $context );
                                $out_as = $this->_dbConn->quoteColumnName( $out_as );
                            }
                            $out = "($context.ToString()) AS $out_as";
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
            $out_str .= $prefix . '.' . $this->_dbConn->quoteColumnName( $field );
        }

        return $out_str;
    }

    // generic assignments

    /**
     * @param $relations
     * @param $data
     * @param $requests
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    protected function retrieveRelatedRecords( $data, $relations, $requests )
    {
        if ( empty( $relations ) || empty( $requests ) )
        {
            return $data;
        }

        $_relatedData = array();
        $_relatedExtras = array( 'limit' => static::DB_MAX_RECORDS_RETURNED, 'fields' => '*' );
        if ( '*' == $requests )
        {
            foreach ( $relations as $_name => $_relation )
            {
                if ( empty( $_relation ) )
                {
                    throw new BadRequestException( "Empty relationship '$_name' found." );
                }
                $_relatedData[$_name] = $this->retrieveRelationRecords( $data, $_relation, $_relatedExtras );
            }
        }
        else
        {
            foreach ( $requests as $_request )
            {
                $_name = Option::get( $_request, 'name' );
                $_relation = Option::get( $relations, $_name );
                if ( empty( $_relation ) )
                {
                    throw new BadRequestException( "Invalid relationship '$_name' requested." );
                }

                $_relatedExtras['fields'] = Option::get( $_request, 'fields' );
                $_relatedData[$_name] = $this->retrieveRelationRecords( $data, $_relation, $_relatedExtras );
            }
        }

        return array_merge( $data, $_relatedData );
    }

    protected function retrieveRelationRecords( $data, $relation, $extras )
    {
        if ( empty( $relation ) )
        {
            return null;
        }

        $relationType = Option::get( $relation, 'type' );
        $relatedTable = Option::get( $relation, 'ref_table' );
        $relatedField = Option::get( $relation, 'ref_field' );
        $field = Option::get( $relation, 'field' );
        $fieldVal = Option::get( $data, $field );

        // do we have permission to do so?
        $this->validateTableAccess( $relatedTable, static::GET );

        switch ( $relationType )
        {
            case 'belongs_to':
                $relatedRecords = $this->retrieveRecordsByFilter( $relatedTable, "$relatedField = '$fieldVal'", $extras );
                if ( !empty( $relatedRecords ) )
                {
                    return Option::get( $relatedRecords, 0 );
                }
                break;
            case 'has_many':
                return $this->retrieveRecordsByFilter( $relatedTable, "$relatedField = '$fieldVal'", $extras );
                break;
            case 'many_many':
                $join = Option::get( $relation, 'join', '' );
                $joinTable = substr( $join, 0, strpos( $join, '(' ) );
                $other = explode( ',', substr( $join, strpos( $join, '(' ) + 1, -1 ) );
                $joinLeftField = trim( Option::get( $other, 0 ) );
                $joinRightField = trim( Option::get( $other, 1 ) );
                if ( !empty( $joinLeftField ) && !empty( $joinRightField ) )
                {
                    $joinExtras = array( 'fields' => $joinRightField );
                    $joinData = $this->retrieveRecordsByFilter( $joinTable, "$joinLeftField = '$fieldVal'", $joinExtras );
                    if ( !empty( $joinData ) )
                    {
                        $relatedIds = array();
                        foreach ( $joinData as $record )
                        {
                            $relatedIds[] = Option::get( $record, $joinRightField );
                        }
                        if ( !empty( $relatedIds ) )
                        {
                            $relatedIds = implode( ',', $relatedIds );
                            $relatedExtras['id_field'] = $relatedField;

                            return $this->retrieveRecordsByIds( $relatedTable, $relatedIds, $extras );
                        }
                    }
                }
                break;
            default:
                throw new InternalServerErrorException( 'Invalid relationship type detected.' );
                break;
        }

        return null;
    }

    /**
     * @param string $one_table
     * @param string $one_id
     * @param string $many_table
     * @param string $many_field
     * @param array  $many_records
     * @param bool   $allow_delete
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return void
     */
    protected function assignManyToOne( $one_table, $one_id, $many_table, $many_field, $many_records = array(), $allow_delete = false )
    {
        if ( empty( $one_id ) )
        {
            throw new BadRequestException( "The $one_table id can not be empty." );
        }

        try
        {
            $manyFields = $this->describeTableFields( $many_table );
            $pkField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $manyFields );
            $fieldInfo = SqlDbUtilities::getFieldFromDescribe( $many_field, $manyFields );
            $deleteRelated = ( !Option::getBool( $fieldInfo, 'allow_null' ) && $allow_delete );
            $relateMany = array();
            $disownMany = array();
            $createMany = array();
            $updateMany = array();
            $deleteMany = array();

            foreach ( $many_records as $item )
            {
                $id = Option::get( $item, $pkField );
                if ( empty( $id ) )
                {
                    // create new child record
                    $item[$many_field] = $one_id; // assign relationship
                    $createMany[] = $item;
                }
                else
                {
                    if ( array_key_exists( $many_field, $item ) )
                    {
                        if ( null == Option::get( $item, $many_field, null, true ) )
                        {
                            // disown this child or delete them
                            if ( $deleteRelated )
                            {
                                $deleteMany[] = $id;
                            }
                            elseif ( count( $item ) > 1 )
                            {
                                $item[$many_field] = null; // assign relationship
                                $updateMany[] = $item;
                            }
                            else
                            {
                                $disownMany[] = $id;
                            }

                            continue;
                        }
                    }

                    // update this child
                    if ( count( $item ) > 1 )
                    {
                        $item[$many_field] = $one_id; // assign relationship
                        $updateMany[] = $item;
                    }
                    else
                    {
                        $relateMany[] = $id;
                    }
                }
            }

            if ( !empty( $createMany ) )
            {
                // create new children
                // do we have permission to do so?
                $this->validateTableAccess( $many_table, static::POST );
                $this->createRecords( $many_table, $createMany );
            }

            if ( !empty( $deleteMany ) )
            {
                // destroy linked children that can't stand alone - sounds sinister
                // do we have permission to do so?
                $this->validateTableAccess( $many_table, static::DELETE );
                $this->deleteRecordsByIds( $many_table, $deleteMany );
            }

            if ( !empty( $updateMany ) || !empty( $relateMany ) || !empty( $disownMany ) )
            {
                // do we have permission to do so?
                $this->validateTableAccess( $many_table, static::PUT );

                if ( !empty( $updateMany ) )
                {
                    // update existing and adopt new children
                    $this->updateRecords( $many_table, $updateMany );
                }

                if ( !empty( $relateMany ) )
                {
                    // adopt/relate/link unlinked children
                    $this->updateRecordsByIds( $many_table, array( $many_field => $one_id ), $relateMany );
                }

                if ( !empty( $disownMany ) )
                {
                    // disown/un-relate/unlink linked children
                    $this->updateRecordsByIds( $many_table, array( $many_field => null ), $disownMany );
                }
            }
        }
        catch ( \Exception $_ex )
        {
            throw new BadRequestException( "Failed to update many to one assignment.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * @param string $one_table
     * @param mixed  $one_id
     * @param string $many_table
     * @param string $map_table
     * @param string $one_field
     * @param string $many_field
     * @param array  $many_records
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
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
            $oneFields = $this->describeTableFields( $one_table );
            $pkOneField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $oneFields );
            $manyFields = $this->describeTableFields( $many_table );
            $pkManyField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $manyFields );
//			$mapFields = $this->describeTableFields( $map_table );
//			$pkMapField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $mapFields );
            $relatedExtras = array( 'fields' => $many_field, 'limit' => static::DB_MAX_RECORDS_RETURNED );
            $maps = $this->retrieveRecordsByFilter( $map_table, "$one_field = ?", array( $one_id ), $relatedExtras );
            $createMap = array(); // map records to create
            $deleteMap = array(); // ids of 'many' records to delete from maps
            $createMany = array();
            $updateMany = array();
            foreach ( $many_records as $item )
            {
                $id = Option::get( $item, $pkManyField );
                if ( empty( $id ) )
                {
                    // create new many record, relationship created later
                    $createMany[] = $item;
                }
                else
                {
                    // pk fields exists, must be dealing with existing 'many' record
                    $oneLookup = "$one_table.$pkOneField";
                    if ( array_key_exists( $oneLookup, $item ) )
                    {
                        if ( null == Option::get( $item, $oneLookup, null, true ) )
                        {
                            // delete this relationship
                            $deleteMap[] = $id;
                            continue;
                        }
                    }

                    // update the 'many' record if more than the above fields
                    if ( count( $item ) > 1 )
                    {
                        $updateMany[] = $item;
                    }

                    // if relationship doesn't exist, create it
                    foreach ( $maps as $map )
                    {
                        if ( Option::get( $map, $many_field ) == $id )
                        {
                            continue 2; // got what we need from this one
                        }
                    }

                    $createMap[] = array( $many_field => $id, $one_field => $one_id );
                }
            }

            if ( !empty( $createMany ) )
            {
                // do we have permission to do so?
                $this->validateTableAccess( $many_table, static::POST );
                // create new many records
                $results = $this->createRecords( $many_table, $createMany );
                // create new relationships for results
                foreach ( $results as $item )
                {
                    $itemId = Option::get( $item, $pkManyField );
                    if ( !empty( $itemId ) )
                    {
                        $createMap[] = array( $many_field => $itemId, $one_field => $one_id );
                    }
                }
            }

            if ( !empty( $updateMany ) )
            {
                // update existing many records
                // do we have permission to do so?
                $this->validateTableAccess( $many_table, static::PUT );
                $this->updateRecords( $many_table, $updateMany );
            }

            if ( !empty( $createMap ) )
            {
                // do we have permission to do so?
                $this->validateTableAccess( $map_table, static::POST );
                $this->createRecords( $map_table, $createMap );
            }

            if ( !empty( $deleteMap ) )
            {
                // do we have permission to do so?
                $this->validateTableAccess( $map_table, static::DELETE );
                $mapList = "'" . implode( "','", $deleteMap ) . "'";
                $filter = "$one_field = '$one_id' && $many_field IN ($mapList)";
                $this->deleteRecordsByFilter( $map_table, $filter );
            }
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to update many to one map assignment.\n{$_ex->getMessage()}" );
        }
    }

    protected function buildQueryStringFromData( $filter_info, $use_params = true, $index_substitution = false )
    {
        $_filters = Option::get( $filter_info, 'filters' );
        if ( empty( $_filters ) )
        {
            return null;
        }

        $_sql = '';
        $_params = array();
        $_combiner = Option::get( $filter_info, 'filter_op', 'and' );
        foreach ( $_filters as $_filter )
        {
            if ( !empty( $_sql ) )
            {
                $_sql .= " $_combiner ";
            }

            $_name = Option::get( $_filter, 'name' );
            $_op = Option::get( $_filter, 'operator' );
            if ( empty( $_name ) || empty( $_op ) )
            {
                // log and bail
                throw new InternalServerErrorException( 'Invalid server-side filter configuration detected.' );
            }

            $_value = Option::get( $_filter, 'value' );
            $_value = static::interpretFilterValue( $_value );
            if ( $use_params )
            {
                $_paramName = ( $index_substitution ) ? '?' : ':ssf_' . $_name;
                $_params[$_paramName] = $_value;
                $_value = $_paramName;
            }
            else
            {
                if ( is_bool( $_value ) )
                {
                    $_value = $_value ? 'true' : 'false';
                }

                $_value = ( is_null( $_value ) ) ? 'NULL' : $this->_dbConn->quoteValue( $_value );
            }

            $_sql .= "$_name $_op $_value";
        }

        return array( 'filter' => $_sql, 'params' => $_params );
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
            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand( $query );
            $reader = $command->query();
            $dummy = array();
            foreach ( $bindings as $binding )
            {
                $_name = Option::get( $binding, 'name' );
                $_type = Option::get( $binding, 'pdo_type' );
                $reader->bindColumn( $_name, $dummy[$_name], $_type );
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

            return $data;
        }
        catch ( \Exception $_ex )
        {
            error_log( 'batchquery: ' . $_ex->getMessage() . PHP_EOL . $query );

            throw $_ex;
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
            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand( $query );
            if ( isset( $params ) && !empty( $params ) )
            {
                $data = $command->queryAll( true, $params );
            }
            else
            {
                $data = $command->queryAll();
            }

            return $data;
        }
        catch ( \Exception $_ex )
        {
            error_log( 'singlequery: ' . $_ex->getMessage() . PHP_EOL . $query . PHP_EOL . print_r( $params, true ) );

            throw $_ex;
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
            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand( $query );
            if ( isset( $params ) && !empty( $params ) )
            {
                $data = $command->execute( $params );
            }
            else
            {
                $data = $command->execute();
            }

            return $data;
        }
        catch ( \Exception $_ex )
        {
            error_log( 'singleexecute: ' . $_ex->getMessage() . PHP_EOL . $query . PHP_EOL . print_r( $params, true ) );

            throw $_ex;
        }
    }

    /**
     * @return int
     */
    public function getDriverType()
    {
        return $this->_driverType;
    }

    protected static function _getIdFieldsInfo( &$fields, $all_field_info )
    {
        $_idFieldsInfo = array();
        if ( empty( $fields ) )
        {
            $fields = array();
            $_idFieldsInfo = SqlDbUtilities::getPrimaryKeys( $all_field_info );
            foreach ( $_idFieldsInfo as $_info )
            {
                $fields[] = Option::get( $_info, 'name' );
            }
        }
        else
        {
            if ( false !== $fields = static::checkIncomingData( $fields, ',' ) )
            {
                foreach ( $fields as $_field )
                {
                    $_idFieldsInfo[] = SqlDbUtilities::getFieldFromDescribe( $_field, $all_field_info );
                }
            }
        }

        return $_idFieldsInfo;
    }
}
