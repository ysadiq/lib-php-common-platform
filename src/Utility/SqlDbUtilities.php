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
use Kisma\Core\Utility\Sql;

/**
 * SqlDbUtilities
 * Generic database utilities
 */
class SqlDbUtilities implements SqlDbDriverTypes
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array
     */
    protected static $_tableCache;
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
        $_tables = is_array( $db ) ? $db : static::_getCachedTables( $db );

        //	Make search case insensitive
        if ( false ===
             ( $_key = array_search( strtolower( $name ), is_array( $db ) ? $_tables : static::$_tableNameCache ) )
        )
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
     * @param string         $include_prefix
     * @param string         $exclude_prefix
     *
     * @throws \Exception
     * @return array
     */
    public static function describeDatabase( $db, $include_prefix = '', $exclude_prefix = '' )
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
            $_names = $temp;
            natcasesort( $_names );
            $labels = static::getLabels(
                array('and', "field=''", array('in', 'table', $_names)),
                array(),
                'table,label,plural'
            );
            $tables = array();
            foreach ( $_names as $name )
            {
                $label = '';
                $plural = '';
                foreach ( $labels as $each )
                {
                    if ( 0 == strcasecmp( $name, $each['table'] ) )
                    {
                        $label = Option::get( $each, 'label' );
                        $plural = Option::get( $each, 'plural' );
                        break;
                    }
                }

                if ( empty( $label ) )
                {
                    $label = Inflector::camelize( $name, '_', true );
                }

                if ( empty( $plural ) )
                {
                    $plural = Inflector::pluralize( $label );
                }

                $tables[] = array('name' => $name, 'label' => $label, 'plural' => $plural);
            }

            return $tables;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to query database schema.\n{$ex->getMessage()}" );
        }
    }

    /**
     * @param \CDbConnection $db
     * @param null           $names
     * @param string         $remove_prefix
     *
     * @throws \Exception
     * @return array|string
     */
    public static function describeTables( $db, $names = null, $remove_prefix = '' )
    {
        $out = array();
        foreach ( $names as $table )
        {
            $out[] = static::describeTable( $db, $table, $remove_prefix );
        }

        return $out;
    }

    /**
     * @param \CDbConnection $db
     * @param string         $name
     * @param string         $remove_prefix
     *
     * @throws \Exception
     * @return array
     */
    public static function describeTable( $db, $name, $remove_prefix = '' )
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
            $localdb = Pii::db();
            $query = $localdb->quoteColumnName( 'table' ) . ' = :tn';
            $labels = static::getLabels( $query, array(':tn' => $name) );
            $labels = static::reformatFieldLabelArray( $labels );
            $labelInfo = Option::get( $labels, '', array() );

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
                'field'       => static::describeTableFields( $db, $name, $labels ),
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
     * @param \CDbConnection $db
     * @param                $name
     * @param array          $labels
     *
     * @throws \Exception
     * @return array
     */
    public static function describeTableFields( $db, $name, $labels = array() )
    {
        $name = static::correctTableName( $db, $name );
        $table = $db->schema->getTable( $name );
        if ( !$table )
        {
            throw new NotFoundException( "Table '$name' does not exist in the database." );
        }

        try
        {
            if ( empty( $labels ) )
            {
                $localdb = Pii::db();
                $query = $localdb->quoteColumnName( 'table' ) . ' = :tn';
                $labels = static::getLabels( $query, array(':tn' => $name) );
                $labels = static::reformatFieldLabelArray( $labels );
            }
            $fields = array();
            foreach ( $table->columns as $column )
            {
                $labelInfo = Option::get( $labels, $column->name, array() );
                $field = static::describeFieldInternal( $column, $table->foreignKeys, $labelInfo );
                $fields[] = $field;
            }

            return $fields;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to query table schema.\n{$ex->getMessage()}" );
        }
    }

    /**
     * @param \CDbConnection $db
     * @param string         $table_name
     * @param array          $field_names
     *
     * @throws \Exception
     * @return array
     */
    public static function describeFields( $db, $table_name, $field_names )
    {
        $table_name = static::correctTableName( $db, $table_name );
        $table = $db->schema->getTable( $table_name );
        if ( !$table )
        {
            throw new NotFoundException( "Table '$table_name' does not exist in the database." );
        }

        $field = array();
        try
        {
            foreach ( $table->columns as $column )
            {
                if ( false === array_search( $column->name, $field_names ) )
                {
                    continue;
                }
                $localdb = Pii::db();
                $query =
                    $localdb->quoteColumnName( 'table' ) .
                    ' = :tn and ' .
                    $localdb->quoteColumnName( 'field' ) .
                    ' = :fn';
                $labels = static::getLabels( $query, array(':tn' => $table_name, ':fn' => $column->name) );
                $labelInfo = Option::get( $labels, 0, array() );
                $field[] = static::describeFieldInternal( $column, $table->foreignKeys, $labelInfo );
                break;
            }
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to query table field schema.\n{$ex->getMessage()}" );
        }

        if ( empty( $field ) )
        {
            throw new NotFoundException( "Fields not found in table '$table_name'." );
        }

        return $field;
    }

    /**
     * @param \CDbConnection $db
     * @param                $table_name
     * @param                $field_name
     *
     * @throws \Exception
     * @return array
     */
    public static function describeField( $db, $table_name, $field_name )
    {
        $table_name = static::correctTableName( $db, $table_name );
        $table = $db->schema->getTable( $table_name );
        if ( !$table )
        {
            throw new NotFoundException( "Table '$table_name' does not exist in the database." );
        }
        $field = array();
        try
        {
            foreach ( $table->columns as $column )
            {
                if ( 0 != strcasecmp( $column->name, $field_name ) )
                {
                    continue;
                }
                $localdb = Pii::db();
                $query =
                    $localdb->quoteColumnName( 'table' ) .
                    ' = :tn and ' .
                    $localdb->quoteColumnName( 'field' ) .
                    ' = :fn';
                $labels = static::getLabels( $query, array(':tn' => $table_name, ':fn' => $field_name) );
                $labelInfo = Option::get( $labels, 0, array() );
                $field = static::describeFieldInternal( $column, $table->foreignKeys, $labelInfo );
                break;
            }
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Failed to query table field schema.\n{$ex->getMessage()}" );
        }

        if ( empty( $field ) )
        {
            throw new NotFoundException( "Field '$field_name' not found in table '$table_name'." );
        }

        return $field;
    }

    /**
     * @param \CDbColumnSchema $column
     * @param array            $foreign_keys
     * @param array            $label_info
     *
     * @throws \Exception
     * @return array
     */
    public static function describeFieldInternal( $column, $foreign_keys, $label_info )
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
     * @param $avail_fields
     *
     * @return array
     */
    public static function listAllFieldsFromDescribe( $avail_fields )
    {
        $out = array();
        foreach ( $avail_fields as $field_info )
        {
            $out[] = $field_info['name'];
        }

        return $out;
    }

    /**
     * @param $field_name
     * @param $avail_fields
     *
     * @return null
     */
    public static function getFieldFromDescribe( $field_name, $avail_fields )
    {
        foreach ( $avail_fields as $field_info )
        {
            if ( 0 == strcasecmp( $field_name, $field_info['name'] ) )
            {
                return $field_info;
            }
        }

        return null;
    }

    /**
     * @param $field_name
     * @param $avail_fields
     *
     * @return bool|int|string
     */
    public static function findFieldFromDescribe( $field_name, $avail_fields )
    {
        foreach ( $avail_fields as $key => $field_info )
        {
            if ( 0 == strcasecmp( $field_name, $field_info['name'] ) )
            {
                return $key;
            }
        }

        return false;
    }

    /**
     * @param $avail_fields
     *
     * @return string
     */
    public static function getPrimaryKeyFieldFromDescribe( $avail_fields )
    {
        foreach ( $avail_fields as $field_info )
        {
            if ( $field_info['is_primary_key'] )
            {
                return $field_info['name'];
            }
        }

        return '';
    }

    /**
     * @param array   $avail_fields
     * @param boolean $names_only Return only an array of names, otherwise return all properties
     *
     * @return array
     */
    public static function getPrimaryKeys( $avail_fields, $names_only = false )
    {
        $_keys = array();
        foreach ( $avail_fields as $_info )
        {
            if ( $_info['is_primary_key'] )
            {
                $_keys[] = ( $names_only ? $_info['name'] : $_info );
            }
        }

        return $_keys;
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

            $allowNull = Utilities::getArrayValue( 'allow_null', $field, true );
            $length = Utilities::getArrayValue( 'length', $field, null );
            if ( !isset( $length ) )
            {
                $length = Utilities::getArrayValue( 'size', $field, null ); // alias
            }
            $default = Utilities::getArrayValue( 'default', $field, null );
            $quoteDefault = false;
            $isPrimaryKey = Utilities::getArrayValue( 'is_primary_key', $field, false );

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
                    $default = ( isset( $default ) ) ? intval( Utilities::boolval( $default ) ) : $default;
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
                        $length = Utilities::getArrayValue( 'precision', $field, null ); // alias
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
                        $scale = Utilities::getArrayValue( 'scale', $field, null );
                        if ( empty( $scale ) )
                        {
                            $scale = Utilities::getArrayValue( 'decimals', $field, null ); // alias
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
                        $length = Utilities::getArrayValue( 'precision', $field, null ); // alias
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
                        $scale = Utilities::getArrayValue( 'scale', $field, null );
                        if ( empty( $scale ) )
                        {
                            $scale = Utilities::getArrayValue( 'decimals', $field, null ); // alias
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
                    $fixed = Utilities::boolval( Utilities::getArrayValue( 'fixed_length', $field, false ) );
                    $national = Utilities::boolval( Utilities::getArrayValue( 'supports_multibyte', $field, false ) );
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
            if ( !Utilities::boolval( $allowNull ) )
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
                $name = Utilities::getArrayValue( 'name', $field, '' );
                if ( empty( $name ) )
                {
                    throw new BadRequestException( "Invalid schema detected - no name element." );
                }
                $type = Utilities::getArrayValue( 'type', $field, '' );
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
                           Utilities::boolval( Utilities::getArrayValue( 'is_primary_key', $field, false ) ) ) &&
                         ( $colSchema->isPrimaryKey )
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
                elseif ( Utilities::boolval( Utilities::getArrayValue( 'is_primary_key', $field, false ) ) )
                {
                    if ( !empty( $primaryKey ) && ( 0 != strcasecmp( $primaryKey, $name ) ) )
                    {
                        throw new BadRequestException( "Designating more than one column as a primary key is not allowed." );
                    }
                    $primaryKey = $name;
                }
                elseif ( ( 0 == strcasecmp( 'reference', $type ) ) ||
                         Utilities::boolval( Utilities::getArrayValue( 'is_foreign_key', $field, false ) )
                )
                {
                    // special case for references because the table referenced may not be created yet
                    $refTable = Utilities::getArrayValue( 'ref_table', $field, '' );
                    if ( empty( $refTable ) )
                    {
                        throw new BadRequestException( "Invalid schema detected - no table element for reference type of $name." );
                    }
                    $refColumns = Utilities::getArrayValue( 'ref_fields', $field, 'id' );
                    $refOnDelete = Utilities::getArrayValue( 'ref_on_delete', $field, null );
                    $refOnUpdate = Utilities::getArrayValue( 'ref_on_update', $field, null );

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
                if ( Utilities::boolval( Utilities::getArrayValue( 'is_unique', $field, false ) ) )
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
                elseif ( Utilities::boolval( Utilities::getArrayValue( 'is_index', $field, false ) ) )
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
                $values = Utilities::getArrayValue( 'value', $field, '' );
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
                $label = Utilities::getArrayValue( 'label', $field, '' );
                if ( !empty( $label ) )
                {
                    $temp['label'] = $label;
                }

                $validation = Utilities::getArrayValue( 'validation', $field );
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
        $references = Utilities::getArrayValue( 'references', $extras, array() );
        if ( !empty( $references ) )
        {
            foreach ( $references as $reference )
            {
                $command->reset();
                $name = $reference['name'];
                $table = $reference['table'];
                $drop = Utilities::boolval( Utilities::getArrayValue( 'drop', $reference, false ) );
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
                $refTable = Utilities::getArrayValue( 'ref_table', $reference, null );
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
        $indexes = Utilities::getArrayValue( 'indexes', $extras, array() );
        if ( !empty( $indexes ) )
        {
            foreach ( $indexes as $index )
            {
                $command->reset();
                $name = $index['name'];
                $table = $index['table'];
                $drop = Utilities::boolval( Utilities::getArrayValue( 'drop', $index, false ) );
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
                $unique = Utilities::boolval( Utilities::getArrayValue( 'unique', $index, false ) );

                /** @noinspection PhpUnusedLocalVariableInspection */
                $rows = $command->createIndex( $name, $table, $index['column'], $unique );
            }
        }

        $labels = Utilities::getArrayValue( 'labels', $extras, array() );

        if ( !empty( $labels ) )
        {
            static::setLabels( $labels );
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
            $columns = Utilities::getArrayValue( 'columns', $results, array() );
            foreach ( $columns as $name => $definition )
            {
                $command->reset();
                $command->addColumn( $table_name, $name, $definition );
                $names[] = $name;
            }
            $columns = Utilities::getArrayValue( 'alter_columns', $results, array() );
            foreach ( $columns as $name => $definition )
            {
                $command->reset();
                $command->alterColumn( $table_name, $name, $definition );
                $names[] = $name;
            }
            $columns = Utilities::getArrayValue( 'drop_columns', $results, array() );
            foreach ( $columns as $name )
            {
                $command->reset();
                $command->dropColumn( $table_name, $name );
                $names[] = $name;
            }
            static::createFieldExtras( $db, $results );

            // refresh the schema that we just added
            $db->schema->refresh();
            static::_getCachedTables( $db, true );

            return $names;
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
     * @param bool           $return_labels_refs
     * @param bool           $checkExist
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     * @return array
     */
    public static function createTable( $db, $table_name, $data, $return_labels_refs = false, $checkExist = true )
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

        $fields = Utilities::getArrayValue( 'field', $data, array() );

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
            $columns = Utilities::getArrayValue( 'columns', $results, array() );

            if ( empty( $columns ) )
            {
                throw new BadRequestException( "No valid fields exist in the received table schema." );
            }

            $command = $db->createCommand();
            $command->createTable( $table_name, $columns );

            $labels = Utilities::getArrayValue( 'labels', $results, array() );
            // add table labels
            $label = Utilities::getArrayValue( 'label', $data, '' );
            $plural = Utilities::getArrayValue( 'plural', $data, '' );
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
            if ( $return_labels_refs )
            {
                return $results;
            }

            static::createFieldExtras( $db, $results );

            return array('name' => $table_name);
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
     * @param bool           $return_labels_refs
     * @param bool           $allow_delete
     *
     * @throws \Exception
     * @return array
     */
    public static function updateTable( $db, $table_name, $data, $return_labels_refs = false, $allow_delete = false )
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
        $newName = Utilities::getArrayValue( 'new_name', $data, '' );

        if ( !empty( $newName ) )
        {
            // todo change table name, has issue with references
        }

        // update column types
        $fields = Utilities::getArrayValue( 'field', $data, array() );
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
                $columns = Utilities::getArrayValue( 'alter_columns', $results, array() );
                foreach ( $columns as $name => $definition )
                {
                    $command->reset();
                    $command->alterColumn( $table_name, $name, $definition );
                }
                $columns = Utilities::getArrayValue( 'drop_columns', $results, array() );
                foreach ( $columns as $name )
                {
                    $command->reset();
                    $command->dropColumn( $table_name, $name );
                }

                $labels = Utilities::getArrayValue( 'labels', $results, array() );
                $references = Utilities::getArrayValue( 'references', $results, array() );
                $indexes = Utilities::getArrayValue( 'indexes', $results, array() );
            }
            // add table labels
            $label = Utilities::getArrayValue( 'label', $data, '' );
            $plural = Utilities::getArrayValue( 'plural', $data, '' );
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
            if ( $return_labels_refs )
            {
                return $results;
            }

            static::createFieldExtras( $db, $results );

            return array('name' => $table_name);
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

                    $_results = static::updateTable( $db, $_tableName, $_table, !$_singleTable, $allow_delete );
                }
                else
                {
                    Log::debug( 'Creating table: ' . $_tableName );

                    $_results = static::createTable( $db, $_tableName, $_table, !$_singleTable, false );

                    if ( !$_singleTable && $rollback )
                    {
                        $_created[] = $_tableName;
                    }
                }

                if ( $_singleTable )
                {
                    $_out[] = $_results;
                }
                else
                {
                    $_labels = array_merge( $_labels, Option::get( $_results, 'labels', array() ) );
                    $_references = array_merge( $_references, Option::get( $_results, 'references', array() ) );
                    $_indexes = array_merge( $_indexes, Option::get( $_results, 'indexes', array() ) );
                    $_out[$_count] = array('name' => $_tableName);
                }
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

        if ( !$_singleTable )
        {
            $_results = array('references' => $_references, 'indexes' => $_indexes, 'labels' => $_labels);

            static::createFieldExtras( $db, $_results );
        }

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
            $command = $db->createCommand();
            $command->dropTable( $table_name );

            $_column = Pii::db()->quoteColumnName( 'table' );
            static::removeLabels( $_column . ' = :table_name', array(':table_name' => $table_name) );

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
            $command = $db->createCommand();
            $command->dropColumn( $table_name, $field_name );
            /** @var \CDbConnection $_dbLocal Local connection */
            $_dbLocal = Pii::db();
            $where = $_dbLocal->quoteColumnName( 'table' ) . ' = :tn';
            $where .= ' and ' . $_dbLocal->quoteColumnName( 'field' ) . ' = :fn';
            static::removeLabels( $where, array(':tn' => $table_name, ':fn' => $field_name) );

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
     * @param string | array $where
     * @param array          $params
     * @param string         $select
     *
     * @return array
     */
    public static function getLabels( $where, $params = array(), $select = '*' )
    {
        $_db = Pii::db();
        $labels = array();
        if ( static::doesTableExist( $_db, 'df_sys_schema_extras' ) )
        {
            $command = $_db->createCommand();
            $command->select( $select );
            $command->from( 'df_sys_schema_extras' );
            $command->where( $where, $params );
            $labels = $command->queryAll();
        }

        return $labels;
    }

    /**
     * @param array $labels
     *
     * @throws \CDbException
     * @return void
     */
    public static function setLabels( $labels )
    {
        $_db = Pii::db();

        if ( !empty( $labels ) && static::doesTableExist( $_db, 'df_sys_schema_extras' ) )
        {
            // todo batch this for speed
            //@TODO Batched it a bit... still probably slow...
            $_tableColumn = $_db->quoteColumnName( 'table' );
            $_fieldColumn = $_db->quoteColumnName( 'field' );

            $_sql = <<<SQL
SELECT
	id
FROM
	df_sys_schema_extras
WHERE
	$_tableColumn = :table_value AND
	$_fieldColumn = :field_value
SQL;

            $_inserts = $_updates = array();

            Sql::setConnection( Pii::pdo() );

            foreach ( $labels as $_label )
            {
                $_id = Sql::scalar(
                    $_sql,
                    0,
                    array(
                        ':table_value' => Option::get( $_label, 'table' ),
                        ':field_value' => Option::get( $_label, 'field' ),
                    )
                );

                if ( empty( $_id ) )
                {
                    $_inserts[] = $_label;
                }
                else
                {
                    $_updates[$_id] = $_label;
                }
            }

            $_transaction = null;

            try
            {
                $_transaction = $_db->beginTransaction();
            }
            catch ( \Exception $_ex )
            {
                //	No transaction support
                $_transaction = false;
            }

            try
            {
                $_command = new \CDbCommand( $_db );

                if ( !empty( $_inserts ) )
                {
                    foreach ( $_inserts as $_insert )
                    {
                        $_command->reset();
                        $_command->insert( 'df_sys_schema_extras', $_insert );
                    }
                }

                if ( !empty( $_updates ) )
                {
                    foreach ( $_updates as $_id => $_update )
                    {
                        $_command->reset();
                        $_command->update( 'df_sys_schema_extras', $_update, 'id = :id', array(':id' => $_id) );
                    }
                }

                if ( $_transaction )
                {
                    $_transaction->commit();
                }
            }
            catch ( \Exception $_ex )
            {
                Log::error( 'Exception storing schema updates: ' . $_ex->getMessage() );

                if ( $_transaction )
                {
                    $_transaction->rollback();
                }
            }
        }
    }

    /**
     * @param      $where
     * @param null $params
     */
    public static function removeLabels( $where, $params = null )
    {
        $_db = Pii::db();

        if ( static::doesTableExist( $_db, 'df_sys_schema_extras' ) )
        {
            $command = $_db->createCommand();
            $command->delete( 'df_sys_schema_extras', $where, $params );
        }
    }

    /**
     * @param array $original
     *
     * @return array
     */
    public static function reformatFieldLabelArray( $original )
    {
        $_new = array();

        foreach ( $original as $_label )
        {
            $_new[Option::get( $_label, 'field' )] = $_label;
        }

        return $_new;
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
    public static function determinePdoBindingType( $type, $db_type = null )
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
     * @param $type
     * @param $db_type
     *
     * @return null|string
     */
    public static function determinePhpConversionType( $type, $db_type = null )
    {
        switch ( $type )
        {
            case 'boolean':
                return 'bool';

            case 'integer':
            case 'id':
            case 'reference':
            case 'user_id':
            case 'user_id_on_create':
            case 'user_id_on_update':
                return 'int';

            case 'decimal':
            case 'float':
            case 'double':
                return 'float';

            case 'string':
                return 'string';
        }

        return null;
    }

    /**
     * @param \CDbConnection $db
     * @param bool           $reset
     *
     * @return array
     */
    protected static function _getCachedTables( $db, $reset = false )
    {
        if ( $reset )
        {
            static::$_tableCache = null;
            static::$_tableNameCache = null;
        }

        if ( !static::$_tableCache )
        {
            static::$_tableCache = static::$_tableNameCache = $db->getSchema()->getTableNames();

            //  Make a new column for the search version of the table name
            foreach ( static::$_tableNameCache as &$_value )
            {
                $_value = strtolower( $_value );
            }
        }

        return static::$_tableCache;
    }

    /**
     * @param array | string $data          Array to check or comma-delimited string to convert
     * @param string | null  $str_delimiter Delimiter to check for string to array mapping, no op if null
     * @param boolean        $check_single  Check if single (associative) needs to be made multiple (numeric)
     * @param string | null  $on_fail       Error string to deliver in thrown exception
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array | boolean If requirements not met then throws exception if
     * $on_fail string given, or returns false. Otherwise returns valid array
     */
    public static function validateAsArray( $data, $str_delimiter = null, $check_single = false, $on_fail = null )
    {
        if ( !empty( $data ) && !is_array( $data ) && ( is_string( $str_delimiter ) && !empty( $str_delimiter ) ) )
        {
            $data = array_map( 'trim', explode( $str_delimiter, trim( $data, $str_delimiter ) ) );
        }

        if ( !is_array( $data ) || empty( $data ) )
        {
            if ( !is_string( $on_fail ) || empty( $on_fail ) )
            {
                return false;
            }

            throw new BadRequestException( $on_fail );
        }

        if ( $check_single )
        {
            if ( !isset( $data[0] ) )
            {
                // single record possibly passed in without wrapper array
                $data = array($data);
            }
        }

        return $data;
    }
}
