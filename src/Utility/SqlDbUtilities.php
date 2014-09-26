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
     * @param integer        $service_id
     * @param \CDbConnection $db
     * @param string         $name
     * @param string         $remove_prefix
     * @param bool           $refresh
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public static function describeTable( $service_id, $db, $name, $remove_prefix = '', $refresh = false )
    {
        $name = static::correctTableName( $db, $name );
        try
        {
            $table = $db->schema->getTable( $name, $refresh );
            if ( !$table )
            {
                throw new NotFoundException( "Table '$name' does not exist in the database." );
            }

            $defaultSchema = $db->schema->getDefaultSchema();
            $extras = static::getSchemaExtrasForTables( $service_id, $name );
            $extras = static::reformatFieldLabelArray( $extras );
            $labelInfo = Option::get( $extras, '', array() );

            $publicName = $table->name;
            $schemaName = $table->schemaName;
            if ( !empty( $schemaName ) )
            {
                if ( $defaultSchema !== $schemaName )
                {
                    $publicName = $schemaName . '.' . $publicName;
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

            $fields = array();
            foreach ( $table->columns as $column )
            {
                $_info = Option::get( $extras, $column->name, array() );
                $fields[] = static::describeFieldInternal( $column, $table->foreignKeys, $_info );
            }

            return array(
                'name'        => $publicName,
                'label'       => $label,
                'plural'      => $plural,
                'primary_key' => $table->primaryKey,
                'name_field'  => $name_field,
                'field'       => $fields,
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
     * @param integer               $service_id
     * @param \CDbConnection        $db
     * @param string                $table_name
     * @param null | string | array $field_names
     * @param bool                  $refresh
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array
     */
    public static function describeTableFields( $service_id, $db, $table_name, $field_names = null, $refresh = false )
    {
        $table_name = static::correctTableName( $db, $table_name );
        $_table = $db->schema->getTable( $table_name, $refresh );
        if ( !$_table )
        {
            throw new NotFoundException( "Table '$table_name' does not exist in the database." );
        }

        if ( !empty( $field_names ) )
        {
            $field_names = static::validateAsArray( $field_names, ',', true, 'No valid field names given.' );
            $extras = static::getSchemaExtrasForFields( $service_id, $table_name, $field_names );
        }
        else
        {
            $extras = static::getSchemaExtrasForTables( $service_id, $table_name );
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
     * @param \CDbConnection $db
     * @param                $parent_table
     *
     * @return array
     * @throws \Exception
     */
    public static function describeTableRelated( $db, $parent_table )
    {
        $_schema = $db->schema->getTable( $parent_table );
        $_related = array();

        // foreign keys point to relationships that this table "belongs to"
        // currently handled by schema handler, see below
//        foreach ( $_schema->foreignKeys as $_key => $_value )
//        {
//            $_refTable = Option::get( $_value, 0 );
//            $_refField = Option::get( $_value, 1 );
//            $_name = $_refTable . '_by_' . $_key;
//            $_related[] = array(
//                'name'      => $_name,
//                'type'      => 'belongs_to',
//                'ref_table' => $_refTable,
//                'ref_field' => $_refField,
//                'field'     => $_key
//            );
//        }

        // foreign refs point to relationships other tables have with this table
        foreach ( $_schema->foreignRefs as $_refs )
        {
            $_name = array();
            switch ( Option::get( $_refs, 'type' ) )
            {
                case 'belongs_to':
                    $_name['name'] = Option::get( $_refs, 'ref_table', '' ) . '_by_' . Option::get( $_refs, 'field', '' );
                    break;
                case 'has_many':
                    $_name['name'] = Inflector::pluralize( Option::get( $_refs, 'ref_table', '' ) ) . '_by_' . Option::get( $_refs, 'ref_field', '' );
                    break;
                case 'many_many':
                    $_join = Option::get( $_refs, 'join', '' );
                    $_join = substr( $_join, 0, strpos( $_join, '(' ) );
                    $_name['name'] = Inflector::pluralize( Option::get( $_refs, 'ref_table', '' ) ) . '_by_' . $_join;
                    break;
            }
            $_related[] = $_name + $_refs;
        }

        return $_related;
    }

    /**
     * @param integer        $service_id
     * @param \CDbConnection $db
     * @param string         $table_name
     * @param array          $fields
     * @param bool           $allow_update
     * @param bool           $allow_delete
     *
     * @return array
     * @throws \Exception
     */
    public static function updateFields( $service_id, $db, $table_name, $fields, $allow_update = false, $allow_delete = false )
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

        $_schema = static::describeTable( $service_id, $db, $table_name );

        try
        {
            $names = array();
            $results = static::buildTableFields( $db, $table_name, $fields, $_schema, $allow_update, $allow_delete );
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

            $labels = Option::get( $results, 'labels', null, true );
            if ( !empty( $labels ) )
            {
                static::setSchemaExtras( $service_id, $labels );
            }

            return array('names' => $names);
        }
        catch ( \Exception $ex )
        {
            Log::error( 'Exception updating fields: ' . $ex->getMessage() );
            throw $ex;
        }
    }

    /**
     * @param integer        $service_id
     * @param \CDbConnection $db
     * @param array          $tables
     * @param bool           $allow_merge
     * @param bool           $allow_delete
     * @param bool           $rollback
     *
     * @throws \Exception
     * @return array
     */
    public static function updateTables( $service_id, $db, $tables, $allow_merge = false, $allow_delete = false, $rollback = false )
    {
        $tables = static::validateAsArray( $tables, null, true, 'There are no table sets in the request.' );

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

                    $_oldSchema = static::describeTable( $service_id, $db, $_tableName );

                    $_results = static::updateTable( $db, $_tableName, $_table, $_oldSchema, $allow_delete );
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

        $_results = array('references' => $_references, 'indexes' => $_indexes);
        static::createFieldExtras( $db, $_results );

        $_out['labels'] = $_labels;
        if ( !empty( $_labels ) )
        {
            static::setSchemaExtras( $service_id, $_labels );
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
            $db->createCommand()->dropTable( $table_name );
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
        }
        catch ( \Exception $ex )
        {
            error_log( $ex->getMessage() );
            throw $ex;
        }
    }

    /**
     * @param $type
     *
     * @return int | null
     */
    public static function determinePdoBindingType( $type )
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
                break;
        }

        return null;
    }

    /**
     * Refreshes all schema associated with this db connection:
     *
     * @param \CDbConnection $db
     *
     * @return array
     */
    public static function refreshCachedTables( $db )
    {
        // let Yii clear out as much as it can
        $db->schema->refresh();

        $_tables = array();
        foreach ( $db->getSchema()->getTableNames() as $_table )
        {
            $_tables[$_table] = $_table;
            // lookup by lowercase
            $_tables[strtolower( $_table )] = $_table;
        }

        // make a quick lookup for table names for this db
        $_hash = spl_object_hash( $db );
        static::$_tableNameCache[$_hash] = $_tables;
        unset( $_tables );
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
                    return 'user_id_on_' . ( Option::getBool( $label_info, 'user_id_on_update' ) ? 'update' : 'create' );
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

            case 'timestamp':
                if ( isset( $label_info['timestamp_on_update'] ) )
                {
                    return 'timestamp_on_' . ( Option::getBool( $label_info, 'timestamp_on_update' ) ? 'update' : 'create' );
                }
                break;
        }

        if ( ( 0 == strcasecmp( $column->dbType, 'datetimeoffset' ) ) || ( 0 == strcasecmp( $column->dbType, 'timestamp' ) )
        )
        {
            if ( isset( $label_info['timestamp_on_update'] ) )
            {
                return 'timestamp_on_' . ( Option::getBool( $label_info, 'timestamp_on_update' ) ? 'update' : 'create' );
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
            case 'nvarchar2':
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
     * @param array        $field
     * @param int          $driver_type
     * @param array | null $old_field
     *
     * @throws \Exception
     * @return null|string
     */
    protected static function buildColumnDefinition( $field, $driver_type = self::DRV_MYSQL, $old_field = null )
    {
        if ( empty( $field ) )
        {
            throw new BadRequestException( "No field given." );
        }

        $type = strtolower( Option::get( $field, 'type', '' ) );
        if ( empty( $type ) )
        {
            throw new BadRequestException( "Invalid schema detected - no type element." );
        }

        // if same as old, don't bother
        if ( !empty( $old_field ) )
        {
            $_same = true;
            foreach ( $field as $_key => $_value )
            {
                switch ( strtolower( $_key ) )
                {
                    case 'label':
                    case 'value':
                    case 'validation':
                        break;
                    default:
                        if ( isset( $old_field[$_key] ) ) // could be extra stuff from client
                        {
                            if ( $_value != $old_field[$_key] )
                            {
                                $_same = false;
                                break 2;
                            }
                        }
                        break;
                }
            }
            if ( $_same )
            {
                return null;
            }
        }

        /* abstract types handled by yii directly for each driver type

            pk: a generic primary key type, will be converted into int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY for MySQL;
            string: string type, will be converted into varchar(255) for MySQL;
            text: text type (long string), will be converted into text for MySQL;
            integer: integer type, will be converted into int(11) for MySQL;
            float: floating number type, will be converted into float for MySQL;
            double: floating number type, will be converted into double for MySQL;
            decimal: decimal number type, will be converted into decimal for MySQL;
            datetime: datetime type, will be converted into datetime for MySQL;
            timestamp: timestamp type, will be converted into timestamp for MySQL;
            time: time type, will be converted into time for MySQL;
            date: date type, will be converted into date for MySQL;
            binary: binary data type, will be converted into blob for MySQL;
            boolean: boolean type, will be converted into tinyint(1) for MySQL;
            money: money/currency type, will be converted into decimal(19,4) for MySQL.
        */

        $allowNull = Option::getBool( $field, 'allow_null', true );
        $default = Option::get( $field, 'default' );
        $quoteDefault = false;
        $isPrimaryKey = Option::getBool( $field, 'is_primary_key', false );
        $definition = $type; // blind copy of column type

        switch ( $type )
        {
            // some types need massaging, some need other required properties
            case 'pk':
            case 'id':
                return 'pk'; // simple primary key, bail here
                break;
            case 'reference':
                $definition = 'int';
                break;
            case 'timestamp_on_create':
            case 'timestamp_on_update':
                $definition = 'timestamp';
                if ( !isset( $default ) )
                {
                    switch ( $driver_type )
                    {
                        case SqlDbUtilities::DRV_SQLSRV:
                        case SqlDbUtilities::DRV_DBLIB:
                            $default = 'getdate()';
                            break;
                        case SqlDbUtilities::DRV_PGSQL:
                        case SqlDbUtilities::DRV_OCSQL:
                            $default = 'current_timestamp';
                            break;
                        default:
                            $default = ( 'timestamp_on_update' === $type ) ? 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP' : 0;
                            break;
                    }
                }
                if ( !isset( $field['allow_null'] ) )
                {
                    $allowNull = false;
                }
                break;
            case 'user_id':
                $definition = 'int';
                break;
            case 'user_id_on_create':
            case 'user_id_on_update':
                $definition = 'int';
                if ( !isset( $field['allow_null'] ) )
                {
                    $allowNull = false;
                }
                break;
            case 'boolean':
                if ( isset( $default ) )
                {
                    // convert to bit 0 or 1, where necessary
                    switch ( $driver_type )
                    {
                        case SqlDbUtilities::DRV_PGSQL:
                            $default = ( Scalar::boolval( $default ) ) ? 'TRUE' : 'FALSE';
                            break;
                        default:
                            $default = ( Scalar::boolval( $default ) ) ? '1' : '0';
                            break;
                    }
                }
                break;
            case 'integer':
                $length = Option::get( $field, 'length', Option::get( $field, 'precision' ) );
                if ( !empty( $length ) )
                {
                    $definition .= "($length)"; // sets the viewable length
                }
                if ( isset( $default ) )
                {
                    $default = intval( $default );
                }
                break;
            case 'decimal':
                $length = Option::get( $field, 'length', Option::get( $field, 'precision' ) );
                if ( !empty( $length ) )
                {
                    $scale = Option::get( $field, 'scale', Option::get( $field, 'decimals' ) );
                    $definition .= ( !empty( $scale ) ) ? "($length,$scale)" : "($length)";
                }
                if ( isset( $default ) )
                {
                    $default = floatval( $default );
                }
                break;
            case 'float':
            case 'double':
                $length = Option::get( $field, 'length', Option::get( $field, 'precision' ) );
                if ( !empty( $length ) )
                {
                    switch ( $driver_type )
                    {
                        case SqlDbUtilities::DRV_MYSQL:
                            $scale = Option::get( $field, 'scale', Option::get( $field, 'decimals' ) );
                            $definition .= ( !empty( $scale ) ) ? "($length,$scale)" : "($length)";
                            break;
                        default:
                            $definition .= "($length)";
                            break;
                    }
                }
                if ( isset( $default ) )
                {
                    $default = floatval( $default );
                }
                break;
            case 'money':
                if ( isset( $default ) )
                {
                    $default = floatval( $default );
                }
                break;
            case 'string':
                $length = Option::get( $field, 'length', Option::get( $field, 'size' ) );
                $fixed = Option::getBool( $field, 'fixed_length' );
                $national = Option::getBool( $field, 'supports_multibyte' );
                if ( $fixed )
                {
                    switch ( $driver_type )
                    {
                        case SqlDbUtilities::DRV_SQLSRV:
                        case SqlDbUtilities::DRV_DBLIB:
                        case SqlDbUtilities::DRV_MYSQL:
                        case SqlDbUtilities::DRV_OCSQL:
                            $definition = ( $national ) ? 'nchar' : 'char';
                            break;
                        case SqlDbUtilities::DRV_PGSQL:
                            $definition = ( $national ) ? 'national char' : 'char';
                            break;
                        default:
                            break;
                    }
                }
                elseif ( $national )
                {
                    switch ( $driver_type )
                    {
                        case SqlDbUtilities::DRV_SQLSRV:
                        case SqlDbUtilities::DRV_DBLIB:
                        case SqlDbUtilities::DRV_MYSQL:
                            $definition = 'nvarchar';
                            break;
                        case SqlDbUtilities::DRV_PGSQL:
                            $definition = 'national varchar';
                            break;
                        case SqlDbUtilities::DRV_OCSQL:
                            $definition = 'nvarchar2';
                            break;
                        default:
                            break;
                    }
                }
                if ( isset( $length ) )
                {
                    $definition .= "($length)";
                }
                $quoteDefault = true;
                break;
            case 'binary':
                $length = Option::get( $field, 'length', Option::get( $field, 'size' ) );
                $fixed = Option::getBool( $field, 'fixed_length' );
                $definition = ( $fixed ) ? 'binary' : 'varbinary';
                if ( isset( $length ) )
                {
                    $definition .= "($length)";
                }
                $quoteDefault = true;
                break;
            case 'text':
                $quoteDefault = true;
                break;
            case 'blob':
                $quoteDefault = true;
                break;
            case 'datetime':
            case 'date':
            case 'time':
                break;
        }

        // additional properties
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
        if ( !Scalar::boolval( $allowNull ) )
        {
            $definition .= ' NOT';
        }
        $definition .= ' NULL';

        return $definition;
    }

    protected static function makeConstraintName( $prefix, $table_name, $field_name, $driver_type )
    {
        $_temp = $prefix . '_' . $table_name . '_' . $field_name;
        switch ( $driver_type )
        {
            case static::DRV_OCSQL:
                // must be less than 30 characters
                if ( 30 < strlen( $_temp ) )
                {
                    $_temp = $prefix . '_' . hash( 'crc32', $table_name . '_' . $field_name );
                }
                break;
        }

        return $_temp;
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
        $fields = static::validateAsArray( $fields, null, true, "No valid fields exist in the received table schema." );

        $_driverType = static::getDbDriverType( $db );
        $_columns = array();
        $_alterColumns = array();
        $_references = array();
        $_indexes = array();
        $_labels = array();
        $_primaryKey = Option::get( $schema, 'primary_key', '' );
        $_dropColumns = array();
        $_oldFields = array();
        foreach ( Option::clean( Option::get( $schema, 'field' ) ) as $_old )
        {
            $_old = array_change_key_case( $_old, CASE_LOWER );

            $_oldFields[Option::get( $_old, 'name' )] = $_old;
        }
        $_fields = array();
        foreach ( $fields as $_field )
        {
            $_field = array_change_key_case( $_field, CASE_LOWER );

            $_fields[Option::get( $_field, 'name' )] = $_field;
        }

        if ( $allow_delete && !empty( $_oldFields ) )
        {
            // check for columns to drop
            foreach ( $_oldFields as $_oldName => $_oldField )
            {
                if ( !isset( $_fields[$_oldName] ) )
                {
                    $_dropColumns[] = $_oldName;
                }
            }
        }

        foreach ( $_fields as $_name => $_field )
        {
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Invalid schema detected - no name element." );
            }

            $_isAlter = isset( $_oldFields[$_name] );
            if ( $_isAlter && !$allow_update )
            {
                throw new BadRequestException( "Field '$_name' already exists in table '$table_name'." );
            }

            $_oldField = ( $_isAlter ) ? $_oldFields[$_name] : array();
            $_oldForeignKey = Option::get( $_oldField, 'is_foreign_key', false );
            $_temp = array();

            if ( null !== $_sql = Option::get( $field, 'sql', null, false, true ) )
            {
                // raw sql definition, just passing it on
                if ( $_isAlter )
                {
                    $_alterColumns[$_name] = $_sql;
                    // may need to clean out references, etc?
                }
                else
                {
                    $_columns[$_name] = $_sql;
                }
            }
            else
            {
                $_definition = static::buildColumnDefinition( $_field, $_driverType, $_oldField );

                if ( !empty( $_definition ) )
                {
                    if ( $_isAlter )
                    {
                        $_alterColumns[$_name] = $_definition;
                    }
                    else
                    {
                        $_columns[$_name] = $_definition;
                    }

                $_type = strtolower( Option::get( $_field, 'type', '' ) );

                if ( ( 'id' == $_type ) || ( 'pk' == $_type ) || Option::getBool( $_field, 'is_primary_key' ) )
                {
                    if ( !empty( $_primaryKey ) && ( 0 != strcasecmp( $_primaryKey, $_name ) ) )
                    {
                        throw new BadRequestException( "Designating more than one column as a primary key is not allowed." );
                    }

                    $_primaryKey = $_name;
                }
                elseif ( ( 'reference' == $_type ) || Option::getBool( $_field, 'is_foreign_key' ) )
                {
                    // special case for references because the table referenced may not be created yet
                    $refTable = Option::get( $_field, 'ref_table' );
                    if ( empty( $refTable ) )
                    {
                        throw new BadRequestException( "Invalid schema detected - no table element for reference type of $_name." );
                    }
                    $refColumns = Option::get( $_field, 'ref_fields', 'id' );
                    $refOnDelete = Option::get( $_field, 'ref_on_delete' );
                    $refOnUpdate = Option::get( $_field, 'ref_on_update' );

                    // will get to it later, $refTable may not be there
                    $_keyName = static::makeConstraintName( 'fk', $table_name, $_name, $_driverType );
                    if ( !$_isAlter || !$_oldForeignKey )
                    {
                        $_references[] = array(
                            'name'       => $_keyName,
                            'table'      => $table_name,
                            'column'     => $_name,
                            'ref_table'  => $refTable,
                            'ref_fields' => $refColumns,
                            'delete'     => $refOnDelete,
                            'update'     => $refOnUpdate
                        );
                    }
                }
                elseif ( ( 'user_id_on_create' == $_type ) || ( 'user_id_on_update' == $_type ) )
                { // && static::is_local_db()
                    // special case for references because the table referenced may not be created yet
                    $_temp['user_id_on_update'] = ( 'user_id_on_update' == $_type ) ? true : false;
                    $_keyName = static::makeConstraintName( 'fk', $table_name, $_name, $_driverType );
                    if ( !$_isAlter || !$_oldForeignKey )
                    {
                        $_references[] = array(
                            'name'       => $_keyName,
                            'table'      => $table_name,
                            'column'     => $_name,
                            'ref_table'  => 'df_sys_user',
                            'ref_fields' => 'id',
                            'delete'     => null,
                            'update'     => null
                        );
                    }
                }
                elseif ( 'user_id' == $_type )
                { // && static::is_local_db()
                    // special case for references because the table referenced may not be created yet
                    $_temp['user_id'] = true;
                    $_keyName = static::makeConstraintName( 'fk', $table_name, $_name, $_driverType );
                    if ( !$_isAlter || !$_oldForeignKey )
                    {
                        $_references[] = array(
                            'name'       => $_keyName,
                            'table'      => $table_name,
                            'column'     => $_name,
                            'ref_table'  => 'df_sys_user',
                            'ref_fields' => 'id',
                            'delete'     => null,
                            'update'     => null
                        );
                    }
                }
                elseif ( 'timestamp_on_create' == $_type )
                {
                    $_temp['timestamp_on_update'] = false;
                }
                elseif ( 'timestamp_on_update' == $_type )
                {
                    $_temp['timestamp_on_update'] = true;
                }
                }
            }

            // regardless of type
            if ( Option::getBool( $_field, 'is_unique' ) )
            {
                // will get to it later, create after table built
                $_keyName = static::makeConstraintName( 'undx', $table_name, $_name, $_driverType );
                $_indexes[] = array(
                    'name'   => $_keyName,
                    'table'  => $table_name,
                    'column' => $_name,
                    'unique' => true,
                    'drop'   => $_isAlter
                );
            }
            elseif ( Option::get( $_field, 'is_index' ) )
            {
                // will get to it later, create after table built
                $_keyName = static::makeConstraintName( 'ndx', $table_name, $_name, $_driverType );
                $_indexes[] = array(
                    'name'   => $_keyName,
                    'table'  => $table_name,
                    'column' => $_name,
                    'drop'   => $_isAlter
                );
            }

            $_values = Option::get( $_field, 'value' );
            if ( empty( $_values ) )
            {
                $_values = Option::getDeep( $_field, 'values', 'value', array() );
            }
            if ( !is_array( $_values ) )
            {
                $_values = array_map( 'trim', explode( ',', trim( $_values, ',' ) ) );
            }
            if ( !empty( $_values ) && ($_values != Option::get( $_oldField, 'value')))
            {
                $_picklist = '';
                foreach ( $_values as $_value )
                {
                    if ( !empty( $_picklist ) )
                    {
                        $_picklist .= "\r";
                    }
                    $_picklist .= $_value;
                }
                if ( !empty( $_picklist ) )
                {
                    $_temp['picklist'] = $_picklist;
                }
            }

            // labels
            $_label = Option::get( $_field, 'label' );
            if ( !empty( $_label ) && ($_label != Option::get( $_oldField, 'label')))
            {
                $_temp['label'] = $_label;
            }

            $_validation = Option::get( $_field, 'validation' );
            if ( !empty( $_validation )  && ($_validation != Option::get( $_oldField, 'validation')))
            {
                $_temp['validation'] = json_encode( $_validation );
            }

            if ( !empty( $_temp ) )
            {
                $_temp['table'] = $table_name;
                $_temp['field'] = $_name;
                $_labels[] = $_temp;
            }
        }

        return array(
            'columns'       => $_columns,
            'alter_columns' => $_alterColumns,
            'drop_columns'  => $_dropColumns,
            'references'    => $_references,
            'indexes'       => $_indexes,
            'labels'        => $_labels
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

        $data = array_change_key_case( $data, CASE_LOWER );
        $_fields = Option::get( $data, 'field' );
        try
        {
            $_results = static::buildTableFields( $db, $table_name, $_fields );
            $_columns = Option::get( $_results, 'columns', array() );

            if ( empty( $_columns ) )
            {
                throw new BadRequestException( "No valid fields exist in the received table schema." );
            }

            $db->createCommand()->createTable( $table_name, $_columns );

            $_labels = Option::get( $_results, 'labels', array() );
            // add table labels
            $_label = Option::get( $data, 'label' );
            $_plural = Option::get( $data, 'plural' );
            if ( !empty( $_label ) || !empty( $_plural ) )
            {
                $_labels[] = array(
                    'table'  => $table_name,
                    'field'  => '',
                    'label'  => $_label,
                    'plural' => $_plural
                );
            }
            $_results['labels'] = $_labels;

            return $_results;
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
     * @param array          $old_schema
     * @param bool           $allow_delete
     *
     * @throws \Exception
     * @return array
     */
    protected static function updateTable( $db, $table_name, $data, $old_schema, $allow_delete = false )
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
        $_newName = Option::get( $data, 'new_name' );

        if ( !empty( $_newName ) )
        {
            // todo change table name, has issue with references
        }

        // update column types

        $_labels = array();
        $_references = array();
        $_indexes = array();
        $_fields = Option::get( $data, 'field' );
        if ( !empty( $_fields ) )
        {
            try
            {
                $_command = $db->createCommand();
                $_results = static::buildTableFields( $db, $table_name, $_fields, $old_schema, true, $allow_delete );
                $_columns = Option::get( $_results, 'columns', array() );
                foreach ( $_columns as $_name => $_definition )
                {
                    $_command->reset();
                    $_command->addColumn( $table_name, $_name, $_definition );
                }
                $_columns = Option::get( $_results, 'alter_columns', array() );
                foreach ( $_columns as $_name => $_definition )
                {
                    $_command->reset();
                    $_command->alterColumn( $table_name, $_name, $_definition );
                }
                $_columns = Option::get( $_results, 'drop_columns', array() );
                foreach ( $_columns as $_name )
                {
                    $_command->reset();
                    $_command->dropColumn( $table_name, $_name );
                }
            }
            catch ( \Exception $ex )
            {
                Log::error( 'Exception updating table: ' . $ex->getMessage() );
                throw $ex;
            }

            $_labels = Option::get( $_results, 'labels', array() );
            $_references = Option::get( $_results, 'references', array() );
            $_indexes = Option::get( $_results, 'indexes', array() );
        }

        // add table labels
        $_label = Option::get( $data, 'label' );
        $_plural = Option::get( $data, 'plural' );
        if ( !is_null( $_label ) || !is_null( $_plural ) )
        {
            if ( ( $_label != Option::get( $old_schema, 'label' ) ) &&
                 ( $_plural != Option::get( $old_schema, 'plural' ) ) )
            {
                $_labels[] = array(
                    'table'  => $table_name,
                    'field'  => '',
                    'label'  => $_label,
                    'plural' => $_plural
                );
            }
        }

        $_results = array('references' => $_references, 'indexes' => $_indexes, 'labels' => $_labels);

        return $_results;
    }

    /**
     * Returns a generic type suitable for type-casting
     *
     * @param \CDbColumnSchema $column
     *
     * @return string
     */
    protected static function determineGenericType( $column )
    {
        $_simpleType = strstr( $column->dbType, '(', true );
        $_simpleType = strtolower( $_simpleType ? : $column->dbType );

        switch ( $_simpleType )
        {
            case 'bit':
            case 'bool':
            case 'boolean':
                return 'boolean';

            case 'number': // Oracle for boolean, integers and decimals
                if ( $column->size == 1 )
                {
                    return 'boolean';
                }
                if ( empty( $column->scale ) )
                {
                    return 'integer';
                }

                return 'decimal';

            case 'decimal':
            case 'numeric':
            case 'percent':
                return 'decimal';

            case 'double':
            case 'double precision':
            case 'binary_double': // oracle
                return 'double';

            case 'float':
            case 'real':
            case 'binary_float': // oracle
                if ( $column->size == 53 )
                {
                    return 'double';
                }

                return 'float';

            case 'money':
            case 'smallmoney':
                return 'money';

            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
            case 'int':
            case 'integer':
                if ( $column->size == 1 )
                {
                    return 'boolean';
                }

                return 'integer';

            case 'timestamp':
            case 'timestamp with time zone': //  PGSQL
            case 'timestamp without time zone': //  PGSQL
            case 'datetimeoffset': //  MSSQL
                return 'timestamp';

            case 'datetime':
            case 'datetime2':
                return 'datetime';

            case 'date':
                return 'date';
            case 'time':
                return 'time';

            case 'binary':
            case 'varbinary':
            case 'blob':
            case 'mediumblob':
            case 'largeblob':
                return 'binary';

            //	String types
            case 'text':
            case 'mediumtext':
            case 'longtext':
            case 'clob':
            case 'nclob':
                return 'text';

            case 'varchar':
                if ( $column->size == -1 )
                {
                    return 'text'; // varchar(max) in MSSQL
                }

                return 'string';

            case 'string':
            case 'varchar2':
            case 'char':
            case 'character':
            case 'character varying':
            case 'nchar':
            case 'nvarchar':
            case 'nvarchar2':
            default:
                return 'string';
        }
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
