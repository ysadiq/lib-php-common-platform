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
use DreamFactory\Platform\Utility\DbUtilities;
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
     * Connection string prefix
     */
    const DSN_PREFIX = 'mongodb://';
    /**
     * Connection string prefix length
     */
    const DSN_PREFIX_LENGTH = 10;
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
    /**
     * @var \MongoCollection
     */
    protected $_collection = null;

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
        Session::replaceLookups( $_credentials, true );

        $_dsn = strval( Option::get( $_credentials, 'dsn', '', true, true ) );
        if ( !empty( $_dsn ) )
        {
            if ( 0 != substr_compare( $_dsn, static::DSN_PREFIX, 0, static::DSN_PREFIX_LENGTH, true ) )
            {
                $_dsn = static::DSN_PREFIX . $_dsn;
            }
        }

        $_options = Option::get( $_credentials, 'options', array() );

        // support old configuration options of user, pwd, and db in credentials directly
        if ( !isset($_options['username']) && (null !== $_username = Option::get( $_credentials, 'user', null, true, true ) ) )
        {
            $_options['username'] = $_username;
        }
        if ( !isset($_options['password']) && (null !== $_password = Option::get( $_credentials, 'pwd', null, true, true ) ) )
        {
            $_options['password'] = $_password;
        }
        if ( !isset($_options['db']) && (null !== $_db = Option::get( $_credentials, 'db', null, true, true ) ) )
        {
            $_options['db'] = $_db;
        }

        if ( !isset( $_db ) && ( null === $_db = Option::get( $_options, 'db', null, false, true ) ) )
        {
            //  Attempt to find db in connection string
            $_db = strstr( substr( $_dsn, static::DSN_PREFIX_LENGTH ), '/' );
            if (false !== $_pos = strpos( $_db, '?' ) )
            {
                $_db = substr( $_db, 0, $_pos );
            }
            $_db = trim( $_db, '/' );
        }

        if ( empty( $_db ) )
        {
            throw new InternalServerErrorException( "No MongoDb database selected in configuration." );
        }

        $_driverOptions = Option::clean( Option::get( $_credentials, 'driver_options' ) );
        if ( null !== $_context = Option::get( $_driverOptions, 'context' ) )
        {
            //  Automatically creates a stream from context
            $_driverOptions['context'] = stream_context_create( $_context );
        }

        try
        {
            $_client = new \MongoClient( $_dsn, $_options, $_driverOptions );

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
     * @param string $name
     *
     * @return string
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function correctTableName( &$name )
    {
        static $_existing = null;

        if ( !$_existing )
        {
            $_existing = $this->_dbConn->getCollectionNames();
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

    // REST service implementation

    /**
     * {@inheritdoc}
     */
    protected function _listTables( /** @noinspection PhpUnusedParameterInspection */ $refresh = true )
    {
        $_resources = array();
        $_result = $this->_dbConn->getCollectionNames();
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
    public function describeTable( $table, $refresh = true )
    {
        $_name = ( is_array( $table ) ) ? Option::get( $table, 'name' ) : $table;

        try
        {
            $_coll = $this->selectTable( $_name );
            $_out = array('name' => $_coll->getName());
            $_out['indexes'] = $_coll->getIndexInfo();
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
    public function createTable( $table, $properties = array(), $check_exist = false, $return_schema = false )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( "No 'name' field in data." );
        }

        try
        {
            $_result = $this->_dbConn->createCollection( $table );
            $_out = array('name' => $_result->getName());
            $_out['indexes'] = $_result->getIndexInfo();

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
    public function updateTable( $table, $properties = array(), $allow_delete_fields = false, $return_schema = false )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( "No 'name' field in data." );
        }

        $this->selectTable( $table );

//		throw new InternalServerErrorException( "Failed to update table '$_name'." );
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
            $this->_dbConn->dropCollection( $_name );

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
        $_coll = $this->selectTable( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_fieldArray = static::buildFieldArray( $_fields );

        static::removeIds( $record, static::DEFAULT_ID_FIELD );
        $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );
        if ( empty( $_parsed ) )
        {
            throw new BadRequestException( 'No valid fields found in request: ' . print_r( $record, true ) );
        }

        // build criteria from filter parameters
        $_criteria = static::buildCriteriaArray( $filter, $params, $_ssFilters );

        try
        {
            $_result = $_coll->update( $_criteria, $_parsed, array('multiple' => true) );
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
        $_coll = $this->selectTable( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_fieldArray = static::buildFieldArray( $_fields );

        static::removeIds( $record, static::DEFAULT_ID_FIELD );
        if ( !static::doesRecordContainModifier( $record ) )
        {
            $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );
            if ( empty( $_parsed ) )
            {
                throw new BadRequestException( 'No valid fields found in request: ' . print_r( $record, true ) );
            }

            $_parsed = array('$set' => $_parsed);
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
            $_result = $_coll->update( $_criteria, $_parsed, array('multiple' => true) );
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
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to update records in '$table'.\n{$_ex->getMessage()}" );
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
            $_coll->remove( $_criteria );

            return array('success' => true);
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
        $_coll = $this->selectTable( $table );

        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_fieldArray = static::buildFieldArray( $_fields );
        $_criteria = static::buildCriteriaArray( $filter, $params, $_ssFilters );

        $_limit = intval( Option::get( $extras, 'limit', 0 ) );
        $_offset = intval( Option::get( $extras, 'offset', 0 ) );
        $_sort = static::buildSortArray( Option::get( $extras, 'order' ) );
        $_addCount = Option::getBool( $extras, 'include_count', false );

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
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to filter records from '$table'.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * @param      $table
     * @param null $fields_info
     * @param null $requested_fields
     * @param null $requested_types
     *
     * @return array
     */
    protected function getIdsInfo( $table, $fields_info = null, &$requested_fields = null, $requested_types = null )
    {
        $requested_fields = static::DEFAULT_ID_FIELD; // can only be this
        $requested_types = Option::clean( $requested_types );
        $_type = Option::get( $requested_types, 0, 'string' );
        $_type = ( empty( $_type ) ) ? 'string' : $_type;

        return array(array('name' => static::DEFAULT_ID_FIELD, 'type' => $_type, 'required' => false));
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

        $_search = array(' or ', ' and ', ' nor ');
        $_replace = array(' || ', ' && ', ' NOR ');
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

            return array('$or' => $_parts);
        }

        $_ops = array_map( 'trim', explode( ' NOR ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_parts = array();
            foreach ( $_ops as $_op )
            {
                $_parts[] = static::buildFilterArray( $_op, $params );
            }

            return array('$nor' => $_parts);
        }

        $_ops = array_map( 'trim', explode( ' && ', $filter ) );
        if ( count( $_ops ) > 1 )
        {
            $_parts = array();
            foreach ( $_ops as $_op )
            {
                $_parts[] = static::buildFilterArray( $_op, $params );
            }

            return array('$and' => $_parts);
        }

        // handle negation operator, i.e. starts with NOT?
        if ( 0 == substr_compare( $filter, 'not ', 0, 4, true ) )
        {
            $_parts = trim( substr( $filter, 4 ) );

            return array('$not' => $_parts);
        }

        // the rest should be comparison operators
        $_search = array(' eq ', ' ne ', ' gte ', ' lte ', ' gt ', ' lt ', ' in ', ' nin ', ' all ', ' like ', ' <> ');
        $_replace = array('=', '!=', '>=', '<=', '>', '<', ' IN ', ' NIN ', ' ALL ', ' LIKE ', '!=');
        $filter = trim( str_ireplace( $_search, $_replace, $filter ) );

        // Note: order matters, watch '='
        $_sqlOperators = array('!=', '>=', '<=', '=', '>', '<', ' IN ', ' NIN ', ' ALL ', ' LIKE ');
        $_mongoOperators = array('$ne', '$gte', '$lte', '$eq', '$gt', '$lt', '$in', '$nin', '$all', 'MongoRegex');
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
                        return array($_field => $_val);

                    case '$in':
                    case '$nin':
                        // todo check for list of mongoIds
                        $_val = array_map( 'trim', explode( ',', trim( trim( $_val, '(,)' ), ',' ) ) );
                        $_valArray = array();
                        foreach ( $_val as $_item )
                        {
                            $_valArray[] = static::_determineValue( $_item, $_field, $params );
                        }

                        return array($_field => array($_mongoOp => $_valArray));

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

                        return array($_field => new \MongoRegex( $_val ));

                    default:
                        return array($_field => array($_mongoOp => $_val));
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
            $value = static::idToMongoId( $value );
        }

        if ( is_string( $value ) )
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
        }

        return $value;
    }

    /**
     * @param      $filter
     * @param null $params
     * @param null $ss_filters
     *
     * @return array|mixed
     * @throws InternalServerErrorException
     */
    protected static function buildCriteriaArray( $filter, $params = null, $ss_filters = null )
    {
        // interpret any parameter values as lookups
        $params = static::interpretRecordValues( $params );

        // build filter array if necessary
        $_criteria = $filter;
        if ( !is_array( $filter ) )
        {
            Session::replaceLookups( $filter );
            $_test = json_decode( $filter, true );
            if ( !is_null( $_test ) )
            {
                // original filter was a json string, use it as array
                $_criteria = $_test;
            }
            else
            {
                $_criteria = static::buildFilterArray( $filter, $params );
            }
        }

        // add server-side filters if necessary
        $_serverCriteria = static::buildSSFilterArray( $ss_filters );
        if ( !empty( $_serverCriteria ) )
        {
            $_criteria =
                ( !empty( $_criteria ) ) ? array('$and' => array($_criteria, $_serverCriteria)) : $_serverCriteria;
        }

        return $_criteria;
    }

    /**
     * @param $ss_filters
     *
     * @return array
     * @throws InternalServerErrorException
     */
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
                $_criteria = array('$and' => $_criteria);
                break;
            case 'OR':
                $_criteria = array('$or' => $_criteria);
                break;
            case 'NOR':
                $_criteria = array('$nor' => $_criteria);
                break;
            default:
                // log and bail
                throw new InternalServerErrorException( 'Invalid server-side filter configuration detected.' );
        }

        return $_criteria;
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
    protected static function cleanRecord( $record = array(), $include = '*', $id_field = null )
    {
        $_out = parent::cleanRecord( $record, $include, $id_field );

        return static::_fromMongoObjects( $_out );
    }

    /**
     * @param array $record
     *
     * @return array|string
     */
    protected static function _fromMongoObjects( array $record )
    {
        if ( !empty( $record ) )
        {
            foreach ( $record as &$_data )
            {
                if ( is_object( $_data ) )
                {
                    if ( $_data instanceof \MongoId )
                    {
                        $_data = $_data->__toString();
                    }
                    elseif ( $_data instanceof \MongoDate )
                    {
//                        $_data = $data->__toString();
                        $_data = array('$date' => date( DATE_ISO8601, $_data->sec ));
                    }
                    elseif ( $_data instanceof \MongoBinData )
                    {
                        $_data = (string)$_data;
                    }
                    elseif ( $_data instanceof \MongoDBRef )
                    {
                    }
                }
            }
        }

        return $record;
    }

    /**
     * @param array $record
     *
     * @return array
     */
    protected static function _toMongoObjects( $record )
    {
        if ( !empty( $record ) )
        {
            foreach ( $record as $_key => $_data )
            {
                if ( !is_object( $_data ) )
                {
                    if ( is_string( $_data ) && ( static::DEFAULT_ID_FIELD == $_key ) )
                    {
                        $record[$_key] = static::idToMongoId( $_data );
                    }
                    elseif ( is_array( $_data ) && ( 1 === count( $_data ) ) )
                    {
                        // using typed definition, i.e. {"$date" : "2014-08-02T08:40:12.569Z" }
                        if ( array_key_exists( '$date', $_data ) )
                        {
                            $_temp = $_data['$date'];
                            if ( empty( $_temp ) )
                            {
                                $record[$_key] = new \MongoDate();
                            }
                            elseif ( is_string( $_temp ) )
                            {
                                $record[$_key] = new \MongoDate( strtotime( $_temp ) );
                            }
                            elseif ( is_int( $_temp ) )
                            {
                                $record[$_key] = new \MongoDate( $_temp );
                            }
                        }
                        elseif ( isset( $_data['$id'] ) )
                        {
                            $record[$_key] = static::idToMongoId( $_data['$id'] );
                        }
                    }
                }
            }
        }

        return $record;
    }

    /**
     * @param mixed $value
     *
     * @return array|string
     */
    protected static function mongoIdToId( $value )
    {
        if ( is_object( $value ) )
        {
            /** $record \MongoId */
            $value = (string)$value;
        }

        return $value;
    }

    /**
     * @param array  $records
     *
     * @return mixed
     */
    protected static function mongoIdsToIds( $records )
    {
        foreach ( $records as $key => $_record )
        {
            $records[$key] = static::mongoIdToId( $_record );
        }

        return $records;
    }

    /**
     * @param mixed $value
     *
     * @return array|bool|float|int|\MongoId|string
     */
    protected static function idToMongoId( $value )
    {
        if ( is_array( $value ) )
        {
            if ( array_key_exists( '$id', $value ) )
            {
                $value = Option::get( $value, '$id' );
            }
        }

        if ( is_string( $value ) )
        {
            if ( ( 24 == strlen( $value ) ) )
            {
                try
                {
                    $_temp = new \MongoId( $value );
                    $value = $_temp;
                }
                catch ( \Exception $_ex )
                {
                    // obviously not a Mongo created Id, let it be
                }
            }
        }

        return $value;
    }

    /**
     * @param string|array $ids
     *
     * @return array
     */
    protected static function idsToMongoIds( $ids )
    {
        if ( !is_array( $ids ) )
        {
            // comma delimited list of ids
            $ids = array_map( 'trim', explode( ',', trim( $ids, ',' ) ) );
        }

        foreach ( $ids as &$_id )
        {
            $_id = static::idToMongoId( $_id );
        }

        return $ids;
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
        $record = $this->interpretRecordValues( $record );

        switch ( $this->getAction() )
        {
            case static::MERGE:
            case static::PATCH:
                if ( static::doesRecordContainModifier( $record ) )
                {
                    return $record;
                }
                break;
        }

        $_parsed = ( empty( $fields_info ) ) ? $record : array();
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

        // convert to native format
        return static::_toMongoObjects( $_parsed );
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

    /**
     * {@inheritdoc}
     */
    protected function initTransaction( $handle = null )
    {
        $this->_collection = $this->selectTable( $handle );

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

        // convert to native format
        $id = static::idToMongoId( $id );

        $_fieldArray = ( $rollback ) ? null : static::buildFieldArray( $_fields );

        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                if ( !$continue && !$rollback && !$single )
                {
                    return parent::addToTransaction( $_parsed, $id );
                }

                $_result = $this->_collection->insert( $_parsed );
                static::processResult( $_result );

                $_out = static::cleanRecord( $_parsed, $_fields, static::DEFAULT_ID_FIELD );

                if ( $rollback )
                {
                    $this->addToRollback( static::recordAsId( $_parsed, static::DEFAULT_ID_FIELD ) );
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
                if ( !$continue && !$rollback && !$single && !empty( $_updates ) )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_options = array('new' => !$rollback);
                if ( empty( $_updates ) )
                {
                    $_out = static::cleanRecord( $record, $_fields, static::DEFAULT_ID_FIELD );
                    static::removeIds( $_parsed, static::DEFAULT_ID_FIELD );
                    $_updates = $_parsed;
                }
                else
                {
                    $record = $_updates;
                    $record[static::DEFAULT_ID_FIELD] = $id;
                    $_out = static::cleanRecord( $record, $_fields, static::DEFAULT_ID_FIELD );
                }

                // simple update overwrite existing record
                $_filter = array(static::DEFAULT_ID_FIELD => $id);
                $_criteria = $this->buildCriteriaArray( $_filter, null, $_ssFilters );
                $_result = $this->_collection->findAndModify( $_criteria, $_updates, $_fieldArray, $_options );
                if ( empty( $_result ) )
                {
                    throw new NotFoundException( "Record with id '$id' not found." );
                }

                if ( $rollback )
                {
                    $this->addToRollback( $_result );
                }
                else
                {
                    $_out = static::_fromMongoObjects( $_result );
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
                if ( !$continue && !$rollback && !$single && !empty( $_updates ) )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_options = array('new' => !$rollback);
                if ( empty( $_updates ) )
                {
                    static::removeIds( $_parsed, static::DEFAULT_ID_FIELD );
                    $_updates = $_parsed;
                }

                $_updates = array('$set' => $_updates);

                // simple merge with existing record
                $_filter = array(static::DEFAULT_ID_FIELD => $id);
                $_criteria = $this->buildCriteriaArray( $_filter, null, $_ssFilters );
                $_result = $this->_collection->findAndModify( $_criteria, $_updates, $_fieldArray, $_options );
                if ( empty( $_result ) )
                {
                    throw new NotFoundException( "Record with id '$id' not found." );
                }

                if ( $rollback )
                {
                    $this->addToRollback( $_result );

                    // need to retrieve the full record here
                    if ( $_requireMore )
                    {
                        /** @var \MongoCursor $_result */
                        $_result = $this->_collection->findOne( $_criteria, $_fieldArray );
                    }
                    else
                    {
                        $_result = array(static::DEFAULT_ID_FIELD => $id);
                    }
                }

                $_out = static::_fromMongoObjects( $_result );
                break;

            case static::DELETE:
                if ( !$continue && !$rollback && !$single )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_options = array('remove' => true);

                // simple delete existing record
                $_filter = array(static::DEFAULT_ID_FIELD => $id);
                $_criteria = $this->buildCriteriaArray( $_filter, null, $_ssFilters );
                $_result = $this->_collection->findAndModify( $_criteria, null, $_fieldArray, $_options );
                if ( empty( $_result ) )
                {
                    throw new NotFoundException( "Record with id '$id' not found." );
                }

                if ( $rollback )
                {
                    $this->addToRollback( $_result );
                    $_out = static::cleanRecord( $record, $_fields, static::DEFAULT_ID_FIELD );
                }
                else
                {
                    $_out = static::_fromMongoObjects( $_result );
                }
                break;

            case static::GET:
                if ( $continue && !$single )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_filter = array(static::DEFAULT_ID_FIELD => $id);
                $_criteria = $this->buildCriteriaArray( $_filter, null, $_ssFilters );
                $_result = $this->_collection->findOne( $_criteria, $_fieldArray );
                if ( empty( $_result ) )
                {
                    throw new NotFoundException( "Record with id '$id' not found." );
                }

                $_out = static::_fromMongoObjects( $_result );
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

        $_updates = Option::get( $extras, 'updates' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_fields = Option::get( $extras, 'fields' );
        $_requireMore = Option::get( $extras, 'require_more' );

        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
                $_result = $this->_collection->batchInsert( $this->_batchRecords, array('continueOnError' => false) );
                static::processResult( $_result );

                $_out = static::cleanRecords( $this->_batchRecords, $_fields );
                break;
            case static::PUT:
                if ( empty( $_updates ) )
                {
                    throw new BadRequestException( 'Batch operation not supported for update by records.' );
                }

                $_filter = array(static::DEFAULT_ID_FIELD => array('$in' => $this->_batchIds));
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );

                $_result = $this->_collection->update( $_criteria, $_updates, null, array('multiple' => true) );
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
                if ( empty( $_updates ) )
                {
                    throw new BadRequestException( 'Batch operation not supported for patch by records.' );
                }

                $_updates = array('$set' => $_updates);

                $_filter = array(static::DEFAULT_ID_FIELD => array('$in' => $this->_batchIds));
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );

                $_result = $this->_collection->update( $_criteria, $_updates, array('multiple' => true) );
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
                $_filter = array(static::DEFAULT_ID_FIELD => array('$in' => $this->_batchIds));
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );

                if ( $_requireMore )
                {
                    $_fieldArray = static::buildFieldArray( $_fields );
                    /** @var \MongoCursor $_result */
                    $_result = $this->_collection->find( $_criteria, $_fieldArray );
                    $_result = static::cleanRecords( iterator_to_array( $_result ) );
                    if ( empty( $_result ) )
                    {
                        throw new NotFoundException( 'No records were found using the given identifiers.' );
                    }

                    if ( count( $this->_batchIds ) !== count( $_result ) )
                    {
                        $_errors = array();
                        foreach ( $this->_batchIds as $_index => $_id )
                        {
                            $_found = false;
                            foreach ( $_result as $_record )
                            {
                                if ( $_id == Option::get( $_record, static::DEFAULT_ID_FIELD ) )
                                {
                                    $_out[$_index] = $_record;
                                    $_found = true;
                                    continue;
                                }
                            }
                            if ( !$_found )
                            {
                                $_errors[] = $_index;
                                $_out[$_index] = "Record with identifier '" . print_r( $_id, true ) . "' not found.";
                            }
                        }
                    }
                    else
                    {
                        $_out = $_result;
                    }
                }
                else
                {
                    $_out = static::idsAsRecords( static::mongoIdsToIds( $this->_batchIds ), static::DEFAULT_ID_FIELD );
                }

                $_result = $this->_collection->remove( $_criteria );
                $_rows = static::processResult( $_result );
                if ( 0 === $_rows )
                {
                    throw new NotFoundException( 'No records were found using the given identifiers.' );
                }

                if ( count( $this->_batchIds ) !== $_rows )
                {
                    throw new BadRequestException( 'Batch Error: Not all requested records were deleted.' );
                }
                break;

            case static::GET:
                $_filter = array(static::DEFAULT_ID_FIELD => array('$in' => $this->_batchIds));
                $_criteria = static::buildCriteriaArray( $_filter, null, $_ssFilters );
                $_fieldArray = static::buildFieldArray( $_fields );

                /** @var \MongoCursor $_result */
                $_result = $this->_collection->find( $_criteria, $_fieldArray );
                $_result = static::cleanRecords( iterator_to_array( $_result ) );
                if ( empty( $_result ) )
                {
                    throw new NotFoundException( 'No records were found using the given identifiers.' );
                }

                if ( count( $this->_batchIds ) !== count( $_result ) )
                {
                    $_errors = array();
                    foreach ( $this->_batchIds as $_index => $_id )
                    {
                        $_found = false;
                        foreach ( $_result as $_record )
                        {
                            if ( $_id == Option::get( $_record, static::DEFAULT_ID_FIELD ) )
                            {
                                $_out[$_index] = $_record;
                                $_found = true;
                                continue;
                            }
                        }
                        if ( !$_found )
                        {
                            $_errors[] = $_index;
                            $_out[$_index] = "Record with identifier '" . print_r( $_id, true ) . "' not found.";
                        }
                    }

                    if ( !empty( $_errors ) )
                    {
                        $_context = array('error' => $_errors, 'record' => $_out);
                        throw new NotFoundException( 'Batch Error: Not all records could be retrieved.', null, null, $_context );
                    }
                }

                $_out = $_result;
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
    protected function rollbackTransaction()
    {
        if ( !empty( $this->_rollbackRecords ) )
        {
            switch ( $this->getAction() )
            {
                case static::POST:
                    // should be ids here from creation
                    $_filter = array(static::DEFAULT_ID_FIELD => array('$in' => $this->_rollbackRecords));
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