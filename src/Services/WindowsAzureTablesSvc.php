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
use DreamFactory\Platform\Utility\DbUtilities;
use Kisma\Core\Utility\Option;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Table\Models\BatchError;
use WindowsAzure\Table\Models\BatchOperations;
use WindowsAzure\Table\Models\BatchResult;
use WindowsAzure\Table\Models\EdmType;
use WindowsAzure\Table\Models\Entity;
use WindowsAzure\Table\Models\Filters\QueryStringFilter;
use WindowsAzure\Table\Models\GetEntityResult;
use WindowsAzure\Table\Models\GetTableResult;
use WindowsAzure\Table\Models\InsertEntityResult;
use WindowsAzure\Table\Models\Property;
use WindowsAzure\Table\Models\QueryEntitiesOptions;
use WindowsAzure\Table\Models\QueryEntitiesResult;
use WindowsAzure\Table\Models\QueryTablesResult;
use WindowsAzure\Table\Models\UpdateEntityResult;
use WindowsAzure\Table\TableRestProxy;

/**
 * WindowsAzureTablesSvc.php
 *
 * A service to handle Windows Azure Tables NoSQL (schema-less) database
 * services accessed through the REST API.
 */
class WindowsAzureTablesSvc extends NoSqlDbSvc
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default record identifier field
     */
    const DEFAULT_ID_FIELD = 'RowKey';
    /**
     * Define identifying field
     */
    const ROW_KEY = 'RowKey';
    /**
     * Define partitioning field
     */
    const PARTITION_KEY = 'PartitionKey';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var TableRestProxy|null
     */
    protected $_dbConn = null;
    /**
     * @var string
     */
    protected $_defaultPartitionKey = null;
    /**
     * @var null | BatchOperations
     */
    protected $_batchOps = null;
    /**
     * @var null | BatchOperations
     */
    protected $_backupOps = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new WindowsAzureTablesSvc
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct( $config )
    {
        parent::__construct( $config );

        $_credentials = Session::replaceLookup( Option::get( $config, 'credentials' ), true );
        $_connectionString = Session::replaceLookup( Option::get( $_credentials, 'connection_string' ), true );
        if ( empty( $_connectionString ) )
        {
            $_name = Session::replaceLookup(
                Option::get( $_credentials, 'account_name', Option::get( $_credentials, 'AccountName' ) ),
                true
            );
            if ( empty( $_name ) )
            {
                throw new \InvalidArgumentException( 'WindowsAzure account name can not be empty.' );
            }

            $_key = Session::replaceLookup(
                Option::get( $_credentials, 'account_key', Option::get( $_credentials, 'AccountKey' ) ),
                true
            );
            if ( empty( $_key ) )
            {
                throw new \InvalidArgumentException( 'WindowsAzure account key can not be empty.' );
            }

            $_protocol = Option::get( $_credentials, 'protocol', 'https' );
            $_connectionString = "DefaultEndpointsProtocol=$_protocol;AccountName=$_name;AccountKey=$_key";
        }

        // set up a default partition key
        $_partitionKey = Session::replaceLookup( Option::get( $_credentials, static::PARTITION_KEY ) );
        if ( !empty( $_partitionKey ) )
        {
            $this->_defaultPartitionKey = $_partitionKey;
        }

        try
        {
            $this->_dbConn = ServicesBuilder::getInstance()->createTableService( $_connectionString );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Windows Azure Table Service Exception:\n{$_ex->getMessage()}" );
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
     * {@InheritDoc}
     */
    protected function _detectRequestMembers()
    {
        parent::_detectRequestMembers();

        if ( !isset( $this->_requestPayload[static::PARTITION_KEY] ) )
        {
            $this->_requestPayload[static::PARTITION_KEY] = $this->_defaultPartitionKey;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function correctTableName( $name  )
    {
        static $_existing = null;

        if ( !$_existing )
        {
            $_existing = $this->_getTablesAsArray();
        }

        if ( empty( $name ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        if ( false === array_search( $name, $_existing ) )
        {
            throw new NotFoundException( "Table '$name' not found." );
        }

        return $name;
    }

    protected function _getTablesAsArray()
    {
        /** @var QueryTablesResult $_result */
        $_result = $this->_dbConn->queryTables();

        /** @var GetTableResult[] $_out */
        $_out = $_result->getTables();

        return $_out;
    }

    // REST service implementation

    /**
     * @throws \Exception
     * @return array
     */
    protected function _listTables()
    {
        $_resources = array();
        $_result = $this->_getTablesAsArray();
        foreach ( $_result as $_table )
        {
            $_resources[] = array('name' => $_table);
        }

        return $_resources;
    }

    // Handle administrative options, table add, delete, etc

    /**
     * {@inheritdoc}
     */
    public function describeTable( $table, $refresh = true  )
    {
        $_name = ( is_array( $table ) ) ? Option::get( $table, 'name' ) : $table;

        try
        {
            $_out = array('name' => $_name);
            $_out['access'] = $this->getPermissions( $_name );

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to get table properties for table '$_name'.\n{$_ex->getMessage(
            )}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTable( $table, $properties = array(), $check_exist = false )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( "No 'name' field in data." );
        }

        try
        {
            $this->_dbConn->createTable( $table );
            $_out = array('name' => $table);

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to create table '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable( $table, $properties = array(), $allow_delete_fields = false )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( "No 'name' field in data." );
        }

//        throw new InternalServerErrorException( "Failed to update table '$_name'." );
        return array('name' => $table);
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
            $this->_dbConn->deleteTable( $_name );

            return array('name' => $_name);
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
        $record = DbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        try
        {
            // parse filter
            $filter = static::buildCriteriaArray( $filter, $params, $_ssFilters );
            /** @var Entity[] $_entities */
            $_entities = $this->queryEntities( $table, $filter, $_fields, $extras );
            foreach ( $_entities as $_entity )
            {
                $_entity = static::parseRecordToEntity( $record, $_entity );
                $this->_dbConn->updateEntity( $table, $_entity );
            }

            $_out = static::parseEntitiesToRecords( $_entities, $_fields );

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to update records in '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function patchRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        try
        {
            // parse filter
            $filter = static::buildCriteriaArray( $filter, $params, $_ssFilters );
            /** @var Entity[] $_entities */
            $_entities = $this->queryEntities( $table, $filter, $_fields, $extras );
            foreach ( $_entities as $_entity )
            {
                $_entity = static::parseRecordToEntity( $record, $_entity );
                $this->_dbConn->mergeEntity( $table, $_entity );
            }

            $_out = static::parseEntitiesToRecords( $_entities, $_fields );

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

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        try
        {
            $filter = static::buildCriteriaArray( $filter, $params, $_ssFilters );
            /** @var Entity[] $_entities */
            $_entities = $this->queryEntities( $table, $filter, $_fields, $extras );
            foreach ( $_entities as $_entity )
            {
                $_partitionKey = $_entity->getPartitionKey();
                $_rowKey = $_entity->getRowKey();
                $this->_dbConn->deleteEntity( $table, $_partitionKey, $_rowKey );
            }

            $_out = static::parseEntitiesToRecords( $_entities, $_fields );

            return $_out;
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
        $this->checkConnection();
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_options = new QueryEntitiesOptions();
        $_options->setSelectFields( array() );
        if ( !empty( $_fields ) && ( '*' != $_fields ) )
        {
            $_fields = array_map( 'trim', explode( ',', trim( $_fields, ',' ) ) );
            $_options->setSelectFields( $_fields );
        }

        $limit = intval( Option::get( $extras, 'limit', 0 ) );
        if ( $limit > 0 )
        {
            $_options->setTop( $limit );
        }

        $filter = static::buildCriteriaArray( $filter, $params, $_ssFilters );
        $_out = $this->queryEntities( $table, $filter, $_fields, $extras, true );

        return $_out;
    }

    protected function getIdsInfo( $table, $fields_info = null, &$requested_fields = null, $requested_types = null )
    {
        $requested_fields = array(static::PARTITION_KEY, static::ROW_KEY); // can only be this
        $_ids = array(
            array('name' => static::PARTITION_KEY, 'type' => 'string', 'required' => true),
            array('name' => static::ROW_KEY, 'type' => 'string', 'required' => true)
        );

        return $_ids;
    }

    /**
     * @param        $table
     * @param string $parsed_filter
     * @param string $fields
     * @param array  $extras
     * @param bool   $parse_results
     *
     * @throws \Exception
     * @return array
     */
    protected function queryEntities( $table, $parsed_filter = '', $fields = null, $extras = array(), $parse_results = false )
    {
        $this->checkConnection();

        $_options = new QueryEntitiesOptions();
        $_options->setSelectFields( array() );

        if ( !empty( $fields ) && ( '*' != $fields ) )
        {
            if ( !is_array( $fields ) )
            {
                $fields = array_map( 'trim', explode( ',', trim( $fields, ',' ) ) );
            }
            $_options->setSelectFields( $fields );
        }

        $limit = intval( Option::get( $extras, 'limit', 0 ) );
        if ( $limit > 0 )
        {
            $_options->setTop( $limit );
        }

        if ( !empty( $parsed_filter ) )
        {
            $_query = new QueryStringFilter( $parsed_filter );
            $_options->setFilter( $_query );
        }

        try
        {
            /** @var QueryEntitiesResult $_result */
            $_result = $this->_dbConn->queryEntities( $table, $_options );

            /** @var Entity[] $entities */
            $_entities = $_result->getEntities();

            if ( $parse_results )
            {
                return static::parseEntitiesToRecords( $_entities );
            }

            return $_entities;
        }
        catch ( ServiceException $_ex )
        {
            throw new InternalServerErrorException( "Failed to filter items from '$table' on Windows Azure Tables service.\n" .
                                                    $_ex->getMessage() );
        }
    }

    /**
     * @param array $record
     * @param array $fields_info
     * @param array $filter_info
     * @param bool  $for_update
     * @param array $old_record
     *
     * @return array
     * @throws \Exception
     */
    protected function parseRecord( $record, $fields_info, $filter_info = null, $for_update = false, $old_record = null )
    {
        $_parsed = ( empty( $fields_info ) ) ? $record : array();

        unset($_parsed['Timestamp']); // not set-able

        if ( !empty( $fields_info ) )
        {
            $_keys = array_keys( $record );
            $_values = array_values( $record );
            foreach ( $fields_info as $_fieldInfo )
            {
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
     * @param array       $record
     * @param null|Entity $entity
     * @param array       $exclude List of keys to exclude from adding to Entity
     *
     * @return Entity
     */
    protected static function parseRecordToEntity( $record = array(), $entity = null, $exclude = array() )
    {
        if ( empty( $entity ) )
        {
            $entity = new Entity();
        }
        foreach ( $record as $_key => $_value )
        {
            if ( false === array_search( $_key, $exclude ) )
            {
                // valid types
//				const DATETIME = 'Edm.DateTime';
//				const BINARY   = 'Edm.Binary';
//				const GUID     = 'Edm.Guid';
                $_edmType = EdmType::STRING;
                switch ( gettype( $_value ) )
                {
                    case 'boolean':
                        $_edmType = EdmType::BOOLEAN;
                        break;
                    case 'double':
                    case 'float':
                        $_edmType = EdmType::DOUBLE;
                        break;
                    case 'integer':
                        $_edmType = ( $_value > 2147483647 ) ? EdmType::INT64 : EdmType::INT32;
                        break;
                }
                if ( $entity->getProperty( $_key ) )
                {
                    $_prop = new Property();
                    $_prop->setEdmType( $_edmType );
                    $_prop->setValue( $_value );
                    $entity->setProperty( $_key, $_prop );
                }
                else
                {
                    $entity->addProperty( $_key, $_edmType, $_value );
                }
            }
        }

        return $entity;
    }

    /**
     * @param null|Entity  $entity
     * @param string|array $include List of keys to include in the output record
     * @param array        $record
     *
     * @return array
     */
    protected static function parseEntityToRecord( $entity, $include = '*', $record = array() )
    {
        if ( !empty( $entity ) )
        {
            if ( empty( $include ) )
            {
                $record[static::PARTITION_KEY] = $entity->getPartitionKey();
                $record[static::ROW_KEY] = $entity->getRowKey();
            }
            elseif ( '*' == $include )
            {
                // return all properties
                /** @var Property[] $properties */
                $properties = $entity->getProperties();
                foreach ( $properties as $key => $property )
                {
                    $record[$key] = $property->getValue();
                }
            }
            else
            {
                if ( !is_array( $include ) )
                {
                    $include = array_map( 'trim', explode( ',', trim( $include, ',' ) ) );
                }
                foreach ( $include as $key )
                {
                    $record[$key] = $entity->getPropertyValue( $key );
                }
            }
        }

        return $record;
    }

    protected static function parseEntitiesToRecords( $entities, $include = '*', $records = array() )
    {
        if ( !is_array( $records ) )
        {
            $records = array();
        }
        foreach ( $entities as $_entity )
        {
            if ( $_entity instanceof BatchError )
            {
                /** @var ServiceException $_error */
                $_error = $_entity->getError();
                throw $_error;
            }
            if ( $_entity instanceof InsertEntityResult )
            {
                /** @var InsertEntityResult $_entity */
                $_entity = $_entity->getEntity();
                $records[] = static::parseEntityToRecord( $_entity, $include );
            }
            else
            {
                $records[] = static::parseEntityToRecord( $_entity, $include );
            }
        }

        return $records;
    }

    protected static function buildCriteriaArray( $filter, $params = null, $ss_filters = null )
    {
        // interpret any parameter values as lookups
        $params = static::interpretRecordValues( $params );

        // build filter array if necessary, add server-side filters if necessary
        if ( !is_array( $filter ) )
        {
            Session::replaceLookupsInStrings( $filter );
            $_criteria = static::parseFilter( $filter, $params );
        }
        else
        {
            $_criteria = $filter;
        }
        $_serverCriteria = static::buildSSFilterArray( $ss_filters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria = ( empty( $_criteria ) ) ? $_serverCriteria : "( $_serverCriteria ) and ( $_criteria )";
        }

        return $_criteria;
    }

    protected static function buildSSFilterArray( $ss_filters )
    {
        if ( empty( $ss_filters ) )
        {
            return '';
        }

        // build the server side criteria
        $_filters = Option::get( $ss_filters, 'filters' );
        if ( empty( $_filters ) )
        {
            return '';
        }

        $_combiner = Option::get( $ss_filters, 'filter_op', 'and' );
        switch ( strtoupper( $_combiner ) )
        {
            case 'AND':
            case 'OR':
                break;
            default:
                // log and bail
                throw new InternalServerErrorException( 'Invalid server-side filter configuration detected.' );
        }

        $_criteria = '';
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

            $_temp = static::parseFilter( "$_name $_op $_value" );
            if ( !empty( $_criteria ) )
            {
                $_criteria .= " $_combiner ";
            }
            $_criteria .= $_temp;
        }

        return $_criteria;
    }

    /**
     * @param string|array $filter Filter for querying records by
     * @param array        $params replacement parameters
     *
     * @return array
     */
    protected static function parseFilter( $filter, $params = array() )
    {
        if ( empty( $filter ) )
        {
            return '';
        }

        if ( is_array( $filter ) )
        {
            return ''; // todo need to build from array of parts
        }

        // handle logical operators first
        // supported logical operators are or, and, not
        $_search = array(' || ', ' && ', ' OR ', ' AND ', ' NOR ', ' NOT ');
        $_replace = array(' or ', ' and ', ' or ', ' and ', ' nor ', ' not ');
        $filter = trim( str_ireplace( $_search, $_replace, ' ' . $filter ) ); // space added for 'not' case

        // the rest should be comparison operators
        // supported comparison operators are eq, ne, gt, ge, lt, le
        $_search =
            array('!=', '>=', '<=', '>', '<', '=', ' EQ ', ' NE ', ' LT ', ' LTE ', ' LE ', ' GT ', ' GTE', ' GE ');
        $_replace = array(
            ' ne ',
            ' ge ',
            ' le ',
            ' gt ',
            ' lt ',
            ' eq ',
            ' eq ',
            ' ne ',
            ' lt ',
            ' le ',
            ' le ',
            ' gt ',
            ' ge ',
            ' ge '
        );
        $filter = trim( str_ireplace( $_search, $_replace, $filter ) );

//			WHERE name LIKE "%Joe%"	not supported
//			WHERE name LIKE "%Joe"	not supported
//			WHERE name LIKE "Joe%"	name ge 'Joe' and name lt 'Jof';
//			if ( ( '%' == $_val[ strlen( $_val ) - 1 ] ) &&
//				 ( '%' != $_val[0] ) )
//			{
//			}

        if ( !empty( $params ) )
        {
            foreach ( $params as $_name => $_value )
            {
                $filter = str_replace( $_name, $_value, $filter );
            }
        }

        return $filter;
    }

    protected static function buildIdsFilter( $ids, $partition_key = null )
    {
        if ( empty( $ids ) )
        {
            return null;
        }

        if ( !is_array( $ids ) )
        {
            $ids = array_map( 'trim', explode( ',', trim( $ids, ',' ) ) );
        }

        $_filters = array();
        $_filter = '';
        $_count = 0;
        foreach ( $ids as $_id )
        {
            if ( !empty( $_filter ) )
            {
                $_filter .= ' or ';
            }
            $_filter .= static::ROW_KEY . " eq '$_id'";
            $_count++;
            if ( $_count >= 14 ) // max comparisons is 15, leave one for partition key
            {
                if ( !empty( $partition_key ) )
                {
                    $_filter = static::PARTITION_KEY . " eq '$partition_key' and ( $_filter )";
                }
                $_filters[] = $_filter;
                $_count = 0;
            }
        }

        if ( !empty( $_filter ) )
        {
            if ( !empty( $partition_key ) )
            {
                $_filter = static::PARTITION_KEY . " eq '$partition_key' and ( $_filter )";
            }
            $_filters[] = $_filter;
        }

        return $_filters;
    }

    protected function checkForIds( &$record, $ids_info, $extras = null, $on_create = false, $remove = false )
    {
        $_id = null;
        if ( !empty( $ids_info ) )
        {
            if ( 1 == count( $ids_info ) )
            {
                $_info = $ids_info[0];
                $_name = Option::get( $_info, 'name' );
                $_value = ( is_array( $record ) ) ? Option::get( $record, $_name, null, $remove ) : $record;
                if ( !empty( $_value ) )
                {
                    $_type = Option::get( $_info, 'type' );
                    switch ( $_type )
                    {
                        case 'int':
                            $_value = intval( $_value );
                            break;
                        case 'string':
                            $_value = strval( $_value );
                            break;
                    }
                    $_id = $_value;
                }
                else
                {
                    $_required = Option::getBool( $_info, 'required' );
                    // could be passed in as a parameter affecting all records
                    $_param = Option::get( $extras, $_name );
                    if ( $on_create && $_required && empty( $_param ) )
                    {
                        return false;
                    }
                }
            }
            else
            {
                $_id = array();
                foreach ( $ids_info as $_info )
                {
                    $_name = Option::get( $_info, 'name' );
                    $_value = Option::get( $record, $_name, null, $remove );
                    if ( !empty( $_value ) )
                    {
                        $_type = Option::get( $_info, 'type' );
                        switch ( $_type )
                        {
                            case 'int':
                                $_value = intval( $_value );
                                break;
                            case 'string':
                                $_value = strval( $_value );
                                break;
                        }
                        $_id[$_name] = $_value;
                    }
                    else
                    {
                        $_required = Option::getBool( $_info, 'required' );
                        // could be passed in as a parameter affecting all records
                        $_param = Option::get( $extras, $_name );
                        if ( $on_create && $_required && empty( $_param ) )
                        {
                            if ( !is_array( $record ) && ( static::ROW_KEY == $_name ) )
                            {
                                $_id[$_name] = $record;
                            }
                            else
                            {
                                return false;
                            }
                        }
                    }
                }
            }
        }

        if ( !empty( $_id ) )
        {
            return $_id;
        }
        elseif ( $on_create )
        {
            return array();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function initTransaction( $handle = null )
    {
        $this->_batchOps = null;
        $this->_backupOps = null;

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
        $_partitionKey = Option::get( $extras, static::PARTITION_KEY );

        if ( !is_array( $id ) )
        {
            $id = array(static::ROW_KEY => $id, static::PARTITION_KEY => $_partitionKey);
        }
        if ( !empty( $_partitionKey ) )
        {
            $id[static::PARTITION_KEY] = $_partitionKey;
        }

        if ( !empty( $_updates ) )
        {
            foreach ( $id as $_field => $_value )
            {
                if ( !isset( $_updates[$_field] ) )
                {
                    $_updates[$_field] = $_value;
                }
            }
            $record = $_updates;
        }
        elseif ( !empty( $record ) )
        {
            if ( !empty( $_partitionKey ) )
            {
                $record[static::PARTITION_KEY] = $_partitionKey;
            }
        }

        if ( !empty( $record ) )
        {
            $_forUpdate = false;
            switch ( $this->getAction() )
            {
                case static::PUT:
                case static::MERGE:
                case static::PATCH:
                    $_forUpdate = true;
                    break;
            }

            $record = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, $_forUpdate );
            if ( empty( $record ) )
            {
                throw new BadRequestException( 'No valid fields were found in record.' );
            }

            $_entity = static::parseRecordToEntity( $record );
        }
        else
        {
            $_entity = static::parseRecordToEntity( $id );
        }

        $_partKey = $_entity->getPartitionKey();
        if ( empty( $_partKey ) )
        {
            throw new BadRequestException( 'No valid partition key found in request.' );
        }

        $_rowKey = $_entity->getRowKey();
        if ( empty( $_rowKey ) )
        {
            throw new BadRequestException( 'No valid row key found in request.' );
        }

        // only allow batch if rollback and same partition
        $_batch = ( $rollback && !empty( $_partitionKey ) );
        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
                if ( $_batch )
                {
                    if ( !isset( $this->_batchOps ) )
                    {
                        $this->_batchOps = new BatchOperations();
                    }
                    $this->_batchOps->addInsertEntity( $this->_transactionTable, $_entity );

                    // track record for output
                    return parent::addToTransaction( $record );
                }

                /** @var InsertEntityResult $_result */
                $_result = $this->_dbConn->insertEntity( $this->_transactionTable, $_entity );

                if ( $rollback )
                {
                    $this->addToRollback( $_entity );
                }

                $_out = static::parseEntityToRecord( $_result->getEntity(), $_fields );
                break;
            case static::PUT:
                if ( $_batch )
                {
                    if ( !isset( $this->_batchOps ) )
                    {
                        $this->_batchOps = new BatchOperations();
                    }
                    $this->_batchOps->addUpdateEntity( $this->_transactionTable, $_entity );

                    // track record for output
                    return parent::addToTransaction( $record );
                }

                if ( $rollback )
                {
                    $_old = $this->_dbConn->getEntity(
                        $this->_transactionTable,
                        $_entity->getRowKey(),
                        $_entity->getPartitionKey()
                    );
                    $this->addToRollback( $_old );
                }

                /** @var UpdateEntityResult $_result */
                $this->_dbConn->updateEntity( $this->_transactionTable, $_entity );

                $_out = static::parseEntityToRecord( $_entity, $_fields );
                break;
            case static::MERGE:
            case static::PATCH:
                if ( $_batch )
                {
                    if ( !isset( $this->_batchOps ) )
                    {
                        $this->_batchOps = new BatchOperations();
                    }
                    $this->_batchOps->addMergeEntity( $this->_transactionTable, $_entity );

                    // track id for output
                    return parent::addToTransaction( null, $_rowKey );
                }

                if ( $rollback || $_requireMore )
                {
                    $_old = $this->_dbConn->getEntity( $this->_transactionTable, $_rowKey, $_partKey );
                    if ( $rollback )
                    {
                        $this->addToRollback( $_old );
                    }
                    if ( $_requireMore )
                    {
                        $_out = array_merge(
                            static::parseEntityToRecord( $_old, $_fields ),
                            static::parseEntityToRecord( $_entity, $_fields )
                        );
                    }
                }

                $_out = ( empty( $_out ) ) ? static::parseEntityToRecord( $_entity, $_fields ) : $_out;

                /** @var UpdateEntityResult $_result */
                $this->_dbConn->mergeEntity( $this->_transactionTable, $_entity );
                break;
            case static::DELETE:
                if ( $_batch )
                {
                    if ( !isset( $this->_batchOps ) )
                    {
                        $this->_batchOps = new BatchOperations();
                    }
                    $this->_batchOps->addDeleteEntity( $this->_transactionTable, $_partKey, $_rowKey );

                    // track id for output
                    return parent::addToTransaction( null, $_rowKey );
                }

                if ( $rollback || $_requireMore )
                {
                    $_old = $this->_dbConn->getEntity( $this->_transactionTable, $_partKey, $_rowKey );
                    if ( $rollback )
                    {
                        $this->addToRollback( $_old );
                    }
                    if ( $_requireMore )
                    {
                        $_out = static::parseEntityToRecord( $_old, $_fields );
                    }
                }

                $this->_dbConn->deleteEntity( $this->_transactionTable, $_partKey, $_rowKey );

                $_out = ( empty( $_out ) ) ? static::parseEntityToRecord( $_entity, $_fields ) : $_out;
                break;
            case static::GET:
                if ( !empty( $_partitionKey ) )
                {
                    // track id for output
                    return parent::addToTransaction( null, $_rowKey );
                }

                /** @var GetEntityResult $_result */
                $_result = $this->_dbConn->getEntity( $this->_transactionTable, $_partKey, $_rowKey );

                $_out = static::parseEntityToRecord( $_result->getEntity(), $_fields );
                break;
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    protected function commitTransaction( $extras = null )
    {
        if ( !isset( $this->_batchOps ) && empty( $this->_batchIds ) && empty( $this->_batchRecords ) )
        {
            return null;
        }

        $_fields = Option::get( $extras, 'fields' );
        $_partitionKey = Option::get( $extras, static::PARTITION_KEY );

        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
            case static::PUT:
                if ( isset( $this->_batchOps ) )
                {
                    /** @var BatchResult $_result */
                    $this->_dbConn->batch( $this->_batchOps );
                }
                if ( !empty( $this->_batchRecords ) )
                {
                    $_out = static::parseEntitiesToRecords( $this->_batchRecords, $_fields );
                }
                break;

            case static::MERGE:
            case static::PATCH:
                if ( isset( $this->_batchOps ) )
                {
                    /** @var BatchResult $_result */
                    $this->_dbConn->batch( $this->_batchOps );
                }
                if ( !empty( $this->_batchIds ) )
                {
                    $_filters = static::buildIdsFilter( $this->_batchIds, $_partitionKey );
                    foreach ( $_filters as $_filter )
                    {
                        $_temp = $this->queryEntities( $this->_transactionTable, $_filter, $_fields, $extras, true );
                        $_out = array_merge( $_out, $_temp );
                    }
                }
                break;

            case static::DELETE:
                if ( !empty( $this->_batchIds ) )
                {
                    $_filters = static::buildIdsFilter( $this->_batchIds, $_partitionKey );
                    foreach ( $_filters as $_filter )
                    {
                        $_temp = $this->queryEntities( $this->_transactionTable, $_filter, $_fields, $extras, true );
                        $_out = array_merge( $_out, $_temp );
                    }
                }
                if ( isset( $this->_batchOps ) )
                {
                    /** @var BatchResult $_result */
                    $this->_dbConn->batch( $this->_batchOps );
                }
                break;

            case static::GET:
                if ( !empty( $this->_batchIds ) )
                {
                    $_filters = static::buildIdsFilter( $this->_batchIds, $_partitionKey );
                    foreach ( $_filters as $_filter )
                    {
                        $_temp = $this->queryEntities( $this->_transactionTable, $_filter, $_fields, $extras, true );
                        $_out = array_merge( $_out, $_temp );
                    }
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
        if ( !isset( $this->_backupOps ) )
        {
            $this->_backupOps = new BatchOperations();
        }
        switch ( $this->getAction() )
        {
            case static::POST:
                $this->_backupOps->addDeleteEntity(
                    $this->_transactionTable,
                    $record->getPartitionKey(),
                    $record->getRowKey()
                );
                break;

            case static::PUT:
            case static::MERGE:
            case static::PATCH:
            case static::DELETE:
                $this->_batchOps->addUpdateEntity( $this->_transactionTable, $record );
                break;

            default:
                break;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function rollbackTransaction()
    {
        if ( !isset( $this->_backupOps ) )
        {
            switch ( $this->getAction() )
            {
                case static::POST:
                case static::PUT:
                case static::PATCH:
                case static::MERGE:
                case static::DELETE:
                    /** @var BatchResult $_result */
                    $this->_dbConn->batch( $this->_backupOps );
                    break;

                default:
                    break;
            }

            $this->_backupOps = null;
        }

        return true;
    }
}
