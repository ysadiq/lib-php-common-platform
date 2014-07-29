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

use DreamFactory\Platform\Interfaces\WatcherLike;
use DreamFactory\Platform\Services\SystemManager;
use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Exceptions\FileSystemException;
use Kisma\Core\Exceptions\StorageException;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;
use Kisma\Core\Utility\Storage;

/**
 * Backs up and restores a directory for use on systems without persistent storage.
 */
class DirectoryStorage extends \ZipArchive
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const DEFAULT_TABLE_NAME = 'dir_store';
    /**
     * @type string
     */
    const CHECKSUM_FILE_NAME = '.md5';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var \PDO The PDO instance to use
     */
    protected $_pdo;
    /**
     * @var string A name to associate with this directory store
     */
    protected $_storageId;
    /**
     * @var string The prefix of the storage table's name.
     */
    protected $_tablePrefix = SystemManager::SYSTEM_TABLE_PREFIX;
    /**
     * @var string The base name of the table
     */
    protected $_tableName = self::DEFAULT_TABLE_NAME;
    /**
     * @var PathWatcher
     */
    protected $_watcher = false;
    /**
     * @var array The paths I'm managing
     */
    protected $_paths = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string      $storageId   The name of this directory store
     * @param \PDO        $pdo         The PDO instance to use
     * @param WatcherLike $watcher     A watcher instance, if you have one
     * @param string      $tableName   The base name of the table. Defaults to "dir_store"
     * @param string      $tablePrefix The prefix of the storage table. Defaults to "df_sys_"
     *
     * @throws \Kisma\Core\Exceptions\StorageException
     */
    public function __construct( $storageId, \PDO $pdo, WatcherLike $watcher = null, $tableName = self::DEFAULT_TABLE_NAME, $tablePrefix = SystemManager::SYSTEM_TABLE_PREFIX )
    {
        $this->_storageId = $storageId;
        $this->_pdo = $pdo;
        $this->_tablePrefix = $tablePrefix;
        $this->_tableName = $tablePrefix . $tableName;

        //  Flush myself before shutdown...
        \register_shutdown_function(
            function ( $store )
            {
                /** @var DirectoryStorage $store */
                $store->_flush();
            },
            $this
        );

        //  Now create the watcher so his shutdown is after mine...
        $this->_watcher = $watcher ?: ( PathWatcher::available() ? new PathWatcher() : null );

        $this->_initDatabase();
    }

    /**
     * @throws \Exception
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    protected function _flush()
    {
        if ( $this->_watcher )
        {
            $_events = $this->_watcher->processEvents( true );

            if ( !empty( $_events ) )
            {
                foreach ( $_events as $_event )
                {
                    if ( !isset( $_event['wd'], $this->_paths, $this->_paths[$_event['wd']] ) )
                    {
                        continue;
                    }

                    //  Watch descriptor from INOTIFY
                    $_id = $_event['wd'];
                    $_path = Option::get( $this->_paths, $_id, array() );
                    $this->backup( $_path['storage_id'], $_path['path'], $_path['local_name'], $_path['new_revision'] );

                    unset( $_event, $_path, $_id );
                }
            }
        }
    }

    /**
     * Creates a zip of a directory and writes to the config table
     *
     * @param string $storageId   The name of this directory store
     * @param string $sourcePath  The directory to store
     * @param string $localName   The local name of the directory
     * @param bool   $newRevision If true, backup will be created as a new row with an incremented revision number
     *
     * @throws \Exception
     * @throws \Kisma\Core\Exceptions\FileSystemException
     * @throws \Kisma\Core\Exceptions\StorageException
     *
     * @return bool
     */
    public function backup( $storageId, $sourcePath, $localName = null, $newRevision = false )
    {
        $_path = $this->_validatePath( $sourcePath );

        //  Make a temp file name...
        $_zipName = $this->_buildZipFile( $_path, $localName );
        $_checksum = md5_file( $_zipName );

        //  Get the latest revision
        $_currentRevision = $this->_getCurrentRevisionId( $storageId );

        if ( $newRevision || false === $_currentRevision )
        {
            $_currentRevision = ( false === $_currentRevision ) ? 0 : ++$_currentRevision;

            $_sql = <<<MYSQL
INSERT INTO {$this->_tableName}
(
    storage_id,
    revision_id,
    data_blob,
    time_stamp,
    check_sum
)
VALUES
(
    :storage_id,
    :revision_id,
    :data_blob,
    :time_stamp,
    :check_sum
)
MYSQL;
        }
        else
        {
            $_sql = <<<MYSQL
UPDATE {$this->_tableName} SET
    data_blob = :data_blob,
    time_stamp = :time_stamp,
    check_sum = :check_sum
WHERE
    storage_id = :storage_id AND
    revision_id = :revision_id
MYSQL;
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
                    $storageId => $_data,
                );

                $_timestamp = time();

                $_result = Sql::execute(
                    $_sql,
                    array(
                        ':storage_id'  => $storageId,
                        ':revision_id' => $_currentRevision,
                        ':data_blob'   => Storage::freeze( $_payload ),
                        ':time_stamp'  => $_timestamp,
                        ':check_sum'   => $_checksum,
                    )
                );

                if ( false === $_result )
                {
                    throw new StorageException( print_r( $this->_pdo->errorInfo(), true ) );
                }

                //  Dump a marker that we've backed up
                if ( !$this->_saveStoreMarker( $_path, $_timestamp, $_checksum ) )
                {
                    Log::error( 'Error creating storage checksum file. No biggie, but you may be out of disk space.' );
                }

                Log::debug( 'DirStore: backup created: ' . $_checksum . '@' . $_timestamp );
            }

            //  Remove temporary file
            \unlink( $_zipName );

            return true;
        }
        catch ( \Exception $_ex )
        {
            Log::error( 'Exception storing backup data: ' . $_ex->getMessage() );
            throw $_ex;
        }
    }

    /**
     * Reads the private storage from the configuration table and restores the directory
     *
     * @param string $storageId   The name of this directory store
     * @param string $path        The path in which to restore the data
     * @param string $localName   The local name of the directory
     * @param bool   $newRevision If true, backup will be created as a new row with an incremented revision number
     *
     * @return bool Only returns false if no backup exists, otherwise TRUE
     * @throws \Exception
     * @throws \Kisma\Core\Exceptions\FileSystemException
     */
    public function restore( $storageId, $path, $localName = null, $newRevision = false )
    {
        $_path = $this->_validatePath( $path );
        $_timestamp = null;

        //  Get the latest revision
        if ( false === ( $_currentRevision = $this->_getCurrentRevisionId( $storageId ) ) )
        {
            //  No backups...
            return false;
        }

        $_marker = $this->_loadStoreMarker( $_path );

        $_sql = <<<MYSQL
SELECT
    *
FROM
    {$this->_tableName}
WHERE
    storage_id = :storage_id AND
    revision_id = :revision_id AND
    time_stamp >= :time_stamp
ORDER BY
    time_stamp DESC
MYSQL;

        $_params = array(
            ':storage_id'  => $storageId,
            ':revision_id' => $_currentRevision,
            ':time_stamp'  => $_marker['time_stamp'],
        );

        if ( false === ( $_row = Sql::find( $_sql, $_params ) ) )
        {
            if ( '00000' != $this->_pdo->errorCode() )
            {
                throw new StorageException(
                    'Error retrieving data to restore: ' . print_r( $this->_pdo->errorInfo(), true )
                );
            }

            //  No rows, nothing to do...
            return false;
        }

        //  Nothing to restore...
        if ( $_marker['time_stamp'] == $_row['time_stamp'] && $_marker['check_sum'] == $_row['check_sum'] )
        {
            return true;
        }

        //  Nothing to restore, bail...
        $_payload = Storage::defrost( Option::get( $_row, 'data_blob' ) );

        if ( empty( $_payload ) )
        {
            //  No data, but has backup
            return true;
        }

        $_data = Option::get( $_payload, $storageId );

        //  Make a temp file name...
        $_zipName = $this->_buildZipFile( $_path, $localName, $_data );

        //  Checksum different?
        if ( $_row['check_sum'] != ( $_checksum = md5_file( $_zipName ) ) )
        {
            //  Open our new zip and extract...
            if ( !$this->open( $_zipName ) )
            {
                throw new FileSystemException( 'Unable to open temporary zip file.' );
            }

            try
            {
                $this->extractTo( $_path );
                $this->close();
            }
            catch ( \Exception $_ex )
            {
                Log::error( 'Exception restoring private backup: ' . $_ex->getMessage() );
                throw $_ex;
            }

            //  Remove temporary file
            \unlink( $_zipName );

            //  Make a new marker with stored info...
            $this->_saveStoreMarker( $_path, $_row['time_stamp'], $_row['check_sum'] );

            //  Watch for changes...
            if ( $this->_watcher )
            {
                $_id = $this->_watcher->watch( $_path );

                if ( false !== $_id )
                {
                    $this->_paths[$_id] = array(
                        'storage_id'   => $storageId,
                        'path'         => $_path,
                        'new_revision' => $newRevision,
                        'local_name'   => $localName
                    );
                }
            }

            Log::debug( 'DirStore: backup restored: ' . $_row['check_sum'] . '@' . $_row['time_stamp'] );
        }

        return true;
    }

    /**
     * Recursively add a path to myself
     *
     * @param string $path
     * @param string $localName
     *
     * @return bool
     */
    protected function _addPath( $path, $localName = null )
    {
        $_excluded = array(
            ltrim( static::CHECKSUM_FILE_NAME, DIRECTORY_SEPARATOR ),
        );

        $_excludedDirs = array(
            '.private/app.store',
            'swagger',
        );

        $_files =
            FileSystem::glob( $path . DIRECTORY_SEPARATOR . '*.*', GlobFlags::GLOB_NODOTS | GlobFlags::GLOB_RECURSE );

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
    protected function _validatePath( $path, $restoring = false )
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

    /**
     * Ensures the storage table exists. Creates if not
     *
     * @throws \Kisma\Core\Exceptions\StorageException
     */
    protected function _initDatabase()
    {
        Sql::setConnection( $this->_pdo );

        //  Create table...
        $_ddl = <<<MYSQL
CREATE TABLE IF NOT EXISTS `{$this->_tableName}`
(
    `storage_id` VARCHAR(64) NOT NULL,
    `revision_id` INT(11) NOT NULL DEFAULT '0',
    `data_blob` MEDIUMTEXT NULL,
    `time_stamp` INT(11) not null,
    `check_sum` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`storage_id`,`revision_id`)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8
MYSQL;

        if ( false === ( $_result = Sql::execute( $_ddl ) ) )
        {
            throw new StorageException( 'Unable to create storage table "' . $this->_tableName . '".' );
        }
    }

    /**
     * @param string $storageId The name of the directory store to check
     *
     * @return bool|int
     * @throws \Kisma\Core\Exceptions\StorageException
     */
    protected function _getCurrentRevisionId( $storageId )
    {
        $_sql = <<<MYSQL
SELECT
    MAX(revision_id)
FROM
    {$this->_tableName}
WHERE
    storage_id = :storage_id
MYSQL;

        if ( false === ( $_revisionId = Sql::scalar( $_sql, 0, array( ':storage_id' => $storageId ) ) ) )
        {
            throw new StorageException(
                'Database error during revision check: ' . print_r( $this->_pdo->errorInfo(), true )
            );
        }

        //  If no revisions, return false...
        return null === $_revisionId ? false : $_revisionId;
    }

    /**
     * Retrieves the timestamp from a marker file, if any
     *
     * @param string $path
     *
     * @return int|null|string
     */
    protected function _loadStoreMarker( $path )
    {
        $_marker = $path . DIRECTORY_SEPARATOR . static::CHECKSUM_FILE_NAME;

        if ( !file_exists( $_marker ) || false === ( $_data = file_get_contents( $_marker ) ) )
        {
            return array( 'time_stamp' => 0, 'check_sum' => null );
        }

        return json_decode( $_data, true );
    }

    /**
     * Sets the timestamp in a marker file
     *
     * @param string $path      The path
     * @param int    $timestamp The timestamp to use or null (which will use the value of time().
     * @param bool   $delete    If true, the marker file will be deleted.
     *
     * @return int|null|string
     */
    protected function _saveStoreMarker( $path, $timestamp, $checksum, $delete = false )
    {
        $_marker = $path . DIRECTORY_SEPARATOR . static::CHECKSUM_FILE_NAME;

        if ( false !== $delete )
        {
            return @\unlink( $_marker );
        }

        $_data = $this->_buildMarkerContent( $path, $timestamp, $checksum );

        return false !== file_put_contents( $_marker, json_encode( $_data ) );
    }

    /**
     * @param string $path
     * @param int    $timestamp
     * @param string $checksum
     *
     * @return array
     */
    protected function _buildMarkerContent( $path, $timestamp, $checksum )
    {
        return array(
            'path'       => $path,
            'time_stamp' => $timestamp,
            'check_sum'  => $checksum,
        );
    }

    /**
     * Given a path, build a zip file and return the name
     *
     * @param string $path
     * @param string $localName [optional]
     * @param string $data      If provided, write to zip file instead of building from path
     *
     * @throws \Kisma\Core\Exceptions\FileSystemException
     * @return string
     */
    protected function _buildZipFile( $path, $localName = null, $data = null )
    {
        $_zipName = tempnam( sys_get_temp_dir(), sha1( uniqid() ) );

        if ( !$this->open( $_zipName, \ZipArchive::CREATE ) )
        {
            throw new FileSystemException( 'Unable to create temporary zip file.' );
        }

        //  Restore prior zipped content?
        if ( null !== $data )
        {
            if ( false === ( $_bytes = file_put_contents( $_zipName, $data ) ) )
            {
                throw new FileSystemException( 'Error creating temporary zip file for restoration.' );
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
}
