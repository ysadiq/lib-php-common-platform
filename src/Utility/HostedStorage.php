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

use DreamFactory\Library\Utility\Exception\FileSystemException;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Platform\Enums\LocalStorageTypes;
use DreamFactory\Platform\Services\SystemManager;
use Kisma\Core\Components\Flexistore;
use Kisma\Core\Utility\FileSystem;

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
class HostedStorage
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @type string Absolute path where storage is mounted
     */
    const STORAGE_MOUNT_POINT = '/data';
    /**
     * @type string Relative path under storage mount
     */
    const STORAGE_BASE_PATH = '/storage';
    /**
     * @type string Relative path under storage base
     */
    const STORAGE_PRIVATE_PATH = '/.private';
    /**
     * @type string Name of the applications directory relative to storage base
     */
    const APPLICATIONS_PATH = '/applications';
    /**
     * @type string Name of the plugins directory relative to storage base
     */
    const PLUGINS_PATH = '/plugins';
    /**
     * @type string Name of the applications directory relative to storage base
     */
    const SWAGGER_PATH = '/swagger';
    /**
     * @type string Name of the plugins directory relative to storage and private base
     */
    const CONFIG_PATH = '/config';
    /**
     * @type string Name of the plugins directory relative to private base
     */
    const SCRIPTS_PATH = '/scripts';
    /**
     * @type string Name of the plugins directory relative to private base
     */
    const USER_SCRIPTS_PATH = '/scripts.user';
    /**
     * @type string Name of the snapshot storage directory relative to private base
     */
    const SNAPSHOT_PATH = '/snapshots';
    /**
     * @type string The hash algorithm to use for directory-creep
     */
    const DATA_STORAGE_HASH = 'sha256';
    /**
     * @type string Zone url to use when testing. Set to null in production
     */
    const DEBUG_ZONE_URL = 'https://ec2.us-east-1.amazonaws.com';
//    const DEBUG_ZONE_URL = null;
    /**
     * @type string Zone name to use when testing. Set to null in production
     */
    const DEBUG_ZONE_NAME = 'ec2.us-east-1';
//    const DEBUG_ZONE_NAME = null;
    /**
     * @type string Our cache key
     */
    const CACHE_KEY = 'platform.hosted_storage';

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
     * @type string The relative storage base path
     */
    protected $_storagePath;
    /**
     * @type string The private storage path
     */
    protected $_privatePath;
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

    /**
     * @param string $instanceName The name of the hosted instance
     * @param string $mountPoint   The mount point of hosted storage
     */
    public function __construct( $instanceName, $mountPoint = self::STORAGE_MOUNT_POINT )
    {
        $this->_mountPoint = $mountPoint;
        $this->getPaths( $instanceName );

        $this->__wakeup();
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
                $this->_storagePath = IfSet::get( $_cached, 'storage_path' );
                $this->_privatePath = IfSet::get( $_cached, 'private_path' );

                $this->createSkeleton( $this->_mountPoint, $this->_storagePath );
            }
        }
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
                    'mount_point'  => $this->_mountPoint,
                    'zone'         => $this->_zone,
                    'partition'    => $this->_partition,
                    'storage_path' => $this->_storagePath,
                    'private_path' => $this->_privatePath,
                )
            );
        }
    }

    /** @inheritdoc */
    public function getStorageId( $instanceName )
    {
        false !== stripos( $instanceName, Fabric::DSP_DEFAULT_SUBDOMAIN ) || $instanceName .= Fabric::DSP_DEFAULT_SUBDOMAIN;

        $this->_storageId = hash( static::DATA_STORAGE_HASH, $instanceName );
        $this->_partition = $this->getPartition( $this->_storageId );

        return $this->_storageId;
    }

    /**
     * Returns the partition id for the given storage id
     *
     * @param string $storageId
     *
     * @return string
     */
    public function getPartition( $storageId )
    {
        return $this->_partition = substr( $storageId, 0, 2 );
    }

    /** @inheritdoc */
    public function getZone( $zone = null )
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

    /** @inheritdoc */
    public function getPaths( $hostname )
    {
        $this->_storageId = $this->getStorageId( $hostname );

        //  Find the zone for this host
        if ( false === ( $this->_zone = $this->getZone( static::DEBUG_ZONE_NAME ) ) )
        {
            //  Local installation
            $this->createSkeleton( $this->_findBasePath() );
        }
        else
        {
            //  Hosted
            $this->createSkeleton(
                $this->_mountPoint,
                $this->_mountPoint . static::STORAGE_BASE_PATH . DIRECTORY_SEPARATOR .
                $this->_zone . DIRECTORY_SEPARATOR .
                substr( $this->_storageId, 0, 2 ) . DIRECTORY_SEPARATOR .
                $this->_storageId
            );
        }

        return array($this->_storagePath, $this->_privatePath);
    }

    /**
     * Give a storage path, set up the default sub paths...
     *
     * @param string $mountPoint
     * @param string $storagePath
     */
    public function createSkeleton( $mountPoint = self::STORAGE_MOUNT_POINT, $storagePath = null )
    {
        if ( !empty( $this->_skeleton ) )
        {
            return;
        }

        $this->_mountPoint = $mountPoint;
        $this->_storagePath = $storagePath ?: $this->_mountPoint . static::STORAGE_BASE_PATH;
        $this->_privatePath = $this->_storagePath . static::STORAGE_PRIVATE_PATH;

        $_template = array(
            $this->_storagePath => array(
                LocalStorageTypes::APPLICATIONS_PATH => static::APPLICATIONS_PATH,
                LocalStorageTypes::PLUGINS_PATH      => static::PLUGINS_PATH,
            ),
            $this->_privatePath => array(
                LocalStorageTypes::LOCAL_CONFIG_PATH => static::CONFIG_PATH,
                LocalStorageTypes::SCRIPTS_PATH      => static::SCRIPTS_PATH,
                LocalStorageTypes::USER_SCRIPTS_PATH => static::USER_SCRIPTS_PATH,
            )
        );

        $this->_skeleton = $_template;
        $this->_paths = array();

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
     * @return string
     */
    public function getMountPoint()
    {
        return $this->_mountPoint;
    }

    /**
     * @return array
     */
    public function getSkeleton()
    {
        return $this->_skeleton;
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
     * Constructs the virtual storage path
     *
     * @param string $append          What to append to the base
     * @param bool   $createIfMissing If true and final directory does not exist, it is created.
     * @param bool   $includesFile    If true, the $base includes a file and is not just a directory
     *
     * @return string
     */
    public function getStorageBasePath( $append = null, $createIfMissing = true, $includesFile = false )
    {
        return $this->_buildPath( $this->_storagePath, $append, $createIfMissing, $includesFile );
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
        return $this->_buildPath( $this->_storagePath, $append, $createIfMissing, $includesFile );
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
        return $this->_buildPath( $this->_privatePath, $append, $createIfMissing, $includesFile );
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
        if ( false === $this->_zone )
        {
            return false;
        }

        if ( empty( $this->_zone ) || empty( $this->_partition ) || empty( $this->_storageId ) )
        {
            return $legacyKey;
        }

        return $this->_zone . DIRECTORY_SEPARATOR . $this->_partition . DIRECTORY_SEPARATOR . $this->_storageId;
    }

    /**
     * @param string $legacyKey
     *
     * @return bool|string The zone/partition/id/tag that make up the new private storage key
     */
    public function getPrivateStorageKey( $legacyKey = null )
    {
        return $this->getStorageKey( $legacyKey ) . static::STORAGE_PRIVATE_PATH;
    }
}
