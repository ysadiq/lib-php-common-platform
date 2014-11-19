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
namespace DreamFactory\Platform\Services;

use Aws\Common\Enum\Region;
use Aws\Sns\SnsClient;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\User\Session;
use Kisma\Core\Utility\Option;

/**
 * AwsSnsSvc.php
 *
 * A service to handle Amazon Web Services SNS push notifications services
 * accessed through the REST API.
 */
class AwsSnsSvc extends BasePushSvc
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const DEFAULT_REGION = Region::US_WEST_1;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var SnsClient|null
     */
    protected $_dbConn = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new AwsSnsSvc
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct( $config )
    {
        parent::__construct( $config );

        $_credentials = Session::replaceLookup( Option::get( $config, 'credentials' ), true );

        // old way
        $_accessKey = Session::replaceLookup( Option::get( $_credentials, 'access_key' ), true );
        $_secretKey = Session::replaceLookup( Option::get( $_credentials, 'secret_key' ), true );
        if ( !empty( $_accessKey ) )
        {
            // old way, replace with 'key'
            $_credentials['key'] = $_accessKey;
        }

        if ( !empty( $_secretKey ) )
        {
            // old way, replace with 'key'
            $_credentials['secret'] = $_secretKey;
        }

        $_region = Option::get( $_credentials, 'region' );
        if ( empty( $_region ) )
        {
            // use a default region if not present
            $_credentials['region'] = static::DEFAULT_REGION;
        }

        try
        {
            $this->_dbConn = SnsClient::factory( $_credentials );
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Amazon SimpleDb Service Exception:\n{$_ex->getMessage()}" );
        }
    }

    /**
     * Object destructor
     */
    public function __destruct()
    {
        try
        {
            $this->_dbConn = null;
        }
        catch ( \Exception $_ex )
        {
            error_log( "Failed to disconnect from database.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * @throws \Exception
     */
    protected function checkConnection()
    {
        if ( empty( $this->_dbConn ) )
        {
            throw new InternalServerErrorException( 'Database connection has not been initialized.' );
        }
    }

    /**
     * {@InheritDoc}
     */
    public function correctTableName( &$name )
    {
        static $_existing = null;

        if ( !$_existing )
        {
            $_existing = $this->_getTopicsAsArray();
        }

        if ( empty( $name ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        if ( false === array_search( $name, $_existing ) )
        {
            throw new NotFoundException( "Table '$name' not found." );
        }

        return $name;
    }


    protected function _getTopicsAsArray()
    {
        $_out = array();
        $_token = null;
        do
        {
            $_result = $this->_dbConn->listTopics(
                array(
                    'NextToken'         => $_token
                )
            );
            $_topics = $_result['Topics'];
            $_token = $_result['NextToken'];

            if ( !empty( $_topics ) )
            {
                $_out = array_merge( $_out, $_topics );
            }
        }
        while ( $_token );

        return $_out;
    }

    // REST service implementation

    /**
     * {@inheritdoc}
     */
    protected function _listTopics( /** @noinspection PhpUnusedParameterInspection */ $refresh = true )
    {
        $_resources = array();
        $_result = $this->_getTopicsAsArray();
        foreach ( $_result as $_topic )
        {
            $_resources[] = array('name' => $_topic);
        }

        return $_resources;
    }

}
