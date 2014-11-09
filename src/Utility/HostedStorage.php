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
    protected $_storageRoot;
    /**
     * @type string The relative storage base path
     */
    protected $_storagePath;
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
    protected $_storageTemplate;
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

    /** @inheritdoc */
    public function createStorageId( $instanceName )
    {
        false !== stripos( $instanceName, Fabric::DSP_DEFAULT_SUBDOMAIN ) || $instanceName .= Fabric::DSP_DEFAULT_SUBDOMAIN;

        $this->_storageId = hash( static::DATA_STORAGE_HASH, $instanceName );
        $this->_storagePartition = static::getStoragePartition( $this->_storageId );

        return $this->_storageId;
    }

    /**
     * Returns the partition id for the given storage id
     *
     * @param string $storageId
     *
     * @return string
     */
    public function getStoragePartition( $storageId )
    {
        return $this->_storagePartition = substr( $storageId, 0, 2 );
    }

    /** @inheritdoc */
    public function getZoneInfo( $zone = null )
    {
        if ( !empty( $zone ) )
        {
            return array($zone, DIRECTORY_SEPARATOR . $zone);
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

        return array($_zone, DIRECTORY_SEPARATOR . $_zone);
    }

    /** @inheritdoc */
    public function getStorageInfo( $hostname, $autoCreate = true )
    {
        $this->_storageId = static::getStorageId( $hostname );

        //  Look for EC2 instance signature
        if ( false === ( list( $this->_zone, $this->_zonePath ) = static::getZoneInfo( static::DEBUG_ZONE_ID ) ) )
        {
            //  Local installation so storage is /storage under installation
            $this->_zone = false;
            $this->_storageRoot = static::_findBasePath();
            $this->_storagePath = $this->_storageRoot . static::STORAGE_BASE_PATH;
        }
        else
        {
            $this->_storageRoot = $this->_storageRoot ?: static::DEFAULT_STORAGE_ROOT;
            $this->_storagePath = $this->_storageRoot . static::STORAGE_BASE_PATH . $this->_zonePath . DIRECTORY_SEPARATOR .
                substr( $this->_storageId, 0, 2 ) . DIRECTORY_SEPARATOR . $this->_storageId;
        }

        //  Subdivide by the first two digits of the storageId and the index if not local
        static::_ensureTemplate( $this->_storageRoot );

        return array($this->_storagePath, $this->_privatePath);
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
     * @param string $legacyStorageId The legacy storage ID
     * @param string $legacyPrivateId The legacy private ID
     * @param string $targetStorageId The mapping target storage ID
     */
    public function mapLegacyStorage( $legacyStorageId, $legacyPrivateId, $targetStorageId )
    {
        list( $_zone, $_zonePath, $_storagePath, $_privatePath ) =
            static::getStorageInfo( $targetStorageId );

    }

    /**
     * Give a storage path, set up the default sub paths...
     *
     * @param string $storageRoot
     * @param string $storagePath
     */
    public function setTemplateDefaults( $storageRoot = self::DEFAULT_STORAGE_ROOT, $storagePath = null )
    {
        if ( !empty( $this->_storageTemplate ) )
        {
            return;
        }

        $this->_storageRoot = $storageRoot;
        $this->_storagePath = $storagePath ?: $this->_storageRoot . static::STORAGE_BASE_PATH;
        $this->_privatePath = $this->_storagePath . static::STORAGE_PRIVATE_PATH;

        $_template = array(
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

        $this->_storageTemplate = $_template;
    }

    /**
     * @param string $path
     * @param bool   $throwExceptionOnFailure
     *
     * @return bool
     */
    protected function _ensurePath( $path, $throwExceptionOnFailure = true )
    {
        if ( !is_dir( $path ) && false === mkdir( $path, 0777, true ) )
        {
            if ( $throwExceptionOnFailure )
            {
                throw new \RuntimeException( 'Error creating storage area: ' . $path );
            }

            return false;
        }

        return true;
    }

    /**
     * Ensures the directory in the template are created and available. Only template items that are arrays are processed.
     *
     * @param string $basePath
     */
    protected function _ensureTemplate( $basePath )
    {
        foreach ( $this->_storageTemplate as $_basePath => $_paths )
        {
            foreach ( $_paths as $_path )
            {
                static::_ensurePath( $_basePath . $_path );
            }
        }
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
    public function getStoragePath()
    {
        return $this->_storagePath;
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
    public function getStorageTemplate()
    {
        return $this->_storageTemplate;
    }

    /**
     * @return string
     */
    public function getPrivatePath()
    {
        return $this->_privatePath;
    }
}
