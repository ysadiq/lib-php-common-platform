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
use DreamFactory\Platform\Enums\ContentTypes;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Exceptions\FileSystemException;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * A universal translator
 */
class BabelFish
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
     * @param bool $raw Return the data verbatim
     *
     * @throws \Exception
     * @return string|array
     */
    public static function getRequestData( $from_file = false, $as_array = false, $raw = false )
    {
        $_request = Pii::request( false );

        if ( 'null' == ( $_data = $_request->getContent() ) )
        {
            $_data = null;
        }

        if ( $raw )
        {
            return $_data;
        }

        $_contentType = $_request->getContentType();

        if ( $from_file && empty( $_data ) )
        {
            if ( false === ( $_data = static::_getPostedFileData( $_fileContentType ) ) )
            {
                return null;
            }

            $_contentType = $_request->getFormat( $_fileContentType );
        }

        //  Still empty? bail
        if ( empty( $_data ) )
        {
            return $_data;
        }

        if ( $as_array )
        {
            $_postData = $_data;
            $_data = array();

            switch ( ContentTypes::fromMimeType( $_contentType ) )
            {
                case ContentTypes::JSON:
                    $_data = static::_toJson( $_postData );
                    break;

                case ContentTypes::XML:
                    $_data = DataFormat::xmlToArray( $_postData );
                    break;

                case ContentTypes::CSV:
                    $_data = DataFormat::csvToArray( $_postData );
                    break;

                default:
                    if ( empty( $_data ) )
                    {
                        $_data = static::_toJson( $_postData );
                    }

                    //  get rid of xml wrapper if present
                    $_data = Option::get( $_data, 'dfapi', $_data );
                    break;
            }
        }

        return $_data;
    }

    /**
     * @param string $contentType The content-type of the uploaded file is returned in this variable
     *
     * @throws \Kisma\Core\Exceptions\FileSystemException
     * @return string|bool
     */
    protected static function _getPostedFileData( &$contentType = null )
    {
        if ( null === ( $_file = Option::get( $_FILES, 'files' ) ) )
        {
            return false;
        }

        //  Older html multi-part/form-data post, single or multiple files
        if ( is_array( $_file['error'] ) )
        {
            throw new \InvalidArgumentException( 'Only a single file is allowed for import of data.' );
        }

        $_name = $_file['name'];

        if ( UPLOAD_ERR_OK !== ( $_error = $_file['error'] ) )
        {
            throw new FileSystemException( 'Upload of file "' . $_name . '" failed: ' . $_error );
        }

        $contentType = $_file['type'];
        $_filename = $_file['tmp_name'];

        if ( false === ( $_data = file_get_contents( $_filename ) ) )
        {
            throw new FileSystemException( 'Error reading contents of uploaded file.' );
        }

        return $_data;
    }

    /**
     * @param string $postData
     *
     * @return array|string
     * @throws \Exception
     */
    protected static function _toJson( $postData )
    {
        if ( empty( $postData ) )
        {
            return null;
        }

        try
        {
            $_data = DataFormat::jsonToArray( $postData );
        }
        catch ( \Exception $_ex )
        {
            //  Ignored
        }

        if ( false !== ( $_json = json_decode( $postData, true ) ) && JSON_ERROR_NONE == json_last_error() )
        {
            return $_json;
        }

        return $postData;
    }
}
