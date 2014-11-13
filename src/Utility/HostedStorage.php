<?php
/**
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

use DreamFactory\Library\Utility\Exceptions\FileSystemException;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Platform\Enums\FabricStoragePaths;
use DreamFactory\Platform\Enums\LocalStorageTypes;
use DreamFactory\Platform\Interfaces\ClusterStorageProviderLike;
use DreamFactory\Platform\Services\SystemManager;
use Kisma\Core\Components\Flexistore;
use Kisma\Core\Utility\FileSystem;

ini_set( 'error_reporting', -1 );
ini_set( 'display_errors', 1 );

/**
 * DreamFactory Enterprise(tm) Hosted Storage Provider
 *
 * The layout of the hosted storage area is as follows:
 *
 * /mount_point                             <----- Mount point/absolute path of storage area
 *      /storage                            <----- Root directory of hosted storage
 *          /zone                           <----- The storage zones (ec2.us-east-1, ec2.us-west-1, local, etc.)
 *              /[00-ff]                    <----- The first two bytes of hashes within
 *                  /instance-hash          <----- The hash of the instance name
 *
 * Example paths:
 *
 * /data/storage/ec2.us-east-1/33/33f58e59068f021c975a1cac49c7b6818de9df5831d89677201b9c3bd98ee1ed/
 * /data/storage/ec2.us-east-1/33/33f58e59068f021c975a1cac49c7b6818de9df5831d89677201b9c3bd98ee1ed/.private
 * /data/storage/ec2.us-east-1/33/33f58e59068f021c975a1cac49c7b6818de9df5831d89677201b9c3bd98ee1ed/.private/scripts
 */
class HostedStorage extends FabricStoragePaths implements ClusterStorageProviderLike
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /** @inheritdoc */
    const DEBUG_ZONE_URL = 'https://ec2.us-east-1.amazonaws.com';
    /** @inheritdoc */
    const DEBUG_ZONE_NAME = 'ec2.us-east-1';

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type string This instance's storage ID
     */
    protected $_storageId;
    /**
     * @type string The absolute storage root path
     */
    protected $_mountPoint;
    /**
     * @type string The deployment zone name/id
     */
    protected $_zone;
    /**
     * @type string The storage partition name/id
     */
    protected $_partition;
    /**
     * @type array The hosted storage path structure, relative to the storage root
     */
    protected $_skeleton;
    /**
     * @type Flexistore
     */
    protected $_cache;
    /**
     * @type array Array of calculated paths
     */
    protected $_paths;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /** @inheritdoc */
    public function initialize( $hostname, $mountPoint = FabricStoragePaths::STORAGE_MOUNT_POINT )
    {
        false !== stripos( $hostname, Fabric::DSP_DEFAULT_SUBDOMAIN ) || $hostname .= Fabric::DSP_DEFAULT_SUBDOMAIN;

        $this->_mountPoint = $this->_mountPoint ?: $mountPoint;
        $this->_storageId = hash( static::DATA_STORAGE_HASH, $hostname );
        $this->_partition = substr( $this->_storageId, 0, 2 );

        //  Find the zone for this host
        if ( false === ( $this->_zone = $this->_findZone( static::DEBUG_ZONE_NAME ) ) )
        {
            //  Local installation
            $_basePath = $this->_findBasePath();

            return $this->_createStructure(
                $_basePath,
                $_basePath . DIRECTORY_SEPARATOR . static::STORAGE_PATH
            );
        }

        //  Hosted
        return $this->_createStructure(
            $this->_mountPoint,
            $this->_mountPoint . static::STORAGE_PATH . DIRECTORY_SEPARATOR .
            $this->_zone . DIRECTORY_SEPARATOR .
            $this->_partition . DIRECTORY_SEPARATOR .
            $this->_storageId
        );
    }

    /**
     * Returns the owner's storage space structure
     *
     * @return array An associative array of the paths that define the layout of the storage directory
     */
    public function getStorageStructure()
    {
        return $this->_skeleton;
    }

    /**
     * Constructs the virtual storage path
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getStoragePath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( $this->_paths[LocalStorageTypes::STORAGE_PATH], $append, $createIfMissing, $includesFile );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getPrivatePath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( $this->_paths[LocalStorageTypes::PRIVATE_STORAGE_PATH], $append, $createIfMissing, $includesFile );
    }

    /**
     * Returns the platform's local configuration path, not the platform's config path in the root
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getLocalConfigPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( $this->getPrivatePath( static::CONFIG_PATH ), $append, $createIfMissing, $includesFile );
    }

    /**
     * Returns the library configuration path, not the platform's config path in the root
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getLibraryConfigPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( SystemManager::getConfigPath(), $append, $createIfMissing, $includesFile );
    }

    /**
     * Returns the library template configuration path, not the platform's config path in the root
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getLibraryTemplatePath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( $this->getLibraryConfigPath( '/templates' ), $append, $createIfMissing, $includesFile );
    }

    /**
     * Returns the platform configuration path, in the root
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getPlatformConfigPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( $this->_findBasePath() . static::CONFIG_PATH, $append, $createIfMissing, $includesFile );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getSnapshotPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( $this->getPrivatePath( static::SNAPSHOT_PATH ), $append, $createIfMissing, $includesFile );
    }

    /**
     * Constructs the virtual swagger path
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getSwaggerPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( $this->getStoragePath( static::SWAGGER_PATH ), $append, $createIfMissing, $includesFile );
    }

    /**
     * Constructs the virtual plugins path
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getPluginsPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( $this->getStoragePath( static::PLUGINS_PATH ), $append, $createIfMissing, $includesFile );
    }

    /**
     * Constructs the virtual private path
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getApplicationsPath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( $this->getStoragePath( static::APPLICATIONS_PATH ), $append, $createIfMissing, $includesFile );
    }

    /**
     * @param string $legacyKey
     *
     * @return bool|string The zone/partition/id that make up the new public storage key
     */
    public function getStorageKey( $legacyKey = null )
    {
        static $_storageKey = null;

        if ( !$_storageKey )
        {
            if ( false === $this->_zone )
            {
                return false;
            }

            if ( empty( $this->_zone ) || empty( $this->_partition ) || empty( $this->_storageId ) )
            {
                return $_storageKey = $legacyKey;
            }
        }

        return
            $_storageKey =
                $_storageKey ?: $this->_zone . DIRECTORY_SEPARATOR . $this->_partition . DIRECTORY_SEPARATOR . $this->_storageId;
    }

    /**
     * @param string $legacyKey
     *
     * @return bool|string The zone/partition/id/tag that make up the new private storage key
     */
    public function getPrivateStorageKey( $legacyKey = null )
    {
        static $_privateKey = null;

        return
            $_privateKey =
                $_privateKey ?: $this->getStorageKey( $legacyKey ) . static::PRIVATE_STORAGE_PATH;
    }

    /** @inheritdoc */
    public function getStorageId()
    {
        return $this->_storageId;
    }

    /**
     * Find the zone of this cluster
     *
     * @param string $zone
     *
     * @return bool|mixed|null
     */
    protected function _findZone( $zone = null )
    {
        if ( !empty( $zone ) )
        {
            return $zone;
        }

        if ( !Fabric::fabricHosted() )
        {
            return false;
        }

        //  Try ec2...
        $_url = getenv( 'EC2_URL' ) ?: static::DEBUG_ZONE_URL;

        //  Not on EC2, we're something else
        if ( empty( $_url ) )
        {
            return false;
        }

        //  Get the EC2 zone of this instance from the url
        $_zone = str_ireplace( array('https://', '.amazonaws.com'), null, $_url );

        return $_zone;
    }

    /**
     * Give a storage path, set up the default sub paths...
     *
     * @param string $mountPoint
     * @param string $storagePath
     *
     * @return array
     */
    protected function _createStructure( $mountPoint = null, $storagePath = null )
    {
        $this->_mountPoint = $mountPoint ?: $this->_mountPoint;
        $_storagePath = $storagePath ?: $this->_mountPoint . static::STORAGE_PATH;
        $_privatePath = $_storagePath . static::PRIVATE_STORAGE_PATH;

        $this->_paths = array(
            LocalStorageTypes::STORAGE_ROOT         => $this->_mountPoint,
            LocalStorageTypes::STORAGE_PATH         => $_storagePath,
            LocalStorageTypes::PRIVATE_STORAGE_PATH => $_privatePath,
        );

        $this->_skeleton = array(
            $_storagePath => array(
                LocalStorageTypes::APPLICATIONS_PATH => static::APPLICATIONS_PATH,
                LocalStorageTypes::PLUGINS_PATH      => static::PLUGINS_PATH,
            ),
            $_privatePath => array(
                LocalStorageTypes::LOCAL_CONFIG_PATH => static::CONFIG_PATH,
                LocalStorageTypes::SCRIPTS_PATH      => static::SCRIPTS_PATH,
                LocalStorageTypes::USER_SCRIPTS_PATH => static::USER_SCRIPTS_PATH,
            )
        );

        //  Ensures the directories in the skeleton are created and available. Only template items that are arrays are processed.
        foreach ( $this->_skeleton as $_basePath => $_paths )
        {
            foreach ( $_paths as $_key => $_path )
            {
                if ( !FileSystem::ensurePath( $_basePath . $_path ) )
                {
                    throw new FileSystemException( 'Unable to create storage path "' . $_basePath . $_path . '"' );
                }

                $this->_paths[$_key] = $_path;
            }
        }

        return $this->_paths;
    }

    /**
     * Locate the base platform directory
     *
     * @param string $start
     *
     * @return string
     */
    protected function _findBasePath( $start = null )
    {
        $_path = $start ?: getcwd();

        while ( true )
        {
            if ( file_exists( $_path . DIRECTORY_SEPARATOR . 'composer.json' ) && is_dir( $_path . DIRECTORY_SEPARATOR . 'vendor' ) )
            {
                break;
            }

            $_path = dirname( $_path );

            if ( empty( $_path ) || $_path == DIRECTORY_SEPARATOR )
            {
                throw new \RuntimeException( 'Base platform installation path not found.' );
            }
        }

        return $_path;
    }

    /**
     * Constructs a virtual platform path
     *
     * @param string $base            The base path to start with
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @throws FileSystemException
     * @return string
     */
    protected function _buildPath( $base, $append = null, $createIfMissing = true, $includesFile = false )
    {
        $_appendage = ( $append ? DIRECTORY_SEPARATOR . ltrim( $append, DIRECTORY_SEPARATOR ) : null );

        //	Make a cache tag that includes the requested path...
        $_cacheKey = hash( static::DATA_STORAGE_HASH, $base . $_appendage );

        $_path = $this->_cache->get( $_cacheKey );

        if ( empty( $_path ) )
        {
            $_path = realpath( $base );
            $_checkPath = $_path . $_appendage;

            if ( $includesFile )
            {
                $_checkPath = dirname( $_checkPath );
            }

            if ( $createIfMissing && !is_dir( $_checkPath ) )
            {
                if ( false === @\mkdir( $_checkPath, 0777, true ) )
                {
                    throw new FileSystemException( 'File system error creating directory: ' . $_checkPath );
                }
            }

            $_path .= $_appendage;

            //	Store path for next time...
            $this->_cache->set( $_cacheKey, $_path );
        }

        return $_path;
    }

    /**
     * Create and load the cache...
     */
    public function __wakeup()
    {
        $this->_cache = Flexistore::createFileStore( null, null, static::CACHE_KEY );

        if ( false !== ( $_cached = $this->_cache->get( $this->_storageId ) ) )
        {
            if ( is_array( $_cached ) )
            {
                $this->_mountPoint = IfSet::get( $_cached, 'mount_point', static::STORAGE_MOUNT_POINT );
                $this->_zone = IfSet::get( $_cached, 'zone' );
                $this->_partition = IfSet::get( $_cached, 'partition' );
                $this->_paths = IfSet::get( $_cached, 'paths' );

                $this->_createStructure( $this->_mountPoint, $this->_paths[LocalStorageTypes::STORAGE_PATH] );
            }
        }
    }

    /** ctor */
    public function __construct()
    {
        $this->__wakeup();
    }

    /**
     * StayPuft
     */
    public function __destruct()
    {
        $this->__sleep();
    }

    /**
     * Save off to cache before snoozy
     */
    public function __sleep()
    {
        if ( $this->_cache )
        {
            $this->_cache->set(
                $this->_storageId,
                array(
                    'mount_point' => $this->_mountPoint,
                    'zone'        => $this->_zone,
                    'partition'   => $this->_partition,
                    'paths'       => $this->_paths,
                )
            );
        }
    }

}