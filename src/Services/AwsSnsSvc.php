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
    const APPLICATION_RESOURCE = 'app';
    /**
     * Resource tag for dealing with subscription
     */
    const ENDPOINT_RESOURCE = 'endpoint';
    /**
     * Resource tag for dealing with subscription
     */
    const ARN_PREFIX = 'arn:aws:sns:';
    /**
     * List types when requesting resources
     */
    const FORMAT_SIMPLE = 'simple';
    const FORMAT_ARN = 'arn';
    const FORMAT_FULL = 'full';

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

        if ( !empty( $_more ) )
        {
            if ( ( static::APPLICATION_RESOURCE == $this->_resource ) && ( static::ENDPOINT_RESOURCE !== $_more ) )
            {
                do
                {
                    $this->_resourceId .= '/' . $_more;
                    $_pos++;
                    $_more = Option::get( $this->_resourceArray, $_pos );
                }
                while ( !empty( $_more ) && ( static::ENDPOINT_RESOURCE !== $_more ) );
            }
            elseif ( static::ENDPOINT_RESOURCE == $this->_resource )
            {
                //  This will be the full resource path
                do
                {
                    $this->_resourceId .= '/' . $_more;
                    $_pos++;
                    $_more = Option::get( $this->_resourceArray, $_pos );
                }
                while ( !empty( $_more ) );
            }
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
        $_reqAction = $this->getRequestedAction();
        $_fullResourcePath = null;
        if ( !empty( $this->_resource ) )
        {
            switch ( $this->_resource )
            {
                case static::TOPIC_RESOURCE:
                    $_fullResourcePath = $this->_resource . '/';
                    if ( !empty( $this->_resourceId ) )
                    {
                        $_fullResourcePath .= $this->stripArnPrefix( $this->_resourceId );
                        if ( static::SUBSCRIPTION_RESOURCE == $this->_relatedResource )
                        {
                            $_relatedResourcePath = $this->_relatedResource . '/';
                            if ( !empty( $this->_relatedResourceId ) )
                            {
                                $_relatedResourcePath .= $this->stripArnPrefix( $this->_relatedResourceId );
                            }
                            $this->checkPermission( $_reqAction, $_relatedResourcePath );
                        }
                    }
                    break;
                case static::SUBSCRIPTION_RESOURCE:
                    $_fullResourcePath = $this->_resource . '/';
                    if ( !empty( $this->_resourceId ) )
                    {
                        $_fullResourcePath .= $this->stripArnPrefix( $this->_resourceId );
                    }
                    break;
                case static::APPLICATION_RESOURCE:
                    $_fullResourcePath = $this->_resource . '/';
                    if ( !empty( $this->_resourceId ) )
                    {
                        $_fullResourcePath .= $this->stripArnPrefix( $this->_resourceId );
                        if ( static::ENDPOINT_RESOURCE == $this->_relatedResource )
                        {
                            $_relatedResourcePath = $this->_relatedResource . '/';
                            if ( !empty( $this->_relatedResourceId ) )
                            {
                                $_relatedResourcePath .= $this->stripArnPrefix( $this->_relatedResourceId );
                            }
                            $this->checkPermission( $_reqAction, $_relatedResourcePath );
                        }
                    }
                    break;
                case static::ENDPOINT_RESOURCE:
                    $_fullResourcePath = $this->_resource . '/';
                    if ( !empty( $this->_resourceId ) )
                    {
                        $_fullResourcePath .= $this->stripArnPrefix( $this->_resourceId );
                    }
                    break;
                default:
                    break;
            }
        }

        $this->checkPermission( $_reqAction, $_fullResourcePath );
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

            case static::ENDPOINT_RESOURCE:
                return $this->_handleEndpoints();

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
                $_result = static::retrieveTopics( static::FORMAT_SIMPLE );
                foreach ( $_result as $_topic )
                {
                    $_name = static::TOPIC_RESOURCE . '/' . $_topic;
                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        $_resources[] = $_name;
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
                $_result = static::retrieveSubscriptions( null, static::FORMAT_SIMPLE );
                foreach ( $_result as $_sub )
                {
                    $_name = static::SUBSCRIPTION_RESOURCE . '/' . $_sub;
                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        $_resources[] = $_name;
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
                $_result = static::retrieveApplications( static::FORMAT_SIMPLE );
                foreach ( $_result as $_app )
                {
                    $_name = static::APPLICATION_RESOURCE . '/' . $_app;
                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        $_resources[] = $_name;
                    }
                }
            }

            $_access = $this->getPermissions( static::ENDPOINT_RESOURCE );
            if ( !empty( $_access ) )
            {
                if ( $_namesOnly )
                {
                    $_resources[] = static::ENDPOINT_RESOURCE;
                }
                elseif ( $_asComponents )
                {
                    $_resources[] = static::ENDPOINT_RESOURCE . '/';
                    $_resources[] = static::ENDPOINT_RESOURCE . '/*';
                }
                else
                {
                    $_resources[] = array('name' => static::ENDPOINT_RESOURCE, 'access' => $_access);
                }
            }
            if ( $_asComponents )
            {
                $_result = static::retrieveEndpoints( null, static::FORMAT_SIMPLE );
                foreach ( $_result as $_end )
                {
                    $_name = static::ENDPOINT_RESOURCE . '/' . $_end;
                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        $_resources[] = $_name;
                    }
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

        $_result = $this->publish( $this->_requestPayload );

        return $_result;
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
     * @param string $format
     *
     * @return array
     */
    protected function retrieveTopics( $format = null )
    {
        $_resources = array();
        $_result = $this->_getTopicsAsArray();
        foreach ( $_result as $_topic )
        {
            switch ( $format )
            {
                case static::FORMAT_SIMPLE:
                    $_resources[] = $this->stripArnPrefix( IfSet::get( $_topic, 'TopicArn' ) );
                    break;
                case static::FORMAT_ARN:
                    $_resources[] = IfSet::get( $_topic, 'TopicArn' );
                    break;
                case static::FORMAT_FULL:
                default:
                    $_topic['Topic'] = $this->stripArnPrefix( IfSet::get( $_topic, 'TopicArn' ) );
                    $_resources[] = $_topic;
                    break;
            }
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
                $_out = Option::get( $_result->toArray(), 'Attributes' );
                $_out['Topic'] = $this->stripArnPrefix( $resource );

                return $_out;
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
     * @param string      $request
     * @param string|null $resource_type
     * @param string|null $resource_id
     *
     * @return array
     * @throws InternalServerErrorException
     */
    public function publish( $request, $resource_type = null, $resource_id = null )
    {
        /** http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.Sns.SnsClient.html#_publish */
        $_data = array();
        if ( is_array( $request ) )
        {
            if ( null !== $_message = IfSet::get( $request, 'Message' ) )
            {
                $_data = array_merge( $_data, $request );
                if ( is_array( $_message ) )
                {
                    $_data['Message'] = json_encode($_message);

                    if ( !IfSet::has( $request, 'MessageStructure' ) )
                    {
                        $_data['MessageStructure'] = 'json';
                    }
                }
            }
            else
            {
                //  This array is the message
                $_data['Message'] = json_encode($request);
                $_data['MessageStructure'] = 'json';
            }
        }
        else
        {
            //  This string is the message
            $_data['Message'] = $request;
        }

        switch ( $resource_type )
        {
            case static::TOPIC_RESOURCE:
                $_data['TopicArn'] = $this->addArnPrefix( $resource_id );
                break;
            case static::ENDPOINT_RESOURCE:
                $_data['TargetArn'] = $this->addArnPrefix( $resource_id );
                break;
            default:
                //  Must contain resource, either Topic or Endpoint ARN
                $_topic = IfSet::get( $_data, 'Topic', IfSet::get( $_data, 'TopicArn' ) );
                $_endpoint = IfSet::get( $_data, 'Endpoint', IfSet::get( $_data, 'EndpointArn', IfSet::get( $_data, 'TargetArn' ) ) );
                if ( !empty( $_topic ) )
                {
                    $_data['TopicArn'] = $this->addArnPrefix( $_topic );
                }
                elseif ( !empty( $_endpoint ) )
                {
                    $_data['TargetArn'] = $this->addArnPrefix( $_endpoint );
                }
                else
                {
                    throw new BadRequestException( "Publish request does not contain resource, either 'Topic' or 'Endpoint'." );
                }

                break;
        }

        try
        {
            if ( null !== $_result = $this->_conn->publish( $_data ) )
            {
                $_id = IfSet::get( $_result->toArray(), 'MessageId', '' );

                return array('MessageId' => $_id);
            }
        }
        catch ( \Exception $_ex )
        {
            if ( null !== $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException( "Failed to push message.\n{$_ex->getMessage()}", $_ex->getCode() );
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
                    $_namesOnly = Option::getBool( $_REQUEST, 'names_only' );
                    $_asComponents = Option::getBool( $_REQUEST, 'as_access_components' );
                    $_format = ( $_namesOnly || $_asComponents ) ? static::FORMAT_SIMPLE : static::FORMAT_FULL;
                    $_result = array('resource' => $this->retrieveTopics( $_format ));
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
                    $_result = $this->publish( $this->_requestPayload, static::TOPIC_RESOURCE, $this->_resourceId );
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
                    $this->_requestPayload['Topic'] = $this->_resourceId;
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
            $_name = IfSet::get( $request, 'Name' );
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
                $_arn = IfSet::get( $_result->toArray(), 'TopicArn', '' );

                return array('Topic' => $this->stripArnPrefix( $_arn ), 'TopicArn' => $_arn);
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
            $_name = IfSet::get( $request, 'Topic', IfSet::get( $request, 'TopicArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Update topic request contains no 'Topic' field." );
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
                return array('success' => true);
            }
        }
        catch ( \Exception $_ex )
        {
            if ( null !== $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException( "Failed to update topic '{$request['TopicArn']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function deleteTopic( $request )
    {
        $_data = array();
        if ( is_array( $request ) )
        {
            $_name = IfSet::get( $request, 'Topic', IfSet::get( $request, 'TopicArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Delete Topic request contains no 'Topic' field." );
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
                return array('success' => true);
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
        $_related = ( $this->_relatedResource === static::SUBSCRIPTION_RESOURCE );
        $_theId = ( $_related ) ? $this->_relatedResourceId : $this->_resourceId;
        $_parent = ( $_related ) ? $this->_resourceId : null;

        switch ( $this->_action )
        {
            case static::GET:
                if ( empty( $_theId ) )
                {
                    $_namesOnly = Option::getBool( $_REQUEST, 'names_only' );
                    $_asComponents = Option::getBool( $_REQUEST, 'as_access_components' );
                    $_format = ( $_namesOnly || $_asComponents ) ? static::FORMAT_SIMPLE : static::FORMAT_FULL;
                    $_result = array('resource' => $this->retrieveSubscriptions( $_parent, $_format ));
                }
                else
                {
                    $_result = $this->retrieveSubscription( $_theId );
                }
                break;

            case static::POST:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in subscription post request.' );
                }

                if ( empty( $_theId ) )
                {
                    if ( $_parent )
                    {
                        $this->_requestPayload['Topic'] = $_parent;
                    }
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

                if ( !empty( $_theId ) )
                {
                    $this->_requestPayload['Subscription'] = $_theId;
                }
                $_result = $this->updateSubscription( $this->_requestPayload );
                break;

            case static::DELETE:
                if ( empty( $_theId ) )
                {
                    if ( empty( $this->_requestPayload ) )
                    {
                        throw new BadRequestException( 'No data in subscription delete request.' );
                    }

                    $_result = $this->deleteSubscription( $this->_requestPayload );
                }
                else
                {
                    $_result = $this->deleteSubscription( $_theId );
                }
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
    protected function _getSubscriptionsAsArray( $topic = null )
    {
        $_out = array();
        $_token = null;
        if ( !empty( $topic ) )
        {
            $topic = $this->addArnPrefix( $topic );
        }
        try
        {
            do
            {
                if ( empty( $topic ) )
                {
                    $_result = $this->_conn->listSubscriptions(
                        array(
                            'NextToken' => $_token
                        )
                    );
                }
                else
                {
                    $_result = $this->_conn->listSubscriptionsByTopic(
                        array(
                            'TopicArn'  => $topic,
                            'NextToken' => $_token
                        )
                    );
                }
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
     * @param string $format
     *
     * @return array
     */
    protected function retrieveSubscriptions( $topic = null, $format = null )
    {
        $_resources = array();
        $_result = $this->_getSubscriptionsAsArray( $topic );
        foreach ( $_result as $_sub )
        {
            switch ( $format )
            {
                case static::FORMAT_SIMPLE:
                    $_resources[] = $this->stripArnPrefix( IfSet::get( $_sub, 'SubscriptionArn' ) );
                    break;
                case static::FORMAT_ARN:
                    $_resources[] = IfSet::get( $_sub, 'SubscriptionArn' );
                    break;
                case static::FORMAT_FULL:
                default:
                    $_sub['Subscription'] = $this->stripArnPrefix( IfSet::get( $_sub, 'SubscriptionArn' ) );
                    $_resources[] = $_sub;
                    break;
            }
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
                $_out = array_merge( $_request, Option::get( $_result->toArray(), 'Attributes', array() ) );
                $_out['Subscription'] = $this->stripArnPrefix( $resource );

                return $_out;
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
            $_name = IfSet::get( $request, 'Topic', IfSet::get( $request, 'TopicArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Create Subscription request contains no 'Topic' field." );
            }

            $request['TopicArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            throw new BadRequestException( "Create Subscription request contains no fields." );
        }

        try
        {
            if ( null !== $_result = $this->_conn->subscribe( $request ) )
            {
                $_arn = IfSet::get( $_result->toArray(), 'SubscriptionArn', '' );

                return array('Subscription' => $this->stripArnPrefix( $_arn ), 'SubscriptionArn' => $_arn);
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
            $_name = IfSet::get( $request, 'Subscription', IfSet::get( $request, 'SubscriptionArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Update subscription request contains no 'Subscription' field." );
            }

            $request['SubscriptionArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            throw new BadRequestException( "Update subscription request contains no fields." );
        }

        try
        {
            if ( null !== $_result = $this->_conn->setSubscriptionAttributes( $request ) )
            {
                return array('success' => true);
            }
        }
        catch ( \Exception $_ex )
        {
            if ( null !== $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException( "Failed to update subscription '{$request['SubscriptionArn']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function deleteSubscription( $request )
    {
        $_data = array();
        if ( is_array( $request ) )
        {
            $_name = IfSet::get( $request, 'Subscription', IfSet::get( $request, 'SubscriptionArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Delete subscription request contains no 'Subscription' field." );
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
                return array('success' => true);
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
                    $_namesOnly = Option::getBool( $_REQUEST, 'names_only' );
                    $_asComponents = Option::getBool( $_REQUEST, 'as_access_components' );
                    $_format = ( $_namesOnly || $_asComponents ) ? static::FORMAT_SIMPLE : static::FORMAT_FULL;
                    $_result = array('resource' => $this->retrieveApplications( $_format ));
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
                    $this->_requestPayload['Application'] = $this->_resourceId;
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
     * @param string $format
     *
     * @return array
     */
    protected function retrieveApplications( $format = null )
    {
        $_resources = array();
        $_result = $this->_getApplicationsAsArray();
        foreach ( $_result as $_app )
        {
            switch ( $format )
            {
                case static::FORMAT_SIMPLE:
                    $_resources[] = $this->stripArnPrefix( IfSet::get( $_app, 'PlatformApplicationArn' ) );
                    break;
                case static::FORMAT_ARN:
                    $_resources[] = IfSet::get( $_app, 'PlatformApplicationArn' );
                    break;
                case static::FORMAT_FULL:
                default:
                    $_app['Application'] = $this->stripArnPrefix( IfSet::get( $_app, 'PlatformApplicationArn' ) );
                    $_resources[] = $_app;
                    break;
            }
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

                return array(
                    'Application'            => $this->stripArnPrefix( $resource ),
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
        if ( is_array( $request ) )
        {
            $_name = IfSet::get( $request, 'Name' );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Create application request contains no 'Name' field." );
            }
        }
        else
        {
            throw new BadRequestException( "Create application request contains no fields." );
        }

        try
        {
            if ( null !== $_result = $this->_conn->createPlatformApplication( $request ) )
            {
                $_arn = IfSet::get( $_result->toArray(), 'PlatformApplicationArn', '' );

                return array('Application' => $this->stripArnPrefix( $_arn ), 'PlatformApplicationArn' => $_arn);
            }
        }
        catch ( \Exception $_ex )
        {
            if ( null !== $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException( "Failed to create application '{$request['Name']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function updateApplication( $request )
    {
        if ( is_array( $request ) )
        {
            $_name = IfSet::get( $request, 'Application', IfSet::get( $request, 'PlatformApplicationArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Update application request contains no 'Application' field." );
            }

            $request['PlatformApplicationArn'] = $this->addArnPrefix( $_name );
        }
        else
        {
            throw new BadRequestException( "Update application request contains no fields." );
        }

        try
        {
            if ( null !== $_result = $this->_conn->setPlatformApplicationAttributes( $request ) )
            {
                return array('success' => true);
            }
        }
        catch ( \Exception $_ex )
        {
            if ( null !== $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException(
                "Failed to update application '{$request['PlatformApplicationArn']}'.\n{$_ex->getMessage()}", $_ex->getCode()
            );
        }

        return array();
    }

    public function deleteApplication( $request )
    {
        $_data = array();
        if ( is_array( $request ) )
        {
            $_name = IfSet::get( $request, 'Application', IfSet::get( $request, 'PlatformApplicationArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Delete application request contains no 'Application' field." );
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
                return array('success' => true);
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
        $_related = ( $this->_relatedResource === static::ENDPOINT_RESOURCE );
        $_theId = ( $_related ) ? $this->_relatedResourceId : $this->_resourceId;
        $_parent = ( $_related ) ? $this->_resourceId : null;

        switch ( $this->_action )
        {
            case static::GET:
                if ( empty( $_theId ) )
                {
                    $_namesOnly = Option::getBool( $_REQUEST, 'names_only' );
                    $_asComponents = Option::getBool( $_REQUEST, 'as_access_components' );
                    $_format = ( $_namesOnly || $_asComponents ) ? static::FORMAT_SIMPLE : static::FORMAT_FULL;
                    $_result = array('resource' => $this->retrieveEndpoints( $_parent, $_format ));
                }
                else
                {
                    $_result = $this->retrieveEndpoint( $_theId );
                }
                break;

            case static::POST:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in endpoint post request.' );
                }

                if ( empty( $_theId ) )
                {
                    if ( !empty( $_parent ) )
                    {
                        $this->_requestPayload['PlatformApplicationArn'] = $this->addArnPrefix( $_parent );
                    }
                    $_result = $this->createEndpoint( $this->_requestPayload );
                }
                else
                {
                    $_result = $this->publish( $this->_requestPayload, static::ENDPOINT_RESOURCE, $_theId );
                }
                break;

            case static::PUT:
            case static::PATCH:
            case static::MERGE:
                if ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in endpoint update request.' );
                }

                if ( !empty( $_theId ) )
                {
                    $this->_requestPayload['Endpoint'] = $_theId;
                }
                $_result = $this->updateEndpoint( $this->_requestPayload );
                break;

            case static::DELETE:
                if ( empty( $_theId ) )
                {
                    if ( empty( $this->_requestPayload ) )
                    {
                        throw new BadRequestException( 'No data in endpoint delete request.' );
                    }

                    $this->deleteEndpoint( $this->_requestPayload );
                }
                else
                {
                    $this->deleteEndpoint( $_theId );
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

        $application = $this->addArnPrefix( $application );
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
     * @param string $format
     *
     * @return array
     */
    public function retrieveEndpoints( $applications, $format = null )
    {
        $_resources = array();
        $applications = Option::clean( $applications );
        if ( empty( $applications ) )
        {
            $applications = $this->retrieveApplications( static::FORMAT_ARN );
        }

        foreach ( $applications as $application )
        {
            $_result = $this->_getEndpointsAsArray( $application );
            foreach ( $_result as $_end )
            {
                switch ( $format )
                {
                    case static::FORMAT_SIMPLE:
                        $_resources[] = $this->stripArnPrefix( IfSet::get( $_end, 'EndpointArn' ) );
                        break;
                    case static::FORMAT_ARN:
                        $_resources[] = IfSet::get( $_end, 'EndpointArn' );
                        break;
                    case static::FORMAT_FULL:
                    default:
                        $_end['Endpoint'] = $this->stripArnPrefix( IfSet::get( $_end, 'EndpointArn' ) );
                        $_resources[] = $_end;
                        break;
                }
            }
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

                return array('Endpoint' => $this->stripArnPrefix( $resource ), 'EndpointArn' => $this->addArnPrefix( $resource ), 'Attributes' => $_attributes);
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
            $_name = IfSet::get( $request, 'Application', IfSet::get( $request, 'PlatformApplicationArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Create endpoint request contains no 'Application' field." );
            }
            $request['PlatformApplicationArn'] = $this->addArnPrefix( $_name );
            $_name = IfSet::get( $request, 'Token' );
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
                $_arn = IfSet::get( $_result->toArray(), 'EndpointArn', '' );

                return array('Endpoint' => $this->stripArnPrefix( $_arn ), 'EndpointArn' => $_arn);
            }
        }
        catch ( \Exception $_ex )
        {
            if ( null !== $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException(
                "Failed to create endpoint for '{$request['PlatformApplicationArn']}'.\n{$_ex->getMessage()}", $_ex->getCode()
            );
        }

        return array();
    }

    public function updateEndpoint( $request )
    {
        if ( is_array( $request ) )
        {
            $_name = IfSet::get( $request, 'Endpoint', IfSet::get( $request, 'EndpointArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Update endpoint request contains no 'Endpoint' field." );
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
                return array('success' => true);
            }
        }
        catch ( \Exception $_ex )
        {
            if ( null !== $_newEx = static::translateException( $_ex ) )
            {
                throw $_newEx;
            }

            throw new InternalServerErrorException( "Failed to update endpoint '{$request['EndpointArn']}'.\n{$_ex->getMessage()}", $_ex->getCode() );
        }

        return array();
    }

    public function deleteEndpoint( $request )
    {
        $_data = array();
        if ( is_array( $request ) )
        {
            $_name = IfSet::get( $request, 'Endpoint', IfSet::get( $request, 'EndpointArn' ) );
            if ( empty( $_name ) )
            {
                throw new BadRequestException( "Delete endpoint request contains no 'Endpoint' field." );
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
                return array('success' => true);
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
