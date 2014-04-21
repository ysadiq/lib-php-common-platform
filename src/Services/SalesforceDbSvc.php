<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\NotImplementedException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Yii\Utility\Pii;
use Guzzle\Http\Client as GuzzleClient;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;
use Phpforce\SoapClient as SoapClient;

/**
 * SalesforceDbSvc.php
 * A service to handle Salesforce services accessed through the REST API.
 *
 */
class SalesforceDbSvc extends BaseDbSvc
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default record identifier field
     */
    const DEFAULT_ID_FIELD = 'Id';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string
     */
    protected $_username;
    /**
     * @var array
     */
    protected $_password;
    /**
     * @var array
     */
    protected $_securityToken;
    /**
     * @var array
     */
    protected $_version = 'v28.0';
    /**
     * @var array
     */
    protected $_sessionCache;
    /**
     * @var array
     */
    protected $_fieldCache;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new SalesforceDbSvc
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     * @return \DreamFactory\Platform\Services\SalesforceDbSvc
     */
    public function __construct( $config )
    {
        if ( null === Option::get( $config, 'verb_aliases' ) )
        {
            //	Default verb aliases
            $config['verb_aliases'] = array(
                static::PATCH => static::PUT,
                static::MERGE => static::PUT,
            );
        }

        parent::__construct( $config );

        $_credentials = Session::replaceLookup( Option::get( $config, 'credentials' ) );

        $this->_username = Session::replaceLookup( Option::get( $_credentials, 'username' ) );
        $this->_password = Session::replaceLookup( Option::get( $_credentials, 'password' ) );
        $this->_securityToken = Session::replaceLookup( Option::get( $_credentials, 'security_token' ) );
        if ( empty( $this->_securityToken ) )
        {
            $this->_securityToken = ''; // gets appended to password
        }

        if ( empty( $this->_username ) || empty( $this->_password ) )
        {
            throw new \InvalidArgumentException( 'A Salesforce username and password are required for this service.' );
        }

        $_version = Option::get( $_credentials, 'version' );
        if ( !empty( $_version ) )
        {
            $this->_version = $_version;
        }

        $this->_sessionCache = Pii::getState( 'service.' . $this->getApiName() . '.cache', array() );

        $this->_fieldCache = array();
    }

    protected function _getSoapLoginResult()
    {
        //@todo use client provided Salesforce wsdl for the different versions
        $_wsdl = Platform::getLibraryTemplatePath( '/salesforce/salesforce.enterprise.wsdl.xml' );

        $_builder = new SoapClient\ClientBuilder( $_wsdl, $this->_username, $this->_password, $this->_securityToken );
        $_soapClient = $_builder->build();
        if ( !isset( $_soapClient ) )
        {
            throw new InternalServerErrorException( 'Failed to build session with Salesforce.' );
        }

        $_result = $_soapClient->getLoginResult();
        $this->_sessionCache['server_instance'] = $_result->getServerInstance();
        $this->_sessionCache['session_id'] = $_result->getSessionId();
        Pii::setState( 'service.' . $this->getApiName() . '.cache', $this->_sessionCache );
    }

    protected function _getSessionId()
    {
        $_id = Option::get( $this->_sessionCache, 'session_id' );
        if ( empty( $_id ) )
        {
            $this->_getSoapLoginResult();

            $_id = Option::get( $this->_sessionCache, 'session_id' );
            if ( empty( $_id ) )
            {
                throw new InternalServerErrorException( 'Failed to get session id from Salesforce.' );
            }
        }

        return $_id;
    }

    protected function _getServerInstance()
    {
        $_instance = Option::get( $this->_sessionCache, 'server_instance' );
        if ( empty( $_instance ) )
        {
            $this->_getSoapLoginResult();

            $_instance = Option::get( $this->_sessionCache, 'server_instance' );
            if ( empty( $_instance ) )
            {
                throw new InternalServerErrorException( 'Failed to get server instance from Salesforce.' );
            }
        }

        return $_instance;
    }

    /**
     * Perform call to Salesforce REST API
     *
     * @param string       $method
     * @param string       $uri
     * @param array        $parameters
     * @param mixed        $body
     * @param GuzzleClient $client
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @return array The JSON response as an array
     */
    protected function callGuzzle( $method = 'GET', $uri = null, $parameters = array(), $body = null, $client = null )
    {
        $_options = array();
        try
        {
            if ( !isset( $client ) )
            {
                $client = $this->getGuzzleClient();
            }
            $request = $client->createRequest( $method, $uri, null, $body, $_options );
            $request->setHeader( 'Authorization', 'Bearer ' . $this->_getSessionId() );
            if ( !empty( $body ) )
            {
                $request->setHeader( 'Content-Type', 'application/json' );
            }
            if ( !empty( $parameters ) )
            {
                $request->getQuery()->merge( $parameters );
            }

            $response = $request->send();

            return $response->json();
        }
        catch ( \Guzzle\Http\Exception\BadResponseException $ex )
        {
            $_response = $ex->getResponse();
            $_status = $_response->getStatusCode();
            if ( 401 == $_status )
            {
                // attempt the clear cache and rebuild session
                $this->_sessionCache = array();
                // resend request
                try
                {
                    $client = $client->setBaseUrl( $this->getBaseUrl() );
                    $request = $client->createRequest( $method, $uri, null, $body, $_options );
                    $request->setHeader( 'Authorization', 'Bearer ' . $this->_getSessionId() );
                    if ( !empty( $body ) )
                    {
                        $request->setHeader( 'Content-Type', 'application/json' );
                    }
                    if ( !empty( $parameters ) )
                    {
                        $request->getQuery()->merge( $parameters );
                    }

                    $response = $request->send();

                    return $response->json();
                }
                catch ( \Guzzle\Http\Exception\BadResponseException $ex )
                {
                    $_response = $ex->getResponse();
                    $_status = $_response->getStatusCode();
                    $_error = $_response->json();
                    $_error = Option::get( $_error, 0, array() );
                    $_message = Option::get( $_error, 'message', $_response->getMessage() );
                    $_code = Option::get( $_error, 'errorCode', 'ERROR' );
                    throw new RestException( $_status, $_code . ' ' . $_message );
                }
                catch ( \Exception $ex )
                {
                    throw new InternalServerErrorException( $ex->getMessage(), $ex->getCode() ? : null );
                }

            }

            $_error = $_response->json();
            $_error = Option::get( $_error, 0, array() );
            $_message = Option::get( $_error, 'message', $_response->getMessage() );
            $_code = Option::get( $_error, 'errorCode', 'ERROR' );
            throw new RestException( $_status, $_code . ' ' . $_message );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( $ex->getMessage(), $ex->getCode() ? : null );
        }
    }

    protected function getBaseUrl()
    {
        return sprintf(
            'https://%s.salesforce.com/services/data/%s/',
            $this->_getServerInstance(),
            $this->_version
        );
    }

    /**
     * Get Guzzle client
     *
     * @return \Guzzle\Http\Client
     */
    protected function getGuzzleClient()
    {
        return new GuzzleClient( $this->getBaseUrl() );
    }

    /**
     * Object destructor
     */
    public function __destruct()
    {
    }

    /**
     * @param null|array $post_data
     *
     * @return array
     */
    protected function _gatherExtrasFromRequest( $post_data = null )
    {
        $_extras = parent::_gatherExtrasFromRequest( $post_data );

        // get possible paging parameter for large requests
        $_next = FilterInput::request( 'next' );
        if ( empty( $_next ) && !empty( $post_data ) )
        {
            $_next = Option::get( $post_data, 'next' );
        }
        $_extras['next'] = $_next;

        // continue batch processing if an error occurs, if applicable
        $_continue = FilterInput::request( 'continue', false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
        if ( empty( $_continue ) && !empty( $post_data ) )
        {
            $_continue = Option::getBool( $post_data, 'continue' );
        }
        $_extras['continue'] = $_continue;

        return $_extras;
    }

    // REST service implementation

    /**
     * @throws \Exception
     * @return array
     */
    protected function _getSObjectsArray()
    {
        try
        {
            $_result = $this->callGuzzle( 'GET', 'sobjects/' );

            return Option::get( $_result, 'sobjects' );
        }
        catch ( RestException $ex )
        {
            throw new RestException( $ex->getStatusCode(), "Failed to describe sobjects on Salesforce service.{$ex->getMessage()}" );
        }
    }

    /**
     * @param      $table
     * @param bool $as_array
     *
     * @return array|string
     */
    protected function _getAllFields( $table, $as_array = false )
    {
        $_result = $this->getTable( $table );
        $_result = Option::get( $_result, 'fields' );
        if ( empty( $_result ) )
        {
            return array();
        }

        $_fields = array();
        foreach ( $_result as $_field )
        {
            $_fields[] = Option::get( $_field, 'name' );
        }

        if ( $as_array )
        {
            return $_fields;
        }

        return implode( ',', $_fields );
    }

    /**
     * @throws \Exception
     * @return array
     */
    protected function _listResources()
    {
        $_out = array();
        $_result = $this->_getSObjectsArray();
        foreach ( $_result as $_table )
        {
            $_out[] = array( 'name' => Option::get( $_table, 'name' ), 'label' => Option::get( $_table, 'label' ) );
        }

        return array( 'resource' => $_out );
    }

    /**
     * {@inheritdoc}
     */
    public function getTables( $tables = array() )
    {
        if ( empty( $tables ) )
        {
            return $this->_getSObjectsArray();
        }

        return parent::getTables( $tables );
    }

    /**
     * {@inheritdoc}
     */
    public function getTable( $table )
    {
        if ( is_array( $table ) )
        {
            $table = Option::get( $table, 'name' );
        }
        if ( empty( $table ) )
        {
            throw new BadRequestException( "No 'name' field in data." );
        }

        try
        {
            $result = $this->callGuzzle( 'GET', 'sobjects/' . $table . '/describe' );

            return $result;
        }
        catch ( RestException $ex )
        {
            throw new RestException( $ex->getStatusCode(), "Failed to describe sobject '$table' on Salesforce service. {$ex->getMessage()}" );
        }
    }

    //-------- Table Records Operations ---------------------
    // records is an array of field arrays

    /**
     * {@inheritdoc}
     */
    public function createRecords( $table, $records, $fields = null, $extras = array() )
    {
        if ( empty( $records ) || !is_array( $records ) )
        {
            throw new BadRequestException( 'There are no record sets in the request.' );
        }
        if ( !isset( $records[0] ) )
        {
            // single record possibly passed in without wrapper array
            $records = array( $records );
        }

        $_isSingle = ( 1 == count( $records ) );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_idField = Option::get( $extras, 'id_field' );
        if ( empty( $_idField ) )
        {
            $_idField = static::DEFAULT_ID_FIELD;
        }

        $_ids = array();
        $_errors = array();
        $_client = $this->getGuzzleClient();

        foreach ( $records as $_key => $_record )
        {
            try
            {
                $_result = $this->callGuzzle( 'POST', 'sobjects/' . $table . '/', null, json_encode( $_record ), $_client );
                if ( !Option::getBool( $_result, 'success', false ) )
                {
                    $_msg = json_encode( Option::get( $_result, 'errors' ) );
                    throw new InternalServerErrorException( "Record insert failed for table '$table'.\n" . $_msg );
                }
                $_ids[$_key] = Option::get( $_result, 'id' );
            }
            catch ( \Exception $ex )
            {
                if ( $_isSingle )
                {
                    throw $ex;
                }

                $_errors[] = $_key;
                $_ids[$_key] = $ex->getMessage();
                if ( !$_continue )
                {
                    break;
                }
            }
        }

        if ( !empty( $_errors ) )
        {
            $_msg = array( 'errors' => $_errors, 'ids' => $_ids );
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
        }

        $_results = array();
        if ( !static::_requireMoreFields( $fields, $_idField ) )
        {
            foreach ( $_ids as $_id )
            {
                $_results[] = array( $_idField => $_id );
            }
        }
        else
        {
            $_results = $this->retrieveRecordsByIds( $table, $_ids, $fields, $extras );
        }

        return $_results;
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecords( $table, $records, $fields = null, $extras = array() )
    {
        if ( empty( $records ) || !is_array( $records ) )
        {
            throw new BadRequestException( 'There are no record sets in the request.' );
        }
        if ( !isset( $records[0] ) )
        {
            // single record possibly passed in without wrapper array
            $records = array( $records );
        }

        $_isSingle = ( 1 == count( $records ) );
        $_continue = Option::getBool( $extras, 'continue', false );
        $_idField = Option::get( $extras, 'id_field' );
        if ( empty( $_idField ) )
        {
            $_idField = static::DEFAULT_ID_FIELD;
        }

        $_ids = array();
        $_errors = array();
        $_client = $this->getGuzzleClient();

        foreach ( $records as $_key => $_record )
        {
            try
            {
                $_id = Option::get( $_record, $_idField );
                if ( empty( $_id ) )
                {
                    throw new BadRequestException( "Identifying field '$_idField' can not be empty for update record [$_key] request." );
                }

                $_record = Utilities::removeOneFromArray( $_idField, $_record );

                $_result = $this->callGuzzle( 'PATCH', 'sobjects/' . $table . '/' . $_id, null, json_encode( $_record ), $_client );
                if ( $_result && !Option::getBool( $_result, 'success', false ) )
                {
                    $msg = Option::get( $_result, 'errors' );
                    throw new InternalServerErrorException( "Record update failed for table '$table'.\n" . $msg );
                }
                $_ids[$_key] = $_id;
            }
            catch ( \Exception $ex )
            {
                if ( $_isSingle )
                {
                    throw $ex;
                }

                $_errors[] = $_key;
                $_ids[$_key] = $ex->getMessage();
                if ( !$_continue )
                {
                    break;
                }
            }
        }

        if ( !empty( $_errors ) )
        {
            $_msg = array( 'errors' => $_errors, 'ids' => $_ids );
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
        }

        $_results = array();
        if ( !static::_requireMoreFields( $fields, $_idField ) )
        {
            foreach ( $_ids as $_id )
            {
                $_results[] = array( $_idField => $_id );
            }
        }
        else
        {
            $_results = $this->retrieveRecordsByIds( $table, $_ids, $fields, $extras );
        }

        return $_results;
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecordsByFilter( $table, $record, $filter = null, $fields = null, $extras = array() )
    {
        if ( !is_array( $record ) || empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record.' );
        }

        if ( empty( $filter ) )
        {
            throw new BadRequestException( "Filter for delete request can not be empty." );
        }

        $_records = $this->retrieveRecordsByFilter( $table, $filter, null, $extras );

        $_idField = Option::get( $extras, 'id_field' );
        if ( empty( $_idField ) )
        {
            $_idField = static::DEFAULT_ID_FIELD;
        }
        $_ids = array();

        foreach ( $_records as $_key => $_record )
        {
            $_id = Option::get( $_record, $_idField );
            if ( empty( $_id ) )
            {
                throw new BadRequestException( "Identifying field '$_idField' can not be empty for update record [$_key] request." );
            }

            $_ids[$_key] = $_id;
        }

        return $this->updateRecordsByIds( $table, $record, $_ids, $fields, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function updateRecordsByIds( $table, $record, $ids, $fields = null, $extras = array() )
    {
        if ( !is_array( $record ) || empty( $record ) )
        {
            throw new BadRequestException( "No record fields were passed in the request." );
        }

        $_client = $this->getGuzzleClient();
        $_continue = Option::getBool( $extras, 'continue', false );
        $_idField = Option::get( $extras, 'id_field' );
        if ( empty( $_idField ) )
        {
            $_idField = static::DEFAULT_ID_FIELD;
        }

        if ( !is_array( $ids ) )
        {
            $ids = array_map( 'trim', explode( ',', trim( $ids, ',' ) ) );
        }
        if ( empty( $ids ) )
        {
            throw new BadRequestException( "Identifying values for '$_idField' can not be empty for update request." );
        }

        $_isSingle = ( 1 == count( $ids ) );

        try
        {
            $record = Utilities::removeOneFromArray( $_idField, $record );

            $_errors = array();

            foreach ( $ids as $_key => $_id )
            {
                try
                {
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '$_idField' can not be empty for update record [$_key] request." );
                    }

                    $_result = $this->callGuzzle( 'PATCH', 'sobjects/' . $table . '/' . $_id, null, json_encode( $record ), $_client );
                    if ( $_result && !Option::getBool( $_result, 'success', false ) )
                    {
                        $msg = Option::get( $_result, 'errors' );
                        throw new InternalServerErrorException( "Record update failed for table '$table'.\n" . $msg );
                    }
                }
                catch ( \Exception $ex )
                {
                    if ( $_isSingle )
                    {
                        throw $ex;
                    }

                    $_errors[] = $_key;
                    $ids[$_key] = $ex->getMessage();
                    if ( !$_continue )
                    {
                        break;
                    }
                }
            }

            if ( !empty( $_errors ) )
            {
                $_msg = array( 'errors' => $_errors, 'ids' => $ids );
                throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
            }

            $_results = array();
            if ( !static::_requireMoreFields( $fields, $_idField ) )
            {
                foreach ( $ids as $_id )
                {
                    $_results[] = array( $_idField => $_id );
                }
            }
            else
            {
                $_results = $this->retrieveRecordsByIds( $table, $ids, $fields, $extras );
            }

            return $_results;
        }
        catch ( \Exception $ex )
        {
            throw $ex;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecords( $table, $records, $fields = null, $extras = array() )
    {
        // currently the same as update here
        return $this->updateRecords( $table, $records, $fields, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecordsByFilter( $table, $record, $filter = null, $fields = null, $extras = array() )
    {
        // currently the same as update here
        return $this->updateRecordsByFilter( $table, $record, $filter, $fields, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function mergeRecordsByIds( $table, $record, $ids, $fields = null, $extras = array() )
    {
        // currently the same as update here
        return $this->updateRecordsByIds( $table, $record, $ids, $fields, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecords( $table, $records, $fields = null, $extras = array() )
    {
        if ( !is_array( $records ) || empty( $records ) )
        {
            throw new BadRequestException( 'There are no record sets in the request.' );
        }
        if ( !isset( $records[0] ) )
        {
            // single record possibly passed in without wrapper array
            $records = array( $records );
        }

        $_idField = Option::get( $extras, 'id_field' );
        if ( empty( $_idField ) )
        {
            $_idField = static::DEFAULT_ID_FIELD;
        }

        $_ids = array();
        foreach ( $records as $_key => $_record )
        {
            $_id = Option::get( $_record, $_idField );
            if ( empty( $_id ) )
            {
                throw new BadRequestException( "Identifying field '$_idField' can not be empty for delete record [$_key] request." );
            }
            $_ids[$_key] = $_id;
        }

        return $this->deleteRecordsByIds( $table, $_ids, $fields, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecordsByFilter( $table, $filter, $fields = null, $extras = array() )
    {
        if ( empty( $filter ) )
        {
            throw new BadRequestException( "Filter for delete request can not be empty." );
        }

        $_records = $this->retrieveRecordsByFilter( $table, $filter, null, $extras );

        return $this->deleteRecords( $table, $_records, $fields, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecordsByIds( $table, $ids, $fields = null, $extras = array() )
    {
        $_client = $this->getGuzzleClient();
        $_continue = Option::getBool( $extras, 'continue', false );
        $_idField = Option::get( $extras, 'id_field' );
        if ( empty( $_idField ) )
        {
            $_idField = static::DEFAULT_ID_FIELD;
        }

        if ( !is_array( $ids ) )
        {
            $ids = array_map( 'trim', explode( ',', trim( $ids, ',' ) ) );
        }
        if ( empty( $ids ) )
        {
            throw new BadRequestException( "Identifying values for '$_idField' can not be empty for delete request." );
        }

        $_isSingle = ( 1 == count( $ids ) );

        try
        {
            $_errors = array();

            // get the returnable fields first, then issue delete
            $_outResults = array();
            if ( static::_requireMoreFields( $fields, $_idField ) )
            {
                $_outResults = $this->retrieveRecordsByIds( $table, $ids, $fields, $extras );
            }

            foreach ( $ids as $_key => $_id )
            {
                try
                {
                    if ( empty( $_id ) )
                    {
                        throw new BadRequestException( "Identifying field '$_idField' can not be empty for delete record [$_key] request." );
                    }

                    $_result = $this->callGuzzle( 'DELETE', 'sobjects/' . $table . '/' . $_id, null, null, $_client );
                    if ( $_result && !Option::getBool( $_result, 'success', false ) )
                    {
                        $msg = Option::get( $_result, 'errors' );
                        throw new InternalServerErrorException( "Record delete failed for table '$table'.\n" . $msg );
                    }
                }
                catch ( \Exception $ex )
                {
                    if ( $_isSingle )
                    {
                        throw $ex;
                    }

                    $_errors[] = $_key;
                    $ids[$_key] = $ex->getMessage();
                    if ( !$_continue )
                    {
                        break;
                    }
                }
            }

            if ( !empty( $_errors ) )
            {
                $_msg = array( 'errors' => $_errors, 'ids' => $ids );
                throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $_msg );
            }

            $_results = array();
            if ( !static::_requireMoreFields( $fields, $_idField ) )
            {
                foreach ( $ids as $_id )
                {
                    $_results[] = array( $_idField => $_id );
                }
            }
            else
            {
                $_results = $_outResults;
            }

            return $_results;
        }
        catch ( \Exception $ex )
        {
            throw $ex;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByFilter( $table, $filter = null, $fields = null, $extras = array() )
    {
        $_idField = Option::get( $extras, 'id_field' );
        $fields = $this->_buildFieldList( $table, $fields, $_idField );

        $_next = Option::get( $extras, 'next' );
        if ( !empty( $_next ) )
        {
            $_result = $this->callGuzzle( 'GET', 'query/' . $_next );
        }
        else
        {
            // build query string
            $_query = 'SELECT ' . $fields . ' FROM ' . $table;

            if ( !empty( $filter ) )
            {
                $_query .= ' WHERE ' . $filter;
            }

            $_order = Option::get( $extras, 'order' );
            if ( !empty( $_order ) )
            {
                $_query .= ' ORDER BY ' . $_order;
            }

            $_offset = intval( Option::get( $extras, 'offset', 0 ) );
            if ( $_offset > 0 )
            {
                $_query .= ' OFFSET ' . $_offset;
            }

            $_limit = intval( Option::get( $extras, 'limit', 0 ) );
            if ( $_limit > 0 )
            {
                $_query .= ' LIMIT ' . $_limit;
            }

            $_result = $this->callGuzzle( 'GET', 'query', array( 'q' => $_query ) );
        }

        $_data = Option::get( $_result, 'records', array() );

        $_includeCount = Option::getBool( $extras, 'include_count', false );
        $_moreToken = Option::get( $_result, 'nextRecordsUrl' );
        if ( $_includeCount || $_moreToken )
        {
            // count total records
            $_data['meta']['count'] = intval( Option::get( $_result, 'totalSize' ) );
            if ( $_moreToken )
            {
                $_data['meta']['next'] = substr( $_moreToken, strrpos( $_moreToken, '/' ) + 1 );
            }
        }

        return $_data;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecords( $table, $records, $fields = null, $extras = array() )
    {
        if ( empty( $records ) || !is_array( $records ) )
        {
            throw new BadRequestException( 'There are no record sets in the request.' );
        }
        if ( !isset( $records[0] ) )
        {
            // single record
            $records = array( $records );
        }

        $_idField = Option::get( $extras, 'id_field' );
        if ( empty( $_idField ) )
        {
            $_idField = static::DEFAULT_ID_FIELD;
        }

        $_ids = array();
        foreach ( $records as $_key => $_record )
        {
            $_id = Option::get( $_record, $_idField );
            if ( empty( $_id ) )
            {
                throw new BadRequestException( "Identifying field '$_idField' can not be empty for retrieve record [$_key] request." );
            }
            $_ids[] = $_id;
        }

        return $this->retrieveRecordsByIds( $table, $_ids, $fields, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecord( $table, $record, $fields = null, $extras = array() )
    {
        if ( !is_array( $record ) || empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record.' );
        }

        $_idField = Option::get( $extras, 'id_field' );
        if ( empty( $_idField ) )
        {
            $_idField = static::DEFAULT_ID_FIELD;
        }

        $_id = Option::get( $record, $_idField );
        if ( empty( $_id ) )
        {
            throw new BadRequestException( "Identifying field '$_idField' can not be empty for retrieve record request." );
        }

        return $this->retrieveRecordById( $table, $_id, $fields, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByIds( $table, $ids, $fields = null, $extras = array() )
    {
        if ( empty( $ids ) )
        {
            return array();
        }

        if ( !is_array( $ids ) )
        {
            $ids = array_map( 'trim', explode( ',', $ids ) );
        }

        $_idField = Option::get( $extras, 'id_field' );
        if ( empty( $_idField ) )
        {
            $_idField = static::DEFAULT_ID_FIELD;
        }

        $fields = $this->_buildFieldList( $table, $fields, $_idField );

        $_idList = "('" . implode( "','", $ids ) . "')";
        $_query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE ' . $_idField . ' IN ' . $_idList;

        $_result = $this->callGuzzle( 'GET', 'query', array( 'q' => $_query ) );

        $_data = Option::get( $_result, 'records', array() );

        return $_data;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordById( $table, $id, $fields = null, $extras = array() )
    {
        if ( empty( $id ) )
        {
            return array();
        }

        $_idField = Option::get( $extras, 'id_field' );
        $fields = $this->_buildFieldList( $table, $fields, $_idField );

        $_result = $this->callGuzzle( 'GET', 'sobjects/' . $table . '/' . $id, array( 'fields' => $fields ) );

        if ( empty( $_result ) )
        {
            throw new NotFoundException( "Record with id '$id' was not found." );
        }

        return $_result;
    }

    // Handle administrative options, table add, delete, etc

    /**
     * Create one or more tables by array of table properties
     *
     * @param array $tables
     *
     * @return array
     * @throws \Exception
     */
    public function createTables( $tables = array() )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    /**
     * Create a single table by name, additional properties
     *
     * @param array $properties
     *
     * @throws \Exception
     */
    public function createTable( $properties = array() )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    /**
     * Update properties related to the table
     *
     * @param array $tables
     *
     * @return array
     * @throws \Exception
     */
    public function updateTables( $tables = array() )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    /**
     * Update properties related to the table
     *
     * @param array $properties
     *
     * @return array
     * @throws \Exception
     */
    public function updateTable( $properties = array() )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    /**
     * Delete multiple tables and all of their contents
     *
     * @param array $tables
     * @param bool  $check_empty
     *
     * @return array
     * @throws \Exception
     */
    public function deleteTables( $tables = array(), $check_empty = false )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    /**
     * Delete the table and all of its contents
     *
     * @param string $table
     * @param bool   $check_empty
     *
     * @throws \Exception
     * @return array
     */
    public function deleteTable( $table, $check_empty = false )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    protected function getFieldsInfo( $table )
    {
        return $this->_getAllFields( $table, true );
    }

    protected function getIdsInfo( $table, $fields_info = null, &$requested_fields = null, $requested_types = null )
    {
        $_idsInfo = array();
        if ( empty( $requested_fields ) )
        {
            $requested_fields = array();
            foreach ( $_idsInfo as $_info )
            {
                $requested_fields[] = Option::get( $_info, 'name' );
            }
        }
        else
        {
            if ( false !== $requested_fields = static::validateAsArray( $requested_fields, ',' ) )
            {
                foreach ( $requested_fields as $_field )
                {
                    $_idsInfo[] = array( 'name' => $_field ); // search fields info
                }
            }
        }

        return $_idsInfo;
    }

    /**
     * @param      $table
     * @param null $fields
     * @param null $id_field
     *
     * @return array|null|string
     */
    protected function _buildFieldList( $table, $fields = null, $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            $id_field = static::DEFAULT_ID_FIELD;
        }

        if ( empty( $fields ) )
        {
            $fields = $id_field;
        }
        elseif ( '*' == $fields )
        {
            $fields = $this->_getAllFields( $table );
        }
        else
        {
            if ( is_array( $fields ) )
            {
                $fields = implode( ',', $fields );
            }

            // make sure the Id field is always returned
            if ( false === array_search(
                    strtolower( $id_field ),
                    array_map(
                        'trim',
                        explode( ',', strtolower( $fields ) )
                    )
                )
            )
            {
                $fields = array_map( 'trim', explode( ',', $fields ) );
                $fields[] = $id_field;
                $fields = implode( ',', $fields );
            }
        }

        return $fields;
    }

    /**
     * @param mixed|null $handle
     *
     * @return bool
     */
    protected function initTransaction( $handle = null )
    {
        $this->_collection = $handle;
        $this->_batchRecords = array();
        $this->_rollbackRecords = array();

        return true;
    }

    /**
     * @param mixed      $record
     * @param mixed      $id
     * @param null|array $extras Additional items needed to complete the transaction
     * @param bool       $save_old
     * @param bool       $batch  Request for batch, if applicable
     *
     * @throws \DreamFactory\Platform\Exceptions\NotImplementedException
     * @return null|array Array of output fields
     */
    protected function addToTransaction( $record = null, $id = null, $extras = null, $save_old = false, $batch = false )
    {
        $_out = array();
        if ( !empty( $this->_batchRecords ) )
        {
            switch ( $this->getAction() )
            {
                case static::POST:
                    break;
                case static::PUT:
                    break;

                case static::MERGE:
                case static::PATCH:
                    break;

                case static::DELETE:
                    break;

                default:
                    break;
            }

            $this->_batchRecords = array();
        }

        return $_out;
    }

    /**
     * @param null|array $extras
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    protected function commitTransaction( $extras = null )
    {
        $_out = array();
        if ( !empty( $this->_batchRecords ) )
        {
            switch ( $this->getAction() )
            {
                case static::POST:
                    break;
                case static::PUT:
                    break;

                case static::MERGE:
                case static::PATCH:
                    break;

                case static::DELETE:
                    break;

                default:
                    break;
            }

            $this->_batchRecords = array();
        }

        return $_out;
    }

    /**
     * @param mixed $record
     *
     * @return bool
     */
    protected function addToRollback( $record )
    {
        $this->_rollbackRecords[] = $record;

        return true;
    }

    /**
     * @return bool
     */
    protected function rollbackTransaction()
    {
        if ( !empty( $this->_rollbackRecords ) )
        {
            switch ( $this->getAction() )
            {
                case static::POST:
                    break;

                case static::PUT:
                case static::PATCH:
                case static::MERGE:
                case static::DELETE:
                    break;

                default:
                    break;
            }

            $this->_rollbackRecords = array();
        }

        return true;
    }
}
