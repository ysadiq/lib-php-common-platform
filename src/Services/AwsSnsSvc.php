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
use DreamFactory\Library\Utility\IfSet;
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

    /**
     * AWS Region when not defined in configuration
     */
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
            if ( null === $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException( "Amazon SNS Exception:\n{$_ex->getMessage()}", $_ex->getCode() );
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
    public function correctTopicName( &$name )
    {
        static $_existing = null;

        if ( !$_existing )
        {
            $_existing = $this->_getTopicsAsArray();
        }

        if ( empty( $name ) )
        {
            throw new BadRequestException( 'Topic name can not be empty.' );
        }

        if ( false === array_search( $name, $_existing ) )
        {
            throw new NotFoundException( "Topic '$name' not found." );
        }

        return $name;
    }

    /**
     * @return array
     */
    protected function _getTopicsAsArray()
    {
        $_out = array();
        $_token = null;
        try
        {
            do
            {
                $_result = $this->_dbConn->listTopics(
                    array(
                        'NextToken' => $_token
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
        }
        catch ( \Exception $_ex )
        {
            if ( null === $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException( "Failed to retrieve topics.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return $_out;
    }

    // REST service implementation

    /**
     * {@inheritdoc}
     */
    protected function _listTopics( /** @noinspection PhpUnusedParameterInspection */
        $refresh = true )
    {
        $_resources = array();
        $_result = $this->_getTopicsAsArray();
        foreach ( $_result as $_topic )
        {
            $_name = IfSet::get( $_topic, 'TopicArn' );
            $_topic['name'] = $_name;
            $_resources[] = $_topic;
        }

        return $_resources;
    }

    /**
     * @param $resource
     *
     * @return array
     * @throws InternalServerErrorException
     * @throws NotFoundException
     */
    public function retrieveTopic( $resource )
    {
        $_request = array('TopicArn' => $resource);

        try
        {
            if ( null !== $_result = $this->_dbConn->getTopicAttributes( $_request ) )
            {
                return $_result->toArray();
            }
        }
        catch ( \Exception $_ex )
        {
            if ( null === $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException( "Failed to retrieve properties for '$resource'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function pushMessage( $resource, $request )
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $_msgFormat = <<<JSON
{
    "default": "ENTER YOUR MESSAGE",
    "email": "ENTER YOUR MESSAGE",
    "sqs": "ENTER YOUR MESSAGE",
    "http": "ENTER YOUR MESSAGE",
    "https": "ENTER YOUR MESSAGE",
    "sms": "ENTER YOUR MESSAGE",
    "APNS": "{\"aps\":{\"alert\": \"ENTER YOUR MESSAGE\",\"sound\":\"default\"} }",
    "GCM": "{ \"data\": { \"message\": \"ENTER YOUR MESSAGE\" } }",
    "ADM": "{ \"data\": { \"message\": \"ENTER YOUR MESSAGE\" } }",
    "BAIDU": "{\"title\":\"ENTER YOUR TITLE\",\"description\":\"ENTER YOUR DESCRIPTION\"}",
    "MPNS" : "<?xml version=\"1.0\" encoding=\"utf-8\"?><wp:Notification xmlns:wp=\"WPNotification\"><wp:Tile><wp:Count>ENTER COUNT</wp:Count><wp:Title>ENTER YOUR MESSAGE</wp:Title></wp:Tile></wp:Notification>",
    "WNS" : "<badge version=\"1\" value=\"23\"/>"
}
JSON;

        $_data = array('TopicArn' => $resource);
        if ( is_array( $request ) )
        {
            if ( null !== $_message = Ifset::get( $request, 'Message' ) )
            {
                $_data = array_merge( $_data, $request );
                if ( is_array( $_message ) && !Ifset::has( $request, 'MessageStructure' ) )
                {
                    $_data['MessageStructure'] = 'json';
                }
            }
            else
            {
                //  This is the message
                $_data['Message'] = $request;
                $_data['MessageStructure'] = 'json';
            }
        }
        else
        {
            $_data['Message'] = $request;
        }

        try
        {
            if ( null !== $_result = $this->_dbConn->publish( $_data ) )
            {
                return $_result->toArray();
            }
        }
        catch ( \Exception $_ex )
        {
            if ( null !== $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException( "Failed to push message to '$resource'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    /**
     * Translates AWS SNS Exceptions to DF Exceptions
     * If not an AWS SNS Exception, then null is returned.
     *
     * @param \Exception  $exception
     * @param string|null $add_msg
     *
     * @return BadRequestException|InternalServerErrorException|NotFoundException|null
     */
    static public function translateException( \Exception $exception, $add_msg = null )
    {
        $_msg = strval( $add_msg ) . $exception->getMessage();
        switch ( get_class( $exception ) )
        {
            case 'Aws\Sns\Exception\AuthorizationErrorException':
            case 'Aws\Sns\Exception\EndpointDisabledException':
            case 'Aws\Sns\Exception\InvalidParameterException':
            case 'Aws\Sns\Exception\PlatformApplicationDisabledException':
            case 'Aws\Sns\Exception\SubscriptionLimitExceededException':
            case 'Aws\Sns\Exception\TopicLimitExceededException':
                return new BadRequestException( $_msg, $exception->getCode() );
            case 'Aws\Sns\Exception\NotFoundException':
                return new NotFoundException( $_msg, $exception->getCode() );
            case 'Aws\Sns\Exception\SnsException':
            case 'Aws\Sns\Exception\InternalErrorException':
                return new InternalServerErrorException( $_msg, $exception->getCode() );
            default:
                return null;
        }
    }
}
