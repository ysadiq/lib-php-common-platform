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
namespace DreamFactory\Platform\Components;

use DreamFactory\Platform\Interfaces\TransformerLike;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * DataTablesFormatter
 * A simple data formatter
 */
class DataTablesFormatter implements TransformerLike
{
    /**
     * @param mixed $dataToFormat
     * @param array $options Any formatter-specific options
     *
     * @return mixed The formatted data
     */
    public static function format( $dataToFormat, $options = array() )
    {
        if ( null === ( $_data = Option::get( $dataToFormat, 'resource' ) ) )
        {
            if ( null === ( $_data = Option::get( $dataToFormat, 'record' ) ) )
            {
                $_key = null;
                $_data = $dataToFormat;
            }
        }

        $_count = 0;
        $_echo = FilterInput::get( $_GET, 'sEcho', FILTER_SANITIZE_NUMBER_INT );
        $_response = array();

        if ( !empty( $_data ) )
        {
            foreach ( $_data as $_row )
            {
                //	DataTables just gets the values, not the keys
                $_response[] = array_values( $_row );
                $_count++;

                unset( $_row );
            }

            unset( $_rows );
        }

        //	DT expected format
        return array(
            'sEcho'                => $_echo,
            'iTotalRecords'        => $_count,
            'iTotalDisplayRecords' => $_count,
            'aaData'               => $_response,
        );
    }

    /**
     * Adds criteria garnered from the query string from DataTables
     *
     * @param array|\CDbCriteria $criteria
     * @param array              $columns
     *
     * @return array|\CDbCriteria
     */
    public static function buildCriteria( $columns, $criteria = null )
    {
        $criteria = $criteria ? : array();

        $_criteria = ( !( $criteria instanceof \CDbCriteria ) ? new \CDbCriteria( $criteria ) : $criteria );

        //	Columns
        $_criteria->select = ( !empty( $_columns ) ? implode( ', ', $_columns ) : '*' );

        //	Limits
        $_limit = FilterInput::get( INPUT_GET, 'iDisplayLength', -1, FILTER_SANITIZE_NUMBER_INT );
        $_limitStart = FilterInput::get( INPUT_GET, 'iDisplayStart', 0, FILTER_SANITIZE_NUMBER_INT );

        if ( -1 != $_limit )
        {
            $_criteria->limit = $_limit;
            $_criteria->offset = $_limitStart;
        }

        //	Sort
        $_order = array();

        if ( isset( $_GET['iSortCol_0'] ) )
        {
            for ( $_i = 0, $_count = FilterInput::get( INPUT_GET, 'iSortingCols', 0, FILTER_SANITIZE_NUMBER_INT ); $_i < $_count; $_i++ )
            {
                $_column = FilterInput::get( INPUT_GET, 'iSortCol_' . $_i, 0, FILTER_SANITIZE_NUMBER_INT );

                if ( isset( $_GET['bSortable_' . $_column] ) && 'true' == $_GET['bSortable_' . $_column] )
                {
                    $_order[] = $columns[$_column] . ' ' . FilterInput::get( INPUT_GET, 'sSortDir_' . $_i, null, FILTER_SANITIZE_STRING );
                }
            }
        }

        if ( !empty( $_order ) )
        {
            $_criteria->order = implode( ', ', $_order );
        }

        return $_criteria;
    }
}