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

use Aws\Common\Enum\Region;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Enum\ComparisonOperator;
use Aws\DynamoDb\Enum\KeyType;
use Aws\DynamoDb\Enum\ReturnValue;
use Aws\DynamoDb\Enum\Type;
use Aws\DynamoDb\Model\Attribute;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Resources\User\Session;
use Kisma\Core\Utility\Option;

/**
 * AwsDynamoDbSvc.php
 *
 * A service to handle Amazon Web Services DynamoDb NoSQL (schema-less) database
 * services accessed through the REST API.
 */
class AwsDynamoDbSvc extends NoSqlDbSvc
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const TABLE_INDICATOR = 'TableName';

    const DEFAULT_REGION = Region::US_WEST_1;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var DynamoDbClient|null
     */
    protected $_dbConn = null;
    /**
     * @var array
     */
    protected $_defaultCreateTable = array(
        'AttributeDefinitions'  => array(
            array(
                'AttributeName' => 'id',
                'AttributeType' => Type::S
            )
        ),
        'KeySchema'             => array(
            array(
                'AttributeName' => 'id',
                'KeyType'       => KeyType::HASH
            )
        ),
        'ProvisionedThroughput' => array(
            'ReadCapacityUnits'  => 10,
            'WriteCapacityUnits' => 20
        )
    );

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new AwsDynamoDbSvc
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
        $_parameters = Option::get( $config, 'parameters' );

        // old way
        $_accessKey = Option::get( $_credentials, 'access_key' );
        $_secretKey = Option::get( $_credentials, 'secret_key' );
        if ( !empty( $_accessKey ) )
        {
            // old way, replace with 'key'
            $_credentials['key'] = $_accessKey;
        }

        if ( !empty( $_secretKey ) )
        {
            // old way, replace with 'key'
            $_credentials['secret'] = $_secretKey;
        }

        $_region = Option::get( $_credentials, 'region' );
        if ( empty( $_region ) )
        {
            // use a default region if not present
            $_credentials['region'] = static::DEFAULT_REGION;
        }

        // set up a default partition key
        if ( null !== ( $_table = Option::get( $_parameters, 'default_create_table' ) ) )
        {
            $this->_defaultCreateTable = $_table;
        }

        try
        {
            $this->_dbConn = DynamoDbClient::factory( $_credentials );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Amazon DynamoDb Service Exception:\n{$_ex->getMessage()}" );
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
        if ( empty( $this->_dbConn ) )
        {
            throw new InternalServerErrorException( 'Database connection has not been initialized.' );
        }
    }

    /**
     * @param $name
     *
     * @return string
     */
    public function correctTableName( $name )
    {
        return $name;
    }

    protected function _getTablesAsArray()
    {
        $_out = array();
        do
        {
            $_result = $this->_dbConn->listTables(
                array(
                    'Limit'                   => 100, // arbitrary limit
                    'ExclusiveStartTableName' => isset( $_result ) ? $_result['LastEvaluatedTableName'] : null
                )
            );

            $_out = array_merge( $_out, $_result['TableNames'] );
        }
        while ( $_result['LastEvaluatedTableName'] );

        return $_out;
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
            $_result = $this->_getTablesAsArray();
            foreach ( $_result as $_table )
            {
                $_access = $this->getPermissions( $_table );
                if ( !empty( $_access ) )
                {
                    $_resources[] = array( 'name' => $_table, 'access' => $_access, static::TABLE_INDICATOR => $_table );
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
            $_existing = $this->_getTablesAsArray();
        }

        $_name = ( is_array( $table ) ) ? Option::get( $table, 'name', Option::get( $table, static::TABLE_INDICATOR ) ) : $table;
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
            $_result = $this->_dbConn->describeTable(
                array(
                    static::TABLE_INDICATOR => $_name
                )
            );

            // The result of an operation can be used like an array
            $_out = $_result['Table'];
            $_out['name'] = $table;
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
        $_name = Option::get( $properties, 'name', Option::get( $properties, static::TABLE_INDICATOR ) );
        if ( empty( $_name ) )
        {
            throw new BadRequestException( "No 'name' field in data." );
        }

        try
        {
            $_properties = array_merge(
                array( static::TABLE_INDICATOR => $_name ),
                $this->_defaultCreateTable,
                $properties
            );
            $_result = $this->_dbConn->createTable( $_properties );

            // Wait until the table is created and active
            $this->_dbConn->waitUntilTableExists(
                array(
                    static::TABLE_INDICATOR => $_name
                )
            );

            $_out = array_merge( array( 'name' => $_name ), $_result['TableDescription'] );

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
        $_name = Option::get( $properties, 'name', Option::get( $properties, static::TABLE_INDICATOR ) );
        if ( empty( $_name ) )
        {
            throw new BadRequestException( "No 'name' field in data." );
        }

        try
        {
            // Update the provisioned throughput capacity of the table
            $_properties = array_merge(
                array( static::TABLE_INDICATOR => $_name ),
                $properties
            );
            $_result = $this->_dbConn->updateTable( $_properties );

            // Wait until the table is active again after updating
            $this->_dbConn->waitUntilTableExists(
                array(
                    static::TABLE_INDICATOR => $_name
                )
            );

            return array_merge( array( 'name' => $_name ), $_result['TableDescription'] );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to update table '$_name'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTable( $table, $check_empty = false )
    {
        $_name = ( is_array( $table ) ) ? Option::get( $table, 'name', Option::get( $table, static::TABLE_INDICATOR ) ) : $table;
        if ( empty( $_name ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        try
        {
            $_result = $this->_dbConn->deleteTable(
                array(
                    static::TABLE_INDICATOR => $_name
                )
            );

            $this->_dbConn->waitUntilTableNotExists(
                array(
                    static::TABLE_INDICATOR => $_name
                )
            );

            return array_merge( array( 'name' => $_name ), $_result['TableDescription'] );
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
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $records ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_useBatch = Option::getBool( $extras, 'batch', false );

        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
        $_idType = $_info['types'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

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
                    if ( !$this->_containsIdFields( $_record, $_idField ) )
                    {
                        // can we auto create an id here?
                        throw new BadRequestException( "Identifying field(s) not found in record." );
                    }

                    $_parsed = $this->parseRecord( $_record, $_fieldInfo, $_ssFilters );
                    if ( empty( $_parsed ) )
                    {
                        throw new BadRequestException( "No valid fields found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_item = $this->_dbConn->formatAttributes( $_parsed );

                    if ( $_useBatch )
                    {
                        // WARNING: no validation that id doesn't exist is done via batching!
                        // Add operation to list of batch operations.
                        $_batched[] = array( 'PutRequest' => array( 'Item' => $_item ) );
                        continue;
                    }

                    // simple insert request
                    /*$_result = */
                    $this->_dbConn->putItem(
                        array(
                            static::TABLE_INDICATOR => $table,
                            'Item'      => $_item,
                            'Expected'  => array( $_idField[0] => array( 'Exists' => false ) )
                        )
                    );

                    if ( $_rollback )
                    {
                        $_key = static::_buildKey( $_idField, $_idType, $_record );
                        $_backup[] = array( 'DeleteRequest' => array( 'Key' => $_key ) );
                    }

                    $_out[$_index] = static::cleanRecord( $_parsed, $_fields, $_idField );
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
                /*$_result = */
                $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $table => $_batched, ) ) );

                // todo check $_result['UnprocessedItems'] for 'PutRequest'

                $_out = static::cleanRecords( $records, $_fields, $_idField );
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
                try
                {
                    /* $_result = */
                    $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $table => $_backup, ) ) );

                    // todo check $_result['UnprocessedItems'] for 'DeleteRequest'
                }
                catch ( \Exception $_rex )
                {
                }

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
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $records ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_useBatch = Option::getBool( $extras, 'batch', false );

        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
        $_idType = $_info['types'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_out = array();
        $_errors = array();
        $_batched = array();
        $_backup = array();
        try
        {
            $_fieldInfo = array();
            $_options = ( $_rollback ) ? ReturnValue::ALL_OLD : ReturnValue::NONE;

            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( !$this->_containsIdFields( $_record, $_idField ) )
                    {
                        throw new BadRequestException( "Identifying field(s) not found in record $_index." );
                    }

                    $_parsed = $this->parseRecord( $_record, $_fieldInfo, $_ssFilters, true );
                    if ( empty( $_parsed ) )
                    {
                        throw new BadRequestException( "No valid fields found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_item = $this->_dbConn->formatAttributes( $_parsed );
                    $_expected = static::_buildKey( $_idField, $_idType, $_record );

                    if ( $_useBatch )
                    {
                        // WARNING: no validation that id doesn't exist is done via batching!
                        // Add operation to list of batch operations.
                        $_batched[] = array( 'PutRequest' => array( 'Item' => $_item ) );
                        continue;
                    }

                    // simple insert request
                    $_result = $this->_dbConn->putItem(
                        array(
                            static::TABLE_INDICATOR    => $table,
                            'Item'         => $_item,
//                            'Expected'     => $_expected,
                            'ReturnValues' => $_options
                        )
                    );

                    if ( $_rollback )
                    {
                        $_temp = Option::get( $_result, 'Attributes' );
                        if ( !empty( $_temp ) )
                        {
                            $_backup[] = array( 'PutRequest' => array( 'Item' => $_temp ) );
                        }
                    }

                    $_out[$_index] = static::cleanRecord( $_parsed, $_fields, $_idField );
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
                /*$_result = */
                $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $table => $_batched, ) ) );

                // todo check $_result['UnprocessedItems'] for 'PutRequest'

                $_out = static::cleanRecords( $records, $_fields, $_idField );
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
                try
                {
                    /*$_result = */
                    $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $table => $_backup, ) ) );

                    // todo check $_result['UnprocessedItems'] for 'PutRequest'
                }
                catch ( \Exception $_rex )
                {
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

        // slow, but workable for now, maybe faster than updating individuals
        $_retrieveExtras = array( 'fields' => '');
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $_retrieveExtras );
        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds($_records, $_idField);

        return $this->updateRecordsByIds( $table, $record, $_ids, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_useBatch = Option::getBool( $extras, 'batch', false );

        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
//        $_idType = $_info['types'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

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
            $_options = ( $_rollback ) ? ReturnValue::ALL_OLD : ReturnValue::NONE;

            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    $_parsed[$_idField[0]] = $_id;

                    $_item = $this->_dbConn->formatAttributes( $_parsed );

                    if ( $_useBatch )
                    {
                        // WARNING: no validation that id doesn't exist is done via batching!
                        // Add operation to list of batch operations.
                        $_batched[] = array( 'PutRequest' => array( 'Item' => $_item ) );
                        $_out[$_index] = static::cleanRecord( $_parsed, $_fields, $_idField );
                        continue;
                    }

                    // simple insert request
                    $_result = $this->_dbConn->putItem(
                        array(
                            static::TABLE_INDICATOR    => $table,
                            'Item'         => $_item,
//                            'Expected'     => array( $_idField[0] => array( 'Exists' => true ) ),
                            'ReturnValues' => $_options
                        )
                    );

                    if ( $_rollback )
                    {
                        $_temp = Option::get( $_result, 'Attributes' );
                        if ( !empty( $_temp ) )
                        {
                            $_backup[] = array( 'PutRequest' => array( 'Item' => $_temp ) );
                        }
                    }

                    $_out[$_index] = static::cleanRecord( $_parsed, $_fields, $_idField );
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
                /*$_result = */
                $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $table => $_batched, ) ) );

                // todo check $_result['UnprocessedItems'] for 'PutRequest'
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
                try
                {
                    /*$_result = */
                    $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $table => $_backup, ) ) );

                    // todo check $_result['UnprocessedItems'] for 'PutRequest'
                }
                catch ( \Exception $_rex )
                {
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
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $records ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
        $_idType = $_info['types'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_out = array();
        $_errors = array();
        $_backup = array();
        try
        {
            $_fieldInfo = array();
            $_options = ( $_rollback ) ? ReturnValue::ALL_OLD : ReturnValue::ALL_NEW;

            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( !$this->_containsIdFields( $_record, $_idField ) )
                    {
                        throw new BadRequestException( "Identifying field(s) not found in record $_index." );
                    }

                    $_parsed = $this->parseRecord( $_record, $_fieldInfo, $_ssFilters, true );
                    if ( empty( $_parsed ) )
                    {
                        throw new BadRequestException( "No valid fields found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_key = static::_buildKey( $_idField, $_idType, $_parsed, true );
                    $_updates = $this->_dbConn->formatAttributes( $_parsed, Attribute::FORMAT_UPDATE );

                    // simple insert request
                    $_result = $this->_dbConn->updateItem(
                        array(
                            static::TABLE_INDICATOR        => $table,
                            'Key'              => $_key,
                            'AttributeUpdates' => $_updates,
                            'ReturnValues'     => $_options
                        )
                    );

                    $_temp = Option::get( $_result, 'Attributes', array() );
                    if ( $_rollback )
                    {
                        $_backup[] = array( 'PutRequest' => array( 'Item' => $_temp ) );
                        // todo merge old record with new changes
                        $_out[$_index] = static::cleanRecord( $_parsed, $_fields, $_idField );
                    }
                    else
                    {
                        $_temp = static::_unformatAttributes( $_temp );
                        $_out[$_index] = static::cleanRecord( $_temp, $_fields, $_idField );
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
                try
                {
                    /*$_result = */
                    $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $table => $_backup, ) ) );

                    // todo check $_result['UnprocessedItems'] for 'PutRequest'
                }
                catch ( \Exception $_ex )
                {
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

        // slow, but workable for now, maybe faster than merging individuals
        $_retrieveExtras = array( 'fields' => '' );
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $_retrieveExtras );
        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds($_records, $_idField);

        return $this->mergeRecordsByIds( $table, $record, $_ids, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
        $_idType = $_info['types'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_fieldInfo = array();
        $_parsed = $this->parseRecord( $record, $_fieldInfo, $_ssFilters, true );
        if ( empty( $_parsed ) )
        {
            throw new BadRequestException( 'No valid fields found in request: ' . print_r( $record, true ) );
        }

        static::removeIds( $_parsed, $_idField );
        $_updates = $this->_dbConn->formatAttributes( $_parsed, Attribute::FORMAT_UPDATE );

        $_out = array();
        $_errors = array();
        $_backup = array();
        try
        {
            $_options = ( $_rollback ) ? ReturnValue::ALL_OLD : ReturnValue::ALL_NEW;

            foreach ( $ids as $_index => $_id )
            {
                $_temp = array( $_idField[0] => $_id );
                $_key = static::_buildKey( $_idField, $_idType, $_temp );
                try
                {
                    // simple insert request
                    $_result = $this->_dbConn->updateItem(
                        array(
                            static::TABLE_INDICATOR        => $table,
                            'Key'              => $_key,
                            'AttributeUpdates' => $_updates,
                            'ReturnValues'     => $_options
                        )
                    );

                    $_temp = Option::get( $_result, 'Attributes', array() );
                    if ( $_rollback )
                    {
                        $_backup[] = array( 'PutRequest' => array( 'Item' => $_temp ) );
                        // todo merge old record with new changes
                        $_out[$_index] = static::cleanRecord( $_parsed, $_fields, $_idField );
                    }
                    else
                    {
                        $_temp = static::_unformatAttributes( $_temp );
                        $_out[$_index] = static::cleanRecord( $_temp, $_fields, $_idField );
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
                try
                {
                    /*$_result = */
                    $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $table => $_backup, ) ) );

                    // todo check $_result['UnprocessedItems'] for 'PutRequest'
                }
                catch ( \Exception $_ex )
                {
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
        // todo faster way?
        $_records = $this->retrieveRecordsByFilter( $table, '' );

        return $this->deleteRecords( $table, $_records );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecords( $table, $records, $extras = array() )
    {
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );
        $table = $this->correctTableName( $table );

        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
//        $_idType = $_info['types'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $records, $_idField );

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

        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );

        return $this->deleteRecords( $table, $_records, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecordsByIds( $table, $ids, $extras = array() )
    {
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );
        $table = $this->correctTableName( $table );

        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = Option::getBool( $extras, 'rollback', false );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_useBatch = Option::getBool( $extras, 'batch', false );

        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
        $_idType = $_info['types'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_out = array();
        $_errors = array();
        $_batched = array();
        $_backup = array();
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    $_record = array( $_idField[0] => $_id );

                    $_key = static::_buildKey( $_idField, $_idType, $_record );

                    if ( $_useBatch )
                    {
                        // WARNING: no validation that id doesn't exist is done via batching!
                        // Add operation to list of batch operations.
                        $_batched[] = array( 'DeleteRequest' => array( 'Key' => $_key ) );
                    }

                    $_result = $this->_dbConn->deleteItem(
                        array(
                            static::TABLE_INDICATOR    => $table,
                            'Key'          => $_key,
                            'ReturnValues' => ReturnValue::ALL_OLD,
                        )
                    );

                    $_temp = Option::get( $_result, 'Attributes', array() );

                    if ( $_rollback )
                    {
                        $_backup[] = array( 'PutRequest' => array( 'Item' => $_temp ) );
                    }

                    $_temp = static::_unformatAttributes( $_temp );
                    $_out[$_index] = static::cleanRecord( $_temp, $_fields, $_idField );
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
                if ( static::_requireMoreFields( $_fields, $_idField ) )
                {
                    $_out = $this->retrieveRecordsByIds( $table, $ids, $_fields, $extras );
                }

                /*$_result = */
                $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $table => $_batched, ) ) );

                // todo check $_result['UnprocessedItems'] for 'DeleteRequest'
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
                try
                {
                    /*$_result = */
                    $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $table => $_backup, ) ) );

                    // todo check $_result['UnprocessedItems'] for 'PutRequest'
                }
                catch ( \Exception $_ex )
                {
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
        $table = $this->correctTableName( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_scanProperties = array( static::TABLE_INDICATOR => $table );

        $_parsedFilter = static::buildCriteriaArray( $filter, $params, $_ssFilters );
        if ( !empty( $_parsedFilter ) )
        {
            $_scanProperties['ScanFilter'] = $_parsedFilter;
        }

        $_fields = static::_buildAttributesToGet( $_fields );
        if ( !empty( $_fields ) )
        {
            $_scanProperties['AttributesToGet'] = $_fields;
        }

        $_limit = Option::get( $extras, 'limit' );
        if ( $_limit > 0 )
        {
            $_scanProperties['Limit'] = $_limit;
        }

        try
        {
            $_result = $this->_dbConn->scan( $_scanProperties );
            $_out = array();
            foreach ( $_result['Items'] as $_item )
            {
                $_out[] = static::_unformatAttributes( $_item );
            }

            return $_out;
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
        $table = $this->correctTableName( $table );

        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
//        $_idType = $_info['types'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $records, $_idField );

        return $this->retrieveRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByIds( $table, $ids, $extras = array() )
    {
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );
        $table = $this->correctTableName( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_info = $this->_getKeyInfo( $table, $extras );
        $_idField = $_info['fields'];
        $_idType = $_info['types'];
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_keys = array();
        foreach ( $ids as $_id )
        {
            $_record = array( $_idField[0] => $_id );
            $_key = static::_buildKey( $_idField, $_idType, $_record );
            $_keys[] = $_key;
//            $_scanProperties = array(
//                static::TABLE_INDICATOR      => $table,
//                'Key'            => $_keys,
//                'ConsistentRead' => true,
//            );
//
//            $_fields = static::_buildAttributesToGet( $_fields, $_idField );
//            if ( !empty( $_fields ) )
//            {
//                $_scanProperties['AttributesToGet'] = $_fields;
//            }
//
//            $_result = $this->_dbConn->getItem( $_scanProperties );
//
//            // Grab value from the result object like an array
//            return static::_unformatAttributes( $_result['Item'] );
        }

        $_scanProperties = array(
            'Keys'           => $_keys,
            'ConsistentRead' => true,
        );

        $_fields = static::_buildAttributesToGet( $_fields, $_idField );
        if ( !empty( $_fields ) )
        {
            $_scanProperties['AttributesToGet'] = $_fields;
        }

        try
        {
            // Get multiple items by key in a BatchGetItem request
            $_result = $this->_dbConn->batchGetItem(
                array(
                    'RequestItems' => array(
                        $table => $_scanProperties
                    )
                )
            );

            $_items = $_result->getPath( "Responses/{$table}" );
            $_out = array();
            foreach ( $_items as $_item )
            {
                $_out[] = static::_unformatAttributes( $_item );
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to get records from '$table'.\n{$_ex->getMessage()}" );
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

    protected static function _unformatValue( $value )
    {
        // represented as arrays, though there is only ever one item present
        foreach ( $value as $type => $actual )
        {
            switch ( $type )
            {
                case Type::S:
                case Type::B:
                    return $actual;
                case Type::N:
                    if ( intval( $actual ) == $actual )
                    {
                        return intval( $actual );
                    }
                    else
                    {
                        return floatval( $actual );
                    }
                case Type::SS:
                case Type::BS:
                    return $actual;
                case Type::NS:
                    $_out = array();
                    foreach ( $actual as $_item )
                    {
                        if ( intval( $_item ) == $_item )
                        {
                            $_out[] = intval( $_item );
                        }
                        else
                        {
                            $_out[] = floatval( $_item );
                        }
                    }

                    return $_out;
            }
        }

        return $value;
    }

    /**
     * @param array $record
     *
     * @return array
     */
    protected static function _unformatAttributes( $record )
    {
        $_out = array();
        foreach ( $record as $_key => $_value )
        {
            $_out[$_key] = static::_unformatValue( $_value );
        }

        return $_out;
    }

    protected static function _buildAttributesToGet( $fields = null, $id_fields = null )
    {
        if ( '*' == $fields )
        {
            return null;
        }
        if ( empty( $fields ) )
        {
            if ( empty( $id_fields ) )
            {
                return null;
            }
            if ( !is_array( $id_fields ) )
            {
                $id_fields = array_map( 'trim', explode( ',', trim( $id_fields, ',' ) ) );
            }

            return $id_fields;
        }

        if ( !is_array( $fields ) )
        {
            $fields = array_map( 'trim', explode( ',', trim( $fields, ',' ) ) );
        }

        return $fields;
    }

    protected function _getKeyInfo( $table, $extras = null )
    {
        $_fields = Option::get( $extras, 'id_field' );
        if ( !empty( $_fields ) )
        {
            if ( !is_array( $_fields ) )
            {
                $_fields = array_map( 'trim', explode( ',', trim( $_fields, ',' ) ) );
            }
            $_types = Option::get( $extras, 'id_type', Type::S );
            if ( !is_array( $_types ) )
            {
                $_types = array_map( 'trim', explode( ',', trim( $_types, ',' ) ) );
            }
            $_keyTypes = Option::get( $extras, 'id_key_type', KeyType::HASH );
            if ( !is_array( $_keyTypes ) )
            {
                $_keyTypes = array_map( 'trim', explode( ',', trim( $_keyTypes, ',' ) ) );
            }
        }
        else
        {
            $_result = $this->getTable( $table );
            $_keys = Option::get( $_result, 'KeySchema', array() );
            $_definitions = Option::get( $_result, 'AttributeDefinitions', array() );
            $_fields = array();
            $_types = array();
            $_keyTypes = array();
            foreach ( $_keys as $_key )
            {
                $_name = Option::get( $_key, 'AttributeName' );
                $_fields[] = $_name;
                $_keyTypes[] = Option::get( $_key, 'KeyType' );
                foreach ( $_definitions as $_type )
                {
                    if ( 0 == strcmp( $_name, Option::get( $_type, 'AttributeName' ) ) )
                    {
                        $_types[] = Option::get( $_type, 'AttributeType' );
                    }
                }
            }
        }

        return array( 'fields' => $_fields, 'types' => $_types, 'key_type' => $_keyTypes );
    }

    protected static function _buildKey( $fields, $types, &$record, $remove = false )
    {
        $_keys = array();
        foreach ( $fields as $_ndx => $_field )
        {
            $_value = Option::get( $record, $_field, null, $remove );
            if ( empty( $_value ) )
            {
                throw new BadRequestException( "Identifying field(s) not found in record." );
            }

            switch ( $types[$_ndx] )
            {
                case Type::N:
                    $_value = array( Type::N => strval( $_value ) );
                    break;
                default:
                    $_value = array( Type::S => $_value );
            }
            $_keys[$_field] = $_value;
        }

        return $_keys;
    }

    protected static function buildCriteriaArray( $filter, $params = null, $ss_filters = null )
    {
        // build filter array if necessary, add server-side filters if necessary
        $_criteria = ( !is_array( $filter ) ) ? static::buildFilterArray( $filter, $params ) : $filter;
        $_serverCriteria = static::buildSSFilterArray( $ss_filters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( !empty( $_criteria ) ) ? array( $_criteria, $_serverCriteria ) : $_serverCriteria;
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
                return $_criteria;
            case 'OR':
                return array( 'split' => $_criteria );
            default:
                // log and bail
                throw new InternalServerErrorException( 'Invalid server-side filter configuration detected.' );
        }
    }

    /**
     * @param string|array $filter Filter for querying records by
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
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
        $_ops = array_map( 'trim', explode( ' && ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_parts = array();
            foreach ( $_ops as $_op )
            {
                $_parts = array_merge( $_parts, static::buildFilterArray( $_op, $params ) );
            }

            return $_parts;
        }

        $_ops = array_map( 'trim', explode( ' || ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            // need to split this into multiple queries
            throw new BadRequestException( 'OR logical comparison not currently supported on DynamoDb.' );
        }

        $_ops = array_map( 'trim', explode( ' NOR ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            throw new BadRequestException( 'NOR logical comparison not currently supported on DynamoDb.' );
        }

        // handle negation operator, i.e. starts with NOT?
        if ( 0 == substr_compare( $filter, 'not ', 0, 4, true ) )
        {
            throw new BadRequestException( 'NOT logical comparison not currently supported on DynamoDb.' );
        }

        // the rest should be comparison operators
        $_search = array(
            ' eq ',
            ' ne ',
            ' <> ',
            ' gte ',
            ' lte ',
            ' gt ',
            ' lt ',
            ' in ',
            ' between ',
            ' begins_with ',
            ' contains ',
            ' not_contains ',
            ' like '
        );
        $_replace = array(
            '=',
            '!=',
            '!=',
            '>=',
            '<=',
            '>',
            '<',
            ' IN ',
            ' BETWEEN ',
            ' BEGINS_WITH ',
            ' CONTAINS ',
            ' NOT_CONTAINS ',
            ' LIKE '
        );
        $filter = trim( str_ireplace( $_search, $_replace, $filter ) );

        // Note: order matters, watch '='
        $_sqlOperators = array(
            '!=',
            '>=',
            '<=',
            '=',
            '>',
            '<',
            ' IN ',
            ' BETWEEN ',
            ' BEGINS_WITH ',
            ' CONTAINS ',
            ' NOT_CONTAINS ',
            ' LIKE '
        );
        $_dynamoOperators = array(
            ComparisonOperator::NE,
            ComparisonOperator::GE,
            ComparisonOperator::LE,
            ComparisonOperator::EQ,
            ComparisonOperator::GT,
            ComparisonOperator::LT,
            ComparisonOperator::IN,
            ComparisonOperator::BETWEEN,
            ComparisonOperator::BEGINS_WITH,
            ComparisonOperator::CONTAINS,
            ComparisonOperator::NOT_CONTAINS,
            ComparisonOperator::CONTAINS
        );

        foreach ( $_sqlOperators as $_key => $_sqlOp )
        {
            $_ops = array_map( 'trim', explode( $_sqlOp, $filter ) );
            if ( count( $_ops ) > 1 )
            {
                $_field = $_ops[0];
                $_val = static::_determineValue( $_ops[1], $params );
                $_dynamoOp = $_dynamoOperators[$_key];
                switch ( $_dynamoOp )
                {
                    case ComparisonOperator::NE:
                        if ( 0 == strcasecmp( 'null', $_ops[1] ) )
                        {
                            return array(
                                $_ops[0] => array(
                                    'ComparisonOperator' => ComparisonOperator::NOT_NULL
                                )
                            );
                        }

                        return array(
                            $_ops[0] => array(
                                'AttributeValueList' => $_val,
                                'ComparisonOperator' => $_dynamoOp
                            )
                        );

                    case ComparisonOperator::EQ:
                        if ( 0 == strcasecmp( 'null', $_ops[1] ) )
                        {
                            return array(
                                $_ops[0] => array(
                                    'ComparisonOperator' => ComparisonOperator::NULL
                                )
                            );
                        }

                        return array(
                            $_ops[0] => array(
                                'AttributeValueList' => $_val,
                                'ComparisonOperator' => ComparisonOperator::EQ
                            )
                        );

                    case ComparisonOperator::CONTAINS:
//			WHERE name LIKE "%Joe%"	use CONTAINS "Joe"
//			WHERE name LIKE "Joe%"	use BEGINS_WITH "Joe"
//			WHERE name LIKE "%Joe"	not supported
                        $_val = $_ops[1];
                        $_type = Type::S;
                        if ( trim( $_val, "'\"" ) === $_val )
                        {
                            $_type = Type::N;
                        }

                        $_val = trim( $_val, "'\"" );
                        if ( '%' == $_val[strlen( $_val ) - 1] )
                        {
                            if ( '%' == $_val[0] )
                            {
                                return array(
                                    $_ops[0] => array(
                                        'AttributeValueList' => array( $_type => trim( $_val, '%' ) ),
                                        'ComparisonOperator' => ComparisonOperator::CONTAINS
                                    )
                                );
                            }
                            else
                            {
                                throw new BadRequestException( 'ENDS_WITH currently not supported in DynamoDb.' );
                            }
                        }
                        else
                        {
                            if ( '%' == $_val[0] )
                            {
                                return array(
                                    $_ops[0] => array(
                                        'AttributeValueList' => array( $_type => trim( $_val, '%' ) ),
                                        'ComparisonOperator' => ComparisonOperator::BEGINS_WITH
                                    )
                                );
                            }
                            else
                            {
                                return array(
                                    $_ops[0] => array(
                                        'AttributeValueList' => array( $_type => trim( $_val, '%' ) ),
                                        'ComparisonOperator' => ComparisonOperator::CONTAINS
                                    )
                                );
                            }
                        }

                    default:
                        return array(
                            $_ops[0] => array(
                                'AttributeValueList' => $_val,
                                'ComparisonOperator' => $_dynamoOp
                            )
                        );
                }
            }
        }

        return $filter;
    }

    /**
     * @param string $value
     * @param array  $replacements
     *
     * @return bool|float|int|string
     */
    private static function _determineValue( $value, $replacements = null )
    {
        // process parameter replacements
        if ( is_string( $value ) && !empty( $value ) && ( ':' == $value[0] ) )
        {
            if ( isset( $replacements, $replacements[$value] ) )
            {
                $value = $replacements[$value];
            }
        }

        if ( trim( $value, "'\"" ) !== $value )
        {
            return array( array( Type::S => trim( $value, "'\"" ) ) ); // meant to be a string
        }

        if ( is_numeric( $value ) )
        {
            $value = ( $value == strval( intval( $value ) ) ) ? intval( $value ) : floatval( $value );

            return array( array( Type::N => $value ) );
        }

        if ( 0 == strcasecmp( $value, 'true' ) )
        {
            return array( array( Type::N => 1 ) );
        }

        if ( 0 == strcasecmp( $value, 'false' ) )
        {
            return array( array( Type::N => 0 ) );
        }

        return $value;
    }
}
