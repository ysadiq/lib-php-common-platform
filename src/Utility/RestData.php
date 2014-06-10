<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Utility;

use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * REST Request Utilities
 */
class RestData
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Checks for post data and performs gunzip functions
     * Also checks for single uploaded data in a file if requested
     * Also converts output to native php array if requested
     *
     * @param bool $from_file
     * @param bool $as_array
     *
     * @throws \Exception
     * @return string|array
     */
    public static function getPostedData( $from_file = false, $as_array = false )
    {
        $_data = Pii::request( false )->getContent();

        if ( empty( $_data ) && $from_file )
        {
            $_file = Option::get( $_FILES, 'files' );
            if ( empty( $_file ) )
            {
                return null; // can't find anything to return
            }

            //	Older html multi-part/form-data post, single or multiple files
            if ( is_array( $_file['error'] ) )
            {
                throw new \Exception( 'Only a single file is allowed for import of data.' );
            }

            $_name = $_file['name'];
            if ( UPLOAD_ERR_OK !== ( $_error = $_file['error'] ) )
            {
                throw new \Exception( "Failed to receive upload of \"$_name\": $_error" );
            }

            $_contentType = $_file['type'];
//            $_extension = strtolower( pathinfo( $_name, PATHINFO_EXTENSION ) );
            $_filename = $_file['tmp_name'];
            $_data = file_get_contents( $_filename );
        }
        else
        {
            $_contentType = FilterInput::server( 'CONTENT_TYPE' );
        }

        if ( !empty( $_data ) && $as_array )
        {
            $_postData = $_data;
            $_data = array();
            if ( !empty( $_contentType ) )
            {
                if ( false !== stripos( $_contentType, '/json' ) )
                {
                    // application/json
                    try
                    {
                        $_data = DataFormat::jsonToArray( $_postData );
                    }
                    catch ( \Exception $_ex )
                    {
                        if ( empty( $_postData ) )
                        {
                            throw $_ex;
                        }

                        $_data = $_postData;
                    }
                }
                elseif ( false !== stripos( $_contentType, '/xml' ) )
                {
                    // application/xml or text/xml
                    $_data = DataFormat::xmlToArray( $_postData );
                }
                elseif ( false !== stripos( $_contentType, '/csv' ) )
                {
                    // text/csv
                    $_data = DataFormat::csvToArray( $_postData );
                }
            }

            if ( empty( $_data ) )
            {
                // last chance, assume it is json
                $_data = DataFormat::jsonToArray( $_postData );
            }

            // get rid of xml wrapper if present
            $_data = Option::get( $_data, 'dfapi', $_data );
        }

        return $_data;
    }
}
