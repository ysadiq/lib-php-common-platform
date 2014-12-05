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
use DreamFactory\Yii\Utility\Pii;
use Guzzle\Http\Client as GuzzleClient;
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

        $_credentials = Option::get( $config, 'credentials' );
        Session::replaceLookups( $_credentials, true );

        $this->_username = Option::get( $_credentials, 'username' );
        $this->_password = Option::get( $_credentials, 'password' );
        $this->_securityToken = Option::get( $_credentials, 'security_token' );
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

    /**
     * Object destructor
     */
    public function __destruct()
    {
    }

    // REST service implementation

    /**
     * @param bool $list_only
     *
     * @return array
     */
    protected function _getSObjectsArray( $list_only = false )
    {
        $_result = $this->callGuzzle( 'GET', 'sobjects/' );

        $_tables = Option::clean( Option::get( $_result, 'sobjects' ) );
        if ( $list_only )
        {
            $_out = array();
            foreach ( $_tables as $_table )
            {
                $_out[] = Option::get( $_table, 'name' );
            }

            return $_out;
        }

        return $_tables;
    }

    /**
     * {@inheritdoc}
     */
    protected function _listTables( /** @noinspection PhpUnusedParameterInspection */ $refresh = true )
    {
        return $this->_getSObjectsArray();
    }

    // Handle administrative options, table add, delete, etc

    /**
     * {@inheritdoc}
     */
    public function correctTableName( &$name  )
    {
        static $_existing = null;

        if ( !$_existing )
        {
            $_existing = $this->_getSObjectsArray( true );
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

    /**
     * {@inheritdoc}
     */
    public function describeTable( $table, $refresh = false )
    {
        $_name = ( is_array( $table ) ) ? Option::get( $table, 'name' ) : $table;

        try
        {
            $result = $this->callGuzzle( 'GET', 'sobjects/' . $table . '/describe' );

            $_out = $result;
            $_out['access'] = $this->getPermissions( $_name );

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            throw new InternalServerErrorException( "Failed to get table properties for table '$_name'.\n{$_ex->getMessage(
            )}" );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function describeField( $table, $field, $refresh = false )
    {
        $_result = $this->describeTable( $table );
        $_fields = Option::get( $_result, 'fields' );
        if ( empty( $_fields ) )
        {
            foreach ( $_fields as $_item )
            {
                if ( Option::get( $_item, 'name' ) == $field )
                {
                    return $_item;
                }
            }
        }

        throw new NotFoundException( "Field '$field' not found." );
    }

    /**
     * {@inheritdoc}
     */
    public function createTable( $table, $properties = array(), $check_exist = false, $return_schema = false )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    /**
     * {@inheritdoc}
     */
    public function updateTable( $table, $properties = array(), $allow_delete_fields = false, $return_schema = false )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTable( $table, $check_empty = false )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    /**
     * {@inheritdoc}
     */
    public function createField( $table, $field, $properties = array(), $check_exist = false, $return_schema = false )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    /**
     * {@inheritdoc}
     */
    public function updateField( $table, $field, $properties = array(), $allow_delete_parts = false, $return_schema = false )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteField( $table, $field )
    {
        throw new NotImplementedException( "Metadata actions currently not supported." );
    }

    //-------- Table Records Operations ---------------------
    // records is an array of field arrays

    /**
     * {@inheritdoc}
     */
    public function patchRecords( $table, $records, $extras = array() )
    {
        // currently the same as update here
        return $this->updateRecords( $table, $records, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function patchRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        // currently the same as update here
        return $this->updateRecordsByFilter( $table, $record, $filter, $params, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function patchRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        // currently the same as update here
        return $this->updateRecordsByIds( $table, $record, $ids, $extras );
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() )
    {
        $_fields = Option::get( $extras, 'fields' );
        $_idField = Option::get( $extras, 'id_field' );
        $fields = $this->_buildFieldList( $table, $_fields, $_idField );

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

            $_result = $this->callGuzzle( 'GET', 'query', array('q' => $_query) );
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

    // Helper functions

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

    protected function getFieldsInfo( $table )
    {
        $_result = $this->describeTable( $table );
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

        return $_result;
    }

    protected function getIdsInfo( $table, $fields_info = null, &$requested_fields = null, $requested_types = null )
    {
        $requested_fields = static::DEFAULT_ID_FIELD; // can only be this
        $requested_types = Option::clean( $requested_types );
        $_type = Option::get( $requested_types, 0, 'string' );
        $_type = ( empty( $_type ) ) ? 'string' : $_type;

        return array(array('name' => static::DEFAULT_ID_FIELD, 'type' => $_type, 'required' => false));
    }

    /**
     * @param      $table
     * @param bool $as_array
     *
     * @return array|string
     */
    protected function _getAllFields( $table, $as_array = false )
    {
        $_result = $this->describeTable( $table );
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
     * {@inheritdoc}
     */
    protected function initTransaction( $handle = null )
    {

        return parent::initTransaction( $handle );
    }

    /**
     * {@inheritdoc}
     */
    protected function addToTransaction( $record = null, $id = null, $extras = null, $rollback = false, $continue = false, $single = false )
    {
        $_fields = Option::get( $extras, 'fields' );
        $_fieldsInfo = Option::get( $extras, 'fields_info' );
        $_ssFilters = Option::get( $extras, 'ss_filters' );
        $_updates = Option::get( $extras, 'updates' );
        $_idsInfo = Option::get( $extras, 'ids_info' );
        $_idFields = Option::get( $extras, 'id_fields' );
        $_needToIterate = ( $single || $continue || ( 1 < count( $_idsInfo ) ) );
        $_requireMore = Option::getBool( $extras, 'require_more' );

        $_client = $this->getGuzzleClient();

        $_out = array();
        switch ( $this->getAction() )
        {
            case static::POST:
                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                $_native = json_encode( $_parsed );
                $_result =
                    $this->callGuzzle( 'POST', 'sobjects/' . $this->_transactionTable . '/', null, $_native, $_client );
                if ( !Option::getBool( $_result, 'success', false ) )
                {
                    $_msg = json_encode( Option::get( $_result, 'errors' ) );
                    throw new InternalServerErrorException( "Record insert failed for table '$this->_transactionTable'.\n" .
                                                            $_msg );
                }

                $id = Option::get( $_result, 'id' );

                // add via record, so batch processing can retrieve extras
                return ( $_requireMore ) ? parent::addToTransaction( $id ) : array($_idFields => $id);

            case static::PUT:
            case static::MERGE:
            case static::PATCH:
                if ( !empty( $_updates ) )
                {
                    $record = $_updates;
                }

                $_parsed = $this->parseRecord( $record, $_fieldsInfo, $_ssFilters, true );
                if ( empty( $_parsed ) )
                {
                    throw new BadRequestException( 'No valid fields were found in record.' );
                }

                static::removeIds( $_parsed, $_idFields );
                $_native = json_encode( $_parsed );

                $_result = $this->callGuzzle(
                    'PATCH',
                    'sobjects/' . $this->_transactionTable . '/' . $id,
                    null,
                    $_native,
                    $_client
                );
                if ( $_result && !Option::getBool( $_result, 'success', false ) )
                {
                    $msg = Option::get( $_result, 'errors' );
                    throw new InternalServerErrorException( "Record update failed for table '$this->_transactionTable'.\n" .
                                                            $msg );
                }

                // add via record, so batch processing can retrieve extras
                return ( $_requireMore ) ? parent::addToTransaction( $id ) : array($_idFields => $id);

            case static::DELETE:
                $_result = $this->callGuzzle(
                    'DELETE',
                    'sobjects/' . $this->_transactionTable . '/' . $id,
                    null,
                    null,
                    $_client
                );
                if ( $_result && !Option::getBool( $_result, 'success', false ) )
                {
                    $msg = Option::get( $_result, 'errors' );
                    throw new InternalServerErrorException( "Record delete failed for table '$this->_transactionTable'.\n" .
                                                            $msg );
                }

                // add via record, so batch processing can retrieve extras
                return ( $_requireMore ) ? parent::addToTransaction( $id ) : array($_idFields => $id);

            case static::GET:
                if ( !$_needToIterate )
                {
                    return parent::addToTransaction( null, $id );
                }

                $_fields = $this->_buildFieldList( $this->_transactionTable, $_fields, $_idFields );

                $_result = $this->callGuzzle(
                    'GET',
                    'sobjects/' . $this->_transactionTable . '/' . $id,
                    array('fields' => $_fields)
                );
                if ( empty( $_result ) )
                {
                    throw new NotFoundException( "Record with identifier '" . print_r( $id, true ) . "' not found." );
                }

                $_out = $_result;
                break;
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
     */
    protected function commitTransaction( $extras = null )
    {
        if ( empty( $this->_batchRecords ) && empty( $this->_batchIds ) )
        {
            if ( isset( $this->_transaction ) )
            {
                $this->_transaction->commit();
            }

            return null;
        }

        $_fields = Option::get( $extras, 'fields' );
        $_idsInfo = Option::get( $extras, 'ids_info' );
        $_idFields = Option::get( $extras, 'id_fields' );

        $_out = array();
        $_action = $this->getAction();
        if ( !empty( $this->_batchRecords ) )
        {
            if ( 1 == count( $_idsInfo ) )
            {
                // records are used to retrieve extras
                // ids array are now more like records
                $_fields = $this->_buildFieldList( $this->_transactionTable, $_fields, $_idFields );

                $_idList = "('" . implode( "','", $this->_batchRecords ) . "')";
                $_query =
                    'SELECT ' .
                    $_fields .
                    ' FROM ' .
                    $this->_transactionTable .
                    ' WHERE ' .
                    $_idFields .
                    ' IN ' .
                    $_idList;

                $_result = $this->callGuzzle( 'GET', 'query', array('q' => $_query) );

                $_out = Option::get( $_result, 'records', array() );
                if ( empty( $_out ) )
                {
                    throw new NotFoundException( 'No records were found using the given identifiers.' );
                }
            }
            else
            {
                $_out = $this->retrieveRecords( $this->_transactionTable, $this->_batchRecords, $extras );
            }

            $this->_batchRecords = array();
        }
        elseif ( !empty( $this->_batchIds ) )
        {
            switch ( $_action )
            {
                case static::PUT:
                case static::MERGE:
                case static::PATCH:
                    break;

                case static::DELETE:
                    break;

                case static::GET:
                    $_fields = $this->_buildFieldList( $this->_transactionTable, $_fields, $_idFields );

                    $_idList = "('" . implode( "','", $this->_batchIds ) . "')";
                    $_query =
                        'SELECT ' .
                        $_fields .
                        ' FROM ' .
                        $this->_transactionTable .
                        ' WHERE ' .
                        $_idFields .
                        ' IN ' .
                        $_idList;

                    $_result = $this->callGuzzle( 'GET', 'query', array('q' => $_query) );

                    $_out = Option::get( $_result, 'records', array() );
                    if ( empty( $_out ) )
                    {
                        throw new NotFoundException( 'No records were found using the given identifiers.' );
                    }

                    break;

                default:
                    break;
            }

            if ( empty( $_out ) )
            {
                $_out = $this->_batchIds;
            }

            $this->_batchIds = array();
        }

        return $_out;
    }

    /**
     * {@inheritdoc}
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
