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
namespace DreamFactory\Platform\Utility;

use DreamFactory\Platform\Enums\PlatformStorageDrivers;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Interfaces\SqlDbDriverTypes;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Exceptions\StorageException;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;

/**
 * SqlDbUtilities
 * Generic database utilities
 */
class SqlDbUtilities extends DbUtilities implements SqlDbDriverTypes
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array
     */
    protected static $_tableNameCache;
    /**
     * @var array
     */
    protected static $_schemaCache;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param \CDbConnection $db
     *
     * @return int
     */
    public static function getDbDriverType( $db )
    {
        return PlatformStorageDrivers::driverType( $db->driverName );
    }

    /**
     * @param \CDbConnection $db
     * @param string         $name
     *
     * @throws BadRequestException
     * @throws NotFoundException
     * @return string
     */
    public static function correctTableName( $db, $name )
    {
        if ( false !== ( $_table = static::doesTableExist( $db, $name, true ) ) )
        {
            return $_table;
        }

        throw new NotFoundException( 'Table "' . $name . '" does not exist in the database.' );
    }

    /**
     * @param \CDbConnection|array $db         A database connection or the array of tables if you already have it pulled
     * @param string               $name       The name of the table to check
     * @param bool                 $returnName If true, the table name is returned instead of TRUE
     *
     * @throws \InvalidArgumentException
     * @return bool
     */
    public static function doesTableExist( $db, $name, $returnName = false )
    {
        if ( empty( $name ) )
        {
            throw new \InvalidArgumentException( 'Table name cannot be empty.' );
        }

        //  Build the lower-cased table array
        if ( is_array( $db ) )
        {
            $_tables = array();

            foreach ( $db as $_key => $_value )
            {
                $_key = is_numeric( $_key ) ? strtolower( $_value ) : strtolower( $_key );
                $_tables[$_key] = $_value;
            }
        }
        else
        {
            $_tables = static::_getCachedTables( $db );
        }

        //	Search normal, return real name
        if ( array_key_exists( $name, $_tables ) )
        {
            $_key = $name;
        }
        else if ( array_key_exists( strtolower( $name ), $_tables ) )
        {
            //  Search lower-cased, return real name
            $_key = strtolower( $name );
        }
        else
        {
            return false;
        }

        return $returnName ? $_tables[$_key] : true;
    }

    /**
     * @param \CDbConnection $db
     * @param string         $include
     * @param string         $exclude
     *
     * @return array
     * @throws \Exception
     */
    public static function listTables( $db, $include = null, $exclude = null )
    {
        //	Todo need to assess schemas in ms sql and load them separately.
        try
        {
            $_names = $db->schema->getTableNames();
            $includeArray = array_map( 'trim', explode( ',', strtolower( $include ) ) );
            $excludeArray = array_map( 'trim', explode( ',', strtolower( $exclude ) ) );
            $temp = array();

            foreach ( $_names as $name )
            {
                if ( !empty( $include ) )
                {
                    if ( false === array_search( strtolower( $name ), $includeArray ) )
                    {
                        continue;
                    }
                }
                elseif ( !empty( $exclude ) )
                {
                    if ( false !== array_search( strtolower( $name ), $excludeArray ) )
                    {
                        continue;
                    }
                }
                $temp[] = $name;
            }
            $_names = $temp;
            natcasesort( $_names );

            return array_values( $_names );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to list database tables.\n{$ex->getMessage()}" );
        }
    }

    /**
     * @param \CDbConnection $db
     * @param string         $include
     * @param string         $exclude
     *
     * @return array
     * @throws \Exception
     */
    public static function listStoredProcedures( $db, $include = null, $exclude = null )
    {
        try
        {
            $_names = $db->schema->getProcedureNames();
            $includeArray = array_map( 'trim', explode( ',', strtolower( $include ) ) );
            $excludeArray = array_map( 'trim', explode( ',', strtolower( $exclude ) ) );
            $temp = array();

            foreach ( $_names as $name )
            {
                if ( !empty( $include ) )
                {
                    if ( false === array_search( strtolower( $name ), $includeArray ) )
                    {
                        continue;
                    }
                }
                elseif ( !empty( $exclude ) )
                {
                    if ( false !== array_search( strtolower( $name ), $excludeArray ) )
                    {
                        continue;
                    }
                }
                $temp[] = $name;
            }
            $_names = $temp;
            natcasesort( $_names );

            return array_values( $_names );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to list database stored procedures.\n{$ex->getMessage()}" );
        }
    }

    /**
     * @param \CDbConnection $db
     * @param string         $name
     * @param array          $params
     * @param array          $schema
     * @param string         $wrapper
     *
     * @throws \Exception
     * @return array
     */
    public static function callProcedure( $db, $name, $params = null, $schema = null, $wrapper = null )
    {
        if ( empty( $name ) )
        {
            throw new BadRequestException( 'Stored procedure name can not be empty.' );
        }

        if ( is_array( $params ) )
        {
            foreach ( $params as $_key => $_param )
            {
                // overcome shortcomings of passed in data
                if ( null === $_pType = Option::get( $_param, 'param_type', null, false, true ) )
                {
                    $_pType = 'IN';
                    $params[$_key]['param_type'] = $_pType;
                }
                if ( null === $_pName = Option::get( $_param, 'name', null, false, true ) )
                {
                    if ( 0 !== strcasecmp( $_pName, 'IN' ) )
                    {
                        throw new BadRequestException( 'Stored procedure output parameter name can not be empty.' );
                    }

                    $_pName = 'param_' . $_key;
                    $params[$_key]['name'] = $_pName;
                }
                if ( null === $_pValue = Option::get( $_param, 'value', null ) )
                {
                    $_pValue = null;
                    $params[$_key]['value'] = $_pValue;
                }
                if ( null === $_rType = Option::get( $_param, 'type', null, false, true ) )
                {
                    $_rType = ( isset( $_pValue ) ) ? gettype( $_pValue ) : 'string';
                    $params[$_key]['type'] = $_rType;
                }
                if ( null === $_rLength = Option::get( $_param, 'length', null, false, true ) )
                {
                    $_rLength = 256;
                    switch ( $_rType )
                    {
                        case 'int':
                        case 'integer':
                            $_rLength = 12;
                            break;
                    }
                    $params[$_key]['length'] = $_rLength;
                }
            }
        }
        else
        {
            $params = array();
        }

        try
        {
            $_result = $db->schema->callProcedure( $name, $params );

            // convert result field values to types according to schema received
            if ( is_array( $schema ) && is_array( $_result ) )
            {
                foreach ( $_result as &$_row )
                {
                    if ( is_array( $_row ) )
                    {
                        foreach ( $_row as $_key => $_value )
                        {
                            if ( null !== $_type = Option::get( $schema, $_key, null, false, true ) )
                            {
                                $_row[$_key] = static::formatValue( $_value, $_type );
                            }
                        }
                    }
                }
            }

            // wrap the result set if desired
            if ( !empty( $wrapper ) )
            {
                $_result = array($wrapper => $_result);
            }

            // add back output parameters to results
            if ( !empty( $params ) )
            {
                foreach ( $params as $_param )
                {
                    $_pType = strtoupper( Option::get( $_param, 'param_type', 'IN' ) );
                    switch ( $_pType )
                    {
                        case 'INOUT':
                        case 'OUT':
                            $_name = $_param['name'];
                            $_type = $_param['type'];
                            if ( null !== $_value = Option::get( $_param, 'value', null ) )
                            {
                                $_value = static::formatValue( $_value, $_type );
                            }
                            $_result[$_name] = $_value;
                            break;
                    }
                }
            }

            return $_result;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to call database stored procedure.\n{$ex->getMessage()}" );
        }
    }

    /**
     * @param \CDbConnection $db
     * @param string         $include_prefix
     * @param string         $exclude_prefix
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array
     */
    public static function describeDatabase( $db, $include_prefix = null, $exclude_prefix = null )
    {
        try
        {
            $_names = $db->schema->getTableNames();
            $temp = array();
            foreach ( $_names as $name )
            {
                if ( !empty( $include_prefix ) )
                {
                    if ( 0 != substr_compare( $name, $include_prefix, 0, strlen( $include_prefix ), true ) )
                    {
                        continue;
                    }
                }
                elseif ( !empty( $exclude_prefix ) )
                {
                    if ( 0 == substr_compare( $name, $exclude_prefix, 0, strlen( $exclude_prefix ), true ) )
                    {
                        continue;
                    }
                }
                $temp[] = $name;
            }

            natcasesort( $_names );

            return $temp;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to query database schema.\n{$ex->getMessage()}" );
        }
    }

    /**
     * @param \CDbConnection $db
     * @param string         $name
     * @param string         $remove_prefix
     * @param null | array   $extras
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public static function describeTable( $db, $name, $remove_prefix = '', $extras = null )
    {
        $name = static::correctTableName( $db, $name );
        try
        {
            $table = $db->schema->getTable( $name );
            if ( !$table )
            {
                throw new NotFoundException( "Table '$name' does not exist in the database." );
            }

            $_driverType = static::getDbDriverType( $db );
            $extras = static::reformatFieldLabelArray( $extras );
            $labelInfo = Option::get( $extras, '', array() );

            $publicName = $table->name;
            $schemaName = $table->schemaName;
            if ( !empty( $schemaName ) )
            {
                switch ( $_driverType )
                {
                    case static::DRV_SQLSRV:
                        if ( 'dbo' !== $schemaName )
                        {
                            $publicName = $schemaName . '.' . $publicName;
                        }
                        break;
                    case static::DRV_PGSQL:
                        if ( 'public' !== $schemaName )
                        {
                            $publicName = $schemaName . '.' . $publicName;
                        }
                        break;
                    default:
                        $publicName = $schemaName . '.' . $publicName;
                        break;
                }
            }

            if ( !empty( $remove_prefix ) )
            {
                if ( substr( $publicName, 0, strlen( $remove_prefix ) ) == $remove_prefix )
                {
                    $publicName = substr( $publicName, strlen( $remove_prefix ), strlen( $publicName ) );
                }
            }

            $label = Option::get( $labelInfo, 'label', Inflector::camelize( $publicName, '_', true ) );
            $plural = Option::get( $labelInfo, 'plural', Inflector::pluralize( $label ) );
            $name_field = Option::get( $labelInfo, 'name_field' );

            return array(
                'name'        => $publicName,
                'label'       => $label,
                'plural'      => $plural,
                'primary_key' => $table->primaryKey,
                'name_field'  => $name_field,
                'field'       => static::describeTableFields( $db, $name, null, $extras ),
                'related'     => static::describeTableRelated( $db, $name )
            );
        }
        catch ( RestException $ex )
        {
            throw $ex;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to query database schema.\n{$ex->getMessage()}" );
        }
    }

    /**
     * @param \CDbConnection        $db
     * @param string                $table_name
     * @param null | string | array $field_names
     * @param null | array          $extras
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array
     */
    public static function describeTableFields( $db, $table_name, $field_names = null, $extras = null )
    {
        $table_name = static::correctTableName( $db, $table_name );
        $_table = $db->schema->getTable( $table_name );
        if ( !$_table )
        {
            throw new NotFoundException( "Table '$table_name' does not exist in the database." );
        }

        if ( !empty( $field_names ) )
        {
            $field_names = static::validateAsArray( $field_names, ',', true, 'No valid field names given.' );
        }
        $extras = static::reformatFieldLabelArray( $extras );

        $_out = array();
        try
        {
            foreach ( $_table->columns as $column )
            {

                if ( empty( $field_names ) || ( false !== array_search( $column->name, $field_names ) ) )
                {
                    $_info = Option::get( $extras, $column->name, array() );
                    $_out[] = static::describeFieldInternal( $column, $_table->foreignKeys, $_info );
                }
            }
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to query table field schema.\n{$ex->getMessage()}" );
        }

        if ( empty( $_out ) )
        {
            throw new NotFoundException( "No requested fields found in table '$table_name'." );
        }

        return $_out;
    }

    /**
     * @param \CDbColumnSchema $column
     * @param array            $foreign_keys
     * @param array            $label_info
     *
     * @throws \Exception
     * @return array
     */
    protected static function describeFieldInternal( $column, $foreign_keys, $label_info )
    {
        $label = Option::get( $label_info, 'label', Inflector::camelize( $column->name, '_', true ) );
        $validation = json_decode( Option::get( $label_info, 'validation' ), true );
        $picklist = Option::get( $label_info, 'picklist' );
        $picklist = ( !empty( $picklist ) ) ? explode( "\r", $picklist ) : array();
        $refTable = '';
        $refFields = '';
        if ( 1 == $column->isForeignKey )
        {
            $referenceTo = Option::get( $foreign_keys, $column->name );
            $refTable = Option::get( $referenceTo, 0 );
            $refFields = Option::get( $referenceTo, 1 );
        }

        return array(
            'name'               => $column->name,
            'label'              => $label,
            'type'               => static::_determineDfType( $column, $label_info ),
            'db_type'            => $column->dbType,
            'length'             => intval( $column->size ),
            'precision'          => intval( $column->precision ),
            'scale'              => intval( $column->scale ),
            'default'            => $column->defaultValue,
            'required'           => static::determineRequired( $column ),
            'allow_null'         => $column->allowNull,
            'fixed_length'       => static::determineIfFixedLength( $column->dbType ),
            'supports_multibyte' => static::determineMultiByteSupport( $column->dbType ),
            'auto_increment'     => $column->autoIncrement,
            'is_primary_key'     => $column->isPrimaryKey,
            'is_foreign_key'     => $column->isForeignKey,
            'ref_table'          => $refTable,
            'ref_fields'         => $refFields,
            'validation'         => $validation,
            'value'              => $picklist
        );
    }

    /**
     * @param \CDbConnection $db
     * @param                $parent_table
     *
     * @return array
     * @throws \Exception
     */
    public static function describeTableRelated( $db, $parent_table )
    {
        $names = $db->schema->getTableNames();
        natcasesort( $names );
        $names = array_values( $names );
        $related = array();
        foreach ( $names as $name )
        {
            $table = $db->schema->getTable( $name );
            if ( !$table )
            {
                throw new NotFoundException( "Table '$name' does not exist in the database." );
            }
            $fks = $fks2 = $table->foreignKeys;
            foreach ( $fks as $key => $value )
            {
                $refTable = Option::get( $value, 0 );
                $refField = Option::get( $value, 1 );
                if ( 0 == strcasecmp( $refTable, $parent_table ) )
                {
                    // other, must be has_many or many_many
                    $relationName = Inflector::pluralize( $name ) . '_by_' . $key;
                    $related[] = array(
                        'name'      => $relationName,
                        'type'      => 'has_many',
                        'ref_table' => $name,
                        'ref_field' => $key,
                        'field'     => $refField
                    );
                    // if other has many relationships exist, we can say these are related as well
                    foreach ( $fks2 as $key2 => $value2 )
                    {
                        $tmpTable = Option::get( $value2, 0 );
                        $tmpField = Option::get( $value2, 1 );
                        if ( ( 0 != strcasecmp( $key, $key2 ) ) && // not same key
                             ( 0 != strcasecmp( $tmpTable, $name ) ) && // not self-referencing table
                             ( 0 != strcasecmp( $parent_table, $name ) )
                        )
                        { // not same as parent, i.e. via reference back to self
                            // not the same key
                            $relationName = Inflector::pluralize( $tmpTable ) . '_by_' . $name;
                            $related[] = array(
                                'name'      => $relationName,
                                'type'      => 'many_many',
                                'ref_table' => $tmpTable,
                                'ref_field' => $tmpField,
                                'join'      => "$name($key,$key2)",
                                'field'     => $refField
                            );
                        }
                    }
                }
                if ( 0 == strcasecmp( $name, $parent_table ) )
                {
                    // self, get belongs to relations
                    $relationName = $refTable . '_by_' . $key;
                    $related[] = array(
                        'name'      => $relationName,
                        'type'      => 'belongs_to',
                        'ref_table' => $refTable,
                        'ref_field' => $refField,
                        'field'     => $key
                    );
                }
            }
        }

        return $related;
    }

    /**
     * @param            $column
     * @param null|array $label_info
     *
     * @return string
     */
    protected static function _determineDfType( $column, $label_info = null )
    {
        $_simpleType = static::determineGenericType( $column );

        switch ( $_simpleType )
        {
            case 'integer':
                if ( $column->isPrimaryKey && $column->autoIncrement )
                {
                    return 'id';
                }

                if ( isset( $label_info['user_id_on_update'] ) )
                {
                    return
                        'user_id_on_' . ( Option::getBool( $label_info, 'user_id_on_update' ) ? 'update' : 'create' );
                }

                if ( null !== Option::get( $label_info, 'user_id' ) )
                {
                    return 'user_id';
                }

                if ( $column->isForeignKey )
                {
                    return 'reference';
                }
                break;

            case 'datetimeoffset':
            case 'timestamp':
                if ( isset( $label_info['timestamp_on_update'] ) )
                {
                    return
                        'timestamp_on_' .
                        ( Option::getBool( $label_info, 'timestamp_on_update' ) ? 'update' : 'create' );
                }
                break;
        }

        if ( ( 0 == strcasecmp( $column->dbType, 'datetimeoffset' ) ) ||
             ( 0 == strcasecmp( $column->dbType, 'timestamp' ) )
        )
        {
            if ( isset( $label_info['timestamp_on_update'] ) )
            {
                return
                    'timestamp_on_' . ( Option::getBool( $label_info, 'timestamp_on_update' ) ? 'update' : 'create' );
            }
        }

        return $_simpleType;
    }

    /**
     * @param $type
     *
     * @return bool
     */
    protected static function determineMultiByteSupport( $type )
    {
        switch ( $type )
        {
            case 'nchar':
            case 'nvarchar':
                return true;
            // todo mysql shows these are varchar with a utf8 character set, not in column data
            default:
                return false;
        }
    }

    /**
     * @param $type
     *
     * @return bool
     */
    protected static function determineIfFixedLength( $type )
    {
        switch ( $type )
        {
            case 'char':
            case 'nchar':
            case 'binary':
                return true;
            default:
                return false;
        }
    }

    /**
     * @param $column
     *
     * @return bool
     */
    protected static function determineRequired( $column )
    {
        if ( ( 1 == $column->allowNull ) || ( isset( $column->defaultValue ) ) || ( 1 == $column->autoIncrement ) )
        {
            return false;
        }

        return true;
    }

    /**
     * @param     $field
     * @param int $driver_type
     *
     * @throws \Exception
     * @return array|string
     */
    protected static function buildColumnType( $field, $driver_type = self::DRV_MYSQL )
    {
        if ( empty( $field ) )
        {
            throw new BadRequestException( "No field given." );
        }

        try
        {
            $sql = Option::get( $field, 'sql' );
            if ( !empty( $sql ) )
            {
                // raw sql definition, just pass it on
                return $sql;
            }
            $type = Option::get( $field, 'type' );
            if ( empty( $type ) )
            {
                throw new BadRequestException( "Invalid schema detected - no type element." );
            }
            /* abstract types handled by yii directly for each driver type

                pk: a generic primary key type, will be converted into int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY for MySQL;
                string: string type, will be converted into varchar(255) for MySQL;
                text: text type (long string), will be converted into text for MySQL;
                integer: integer type, will be converted into int(11) for MySQL;
                float: floating number type, will be converted into float for MySQL;
                decimal: decimal number type, will be converted into decimal for MySQL;
                datetime: datetime type, will be converted into datetime for MySQL;
                timestamp: timestamp type, will be converted into timestamp for MySQL;
                time: time type, will be converted into time for MySQL;
                date: date type, will be converted into date for MySQL;
                binary: binary data type, will be converted into blob for MySQL;
                boolean: boolean type, will be converted into tinyint(1) for MySQL;
                money: money/currency type, will be converted into decimal(19,4) for MySQL.
            */

            if ( ( 0 == strcasecmp( 'id', $type ) ) || ( 0 == strcasecmp( 'pk', $type ) ) )
            {
                return 'pk'; // simple primary key
            }

            $allowNull = Option::getBool( $field, 'allow_null', true );
            $length = Option::get( $field, 'length' );
            if ( !isset( $length ) )
            {
                $length = Option::get( $field, 'size' ); // alias
            }
            $default = Option::get( $field, 'default' );
            $quoteDefault = false;
            $isPrimaryKey = Option::getBool( $field, 'is_primary_key', false );

            switch ( strtolower( $type ) )
            {
                // some types need massaging, some need other required properties
                case "reference":
                    $definition = 'int';
                    break;
                case "timestamp_on_create":
                    switch ( $driver_type )
                    {
                        case SqlDbUtilities::DRV_SQLSRV:
                        case SqlDbUtilities::DRV_DBLIB:
                            $definition = 'datetimeoffset';
                            $default = 'getdate()';
                            break;
                        case SqlDbUtilities::DRV_PGSQL:
                            $definition = 'timestamp';
                            $default = 'current_timestamp';
                            break;
                        default:
                            $definition = 'timestamp';
                            $default = 0;
                            break;
                    }
                    $allowNull = ( isset( $field['allow_null'] ) ) ? $allowNull : false;
                    break;
                case "timestamp_on_update":
                    switch ( $driver_type )
                    {
                        case SqlDbUtilities::DRV_SQLSRV:
                        case SqlDbUtilities::DRV_DBLIB:
                            $definition = 'datetimeoffset';
                            $default = 'getdate()';
                            break;
                        case SqlDbUtilities::DRV_PGSQL:
                            $definition = 'timestamp';
                            $default = 'current_timestamp';
                            break;
                        default:
                            $definition = 'timestamp';
                            $default = 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
                            break;
                    }
                    $allowNull = ( isset( $field['allow_null'] ) ) ? $allowNull : false;
                    break;
                case "user_id":
                case "user_id_on_create":
                case "user_id_on_update":
                    $definition = 'int';
                    $allowNull = ( isset( $field['allow_null'] ) ) ? $allowNull : false;
                    break;
                // numbers
                case 'bit': // ms sql alias
                case 'bool': // alias
                case 'boolean': // alias
                    $definition = 'boolean';
                    // convert to bit 0 or 1
                    $default = ( isset( $default ) ) ? intval( Scalar::boolval( $default ) ) : $default;
                    break;
                case 'tinyint':
                case 'smallint':
                case 'mediumint':
                case 'int':
                case 'bigint':
                case 'integer':
                    $definition =
                        ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                            ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) && ( 'mediumint' == $type ) ) ? 'int'
                            : $type;
                    if ( isset( $length ) )
                    {
                        if ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                               ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) && ( $length <= 255 ) && ( $length > 0 )
                        )
                        {
                            $definition .= '(' . intval( $length ) . ')'; // sets the viewable length
                        }
                    }
                    // convert to int
                    $default = ( isset( $default ) ) ? intval( $default ) : $default;
                    break;
                case 'decimal':
                case 'numeric': // alias
                case 'number': // alias
                case 'percent': // alias
                    $definition = 'decimal';
                    if ( !isset( $length ) )
                    {
                        $length = Option::get( $field, 'precision' ); // alias
                    }
                    if ( isset( $length ) )
                    {
                        $length = intval( $length );
                        if ( ( ( SqlDbUtilities::DRV_MYSQL == $driver_type ) && ( $length > 65 ) ) ||
                             ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                                 ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) && ( $length > 38 ) )
                        )
                        {
                            throw new BadRequestException( "Decimal precision '$length' is out of valid range." );
                        }
                        $scale = Option::get( $field, 'scale' );
                        if ( empty( $scale ) )
                        {
                            $scale = Option::get( $field, 'decimals' ); // alias
                        }
                        if ( !empty( $scale ) )
                        {
                            if ( ( ( SqlDbUtilities::DRV_MYSQL == $driver_type ) && ( $scale > 30 ) ) ||
                                 ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                                     ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) && ( $scale > 18 ) ) ||
                                 ( $scale > $length )
                            )
                            {
                                throw new BadRequestException( "Decimal scale '$scale' is out of valid range." );
                            }
                            $definition .= "($length,$scale)";
                        }
                        else
                        {
                            $definition .= "($length)";
                        }
                    }
                    // convert to float
                    $default = ( isset( $default ) ) ? floatval( $default ) : $default;
                    break;
                case 'float':
                case 'double':
                    $definition =
                        ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                            ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) ) ? 'float' : $type;
                    if ( !isset( $length ) )
                    {
                        $length = Option::get( $field, 'precision' ); // alias
                    }
                    if ( isset( $length ) )
                    {
                        $length = intval( $length );
                        if ( ( ( SqlDbUtilities::DRV_MYSQL == $driver_type ) && ( $length > 53 ) ) ||
                             ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                                 ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) && ( $length > 38 ) )
                        )
                        {
                            throw new BadRequestException( "Decimal precision '$length' is out of valid range." );
                        }
                        $scale = Option::get( $field, 'scale' );
                        if ( empty( $scale ) )
                        {
                            $scale = Option::get( $field, 'decimals' ); // alias
                        }
                        if ( !empty( $scale ) &&
                             !( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                                ( SqlDbUtilities::DRV_DBLIB == $driver_type ) )
                        )
                        {
                            if ( ( ( SqlDbUtilities::DRV_MYSQL == $driver_type ) && ( $scale > 30 ) ) ||
                                 ( $scale > $length )
                            )
                            {
                                throw new BadRequestException( "Decimal scale '$scale' is out of valid range." );
                            }
                            $definition .= "($length,$scale)";
                        }
                        else
                        {
                            $definition .= "($length)";
                        }
                    }
                    // convert to float
                    $default = ( isset( $default ) ) ? floatval( $default ) : $default;
                    break;
                case 'money':
                case 'smallmoney':
                    $definition =
                        ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                            ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) ) ? $type : 'money'; // let yii handle it
                    // convert to float
                    $default = ( isset( $default ) ) ? floatval( $default ) : $default;
                    break;
                // string types
                case 'string':
                case 'binary':
                case 'varbinary':
                case 'char':
                case 'varchar':
                case 'nchar':
                case 'nvarchar':
                    $fixed = Option::getBool( $field, 'fixed_length' );
                    $national = Option::getBool( $field, 'supports_multibyte' );
                    if ( 0 == strcasecmp( 'string', $type ) )
                    {
                        if ( $fixed )
                        {
                            $type = ( $national ) ? 'nchar' : 'char';
                        }
                        else
                        {
                            $type = ( $national ) ? 'nvarchar' : 'varchar';
                        }
                        if ( !isset( $length ) )
                        {
                            $length = 255;
                        }
                    }
                    elseif ( 0 == strcasecmp( 'binary', $type ) )
                    {
                        $type = ( $fixed ) ? 'binary' : 'varbinary';
                        if ( !isset( $length ) )
                        {
                            $length = 255;
                        }
                    }
                    $definition = $type;
                    switch ( $type )
                    {
                        case 'varbinary':
                        case 'varchar':
                            if ( isset( $length ) )
                            {
                                $length = intval( $length );
                                if ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                                       ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) && ( $length > 8000 )
                                )
                                {
                                    $length = 'max';
                                }
                                if ( ( SqlDbUtilities::DRV_MYSQL == $driver_type ) && ( $length > 65535 ) )
                                {
                                    // max allowed is really dependent number of string columns
                                    throw new BadRequestException( "String length '$length' is out of valid range." );
                                }
                                $definition .= "($length)";
                            }
                            break;
                        case 'binary':
                        case 'char':
                            if ( isset( $length ) )
                            {
                                $length = intval( $length );
                                if ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                                       ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) && ( $length > 8000 )
                                )
                                {
                                    throw new BadRequestException( "String length '$length' is out of valid range." );
                                }
                                if ( ( SqlDbUtilities::DRV_MYSQL == $driver_type ) && ( $length > 255 ) )
                                {
                                    throw new BadRequestException( "String length '$length' is out of valid range." );
                                }
                                $definition .= "($length)";
                            }
                            break;
                        case 'nvarchar':
                            if ( isset( $length ) )
                            {
                                $length = intval( $length );
                                if ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                                       ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) && ( $length > 4000 )
                                )
                                {
                                    $length = 'max';
                                }
                                if ( ( SqlDbUtilities::DRV_MYSQL == $driver_type ) && ( $length > 65535 ) )
                                {
                                    // max allowed is really dependent number of string columns
                                    throw new BadRequestException( "String length '$length' is out of valid range." );
                                }
                                $definition .= "($length)";
                            }
                            break;
                        case 'nchar':
                            if ( isset( $length ) )
                            {
                                $length = intval( $length );
                                if ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                                       ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) && ( $length > 4000 )
                                )
                                {
                                    throw new BadRequestException( "String length '$length' is out of valid range." );
                                }
                                if ( ( SqlDbUtilities::DRV_MYSQL == $driver_type ) && ( $length > 255 ) )
                                {
                                    throw new BadRequestException( "String length '$length' is out of valid range." );
                                }
                                $definition .= "($length)";
                            }
                            break;
                    }
                    $quoteDefault = true;
                    break;
                case 'text':
                    $definition =
                        ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                            ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) ) ? 'varchar(max)'
                            : 'text'; // microsoft recommended
                    $quoteDefault = true;
                    break;
                case 'blob':
                    $definition =
                        ( ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                            ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) ) ? 'varbinary(max)'
                            : 'blob'; // microsoft recommended
                    $quoteDefault = true;
                    break;
                case 'datetime':
                    $definition =
                        ( ( SqlDbUtilities::DRV_SQLSRV == $driver_type ) ||
                          ( SqlDbUtilities::DRV_DBLIB == $driver_type ) ) ? 'datetime2'
                            : 'datetime'; // microsoft recommends
                    break;
                default:
                    // blind copy of column type
                    $definition = $type;
            }

            // additional properties
            if ( !Scalar::boolval( $allowNull ) )
            {
                $definition .= ' NOT';
            }
            $definition .= ' NULL';
            if ( isset( $default ) )
            {
                if ( $quoteDefault )
                {
                    $default = "'" . $default . "'";
                }
                $definition .= ' DEFAULT ' . $default;
            }
            if ( $isPrimaryKey )
            {
                $definition .= ' PRIMARY KEY';
            }

            return $definition;
        }
        catch ( \Exception $ex )
        {
            throw $ex;
        }
    }

    /**
     * @param \CDbConnection       $db
     * @param string               $table_name
     * @param array                $fields
     * @param null|\CDbTableSchema $schema
     * @param bool                 $allow_update
     * @param bool                 $allow_delete
     *
     * @throws \Exception
     * @return string
     */
    protected static function buildTableFields( $db, $table_name, $fields, $schema = null, $allow_update = false, $allow_delete = false )
    {
        if ( empty( $fields ) )
        {
            throw new BadRequestException( "No fields given." );
        }

        $_driverType = static::getDbDriverType( $db );
        $columns = array();
        $alter_columns = array();
        $references = array();
        $indexes = array();
        $labels = array();
        $primaryKey = '';
        if ( isset( $schema ) )
        {
            $primaryKey = $schema->primaryKey;
        }
        if ( !isset( $fields[0] ) )
        {
            $fields = array($fields);
        }

        $drop_columns = array();
        if ( $allow_delete )
        {
            foreach ( $schema->columnNames as $_oldName )
            {
                $_found = false;
                foreach ( $fields as $field )
                {
                    $_name = strval( Option::get( $field, 'name' ) );
                    if ( 0 === strcasecmp( $_name, $_oldName ) )
                    {
                        $_found = true;
                        break;
                    }
                }
                if ( !$_found )
                {
                    $drop_columns[] = $_oldName;
                }
            }
        }
        foreach ( $fields as $field )
        {
            try
            {
                $name = Option::get( $field, 'name' );
                if ( empty( $name ) )
                {
                    throw new BadRequestException( "Invalid schema detected - no name element." );
                }
                $type = Option::get( $field, 'type', '' );
                $colSchema = ( isset( $schema ) ) ? $schema->getColumn( $name ) : null;
                $isAlter = false;
                if ( isset( $colSchema ) )
                {
                    if ( !$allow_update )
                    {
                        throw new BadRequestException( "Field '$name' already exists in table '$table_name'." );
                    }
                    if ( ( ( 0 == strcasecmp( 'id', $type ) ) ||
                           ( 0 == strcasecmp( 'pk', $type ) ) ||
                           Option::getBool( $field, 'is_primary_key' ) ) && ( $colSchema->isPrimaryKey )
                    )
                    {
                        // don't try to alter
                    }
                    else
                    {
                        $definition = static::buildColumnType( $field, $_driverType );
                        if ( !empty( $definition ) )
                        {
                            $alter_columns[$name] = $definition;
                        }
                    }
                    $isAlter = true;
                    // todo manage type changes, data migration?
                }
                else
                {
                    $definition = static::buildColumnType( $field, $_driverType );
                    if ( !empty( $definition ) )
                    {
                        $columns[$name] = $definition;
                    }
                }

                // extra checks
                if ( empty( $type ) )
                {
                    // raw definition, just pass it on
                    if ( $isAlter )
                    {
                        // may need to clean out references, etc?
                    }
                    continue;
                }

                $temp = array();
                if ( ( 0 == strcasecmp( 'id', $type ) ) || ( 0 == strcasecmp( 'pk', $type ) ) )
                {
                    if ( !empty( $primaryKey ) && ( 0 != strcasecmp( $primaryKey, $name ) ) )
                    {
                        throw new BadRequestException( "Designating more than one column as a primary key is not allowed." );
                    }
                    $primaryKey = $name;
                }
                elseif ( Option::getBool( $field, 'is_primary_key' ) )
                {
                    if ( !empty( $primaryKey ) && ( 0 != strcasecmp( $primaryKey, $name ) ) )
                    {
                        throw new BadRequestException( "Designating more than one column as a primary key is not allowed." );
                    }
                    $primaryKey = $name;
                }
                elseif ( ( 0 == strcasecmp( 'reference', $type ) ) || Option::getBool( $field, 'is_foreign_key' )
                )
                {
                    // special case for references because the table referenced may not be created yet
                    $refTable = Option::get( $field, 'ref_table' );
                    if ( empty( $refTable ) )
                    {
                        throw new BadRequestException( "Invalid schema detected - no table element for reference type of $name." );
                    }
                    $refColumns = Option::get( $field, 'ref_fields', 'id' );
                    $refOnDelete = Option::get( $field, 'ref_on_delete' );
                    $refOnUpdate = Option::get( $field, 'ref_on_update' );

                    // will get to it later, $refTable may not be there
                    $keyName = 'fk_' . $table_name . '_' . $name;
                    if ( !$isAlter || !$colSchema->isForeignKey )
                    {
                        $references[] = array(
                            'name'       => $keyName,
                            'table'      => $table_name,
                            'column'     => $name,
                            'ref_table'  => $refTable,
                            'ref_fields' => $refColumns,
                            'delete'     => $refOnDelete,
                            'update'     => $refOnUpdate
                        );
                    }
                }
                elseif ( ( 0 == strcasecmp( 'user_id_on_create', $type ) ) )
                { // && static::is_local_db()
                    // special case for references because the table referenced may not be created yet
                    $temp['user_id_on_update'] = false;
                    $keyName = 'fk_' . $table_name . '_' . $name;
                    if ( !$isAlter || !$colSchema->isForeignKey )
                    {
                        $references[] = array(
                            'name'       => $keyName,
                            'table'      => $table_name,
                            'column'     => $name,
                            'ref_table'  => 'df_sys_user',
                            'ref_fields' => 'id',
                            'delete'     => null,
                            'update'     => null
                        );
                    }
                }
                elseif ( ( 0 == strcasecmp( 'user_id_on_update', $type ) ) )
                { // && static::is_local_db()
                    // special case for references because the table referenced may not be created yet
                    $temp['user_id_on_update'] = true;
                    $keyName = 'fk_' . $table_name . '_' . $name;
                    if ( !$isAlter || !$colSchema->isForeignKey )
                    {
                        $references[] = array(
                            'name'       => $keyName,
                            'table'      => $table_name,
                            'column'     => $name,
                            'ref_table'  => 'df_sys_user',
                            'ref_fields' => 'id',
                            'delete'     => null,
                            'update'     => null
                        );
                    }
                }
                elseif ( ( 0 == strcasecmp( 'user_id', $type ) ) )
                { // && static::is_local_db()
                    // special case for references because the table referenced may not be created yet
                    $temp['user_id'] = true;
                    $keyName = 'fk_' . $table_name . '_' . $name;
                    if ( !$isAlter || !$colSchema->isForeignKey )
                    {
                        $references[] = array(
                            'name'       => $keyName,
                            'table'      => $table_name,
                            'column'     => $name,
                            'ref_table'  => 'df_sys_user',
                            'ref_fields' => 'id',
                            'delete'     => null,
                            'update'     => null
                        );
                    }
                }
                elseif ( ( 0 == strcasecmp( 'timestamp_on_create', $type ) ) )
                {
                    $temp['timestamp_on_update'] = false;
                }
                elseif ( ( 0 == strcasecmp( 'timestamp_on_update', $type ) ) )
                {
                    $temp['timestamp_on_update'] = true;
                }
                // regardless of type
                if ( Option::getBool( $field, 'is_unique' ) )
                {
                    // will get to it later, create after table built
                    $keyName = 'undx_' . $table_name . '_' . $name;
                    $indexes[] = array(
                        'name'   => $keyName,
                        'table'  => $table_name,
                        'column' => $name,
                        'unique' => true,
                        'drop'   => $isAlter
                    );
                }
                elseif ( Option::get( $field, 'is_index' ) )
                {
                    // will get to it later, create after table built
                    $keyName = 'ndx_' . $table_name . '_' . $name;
                    $indexes[] = array(
                        'name'   => $keyName,
                        'table'  => $table_name,
                        'column' => $name,
                        'drop'   => $isAlter
                    );
                }

                $picklist = '';
                $values = Option::get( $field, 'value' );
                if ( empty( $values ) )
                {
                    $values = ( isset( $field['values']['value'] ) ) ? $field['values']['value'] : array();
                }
                if ( !is_array( $values ) )
                {
                    $values = array_map( 'trim', explode( ',', trim( $values, ',' ) ) );
                }
                if ( !empty( $values ) )
                {
                    foreach ( $values as $value )
                    {
                        if ( !empty( $picklist ) )
                        {
                            $picklist .= "\r";
                        }
                        $picklist .= $value;
                    }
                }
                if ( !empty( $picklist ) )
                {
                    $temp['picklist'] = $picklist;
                }

                // labels
                $label = Option::get( $field, 'label' );
                if ( !empty( $label ) )
                {
                    $temp['label'] = $label;
                }

                $validation = Option::get( $field, 'validation' );
                if ( !empty( $validation ) )
                {
                    $temp['validation'] = json_encode( $validation );
                }

                if ( !empty( $temp ) )
                {
                    $temp['table'] = $table_name;
                    $temp['field'] = $name;
                    $labels[] = $temp;
                }
            }
            catch ( \Exception $ex )
            {
                throw $ex;
            }
        }

        return array(
            'columns'       => $columns,
            'alter_columns' => $alter_columns,
            'drop_columns'  => $drop_columns,
            'references'    => $references,
            'indexes'       => $indexes,
            'labels'        => $labels
        );
    }

    /**
     * @param \CDbConnection $db
     * @param array          $extras
     *
     * @return array
     */
    protected static function createFieldExtras( $db, $extras )
    {
        $command = $db->createCommand();
        $references = Option::get( $extras, 'references', array() );
        if ( !empty( $references ) )
        {
            foreach ( $references as $reference )
            {
                $command->reset();
                $name = $reference['name'];
                $table = $reference['table'];
                $drop = Option::getBool( $reference, 'drop' );
                if ( $drop )
                {
                    try
                    {
                        $command->dropForeignKey( $name, $table );
                    }
                    catch ( \Exception $ex )
                    {
                        \Yii::log( $ex->getMessage() );
                    }
                }
                // add new reference
                $refTable = Option::get( $reference, 'ref_table' );
                if ( !empty( $refTable ) )
                {
                    if ( ( 0 == strcasecmp( 'df_sys_user', $refTable ) ) && ( $db != Pii::db() ) )
                    {
                        // using user id references from a remote db
                        continue;
                    }
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $rows = $command->addForeignKey(
                        $name,
                        $table,
                        $reference['column'],
                        $refTable,
                        $reference['ref_fields'],
                        $reference['delete'],
                        $reference['update']
                    );
                }
            }
        }
        $indexes = Option::get( $extras, 'indexes', array() );
        if ( !empty( $indexes ) )
        {
            foreach ( $indexes as $index )
            {
                $command->reset();
                $name = $index['name'];
                $table = $index['table'];
                $drop = Option::getBool( $index, 'drop' );
                if ( $drop )
                {
                    try
                    {
                        $command->dropIndex( $name, $table );
                    }
                    catch ( \Exception $ex )
                    {
                        \Yii::log( $ex->getMessage() );
                    }
                }
                $unique = Option::getBool( $index, 'unique' );

                /** @noinspection PhpUnusedLocalVariableInspection */
                $rows = $command->createIndex( $name, $table, $index['column'], $unique );
            }
        }
    }

    /**
     * @param \CDbConnection $db
     * @param string         $table_name
     * @param array          $fields
     * @param bool           $allow_update
     * @param bool           $allow_delete
     *
     * @return array
     * @throws \Exception
     */
    public static function updateFields( $db, $table_name, $fields, $allow_update = false, $allow_delete = false )
    {
        if ( empty( $table_name ) )
        {
            throw new BadRequestException( "Table schema received does not have a valid name." );
        }
        // does it already exist
        if ( !static::doesTableExist( $db, $table_name ) )
        {
            throw new NotFoundException( "Update schema called on a table with name '$table_name' that does not exist in the database." );
        }

        $schema = $db->schema->getTable( $table_name );
        if ( !$table_name )
        {
            throw new NotFoundException( "Table '$table_name' does not exist in the database." );
        }
        try
        {
            $names = array();
            $results = static::buildTableFields( $db, $table_name, $fields, $schema, $allow_update, $allow_delete );
            $command = $db->createCommand();
            $columns = Option::get( $results, 'columns', array() );
            foreach ( $columns as $name => $definition )
            {
                $command->reset();
                $command->addColumn( $table_name, $name, $definition );
                $names[] = $name;
            }
            $columns = Option::get( $results, 'alter_columns', array() );
            foreach ( $columns as $name => $definition )
            {
                $command->reset();
                $command->alterColumn( $table_name, $name, $definition );
                $names[] = $name;
            }
            $columns = Option::get( $results, 'drop_columns', array() );
            foreach ( $columns as $name )
            {
                $command->reset();
                $command->dropColumn( $table_name, $name );
                $names[] = $name;
            }
            static::createFieldExtras( $db, $results );

            $labels = Option::get( $results, 'labels', array() );

            // refresh the schema that we just added
            $db->schema->refresh();
            static::_getCachedTables( $db, true );

            return array('names' => $names, 'labels' => $labels);
        }
        catch ( \Exception $ex )
        {
            Log::error( 'Exception creating fields: ' . $ex->getMessage() );
            throw $ex;
        }
    }

    /**
     * @param \CDbConnection $db
     * @param string         $table_name
     * @param array          $data
     * @param bool           $checkExist
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     * @return array
     */
    protected static function createTable( $db, $table_name, $data, $checkExist = true )
    {
        if ( empty( $table_name ) )
        {
            throw new BadRequestException( "Table schema received does not have a valid name." );
        }

        // does it already exist
        if ( true === $checkExist && static::doesTableExist( $db, $table_name ) )
        {
            throw new BadRequestException( "A table with name '$table_name' already exist in the database." );
        }

        $fields = Option::get( $data, 'field', array() );

        if ( empty( $fields ) )
        {
            $fields = ( isset( $data['fields']['field'] ) ) ? $data['fields']['field'] : array();
        }

        if ( empty( $fields ) )
        {
            throw new BadRequestException( "No valid fields exist in the received table schema." );
        }

        if ( !isset( $fields[0] ) )
        {
            $fields = array($fields);
        }

        try
        {
            $results = static::buildTableFields( $db, $table_name, $fields );
            $columns = Option::get( $results, 'columns', array() );

            if ( empty( $columns ) )
            {
                throw new BadRequestException( "No valid fields exist in the received table schema." );
            }

            $command = $db->createCommand();
            $command->createTable( $table_name, $columns );

            $labels = Option::get( $results, 'labels', array() );
            // add table labels
            $label = Option::get( $data, 'label' );
            $plural = Option::get( $data, 'plural' );
            if ( !empty( $label ) || !empty( $plural ) )
            {
                $labels[] = array(
                    'table'  => $table_name,
                    'field'  => '',
                    'label'  => $label,
                    'plural' => $plural
                );
            }
            $results['labels'] = $labels;

            return $results;
        }
        catch ( \Exception $ex )
        {
            Log::error( 'Exception creating table: ' . $ex->getMessage() );
            throw $ex;
        }
    }

    /**
     * @param \CDbConnection $db
     * @param string         $table_name
     * @param array          $data
     * @param bool           $allow_delete
     *
     * @throws \Exception
     * @return array
     */
    protected static function updateTable( $db, $table_name, $data, $allow_delete = false )
    {
        if ( empty( $table_name ) )
        {
            throw new BadRequestException( "Table schema received does not have a valid name." );
        }
        // does it already exist
        if ( !static::doesTableExist( $db, $table_name ) )
        {
            throw new BadRequestException( "Update schema called on a table with name '$table_name' that does not exist in the database." );
        }

        //  Is there a name update
        $newName = Option::get( $data, 'new_name' );

        if ( !empty( $newName ) )
        {
            // todo change table name, has issue with references
        }

        // update column types
        $fields = Option::get( $data, 'field', array() );
        if ( empty( $fields ) )
        {
            $fields = ( isset( $data['fields']['field'] ) ) ? $data['fields']['field'] : array();
        }
        try
        {
            $command = $db->createCommand();
            $labels = array();
            $references = array();
            $indexes = array();
            if ( !empty( $fields ) )
            {
                $schema = $db->schema->getTable( $table_name );
                if ( !$table_name )
                {
                    throw new NotFoundException( "Table '$table_name' does not exist in the database." );
                }
                $results = static::buildTableFields( $db, $table_name, $fields, $schema, true, $allow_delete );
                $columns = Option::get( $results, 'columns', array() );
                foreach ( $columns as $name => $definition )
                {
                    $command->reset();
                    $command->addColumn( $table_name, $name, $definition );
                }
                $columns = Option::get( $results, 'alter_columns', array() );
                foreach ( $columns as $name => $definition )
                {
                    $command->reset();
                    $command->alterColumn( $table_name, $name, $definition );
                }
                $columns = Option::get( $results, 'drop_columns', array() );
                foreach ( $columns as $name )
                {
                    $command->reset();
                    $command->dropColumn( $table_name, $name );
                }

                $labels = Option::get( $results, 'labels', array() );
                $references = Option::get( $results, 'references', array() );
                $indexes = Option::get( $results, 'indexes', array() );
            }
            // add table labels
            $label = Option::get( $data, 'label' );
            $plural = Option::get( $data, 'plural' );
            if ( !empty( $label ) || !empty( $plural ) )
            {
                $labels[] = array(
                    'table'  => $table_name,
                    'field'  => '',
                    'label'  => $label,
                    'plural' => $plural
                );
            }

            $results = array('references' => $references, 'indexes' => $indexes, 'labels' => $labels);

            return $results;
        }
        catch ( \Exception $ex )
        {
            Log::error( 'Exception updating table: ' . $ex->getMessage() );
            throw $ex;
        }
    }

    /**
     * @param \CDbConnection $db
     * @param array          $tables
     * @param bool           $allow_merge
     * @param bool           $allow_delete
     * @param bool           $rollback
     *
     * @throws \Exception
     * @return array
     */
    public static function updateTables( $db, $tables, $allow_merge = false, $allow_delete = false, $rollback = false )
    {
        $tables = static::validateAsArray( $tables, null, true, 'There are no table sets in the request.' );

        //  Refresh the schema so we have the latest
        $db->schema->refresh();
        static::_getCachedTables( $db, true );

        $_created = $_references = $_indexes = $_labels = $_out = array();
        $_count = 0;
        $_singleTable = ( 1 == count( $tables ) );

        foreach ( $tables as $_table )
        {
            try
            {
                if ( null === ( $_tableName = Option::get( $_table, 'name' ) ) )
                {
                    throw new BadRequestException( 'Table name missing from schema.' );
                }

                //	Does it already exist
                if ( static::doesTableExist( $db, $_tableName ) )
                {
                    if ( !$allow_merge )
                    {
                        throw new BadRequestException( "A table with name '$_tableName' already exist in the database." );
                    }

                    Log::debug( 'Schema update: ' . $_tableName );

                    $_results = static::updateTable( $db, $_tableName, $_table, $allow_delete );
                }
                else
                {
                    Log::debug( 'Creating table: ' . $_tableName );

                    $_results = static::createTable( $db, $_tableName, $_table, false );

                    if ( !$_singleTable && $rollback )
                    {
                        $_created[] = $_tableName;
                    }
                }

                $_labels = array_merge( $_labels, Option::get( $_results, 'labels', array() ) );
                $_references = array_merge( $_references, Option::get( $_results, 'references', array() ) );
                $_indexes = array_merge( $_indexes, Option::get( $_results, 'indexes', array() ) );
                $_out[$_count] = array('name' => $_tableName);
            }
            catch ( \Exception $ex )
            {
                if ( $rollback || $_singleTable )
                {
                    //  Delete any created tables
                    throw $ex;
                }

                $_out[$_count] = array(
                    'error' => array(
                        'message' => $ex->getMessage(),
                        'code'    => $ex->getCode()
                    )
                );
            }

            $_count++;
        }

        //  Refresh the schema that we just added
        $db->schema->refresh();
        static::_getCachedTables( $db, true );

        $_results = array('references' => $_references, 'indexes' => $_indexes);
        static::createFieldExtras( $db, $_results );

        $_out['labels'] = $_labels;

        return $_out;
    }

    /**
     * @param \CDbConnection $db
     * @param string         $table_name
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws StorageException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     */
    public static function dropTable( $db, $table_name )
    {
        if ( empty( $table_name ) )
        {
            throw new BadRequestException( "Table name received is empty." );
        }

        //  Does it exist
        if ( !static::doesTableExist( $db, $table_name ) )
        {
            throw new NotFoundException( 'Table "' . $table_name . '" not found.' );
        }
        try
        {
            $db->createCommand()->dropTable( $table_name );

            //  Refresh the schema that we just added
            $db->schema->refresh();
            static::_getCachedTables( $db, true );
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Exception dropping table: ' . $_ex->getMessage() );

            throw $_ex;
        }
    }

    /**
     * @param \CDbConnection $db
     * @param string         $table_name
     * @param string         $field_name
     *
     * @throws \Exception
     */
    public static function dropField( $db, $table_name, $field_name )
    {
        if ( empty( $table_name ) )
        {
            throw new BadRequestException( "Table name received is empty." );
        }
        // does it already exist
        if ( !static::doesTableExist( $db, $table_name ) )
        {
            throw new NotFoundException( "A table with name '$table_name' does not exist in the database." );
        }
        try
        {
            $db->createCommand()->dropColumn( $table_name, $field_name );

            // refresh the schema that we just added
            $db->schema->refresh();
            static::_getCachedTables( $db, true );
        }
        catch ( \Exception $ex )
        {
            error_log( $ex->getMessage() );
            throw $ex;
        }
    }

    /**
     * Returns a generic type suitable for type-casting
     *
     * @param \CDbColumnSchema $column
     *
     * @return string
     */
    public static function determineGenericType( $column )
    {
        $_simpleType = strstr( $column->dbType, '(', true );
        $_simpleType = strtolower( $_simpleType ? : $column->dbType );

        switch ( $_simpleType )
        {
            case 'bit':
            case 'bool':
            case 'boolean':
                return 'boolean';

            case 'decimal':
            case 'numeric':
            case 'number':
            case 'percent':
                return 'decimal';

            case 'double':
            case 'float':
            case 'real':
            case 'double precision':
                return 'float';

            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
            case 'integer':
                if ( $column->size == 1 )
                {
                    return 'boolean';
                }

                return 'integer';

            case 'binary':
            case 'varbinary':
            case 'blob':
            case 'mediumblob':
            case 'largeblob':
                return 'binary';

            case 'datetimeoffset':
            case 'timestamp':
            case 'datetime':
            case 'datetime2':
                return 'datetime';

            //	String types
            case 'string':
            case 'char':
            case 'character':
            case 'text':
            case 'mediumtext':
            case 'longtext':
            case 'varchar':
            case 'character varying':
            case 'nchar':
            case 'nvarchar':
            default:
                return 'string';
        }
    }

    /**
     * @param $type
     * @param $db_type
     *
     * @return int | null
     */
    public static function determinePdoBindingType( $type, /** @noinspection PhpUnusedParameterInspection */
        $db_type = null )
    {
        switch ( $type )
        {
            case 'boolean':
                return \PDO::PARAM_BOOL;

            case 'integer':
            case 'id':
            case 'reference':
            case 'user_id':
            case 'user_id_on_create':
            case 'user_id_on_update':
                return \PDO::PARAM_INT;

            case 'string':
                return \PDO::PARAM_STR;

            case 'decimal':
            case 'float':
                break;
        }

        return null;
    }

    /**
     * Returns an array of tables from the $db indexed by the lowercase name of the table:
     *
     * array(
     *   'todo' => 'Todo',
     *   'contactinfo' => 'ContactInfo',
     * )
     *
     * @param \CDbConnection $db
     * @param bool           $reset
     *
     * @return array
     */
    protected static function _getCachedTables( $db, $reset = false )
    {
        if ( $reset )
        {
            static::$_tableNameCache = array();
        }

        $_hash = spl_object_hash( $db );

        if ( !isset( static::$_tableNameCache[$_hash] ) )
        {
            $_tables = array();

            //  Make a new column for the search version of the table name
            foreach ( $db->getSchema()->getTableNames() as $_table )
            {
                $_tables[strtolower( $_table )] = $_table;
            }

            static::$_tableNameCache[$_hash] = $_tables;

            unset( $_tables );
        }

        return static::$_tableNameCache[$_hash];
    }
}
