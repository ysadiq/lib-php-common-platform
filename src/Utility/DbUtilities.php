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
use Kisma\Core\Utility\Sql;

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
     * @param string | array $where
     * @param array          $params
     * @param string         $select
     *
     * @return array
     */
    public static function getLabels( $where, $params = array(), $select = '*' )
    {
        $labels = array();
        try
        {
            $_db = Pii::db();
            $command = $_db->createCommand();
            $command->select( $select );
            $command->from( 'df_sys_schema_extras' );
            $command->where( $where, $params );
            $labels = $command->queryAll();
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Failed to query df_sys_schema_extras. ' . $_ex->getMessage() );
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
        if ( empty( $labels ) )
        {
            return;
        }

        try
        {
            $_db = Pii::db();

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
        catch ( \Exception $_ex )
        {
            Log::error( 'Failed to update df_sys_schema_extras. ' . $_ex->getMessage() );
        }
    }

    /**
     * @param      $where
     * @param null $params
     */
    public static function removeLabels( $where, $params = null )
    {
        try
        {
            $_db = Pii::db();
            $command = $_db->createCommand();
            $command->delete( 'df_sys_schema_extras', $where, $params );
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
