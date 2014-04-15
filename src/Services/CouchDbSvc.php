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
use DreamFactory\Platform\Resources\User\Session;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * CouchDbSvc.php
 *
 * A service to handle Amazon Web Services DynamoDb NoSQL (schema-less) database
 * services accessed through the REST API.
 */
class CouchDbSvc extends NoSqlDbSvc
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default record identifier field
     */
    const DEFAULT_ID_FIELD = '_id';
    /**
     * Define record id field
     */
    const ID_FIELD = '_id';
    /**
     * Define record revision field
     */
    const REV_FIELD = '_rev';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var \couchClient|null
     */
    protected $_dbConn = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new CouchDbSvc
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
        $_dsn = Option::get( $_credentials, 'dsn' );
        if ( empty( $_dsn ) )
        {
            $_dsn = 'http://localhost:5984';
        }

        try
        {
            $this->_dbConn = new \couchClient( $_dsn, 'default' );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "CouchDb Service Exception:\n{$_ex->getMessage()}" );
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
     * @return string
     */
    public function selectTable( $name )
    {
        $this->checkConnection();
        $this->_dbConn->useDatabase( $name );

        return $name;
    }

    /**
     * @param null $post_data
     *
     * @return array
     */
    protected function _gatherExtrasFromRequest( $post_data = null )
    {
        $_extras = parent::_gatherExtrasFromRequest( $post_data );
        $_extras[static::REV_FIELD] = FilterInput::request( static::REV_FIELD, Option::get( $post_data, static::REV_FIELD ) );

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
            $_result = $this->_dbConn->listDatabases();
            foreach ( $_result as $_table )
            {
                if ( '_' != substr( $_table, 0, 1 ) )
                {
                    $_access = $this->getPermissions( $_table );
                    if ( !empty( $_access ) )
                    {
                        $_resources[] = array( 'name' => $_table, 'access' => $_access );
                    }
                }
            }

            return array( 'resource' => $_resources );
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
            $_existing = $this->_dbConn->listDatabases();
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
            $this->selectTable( $_name );
            $_out = $this->_dbConn->asArray()->getDatabaseInfos();
            $_out['name'] = $_name;
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
            $this->selectTable( $_name );
            $_result = $this->_dbConn->asArray()->createDatabase();

            // $_result['ok'] = true
            $_out = array( 'name' => $_name );

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
            $this->selectTable( $_name );
            $_result = $this->_dbConn->asArray()->deleteDatabase();

            // $_result['ok'] = true

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
    public function updateRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
 		$this->selectTable( $table );

        // retrieve records to get latest rev and id
        $_results = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
        // make sure record doesn't contain identifiers
        unset( $record[static::DEFAULT_ID_FIELD] );
        unset( $record[static::REV_FIELD] );

        $_updates = array();
        foreach ( $_results as $result )
        {
            $_updates[] = array_merge( $result, $record );
        }

        return $this->updateRecords( $table, $_updates, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $table = $this->selectTable( $table );

        $_fields = Option::get( $extras, 'fields' );
        try
        {
            // get all fields of each record
            $_merges = $this->retrieveRecordsByFilter( $table, $filter, '*', $extras );
            // merge in changes from $records to $_merges
            unset( $record[static::DEFAULT_ID_FIELD] );
            unset( $record[static::REV_FIELD] );
            foreach ( $_merges as $_key => $_merge )
            {
                $_merges[$_key] = array_merge( $_merge, $record );
            }
            // write back the changes
            $result = $this->_dbConn->asArray()->storeDocs( $_merges, true );
            $_out = static::cleanRecords( $result, $_fields );
            if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
            {
                // merge in rev updates
                $_merges = static::recordArrayMerge( $_merges, $_out );

                return static::cleanRecords( $_merges, $_fields );
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to patch records in '$table'.\n{$_ex->getMessage()}" );
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

        $table = $this->selectTable( $table );
        try
        {
            $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
            $results = $this->_dbConn->asArray()->deleteDocs( $_records, true );

            return $_records;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() )
    {
        $this->selectTable( $table );
        $_fields = Option::get( $extras, 'fields' );

        $_moreFields = static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD );
        try
        {
            // todo  how to filter here?
            $result = $this->_dbConn->asArray()->include_docs( $_moreFields )->getAllDocs();
            $_rows = Option::get( $result, 'rows' );
            $_out = static::cleanRecords( $_rows, $_fields, $_moreFields );

            return $_out;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to filter items from '$table' on CouchDb service.\n" . $ex->getMessage() );
        }
    }

    protected function getIdsInfo( $table, $fields_info = null, &$requested = null )
    {
        $requested = array( static::ID_FIELD, static::REV_FIELD ); // can only be this
        $_ids = array(
            array( 'name' => static::ID_FIELD, 'type' => 'string', 'required' => true ),
            array( 'name' => static::REV_FIELD, 'type' => 'string', 'required' => false )
        );

        return $_ids;
    }

    /**
     * @param array        $record
     * @param string|array $include List of keys to include in the output record
     *
     * @return array
     */
    protected static function cleanRecord( $record = array(), $include = '*' )
    {
        if ( '*' !== $include )
        {
            $_id = Option::get( $record, static::DEFAULT_ID_FIELD );
            if ( empty( $_id ) )
            {
                $_id = Option::get( $record, 'id' );
            }
            $_rev = Option::get( $record, static::REV_FIELD );
            if ( empty( $_rev ) )
            {
                $_rev = Option::get( $record, 'rev' );
                if ( empty( $_rev ) )
                {
                    $_rev = Option::getDeep( $record, 'value', 'rev' );
                }
            }
            $_out = array( static::DEFAULT_ID_FIELD => $_id, static::REV_FIELD => $_rev );

            if ( empty( $include ) )
            {
                return $_out;
            }
            if ( !is_array( $include ) )
            {
                $include = array_map( 'trim', explode( ',', trim( $include, ',' ) ) );
            }
            foreach ( $include as $key )
            {
                if ( 0 == strcasecmp( $key, static::DEFAULT_ID_FIELD ) || 0 == strcasecmp( $key, static::REV_FIELD ) )
                {
                    continue;
                }
                $_out[$key] = Option::get( $record, $key );
            }

            return $_out;
        }

        return $record;
    }

    protected static function cleanRecords( $records, $include, $use_doc = false )
    {
        $_out = array();
        foreach ( $records as $_record )
        {
            if ( $use_doc )
            {
                $_record = Option::get( $_record, 'doc', $_record );
            }
            $_out[] = static::cleanRecord( $_record, $include, static::DEFAULT_ID_FIELD );
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    protected function initTransaction( $handle = null )
    {
        $this->selectTable( $handle );

        return parent::initTransaction( $handle );
    }

    /**
     * {@inheritdoc}
     */
    protected function addToTransaction( $record = null, $id = null, $extras = null, $rollback = false, $continue = false, $single = false )
    {
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_fields = Option::get( $extras, 'fields' );
        $_fieldsInfo = Option::get( $extras, 'fields_info' );
        $_requireMore = Option::get( $extras, 'require_more' );
        $_updates = Option::get( $extras, 'updates' );

        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
                $record = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                if ( !$continue && !$rollback )
                {
                    return parent::addToTransaction( $record, $id );
                }

                $_result = $this->_dbConn->asArray()->storeDoc( (object)$record );

                $_out = static::cleanRecord( $_result, $_fields, static::DEFAULT_ID_FIELD );
                if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
                {
                    // for returning latest _rev
                    $_out = static::recordArrayMerge( $record, $_out );
                }

                if ( $rollback )
                {
                    $this->addToRollback( static::recordAsId( $_result, static::DEFAULT_ID_FIELD ) );
                }
                break;

            case static::PUT:
                if ( !empty( $_updates ) )
                {
                    $_parsed = $this->parseRecord( $_updates, $_fieldsInfo, $_ssFilters, true );
                    $_updates = $_parsed;
                }
                else
                {
                    $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );
                }
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                // only update/patch by ids can use batching
                if ( !$continue && !$rollback && !empty( $_updates ) )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_result = $this->_dbConn->asArray()->storeDoc( (object)$record );
                $_out = static::cleanRecord( $_result, $_fields, static::DEFAULT_ID_FIELD );
                if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
                {
                    $_out = static::recordArrayMerge( $record, $_out );
                }

                // by id
                // retrieve record to get latest rev and id
                $_result = $this->retrieveRecordById( $this->_transactionTable, $id, $extras );
                // make sure record doesn't contain identifiers
                unset( $record[static::DEFAULT_ID_FIELD] );
                unset( $record[static::REV_FIELD] );

                $_update = array_merge( $_result, $record );
                $_result = $this->_dbConn->asArray()->storeDoc( (object)$_update );

                if ( $rollback )
                {
                    $this->addToRollback( $_result );
                }
                break;

            case static::MERGE:
            case static::PATCH:
                if ( !empty( $_updates ) )
                {
                    $_parsed = $this->parseRecord( $_updates, $_fieldsInfo, $_ssFilters, true );
                    $_updates = $_parsed;
                }
                else
                {
                    $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );
                }
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                // only update/patch by ids can use batching
                if ( !$continue && !$rollback && !empty( $_updates ) )
                {
                    return parent::addToTransaction( null, $id );
                }

                // get all fields of record
                $_merge = $this->_dbConn->asArray()->getDoc( $id );
                // merge in changes from $record to $_merge
                $_merge = array_merge( $_merge, $record );
                // write back the changes
                $_result = $this->_dbConn->asArray()->storeDoc( (object)$_merge );
                $_out = static::cleanRecord( $_result, $_fields, static::DEFAULT_ID_FIELD );
                if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
                {
                    // merge in rev updates
                    $_merge[static::REV_FIELD] = Option::get( $_out, static::REV_FIELD );

                    return static::cleanRecord( $_merge, $_fields, static::DEFAULT_ID_FIELD );
                }
                break;

            case static::DELETE:
                if ( !$continue && !$rollback )
                {
                    return parent::addToTransaction( null, $id );
                }

                if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
                {
                    $_result = $this->_dbConn->asArray()->getDoc( $id );
                    $_out = static::cleanRecord( $record, $_fields, static::DEFAULT_ID_FIELD );
                }
                if ( $rollback )
                {
                    $this->addToRollback( $_result );
                    $_out = static::cleanRecord( $record, $_fields, static::DEFAULT_ID_FIELD );
                }
                $_result = $this->_dbConn->asArray()->deleteDoc( (object)$record );
                if ( empty( $_out ) )
                {
                    $_out = static::cleanRecord( $_result, $_fields, static::DEFAULT_ID_FIELD );
                }
                if ( $rollback )
                {
                    $this->addToRollback( $_result );
                    $_out = static::cleanRecord( $record, $_fields, static::DEFAULT_ID_FIELD );
                }
                break;

            case static::GET:
                $_result = $this->_dbConn->asArray()->getDoc( $id );

                $_out = static::cleanRecord( $_result, $_fields, static::DEFAULT_ID_FIELD );

                return parent::addToTransaction( null, $id );
//                $_filter = array( static::DEFAULT_ID_FIELD => $id );
//                $_criteria = $this->buildCriteriaArray( $_filter, null, $_ssFilters );
//                $_result = $this->_collection->findOne( $_criteria, $_fieldArray );
//                if ( empty( $_result ) )
//                {
//                    throw new NotFoundException( "Record with id '" . static::mongoIdToId( $id ) . "' not found." );
//                }
//
//                $_out = static::mongoIdToId( $_result );
//                break;
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    protected function commitTransaction( $extras = null )
    {
        if ( empty( $this->_batchRecords ) && empty( $this->_batchIds ) )
        {
            return null;
        }

        $_updates = Option::get( $extras, 'updates' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_fields = Option::get( $extras, 'fields' );
        $_requireMore = Option::get( $extras, 'require_more' );

        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
                $result = $this->_dbConn->asArray()->storeDocs( $this->_batchRecords, $_rollback );
                $_out = static::cleanRecords( $result, $_fields );
                if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
                {
                    return $this->retrieveRecords( $table, $_out, $extras );
                }
                $_result = $this->_collection->batchInsert( $this->_batchRecords, array( 'continueOnError' => false ) );
                static::processResult( $_result );

                $_out = static::cleanRecords( $this->_batchRecords, $_fields );
                break;
            case static::PUT:
                $result = $this->_dbConn->asArray()->storeDocs( $records, $_rollback );
                $_out = static::cleanRecords( $result, $_fields );
                if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
                {
                    // merge in rev info
                    $_out = static::recordArrayMerge( $records, $_out );
                }

                // by ids
                // retrieve records to get latest rev and id
                $_results = $this->retrieveRecordsByIds( $table, $ids, $extras );
                // make sure record doesn't contain identifiers
                unset( $record[static::DEFAULT_ID_FIELD] );
                unset( $record[static::REV_FIELD] );

                $_updates = array();
                foreach ( $_results as $_result )
                {
                    $_updates[] = array_merge( $_result, $record );
                }

                return $this->updateRecords( $table, $_updates, $extras );

                if ( empty( $_updates ) )
                {
                    throw new BadRequestException( 'Batch operation not supported for update by records.' );
                }

                $_filter = array( static::DEFAULT_ID_FIELD => array( '$in' => $this->_batchIds ) );
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );

                $_result = $this->_collection->update( $_criteria, $_updates, null, array( 'multiple' => true ) );
                $_rows = static::processResult( $_result );
                if ( 0 === $_rows )
                {
                    throw new NotFoundException( 'No requested records were found to update.' );
                }

                if ( count( $this->_batchIds ) !== $_rows )
                {
                    throw new BadRequestException( 'Batch Error: Not all requested records were found to update.' );
                }

                if ( $_requireMore )
                {
                    $_fieldArray = static::buildFieldArray( $_fields );
                    /** @var \MongoCursor $_result */
                    $_result = $this->_collection->find( $_criteria, $_fieldArray );
                    $_out = static::cleanRecords( iterator_to_array( $_result ) );
                }
                else
                {
                    $_out = static::idsAsRecords( static::mongoIdsToIds( $this->_batchIds ), static::DEFAULT_ID_FIELD );
                }
                break;

            case static::MERGE:
            case static::PATCH:
                // get all fields of each record
                $_merges = $this->retrieveRecords( $table, $records, '*', $extras );
                // merge in changes from $records to $_merges
                $_merges = static::recordArrayMerge( $_merges, $records );
                // write back the changes
                $_result = $this->_dbConn->asArray()->storeDocs( $_merges, $_rollback );
                $_out = static::cleanRecords( $_result, $_fields );
                if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
                {
                    // merge in rev updates
                    $_merges = static::recordArrayMerge( $_merges, $_out );
                    $_out = static::cleanRecords( $_merges, $_fields );
                }

                if ( empty( $_updates ) )
                {
                    throw new BadRequestException( 'Batch operation not supported for patch by records.' );
                }

                $_updates = array( '$set' => $_updates );

                $_filter = array( static::DEFAULT_ID_FIELD => array( '$in' => $this->_batchIds ) );
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );

                $_result = $this->_collection->update( $_criteria, $_updates, array( 'multiple' => true ) );
                $_rows = static::processResult( $_result );
                if ( 0 === $_rows )
                {
                    throw new NotFoundException( 'No requested records were found to patch.' );
                }

                if ( count( $this->_batchIds ) !== $_rows )
                {
                    throw new BadRequestException( 'Batch Error: Not all requested records were found to patch.' );
                }

                if ( $_requireMore )
                {
                    $_fieldArray = static::buildFieldArray( $_fields );
                    /** @var \MongoCursor $_result */
                    $_result = $this->_collection->find( $_criteria, $_fieldArray );
                    $_out = static::cleanRecords( iterator_to_array( $_result ) );
                }
                else
                {
                    $_out = static::idsAsRecords( static::mongoIdsToIds( $this->_batchIds ), static::DEFAULT_ID_FIELD );
                }
                break;

            case static::DELETE:
                $_out = array();
                if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
                {
                    $_out = $this->retrieveRecords( $table, $records, $_fields, $extras );
                }

                $result = $this->_dbConn->asArray()->deleteDocs( $records, $_rollback );
                if ( empty( $_out ) )
                {
                    $_out = static::cleanRecords( $result, $_fields );;
                }

                $_filter = array( static::DEFAULT_ID_FIELD => array( '$in' => $this->_batchIds ) );
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );

                if ( $_requireMore )
                {
                    $_fieldArray = static::buildFieldArray( $_fields );
                    /** @var \MongoCursor $_result */
                    $_result = $this->_collection->find( $_criteria, $_fieldArray );
                    $_out = static::cleanRecords( iterator_to_array( $_result ) );
                }
                else
                {
                    $_out = static::idsAsRecords( static::mongoIdsToIds( $this->_batchIds ), static::DEFAULT_ID_FIELD );
                }

                $_result = $this->_collection->remove( $_criteria );
                $_rows = static::processResult( $_result );
                if ( 0 === $_rows )
                {
                    throw new NotFoundException( 'No requested ids were found to delete.' );
                }
                if ( count( $this->_batchIds ) !== $_rows )
                {
                    throw new BadRequestException( 'Batch Error: Not all requested ids were found to delete.' );
                }
                break;

            case static::GET:
                $result = $this->_dbConn->asArray()->include_docs( $_moreFields )->keys( $ids )->getAllDocs();
                $_rows = Option::get( $result, 'rows' );
                $_out = static::cleanRecords( $_rows, $_fields, $_moreFields );

                $_filter = array( static::DEFAULT_ID_FIELD => array( '$in' => $this->_batchIds ) );
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );
                $_fieldArray = static::buildFieldArray( $_fields );
                /** @var \MongoCursor $_result */
                $_result = $this->_collection->find( $_criteria, $_fieldArray );
                $_out = static::cleanRecords( iterator_to_array( $_result ) );
                if ( count( $this->_batchIds ) !== count( $_out ) )
                {
                    throw new BadRequestException( 'Batch Error: Not all requested ids were found to retrieve.' );
                }
                break;

            default:
                break;
        }

        $this->_batchIds = array();
        $this->_batchRecords = array();

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    protected function addToRollback( $record )
    {
        return parent::addToRollback( $record );
    }

    /**
     * {@inheritdoc}
     */
    protected function rollbackTransaction()
    {
        if ( !empty( $this->_rollbackRecords ) )
        {
            switch ( $this->getAction() )
            {
                case static::POST:
                    // should be ids here from creation
                    $_filter = array( static::DEFAULT_ID_FIELD => array( '$in' => $this->_rollbackRecords ) );
                    $this->_collection->remove( $_filter );
                    break;

                case static::PUT:
                case static::PATCH:
                case static::MERGE:
                case static::DELETE:
                    foreach ( $this->_rollbackRecords as $_record )
                    {
                        $this->_collection->save( $_record );
                    }
                    break;

                default:
                    break;
            }

            $this->_rollbackRecords = array();
        }

        return true;
    }
}
