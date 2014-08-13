<?php
/**
 * This file is part of the DreamFactory Freezer(tm)
 *
 * Copyright 2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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

use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;
use Kisma\Core\Utility\Storage;

/**
 * Backs up and restores a directory
 */
class PathZipper extends \ZipArchive
{
    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type string
     */
    protected $_zipName;
    /**
     * @type string
     */
    protected $_pathToZip;
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $zipName The name of the zip file to create or update
     * @param string $this    ->_pathToZip The path of what to put in zip file
     */
    public function __construct( $zipName, $pathToZip )
    {
        $this->_zipName = $zipName;
        $this->_pathToZip = $pathToZip;
    }

    public function zipPath( $localName = null, $exclude = array() )
    {
        $_path = $this->_validatePath( $this->_pathToZip );

        //  Make a temp file name...
        $_zipName = $this->_pathToZip . DIRECTORY_SEPARATOR ..$this->_zipName . ( false === stripos( $this->_zipName, '.zip' ) ? '.zip' : null );
        $_checksum = md5_file( $_zipName );
        $_timestamp = time();

        //  Remove temporary file
        \unlink( $_zipName );

        return true;
    }

    /**
     * Given a path, build a zip file and return the name
     *
     * @param string $localName The local name of the path
     * @param string $data      If provided, write to zip file instead of building from path
     * @param array  $excludes Files in this array will not be added to the xip file
     *
     * @throws FreezerException
     * @return string
     */
    protected function _createZipFile( $localName = null, $data = null, array $excludes = array )
    {
        $_zipName = tempnam( sys_get_temp_dir(), sha1( uniqid() ) );

        if ( !$this->open( $_zipName, static::CREATE ) )
        {
            throw new FreezerException( 'Unable to create temporary zip file.' );
        }

//  Restore prior zipped content?
if ( null !== $data )
{
    if ( false === ( $_bytes = file_put_contents( $_zipName, $data ) ) )
    {
        $this->close();
        @\unlink( $_zipName );

        throw new FreezerException( 'Error creating temporary zip file for restoration.' );
    }

    return $_zipName;
}

//  Build from $path
if ( $localName )
{
    $this->addEmptyDir( $localName );
}

$this->_addPath( $path, $localName );
$this->close();

return $_zipName;
}

/**
 * Recursively add a path to myself
 *
 * @param string $path
 * @param string $localName
 *
 * @return bool
 */
protected
function _addPath( $path, $localName = null )
{
    $_excluded = array(
        ltrim( static::CHECKSUM_FILE_NAME, DIRECTORY_SEPARATOR ),
    );

    $_excludedDirs = array(
        '.private/app.store',
        'swagger',
    );

    $_files = FileSystem::glob( $path . DIRECTORY_SEPARATOR . '*.*', GlobFlags::GLOB_NODOTS | GlobFlags::GLOB_RECURSE );

    if ( empty( $_files ) )
    {
        return false;
    }

    //  Clean out the stuff we don't want in there...
    foreach ( $_files as $_index => $_file )
    {
        foreach ( $_excludedDirs as $_mask )
        {
            if ( 0 === strpos( $_file, $_mask, 0 ) )
            {
                unset( $_files[$_index] );
            }
        }

        if ( in_array( $_file, $_excluded ) && isset( $_files[$_index] ) )
        {
            unset( $_files[$_index] );
        }
    }

    foreach ( $_files as $_file )
    {
        $_filePath = $path . DIRECTORY_SEPARATOR . $_file;
        $_localFilePath = ( $localName ? $localName . DIRECTORY_SEPARATOR . $_file : $_file );

        if ( is_dir( $_filePath ) )
        {
            $this->addEmptyDir( $_file );
        }
        else
        {
            $this->addFile( $_filePath, $_localFilePath );
        }
    }

    return true;
}

/**
 * @param string $path
 * @param bool   $restoring If true, the directory will be created if it does not exist
 *
 * @return string
 */
protected
function _validatePath( $path, $restoring = false )
{
    if ( empty( $path ) )
    {
        throw new \InvalidArgumentException( 'Invalid path specified.' );
    }

    if ( !is_dir( $path ) )
    {
        //  Try and make the directory if wanted
        if ( !$restoring || false === ( $_result = mkdir( $path, 0777, true ) ) )
        {
            throw new \InvalidArgumentException(
                'The path "' . $path . '" does not exist and/or cannot be created. Please validate installation.'
            );
        }
    }

    //  Make sure we can read/write there...
    if ( !is_readable( $path ) || !is_writable( $path ) )
    {
        throw new \InvalidArgumentException(
            'The path "' . $path . '" exists but cannot be accessed. Please validate installation.'
        );
    }

    return rtrim( $path, '/' );
}

}
