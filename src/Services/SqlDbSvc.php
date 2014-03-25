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

use DreamFactory\Common\Exceptions\RestException;
use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
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
        if ( !isset( $this->_dbConn ) )
        {
            throw new InternalServerErrorException( 'Database driver has not been initialized.' );
        }

        try
        {
            $this->_dbConn->setActive( true );
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
                try
                {
                    if ( null != $_name = Option::get( $_table, 'name' ) )
                    {
                        $this->validateTableAccess( $_name );
                        $_resources[] = $_table;
                    }
                }
                catch ( \Exception $_ex )
                {
                    // do not include in listing
                }
            }

            return array( 'resource' => $_resources );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error describing database tables.\n{$ex->getMessage()}" );
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
            return SqlDbUtilities::describeTable( $this->_dbConn, $_name );
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Error describing database table '$_name'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTables( $tables = array() )
    {
        throw new BadRequestException( 'Editing database schema is only allowed through a SQL DB Schema service.' );
    }

    /**
     * {@inheritdoc}
     */
    public function createTable( $properties = array() )
    {
        throw new BadRequestException( 'Editing database schema is only allowed through a SQL DB Schema service.' );
    }

    /**
     * {@inheritdoc}
     */
    public function updateTables( $tables = array() )
    {
        throw new BadRequestException( 'Editing database schema is only allowed through a SQL DB Schema service.' );
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable( $properties = array() )
    {
        throw new BadRequestException( 'Creating database schema is only allowed through a SQL DB Schema service.' );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTables( $tables = array(), $check_empty = false )
    {
        throw new BadRequestException( 'Editing database schema is only allowed through a SQL DB Schema service.' );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTable( $table, $check_empty = false )
    {
        throw new BadRequestException( 'Editing database schema is only allowed through a SQL DB Schema service.' );
    }

    //-------- Table Records Operations ---------------------
    // records is an array of field arrays

    /**
     * {@inheritdoc}
     */
    public function createRecords( $table, $records, $extras = array() )
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
        $_allowRelatedDelete = Option::getBool( $extras, 'allow_related_delete', false );
        $_idFields = Option::get( $extras, 'id_field' );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
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
                    $_idFields[] = Option::get( $_temp, 'name' );
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
            $command = $this->_dbConn->createCommand();
            $_ids = array();
            $_errors = array();
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

                    $_id = null;
                    if ( !empty( $_idFieldsInfo ) )
                    {
                        foreach ( $_idFieldsInfo as $_info )
                        {
                            // todo support multi-field keys
                            if ( Option::getBool( $_info, 'auto_increment' ) )
                            {
                                $_id = (int)$this->_dbConn->lastInsertID;
                            }
                            else
                            {
                                // must have been passed in with request
                                $_id = Option::get( $_record, Option::get( $_info, 'name' ) );
                            }
                        }

                        if ( !empty( $_relatedInfo ) )
                        {
                            $this->updateRelations( $table, $_record, $_id, $_relatedInfo, $_allowRelatedDelete );
                        }
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
            if ( !static::_requireMoreFields( $_fields, $_idFields ) )
            {
                $_temp = array();
                foreach ( $_ids as $_id )
                {
                    foreach ( $_idFields as $_field )
                    {
                        $_temp[$_field] = $_id;
                    }
                }
                $_results[] = $_temp;
            }
            else
            {
                $_results = $this->retrieveRecordsByIds( $table, $_ids, $extras );
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
    public function updateRecords( $table, $records, $extras = array() )
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
        $_allowRelatedDelete = Option::getBool( $extras, 'allow_related_delete', false );
        $_idField = Option::get( $extras, 'id_field' );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
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
            $command = $this->_dbConn->createCommand();
            $_ids = array();
            $_errors = array();
            $_transaction = null;

            if ( $_rollback && !$_isSingle )
            {
                $_transaction = $this->_dbConn->beginTransaction();
            }

            foreach ( $records as $_key => $_record )
            {
                try
                {
                    $_id = Option::get( $_record, $_idField, null, true );
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '$_idField' can not be empty for update record [$_key] request." );
                    }

                    $_parsed = $this->parseRecord( $_record, $_fieldInfo, $_ssFilters, true );
                    if ( !empty( $_parsed ) )
                    {
                        // simple update request
                        $command->reset();
                        /*$rows = */
                        $command->update( $table, $_parsed, array( 'in', $_idField, $_id ) );
                    }

                    $_ids[$_key] = $_id;

                    if ( !empty( $_relatedInfo ) )
                    {
                        $this->updateRelations( $table, $_record, $_id, $_relatedInfo, $_allowRelatedDelete );
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
            if ( !static::_requireMoreFields( $_fields, $_idField ) )
            {
                foreach ( $_ids as $_id )
                {
                    $_results[] = array( $_idField => $_id );
                }
            }
            else
            {
                $_results = $this->retrieveRecordsByIds( $table, $_ids, $extras );
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
    public function updateRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        if ( !is_array( $record ) || empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record.' );
        }

        $table = $this->correctTableName( $table );

        try
        {
            $_fields = Option::get( $extras, 'fields' );
            $_ssFilters = Option::get( $extras, 'ss_filters' );
            $fieldInfo = $this->describeTableFields( $table );
//			$relatedInfo = $this->describeTableRelated( $table );
            // simple update request
            $parsed = $this->parseRecord( $record, $fieldInfo, $_ssFilters, true );
            if ( empty( $parsed ) )
            {
                throw new BadRequestException( "No valid field values were passed in the request." );
            }

            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand();
            $command->update( $table, $parsed, $filter );
            // todo how to update relations here?

            $results = array();
            if ( !empty( $_fields ) )
            {
                $results = $this->retrieveRecordsByFilter( $table, $filter, $params );
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
    public function updateRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        if ( !is_array( $record ) || empty( $record ) )
        {
            throw new BadRequestException( "No record fields were passed in the request." );
        }

        $table = $this->correctTableName( $table );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_allowRelatedDelete = Option::getBool( $extras, 'allow_related_delete', false );
        $_idField = Option::get( $extras, 'id_field' );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        if ( !is_array( $ids ) )
        {
            $ids = array_map( 'trim', explode( ',', trim( $ids, ',' ) ) );
        }
        if ( empty( $ids ) )
        {
            throw new BadRequestException( "Identifying values for '$_idField' can not be empty for update request." );
        }

        $_isSingle = ( 1 == count( $ids ) );
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

            $record = DataFormat::removeOneFromArray( $_idField, $record );
            // simple update request
            $_parsed = $this->parseRecord( $record, $_fieldInfo, $_ssFilters, true );
            $_filter = "$_idField = :$_idField";
            $_params = array();
            $_criteria = $this->_convertFilterStringToNative( $_filter, $_params, $_ssFilters );

            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );

            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand();
            $_errors = array();
            $_transaction = null;

            if ( $_rollback && !$_isSingle )
            {
                $_transaction = $this->_dbConn->beginTransaction();
            }

            foreach ( $ids as $_key => $_id )
            {
                try
                {
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '$_idField' can not be empty for update record request." );
                    }

                    if ( !empty( $_parsed ) )
                    {
                        $_params[":$_idField"] = $_id;
                        // simple update request
                        $command->reset();
                        /*$rows = */
                        $command->update( $table, $_parsed, $_where, $_params );
                    }

                    if ( !empty( $_relatedInfo ) )
                    {
                        $this->updateRelations( $table, $record, $_id, $_relatedInfo, $_allowRelatedDelete );
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
            if ( !static::_requireMoreFields( $_fields, $_idField ) )
            {
                foreach ( $ids as $_id )
                {
                    $_results[] = array( $_idField => $_id );
                }
            }
            else
            {
                $_results = $this->retrieveRecordsByIds( $table, $ids, $extras );
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
    public function truncateTable( $table )
    {
        // truncate the table, return success
        $table = $this->correctTableName( $table );
        try
        {
            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand();
            $results = array();
            /*$rows = */
            $command->truncateTable( $table );

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
    public function deleteRecords( $table, $records, $extras = array() )
    {
        if ( !is_array( $records ) || empty( $records ) )
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

        return $this->deleteRecordsByIds( $table, $_ids, $extras );
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
            $results = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );

            // build filter string if necessary, add server-side filters if necessary
            $_ssFilters = Option::get( $extras, 'ss_filters' );
            if ( is_array( $filter ) )
            {
                $_criteria = $this->_convertFilterArrayToNative( $filter, $params, $_ssFilters );
            }
            else
            {
                $_criteria = $this->_convertFilterStringToNative( $filter, $params, $_ssFilters );
            }

            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );

            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand();
            $command->delete( $table, $_where, $_params );

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
    public function deleteRecordsByIds( $table, $ids, $extras = array() )
    {
        $table = $this->correctTableName( $table );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_idField = Option::get( $extras, 'id_field' );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
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

            // get the returnable fields first, then issue delete
            $_outResults = array();
            if ( static::_requireMoreFields( $_fields, $_idField ) )
            {
                $_outResults = $this->retrieveRecordsByIds( $table, $ids, $extras );
            }

            $_transaction = null;
            if ( $_rollback && !$_isSingle )
            {
                $_transaction = $this->_dbConn->beginTransaction();
            }

            // build filter string if necessary, add server-side filters if necessary
            $_filter = "$_idField = :$_idField";
            $_params = array();
            $_criteria = $this->_convertFilterStringToNative( $_filter, $_params, $_ssFilters );

            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );

            /** @var \CDbCommand $command */
            $command = $this->_dbConn->createCommand();

            $_errors = array();
            foreach ( $ids as $_key => $_id )
            {
                try
                {
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '$_idField' can not be empty for delete record request." );
                    }

                    // simple delete request
                    $_params[":$_idField"] = $_id;
                    $rows = $command->delete( $table, $_where, $_params );
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
            if ( !static::_requireMoreFields( $_fields, $_idField ) )
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
    public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() )
    {
        $table = $this->correctTableName( $table );
        try
        {
            $_availFields = $this->describeTableFields( $table );
            $_related = Option::get( $extras, 'related' );
            $_relations = ( empty( $_related ) ) ? array() : $this->describeTableRelated( $table );

            $_fields = Option::get( $extras, 'fields' );
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_availFields );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );
            $_fields = ( empty( $_fields ) ) ? '*' : $_fields;

            $_order = Option::get( $extras, 'order' );
            $_limit = intval( Option::get( $extras, 'limit', 0 ) );
            $_offset = intval( Option::get( $extras, 'offset', 0 ) );
            $_maxAllowed = static::getMaxRecordsReturnedLimit();
            $_needLimit = false;

            // build filter string if necessary, add server-side filters if necessary
            $_ssFilters = Option::get( $extras, 'ss_filters' );
            if ( is_array( $filter ) )
            {
                $_criteria = $this->_convertFilterArrayToNative( $filter, $params, $_ssFilters );
            }
            else
            {
                $_criteria = $this->_convertFilterStringToNative( $filter, $params, $_ssFilters );
            }

            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );
            if ( isset( $_params[0] ) )
            {
                // using PDO ? prepare statements, requires 1-based array...yeah crazy, I know!
                $_params = static::one_index_array( $_params );
            }

            // use query builder
            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();
            $_command->select( $_fields );
            $_command->from( $table );

            if ( !empty( $_where ) )
            {
                $_command->where( $_where );
            }
            if ( !empty( $_params ) )
            {
                $_command->bindValues( $_params );
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
            foreach ( $_bindings as $_binding )
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
                foreach ( $_bindings as $_binding )
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

            if ( !empty( $_relations ) )
            {
                foreach ( $_data as $_key => $_temp )
                {
                    $_data[$_key] = $this->retrieveRelatedRecords( $_temp, $_relations, $_related );
                }
            }

            $_includeCount = Option::getBool( $extras, 'include_count', false );
            // count total records
            if ( $_includeCount || $_needLimit )
            {
                $_command->reset();
                $_command->select( '(COUNT(*)) as ' . $this->_dbConn->quoteColumnName( 'count' ) );
                $_command->from( $table );
                if ( !empty( $_where ) )
                {
                    $_command->where( $_where );
                }
                if ( !empty( $_params ) )
                {
                    $_command->bindValues( $_params );
                }

                $_count = intval( $_command->queryScalar() );

                if ( $_includeCount || $_count > $_maxAllowed )
                {
                    $_data['meta']['count'] = $_count;
                }
                if ( ( $_count - $_offset ) > $_limit )
                {
                    $_data['meta']['next'] = $_offset + $_limit + 1;
                }
            }

            if ( Option::getBool( $extras, 'include_schema', false ) )
            {
                $_data['meta']['schema'] = SqlDbUtilities::describeTable( $this->_dbConn, $table );
            }

            return $_data;
        }
        catch ( \Exception $_ex )
        {
            throw $_ex;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecords( $table, $records, $extras = array() )
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

        return $this->retrieveRecordsByIds( $table, $idList, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByIds( $table, $ids, $extras = array() )
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
        try
        {
            $_availFields = $this->describeTableFields( $table );
            $_relations = ( empty( $this->_related ) ? array() : $this->describeTableRelated( $table ) );

            $_idField = Option::get( $extras, 'id_field' );
            if ( empty( $_idField ) )
            {
                $_idField = SqlDbUtilities::getPrimaryKeyFieldFromDescribe( $_availFields );
                if ( empty( $_idField ) )
                {
                    throw new BadRequestException( "Identifying field can not be empty." );
                }
            }

            $_fields = Option::get( $extras, 'fields' );
            if ( !empty( $_fields ) && ( '*' !== $_fields ) )
            {
                // add id field to field list
                $_fields = DataFormat::addOnceToList( $_fields, $_idField, ',' );
            }
            $_result = $this->parseFieldsForSqlSelect( $_fields, $_availFields );
            $_bindings = Option::get( $_result, 'bindings' );
            $_fields = Option::get( $_result, 'fields' );

            $_filter = array( 'in', $_idField, $ids );
            // build filter string if necessary, add server-side filters if necessary
            $_ssFilters = Option::get( $extras, 'ss_filters' );
            $_criteria = $this->_convertFilterArrayToNative( $_filter, array(), $_ssFilters );
            $_where = Option::get( $_criteria, 'where' );
            $_params = Option::get( $_criteria, 'params', array() );

            // use query builder
            /** @var \CDbCommand $_command */
            $_command = $this->_dbConn->createCommand();
            $_command->select( $_fields );
            $_command->from( $table );
            $_command->where( $_where );
            if ( !empty( $_params ) )
            {
                $_command->bindValues( $_params );
            }

            $this->checkConnection();
            $_reader = $_command->query();
            $_data = array();
            $_dummy = array();
            foreach ( $_bindings as $_binding )
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
                foreach ( $_bindings as $_binding )
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

            if ( !empty( $_relations ) )
            {
                foreach ( $_data as $_key => $_temp )
                {
                    $_data[$_key] = $this->retrieveRelatedRecords( $_temp, $_relations, $this->_related );
                }
            }

            // order returned data by received ids, fill in error for those not found
            $_results = array();
            foreach ( $ids as $_id )
            {
                $_foundRecord = null;
                foreach ( $_data as $_record )
                {
                    if ( isset( $_record[$_idField] ) && ( $_record[$_idField] == $_id ) )
                    {
                        $_foundRecord = $_record;
                        break;
                    }
                }
                $_results[] = ( isset( $_foundRecord ) ? $_foundRecord : ( "Could not find record for id = '$_id'" ) );
            }

            return $_results;
        }
        catch ( \Exception $_ex )
        {
            throw $_ex;
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
     * {@inheritdoc}
     */
    protected function _convertFilterStringToNative( $filter, $params = array(), $ss_filters = array() )
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

    /**
     * {@inheritdoc}
     */
    protected function _convertFilterArrayToNative( $filter, $params = array(), $ss_filters = array() )
    {
        // todo parse client filter?
        $_filterArray = $filter;
        // implode filter into string and search for index substitution
        $_indexSub = ( false != strpos(
                implode(
                    array_map(
                        function ( $a )
                        {
                            return implode( $a, ' ' );
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
        $record = DataFormat::arrayKeyLower( $record );
        $keys = array_keys( $record );
        $values = array_values( $record );
        foreach ( $avail_fields as $field_info )
        {
            $name = strtolower( Option::get( $field_info, 'name', '' ) );
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
                if ( DataFormat::isInList( Option::get( $field_info, 'validation', '' ), 'api_read_only', ',' ) )
                {
                    unset( $keys[$pos] );
                    unset( $values[$pos] );
                    continue; // should I error this?
                }
                if ( is_null( $fieldVal ) && !Option::getBool( $field_info, 'allow_null' ) )
                {
                    if ( $for_update )
                    {
                        continue;
                    }

                    // todo throw away nulls for now
                    throw new BadRequestException( "Field '$name' can not be NULL." );
                }
                else
                {
                    if ( !is_null( $fieldVal ) )
                    {
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
     * @param array  $record
     * @param mixed  $id
     * @param array  $avail_relations
     * @param bool   $allow_delete
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return void
     */
    protected function updateRelations( $table, $record, $id, $avail_relations, $allow_delete = false )
    {
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
        $field_arr = array_map( 'trim', explode( ',', $fields ) );
        $as_arr = array_map( 'trim', explode( ',', $fields_as ) );
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
        catch ( \Exception $ex )
        {
            throw new BadRequestException( "Error updating many to one assignment.\n{$ex->getMessage()}", $ex->getCode() );
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
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error updating many to one map assignment.\n{$ex->getMessage()}", $ex->getCode() );
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
        catch ( \Exception $ex )
        {
            error_log( 'batchquery: ' . $ex->getMessage() . PHP_EOL . $query );

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
        catch ( \Exception $ex )
        {
            error_log( 'singlequery: ' . $ex->getMessage() . PHP_EOL . $query . PHP_EOL . print_r( $params, true ) );

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
        catch ( \Exception $ex )
        {
            error_log( 'singleexecute: ' . $ex->getMessage() . PHP_EOL . $query . PHP_EOL . print_r( $params, true ) );

            throw $ex;
        }
    }

    /**
     * @return int
     */
    public function getDriverType()
    {
        return $this->_driverType;
    }
}
