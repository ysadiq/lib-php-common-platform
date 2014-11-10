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

use DreamFactory\Library\Utility\FileSystem;

/**
 * DreamFactory Enterprise(tm) Hosted Storage Provider
 *
 * The layout of the hosted storage area is as follows:
 *
 * /root                                    <----- Mount point/absolute path of storage area
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
 *
 * @package DreamFactory\Platform\Utility
 */
class HostedStorage
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @type string Absolute path to shared-storage root
     */
    const DEFAULT_STORAGE_ROOT = '/data';
    /**
     * @type string Relative path under storage root
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
     * @type string Name of the plugins directory relative to storage base
     */
    const CONFIG_PATH = '/config';
    /**
     * @type string Name of the plugins directory relative to storage base
     */
    const SCRIPTS_PATH = '/scripts';
    /**
     * @type string Name of the plugins directory relative to storage base
     */
    const USER_SCRIPTS_PATH = '/scripts.user';
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

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type string The absolute storage root path
     */
    protected $_storageRoot = self::DEFAULT_STORAGE_ROOT;
    /**
     * @type string The relative storage base path
     */
    protected $_storageBase = self::STORAGE_BASE_PATH;
    /**
     * @type string The absolute generated storage path
     */
    protected $_storagePath;
    /**
     * @type string The deployment zone name/id
     */
    protected $_zone = false;
    /**
     * @type string The storage partition name/id
     */
    protected $_partition = null;
    /**
     * @type array The hosted storage path structure, relative to the storage root
     */
    protected $_skeleton;
    /**
     * @type string This instance's storage ID
     */
    protected $_storageId;
    /**
     * @type string The private storage path
     */
    protected $_privatePath;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    public function __construct( $instanceName, $storageRoot = null, $storageBase = null )
    {
        $this->_storageRoot = $storageRoot ?: static::DEFAULT_STORAGE_ROOT;
        $this->initialize( $instanceName, $storageRoot, $storageBase );
    }

    /** @inheritdoc */
    public function initialize( $instanceName, $storageRoot = self::DEFAULT_STORAGE_ROOT, $storageBase = self::STORAGE_BASE_PATH )
    {
        false !== stripos( $instanceName, Fabric::DSP_DEFAULT_SUBDOMAIN ) || $instanceName .= Fabric::DSP_DEFAULT_SUBDOMAIN;

        $this->_storageRoot = $storageRoot ?: static::DEFAULT_STORAGE_ROOT;
        $this->_storageBase = $storageBase ?: static::STORAGE_BASE_PATH;
        $this->_storageId = hash( static::DATA_STORAGE_HASH, $instanceName );
        $this->_storagePartition = $this->getStoragePartition();

        //  Find our zone
        if ( false === ( $this->_zone = $this->getStorageZone() ) )
        {
            //  Local installation storage root is the install path with no zone or partition
            $this->_storageRoot = static::_findBasePath();

            $this->_zone = null;
            $this->_partition = null;
        }

        $this->_buildStoragePath();
        $this->_createSkeleton();
    }

    /**
     * Returns the partition id for the given storage id
     *
     * @param string $storageId
     *
     * @return string
     */
    public function getStoragePartition( $storageId = null )
    {
        return substr( $storageId ?: $this->_storageId, 0, 2 );
    }

    /** @inheritdoc */
    public function getStorageZone( $zone = null )
    {
        //  If one was passed, use it
        if ( !empty( $zone ) )
        {
            return trim( $zone, DIRECTORY_SEPARATOR . ' ' );
        }

        //  Only hosted instances use a zone
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
        return trim( str_ireplace( array('https://', '.amazonaws.com'), null, $_url ), DIRECTORY_SEPARATOR . ' ' );
    }

    /**
     * Builds the storage and private paths from the parts
     */
    protected function _buildStoragePath()
    {
        $this->_storagePath = FileSystem::makePath( true, $this->_storageRoot, $this->_storageBase, $this->_zone, $this->_partition );
        $this->_privatePath = $this->_storagePath . static::STORAGE_PRIVATE_PATH;

        $this->_createSkeleton();
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
     * Give a storage path, set up the default sub paths...
     */
    protected function _createSkeleton()
    {
        $_skeleton = array(
            $this->_storagePath => array(
                static::APPLICATIONS_PATH,
                static::PLUGINS_PATH,
            ),
            $this->_privatePath => array(
                static::CONFIG_PATH,
                static::SCRIPTS_PATH,
                static::USER_SCRIPTS_PATH,
            )
        );

        $this->_skeleton = $_skeleton;
        $this->_ensureSkeleton();
    }

    /**
     * Ensures the directories in the skeleton are created and available. Only skeleton items that are arrays are processed.
     */
    protected function _ensureSkeleton()
    {
        foreach ( $this->_skeleton as $_basePath => $_paths )
        {
            if ( is_array( $_paths ) )
            {
                foreach ( $_paths as $_path )
                {
                    if ( !FileSystem::ensurePath( $_path ) )
                    {
                        throw new \RuntimeException( 'Error creating storage path "' . $_path . '"' );
                    }

                }
            }
        }
    }

    /**
     * @param string $path
     * @param mixed  $contents
     * @param bool   $jsonEncode
     * @param int    $jsonOptions
     *
     * @return int
     */
    public function putPublicFile( $path, $contents, $jsonEncode = true, $jsonOptions = 0 )
    {
        $_filePath = $this->getStoragePath() . DIRECTORY_SEPARATOR . ltrim( $path, DIRECTORY_SEPARATOR . ' ' );

        if ( $jsonEncode )
        {
            $contents = json_encode( $contents, $jsonOptions );
        }

        return file_put_contents( $_filePath, $contents );
    }

    /**
     * @return string
     */
    public function getStorageId()
    {
        return $this->_storageId;
    }

    /**
     * @return string
     */
    public function getStorageRoot()
    {
        return $this->_storageRoot;
    }

    /**
     * @return string
     */
    public function getStorageBase()
    {
        return $this->_storageBase;
    }

    /**
     * @return string
     */
    public function getZone()
    {
        return $this->_zone;
    }

    /**
     * @return string
     */
    public function getPartition()
    {
        return $this->_partition;
    }

    /**
     * @return array
     */
    public function getSkeleton()
    {
        return $this->_skeleton;
    }

    /**
     * @return string
     */
    public function getStoragePath()
    {
        return $this->_storagePath;
    }

    /**
     * @return string
     */
    public function getPluginsPath()
    {
        return $this->_storagePath . DIRECTORY_SEPARATOR . static::PLUGINS_PATH;
    }

    /**
     * @return string
     */
    public function getApplicationsPath()
    {
        return $this->_storagePath . DIRECTORY_SEPARATOR . static::APPLICATIONS_PATH;
    }

    /**
     * @return string
     */
    public function getPrivatePath()
    {
        return $this->_privatePath;
    }

    /**
     * @return string
     */
    public function getLocalConfigPath()
    {
        return $this->_privatePath;
    }

    /**
     * @return string
     */
    public function getScriptsPath()
    {
        return $this->_privatePath . DIRECTORY_SEPARATOR . static::SCRIPTS_PATH;
    }

    /**
     * @return string
     */
    public function getUserScriptsPath()
    {
        return $this->_privatePath . DIRECTORY_SEPARATOR . static::USER_SCRIPTS_PATH;
    }

}
