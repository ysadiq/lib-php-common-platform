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

use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Yii\Models\Config;
use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Exceptions\FileSystemException;
use Kisma\Core\Exceptions\StorageException;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Storage;

/**
 * Backup/Restore private storage area to df_sys_config
 */
class PrivateStorage extends \ZipArchive
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Creates a zip of a directory and writes to the config table
     *
     * @param string $sourcePath
     * @param string $localName
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \Exception
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    public static function backup( $sourcePath, $localName = null )
    {
        $_path = static::_validatePath( $sourcePath );

        $_zip = new static();

        //  Make a temp file name...
        $_zipName = tempnam( sys_get_temp_dir(), sha1( uniqid() ) );

        if ( !$_zip->open( $_zipName, \ZipArchive::CREATE ) )
        {
            throw new FileSystemException( 'Unable to create temporary zip file.' );
        }

        if ( $localName )
        {
            $_zip->addEmptyDir( $localName );
        }

        $_zip->_addPath( $_path, $localName );
        $_zip->close();

        //  Read configuration record
        /** @var Config $_config */
        $_config = ResourceStore::model( 'config' )->find( array( 'select' => 'id, private_storage', 'limit' => 1, 'order' => 'id' ) );

        if ( empty( $_config ) )
        {
            throw new \RuntimeException( 'Platform configuration record not found.' );
        }

        try
        {
            if ( false === ( $_data = file_get_contents( $_zipName ) ) )
            {
                throw new \RuntimeException( 'Error reading temporary zip file for storage.' );
            }

            if ( !empty( $_data ) )
            {
                $_payload = array(
                    '.private' => $_data,
                );

                $_config->setAttribute( 'private_storage', Storage::freeze( $_payload ) );

                if ( !$_config->update( array( 'private_storage' ) ) )
                {
                    throw new StorageException( $_config->getErrorsForLogging() );
                }
            }
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Exception saving private backup: ' . $_ex->getMessage() );
            throw $_ex;
        }

        //  Remove temporary file
        \unlink( $_zipName );
    }

    /**
     * Reads the private storage from the configuration table and restores the directory
     *
     * @param string $path
     *
     * @throws \Exception
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    public static function restore( $path )
    {
        $_path = static::_validatePath( $path );

        //  Read configuration record
        /** @var Config $_config */
        if ( null === ( $_config = Config::model()->find( array( 'select' => 'private_storage', 'limit' => 1, 'order' => 'id' ) ) ) )
        {
            throw new \RuntimeException( 'Platform configuration record not found.' );
        }

        //  Nothing to restore, bail...
        $_payload = Storage::defrost( $_config->getAttribute( 'private_storage' ) );

        if ( empty( $_payload ) )
        {
            return;
        }

        $_data = Option::get( $_payload, '.private' );

        //  Make a temp file name...
        $_zipName = tempnam( sys_get_temp_dir(), sha1( uniqid() ) );

        if ( false === ( $_bytes = file_put_contents( $_zipName, $_data ) ) )
        {
            throw new FileSystemException( 'Error creating temporary zip file for restoration.' );
        }

        //  Open our new zip and extract...
        $_zip = new static();

        if ( !$_zip->open( $_zipName ) )
        {
            throw new FileSystemException( 'Unable to open temporary zip file.' );
        }

        try
        {
            $_zip->extractTo( $_path );
            $_zip->close();
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Exception restoring private backup: ' . $_ex->getMessage() );
            throw $_ex;
        }

        //  Remove temporary file
        \unlink( $_zipName );
    }

    /**
     * Recursively add a path to myself
     *
     * @param string $path
     * @param string $localName
     */
    protected function _addPath( $path, $localName = null )
    {
        $_files = FileSystem::glob( $path . '/*.*', GlobFlags::GLOB_NODOTS | GlobFlags::GLOB_RECURSE );

        if ( empty( $_files ) )
        {
            return;
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
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected static function _validatePath( $path )
    {
        if ( empty( $path ) )
        {
            throw new \InvalidArgumentException( 'Invalid path specified.' );
        }

        if ( !is_dir( $path ) )
        {
            //  Try and make the directory
            if ( false === ( $_result = mkdir( $path, 0777, true ) ) )
            {
                throw new \InvalidArgumentException( 'The path "' . $path . '" cannot be created. Please validate installation.' );
            }
        }

        //  Make sure we can read/write there...
        if ( !is_readable( $path ) || !is_writable( $path ) )
        {
            throw new \InvalidArgumentException( 'The path "' . $path . '" exists but cannot be accessed. Please validate installation.' );
        }

        return rtrim( $path, '/' );
    }
}
