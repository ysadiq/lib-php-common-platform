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

        $_credentials = Session::replaceLookup( Option::get( $config, 'credentials' ));
        $_dsn = Session::replaceLookup( Option::get( $_credentials, 'dsn' ));
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
            $this->_dbConn->asArray()->createDatabase();
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
            $this->_dbConn->asArray()->deleteDatabase();

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

        $_fields = Option::get( $extras, 'fields' );

        // retrieve records to get latest rev and id
        $_results = $this->_dbConn->asArray()->include_docs( false )->getAllDocs();

        $_updates = array();
        foreach ( $_results as $_old )
        {
            // replace everything but the ids
            $record[static::DEFAULT_ID_FIELD] = Option::get( $_old, static::DEFAULT_ID_FIELD );
            $record[static::REV_FIELD] = Option::get( $_old, static::REV_FIELD );
            $_updates[] = $record;
        }

        try
        {
            // write back the changes
            $_result = $this->_dbConn->asArray()->storeDocs( $_updates, true );

            if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
            {
                // merge in rev updates
                $_result = static::recordArrayMerge( $_updates, $_result );
            }

            $_out = static::cleanRecords( $_result, $_fields );

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
    public function mergeRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $this->selectTable( $table );

        $_fields = Option::get( $extras, 'fields' );
        try
        {
            // retrieve records to get latest rev and id
            $_results = $this->_dbConn->asArray()->include_docs( true )->getAllDocs();

            // merge in changes from $records to $_merges
            unset( $record[static::DEFAULT_ID_FIELD] );
            unset( $record[static::REV_FIELD] );

            $_updates = array();
            foreach ( $_results as $_old )
            {
                $_updates[] = array_merge( $_old, $record );
            }

            // write back the changes
            $_result = $this->_dbConn->asArray()->storeDocs( $_updates, true );
            if ( static::_requireMoreFields( $_fields, static::DEFAULT_ID_FIELD ) )
            {
                // merge in rev updates
                $_result = static::recordArrayMerge( $_updates, $_result );
            }

            $_out = static::cleanRecords( $_result, $_fields );

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

        $this->selectTable( $table );
        try
        {
            $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
            $this->_dbConn->asArray()->deleteDocs( $_records, true );

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
            $_result = $this->_dbConn->asArray()->include_docs( $_moreFields )->getAllDocs();
            $_rows = Option::get( $_result, 'rows' );
            $_out = static::cleanRecords( $_rows, $_fields, $_moreFields );

            return $_out;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to filter items from '$table'.\n" . $ex->getMessage() );
        }
    }

    protected function getIdsInfo( $table, $fields_info = null, &$requested = null )
    {
        $requested = array( static::ID_FIELD ); // can only be this
        $_ids = array(
            array( 'name' => static::ID_FIELD, 'type' => 'string', 'required' => true ),
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

                if ( $rollback )
                {
                    return parent::addToTransaction( $record, $id );
                }

                $_result = $this->_dbConn->asArray()->storeDoc( (object)$record );

                if ( $_requireMore )
                {
                    // for returning latest _rev
                    $_result = array_merge( $record, $_result );
                }

                $_out = static::cleanRecord( $_result, $_fields );
                break;

            case static::PUT:
                if ( !empty( $_updates ) )
                {
                    // make sure record doesn't contain identifiers
                    unset( $_updates[static::DEFAULT_ID_FIELD] );
                    unset( $_updates[static::REV_FIELD] );
                    $_parsed = $this->parseRecord( $_updates, $_fieldsInfo, $_ssFilters, true );
                    if ( empty( $_parsed ) )
                    {
                        throw new BadRequestException( 'No valid fields were found in record.' );
                    }
                }

                if ( $rollback )
                {
                    return parent::addToTransaction( $record, $id );
                }

                if ( !empty( $_updates ) )
                {
                    $record = $_updates;
                }

                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                $_old = null;
                if ( !isset( $record[static::REV_FIELD] ) || $rollback )
                {
                    // unfortunately we need the rev, so go get the latest
                    $_old = $this->_dbConn->asArray()->getDoc( $id );
                    $record[static::REV_FIELD] = Option::get( $_old, static::REV_FIELD );
                }

                $_result = $this->_dbConn->asArray()->storeDoc( (object)$record );

                if ( $rollback )
                {
                    // keep the new rev
                    $_old = array_merge( $_old, $_result );
                    $this->addToRollback( $_old );
                }

                if ( $_requireMore )
                {
                    $_result = array_merge( $record, $_result );
                }

                $_out = static::cleanRecord( $_result, $_fields );
                break;

            case static::MERGE:
            case static::PATCH:
                if ( !empty( $_updates ) )
                {
                    $record = $_updates;
                }

                // make sure record doesn't contain identifiers
                unset( $record[static::DEFAULT_ID_FIELD] );
                unset( $record[static::REV_FIELD] );
                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                // only update/patch by ids can use batching
                if ( !$single && !$continue && !$rollback )
                {
                    return parent::addToTransaction( $_parsed, $id );
                }

                // get all fields of record
                $_old = $this->_dbConn->asArray()->getDoc( $id );

                // merge in changes from $record to $_merge
                $record = array_merge( $_old, $record );
                // write back the changes
                $_result = $this->_dbConn->asArray()->storeDoc( (object)$record );

                if ( $rollback )
                {
                    // keep the new rev
                    $_old = array_merge( $_old, $_result );
                    $this->addToRollback( $_old );
                }

                if ( $_requireMore )
                {
                    $_result = array_merge( $record, $_result );
                }

                $_out = static::cleanRecord( $_result, $_fields );
                break;

            case static::DELETE:
                if ( !$single && !$continue && !$rollback )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_old = $this->_dbConn->asArray()->getDoc( $id );

                if ( $rollback )
                {
                    $this->addToRollback( $_old );
                }

                $this->_dbConn->asArray()->deleteDoc( (object)$record );

                $_out = static::cleanRecord( $_old, $_fields );
                break;

            case static::GET:
                if ( !$single )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_result = $this->_dbConn->asArray()->getDoc( $id );

                $_out = static::cleanRecord( $_result, $_fields );

                break;
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

        $_fields = Option::get( $extras, 'fields' );
        $_requireMore = Option::getBool( $extras, 'require_more' );

        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
                $_result = $this->_dbConn->asArray()->storeDocs( $this->_batchRecords, true );
                if ( $_requireMore )
                {
                    $_result = static::recordArrayMerge( $this->_batchRecords, $_result );
                }

                $_out = static::cleanRecords( $_result, $_fields );
                break;

            case static::PUT:
                $_result = $this->_dbConn->asArray()->storeDocs( $this->_batchRecords, true );
                if ( $_requireMore )
                {
                    $_result = static::recordArrayMerge( $this->_batchRecords, $_result );
                }

                $_out = static::cleanRecords( $_result, $_fields );
                break;

            case static::MERGE:
            case static::PATCH:
                $_result = $this->_dbConn->asArray()->storeDocs( $this->_batchRecords, true );
                if ( $_requireMore )
                {
                    $_result = static::recordArrayMerge( $this->_batchRecords, $_result );
                }

                $_out = static::cleanRecords( $_result, $_fields );
                break;

            case static::DELETE:
                $_out = array();
                if ( $_requireMore )
                {
                    $_result = $this->_dbConn->asArray()->include_docs( true )->keys( $this->_batchIds )->getAllDocs();
                    $_rows = Option::get( $_result, 'rows' );
                    $_out = static::cleanRecords( $_rows, $_fields, true );
                }

                $_result = $this->_dbConn->asArray()->deleteDocs( $this->_batchRecords, true );
                if ( empty( $_out ) )
                {
                    $_out = static::cleanRecords( $_result, $_fields );
                }
                break;

            case static::GET:
                $_result = $this->_dbConn->asArray()->include_docs( $_requireMore )->keys( $this->_batchIds )->getAllDocs();
                $_rows = Option::get( $_result, 'rows' );
                $_out = static::cleanRecords( $_rows, $_fields, true );

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
                    $this->_dbConn->asArray()->deleteDocs( $this->_rollbackRecords, true );
                    break;

                case static::PUT:
                case static::PATCH:
                case static::MERGE:
                case static::DELETE:
                    $this->_dbConn->asArray()->storeDocs( $this->_rollbackRecords, true );
                    break;
                default:
                    // nothing to do here, rollback handled on bulk calls
                    break;
            }

            $this->_rollbackRecords = array();
        }

        return true;
    }
}
