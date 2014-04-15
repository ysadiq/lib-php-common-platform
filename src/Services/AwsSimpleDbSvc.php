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
use Aws\SimpleDb\SimpleDbClient;
use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\Utilities;
use Kisma\Core\Utility\Option;

/**
 * AwsSimpleDbSvc.php
 *
 * A service to handle Amazon Web Services SimpleDb NoSQL (schema-less) database
 * services accessed through the REST API.
 */
class AwsSimpleDbSvc extends NoSqlDbSvc
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const TABLE_INDICATOR = 'DomainName';

    const DEFAULT_REGION = Region::US_WEST_1;
    /**
     * Default record identifier field
     */
    const DEFAULT_ID_FIELD = 'Name';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var SimpleDbClient|null
     */
    protected $_dbConn = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new AwsSimpleDbSvc
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

        // reply in simplified blend format by default
        if ( null !== ( $_blendFormat = Option::get( $_parameters, 'blend_format' ) ) )
        {
            $this->_defaultBlendFormat = DataFormat::boolval( $_blendFormat );
        }

        try
        {
            $this->_dbConn = SimpleDbClient::factory( $_credentials );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Amazon SimpleDb Service Exception:\n{$_ex->getMessage()}" );
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
        $_token = null;
        do
        {
            $_result = $this->_dbConn->listDomains(
                array(
                    'MxNumberOfDomains' => 100, // arbitrary limit
                    'NextToken'         => $_token
                )
            );
            $_domains = $_result['DomainNames'];
            $_token = $_result['NextToken'];

            if ( !empty( $_domains ) )
            {
                $_out = array_merge( $_out, $_domains );
            }
        }
        while ( $_token );

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
            $_result = $this->_dbConn->domainMetadata(
                array(
                    static::TABLE_INDICATOR => $_name
                )
            );

            // The result of an operation can be used like an array
            $_out = $_result->toArray();
            $_out['name'] = $_name;
            $_out[static::TABLE_INDICATOR] = $_name;
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
                $properties
            );
            $_result = $this->_dbConn->createDomain( $_properties );

            $_out = array_merge( array( 'name' => $_name, static::TABLE_INDICATOR => $_name ), $_result->toArray() );

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

        throw new BadRequestException( "Update table operation is not supported for this service." );
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
            $_result = $this->_dbConn->deleteDomain(
                array(
                    static::TABLE_INDICATOR => $_name
                )
            );

            return array_merge( array( 'name' => $_name, static::TABLE_INDICATOR => $_name ), $_result->toArray() );
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
    public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() )
    {
        $table = $this->correctTableName( $table );

        $_idField = Option::get( $extras, 'id_field', static::DEFAULT_ID_FIELD );
        $_fields = Option::get( $extras, 'fields' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );

        $_select = 'select ';

        $_fields = static::_buildAttributesToGet( $_fields );
        $_select .= $_fields . ' from ' . $table;

        if ( !empty( $filter ) )
        {
            $filter = static::_parseFilter( $filter );
            $_select .= ' where ' . $filter;
        }

        $_order = Option::get( $extras, 'order' );
        if ( $_order > 0 )
        {
            $_select .= ' order by ' . $_order;
        }

        $_limit = Option::get( $extras, 'limit' );
        if ( $_limit > 0 )
        {
            $_select .= ' limit ' . $_limit;
        }

        try
        {
            $_result = $this->_dbConn->select( array( 'SelectExpression' => $_select ) );
            $_items = Option::clean( $_result['Items'] );

            $_out = array();
            foreach ( $_items as $_item )
            {
                $_attributes = Option::get( $_item, 'Attributes' );
                $_name = Option::get( $_item, $_idField );
                $_out[] = array_merge(
                    static::_unformatAttributes( $_attributes ),
                    array( $_idField => $_name )
                );
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
    public function retrieveRecord( $table, $record, $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $table = $this->correctTableName( $table );

        $_fields = Option::get( $extras, 'fields' );

        $_idField = Option::get( $extras, 'id_field', static::DEFAULT_ID_FIELD );
        if ( empty( $_idField ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_id = Option::get( $record, $_idField );
        $_scanProperties = array(
            static::TABLE_INDICATOR => $table,
            'ItemName'              => $_id,
            'ConsistentRead'        => true,
        );
        $fields = static::_buildAttributesToGet( $_fields, $_idField );
        if ( !empty( $fields ) )
        {
            $_scanProperties['AttributeNames'] = $fields;
        }

        try
        {
            $_result = $this->_dbConn->getAttributes( $_scanProperties );
            $_out = array_merge(
                static::_unformatAttributes( $_result['Attributes'] ),
                array( $_idField => $_id )
            );

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to get records from '$table.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByIds( $table, $ids, $extras = array() )
    {
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = Option::get( $extras, 'fields' );

        $_filter = "itemName() in ('" . implode( "','", $ids ) . "')";

        return $this->retrieveRecordsByFilter( $table, $_filter, $_fields, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordById( $table, $id, $fields = null, $extras = array() )
    {
        if ( empty( $id ) )
        {
            throw new BadRequestException( "Identifying field(s) values can not be empty." );
        }

        $table = $this->correctTableName( $table );
        $_idField = Option::get( $extras, 'id_field' );
        if ( empty( $_idField ) )
        {
            $_idField = static::DEFAULT_ID_FIELD;
        }
        $_scanProperties = array(
            static::TABLE_INDICATOR => $table,
            'ItemName'              => $id,
            'ConsistentRead'        => true,
        );
        $fields = static::_buildAttributesToGet( $fields, $_idField );
        if ( !empty( $fields ) )
        {
            $_scanProperties['AttributeNames'] = $fields;
        }

        try
        {
            $_result = $this->_dbConn->getAttributes( $_scanProperties );
            $_out = array_merge(
                static::_unformatAttributes( $_result['Attributes'] ),
                array( $_idField => $id )
            );

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

    protected static function _formatValue( $value )
    {
        if ( is_string( $value ) )
        {
            return $value;
        }
        if ( is_array( $value ) )
        {
            return '#DFJ#' . json_encode( $value );
        }
        if ( is_bool( $value ) )
        {
            return '#DFB#' . strval( $value );
        }
        if ( is_float( $value ) )
        {
            return '#DFF#' . strval( $value );
        }
        if ( is_int( $value ) )
        {
            return '#DFI#' . strval( $value );
        }

        return $value;
    }

    protected static function _unformatValue( $value )
    {
        if ( 0 == substr_compare( $value, '#DFJ#', 0, 5 ) )
        {
            return json_decode( substr( $value, 5 ) );
        }
        if ( 0 == substr_compare( $value, '#DFB#', 0, 5 ) )
        {
            return (bool)substr( $value, 5 );
        }
        if ( 0 == substr_compare( $value, '#DFF#', 0, 5 ) )
        {
            return floatval( substr( $value, 5 ) );
        }
        if ( 0 == substr_compare( $value, '#DFI#', 0, 5 ) )
        {
            return intval( substr( $value, 5 ) );
        }

        return $value;
    }

    /**
     * @param array $record
     * @param bool  $replace
     *
     * @return array
     */
    protected static function _formatAttributes( $record, $replace = false )
    {
        $_out = array();
        if ( !empty( $record ) )
        {
            foreach ( $record as $_name => $_value )
            {
                if ( Utilities::isArrayNumeric( $_value ) )
                {
                    foreach ( $_value as $_key => $_part )
                    {
                        $_part = static::_formatValue( $_part );
                        if ( 0 == $_key )
                        {
                            $_out[] = array( 'Name' => $_name, 'Value' => $_part, 'Replace' => $replace );
                        }
                        else
                        {
                            $_out[] = array( 'Name' => $_name, 'Value' => $_part );
                        }
                    }

                }
                else
                {
                    $_value = static::_formatValue( $_value );
                    $_out[] = array( 'Name' => $_name, 'Value' => $_value, 'Replace' => $replace );
                }
            }
        }

        return $_out;
    }

    /**
     * @param array $record
     *
     * @return array
     */
    protected static function _unformatAttributes( $record )
    {
        $_out = array();
        if ( !empty( $record ) )
        {
            foreach ( $record as $_attribute )
            {
                $_name = Option::get( $_attribute, 'Name' );
                if ( empty( $_name ) )
                {
                    continue;
                }

                $_value = Option::get( $_attribute, 'Value' );
                if ( isset( $_out[$_name] ) )
                {
                    $_temp = $_out[$_name];
                    if ( is_array( $_temp ) )
                    {
                        $_temp[] = static::_unformatValue( $_value );
                        $_value = $_temp;
                    }
                    else
                    {
                        $_value = array( $_temp, static::_unformatValue( $_value ) );
                    }
                }
                else
                {
                    $_value = static::_unformatValue( $_value );
                }
                $_out[$_name] = $_value;
            }
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

    /**
     * @param string|array $filter Filter for querying records by
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    protected static function _parseFilter( $filter )
    {
        if ( empty( $filter ) )
        {
            return $filter;
        }

        if ( is_array( $filter ) )
        {
            throw new BadRequestException( 'Filtering in array format is not currently supported on SimpleDb.' );
        }

        // handle logical operators first
        $_search = array( ' || ', ' && ' );
        $_replace = array( ' or ', ' and ' );
        $filter = trim( str_ireplace( $_search, $_replace, $filter ) );

        // the rest should be comparison operators
        $_search = array( ' eq ', ' ne ', ' gte ', ' lte ', ' gt ', ' lt ' );
        $_replace = array( ' = ', ' != ', ' >= ', ' <= ', ' > ', ' < ' );
        $filter = trim( str_ireplace( $_search, $_replace, $filter ) );

        // check for x = null
        $filter = str_ireplace( ' = null', ' is null', $filter );
        // check for x != null
        $filter = str_ireplace( ' != null', ' is not null', $filter );

        return $filter;
    }

    /**
     * {@inheritdoc}
     */
    protected function initTransaction( $handle = null )
    {
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
        $_idsInfo = Option::get( $extras, 'ids_info' );
        $_idFields = Option::get( $extras, 'id_fields' );
        $_updates = Option::get( $extras, 'updates' );

        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                $_native = $this->_formatAttributes( $_parsed );
                $_batched = array( 'Name' => $id, 'Attributes' => $_native );

                /*$_result = */
                $this->_dbConn->putAttributes(
                    array(
                        static::TABLE_INDICATOR => $this->_transactionTable,
                        'ItemName'              => $id,
                        'Attributes'            => $_native,
                        'Expected'              => array( $_idFields[0] => array( 'Exists' => false ) )
                    )
                );

                if ( $rollback )
                {
                    $this->addToRollback( $id );
                }

                $_out = static::cleanRecord( $record, $_fields, $_idFields );
                break;
            case static::PUT:
                if ( !empty( $_updates ) )
                {
                    // only update by full records can use batching
                    $_updates[$_idFields[0]] = $id;
                    $record = $_updates;
                }

                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                $_native = $this->_formatAttributes( $_parsed, true );
                $_batched = array( 'Name' => $id, 'Attributes' => $_native );

                if ( !$continue && !$rollback )
                {
                    $_batched = array( 'Name' => $id, 'Attributes' => $_native );
                    return parent::addToTransaction( $_batched, $id );
                }

                $_result = $this->_dbConn->putAttributes(
                    array(
                        static::TABLE_INDICATOR => $this->_transactionTable,
                        'ItemName'              => $id,
                        'Attributes'            => $_native,
                    )
                );

                if ( $rollback )
                {
                    $_old = Option::get( $_result, 'Attributes', array() );
                    $this->addToRollback( $_old );
                }

                $_out = static::cleanRecord( $record, $_fields, $_idFields );
                break;

            case static::MERGE:
            case static::PATCH:
                if ( !empty( $_updates ) )
                {
                    $_updates[$_idFields[0]] = $id;
                    $record = $_updates;
                }

                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                $_native = $this->_formatAttributes( $_parsed, true );
            $_batched = array( 'Name' => $id, 'Attributes' => $_native );

                $_result = $this->_dbConn->putAttributes(
                    array(
                        static::TABLE_INDICATOR => $this->_transactionTable,
                        'ItemName'              => $id,
                        'Attributes'            => $_native,
                    )
                );

                if ( $rollback )
                {
                    $_old = Option::get( $_result, 'Attributes', array() );
                    $this->addToRollback( $_old );
                }

                $_out = static::cleanRecord( $record, $_fields, $_idFields );
                break;

            case static::DELETE:
                if ( !$continue && !$rollback )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_result = $this->_dbConn->deleteAttributes(
                    array(
                        static::TABLE_INDICATOR => $this->_transactionTable,
                        'ItemName'              => $id
                    )
                );

                $_temp = Option::get( $_result, 'Attributes', array() );

                if ( $rollback )
                {
                    $this->addToRollback( $_temp );
                }

                $_temp = $this->_unformatAttributes( $_temp );
                $_out = static::cleanRecord( $_temp, $_fields, $_idFields );
                break;

            case static::GET:
                $_scanProperties = array(
                    static::TABLE_INDICATOR => $this->_transactionTable,
                    'ItemName'              => $id,
                    'ConsistentRead'        => true,
                );

                $_fields = static::_buildAttributesToGet( $_fields, $_idFields );
                if ( !empty( $_fields ) )
                {
                    $_scanProperties['AttributeNames'] = $_fields;
                }

                $_result = $this->_dbConn->getAttributes( $_scanProperties );

                $_out = array_merge(
                    static::_unformatAttributes( $_result['Attributes'] ),
                    array( $_idFields[0] => $id )
                );
                break;
            default:
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

        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_fields = Option::get( $extras, 'fields' );
        $_requireMore = Option::get( $extras, 'require_more' );
        $_idsInfo = Option::get( $extras, 'ids_info' );
        $_idFields = Option::get( $extras, 'id_fields' );

        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
                $_result = $this->_dbConn->batchPutAttributes(
                    array(
                        static::TABLE_INDICATOR => $this->_transactionTable,
                        'Items'                 => $this->_batchRecords,
                    )
                );

                $_out = static::cleanRecords( $this->_batchRecords, $_fields, $_idFields );
                break;

            case static::PUT:
                $_result = $this->_dbConn->batchPutAttributes(
                    array(
                        static::TABLE_INDICATOR => $this->_transactionTable,
                        'Items'                 => $this->_batchRecords,
                    )
                );

                $_out = static::cleanRecords( $this->_batchRecords, $_fields, $_idFields );
                break;

            case static::MERGE:
            case static::PATCH:
                throw new BadRequestException( 'Batch operation not supported for patch.' );
                break;

            case static::DELETE:
                $_requests = array();
                foreach ( $this->_batchIds as $_id )
                {
                    $_record = array( $_idFields[0] => $_id );
                    $_out[] = $_record;
                    $_key = static::_buildKey( $_idsInfo, $_record );
                    $_requests[] = array( 'DeleteRequest' => array( 'Key' => $_key ) );
                }
                if ( $_requireMore )
                {
                    $_scanProperties = array(
                        'Items'          => $this->_batchRecords,
                        'ConsistentRead' => true,
                    );

                    $_attributes = static::_buildAttributesToGet( $_fields, $_idFields );
                    if ( !empty( $_attributes ) )
                    {
                        $_scanProperties['AttributesToGet'] = $_attributes;
                    }

                    // Get multiple items by key in a BatchGetItem request
                    $_result = $this->_dbConn->batchGetItem(
                        array(
                            'RequestItems' => array(
                                $this->_transactionTable => $_scanProperties
                            )
                        )
                    );

                    $_out = array();
                    $_items = $_result->getPath( "Responses/{$this->_transactionTable}" );
                    foreach ( $_items as $_item )
                    {
                        $_out[] = $this->_unformatAttributes( $_item );
                    }
                }

                /*$_result = */
                $this->_dbConn->batchDeleteAttributes(
                    array(
                        static::TABLE_INDICATOR => $this->_transactionTable,
                        'Items'                 => $this->_batchRecords
                    )
                );

                // todo check $_result['UnprocessedItems'] for 'DeleteRequest'
                break;

            case static::GET:
                $_keys = array();
                foreach ( $this->_batchIds as $_id )
                {
                    $_record = array( $_idFields[0] => $_id );
                    $_key = static::_buildKey( $_idsInfo, $_record );
                    $_keys[] = $_key;
                }

                $_scanProperties = array(
                    'Keys'           => $_keys,
                    'ConsistentRead' => true,
                );

                $_fields = static::_buildAttributesToGet( $_fields, $_idFields );
                if ( !empty( $_fields ) )
                {
                    $_scanProperties['AttributesToGet'] = $_fields;
                }

                // Get multiple items by key in a BatchGetItem request
                $_result = $this->_dbConn->batchGetItem(
                    array(
                        'RequestItems' => array(
                            $this->_transactionTable => $_scanProperties
                        )
                    )
                );

                $_items = $_result->getPath( "Responses/{$this->_transactionTable}" );
                foreach ( $_items as $_item )
                {
                    $_out[] = $this->formatRecordFromNative( $_item );
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
                    /* $_result = */
                    $this->_dbConn->batchDeleteAttributes(
                        array(
                            static::TABLE_INDICATOR => $this->_transactionTable,
                            'Items'                 => $this->_rollbackRecords
                        )
                    );
                    break;

                case static::PUT:
                case static::PATCH:
                case static::MERGE:
                case static::DELETE:
                    $_requests = array();
                    foreach ( $this->_rollbackRecords as $_item )
                    {
                        $_requests[] = array( 'PutRequest' => array( 'Item' => $_item ) );
                    }

                    /* $_result = */
                    $this->_dbConn->batchWriteItem( array( 'RequestItems' => array( $this->_transactionTable => $_requests ) ) );

                    // todo check $_result['UnprocessedItems'] for 'PutRequest'
                    break;

                default:
                    break;
            }

            $this->_rollbackRecords = array();
        }

        return true;
    }
}
