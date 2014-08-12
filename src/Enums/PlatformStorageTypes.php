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
namespace DreamFactory\Platform\Enums;

use Kisma\Core\Enums\SeedEnum;
use Kisma\Core\Utility\Option;

/**
 * PlatformStorageTypes
 */
class PlatformStorageTypes extends SeedEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var int
     */
    const AWS_S3 = 0;
    /**
     * @var int
     */
    const AWS_DYNAMODB = 1;
    /**
     * @var int
     */
    const AWS_SIMPLEDB = 2;
    /**
     * @var int
     */
    const AZURE_BLOB = 3;
    /**
     * @var int
     */
    const AZURE_TABLES = 4;
    /**
     * @var int
     */
    const COUCHDB = 5;
    /**
     * @var int
     */
    const MONGODB = 6;
    /**
     * @var int
     */
    const OPENSTACK_OBJECT_STORAGE = 7;
    /**
     * @var int
     */
    const RACKSPACE_CLOUDFILES = 8;

    /**
     * @param int    $value        enumerated type value
     * @param string $service_name given name of the service, also returned as default
     *
     * @var array A map of classes for services
     */
    protected static $_classMap = array(
        self::AWS_S3                   => 'AwsS3Svc',
        self::AWS_DYNAMODB             => 'AwsDynamoDbSvc',
        self::AWS_SIMPLEDB             => 'AwsSimpleDbSvc',
        self::AZURE_BLOB               => 'WindowsAzureBlobSvc',
        self::AZURE_TABLES             => 'WindowsAzureTablesSvc',
        self::COUCHDB                  => 'CouchDbSvc',
        self::MONGODB                  => 'MongoDbSvc',
        self::OPENSTACK_OBJECT_STORAGE => 'OpenStackObjectStoreSvc',
        self::RACKSPACE_CLOUDFILES     => 'OpenStackObjectStoreSvc',
    );

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param int    $storage_type enumerated storage type value
     * @param string $service_name given name of the service, also returned as default
     *
     * @return string - associated file name of native service
     */
    public static function getFileName( $storage_type, $service_name )
    {
        $_serviceName = $service_name ? : null;

        if ( null !== ( $_fileName = Option::get( static::$_classMap, $storage_type ) ) )
        {
            return $_fileName;
        }

        return $_serviceName;
    }

}
