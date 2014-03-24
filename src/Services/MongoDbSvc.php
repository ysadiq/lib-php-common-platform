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
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Unexpected MongoDb Service Exception:\n{$ex->getMessage()}" );
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
        catch ( \Exception $ex )
        {
            error_log( "Failed to disconnect from database.\n{$ex->getMessage()}" );
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
                try
                {
                    $this->validateTableAccess( $_table );
                    $_resources[] = array( 'name' => $_table );
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
            throw new InternalServerErrorException( "Failed to list resources for this service.\n" . $ex->getMessage() );
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
            $_coll = $this->selectTable( $_name );
            $_out = array( 'name' => $_coll->getName() );
            $_out['indexes'] = $_coll->getIndexInfo();

            return $_out;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to get container info on MongoDb service.\n" . $ex->getMessage() );
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
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to create a container on MongoDb service.\n" . $ex->getMessage() );
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

//		throw new InternalServerErrorException( "Failed to update table '$table' on MongoDb service." );
        return array( 'name' => $_name );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTable( $table, $check_empty = false )
    {
        if ( is_array( $table ) )
        {
            $table = Option::get( $table, 'name' );
        }
        if ( empty( $table ) )
        {
            throw new BadRequestException( "No 'name' field in data." );
        }
        try
        {
            $this->_dbConn->dropCollection( $table );

            return array( 'name' => $table );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to delete table '$table' from MongoDb service.\n" . $ex->getMessage() );
        }
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

        $_coll = $this->selectTable( $table );
        $_rollback = Option::get( $extras, 'rollback', false );
        $_fields = Option::get( $extras, 'fields' );
        $records = static::idsToMongoIds( $records );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        if ( !empty( $_ssFilters ) )
        {
            foreach ( $records as $_record )
            {
                $this->validateRecord( $_record, $_ssFilters );
            }
        }
        try
        {
            $_coll->batchInsert( $records, array( 'continueOnError' => !$_rollback ) );
            $_out = static::cleanRecords( $records, $_fields );

            return $_out;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to create items in '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createRecord( $table, $record, $extras = array() )
    {
        if ( empty( $record ) || !is_array( $record ) )
        {
            throw new BadRequestException( 'There are no record fields in the request.' );
        }

        $_coll = $this->selectTable( $table );
        $record = static::idToMongoId( $record );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        if ( !empty( $_ssFilters ) )
        {
            $this->validateRecord( $record, $_ssFilters );
        }
        try
        {
            $_coll->save( $record ); // same as insert if no _id
            $_out = static::cleanRecord( $record, $_fields );

            return $_out;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to create item in '$table' on MongoDb service.\n" . $ex->getMessage() );
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

        $_coll = $this->selectTable( $table );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_out = array();
        foreach ( $records as $_record )
        {
            if ( !empty( $_ssFilters ) )
            {
                $this->validateRecord( $_record, $_ssFilters );
            }
            try
            {
                $_record = static::idToMongoId( $_record );
                $_coll->save( $_record ); // same as update if _id
                $_out[] = static::cleanRecord( $_record, $_fields );
            }
            catch ( \Exception $ex )
            {
                throw new InternalServerErrorException( "Failed to update items in '$table' on MongoDb service.\n" . $ex->getMessage() );
            }
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecord( $table, $record, $extras = array() )
    {
        if ( empty( $record ) || !is_array( $record ) )
        {
            throw new BadRequestException( 'There are no record fields in the request.' );
        }

        $_coll = $this->selectTable( $table );
        $record = static::idToMongoId( $record );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        if ( !empty( $_ssFilters ) )
        {
            $this->validateRecord( $record, $_ssFilters );
        }
        try
        {
            $_coll->save( $record ); // same as update if _id

            return static::cleanRecord( $record, $_fields );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to update item in '$table' on MongoDb service.\n" . $ex->getMessage() );
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

        unset( $record[static::DEFAULT_ID_FIELD] ); // make sure the record has no identifier
        $_coll = $this->selectTable( $table );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        // build criteria from filter parameters
        $_criteria = static::buildFilterArray( $filter );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }
        try
        {
            $_coll->update( $_criteria, $record, array( 'multiple' => true ) );
            /** @var \MongoCursor $_result */
            $_result = $_coll->find( $_criteria, $_fieldArray );
            $_out = iterator_to_array( $_result );

            return static::cleanRecords( $_out );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to update item in '$table' on MongoDb service.\n" . $ex->getMessage() );
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

        if ( empty( $ids ) )
        {
            throw new BadRequestException( "Identifying values for '_id' can not be empty for update request." );
        }

        $_coll = $this->selectTable( $table );
        $ids = static::idsToMongoIds( $ids );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        if ( !empty( $_ssFilters ) )
        {
            $this->validateRecord( $record, $_ssFilters );
        }
        // build criteria from filter parameters
        $_criteria = array( static::DEFAULT_ID_FIELD => array( '$in' => $ids ) );
        unset( $record[static::DEFAULT_ID_FIELD] ); // make sure the record has no identifier
        try
        {
            $_coll->update( $_criteria, $record, array( 'multiple' => true ) );
            $_out = array();
            foreach ( $ids as $_id )
            {
                $record[static::DEFAULT_ID_FIELD] = $_id;
                $_out[] = static::cleanRecords( $record, $_fields );
            }

            return $_out;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to update item in '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecordById( $table, $record, $id, $extras = array() )
    {
        if ( !isset( $record ) || empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record.' );
        }
        if ( empty( $id ) )
        {
            throw new BadRequestException( "No identifier exist in record." );
        }

        $_coll = $this->selectTable( $table );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        if ( !empty( $_ssFilters ) )
        {
            $this->validateRecord( $record, $_ssFilters );
        }
        $record[static::DEFAULT_ID_FIELD] = static::idToMongoId( $id );
        try
        {
            $_coll->save( $record );

            return static::cleanRecord( $record, $_fields );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to update item in '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecords( $table, $records, $extras = array() )
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

        $_coll = $this->selectTable( $table );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_out = array();
        foreach ( $records as $_record )
        {
            $_id = Option::get( $_record, static::DEFAULT_ID_FIELD, null, true );
            if ( empty( $_id ) )
            {
                throw new BadRequestException( "Identifying field '_id' can not be empty for merge record request." );
            }

            $_criteria = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $_id ) );
            $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
            if ( !empty( $_serverCriteria ) )
            {
                $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
            }
            try
            {
                if ( !static::doesRecordContainModifier( $_record ) )
                {
                    $_record = array( '$set' => $_record );
                }
                $result = $_coll->findAndModify(
                    $_criteria,
                    $_record,
                    $_fieldArray,
                    array( 'new' => true )
                );

                $_out[] = static::mongoIdToId( $result );
            }
            catch ( \Exception $ex )
            {
                throw new InternalServerErrorException( "Failed to update items in '$table' on MongoDb service.\n" . $ex->getMessage() );
            }
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecord( $table, $record, $extras = array() )
    {
        if ( empty( $record ) || !is_array( $record ) )
        {
            throw new BadRequestException( 'There are no record fields in the request.' );
        }

        $_id = Option::get( $record, static::DEFAULT_ID_FIELD, null, true );
        if ( empty( $_id ) )
        {
            throw new BadRequestException( "Identifying field '_id' can not be empty for merge record request." );
        }

        $_coll = $this->selectTable( $table );
        $_criteria = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $_id ) );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        if ( !empty( $_ssFilters ) )
        {
            $this->validateRecord( $record, $_ssFilters );
        }
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }
        if ( !static::doesRecordContainModifier( $record ) )
        {
            $record = array( '$set' => $record );
        }
        try
        {
            $result = $_coll->findAndModify(
                $_criteria,
                $record,
                $_fieldArray,
                array( 'new' => true )
            );

            return static::mongoIdToId( $result );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to update item in '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        if ( !is_array( $record ) || empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record.' );
        }

        unset( $record[static::DEFAULT_ID_FIELD] );
        $_coll = $this->selectTable( $table );
        // build criteria from filter parameters
        $_criteria = static::buildFilterArray( $filter );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        if ( !empty( $_ssFilters ) )
        {
            $this->validateRecord( $record, $_ssFilters );
        }
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }
        if ( !static::doesRecordContainModifier( $record ) )
        {
            $record = array( '$set' => $record );
        }
        try
        {
            $_coll->update( $_criteria, $record, array( 'multiple' => true ) );
            /** @var \MongoCursor $_result */
            $_result = $_coll->find( $_criteria, $_fieldArray );
            $_out = iterator_to_array( $_result );

            return static::cleanRecords( $_out );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to update item in '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        if ( !is_array( $record ) || empty( $record ) )
        {
            throw new BadRequestException( "No record fields were passed in the request." );
        }
        if ( empty( $ids ) )
        {
            throw new BadRequestException( "Identifying values for '_id' can not be empty for update request." );
        }

        $_coll = $this->selectTable( $table );
        $ids = static::idsToMongoIds( $ids );
        $_criteria = array( static::DEFAULT_ID_FIELD => array( '$in' => $ids ) );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        if ( !empty( $_ssFilters ) )
        {
            $this->validateRecord( $record, $_ssFilters );
        }
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }
        unset( $record[static::DEFAULT_ID_FIELD] ); // make sure the record has no identifier

        if ( !static::doesRecordContainModifier( $record ) )
        {
            $record = array( '$set' => $record );
        }
        try
        {
            $_coll->update( $_criteria, $record, array( 'multiple' => true ) );
            if ( static::_requireMoreFields( $_fields ) )
            {
                /** @var \MongoCursor $result */
                $result = $_coll->find( $_criteria, $_fieldArray );
                $_out = iterator_to_array( $result );
            }
            else
            {
                $_out = static::idsAsRecords( $ids );
            }

            return static::cleanRecords( $_out );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to update item in '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecordById( $table, $record, $id, $extras = array() )
    {
        if ( !isset( $record ) || empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record.' );
        }
        if ( empty( $id ) )
        {
            throw new BadRequestException( "No identifier exist in record." );
        }

        $_coll = $this->selectTable( $table );
        $_criteria = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $id ) );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        if ( !empty( $_ssFilters ) )
        {
            $this->validateRecord( $record, $_ssFilters );
        }
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }
        unset( $record[static::DEFAULT_ID_FIELD] );
        if ( !static::doesRecordContainModifier( $record ) )
        {
            $record = array( '$set' => $record );
        }
        try
        {
            $result = $_coll->findAndModify(
                $_criteria,
                $record,
                $_fieldArray,
                array( 'new' => true )
            );

            return static::mongoIdToId( $result );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to update item in '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function truncateTable( $table )
    {
        $_coll = $this->selectTable( $table );
        try
        {
            $result = $_coll->remove( array() );

            return array( 'success' => $result );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to delete all items from '$table' on MongoDb service.\n" . $ex->getMessage() );
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

        $_coll = $this->selectTable( $table );
        $_ids = static::recordsAsIds( $records );
        $_criteria = array( static::DEFAULT_ID_FIELD => array( '$in' => static::idsToMongoIds( $_ids ) ) );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }
        try
        {
            if ( static::_requireMoreFields( $_fields ) )
            {
                /** @var \MongoCursor $_result */
                $_result = $_coll->find( $_criteria, $_fieldArray );
                $_out = iterator_to_array( $_result );
            }
            else
            {
                $_out = static::idsAsRecords( $_ids );
            }

            $_coll->remove( $_criteria );

            return static::cleanRecords( $_out );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to delete items from '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecord( $table, $record, $extras = array() )
    {
        if ( !isset( $record ) || empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record.' );
        }

        $_coll = $this->selectTable( $table );
        $_criteria = static::idToMongoId( $record );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }
        try
        {
            $result = $_coll->findAndModify( $_criteria, null, $_fieldArray, array( 'remove' => true ) );

            return static::mongoIdToId( $result );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to delete item from '$table' on MongoDb service.\n" . $ex->getMessage() );
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

        $_coll = $this->selectTable( $table );
        // build criteria from filter parameters
        $_criteria = static::buildFilterArray( $filter );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }
        try
        {
            /** @var \MongoCursor $_result */
            $_result = $_coll->find( $_criteria, $_fieldArray );
            $_out = iterator_to_array( $_result );
            $_coll->remove( $_criteria );

            return static::cleanRecords( $_out );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to update item in '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecordsByIds( $table, $ids, $extras = array() )
    {
        if ( empty( $ids ) )
        {
            throw new BadRequestException( "Identifying values for '_id' can not be empty for update request." );
        }

        $_coll = $this->selectTable( $table );
        $ids = static::idsToMongoIds( $ids );
        $_criteria = array( static::DEFAULT_ID_FIELD => array( '$in' => $ids ) );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }
        try
        {
            if ( static::_requireMoreFields( $_fields ) )
            {
                /** @var \MongoCursor $_result */
                $_result = $_coll->find( $_criteria, $_fieldArray );
                $_out = iterator_to_array( $_result );
            }
            else
            {
                $_out = static::idsAsRecords( $ids );
            }

            $_coll->remove( $_criteria );

            return static::cleanRecords( $_out );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to delete items from '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecordById( $table, $id, $extras = array() )
    {
        if ( empty( $id ) )
        {
            throw new BadRequestException( "No identifier exist in record." );
        }

        $_coll = $this->selectTable( $table );
        $_criteria = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $id ) );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }

        try
        {
            $result = $_coll->findAndModify( $_criteria, null, $_fieldArray, array( 'remove' => true ) );

            return static::mongoIdToId( $result );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to delete item from '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() )
    {
        $_coll = $this->selectTable( $table );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );

        $_criteria = static::buildFilterArray( $filter );
        // build filter string if necessary, add server-side filters if necessary
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }

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
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to filter items from '$table' on MongoDb service.\n" . $ex->getMessage() );
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

        $_coll = $this->selectTable( $table );
        $_ids = static::idsToMongoIds( static::recordsAsIds( $records ) );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_criteria = array( '$in' => $_ids );
        // build filter string if necessary, add server-side filters if necessary
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }

        try
        {
            /** @var \MongoCursor $result */
            $result = $_coll->find( $_criteria, $_fieldArray );
            $_out = iterator_to_array( $result );

            return static::cleanRecords( $_out );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to get items from '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecord( $table, $record, $extras = array() )
    {
        if ( !is_array( $record ) || empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record.' );
        }

        $_coll = $this->selectTable( $table );
        $_id = Option::get( $record, static::DEFAULT_ID_FIELD );
        if ( empty( $_id ) )
        {
            throw new BadRequestException( "Identifying field '_id' can not be empty for retrieve record request." );
        }

        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_criteria = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $_id ) );
        // build filter string if necessary, add server-side filters if necessary
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }

        try
        {
            $result = $_coll->findOne( $_criteria, $_fieldArray );

            return static::mongoIdToId( $result );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to get item '$table/$_id' on MongoDb service.\n" . $ex->getMessage() );
        }
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

        $_coll = $this->selectTable( $table );
        $ids = static::idsToMongoIds( $ids );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_criteria = array( static::DEFAULT_ID_FIELD => array( '$in' => $ids ) );
        // build filter string if necessary, add server-side filters if necessary
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }

        try
        {
            /** @var \MongoCursor $result */
            $result = $_coll->find( $_criteria, $_fieldArray );
            $_out = iterator_to_array( $result );

            return static::cleanRecords( $_out );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to get items from '$table' on MongoDb service.\n" . $ex->getMessage() );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordById( $table, $id, $extras = array() )
    {
        if ( empty( $id ) )
        {
            return array();
        }

        $_coll = $this->selectTable( $table );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldArray = static::buildFieldArray( $_fields );
        $_criteria = array( static::DEFAULT_ID_FIELD => static::idToMongoId( $id ) );
        // build filter string if necessary, add server-side filters if necessary
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_serverCriteria = $this->buildSSFilterArray( $_ssFilters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }

        try
        {
            $result = $_coll->findOne( $_criteria, $_fieldArray );
            if ( empty( $result ) && is_numeric( $id ) )
            {
                // defaults to string ids, could be numeric, try that
                $id = ( $id == strval( intval( $id ) ) ) ? intval( $id ) : floatval( $id );
                $result = $_coll->findOne( array( static::DEFAULT_ID_FIELD => $id ), $_fieldArray );
            }
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to get item '$table/$id' on MongoDb service.\n" . $ex->getMessage() );
        }

        if ( empty( $result ) )
        {
            throw new NotFoundException( "Record with id '$id' was not found." );
        }

        return static::mongoIdToId( $result );
    }

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
        $_out = array();
        foreach ( $include as $key )
        {
            $_out[$key] = true;
        }

        return $_out;
    }

    /**
     * @param string|array $filter Filter for querying records by
     *
     * @return array
     */
    protected static function buildFilterArray( $filter )
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
                $_parts[] = static::buildFilterArray( $_op );
            }

            return array( '$or' => $_parts );
        }

        $_ops = array_map( 'trim', explode( ' NOR ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_parts = array();
            foreach ( $_ops as $_op )
            {
                $_parts[] = static::buildFilterArray( $_op );
            }

            return array( '$nor' => $_parts );
        }

        $_ops = array_map( 'trim', explode( ' && ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_parts = array();
            foreach ( $_ops as $_op )
            {
                $_parts[] = static::buildFilterArray( $_op );
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

        $_ops = array_map( 'trim', explode( '!=', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_val = static::_determineValue( $_ops[1] );

            return array( $_ops[0] => array( '$ne' => $_val ) );
        }

        $_ops = array_map( 'trim', explode( '>=', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_val = static::_determineValue( $_ops[1] );

            return array( $_ops[0] => array( '$gte' => $_val ) );
        }

        $_ops = array_map( 'trim', explode( '<=', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_val = static::_determineValue( $_ops[1] );

            return array( $_ops[0] => array( '$lte' => $_val ) );
        }

        $_ops = array_map( 'trim', explode( '=', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_val = static::_determineValue( $_ops[1] );

            return array( $_ops[0] => $_val );
        }

        $_ops = array_map( 'trim', explode( '>', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_val = static::_determineValue( $_ops[1] );

            return array( $_ops[0] => array( '$gt' => $_val ) );
        }

        $_ops = array_map( 'trim', explode( '<', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_val = static::_determineValue( $_ops[1] );

            return array( $_ops[0] => array( '$lt' => $_val ) );
        }

        $_ops = array_map( 'trim', explode( ' IN ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_val = static::_determineValue( $_ops[1] );

            return array( $_ops[0] => array( '$in' => $_val ) );
        }

        $_ops = array_map( 'trim', explode( ' NIN ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_val = static::_determineValue( $_ops[1] );

            return array( $_ops[0] => array( '$nin' => $_val ) );
        }

        $_ops = array_map( 'trim', explode( ' ALL ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_val = static::_determineValue( $_ops[1] );

            return array( $_ops[0] => array( '$all' => $_val ) );
        }

        $_ops = array_map( 'trim', explode( ' LIKE ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
//			WHERE name LIKE "%Joe%"	find(array("name" => new MongoRegex("/Joe/")));
//			WHERE name LIKE "Joe%"	find(array("name" => new MongoRegex("/^Joe/")));
//			WHERE name LIKE "%Joe"	find(array("name" => new MongoRegex("/Joe$/")));
            $_val = static::_determineValue( $_ops[1] );
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

            return array( $_ops[0] => new \MongoRegex( $_val ) );
        }

        return $filter;
    }

    /**
     * @param $value
     *
     * @return bool|float|int|string
     */
    private static function _determineValue( $value )
    {
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

    protected static function buildCriteriaArray( $filter, $ss_filters = null )
    {
        // build filter string if necessary, add server-side filters if necessary
        $_criteria = static::buildFilterArray( $filter );
        $_serverCriteria = static::buildSSFilterArray( $ss_filters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( '$and' => array( $_criteria, $_serverCriteria ) ) : $_serverCriteria;
        }

        return $_criteria;
    }

    protected static function buildSSFilterArray( $ss_filters )
    {
        if ( !empty( $ss_filters ) )
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

        return array( $_combiner => $_criteria );
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
        if ( empty( $id_field ) )
        {
            $id_field = static::DEFAULT_ID_FIELD;
        }
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
                    catch ( \Exception $ex )
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
                    catch ( \Exception $ex )
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
}