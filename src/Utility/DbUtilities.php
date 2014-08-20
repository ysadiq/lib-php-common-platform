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

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;

/**
 * DbUtilities
 * Generic database utilities
 */
class DbUtilities
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

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
     * @param int            $service_id
     * @param string | array $table_names
     * @param bool           $include_fields
     * @param string | array $select
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public static function getSchemaExtrasForTables( $service_id, $table_names, $include_fields = true, $select = '*' )
    {
        $_db = Pii::db();
        $_params = array();
        $_where = array('and');

        if ( empty( $service_id ) )
        {
            $_where[] = $_db->quoteColumnName( 'service_id' ) . ' IS NULL';
        }
        else
        {
            $_where[] = $_db->quoteColumnName( 'service_id' ) . ' = :id';
            $_params[':id'] = $service_id;
        }

        if ( false === $_values = static::validateAsArray( $table_names, ',', true ) )
        {
            throw new \InvalidArgumentException( 'Invalid table list provided.' );
        }

        $_where[] = array('in', 'table', $_values);

        if ( !$include_fields )
        {
            $_where[] = $_db->quoteColumnName( 'field' ) . " = ''";
        }

        $_results = static::_querySchemaExtras( $_where, $_params, $select );

        if ( empty( $_results ) && ( 1 === $service_id ) )
        {
            // backwards compatible for native databases
            $_params = array();
            $_where = array('and');
            $_where[] = $_db->quoteColumnName( 'service_id' ) . ' IS NULL';
            $_where[] = array('in', 'table', $_values);

            if ( !$include_fields )
            {
                $_where[] = $_db->quoteColumnName( 'field' ) . " = ''";
            }

            $_results = static::_querySchemaExtras( $_where, $_params, $select );
        }

        return $_results;
    }

    /**
     * @param int            $service_id
     * @param string         $table_name
     * @param string | array $field_names
     * @param string | array $select
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public static function getSchemaExtrasForFields( $service_id, $table_name, $field_names, $select = '*' )
    {
        $_db = Pii::db();
        $_params = array();
        $_where = array('and');

        if ( empty( $service_id ) )
        {
            $_where[] = $_db->quoteColumnName( 'service_id' ) . ' IS NULL';
        }
        else
        {
            $_where[] = $_db->quoteColumnName( 'service_id' ) . ' = :id';
            $_params[':id'] = $service_id;
        }

        $_where[] = $_db->quoteColumnName( 'table' ) . ' = :tn';
        $_params[':tn'] = $table_name;

        if ( false === $_values = static::validateAsArray( $field_names, ',', true ) )
        {
            throw new \InvalidArgumentException( 'Invalid field list. ' . $field_names );
        }

        $_where[] = array('in', 'field', $_values);

        $_results = static::_querySchemaExtras( $_where, $_params, $select );

        if ( empty( $_results ) && ( 1 === $service_id ) )
        {
            // backwards compatible for native databases
            $_params = array();
            $_where = array('and');
            $_where[] = $_db->quoteColumnName( 'service_id' ) . ' IS NULL';
            $_where[] = $_db->quoteColumnName( 'table' ) . ' = :tn';
            $_params[':tn'] = $table_name;
            $_where[] = array('in', 'field', $_values);

            $_results = static::_querySchemaExtras( $_where, $_params, $select );
        }

        return $_results;
    }

    protected static function _querySchemaExtras( $where, $params, $select = '*' )
    {
        $_extras = array();
        try
        {
            $_db = Pii::db();
            $_cmd = $_db->createCommand();
            $_cmd->select( $select );
            $_cmd->from( 'df_sys_schema_extras' );
            $_cmd->where( $where, $params );
            $_extras = $_cmd->queryAll();
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Failed to query df_sys_schema_extras. ' . $_ex->getMessage() );
        }

        return $_extras;
    }

    /**
     * @param int   $service_id
     * @param array $labels
     *
     * @return void
     */
    public static function setSchemaExtras( $service_id, $labels )
    {
        if ( empty( $labels ) )
        {
            return;
        }

        $_tables = array();
        foreach ( $labels as $_label )
        {
            $_tables[] = Option::get( $_label, 'table' );
        }

        $_tables = array_unique( $_tables );
        $_oldRows = static::getSchemaExtrasForTables( $service_id, $_tables );

        try
        {
            $_db = Pii::db();

            $_inserts = $_updates = array();

            foreach ( $labels as $_label )
            {
                $_table = Option::get( $_label, 'table' );
                $_field = Option::get( $_label, 'field' );
                $_id = null;
                foreach ( $_oldRows as $_row )
                {
                    if ( ( Option::get( $_row, 'table' ) == $_table ) && ( Option::get( $_row, 'field' ) == $_field ) )
                    {
                        $_id = Option::get( $_row, 'id' );
                    }
                }

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
                        $_insert['service_id'] = $service_id;
                        $_command->insert( 'df_sys_schema_extras', $_insert );
                    }
                }

                if ( !empty( $_updates ) )
                {
                    foreach ( $_updates as $_id => $_update )
                    {
                        $_command->reset();
                        $_update['service_id'] = $service_id;
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
        catch ( \Exception $_ex )
        {
            Log::error( 'Failed to update df_sys_schema_extras. ' . $_ex->getMessage() );
        }
    }

    /**
     * @param int            $service_id
     * @param string | array $table_names
     *
     */
    public static function removeSchemaExtrasForTables( $service_id, $table_names, $include_fields = true )
    {
        try
        {
            $_db = Pii::db();
            $_params = array();
            $_where = array('and');

            if ( empty( $service_id ) )
            {
                $_where[] = $_db->quoteColumnName( 'service_id' ) . ' IS NULL';
            }
            else
            {
                $_where[] = $_db->quoteColumnName( 'service_id' ) . ' = :id';
                $_params[':id'] = $service_id;
            }

            if ( false === $_values = static::validateAsArray( $table_names, ',', true ) )
            {
                throw new \InvalidArgumentException( 'Invalid table list. ' . $table_names );
            }

            $_where[] = array('in', 'table', $_values);

            if ( !$include_fields )
            {
                $_where[] = $_db->quoteColumnName( 'field' ) . " = ''";
            }

            $_db->createCommand()->delete( 'df_sys_schema_extras', $_where, $_params );
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Failed to delete from df_sys_schema_extras. ' . $_ex->getMessage() );
        }
    }

    /**
     * @param int            $service_id
     * @param string         $table_name
     * @param string | array $field_names
     */
    public static function removeSchemaExtrasForFields( $service_id, $table_name, $field_names )
    {
        try
        {
            $_db = Pii::db();
            $_params = array();
            $_where = array('and');

            if ( empty( $service_id ) )
            {
                $_where[] = $_db->quoteColumnName( 'service_id' ) . ' IS NULL';
            }
            else
            {
                $_where[] = $_db->quoteColumnName( 'service_id' ) . ' = :id';
                $_params[':id'] = $service_id;
            }

            $_where[] = $_db->quoteColumnName( 'table' ) . ' = :tn';
            $_params[':tn'] = $table_name;

            if ( false === $_values = static::validateAsArray( $field_names, ',', true ) )
            {
                throw new \InvalidArgumentException( 'Invalid field list. ' . $field_names );
            }

            $_where[] = array('in', 'field', $_values);

            $_db->createCommand()->delete( 'df_sys_schema_extras', $_where, $_params );
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Failed to delete from df_sys_schema_extras. ' . $_ex->getMessage() );
        }
    }

    /**
     * @param array $original
     *
     * @return array
     */
    public static function reformatFieldLabelArray( $original )
    {
        if ( empty( $original ) )
        {
            return array();
        }

        $_new = array();
        foreach ( $original as $_label )
        {
            $_new[Option::get( $_label, 'field' )] = $_label;
        }

        return $_new;
    }

    /**
     * @param $type
     *
     * @return null|string
     */
    public static function determinePhpConversionType( $type )
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

    public static function formatValue( $value, $type )
    {
        switch ( $type )
        {
            case 'int':
            case 'integer':
                return intval( $value );

            case 'decimal':
            case 'double':
            case 'float':
                return floatval( $value );

            case 'boolean':
            case 'bool':
                return Scalar::boolval( $value );
        }

        return $value;
    }
}
