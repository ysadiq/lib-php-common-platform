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
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Resources\User\Session;
use Kisma\Core\Utility\Option;

/**
 * MongoDbSvc.php
 *
 * A service to handle MongoDb NoSQL (schema-less) database
 * services accessed through the REST API.
 */
class MongoDbSvc extends NoSqlDbSvc
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default record identifier field
     */
    const DEFAULT_ID_FIELD = '_id';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var \MongoDB
     */
    protected $_dbConn = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new MongoDbSvc
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct( $config )
    {
        parent::__construct( $config );

        $_credentials = Option::get( $config, 'credentials' );
        $_dsn = Option::get( $_credentials, 'dsn', '' );
        $_db = Option::get( $_credentials, 'db' );
        if ( empty( $_dsn ) )
        {
            $_dsn = 'mongodb://localhost:27017';
            if ( empty( $_db ) )
            {
                throw new InternalServerErrorException( "No MongoDb database selected in configuration." );
            }
        }
        else
        {
            if ( 0 != substr_compare( $_dsn, 'mongodb://', 0, 10, true ) )
            {
                $_dsn = 'mongodb://' . $_dsn;
            }
        }

        $_options = array();
        if ( !empty( $_db ) )
        {
            $_options['db'] = $_db;
        }
        else
        {
            $_db = trim( strstr( substr( $_dsn, strlen( 'mongodb://' ) ), '/' ), '/' );
            if ( empty( $_db ) )
            {
                throw new InternalServerErrorException( "No MongoDb database selected in configuration." );
            }
        }

        $_username = Option::get( $_credentials, 'user' );
        if ( !empty( $_username ) )
        {
            $_options['username'] = $_username;
            $_password = Option::get( $_credentials, 'pwd' );
            if ( !empty( $_password ) )
            {
                $_options['password'] = $_password;
            }
        }

        try
        {
            $_client = new \MongoClient( $_dsn, $_options );
            $this->_dbConn = $_client->selectDB( $_db );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Unexpected MongoDb Service Exception:\n{$_ex->getMessage()}" );
        }
    }

    /**
     * Object destructor
     */
    public function __destruct()
    {
        try
        {
            $this->_dbConn = null;
        }
        catch ( \Exception $_ex )
        {
            error_log( "Failed to disconnect from database.\n{$_ex->getMessage()}" );
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
    }

    /**
     * @param $name
     *
     * @return \MongoCollection|null
     */
    public function selectTable( $name )
    {
        $this->checkConnection();
        $_coll = $this->_dbConn->selectCollection( $name );

        return $_coll;
    }

    /**
     * @param null|array $post_data
     *
     * @return array
     */
    protected function _gatherExtrasFromRequest( $post_data = null )
    {
        $_extras = parent::_gatherExtrasFromRequest( $post_data );

        return $_extras;
    }

    // REST service implementation

    /**
     * @throws \Exception
     * @return array
     */
    protected function _listResources()
    {
        try
        {
            $_resources = array();
            $_result = $this->_dbConn->getCollectionNames();
            foreach ( $_result as $_table )
            {
                $_access = $this->getPermissions( $_table );
                if ( !empty( $_access ) )
                {
                    $_resources[] = array( 'name' => $_table, 'access' => $_access );
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
        static $_existing = null;

        if ( !$_existing )
        {
            $_existing = $this->_dbConn->getCollectionNames();
        }

        $_name = ( is_array( $table ) ) ? Option::get( $table, 'name' ) : $table;
        if ( empty( $_name ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        if ( false === array_search( $_name, $_existing ) )
        {
            throw new NotFoundException( "Table '$_name' not found." );
        }

        try
        {
            $_coll = $this->selectTable( $_name );
            $_out = array( 'name' => $_coll->getName() );
            $_out['indexes'] = $_coll->getIndexInfo();
            $_out['access'] = $this->getPermissions( $_name );

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to get table properties for table '$_name'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTable( $properties = array() )
    {
        $_name = Option::get( $properties, 'name' );
        if ( empty( $_name ) )
        {
            throw new BadRequestException( "No 'name' field in data." );
        }

        try
        {
            $_result = $this->_dbConn->createCollection( $_name );
            $_out = array( 'name' => $_result->getName() );
            $_out['indexes'] = $_result->getIndexInfo();

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to create table '$_name'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable( $properties = array() )
    {
        $_name = Option::get( $properties, 'name' );
        if ( empty( $_name ) )
        {
            throw new BadRequestException( "No 'name' field in data." );
        }

        $this->selectTable( $_name );

//		throw new InternalServerErrorException( "Failed to update table '$_name'." );
        return array( 'name' => $_name );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTable( $table, $check_empty = false )
    {
        $_name = ( is_array( $table ) ) ? Option::get( $table, 'name' ) : $table;
        if ( empty( $_name ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        try
        {
            $this->_dbConn->dropCollection( $_name );

            return array( 'name' => $_name );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to delete table '$_name'.\n{$_ex->getMessage()}" );
        }
    }

    //-------- Table Records Operations ---------------------
    // records is an array of field arrays

    /**
     * {@inheritdoc}
     */
    public function createRecords( $table, $records, $extras = array() )
    {
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );
        $_coll = $this->selectTable( $table );

        $_isSingle = ( 1 == count( $records ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_useBatch = Option::getBool( $extras, 'batch', false );

        $_out = array();
        $_errors = array();
        $_batched = array();
        $_backup = array();
        try
        {
            $_fieldInfo = array();

            foreach ( $records as $_index => $_record )
            {
                try
                {
                    $_record = static::idToMongoId( $_record );
                    $_parsed = $this->parseRecord( $_record, $_fieldInfo, $_ssFilters );
                    if ( empty( $_parsed ) )
                    {
                        throw new BadRequestException( "No valid fields found in record $_index: " . print_r( $_record, true ) );
                    }

                    if ( $_useBatch )
                    {
                        $_batched[] = $_parsed;
                        continue;
                    }

                    // simple insert
                    $_result = $_coll->insert( $_parsed );
                    static::processResult( $_result );

                    if ( $_rollback )
                    {
                        $_backup[] = static::idToMongoId( Option::get( $_parsed, static::DEFAULT_ID_FIELD ) );
                    }

                    $_out[$_index] = static::cleanRecord( $_parsed, $_fields );
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_useBatch )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            if ( $_useBatch )
            {
                $_result = $_coll->batchInsert( $_batched, array( 'continueOnError' => !$_continue ) );
                static::processResult( $_result );

                if ( $_rollback )
                {
                    $_backup = static::recordsAsIds( $_batched );
                }

                $_out = static::cleanRecords( $_batched, $_fields );
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array( 'error' => $_errors, 'record' => $_out );
                $_msg = 'Batch Error: Not all records could be created.';
            }

            if ( $_rollback && !empty( $_backup ) )
            {
                $_filter = array( static::DEFAULT_ID_FIELD => array( '$in' => $_backup ) );
                $_coll->remove( $_filter );

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to create records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecords( $table, $records, $extras = array() )
    {
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );
        $_coll = $this->selectTable( $table );

        $_isSingle = ( 1 == count( $records ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_out = array();
        $_errors = array();
        $_backup = array();
        try
        {
            $_fieldInfo = array();
            $_fieldArray = ( $_rollback ) ? null : static::buildFieldArray( $_fields );
            $_options = array( 'new' => !$_rollback );

            foreach ( $records as $_index => $_record )
            {
                try
                {
                    $_id = Option::get( $_record, static::DEFAULT_ID_FIELD, null, true );
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '_id' can not be empty for update record request." );
                    }

                    $_parsed = $this->parseRecord( $_record, $_fieldInfo, $_ssFilters, true );
                    if ( empty( $_parsed ) )
                    {
                        throw new BadRequestException( "No valid fields found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_filter = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $_id ) );
                    $_criteria = $this->buildCriteriaArray( $_filter, null, $_ssFilters );

                    // simple update overwrite existing record
                    $_result = $_coll->findAndModify( $_criteria, $_parsed, $_fieldArray, $_options );
                    if ( empty( $_result ) )
                    {
                        throw new NotFoundException( "Record with id '$_id' not found." );
                    }

                    if ( $_rollback )
                    {
                        $_backup[] = $_result;
                        $_parsed[static::DEFAULT_ID_FIELD] = $_id;
                        $_out[$_index] = static::cleanRecord( $_parsed, $_fields );
                    }
                    else
                    {
                        $_out[$_index] = static::mongoIdToId( $_result );
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array( 'error' => $_errors, 'record' => $_out );
                $_msg = 'Batch Error: Not all records could be updated.';
            }

            if ( $_rollback && !empty( $_backup ) )
            {
                foreach ( $_backup as $_record )
                {
                    $_coll->save( $_record );
                }

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to update records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $_coll = $this->selectTable( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_fieldInfo = array();
        $_fieldArray = static::buildFieldArray( $_fields );

        static::removeIds( $record );
        $_parsed = $this->parseRecord( $record, $_fieldInfo, $_ssFilters, true );
        if ( empty( $_parsed ) )
        {
            throw new BadRequestException( 'No valid fields found in request: ' . print_r( $record, true ) );
        }

        // build criteria from filter parameters
        $_criteria = static::buildCriteriaArray( $filter, $params, $_ssFilters );

        try
        {
            $_result = $_coll->update( $_criteria, $_parsed, array( 'multiple' => true ) );
            $_rows = static::processResult( $_result );
            if ( $_rows > 0 )
            {
                /** @var \MongoCursor $_result */
                $_result = $_coll->find( $_criteria, $_fieldArray );
                $_out = iterator_to_array( $_result );

                return static::cleanRecords( $_out );
            }

            return array();
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
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );
        $_coll = $this->selectTable( $table );

        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_useBatch = Option::getBool( $extras, 'batch', false );

        static::removeIds( $record );
        $_fieldInfo = array();
        $_parsed = $this->parseRecord( $record, $_fieldInfo, $_ssFilters, true );
        if ( empty( $_parsed ) )
        {
            throw new BadRequestException( 'No valid fields found in request: ' . print_r( $record, true ) );
        }

        $_out = array();
        $_errors = array();
        $_batched = array();
        $_backup = array();
        try
        {
            $_fieldArray = ( $_rollback ) ? null : static::buildFieldArray( $_fields );
            $_options = array( 'new' => !$_rollback );

            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '_id' can not be empty for update record request." );
                    }

                    if ( $_useBatch )
                    {
                        $_batched[] = static::idToMongoId( $_id );
                        continue;
                    }

                    $_filter = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $_id ) );
                    $_criteria = $this->buildCriteriaArray( $_filter, null, $_ssFilters );

                    // simple update overwrite existing record
                    $_result = $_coll->findAndModify( $_criteria, $_parsed, $_fieldArray, $_options );
                    if ( empty( $_result ) )
                    {
                        throw new NotFoundException( "Record with id '$_id' not found." );
                    }

                    if ( $_rollback )
                    {
                        $_backup[] = $_result;
                        $_parsed[static::DEFAULT_ID_FIELD] = $_id;
                        $_out[$_index] = static::cleanRecord( $_parsed, $_fields );
                    }
                    else
                    {
                        $_out[$_index] = static::mongoIdToId( $_result );
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            if ( $_useBatch )
            {
                // build criteria from filter parameters
                $_filter = array( static::DEFAULT_ID_FIELD => array( '$in' => $_batched ) );
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );

                $_result = $_coll->update( $_criteria, $_parsed, null, array( 'multiple' => true ) );
                $_rows = static::processResult( $_result );
                if ( 0 === $_rows )
                {
                    throw new NotFoundException( 'No requested records were found to delete.' );
                }

                if ( count( $_batched ) !== $_rows )
                {
                    throw new BadRequestException( 'Batch Error: Not all requested records were found to update.' );
                }

                $_parsed = static::cleanRecord( $_parsed, $_fields );
                foreach ( $ids as $_id )
                {
                    $_parsed[static::DEFAULT_ID_FIELD] = $_id;
                    $_out[] = $_parsed;
                }
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array( 'error' => $_errors, 'record' => $_out );
                $_msg = 'Batch Error: Not all records could be updated.';
            }

            if ( $_rollback && !empty( $_backup ) )
            {
                foreach ( $_backup as $_record )
                {
                    $_coll->save( $_record );
                }

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to update records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecords( $table, $records, $extras = array() )
    {
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );
        $_coll = $this->selectTable( $table );

        $_isSingle = ( 1 == count( $records ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_out = array();
        $_errors = array();
        $_backup = array();
        try
        {
            $_fieldInfo = array();
            $_fieldArray = ( $_rollback ) ? null : static::buildFieldArray( $_fields );
            $_options = array( 'new' => !$_rollback );

            foreach ( $records as $_index => $_record )
            {
                try
                {
                    $_id = Option::get( $_record, static::DEFAULT_ID_FIELD, null, true );
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '_id' can not be empty for patch record request." );
                    }

                    if ( !static::doesRecordContainModifier( $_record ) )
                    {
                        $_parsed = $this->parseRecord( $_record, $_fieldInfo, $_ssFilters, true );
                        if ( empty( $_parsed ) )
                        {
                            throw new BadRequestException( "No valid fields found in record $_index: " . print_r( $_record, true ) );
                        }

                        $_parsed = array( '$set' => $_parsed );
                    }
                    else
                    {

                        $_parsed = $_record;
                        if ( empty( $_parsed ) )
                        {
                            throw new BadRequestException( "No valid fields found in record $_index: " . print_r( $_record, true ) );
                        }
                    }

                    $_filter = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $_id ) );
                    $_criteria = $this->buildCriteriaArray( $_filter, null, $_ssFilters );

                    // simple update merging with existing record
                    $_result = $_coll->findAndModify( $_criteria, $_parsed, $_fieldArray, $_options );
                    if ( empty( $_result ) )
                    {
                        throw new NotFoundException( "Record with id '$_id' not found." );
                    }

                    if ( $_rollback )
                    {
                        $_backup[] = $_result;
                        $_parsed[static::DEFAULT_ID_FIELD] = $_id;
                        $_out[$_index] = static::cleanRecord( $_parsed, $_fields );
                    }
                    else
                    {
                        $_out[$_index] = static::mongoIdToId( $_result );
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array( 'error' => $_errors, 'record' => $_out );
                $_msg = 'Batch Error: Not all records could be patched.';
            }

            if ( $_rollback && !empty( $_backup ) )
            {
                foreach ( $_backup as $_record )
                {
                    $_coll->save( $_record );
                }

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to patch records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $_coll = $this->selectTable( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_fieldInfo = array();
        $_fieldArray = static::buildFieldArray( $_fields );

        static::removeIds( $record );
        if ( !static::doesRecordContainModifier( $record ) )
        {
            $_parsed = $this->parseRecord( $record, $_fieldInfo, $_ssFilters, true );
            if ( empty( $_parsed ) )
            {
                throw new BadRequestException( 'No valid fields found in request: ' . print_r( $record, true ) );
            }

            $_parsed = array( '$set' => $_parsed );
        }
        else
        {

            $_parsed = $record;
            if ( empty( $_parsed ) )
            {
                throw new BadRequestException( 'No valid fields found in request: ' . print_r( $record, true ) );
            }
        }

        // build criteria from filter parameters
        $_criteria = static::buildCriteriaArray( $filter, $params, $_ssFilters );

        try
        {
            $_result = $_coll->update( $_criteria, $_parsed, array( 'multiple' => true ) );
            $_rows = static::processResult( $_result );
            if ( $_rows > 0 )
            {
                /** @var \MongoCursor $_result */
                $_result = $_coll->find( $_criteria, $_fieldArray );
                $_out = iterator_to_array( $_result );

                return static::cleanRecords( $_out );
            }

            return array();
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
    public function mergeRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );
        $_coll = $this->selectTable( $table );

        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_useBatch = Option::getBool( $extras, 'batch', false );

        static::removeIds( $record );
        $_fieldInfo = array();
        if ( !static::doesRecordContainModifier( $record ) )
        {
            $_parsed = $this->parseRecord( $record, $_fieldInfo, $_ssFilters, true );
            if ( empty( $_parsed ) )
            {
                throw new BadRequestException( 'No valid fields found in request: ' . print_r( $record, true ) );
            }

            $_parsed = array( '$set' => $_parsed );
        }
        else
        {

            $_parsed = $record;
            if ( empty( $_parsed ) )
            {
                throw new BadRequestException( 'No valid fields found in request: ' . print_r( $record, true ) );
            }
        }

        $_out = array();
        $_errors = array();
        $_batched = array();
        $_backup = array();
        try
        {
            $_fieldArray = ( $_rollback ) ? null : static::buildFieldArray( $_fields );
            $_options = array( 'new' => !$_rollback );

            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '_id' can not be empty for update record request." );
                    }

                    if ( $_useBatch )
                    {
                        $_batched[] = static::idToMongoId( $_id );
                        continue;
                    }

                    $_filter = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $_id ) );
                    $_criteria = $this->buildCriteriaArray( $_filter, null, $_ssFilters );

                    // simple update merging with existing record
                    $_result = $_coll->findAndModify( $_criteria, $_parsed, $_fieldArray, $_options );
                    if ( empty( $_result ) )
                    {
                        throw new NotFoundException( "Record with id '$_id' not found." );
                    }

                    if ( $_rollback )
                    {
                        $_backup[] = $_result;
                        $_parsed[static::DEFAULT_ID_FIELD] = $_id;
                        $_out[$_index] = static::cleanRecord( $_parsed, $_fields );
                    }
                    else
                    {
                        $_out[$_index] = static::mongoIdToId( $_result );
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            if ( $_useBatch )
            {
                $_filter = array( static::DEFAULT_ID_FIELD => array( '$in' => $_batched ) );
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );

                $_coll->update( $_criteria, $_parsed, array( 'multiple' => true ) );
                if ( static::_requireMoreFields( $_fields ) )
                {
                    /** @var \MongoCursor $_result */
                    $_result = $_coll->find( $_criteria, $_fieldArray );
                    $_out = static::mongoIdsToIds( iterator_to_array( $_result ) );
                }
                else
                {
                    $_out = static::idsAsRecords( static::mongoIdsToIds( $_batched ) );
                }

            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array( 'error' => $_errors, 'record' => $_out );
                $_msg = 'Batch Error: Not all records could be patched.';
            }

            if ( $_rollback && !empty( $_backup ) )
            {
                foreach ( $_backup as $_record )
                {
                    $_coll->save( $_record );
                }

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to patch records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function truncateTable( $table, $extras = array() )
    {
        $_coll = $this->selectTable( $table );
        try
        {
            // build filter string if necessary, add server-side filters if necessary
            $_ssFilters = Option::get( $extras, 'ss_filters' );
            $_criteria = $this->buildCriteriaArray( array(), null, $_ssFilters );
            $_result = $_coll->remove( $_criteria );

            return array( 'success' => $_result );
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
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );
        $_ids = static::recordsAsIds( $records );

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

        $_coll = $this->selectTable( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_fieldArray = static::buildFieldArray( $_fields );

        // build criteria from filter parameters
        $_criteria = static::buildCriteriaArray( $filter, $params, $_ssFilters );

        try
        {
            /** @var \MongoCursor $_result */
            $_result = $_coll->find( $_criteria, $_fieldArray );
            $_out = iterator_to_array( $_result );
            $_coll->remove( $_criteria );

            return static::cleanRecords( $_out );
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
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );
        $_coll = $this->selectTable( $table );

        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_useBatch = Option::getBool( $extras, 'batch', false );

        $_out = array();
        $_errors = array();
        $_batched = array();
        $_backup = array();
        try
        {
            $_fieldArray = ( $_rollback ) ? null : static::buildFieldArray( $_fields );
            $_options = array( 'remove' => true );

            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '_id' can not be empty for delete record request." );
                    }

                    if ( $_useBatch )
                    {
                        $_batched[] = static::idToMongoId( $_id );
                        continue;
                    }

                    $_filter = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $_id ) );
                    $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );

                    $_result = $_coll->findAndModify( $_criteria, null, $_fieldArray, $_options );
                    if ( empty( $_result ) )
                    {
                        throw new NotFoundException( "Record with id '$_id' not found." );
                    }

                    if ( $_rollback )
                    {
                        $_backup[] = $_result;
                        $_out[$_index] = static::cleanRecord( $_result, $_fields );
                    }
                    else
                    {
                        $_out[$_index] = static::mongoIdToId( $_result );
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle )
                    {
                        throw $_ex;
                    }

                    if ( $_rollback )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    if ( !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            if ( $_useBatch )
            {
                $_filter = array( static::DEFAULT_ID_FIELD => array( '$in' => $_batched ) );
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );

                if ( static::_requireMoreFields( $_fields ) )
                {
                    /** @var \MongoCursor $_result */
                    $_result = $_coll->find( $_criteria, $_fieldArray );
                    $_out = static::mongoIdsToIds( iterator_to_array( $_result ) );
                }
                else
                {
                    $_out = static::idsAsRecords( static::mongoIdsToIds( $_batched ) );
                }

                $_result = $_coll->remove( $_criteria );
                $_rows = static::processResult( $_result );
                if ( 0 === $_rows )
                {
                    throw new NotFoundException( 'No requested ids were found to delete.' );
                }
                if ( count( $ids ) !== $_rows )
                {
                    throw new BadRequestException( 'Batch Error: Not all requested ids were found to delete.' );
                }
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array( 'error' => $_errors, 'record' => $_out );
                $_msg = 'Batch Error: Not all records could be deleted.';
            }

            if ( $_rollback && !empty( $_backup ) )
            {
                foreach ( $_backup as $_record )
                {
                    $_coll->save( $_record );
                }

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() )
    {
        $_coll = $this->selectTable( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_fieldArray = static::buildFieldArray( $_fields );
        $_criteria = static::buildCriteriaArray( $filter, $params, $_ssFilters );

        $_limit = intval( Option::get( $extras, 'limit', 0 ) );
        $_offset = intval( Option::get( $extras, 'offset', 0 ) );
        $_sort = static::buildSortArray( Option::get( $extras, 'order' ) );
        $_addCount = Option::get( $extras, 'include_count', false );

        try
        {
            /** @var \MongoCursor $_result */
            $_result = $_coll->find( $_criteria, $_fieldArray );
            $_count = $_result->count();
            $_maxAllowed = static::getMaxRecordsReturnedLimit();
            $_needMore = ( ( $_count - $_offset ) > $_maxAllowed );
            if ( $_offset )
            {
                $_result = $_result->skip( $_offset );
            }
            if ( $_sort )
            {
                $_result = $_result->sort( $_sort );
            }
            if ( ( $_limit < 1 ) || ( $_limit > $_maxAllowed ) )
            {
                $_limit = $_maxAllowed;
            }
            $_result = $_result->limit( $_limit );

            $_out = iterator_to_array( $_result );
            $_out = static::cleanRecords( $_out );
            if ( $_addCount || $_needMore )
            {
                $_out['meta']['count'] = $_count;
                if ( $_needMore )
                {
                    $_out['meta']['next'] = $_offset + $_limit + 1;
                }
            }

            return $_out;
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to filter records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecords( $table, $records, $extras = array() )
    {
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );
        $_ids = static::recordsAsIds( $records );

        return $this->retrieveRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByIds( $table, $ids, $extras = array() )
    {
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );
        $_coll = $this->selectTable( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_fieldArray = static::buildFieldArray( $_fields );

        $_filter = array( static::DEFAULT_ID_FIELD => array( '$in' => static::idsToMongoIds( $ids ) ) );
        $_criteria = $this->buildCriteriaArray( $_filter, null, $_ssFilters );

        try
        {
//            $_result = $_coll->findOne( $_criteria, $_fieldArray );
//            if ( empty( $_result ) && is_numeric( $id ) )
//            {
//                // defaults to string ids, could be numeric, try that
//                $id = ( $id == strval( intval( $id ) ) ) ? intval( $id ) : floatval( $id );
//                $_result = $_coll->findOne( array( static::DEFAULT_ID_FIELD => $id ), $_fieldArray );
//            }
            /** @var \MongoCursor $_result */
            $_result = $_coll->find( $_criteria, $_fieldArray );
            $_data = iterator_to_array( $_result );

            $_out = array();
            foreach ( $ids as $_id )
            {
                $_foundRecord = null;
                foreach ( $_data as $_record )
                {
                    if ( isset( $_record[static::DEFAULT_ID_FIELD] ) && ( $_record[static::DEFAULT_ID_FIELD] == $_id ) )
                    {
                        $_foundRecord = $_record;
                        break;
                    }
                }
                if ( isset( $_foundRecord ) )
                {
                    $_out[] = $_foundRecord;
                }
                else
                {
                    throw new NotFoundException( "Record with id '$_id' not found." );
                }
            }

            return static::cleanRecords( $_out );
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to get records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * @param $record
     *
     * @return bool
     */
    protected static function doesRecordContainModifier( $record )
    {
        if ( is_array( $record ) )
        {
            foreach ( $record as $_key => $_value )
            {
                if ( !empty( $_key ) && ( '$' == $_key[0] ) )
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string|array $include List of keys to include in the output record
     *
     * @return array
     */
    protected static function buildFieldArray( $include = '*' )
    {
        if ( '*' == $include )
        {
            return array();
        }

        if ( empty( $include ) )
        {
            $include = static::DEFAULT_ID_FIELD;
        }
        if ( !is_array( $include ) )
        {
            $include = array_map( 'trim', explode( ',', trim( $include, ',' ) ) );
        }
        if ( false === array_search( static::DEFAULT_ID_FIELD, $include ) )
        {
            $include[] = static::DEFAULT_ID_FIELD;
        }

        $_out = array();
        foreach ( $include as $key )
        {
            $_out[$key] = true;
        }

        return $_out;
    }

    /**
     * @param string|array $filter Filter for querying records by
     * @param array        $params Filter replacement parameters
     *
     * @return array
     */
    protected static function buildFilterArray( $filter, $params = null )
    {
        if ( empty( $filter ) )
        {
            return array();
        }

        if ( is_array( $filter ) )
        {
            return $filter; // assume they know what they are doing
        }

        $_search = array( ' or ', ' and ', ' nor ' );
        $_replace = array( ' || ', ' && ', ' NOR ' );
        $filter = trim( str_ireplace( $_search, $_replace, $filter ) );

        // handle logical operators first
        $_ops = array_map( 'trim', explode( ' || ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_parts = array();
            foreach ( $_ops as $_op )
            {
                $_parts[] = static::buildFilterArray( $_op, $params );
            }

            return array( '$or' => $_parts );
        }

        $_ops = array_map( 'trim', explode( ' NOR ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_parts = array();
            foreach ( $_ops as $_op )
            {
                $_parts[] = static::buildFilterArray( $_op, $params );
            }

            return array( '$nor' => $_parts );
        }

        $_ops = array_map( 'trim', explode( ' && ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_parts = array();
            foreach ( $_ops as $_op )
            {
                $_parts[] = static::buildFilterArray( $_op, $params );
            }

            return array( '$and' => $_parts );
        }

        // handle negation operator, i.e. starts with NOT?
        if ( 0 == substr_compare( $filter, 'not ', 0, 4, true ) )
        {
            $_parts = trim( substr( $filter, 4 ) );

            return array( '$not' => $_parts );
        }

        // the rest should be comparison operators
        $_search = array( ' eq ', ' ne ', ' gte ', ' lte ', ' gt ', ' lt ', ' in ', ' nin ', ' all ', ' like ', ' <> ' );
        $_replace = array( '=', '!=', '>=', '<=', '>', '<', ' IN ', ' NIN ', ' ALL ', ' LIKE ', '!=' );
        $filter = trim( str_ireplace( $_search, $_replace, $filter ) );

        // Note: order matters, watch '='
        $_sqlOperators = array( '!=', '>=', '<=', '=', '>', '<', ' IN ', ' NIN ', ' ALL ', ' LIKE ' );
        $_mongoOperators = array( '$ne', '$gte', '$lte', '$eq', '$gt', '$lt', '$in', '$nin', '$all', ' LIKE ' );
        foreach ( $_sqlOperators as $_key => $_sqlOp )
        {
            $_ops = array_map( 'trim', explode( $_sqlOp, $filter ) );
            if ( count( $_ops ) > 1 )
            {
                $_field = $_ops[0];
                $_val = static::_determineValue( $_ops[1], $_field, $params );
                $_mongoOp = $_mongoOperators[$_key];
                switch ( $_mongoOp )
                {
                    case '$eq':
                        return array( $_field => $_val );

                    case '$in':
                    case '$nin':
                        // todo check for list of mongoIds
                        return array( $_field => array( $_mongoOp => $_val ) );

                    case 'MongoRegex':
//			WHERE name LIKE "%Joe%"	(array("name" => new MongoRegex("/Joe/")));
//			WHERE name LIKE "Joe%"	(array("name" => new MongoRegex("/^Joe/")));
//			WHERE name LIKE "%Joe"	(array("name" => new MongoRegex("/Joe$/")));
                        $_val = static::_determineValue( $_ops[1], $_field, $params );
                        if ( '%' == $_val[strlen( $_val ) - 1] )
                        {
                            if ( '%' == $_val[0] )
                            {
                                $_val = '/' . trim( $_val, '%' ) . '/ ';
                            }
                            else
                            {
                                $_val = '/^' . rtrim( $_val, '%' ) . '/ ';
                            }
                        }
                        else
                        {
                            if ( '%' == $_val[0] )
                            {
                                $_val = '/' . trim( $_val, '%' ) . '$/ ';
                            }
                            else
                            {
                                $_val = '/' . $_val . '/ ';
                            }
                        }

                        return array( $_field => new \MongoRegex( $_val ) );

                    default:
                        return array( $_field => array( $_mongoOp => $_val ) );
                }
            }
        }

        return $filter;
    }

    /**
     * @param string $value
     * @param string $field
     * @param array  $replacements
     *
     * @return bool|float|int|string|\MongoId
     */
    private static function _determineValue( $value, $field = null, $replacements = null )
    {
        // process parameter replacements
        if ( is_string( $value ) && !empty( $value ) && ( ':' == $value[0] ) )
        {
            if ( isset( $replacements, $replacements[$value] ) )
            {
                $value = $replacements[$value];
            }
        }

        if ( $field && ( static::DEFAULT_ID_FIELD == $field ) )
        {
            return static::idToMongoId( $value, true );
        }

        if ( trim( $value, "'\"" ) !== $value )
        {
            return trim( $value, "'\"" ); // meant to be a string
        }

        if ( is_numeric( $value ) )
        {
            return ( $value == strval( intval( $value ) ) ) ? intval( $value ) : floatval( $value );
        }

        if ( 0 == strcasecmp( $value, 'true' ) )
        {
            return true;
        }

        if ( 0 == strcasecmp( $value, 'false' ) )
        {
            return false;
        }

        return $value;
    }

    protected static function buildCriteriaArray( $filter, $params = null, $ss_filters = null )
    {
        // build filter array if necessary, add server-side filters if necessary
        $_criteria = ( !is_array( $filter ) ) ? static::buildFilterArray( $filter, $params ) : $filter;
        $_serverCriteria = static::buildSSFilterArray( $ss_filters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }

        return $_criteria;
    }

    protected static function buildSSFilterArray( $ss_filters )
    {
        if ( empty( $ss_filters ) )
        {
            return null;
        }

        // build the server side criteria
        $_filters = Option::get( $ss_filters, 'filters' );
        if ( empty( $_filters ) )
        {
            return null;
        }

        $_criteria = array();
        $_combiner = Option::get( $ss_filters, 'filter_op', 'and' );
        foreach ( $_filters as $_filter )
        {
            $_name = Option::get( $_filter, 'name' );
            $_op = Option::get( $_filter, 'operator' );
            if ( empty( $_name ) || empty( $_op ) )
            {
                // log and bail
                throw new InternalServerErrorException( 'Invalid server-side filter configuration detected.' );
            }

            $_value = Option::get( $_filter, 'value' );
            $_value = static::interpretFilterValue( $_value );

            $_criteria[] = static::buildFilterArray( "$_name $_op $_value" );
        }

        if ( 1 == count( $_criteria ) )
        {
            return $_criteria[0];
        }

        switch ( strtoupper( $_combiner ) )
        {
            case 'AND':
                return array( '$and' => $_criteria );
            case 'OR':
                return array( '$or' => $_criteria );
            case 'NOR':
                return array( '$nor' => $_criteria );
            default:
                // log and bail
                throw new InternalServerErrorException( 'Invalid server-side filter configuration detected.' );
        }
    }

    /**
     * @param string|array $sort List of fields to sort the output records by
     *
     * @return array
     */
    protected static function buildSortArray( $sort )
    {
        if ( empty( $sort ) )
        {
            return null;
        }

        if ( !is_array( $sort ) )
        {
            $sort = array_map( 'trim', explode( ',', trim( $sort, ',' ) ) );
        }
        $_out = array();
        foreach ( $sort as $_combo )
        {
            if ( !is_array( $_combo ) )
            {
                $_combo = array_map( 'trim', explode( ' ', trim( $_combo, ' ' ) ) );
            }
            $_dir = 1;
            $_field = '';
            switch ( count( $_combo ) )
            {
                case 1:
                    $_field = $_combo[0];
                    break;
                case 2:
                    $_field = $_combo[0];
                    switch ( $_combo[1] )
                    {
                        case -1:
                        case 'desc':
                        case 'DESC':
                        case 'dsc':
                        case 'DSC':
                            $_dir = -1;
                            break;
                    }
            }
            if ( !empty( $_field ) )
            {
                $_out[$_field] = $_dir;
            }
        }

        return $_out;
    }

    /**
     * @param array        $record
     * @param string|array $include List of keys to include in the output record
     * @param string|array $id_field
     *
     * @return array
     */
    protected static function cleanRecord( $record, $include = '*', $id_field = null )
    {
        $_out = parent::cleanRecord( $record, $include, $id_field );

        return static::mongoIdToId( $_out );
    }

    /**
     * @param array  $records
     * @param string $id_field
     *
     * @return mixed
     */
    protected static function mongoIdsToIds( $records, $id_field = null )
    {
        foreach ( $records as $key => $_record )
        {
            $records[$key] = static::mongoIdToId( $_record, $id_field );
        }

        return $records;
    }

    /**
     * @param mixed  $record
     * @param string $id_field
     *
     * @return array|string
     */
    protected static function mongoIdToId( $record, $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            $id_field = static::DEFAULT_ID_FIELD;
        }
        if ( !is_array( $record ) )
        {
            if ( is_object( $record ) )
            {
                /** $record \MongoId */
                $record = (string)$record;
            }
        }
        else
        {
            /** @var \MongoId $_id in record as '_id' or 'id' */
            $_id = Option::get( $record, $id_field, Option::get( $record, 'id', null, true ) );
            if ( is_object( $_id ) )
            {
                /** $_id \MongoId */
                $_id = (string)$_id;
            }
            $record[$id_field] = $_id;
        }

        return $record;
    }

    /**
     * @param mixed  $record
     * @param bool   $determine_value
     * @param string $id_field
     *
     * @return array|bool|float|int|\MongoId|string
     */
    protected static function idToMongoId( $record, $determine_value = false, $id_field = null )
    {
        if ( !is_array( $record ) )
        {
            if ( is_string( $record ) )
            {
                $_isMongo = false;
                if ( ( 24 == strlen( $record ) ) )
                {
                    // single id
                    try
                    {
                        $record = new \MongoId( $record );
                        $_isMongo = true;
                    }
                    catch ( \Exception $_ex )
                    {
                        // obviously not a Mongo created Id, let it be
                    }
                }
                if ( !$_isMongo && $determine_value )
                {
                    $record = static::_determineValue( $record );
                }
            }
        }
        else
        {
            if ( empty( $id_field ) )
            {
                $id_field = static::DEFAULT_ID_FIELD;
            }

            // single record with fields
            $_id = Option::get( $record, $id_field );
            if ( is_string( $_id ) )
            {
                $_isMongo = false;
                if ( ( 24 == strlen( $_id ) ) )
                {
                    try
                    {
                        $_id = new \MongoId( $_id );
                        $_isMongo = true;
                    }
                    catch ( \Exception $_ex )
                    {
                        // obviously not a Mongo created Id, let it be
                    }
                }
                if ( !$_isMongo && $determine_value )
                {
                    $_id = static::_determineValue( $_id );
                }
                $record[$id_field] = $_id;
            }
        }

        return $record;
    }

    /**
     * @param string|array $records
     * @param string       $id_field
     *
     * @return array
     */
    protected static function idsToMongoIds( $records, $id_field = null )
    {
        $_determineValue = false;
        if ( !is_array( $records ) )
        {
            // comma delimited list of ids
            $records = array_map( 'trim', explode( ',', trim( $records, ',' ) ) );
            $_determineValue = true;
        }

        foreach ( $records as $key => $_record )
        {
            $records[$key] = static::idToMongoId( $_record, $_determineValue, $id_field );
        }

        return $records;
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
//        $record = DataFormat::arrayKeyLower( $record );
        $_parsed = ( empty( $avail_fields ) ) ? $record : array();
        if ( !empty( $avail_fields ) )
        {
            $_keys = array_keys( $record );
            $_values = array_values( $record );
            foreach ( $avail_fields as $_fieldInfo )
            {
//            $name = strtolower( Option::get( $field_info, 'name', '' ) );
                $_name = Option::get( $_fieldInfo, 'name', '' );
                $_type = Option::get( $_fieldInfo, 'type' );
                $_pos = array_search( $_name, $_keys );
                if ( false !== $_pos )
                {
                    $_fieldVal = Option::get( $_values, $_pos );
                    // due to conversion from XML to array, null or empty xml elements have the array value of an empty array
                    if ( is_array( $_fieldVal ) && empty( $_fieldVal ) )
                    {
                        $_fieldVal = null;
                    }

                    /** validations **/

                    $_validations = Option::get( $_fieldInfo, 'validation' );

                    if ( !static::validateFieldValue( $_name, $_fieldVal, $_validations, $for_update, $_fieldInfo ) )
                    {
                        unset( $_keys[$_pos] );
                        unset( $_values[$_pos] );
                        continue;
                    }

                    $_parsed[$_name] = $_fieldVal;
                    unset( $_keys[$_pos] );
                    unset( $_values[$_pos] );
                }

                // add or override for specific fields
                switch ( $_type )
                {
                    case 'timestamp_on_create':
                        if ( !$for_update )
                        {
                            $_parsed[$_name] = new \MongoDate();
                        }
                        break;
                    case 'timestamp_on_update':
                        $_parsed[$_name] = new \MongoDate();
                        break;
                    case 'user_id_on_create':
                        if ( !$for_update )
                        {
                            $userId = Session::getCurrentUserId();
                            if ( isset( $userId ) )
                            {
                                $_parsed[$_name] = $userId;
                            }
                        }
                        break;
                    case 'user_id_on_update':
                        $userId = Session::getCurrentUserId();
                        if ( isset( $userId ) )
                        {
                            $_parsed[$_name] = $userId;
                        }
                        break;
                }
            }
        }

        if ( !empty( $filter_info ) )
        {
            $this->validateRecord( $_parsed, $filter_info, $for_update, $old_record );
        }

        return $_parsed;
    }

    /**
     * @param $result
     *
     * @return int Number of affected records
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     */
    protected static function processResult( $result )
    {
        if ( !is_array( $result ) || empty( $result ) )
        {
            throw new InternalServerErrorException( 'MongoDb did not return an array, check configuration.' );
        }

        $_errorMsg = Option::get( $result, 'err' );
        if ( !empty( $_errorMsg ) )
        {
            throw new InternalServerErrorException( 'MongoDb error:' . $_errorMsg );
        }

        return Option::get( $result, 'n' );
    }
}