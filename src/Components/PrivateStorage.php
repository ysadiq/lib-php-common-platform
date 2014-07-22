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

use DreamFactory\Platform\Yii\Models\Config;
use Kisma\Core\Exceptions\FileSystemException;
use Kisma\Core\Utility\Log;

/**
 * Backup/Restore private storage area to df_sys_config
 */
class PrivateStorage
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string The source path
     */
    protected $_sourcePath = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $sourcePath
     */
    public function __construct( $sourcePath )
    {
        if ( !is_dir( $sourcePath ) )
        {
            //  Try and make the directory
            if ( false === ( $_result = mkdir( $sourcePath, 0777, true ) ) )
            {
                throw new \InvalidArgumentException( 'The source path "' . $sourcePath . '" cannot be created. Please validate installation.' );
            }
        }

        //  Make sure we can read/write there...
        if ( !is_readable( $sourcePath ) || !is_writable( $sourcePath ) )
        {
            throw new \InvalidArgumentException( 'The source path "' . $sourcePath . '" exists but cannot be accessed. Please validate installation.' );
        }

        $this->_sourcePath = rtrim( $sourcePath, '/' ) . '/';
    }

    /**
     * Creates a zip of the private directory and writes to the config table
     */
    public function backup()
    {
        //  Make a temp file name...
        $_zipName = tempnam( sys_get_temp_dir(), sha1( uniqid() ) );

        $_zip = new \ZipArchive();

        if ( !$_zip->open( $_zipName, \ZipArchive::CREATE ) )
        {
            throw new FileSytemException( 'Unable to create temporary zip file.' );
        }

        $_zip->addGlob( '*', GLOB_BRACE, array('remove_path' => $this->_sourcePath) );
        $_zip->addGlob( '.registration_complete*', GLOB_BRACE, array('remove_path' => $this->_sourcePath) );
        $_zip->close();

        //  Read configuration record
        /** @var Config $_config */
        if ( null === ( $_config = Config::model()->find( array('select' => 'private_storage', 'limit' => 1, 'order' => 'id') ) ) )
        {
            throw new \RuntimeException( 'Platform configuration record not found.' );
        }

        try
        {
            if ( false === ( $_config->private_storage = file_get_contents( $_zipName ) ) )
            {
                throw new \RuntimeException( 'Error reading temporary zip file for storage.' );
            }

            if ( !$_config->save() )
            {
                throw new FileSystemException( 'Save failure: ' . $_config->getErrorsForLogging() );
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
     * @param bool $overwrite If true, any conflict files will be overwritten. Otherwise they will be skipped
     *
     * @throws \Exception
     */
    public function restore( $overwrite = true )
    {
        //  Read configuration record
        /** @var Config $_config */
        if ( null === ( $_config = Config::model()->find( array('select' => 'private_storage', 'limit' => 1, 'order' => 'id') ) ) )
        {
            throw new \RuntimeException( 'Platform configuration record not found.' );
        }

        //  Nothing to restore, bail...
        if ( empty( $_config->private_storage ) )
        {
            return;
        }

        //  Make a temp file name...
        $_zipName = tempnam( sys_get_temp_dir(), sha1( uniqid() ) );

        if ( false === ( $_bytes = file_put_contents( $_zipName, $_config->private_storage ) ) )
        {
            throw new FileSystemException( 'Error creating temporary zip file for restoration.' );
        }

        $_zip = new \ZipArchive();

        if ( !$_zip->open( $_zipName ) )
        {
            throw new FileSystemException( 'Unable to open temporary zip file.' );
        }

        try
        {
            $_zip->extractTo( $this->_sourcePath );
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
}
