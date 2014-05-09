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

use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * NoSqlDbSvc.php
 * A service to handle NoSQL (schema-less) database services accessed through the REST API.
 *
 */
abstract class NoSqlDbSvc extends BaseDbSvc
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param null|array $post_data
     *
     * @return array
     */
    protected function _gatherExtrasFromRequest( &$post_data = null )
    {
        $_extras = parent::_gatherExtrasFromRequest( $post_data );

        if ( static::POST == $this->_action )
        {
            // allow system to create psuedo-random identifiers, applicable
            $_extras['create_id'] = FilterInput::request(
                'create_id',
                Option::getBool( $post_data, 'create_id' ),
                FILTER_VALIDATE_BOOLEAN
            );
        }

        // allow batching of record requests, if applicable
        $_extras['batch'] = FilterInput::request(
            'batch',
            Option::getBool( $post_data, 'batch' ),
            FILTER_VALIDATE_BOOLEAN
        );

        return $_extras;
    }

    /**
     * General method for creating a pseudo-random identifier
     *
     * @param string $table Name of the table where the item will be stored
     *
     * @return string
     */
    protected static function _createRecordId( $table )
    {
        $_randomTime = abs( time() );

        if ( $_randomTime == 0 )
        {
            $_randomTime = 1;
        }

        $_random1 = rand( 1, $_randomTime );
        $_random2 = rand( 1, 2000000000 );
        $_generateId = strtolower( md5( $_random1 . $table . $_randomTime . $_random2 ) );
        $_randSmall = rand( 10, 99 );

        return $_generateId . $_randSmall;
    }

}
