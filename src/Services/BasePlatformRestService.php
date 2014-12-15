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

use DreamFactory\Common\Enums\OutputFormats;
use DreamFactory\Platform\Components\DataTablesFormatter;
use DreamFactory\Platform\Enums\DataFormats;
use DreamFactory\Platform\Enums\ResponseFormats;
use DreamFactory\Platform\Events\Enums\PlatformServiceEvents;
use DreamFactory\Platform\Events\PlatformEvent;
use DreamFactory\Platform\Events\PlatformServiceEvent;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\MisconfigurationException;
use DreamFactory\Platform\Exceptions\NoExtraActionsException;
use DreamFactory\Platform\Interfaces\RestServiceLike;
use DreamFactory\Platform\Interfaces\ServiceOnlyResourceLike;
use DreamFactory\Platform\Interfaces\TransformerLike;
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Resources\System\Event;
use DreamFactory\Platform\Utility\DataFormatter;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\RestResponse;
use DreamFactory\Platform\Yii\Models\BasePlatformSystemModel;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;

/**
 * BasePlatformRestService
 * A base class for all DSP REST services
 */
abstract class BasePlatformRestService extends BasePlatformService implements RestServiceLike
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var string
     */
    const ACTION_TOKEN = '{action}';
    /**
     * @var string The default pattern of dispatch methods. Action token embedded.
     */
    const DEFAULT_HANDLER_PATTERN = '_handle{action}';

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var string Full path coming from the URL of the REST call
     */
    protected $_resourcePath = null;
    /**
     * @var array Resource path broken into array by path divider ('/')
     */
    protected $_resourceArray = null;
    /**
     * @var string First piece of the resource path array
     */
    protected $_resource = null;
    /**
     * @var string REST verb to take action on
     */
    protected $_action = self::GET;
    /**
     * @var bool If true, _handleResource() dispatches a call to _handle[Action]() methods if defined.
     * For example, a GET request would be dispatched to _handleGet().
     */
    protected $_autoDispatch = true;
    /**
     * @var string The pattern to search for dispatch methods.
     * The string {action} will be replaced by the inbound action (i.e. Get, Put, Post, etc.)
     */
    protected $_autoDispatchPattern = self::DEFAULT_HANDLER_PATTERN;
    /**
     * @var bool|array Array of verb aliases. Has no effect if $autoDispatch !== true
     *
     * Example:
     *
     * $this->_verbAliases = array(
     *     static::Put => static::Post,
     *     static::Patch => static::Post,
     *     static::Merge => static::Post,
     *
     *     // Use a closure too!
     *     static::Get => function($resource){
     *    ...
     *   },
     * );
     *
     *    The result will be that handleResource() will dispatch a PUT, PATCH, or MERGE request to the POST handler.
     */
    protected $_verbAliases;
    /**
     * @var string REST verb of original request. Set after verb change from $verbAliases map
     */
    protected $_originalAction = null;
    /**
     * @var array Additional actions that this resource will respond to
     */
    protected $_extraActions = null;
    /**
     * @var array The data that came in on the request
     */
    protected $_requestPayload = null;
    /**
     * @var mixed The response to the request
     */
    protected $_response = null;
    /**
     * @var int The HTTP response code returned for this request
     */
    protected $_responseCode = RestResponse::Ok;
    /**
     * @var int The inner payload response format, used for table formatting, etc.
     */
    protected $_responseFormat = ResponseFormats::RAW;
    /**
     * @var int|null Default output format, either null (native), or DataFormats enum value.
     * NOTE: Output format is different from RESPONSE format (inner payload format vs. envelope)
     */
    protected $_outputFormat = DataFormats::JSON;
    /**
     * @var string If set, prompt browser to download response as a file.
     */
    protected $_outputAsFile = null;
    /**
     * @var int
     */
    protected $_serviceId = null;
    /**
     * @var boolean If set, requests are processed as system administrator
     */
    protected $_runAsAdmin = false;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param array $settings
     *
     * @return \DreamFactory\Platform\Services\BasePlatformRestService
     */
    public function __construct( $settings = array() )
    {
        $this->_serviceId = Option::get( $settings, 'id', null, true );

        parent::__construct( Option::clean( $settings ) );
    }

    /**
     * @param string $resource      Optional resource for the REST call
     * @param string $action        HTTP action for this request
     * @param string $output_format Optional override for detecting output format
     * @param bool   $run_as_admin  If true, request will be processed as admin
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return mixed
     */
    public function processRequest( $resource = null, $action = self::GET, $output_format = null, $run_as_admin = false )
    {
        $this->_runAsAdmin = $run_as_admin;

        $this->_setAction( $action );

        //  Get and validate all the request parameters
        $this->_detectAppName();
        $this->_detectResourceMembers( $resource );
        $this->_detectRequestMembers();
        $this->_detectResponseMembers( $output_format );

        //  Perform any pre-request processing
        $this->_preProcess();

        //	Inherent failure?
        if ( false === ( $this->_response = $this->_handleResource() ) )
        {
            $_message =
                $this->_action .
                ' requests' .
                ( !empty( $this->_resource ) ? ' for resource "' . $this->_resourcePath . '"' : ' without a resource' ) .
                ' are not currently supported by the "' .
                $this->_apiName .
                '" service.';

            throw new BadRequestException( $_message );
        }

        $this->_postProcess();

        return $this->_respond();
    }

    /**
     * @param string $resourceName
     *
     * @return BasePlatformRestResource
     * @deprecated Use ResourceStore::resource(). Will be removed in v2.0
     */
    public static function getNewResource( $resourceName = null )
    {
        return ResourceStore::resource( $resourceName );
    }

    /**
     * @param string $resourceName
     *
     * @return BasePlatformSystemModel
     * @deprecated Use ResourceStore::model(). Will be removed in v2.0
     */
    public static function getNewModel( $resourceName = null )
    {
        return ResourceStore::model( $resourceName );
    }

    /**
     * Allows the resource to respond to special actions. Presentation information for instance.
     */
    protected function _handleExtraActions()
    {
        if ( !empty( $this->_extraActions ) && is_array( $this->_extraActions ) )
        {
            static $_keys;

            if ( null === $_keys )
            {
                $_keys = array_keys( $this->_extraActions );
            }

            //	Does this action have a handler?
            if ( false !== ( $_action = array_search( strtolower( $this->_resource ), array_map( 'strtolower', $_keys ) ) )
            )
            {
                $_handler = $this->_extraActions[ $_action ];

                if ( !is_callable( $_handler ) )
                {
                    throw new MisconfigurationException(
                        'The handler specified for extra action "' . $_action . '" is invalid.'
                    );
                }

                //	Added $this as argument because handler *could* be outside this class
                return call_user_func( $_handler, $this );
            }
        }

        //	Nada
        throw new NoExtraActionsException();
    }

    /**
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return bool
     */
    protected function _handleResource()
    {
        //	Allow verb sub-actions
        try
        {
            if ( !empty( $this->_extraActions ) )
            {
                return $this->_handleExtraActions();
            }
        }
        catch ( NoExtraActionsException $_ex )
        {
            //	Safely ignored
        }

        //	Now all actions must be HTTP verbs
        if ( !HttpMethod::contains( $this->_action ) )
        {
            throw new BadRequestException( 'The action "' . $this->_action . '" is not supported.' );
        }

        $_methodToCall = false;

        //	Check verb aliases as closures
        if ( true === $this->_autoDispatch && null !== ( $_alias = Option::get( $this->_verbAliases, $this->_action ) )
        )
        {
            //	A closure?
            if ( is_callable( $_alias ) )
            {
                $_methodToCall = $_alias;
            }
        }

        //  Not an alias, build a dispatch method if needed
        if ( !$_methodToCall )
        {
            //	If we have a dedicated handler method, call it
            $_method = str_ireplace( static::ACTION_TOKEN, $this->_action, $this->_autoDispatchPattern );

            if ( $this->_autoDispatch && method_exists( $this, $_method ) )
            {
                $_methodToCall = array($this, $_method);
            }
        }

        if ( $_methodToCall )
        {
            $_result = call_user_func( $_methodToCall );

            //  Only GETs trigger after the call
            if ( HttpMethod::GET == $this->_action )
            {
                $this->_triggerActionEvent( $_result, null, null, true );
            }

            return $_result;
        }

        //	Otherwise just return false
        return false;
    }

    /**
     * Apply the commonly used REST path members to the class
     *
     * @param string $resourcePath
     *
     * @return $this
     */
    protected function _detectResourceMembers( $resourcePath = null )
    {
        $this->_resourcePath = $resourcePath;
        $this->_resourceArray = ( !empty( $this->_resourcePath ) ) ? explode( '/', $this->_resourcePath ) : array();

        if ( empty( $this->_resource ) )
        {
            if ( null !== ( $_resource = Option::get( $this->_resourceArray, 0 ) ) )
            {
                $this->_resource = $_resource;
            }
        }

        return $this;
    }

    /**
     * Apply the commonly used REST request members to the class
     *
     * @return $this
     */
    protected function _detectRequestMembers()
    {
        // by default, just pull payload off the wire
        $this->_requestPayload = RestData::getPostedData();

        return $this;
    }

    /**
     * Source of the PRE_PROCESS event
     *
     * @return mixed
     */
    protected function _preProcess()
    {
        if ( $this instanceof BasePlatformRestResource || $this instanceof ServiceOnlyResourceLike )
        {
            $this->_triggerActionEvent( $this->_response, PlatformServiceEvents::PRE_PROCESS );
        }
    }

    /**
     * Handle any post-request processing
     * Source of the POST_PROCESS event
     *
     * @return mixed
     */
    protected function _postProcess()
    {
        if ( $this instanceof BasePlatformRestResource || $this instanceof ServiceOnlyResourceLike )
        {
            $this->_triggerActionEvent( $this->_response, PlatformServiceEvents::POST_PROCESS, null, true );
        }
    }

    /**
     * @return mixed|null If response is for internal use, returns result of operation.
     *                    Otherwise, responds to REST client in desired format and ends processing.
     */
    protected function _respond()
    {
        static $_responded = false;

        if ( $_responded )
        {
            return;
        }

        //	Native and PHP response types return, not emit...
        if ( in_array(
            $this->_outputFormat,
            array(false, null, '', DataFormats::PHP_ARRAY, DataFormats::PHP_OBJECT, DataFormats::NATIVE),
            true
        ) )
        {
            return $this->_response;
        }

        $_result = $this->_response;

        if ( false !== $this->_outputFormat && empty( $this->_outputFormat ) )
        {
            // safety belt, should not hit here
            $this->_outputFormat = DataFormats::JSON;
        }

        //	Neutralize the output format
        if ( !is_numeric( $this->_outputFormat ) )
        {
            $this->_outputFormat = DataFormats::toNumeric( $this->_outputFormat );
        }

        if ( null === $this->_nativeFormat && DataFormats::CSV == $this->_outputFormat )
        {
            // need to strip 'record' wrapper before reformatting to csv
            //@todo move this logic elsewhere
            $_result = Option::get( $_result, 'record', $_result );
        }

        //	json response formatted by specialized response class (JsonResponse)
        if ( DataFormats::JSON !== $this->_outputFormat )
        {
            $_result = DataFormatter::reformatData( $_result, $this->_nativeFormat, $this->_outputFormat );
        }

        if ( !empty( $this->_outputFormat ) )
        {
            //	No return from here...
            $_response = RestResponse::sendResults(
                $_result,
                $this->_responseCode,
                $this->_outputFormat,
                $this->_outputAsFile,
                false
            );
        }

        //	Native arrays must stay local, just return
        $_responded = true;

        return $_result;
    }

    /**
     * Determine the app_name/API key of this request
     *
     * @return mixed
     */
    protected function _detectAppName()
    {
        $_request = Pii::requestObject();

        // 	Determine application if any
        $_appName = $_request->get(
            'app_name',
            //	No app_name, look for headers...
            Option::server(
                'HTTP_X_DREAMFACTORY_APPLICATION_NAME',
                Option::server( 'HTTP_X_APPLICATION_NAME' )
            ),
            FILTER_SANITIZE_STRING
        );

        //	Still empty?
        if ( empty( $_appName ) )
        {
            //	We give portal requests a break, as well as inbound OAuth redirects
            if ( false !== stripos( Option::server( 'REQUEST_URI' ), '/rest/portal', 0 ) )
            {
                $_appName = 'portal';
            }
            elseif ( isset( $_REQUEST, $_REQUEST['code'], $_REQUEST['state'], $_REQUEST['oasys'] ) )
            {
                $_appName = 'auth_redirect';
            }
            else
            {
                RestResponse::sendErrors(
                    new BadRequestException( 'No application name header or parameter value in request.' )
                );
            }
        }

        // assign to global for system usage
        SystemManager::setCurrentAppName( $_appName );
    }

    /**
     * @param string $output_format
     */
    protected function _detectResponseMembers( $output_format = null )
    {
        //	Determine output format, inner and outer formatting if necessary
        $this->_outputFormat = RestResponse::detectResponseFormat( $output_format, $this->_responseFormat );

        //	Determine if output as file is enabled
        $_file = FilterInput::request( 'file', null, FILTER_SANITIZE_STRING );

        if ( !empty( $_file ) )
        {
            if ( Scalar::boolval( $_file ) )
            {
                $_file = ( empty( $this->_resource ) ) ? $this->getApiName() : $this->_resource;
                $_file .= '.' . DataFormats::toString( $this->_outputFormat );
            }

            $this->_outputAsFile = $_file;
        }
    }

    /**
     * @return bool
     */
    protected function _handleGet()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function _handleMerge()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function _handleOptions()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function _handleCopy()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function _handleConnect()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function _handlePost()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function _handlePut()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function _handlePatch()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function _handleDelete()
    {
        return false;
    }

    /**
     * @param string $operation
     * @param string $resource
     *
     * @return bool
     */
    public function checkPermission( $operation, $resource = null )
    {
        if ( $this->_runAsAdmin )
        {
            return true;
        }

        return ResourceStore::checkPermission( $operation, $this->_apiName, $resource );
    }

    /**
     * @param string $resource
     *
     * @return string
     */
    public function getPermissions( $resource = null )
    {
        return ResourceStore::getPermissions( $this->_apiName, $resource );
    }

    /**
     * @param string        $eventName
     * @param PlatformEvent $event
     * @param int           $priority
     *
     * @return bool|PlatformEvent
     */
    public function trigger( $eventName, $event = null, $priority = 0 )
    {
        if ( is_array( $event ) )
        {
            $event = new PlatformServiceEvent( $this->_apiName, $this->_resource, $event );
        }

        return Platform::trigger(
            str_ireplace(
                array('{api_name}', '{action}'),
                array($this->_apiName == 'system' ? $this->_resource : $this->_apiName, strtolower( $this->_action )),
                $eventName
            ),
            $event ?: new PlatformServiceEvent( $this->_apiName, $this->_resource, $this->_response ),
            $priority
        );
    }

    /**
     * Triggers the appropriate event for the action /api_name/resource/resourceId.
     *
     * The appropriate event is determined by the event mapping maintained in the
     * resources' Swagger files.
     *
     * The event data {@see PlatformEvent::getData()} in the event is the result/response that
     * the initial REST request is now returning to the client.
     *
     * These events will only trigger a single time per request.
     *
     * @param mixed                $result        The result of the call
     * @param string               $eventName     The event to trigger. If not supplied, one will looked up based on the context
     * @param PlatformServiceEvent $event
     * @param bool                 $isPostProcess Will be true if this is a "post-process" type event
     *
     * @throws \Exception
     * @return bool
     */
    protected function _triggerActionEvent( &$result, $eventName = null, $event = null, $isPostProcess = false )
    {
        static $_triggeredEvents = array();

        $_result = array();
        $_pathInfo = $this->_getCleanedPathInfo();

        $_values = array(
            'action'          => strtolower( $this->_action ),
            'api_name'        => $this->_apiName,
            'service_id'      => $this->_serviceId,
            'request_payload' => $this->_requestPayload,
            'table_name'      => $this->_resource,
            'request_uri'     => explode( '/', $_pathInfo ),
            'container'       => $this->_resource,
            'folder_path'     => Option::get( $this->_resourceArray, 1 ),
            'file_path'       => Option::get( $this->_resourceArray, 2 ),
        );

        //  Add file service replacements
        if ( $this instanceof BaseFileSvc )
        {
            $_values['container'] = $this->getContainerId();
            $_values['folder_path'] = $this->getFolderPath();

            //  Convert embedded slashes to dollar signs
            if ( false !== strpos( $_values['folder_path'], '/' ) )
            {
                $_values['folder_path'] = str_replace( '/', '$', $_values['folder_path'] );
            }

            $_values['file_path'] = $this->getFilePath();
        }

        //  Lookup the appropriate event if not specified.
        $_eventNames = $eventName ?: SwaggerManager::findEvent( $this, $this->_action, null, $_values );
        $_eventNames = is_array( $_eventNames ) ? $_eventNames : array($_eventNames);

        if ( 'rest' !== ( $_part = array_shift( $_values['request_uri'] ) ) )
        {
            array_unshift( $_values['request_uri'], $_part );
        }

        foreach ( $_eventNames as $_eventName )
        {
            //  No event or already triggered, bail.
            if ( empty( $_eventName ) )
            {
                continue;
            }

            $_service = $this->_apiName;
            $_event = $event ?: new PlatformServiceEvent( $_service, $this->_resource, $result );
            $_event->setPostProcessScript( $isPostProcess );
            $_event->setRequestData( $this->_requestPayload );
            $_event->setResponseData( $result );

            //  Normalize the event name
            $_eventName = Event::normalizeEventName( $_event, $_eventName, $_values );

            //  Already triggered?
            if ( isset( $_triggeredEvents[ $_eventName ] ) )
            {
                continue;
            }

            //  Fire it and get the maybe-modified event
            $_event = $this->trigger( $_eventName, $_event );

            //  Merge back the results
            if ( $_event instanceOf PlatformEvent )
            {
                $result = $_eventData = $_event->getResponseData();

                //  Stick it back into the request for processing...
                if ( !$isPostProcess )
                {
                    $this->_requestPayload = $_event->getRequestData();

                    $_request = Pii::requestObject();

                    //  Reinitialize with new content...
                    $_request->initialize(
                        $_request->query->all(),
                        $_request->request->all(),
                        $_request->attributes->all(),
                        $_request->cookies->all(),
                        $_request->files->all(),
                        $_request->server->all(),
                        is_string( $this->_requestPayload )
                            ? $this->_requestPayload
                            : json_encode(
                            $this->_requestPayload,
                            JSON_UNESCAPED_SLASHES
                        )
                    );

                    Pii::app()->setRequestObject( $_request );
                }

                unset( $_eventData );
            }

            //  Cache and bail
            $_triggeredEvents[ $_eventName ] = true;
            $_result[] = $_event;
        }

        return 1 == count( $_result ) ? $_result[0] : $_result;
    }

    /**
     * Cleans the path info/request uri for events
     *
     * @return string
     */
    protected function _getCleanedPathInfo()
    {
        $_pathInfo = Option::server( 'INLINE_REQUEST_URI', Pii::request( false )->getPathInfo() );

        if ( '/rest' == substr( $_pathInfo, 0, 5 ) )
        {
            $_pathInfo = substr( $_pathInfo, 5 );
        }

        return trim( $_pathInfo, '/' );
    }

    /**
     * Adds criteria garnered from the query string from DataTables
     *
     * @param array|\CDbCriteria $criteria
     * @param array              $columns
     * @param array|\CDbCriteria $criteria
     *
     * @return array|\CDbCriteria
     */
    protected function _buildCriteria( $columns, $criteria = null )
    {
        /** @var TransformerLike $_formatter */
        $_formatter = null;

        switch ( $this->_outputFormat )
        {
            case OutputFormats::DataTables:
                $_formatter = new DataTablesFormatter();
                break;
        }

        if ( null !== $_formatter )
        {
            return array();
        }

        return $_formatter->buildCriteria( $columns, $criteria );
    }

    /**
     * @param mixed $response
     *
     * @return BasePlatformRestService
     */
    public function setResponse( $response )
    {
        $this->_response = $response;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * @param string $action
     *
     * @return BasePlatformRestService
     */
    protected function _setAction( $action = self::GET )
    {
        $this->_action = trim( strtoupper( $action ) );

        //	Check verb aliases, set correct action allowing for closures
        if ( null !== ( $_alias = Option::get( $this->_verbAliases, $this->_action ) ) )
        {
            //	A closure?
            if ( !is_callable( $_alias ) )
            {
                //	Set original and work with alias
                $this->_originalAction = $this->_action;
                $this->_action = $_alias;
            }
        }

        return $this;
    }

    /**
     * @param string $action
     *
     * @return BasePlatformRestService
     */
    public function overrideAction( $action )
    {
        $this->_action = trim( strtoupper( $action ) );

        return $this;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->_action;
    }

    /**
     * @return string
     */
    public function getResource()
    {
        return $this->_resource;
    }

    /**
     * @return array
     */
    public function getResourceArray()
    {
        return $this->_resourceArray;
    }

    /**
     * @return string
     */
    public function getResourcePath()
    {
        return $this->_resourcePath;
    }

    /**
     * @param boolean $autoDispatch
     *
     * @return BasePlatformRestService
     */
    public function setAutoDispatch( $autoDispatch )
    {
        $this->_autoDispatch = $autoDispatch;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAutoDispatch()
    {
        return $this->_autoDispatch;
    }

    /**
     * @param string $autoDispatchPattern
     *
     * @return BasePlatformRestService
     */
    public function setAutoDispatchPattern( $autoDispatchPattern )
    {
        $this->_autoDispatchPattern = $autoDispatchPattern;

        return $this;
    }

    /**
     * @return string
     */
    public function getAutoDispatchPattern()
    {
        return $this->_autoDispatchPattern;
    }

    /**
     * @return string
     */
    public function getOriginalAction()
    {
        return $this->_originalAction;
    }

    /**
     * @param array|bool $verbAliases
     *
     * @return BasePlatformRestService
     */
    public function setVerbAliases( $verbAliases )
    {
        $this->_verbAliases = $verbAliases;

        return $this;
    }

    /**
     * @param string $verb
     * @param string $alias
     *
     * @return BasePlatformRestService
     */
    public function setVerbAlias( $verb, $alias )
    {
        Option::set( $this->_verbAliases, $verb, $alias );

        return $this;
    }

    /**
     * @param string $verb Clear one, or all if $verb === null, verb alias mappings
     *
     * @return $this
     */
    public function clearVerbAlias( $verb = null )
    {
        if ( null === $verb || empty( $this->_verbAliases ) )
        {
            $this->_verbAliases = array();
        }
        else
        {
            unset( $this->_verbAliases[ $verb ] );
        }

        return $this;
    }

    /**
     * @return array|bool
     */
    public function getVerbAliases()
    {
        return $this->_verbAliases;
    }

    /**
     * @return string The action actually requested
     */
    public function getRequestedAction()
    {
        return $this->_originalAction ?: $this->_action;
    }

    /**
     * @return int
     */
    public function getServiceId()
    {
        return $this->_serviceId;
    }

    /**
     * @param array $extraActions
     *
     * @return BasePlatformRestService
     */
    public function setExtraActions( array $extraActions )
    {
        $this->_extraActions = $extraActions;

        return $this;
    }

    /**
     * @return array
     */
    public function getExtraActions()
    {
        return $this->_extraActions;
    }

    /**
     * @param string   $action
     * @param callable $handler
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function addExtraAction( $action, $handler )
    {
        if ( !is_callable( $handler ) )
        {
            throw new \InvalidArgumentException( 'The handler specified not callable.' );
        }

        if ( empty( $this->_extraActions ) )
        {
            $this->_extraActions = array();
        }

        $this->_extraActions[ $action ] = $handler;

        return $this;
    }

    /**
     * @param array $requestPayload
     *
     * @return BasePlatformRestService
     */
    public function setRequestPayload( $requestPayload )
    {
        $this->_requestPayload = $requestPayload;

        return $this;
    }

    /**
     * @return array
     */
    public function getRequestPayload()
    {
        return $this->_requestPayload;
    }

    /**
     * @param array $responseCode
     *
     * @return $this
     */
    public function setResponseCode( $responseCode )
    {
        $this->_responseCode = $responseCode;

        return $this;
    }

    /**
     * @return array
     */
    public function getResponseCode()
    {
        return $this->_responseCode;
    }

    /**
     * @param string $outputFormat
     *
     * @return $this
     */
    public function setOutputFormat( $outputFormat )
    {
        $this->_outputFormat = $outputFormat;

        return $this;
    }

    /**
     * @return string
     */
    public function getOutputFormat()
    {
        return $this->_outputFormat;
    }

    /**
     * @param int $responseFormat
     *
     * @return BasePlatformRestService
     */
    public function setResponseFormat( $responseFormat )
    {
        $this->_responseFormat = $responseFormat;

        return $this;
    }

    /**
     * @return int
     */
    public function getResponseFormat()
    {
        return $this->_responseFormat;
    }

    /**
     * @param string $outputAsFile
     *
     * @return BasePlatformRestService
     */
    public function setOutputAsFile( $outputAsFile )
    {
        $this->_outputAsFile = $outputAsFile;

        return $this;
    }

    /**
     * @return string
     */
    public function getOutputAsFile()
    {
        return $this->_outputAsFile;
    }
}
