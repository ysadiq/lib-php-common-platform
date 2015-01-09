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

use Aws\Sns\SnsClient;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Utility\AwsSvcUtilities;
use Kisma\Core\Utility\Option;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Interfaces\ServiceOnlyResourceLike;
use DreamFactory\Platform\Utility\RestData;

/**
 * AwsSnsSvc.php
 *
 * A service to handle Amazon Web Services SNS push notifications services
 * accessed through the REST API.
 */
class AwsSnsSvc extends BasePlatformRestService implements ServiceOnlyResourceLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Service name
     */
    const CLIENT_NAME = 'Sns';
    /**
     * Resource tag for dealing with topics
     */
    const TOPIC_RESOURCE = 'topic';
    /**
     * Resource tag for dealing with subscription
     */
    const SUBSCRIPTION_RESOURCE = 'subscription';
    /**
     * Resource tag for dealing with subscription
     */
    const APPLICATION_RESOURCE = 'application';
    /**
     * Resource tag for dealing with subscription
     */
    const ENDPOINT_RESOURCE = 'endpoint';
    /**
     * Resource tag for dealing with subscription
     */
    const ARN_PREFIX = 'arn:aws:sns:';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var SnsClient|null
     */
    protected $_conn = null;
    /**
     * @var string|null
     */
    protected $_resourceId = null;
    /**
     * @var string|null
     */
    protected $_relatedResource = null;
    /**
     * @var string|null
     */
    protected $_relatedResourceId = null;
    /**
     * @var string|null
     */
    protected $_region = null;

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
        if ( null === Option::get( $settings, 'verb_aliases' ) )
        {
            //	Default verb aliases
            $settings['verb_aliases'] = array(
                static::MERGE => static::PATCH,
            );
        }

        parent::__construct( $config );

        $_credentials = Option::clean( Option::get( $config, 'credentials' ) );
        AwsSvcUtilities::updateCredentials( $_credentials, true );

        $this->_conn = AwsSvcUtilities::createClient( $_credentials, static::CLIENT_NAME );
        $this->_region = Option::get( $_credentials, 'region' );
    }

    /**
     * Object destructor
     */
    public function __destruct()
    {
        try
        {
            $this->_conn = null;
        }
        catch ( \Exception $_ex )
        {
            error_log( "Failed to disconnect from service.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * @throws \Exception
     */
    protected function checkConnection()
    {
        if ( empty( $this->_conn ) )
        {
            throw new InternalServerErrorException( 'Service connection has not been initialized.' );
        }
    }

    protected function addArnPrefix( $name )
    {
        if ( 0 !== substr_compare( $name, static::ARN_PREFIX, 0, strlen( static::ARN_PREFIX ) ) )
        {
            $name = static::ARN_PREFIX . $this->_region . ':' . $name;
        }

        return $name;
    }

    protected function stripArnPrefix( $name )
    {
        if ( 0 === substr_compare( $name, static::ARN_PREFIX, 0, strlen( static::ARN_PREFIX ) ) )
        {
            $name = substr( $name, strlen( static::ARN_PREFIX . $this->_region . ':' ) );
        }

        return $name;
    }

    /**
     * {@InheritDoc}
     */
    protected function _detectResourceMembers( $resourcePath = null )
    {
        parent::_detectResourceMembers( $resourcePath );

        $this->_resourceId = Option::get( $this->_resourceArray, 1 );

        $_pos = 2;
        $_more = Option::get( $this->_resourceArray, $_pos );

        if ( !empty( $_more ) && ( static::APPLICATION_RESOURCE == $this->_resource ) && ( static::ENDPOINT_RESOURCE !== $_more ) )
        {
            do
            {
                $this->_resourceId .= '/' . $_more;
                $_pos++;
                $_more = Option::get( $this->_resourceArray, $_pos );
            }
            while ( !empty( $_more ) && ( static::ENDPOINT_RESOURCE !== $_more ) );
        }

        $this->_relatedResource = Option::get( $this->_resourceArray, $_pos++ );
        $this->_relatedResourceId = Option::get( $this->_resourceArray, $_pos++ );
        $_more = Option::get( $this->_resourceArray, $_pos );

        if ( !empty( $_more ) && ( static::ENDPOINT_RESOURCE == $this->_relatedResource ) )
        {
            do
            {
                $this->_relatedResourceId .= '/' . $_more;
                $_pos++;
                $_more = Option::get( $this->_resourceArray, $_pos );
            }
            while ( !empty( $_more ) );
        }
    }

    /**
     * {@InheritDoc}
     */
    protected function _detectRequestMembers()
    {
        // override - don't call parent class here
        $this->_requestPayload = Option::clean( RestData::getPostedData( true, true ) );
        // MERGE URL parameters with posted data, posted data takes precedence
//        $this->_requestPayload = array_merge( $_REQUEST, $this->_requestPayload );
    }

    /**
     */
    protected function validateResourceAccess()
    {
        $fullResourcePath = null;
        if ( !empty( $this->_resource ) )
        {
            switch ( $this->_resource )
            {
                case static::TOPIC_RESOURCE:
                    $fullResourcePath = rtrim( $this->_resource, '/' ) . '/';
                    if ( !empty( $this->_resourceId ) )
                    {
                        $fullResourcePath .= $this->correctTopicName( $this->_resourceId );
                        if ( static::SUBSCRIPTION_RESOURCE == $this->_relatedResource )
                        {
                            $relatedResourcePath = rtrim( $this->_relatedResource, '/' ) . '/';
                            if ( !empty( $this->_relatedResourceId ) )
                            {
                                $relatedResourcePath .= $this->correctSubscriptionName( $this->_relatedResourceId );
                            }
                            $this->checkPermission( $this->_action, $relatedResourcePath );
                        }
                    }
                    break;
                case static::SUBSCRIPTION_RESOURCE:
                    $fullResourcePath = rtrim( $this->_resource, '/' ) . '/';
                    if ( !empty( $this->_resourceId ) )
                    {
                        $fullResourcePath .= $this->correctSubscriptionName( $this->_resourceId );
                    }
                    break;
                case static::APPLICATION_RESOURCE:
                    $fullResourcePath = rtrim( $this->_resource, '/' ) . '/';
                    if ( !empty( $this->_resourceId ) )
                    {
                        $fullResourcePath .= $this->correctApplicationName( $this->_resourceId );
                        if ( static::ENDPOINT_RESOURCE == $this->_relatedResource )
                        {
                            $relatedResourcePath = rtrim( $this->_relatedResource, '/' ) . '/';
                            if ( !empty( $this->_relatedResourceId ) )
                            {
                                $relatedResourcePath .= $this->correctEndpointName( $this->_relatedResourceId );
                            }
                            $this->checkPermission( $this->_action, $relatedResourcePath );
                        }
                    }
                    break;
                default:
                    break;
            }
        }

        $this->checkPermission( $this->_action, $fullResourcePath );
    }

    /**
     * @return mixed
     */
    protected function _preProcess()
    {
        //	Do validation here
        $this->validateResourceAccess();

        parent::_preProcess();
    }

    /**
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws \Exception
     * @return array|bool
     */
    protected function _handleResource()
    {
        if ( empty( $this->_resource ) )
        {
            return parent::_handleResource();
        }

        switch ( $this->_resource )
        {
            case static::TOPIC_RESOURCE:
                if ( static::SUBSCRIPTION_RESOURCE == $this->_relatedResource )
                {
                    return $this->_handleSubscriptions();
                }

                return $this->_handleTopics();

            case static::SUBSCRIPTION_RESOURCE:
                return $this->_handleSubscriptions();

            case static::APPLICATION_RESOURCE:
                if ( static::ENDPOINT_RESOURCE == $this->_relatedResource )
                {
                    return $this->_handleEndpoints();
                }

                return $this->_handleApplications();

            default:
                throw new BadRequestException( "Invalid resource '{$this->_resource}'." );
                break;
        }
    }

    /**
     * @return array
     */
    protected function _handleGet()
    {
        $_result = array('resource' => $this->retrieveResources( $_REQUEST ));

        $this->_triggerActionEvent( $_result );

        return $_result;
    }

    /**
     * @param array|null $options
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public function retrieveResources( $options = null )
    {
        $_refresh = Option::getBool( $options, 'refresh' );
        $_namesOnly = Option::getBool( $options, 'names_only' );
        $_asComponents = Option::getBool( $options, 'as_access_components' );
        $_resources = array();

        if ( $_asComponents )
        {
            $_resources = array('', '*');
        }
        try
        {
            $_access = $this->getPermissions( static::TOPIC_RESOURCE );
            if ( !empty( $_access ) )
            {
                if ( $_namesOnly )
                {
                    $_resources[] = static::TOPIC_RESOURCE;
                }
                elseif ( $_asComponents )
                {
                    $_resources[] = static::TOPIC_RESOURCE . '/';
                    $_resources[] = static::TOPIC_RESOURCE . '/*';
                }
                else
                {
                    $_resources[] = array('name' => static::TOPIC_RESOURCE, 'access' => $_access);
                }
            }
            if ( $_asComponents )
            {
                $_result = static::retrieveTopics( $_refresh );
                foreach ( $_result as $_topic )
                {
                    if ( null != $_name = Option::get( $_topic, 'Name' ) )
                    {
                        $_name = static::TOPIC_RESOURCE . '/' . $_name;
                        $_access = $this->getPermissions( $_name );
                        if ( !empty( $_access ) )
                        {
                            if ( $_namesOnly || $_asComponents )
                            {
                                $_resources[] = $_name;
                            }
                            else
                            {
                                $_topic['name'] = $_name;
                                $_topic['access'] = $_access;
                                $_resources[] = $_topic;
                            }
                        }
                    }
                }
            }

            $_access = $this->getPermissions( static::SUBSCRIPTION_RESOURCE );
            if ( !empty( $_access ) )
            {
                if ( $_namesOnly )
                {
                    $_resources[] = static::SUBSCRIPTION_RESOURCE;
                }
                elseif ( $_asComponents )
                {
                    $_resources[] = static::SUBSCRIPTION_RESOURCE . '/';
                    $_resources[] = static::SUBSCRIPTION_RESOURCE . '/*';
                }
                else
                {
                    $_resources[] = array('name' => static::SUBSCRIPTION_RESOURCE, 'access' => $_access);
                }
            }
            if ( $_asComponents )
            {
                $_result = static::retrieveSubscriptions( $_refresh );
                foreach ( $_result as $_sub )
                {
                    if ( null != $_name = Option::get( $_sub, 'Name' ) )
                    {
                        $_name = static::SUBSCRIPTION_RESOURCE . '/' . $_name;
                        $_access = $this->getPermissions( $_name );
                        if ( !empty( $_access ) )
                        {
                            if ( $_namesOnly || $_asComponents )
                            {
                                $_resources[] = $_name;
                            }
                            else
                            {
                                $_sub['name'] = $_name;
                                $_sub['access'] = $_access;
                                $_resources[] = $_sub;
                            }
                        }
                    }
                }
            }

            $_access = $this->getPermissions( static::APPLICATION_RESOURCE );
            if ( !empty( $_access ) )
            {
                if ( $_namesOnly )
                {
                    $_resources[] = static::APPLICATION_RESOURCE;
                }
                elseif ( $_asComponents )
                {
                    $_resources[] = static::APPLICATION_RESOURCE . '/';
                    $_resources[] = static::APPLICATION_RESOURCE . '/*';
                }
                else
                {
                    $_resources[] = array('name' => static::APPLICATION_RESOURCE, 'access' => $_access);
                }
            }
            if ( $_asComponents )
            {
                $_result = static::retrieveApplications( $_refresh );
                foreach ( $_result as $_app )
                {
                    if ( null != $_name = Option::get( $_app, 'Name' ) )
                    {
                        $_name = static::APPLICATION_RESOURCE . '/' . $_name;
                        $_access = $this->getPermissions( $_name );
                        if ( !empty( $_access ) )
                        {
                            if ( $_namesOnly || $_asComponents )
                            {
                                $_resources[] = $_name;
                            }
                            else
                            {
                                $_app['name'] = $_name;
                                $_app['access'] = $_access;
                                $_resources[] = $_app;
                            }
                        }
                    }
                }

                $_access = $this->getPermissions( static::ENDPOINT_RESOURCE );
                if ( !empty( $_access ) )
                {
                    $_resources[] = static::ENDPOINT_RESOURCE . '/';
                    $_resources[] = static::ENDPOINT_RESOURCE . '/*';
                }
            }

            return $_resources;
        }
        catch ( RestException $_ex )
        {
            throw $_ex;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to list resources for this service.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handlePost()
    {
        if ( empty( $this->_requestPayload ) )
        {
            throw new BadRequestException( 'No post detected in request.' );
        }

        $this->_triggerActionEvent( $this->_response );

        $_result = $this->pushMessage( $this->_resource, $this->_requestPayload );

        return $_result;
    }

    /**
     * {@InheritDoc}
     */
    public function correctTopicName( &$name )
    {
        if ( empty( $name ) )
        {
            throw new BadRequestException( 'Topic name can not be empty.' );
        }

        return $name;
    }

    /**
     * {@InheritDoc}
     */
    public function correctSubscriptionName( &$name )
    {
        if ( empty( $name ) )
        {
            throw new BadRequestException( 'Subscription name can not be empty.' );
        }

        return $name;
    }

    /**
     * {@InheritDoc}
     */
    public function correctApplicationName( &$name )
    {
        if ( empty( $name ) )
        {
            throw new BadRequestException( 'Application name can not be empty.' );
        }

        return $name;
    }

    /**
     * {@InheritDoc}
     */
    public function correctEndpointName( &$name )
    {
        if ( empty( $name ) )
        {
            throw new BadRequestException( 'Endpoint name can not be empty.' );
        }

        return $name;
    }

    /**
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     * @throws null
     */
    protected function _getTopicsAsArray()
    {
        $_out = array();
        $_token = null;
        try
        {
            do
            {
                $_result = $this->_conn->listTopics(
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

    /**
     * @param bool $refresh
     *
     * @return array
     */
    protected function retrieveTopics( /** @noinspection PhpUnusedParameterInspection */
        $refresh = true )
    {
        $_resources = array();
        $_result = $this->_getTopicsAsArray();
        foreach ( $_result as $_topic )
        {
            $_topic['Name'] = $this->stripArnPrefix( IfSet::get( $_topic, 'TopicArn' ) );
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
        $_request = array('TopicArn' => $this->addArnPrefix( $resource ));

        try
        {
            if ( null !== $_result = $this->_conn->getTopicAttributes( $_request ) )
            {
                return Option::get( $_result->toArray(), 'Attributes' );
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
     * @param string       $resource
     * @param string|array $request
     *
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     * @throws null
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

        $_data = array('TopicArn' => $this->addArnPrefix( $resource ));
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
            if ( null !== $_result = $this->_conn->publish( $_data ) )
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
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleTopics()
    {
        $_result = false;

        switch ( $this->_action )
        {
            case static::GET:
                if ( empty( $this->_resourceId ) )
                {
                    $_result = array('resource' => $this->retrieveTopics());
                }
                else
                {
                    $_result = $this->retrieveTopic( $this->_resourceId );
                }
                break;

            case static::POST:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in topic post request.' );
                }

                if ( empty( $this->_resourceId ) )
                {
                    $_result = $this->createTopic( $this->_requestPayload );
                }
                else
                {
                    $_result = $this->pushMessage( $this->_resourceId, $this->_requestPayload );
                }
                break;

            case static::PUT:
            case static::PATCH:
            case static::MERGE:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in topic update request.' );
                }

                if ( !empty( $this->_resourceId ) )
                {
                    $this->_requestPayload['Name'] = $this->_resourceId;
                }
                $_result = $this->updateTopic( $this->_requestPayload );
                break;

            case static::DELETE:
                if ( empty( $this->_resourceId ) )
                {
                    if ( empty( $this->_requestPayload ) )
                    {
                        throw new BadRequestException( 'No data in topic delete request.' );
                    }

                    $this->deleteTopic( $this->_requestPayload );
                }
                else
                {
                    $this->deleteTopic( $this->_resourceId );
                }
                $_result = array('success' => true);
                break;
        }

        return $_result;
    }

    public function createTopic( $request )
    {
        $_data = array();
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'Name' );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Create Topic request contains no 'Name' field." );
            }

            $_data['Name'] = $_name;
        }
        else
        {
            $_data['Name'] = $request;
        }

        try
        {
            if ( null !== $_result = $this->_conn->createTopic( $_data ) )
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

            throw new InternalServerErrorException( "Failed to create topic '{$_data['Name']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function updateTopic( $request )
    {
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'Name', Ifset::get( $request, 'TopicArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Update topic request contains no 'Name' field." );
            }

            $request['TopicArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            throw new BadRequestException( "Update topic request contains no fields." );
        }

        try
        {
            if ( null !== $_result = $this->_conn->setTopicAttributes( $request ) )
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

            throw new InternalServerErrorException( "Failed to delete topic '{$request['TopicArn']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function deleteTopic( $request )
    {
        $_data = array();
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'Name', Ifset::get( $request, 'TopicArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Delete Topic request contains no 'Name' field." );
            }

            $_data['TopicArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            $_data['TopicArn'] = $this->addArnPrefix( $request );
        }

        try
        {
            if ( null !== $_result = $this->_conn->deleteTopic( $_data ) )
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

            throw new InternalServerErrorException( "Failed to delete topic '{$_data['TopicArn']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleSubscriptions()
    {
        $_result = false;

        switch ( $this->_action )
        {
            case static::GET:
                if ( empty( $this->_resourceId ) )
                {
                    $_result = array('resource' => $this->retrieveSubscriptions());
                }
                else
                {
                    $_result = $this->retrieveSubscription( $this->_resourceId );
                }
                break;

            case static::POST:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in subscription post request.' );
                }

                if ( empty( $this->_resourceId ) )
                {
                    $_result = $this->createSubscription( $this->_requestPayload );
                }
                else
                {
                    $_result = false;  //  Not allowed
                }
                break;

            case static::PUT:
            case static::PATCH:
            case static::MERGE:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in subscription update request.' );
                }

                if ( !empty( $this->_resourceId ) )
                {
                    $this->_requestPayload['Name'] = $this->_resourceId;
                }
                $_result = $this->updateSubscription( $this->_requestPayload );
                break;

            case static::DELETE:
                if ( empty( $this->_resourceId ) )
                {
                    if ( empty( $this->_requestPayload ) )
                    {
                        throw new BadRequestException( 'No data in subscription delete request.' );
                    }

                    $this->deleteSubscription( $this->_requestPayload );
                }
                else
                {
                    $this->deleteSubscription( $this->_resourceId );
                }
                $_result = array('success' => true);
                break;
        }

        return $_result;
    }

    /**
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     * @throws null
     */
    protected function _getSubscriptionsAsArray()
    {
        $_out = array();
        $_token = null;
        try
        {
            do
            {
                $_result = $this->_conn->listSubscriptions(
                    array(
                        'NextToken' => $_token
                    )
                );
                $_topics = $_result['Subscriptions'];
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

            throw new InternalServerErrorException( "Failed to retrieve subscriptions.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return $_out;
    }

    /**
     * @param bool $refresh
     *
     * @return array
     */
    protected function retrieveSubscriptions( /** @noinspection PhpUnusedParameterInspection */
        $refresh = true )
    {
        $_resources = array();
        $_result = $this->_getSubscriptionsAsArray();
        foreach ( $_result as $_sub )
        {
            $_sub['Name'] = $this->stripArnPrefix( IfSet::get( $_sub, 'SubscriptionArn' ) );
            $_resources[] = $_sub;
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
    public function retrieveSubscription( $resource )
    {
        $_request = array('SubscriptionArn' => $this->addArnPrefix( $resource ));

        try
        {
            if ( null !== $_result = $this->_conn->getSubscriptionAttributes( $_request ) )
            {
                return Option::get( $_result->toArray(), 'Attributes' );
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

    public function createSubscription( $request )
    {
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'TopicArn' );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Create Subscription request contains no 'TopicArn' field." );
            }
        }
        else
        {
            throw new BadRequestException( "Create Subscription request contains no fields." );
        }

        try
        {
            if ( null !== $_result = $this->_conn->subscribe( $request ) )
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

            throw new InternalServerErrorException( "Failed to create subscription to  '{$request['TopicArn']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function updateSubscription( $request )
    {
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'Name', Ifset::get( $request, 'SubscriptionArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Update subscription request contains no 'Name' field." );
            }

            $request['SubscriptionArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            throw new BadRequestException( "Update subscription request contains no fields." );
        }

        try
        {
            if ( null !== $_result = $this->_conn->setTopicAttributes( $request ) )
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

            throw new InternalServerErrorException( "Failed to delete subscription '{$request['SubscriptionArn']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function deleteSubscription( $request )
    {
        $_data = array();
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'Name', Ifset::get( $request, 'SubscriptionArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Delete subscription request contains no 'Name' field." );
            }

            $_data['SubscriptionArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            $_data['SubscriptionArn'] = $this->addArnPrefix( $request );
        }

        try
        {
            if ( null !== $_result = $this->_conn->unsubscribe( $_data ) )
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

            throw new InternalServerErrorException( "Failed to delete subscription '{$_data['SubscriptionArn']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleApplications()
    {
        $_result = false;

        switch ( $this->_action )
        {
            case static::GET:
                if ( empty( $this->_resourceId ) )
                {
                    $_result = array('resource' => $this->retrieveApplications());
                }
                else
                {
                    $_result = $this->retrieveApplication( $this->_resourceId );
                }
                break;

            case static::POST:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in application post request.' );
                }

                if ( empty( $this->_resourceId ) )
                {
                    $_result = $this->createApplication( $this->_requestPayload );
                }
                else
                {
                    $_result = false;  //  Not allowed
                }
                break;

            case static::PUT:
            case static::PATCH:
            case static::MERGE:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in application update request.' );
                }

                if ( !empty( $this->_resourceId ) )
                {
                    $this->_requestPayload['Name'] = $this->_resourceId;
                }
                $_result = $this->updateApplication( $this->_requestPayload );
                break;

            case static::DELETE:
                if ( empty( $this->_resourceId ) )
                {
                    if ( empty( $this->_requestPayload ) )
                    {
                        throw new BadRequestException( 'No data in application delete request.' );
                    }

                    $this->deleteApplication( $this->_requestPayload );
                }
                else
                {
                    $this->deleteApplication( $this->_resourceId );
                }
                $_result = array('success' => true);
                break;
        }

        return $_result;
    }

    /**
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     * @throws null
     */
    protected function _getApplicationsAsArray()
    {
        $_out = array();
        $_token = null;
        try
        {
            do
            {
                $_result = $this->_conn->listPlatformApplications(
                    array(
                        'NextToken' => $_token
                    )
                );
                $_topics = $_result['PlatformApplications'];
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

            throw new InternalServerErrorException( "Failed to retrieve applications.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return $_out;
    }

    /**
     * @param bool $refresh
     *
     * @return array
     */
    protected function retrieveApplications( /** @noinspection PhpUnusedParameterInspection */
        $refresh = true )
    {
        $_resources = array();
        $_result = $this->_getApplicationsAsArray();
        foreach ( $_result as $_sub )
        {
            $_sub['Name'] = $this->stripArnPrefix( IfSet::get( $_sub, 'PlatformApplicationArn' ) );
            $_resources[] = $_sub;
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
    public function retrieveApplication( $resource )
    {
        $_request = array('PlatformApplicationArn' => $this->addArnPrefix( $resource ));

        try
        {
            if ( null !== $_result = $this->_conn->getPlatformApplicationAttributes( $_request ) )
            {
                $_attributes = Option::get( $_result->toArray(), 'Attributes' );

                return array('Name'                   => $this->stripArnPrefix( $resource ),
                             'PlatformApplicationArn' => $this->addArnPrefix( $resource ),
                             'Attributes'             => $_attributes
                );
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

    public function createApplication( $request )
    {
        $_data = array();
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'Name' );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Create application request contains no 'Name' field." );
            }

            $_data['Name'] = $_name;
        }
        else
        {
            $_data['Name'] = $request;
        }

        try
        {
            if ( null !== $_result = $this->_conn->createPlatformApplication( $_data ) )
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

            throw new InternalServerErrorException( "Failed to create application '{$_data['Name']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function updateApplication( $request )
    {
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'Name', Ifset::get( $request, 'PlatformApplicationArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Update application request contains no 'Name' field." );
            }

            $request['PlatformApplicationArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            throw new BadRequestException( "Update topic request contains no fields." );
        }

        try
        {
            if ( null !== $_result = $this->_conn->setPlatformApplicationAttributes( $request ) )
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

            throw new InternalServerErrorException(
                "Failed to delete application '{$request['PlatformApplicationArn']}'.\n{$_ex->getMessage()}", $_ex->getCode()
            );
        }

        return array();
    }

    public function deleteApplication( $request )
    {
        $_data = array();
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'Name', Ifset::get( $request, 'PlatformApplicationArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Delete application request contains no 'Name' field." );
            }

            $_data['PlatformApplicationArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            $_data['PlatformApplicationArn'] = $this->addArnPrefix( $request );
        }

        try
        {
            if ( null !== $_result = $this->_conn->deletePlatformApplication( $_data ) )
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

            throw new InternalServerErrorException(
                "Failed to delete application '{$_data['PlatformApplicationArn']}'.\n{$_ex->getMessage()}", $_ex->getCode()
            );
        }

        return array();
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleEndpoints()
    {
        $_result = false;

        switch ( $this->_action )
        {
            case static::GET:
                if ( empty( $this->_relatedResourceId ) )
                {
                    $_result = array('resource' => $this->retrieveEndpoints( $this->_resourceId ));
                }
                else
                {
                    $_result = $this->retrieveEndpoint( $this->_relatedResourceId );
                }
                break;

            case static::POST:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in endpoint post request.' );
                }

                if ( empty( $this->_relatedResourceId ) )
                {
                    $this->_requestPayload['PlatformApplicationArn'] = $this->addArnPrefix( $this->_resourceId );
                    $_result = $this->createEndpoint( $this->_requestPayload );
                }
                else
                {
                    $_result = false;  //  Not allowed
                }
                break;

            case static::PUT:
            case static::PATCH:
            case static::MERGE:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in endpoint update request.' );
                }

                if ( !empty( $this->_relatedResourceId ) )
                {
                    $this->_requestPayload['Name'] = $this->_relatedResourceId;
                }
                $_result = $this->updateEndpoint( $this->_requestPayload );
                break;

            case static::DELETE:
                if ( empty( $this->_relatedResourceId ) )
                {
                    if ( empty( $this->_requestPayload ) )
                    {
                        throw new BadRequestException( 'No data in endpoint delete request.' );
                    }

                    $this->deleteEndpoint( $this->_requestPayload );
                }
                else
                {
                    $this->deleteEndpoint( $this->_relatedResourceId );
                }
                $_result = array('success' => true);
                break;
        }

        return $_result;
    }

    /**
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     * @throws null
     */
    protected function _getEndpointsAsArray( $application )
    {
        if ( empty( $application ) )
        {
            throw new BadRequestException( 'Platform application name required for retrieving endpoints.' );
        }

        $_out = array();
        $_token = null;
        try
        {
            do
            {
                $_result = $this->_conn->listEndpointsByPlatformApplication(
                    array(
                        'PlatformApplicationArn' => $application,
                        'NextToken'              => $_token
                    )
                );
                $_topics = $_result['Endpoints'];
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

            throw new InternalServerErrorException( "Failed to retrieve endpoints.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return $_out;
    }

    /**
     * @param bool $refresh
     *
     * @return array
     */
    public function retrieveEndpoints( $application, /** @noinspection PhpUnusedParameterInspection */
        $refresh = true )
    {
        $_resources = array();
        $_result = $this->_getEndpointsAsArray( $this->addArnPrefix( $application ) );
        foreach ( $_result as $_sub )
        {
            $_sub['Name'] = $this->stripArnPrefix( IfSet::get( $_sub, 'EndpointArn' ) );
            $_resources[] = $_sub;
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
    public function retrieveEndpoint( $resource )
    {
        $_request = array('EndpointArn' => $this->addArnPrefix( $resource ));

        try
        {
            if ( null !== $_result = $this->_conn->getEndpointAttributes( $_request ) )
            {
                $_attributes = Option::get( $_result->toArray(), 'Attributes' );

                return array('Name' => $this->stripArnPrefix( $resource ), 'EndpointArn' => $this->addArnPrefix( $resource ), 'Attributes' => $_attributes);
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

    public function createEndpoint( $request )
    {
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'PlatformApplicationArn' );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Create endpoint request contains no 'PlatformApplicationArn' field." );
            }
            $_name = Ifset::get( $request, 'Token' );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Create endpoint request contains no 'Token' field." );
            }
        }
        else
        {
            throw new BadRequestException( "Create endpoint request contains fields." );
        }

        try
        {
            if ( null !== $_result = $this->_conn->createPlatformEndpoint( $request ) )
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

            throw new InternalServerErrorException( "Failed to create endpoint '{$request['Name']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function updateEndpoint( $request )
    {
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'Name', Ifset::get( $request, 'EndpointArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Update endpoint request contains no 'Name' field." );
            }

            $request['EndpointArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            throw new BadRequestException( "Update endpoint request contains no fields." );
        }

        try
        {
            if ( null !== $_result = $this->_conn->setEndpointAttributes( $request ) )
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

            throw new InternalServerErrorException( "Failed to delete endpoint '{$request['EndpointArn']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function deleteEndpoint( $request )
    {
        $_data = array();
        if ( is_array( $request ) )
        {
            $_name = Ifset::get( $request, 'Name', Ifset::get( $request, 'EndpointArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Delete endpoint request contains no 'Name' field." );
            }

            $_data['EndpointArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            $_data['EndpointArn'] = $this->addArnPrefix( $request );
        }

        try
        {
            if ( null !== $_result = $this->_conn->deleteEndpoint( $_data ) )
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

            throw new InternalServerErrorException( "Failed to delete endpoint '{$_data['EndpointArn']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
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
