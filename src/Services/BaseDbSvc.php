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

use DreamFactory\Platform\Enums\DbFilterOperators;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Interfaces\ServiceOnlyResourceLike;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\DataFormatter;
use DreamFactory\Platform\Utility\DbUtilities;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Option;

/**
 * BaseDbSvc
 *
 * A base service class to handle generic db services accessed through the REST API.
 */
abstract class BaseDbSvc extends BasePlatformRestService implements ServiceOnlyResourceLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default maximum records returned on filter request
     */
    const MAX_RECORDS_RETURNED = 1000;
    /**
     * Default record wrapping tag for single or array of records
     */
    const RECORD_WRAPPER = 'record';
    /**
     * Resource tag for dealing with table schema
     */
    const SCHEMA_RESOURCE = '_schema';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var int|string
     */
    protected $_resourceId = null;
    /**
     * @var boolean
     */
    protected $_useBlendFormat = true;
    /**
     * @var string
     */
    protected $_transactionTable = null;
    /**
     * @var array
     */
    protected $_batchIds = array();
    /**
     * @var array
     */
    protected $_batchRecords = array();
    /**
     * @var array
     */
    protected $_rollbackRecords = array();
    /**
     * This is a check for clients passing single records not wrapped with 'record'
     * but passed to request handlers that expect it. Remove in 2.0.
     *
     * @var boolean
     */
    protected $_singleRecordAmnesty = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct( $settings = array() )
    {
        if ( null === Option::get( $settings, 'verb_aliases' ) )
        {
            //	Default verb aliases
            $settings['verb_aliases'] = array(
                static::MERGE => static::PATCH,
            );
        }

        parent::__construct( $settings );
    }

    /**
     * {@InheritDoc}
     */
    protected function _detectResourceMembers( $resourcePath = null )
    {
        parent::_detectResourceMembers( $resourcePath );

        $this->_resourceId = Option::get( $this->_resourceArray, 1 );
    }

    /**
     * {@InheritDoc}
     *
     * IMPORTANT: The representation of the data will be placed back into the original location/position in the $record from which it was "normalized".
     * This means that any client-side handlers will have to deal with the bogus determinations. Just be aware.
     *
     * Below is a side-by-side comparison of record data as shown sent by or returned to the caller, and sent to an event handler.
     *
     *  REST API v1.0                           Event Representation
     *  -------------                           --------------------
     *  Single row...                           Add a 'record' key and make it look like a multi-row
     *
     *      array(                              array(
     *          'id' => 1,                          'record' => array(
     *      )                                           0 => array( 'id' => 1, ),
     *                                              ),
     *                                          ),
     *
     * Multi-row...                             Stays the same...or gets wrapped by adding a 'record' key
     *
     *      array(                              array(
     *          'record' => array(                  'record' =>  array(
     *              0 => array( 'id' => 1 ),            0 => array( 'id' => 1 ),
     *              1 => array( 'id' => 2 ),            1 => array( 'id' => 2 ),
     *              2 => array( 'id' => 3 ),            2 => array( 'id' => 3 ),
     *          ),                                  ),
     *      )                                   )
     *
     * or...
     *
     *      array(                              array(
     *          0 => array( 'id' => 1 ),            'record' =>  array(
     *          1 => array( 'id' => 2 ),                0 => array( 'id' => 1 ),
     *          2 => array( 'id' => 3 ),                1 => array( 'id' => 2 ),
     *      ),                                          2 => array( 'id' => 3 ),
     *                                              ),
     *                                          )
     */
    protected function _detectRequestMembers()
    {
        // override - don't call parent class here
        $_posted = Option::clean( RestData::getPostedData( true, true ) );

        if ( $this->resourceIsTable( $this->_resource ) )
        {
            if ( !empty( $this->_resourceId ) )
            {
                if ( !empty( $_posted ) )
                {
                    // single records don't use the record wrapper, so wrap it
                    $_posted = array(static::RECORD_WRAPPER => array($_posted));
                }
            }
            elseif ( DataFormatter::isArrayNumeric( $_posted ) )
            {
                // import from csv, etc doesn't include a wrapper, so wrap it
                $_posted = array(static::RECORD_WRAPPER => $_posted);
            }
            else
            {
                switch ( $this->_action )
                {
                    case static::POST:
                    case static::PUT:
                    case static::PATCH:
                    case static::MERGE:
                        // fix wrapper on posted single record
                        if ( !isset( $_posted[static::RECORD_WRAPPER] ) )
                        {
                            $this->_singleRecordAmnesty = true;
                            if ( !empty( $_posted ) )
                            {
                                // stuff it back in for event
                                $_posted[static::RECORD_WRAPPER] = array($_posted);
                            }
                        }
                        break;
                }
            }

            // MERGE URL parameters with posted data, posted data takes precedence
            $this->_requestPayload = array_merge( $_REQUEST, $_posted );

            if ( static::GET == $this->_action )
            {
                // default for GET should be "return all fields"
                if ( !isset( $this->_requestPayload['fields'] ) )
                {
                    $this->_requestPayload['fields'] = '*';
                }
            }

            // Add server side filtering properties
            if ( null != $_ssFilters = Session::getServiceFilters( $this->_action, $this->_apiName, $this->_resource ) )
            {
                $this->_requestPayload['ss_filters'] = $_ssFilters;
            }

            // look for limit, accept top as well as limit
            if ( !isset( $this->_requestPayload['limit'] ) && ( $_limit = Option::get( $this->_requestPayload, 'top' ) )
            )
            {
                $this->_requestPayload['limit'] = $_limit;
            }

            // accept skip as well as offset
            if ( !isset( $this->_requestPayload['offset'] ) && ( $_offset = Option::get( $this->_requestPayload, 'skip' ) )
            )
            {
                $this->_requestPayload['offset'] = $_offset;
            }

            // accept sort as well as order
            if ( !isset( $this->_requestPayload['order'] ) && ( $_order = Option::get( $this->_requestPayload, 'sort' ) )
            )
            {
                $this->_requestPayload['order'] = $_order;
            }
        }
        else
        {
            // admin/schema/etc. requests
            // MERGE URL parameters with posted data, posted data takes precedence
            $this->_requestPayload = array_merge( $_REQUEST, $_posted );
        }

        return $this;
    }

    /**
     * @param $resource
     *
     * @return bool
     */
    protected function resourceIsTable( $resource )
    {
        return !( empty( $resource ) || static::SCHEMA_RESOURCE == $resource );
    }

    /**
     * @param string $name
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return string
     */
    public function correctTableName( &$name )
    {
        return $name;
    }

    /**
     * @param string $main   Main resource or empty for service
     * @param string $sub    Subtending resources if applicable
     * @param string $action Action to validate permission
     */
    protected function validateResourceAccess( &$main, &$sub, $action )
    {
        $_resource = null;
        if ( !empty( $main ) )
        {
            switch ( $main )
            {
                case static::SCHEMA_RESOURCE:
                    $_resource = rtrim( $main, '/' ) . '/';
                    if ( !empty( $sub ) )
                    {
                        $_resource .= $this->correctTableName( $sub );
                    }
                    break;
                default:
                    $_resource = $this->correctTableName( $main );
                    break;
            }

        }

        $this->checkPermission( $action, $_resource );
    }

    /**
     * @param string $table
     * @param string $action
     *
     * @throws BadRequestException
     */
    protected function validateSchemaAccess( $table = null, $action = null )
    {
        $_resource = static::SCHEMA_RESOURCE;
        $this->validateResourceAccess( $_resource, $table, $action );
    }

    /**
     * @param string $table
     * @param string $action
     *
     * @throws BadRequestException
     */
    protected function validateTableAccess( $table, $action = null )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        // finally check that the current user has privileges to access this table
        $_sub = null;
        $this->validateResourceAccess( $table, $_sub, $action );
    }

    /**
     * {@InheritDoc}
     */
    protected function _preProcess()
    {
        //	Do validation here
        $this->validateResourceAccess( $this->_resource, $this->_resourceId, $this->_action );

        parent::_preProcess();
    }

    /**
     * @param bool $refresh
     *
     * @return array
     */
    protected function _listTables( /** @noinspection PhpUnusedParameterInspection */ $refresh = true )
    {
        return array();
    }

    /**
     * @return array|bool
     */
    protected function _handleResource()
    {
        if ( empty( $this->_resource ) )
        {
            switch ( $this->_action )
            {
                case static::GET:
                    $_result = $this->retrieveResources( $this->_requestPayload );

                    $this->_triggerActionEvent( $_result );

                    return array('resource' => $_result);
                default:
                    throw new BadRequestException( 'Currently only GET is supported for this API resource.' );
                    break;
            }
        }

        switch ( $this->_resource )
        {
            case static::SCHEMA_RESOURCE:
                return $this->_handleSchema();
                break;

            default:
                return parent::_handleResource();
                break;
        }
    }

    /**
     * @return array
     */
    protected function _handleGet()
    {
        //	Single resource by ID
        if ( !empty( $this->_resourceId ) )
        {
            $_result = $this->retrieveRecordById( $this->_resource, $this->_resourceId, $this->_requestPayload );
            $this->_triggerActionEvent( $_result );

            return $_result;
        }

        $_ids = Option::get( $this->_requestPayload, 'ids' );

        //	Multiple resources by ID
        if ( !empty( $_ids ) )
        {
            $_result = $this->retrieveRecordsByIds( $this->_resource, $_ids, $this->_requestPayload );
        }
        else
        {
            $_records = Option::get( $this->_requestPayload, static::RECORD_WRAPPER );

            if ( !empty( $_records ) )
            {
                // passing records to have them updated with new or more values, id field required
                $_result = $this->retrieveRecords( $this->_resource, $_records, $this->_requestPayload );
            }
            else
            {
                $_filter = Option::get( $this->_requestPayload, 'filter' );
                $_params = Option::get( $this->_requestPayload, 'params', array() );

                $_result = $this->retrieveRecordsByFilter( $this->_resource, $_filter, $_params, $this->_requestPayload );
            }
        }

        $_meta = Option::get( $_result, 'meta', null, true );
        $_result = array(static::RECORD_WRAPPER => $_result);

        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        $this->_triggerActionEvent( $_result );

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handlePost()
    {
        if ( !empty( $this->_resourceId ) )
        {
            throw new BadRequestException( 'Create record by identifier not currently supported.' );
        }

        $_records = Option::get( $this->_requestPayload, static::RECORD_WRAPPER );
        if ( empty( $_records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->_triggerActionEvent( $this->_response );

        $_result = $this->createRecords( $this->_resource, $_records, $this->_requestPayload );

        if ( $this->_singleRecordAmnesty )
        {
            return Option::get( $_result, 0, $_result );
        }

        $_meta = Option::get( $_result, 'meta', null, true );
        $_result = array(static::RECORD_WRAPPER => $_result);
        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handlePut()
    {
        $_records = Option::get( $this->_requestPayload, static::RECORD_WRAPPER );
        if ( empty( $_records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->_triggerActionEvent( $this->_response );

        if ( !empty( $this->_resourceId ) )
        {
            $_record = Option::get( $_records, 0, $_records );

            return $this->updateRecordById( $this->_resource, $_record, $this->_resourceId, $this->_requestPayload );
        }

        $_ids = Option::get( $this->_requestPayload, 'ids' );

        if ( !empty( $_ids ) )
        {
            $_record = Option::get( $_records, 0, $_records );

            $_result = $this->updateRecordsByIds( $this->_resource, $_record, $_ids, $this->_requestPayload );
        }
        else
        {
            $_filter = Option::get( $this->_requestPayload, 'filter' );
            if ( !empty( $_filter ) )
            {
                $_record = Option::get( $_records, 0, $_records );
                $_params = Option::get( $this->_requestPayload, 'params', array() );
                $_result = $this->updateRecordsByFilter(
                    $this->_resource,
                    $_record,
                    $_filter,
                    $_params,
                    $this->_requestPayload
                );
            }
            else
            {
                $_result = $this->updateRecords( $this->_resource, $_records, $this->_requestPayload );
                if ( $this->_singleRecordAmnesty )
                {
                    return Option::get( $_result, 0 );
                }
            }
        }

        $_meta = Option::get( $_result, 'meta', null, true );
        $_result = array(static::RECORD_WRAPPER => $_result);
        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handlePatch()
    {
        $_records = Option::get( $this->_requestPayload, static::RECORD_WRAPPER );
        if ( empty( $_records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->_triggerActionEvent( $this->_response );

        if ( !empty( $this->_resourceId ) )
        {
            $_record = Option::get( $_records, 0, $_records );

            return $this->patchRecordById( $this->_resource, $_record, $this->_resourceId, $this->_requestPayload );
        }

        $_ids = Option::get( $this->_requestPayload, 'ids' );

        if ( !empty( $_ids ) )
        {
            $_record = Option::get( $_records, 0, $_records );
            $_result = $this->patchRecordsByIds( $this->_resource, $_record, $_ids, $this->_requestPayload );
        }
        else
        {
            $_filter = Option::get( $this->_requestPayload, 'filter' );
            if ( !empty( $_filter ) )
            {
                $_record = Option::get( $_records, 0, $_records );
                $_params = Option::get( $this->_requestPayload, 'params', array() );
                $_result = $this->patchRecordsByFilter(
                    $this->_resource,
                    $_record,
                    $_filter,
                    $_params,
                    $this->_requestPayload
                );
            }
            else
            {
                $_result = $this->patchRecords( $this->_resource, $_records, $this->_requestPayload );
                if ( $this->_singleRecordAmnesty )
                {
                    return Option::get( $_result, 0 );
                }
            }
        }

        $_meta = Option::get( $_result, 'meta', null, true );
        $_result = array(static::RECORD_WRAPPER => $_result);
        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleDelete()
    {
        $this->_triggerActionEvent( $this->_response );

        if ( !empty( $this->_resourceId ) )
        {
            return $this->deleteRecordById( $this->_resource, $this->_resourceId, $this->_requestPayload );
        }

        $_ids = Option::get( $this->_requestPayload, 'ids' );
        if ( !empty( $_ids ) )
        {
            $_result = $this->deleteRecordsByIds( $this->_resource, $_ids, $this->_requestPayload );
        }
        else
        {
            $_records = Option::get( $this->_requestPayload, static::RECORD_WRAPPER );
            if ( !empty( $_records ) )
            {
                $_result = $this->deleteRecords( $this->_resource, $_records, $this->_requestPayload );
            }
            else
            {
                $_filter = Option::get( $this->_requestPayload, 'filter' );
                if ( !empty( $_filter ) )
                {
                    $_params = Option::get( $this->_requestPayload, 'params', array() );
                    $_result = $this->deleteRecordsByFilter( $this->_resource, $_filter, $_params, $this->_requestPayload );
                }
                else
                {
                    if ( !Option::getBool( $this->_requestPayload, 'force' ) )
                    {
                        throw new BadRequestException( 'No filter or records given for delete request.' );
                    }

                    return $this->truncateTable( $this->_resource, $this->_requestPayload );
                }
            }
        }

        $_meta = Option::get( $_result, 'meta', null, true );
        $_result = array(static::RECORD_WRAPPER => $_result);
        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

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
        $_includeSchemas = Option::getBool( $options, 'include_schemas' );
        $_asComponents = Option::getBool( $options, 'as_access_components' );
        $_resources = array();

        if ( $_asComponents )
        {
            $_resources = array('', '*');
        }
        try
        {
            $_result = static::_listTables( $_refresh );
            foreach ( $_result as $_table )
            {
                if ( null != $_name = Option::get( $_table, 'name' ) )
                {
                    $_access = $this->getPermissions( $_name );
                    if ( !empty( $_access ) )
                    {
                        if ( $_asComponents || $_namesOnly )
                        {
                            $_resources[] = $_name;
                        }
                        else
                        {
                            $_table['access'] = $_access;
                            $_resources[] = $_table;
                        }
                    }
                }
            }

            if ( $_includeSchemas || $_asComponents )
            {
                $_name = static::SCHEMA_RESOURCE . '/';
                $_access = $this->getPermissions( $_name );
                if ( !empty( $_access ) )
                {
                    if ( $_namesOnly || $_asComponents )
                    {
                        $_resources[] = $_name;
                        if ( $_asComponents )
                        {
                            $_resources[] = $_name . '*';
                        }
                    }
                    else
                    {
                        $_resources[] = array('name' => $_name, 'access' => $_access);
                    }
                }
                foreach ( $_result as $_table )
                {
                    if ( null != $_name = Option::get( $_table, 'name' ) )
                    {
                        $_name = static::SCHEMA_RESOURCE . '/' . $_name;
                        $_access = $this->getPermissions( $_name );
                        if ( !empty( $_access ) )
                        {
                            if ( $_namesOnly || $_asComponents )
                            {
                                $_resources[] = $_name;
                            }
                            else
                            {
                                $_table['name'] = $_name;
                                $_table['access'] = $_access;
                                $_resources[] = $_table;
                            }
                        }
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

    // Handle table record operations

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function createRecords( $table, $records, $extras = array() )
    {
        $records = DbUtilities::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_isSingle = ( 1 == count( $records ) );
        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_record, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_result = $this->addToTransaction( $_record, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                // operation performed, take output, override earlier
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, static::RECORD_WRAPPER => $_out);
                $_msg = 'Batch Error: Not all records could be created.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to create records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function createRecord( $table, $record, $extras = array() )
    {
        $_records = DbUtilities::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->createRecords( $table, $_records, $extras );

        return $_results[0];
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecords( $table, $records, $extras = array() )
    {
        $records = DbUtilities::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $records ) );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_record, $_idsInfo, $extras ) )
                    {
                        throw new BadRequestException( "Required id field(s) not found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_result = $this->addToTransaction( $_record, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, static::RECORD_WRAPPER => $_out);
                $_msg = 'Batch Error: Not all records could be updated.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to update records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecord( $table, $record, $extras = array() )
    {
        $_records = DbUtilities::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->updateRecords( $table, $_records, $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );

        // slow, but workable for now, maybe faster than merging individuals
        $extras['fields'] = '';
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
        unset( $_records['meta'] );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $_records, $_idsInfo );
        $extras['fields'] = $_fields;

        return $this->updateRecordsByIds( $table, $record, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @return array
     */
    public function updateRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $ids = DbUtilities::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        static::removeIds( $record, $_idFields );
        $extras['updates'] = $record;

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, static::RECORD_WRAPPER => $_out);
                $_msg = 'Batch Error: Not all records could be updated.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to update records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecordById( $table, $record, $id, $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->updateRecordsByIds( $table, $record, $id, $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecords( $table, $records, $extras = array() )
    {
        $records = DbUtilities::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $records ) );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_record, $_idsInfo, $extras ) )
                    {
                        throw new BadRequestException( "Required id field(s) not found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_result = $this->addToTransaction( $_record, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, static::RECORD_WRAPPER => $_out);
                $_msg = 'Batch Error: Not all records could be patched.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to patch records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecord( $table, $record, $extras = array() )
    {
        $_records = DbUtilities::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->patchRecords( $table, $_records, $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param  array $record
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );

        // slow, but workable for now, maybe faster than merging individuals
        $extras['fields'] = '';
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
        unset( $_records['meta'] );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $_records, $_idsInfo );
        $extras['fields'] = $_fields;

        return $this->patchRecordsByIds( $table, $record, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $ids = DbUtilities::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        static::removeIds( $record, $_idFields );
        $extras['updates'] = $record;

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, static::RECORD_WRAPPER => $_out);
                $_msg = 'Batch Error: Not all records could be patched.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to patch records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecordById( $table, $record, $id, $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->patchRecordsByIds( $table, $record, $id, $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecords( $table, $records, $extras = array() )
    {
        $records = DbUtilities::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = array();
        foreach ( $records as $_record )
        {
            $_ids[] = static::checkForIds( $_record, $_idsInfo, $extras );
        }

        return $this->deleteRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecord( $table, $record, $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->deleteRecords( $table, array($record), $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordsByFilter( $table, $filter, $params = array(), $extras = array() )
    {
        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );

        // slow, but workable for now, maybe faster than deleting individuals
        $extras['fields'] = '';
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
        unset( $_records['meta'] );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $_records, $_idsInfo, $extras );
        $extras['fields'] = $_fields;

        return $this->deleteRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordsByIds( $table, $ids, $extras = array() )
    {
        $ids = DbUtilities::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, static::RECORD_WRAPPER => $_out);
                $_msg = 'Batch Error: Not all records could be deleted.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordById( $table, $id, $extras = array() )
    {
        $_results = $this->deleteRecordsByIds( $table, $id, $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    abstract public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() );

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecords( $table, $records, $extras = array() )
    {
        $records = DbUtilities::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = array();
        foreach ( $records as $_record )
        {
            $_ids[] = static::checkForIds( $_record, $_idsInfo, $extras );
        }

        return $this->retrieveRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecord( $table, $record, $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->retrieveRecords( $table, array($record), $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecordsByIds( $table, $ids, $extras = array() )
    {
        $ids = DbUtilities::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['single'] = $_isSingle;
        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, false, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, static::RECORD_WRAPPER => $_out);
                $_msg = 'Batch Error: Not all records could be retrieved.';
            }

            if ( $_ex instanceof RestException )
            {
                $_temp = $_ex->getContext();
                $_context = ( empty( $_temp ) ) ? $_context : $_temp;
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to retrieve records from '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param mixed  $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecordById( $table, $id, $extras = array() )
    {
        $_results = $this->retrieveRecordsByIds( $table, $id, $extras );

        return Option::get( $_results, 0, array() );
    }

    // Helper function for record usage

    /**
     * @param $table
     *
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function getFieldsInfo( $table )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table can not be empty.' );
        }

        return array();
    }

    /**
     * @param      $table
     * @param null $fields_info
     * @param null $requested_fields
     * @param null $requested_types
     *
     * @return mixed
     */
    abstract protected function getIdsInfo( $table, $fields_info = null, &$requested_fields = null, $requested_types = null );

    /**
     * @param $id_info
     *
     * @return array
     */
    protected function getIdFieldsFromInfo( $id_info )
    {
        $_fields = array();
        foreach ( $id_info as $_info )
        {
            $_fields[] = Option::get( $_info, 'name' );
        }

        return $_fields;
    }

    /**
     * @param      $record
     * @param      $ids_info
     * @param null $extras
     * @param bool $on_create
     * @param bool $remove
     *
     * @return array|bool|int|mixed|null|string
     */
    protected function checkForIds( &$record, $ids_info, $extras = null, $on_create = false, $remove = false )
    {
        $_id = null;
        if ( !empty( $ids_info ) )
        {
            if ( 1 == count( $ids_info ) )
            {
                $_info = $ids_info[0];
                $_name = Option::get( $_info, 'name' );
                $_value = ( is_array( $record ) ) ? Option::get( $record, $_name, null, $remove ) : $record;
                if ( !empty( $_value ) )
                {
                    $_type = Option::get( $_info, 'type' );
                    switch ( $_type )
                    {
                        case 'int':
                            $_value = intval( $_value );
                            break;
                        case 'string':
                            $_value = strval( $_value );
                            break;
                    }
                    $_id = $_value;
                }
                else
                {
                    $_required = Option::getBool( $_info, 'required' );
                    // could be passed in as a parameter affecting all records
                    $_param = Option::get( $extras, $_name );
                    if ( $on_create && $_required && empty( $_param ) )
                    {
                        return false;
                    }
                }
            }
            else
            {
                $_id = array();
                foreach ( $ids_info as $_info )
                {
                    $_name = Option::get( $_info, 'name' );
                    $_value = Option::get( $record, $_name, null, $remove );
                    if ( !empty( $_value ) )
                    {
                        $_type = Option::get( $_info, 'type' );
                        switch ( $_type )
                        {
                            case 'int':
                                $_value = intval( $_value );
                                break;
                            case 'string':
                                $_value = strval( $_value );
                                break;
                        }
                        $_id[$_name] = $_value;
                    }
                    else
                    {
                        $_required = Option::getBool( $_info, 'required' );
                        // could be passed in as a parameter affecting all records
                        $_param = Option::get( $extras, $_name );
                        if ( $on_create && $_required && empty( $_param ) )
                        {
                            return false;
                        }
                    }
                }
            }
        }

        if ( !empty( $_id ) )
        {
            return $_id;
        }
        elseif ( $on_create )
        {
            return array();
        }

        return false;
    }

    /**
     * @param array $record
     * @param array $fields_info
     * @param array $filter_info
     * @param bool  $for_update
     * @param array $old_record
     *
     * @return array
     * @throws \Exception
     */
    protected function parseRecord( $record, $fields_info, $filter_info = null, $for_update = false, $old_record = null )
    {
//        $record = DataFormat::arrayKeyLower( $record );
        $_parsed = ( empty( $fields_info ) ) ? $record : array();
        if ( !empty( $fields_info ) )
        {
            $_keys = array_keys( $record );
            $_values = array_values( $record );
            foreach ( $fields_info as $_fieldInfo )
            {
//            $name = strtolower( Option::get( $field_info, 'name', '' ) );
                $_name = Option::get( $_fieldInfo, 'name', '' );
                $_type = Option::get( $_fieldInfo, 'type' );
                $_pos = array_search( $_name, $_keys );
                if ( false !== $_pos )
                {
                    $_fieldVal = Option::get( $_values, $_pos );
                    // due to conversion from XML to array, null or empty xml elements have the array value of an empty array
                    if ( is_array( $_fieldVal ) && empty( $_fieldVal ) )
                    {
                        $_fieldVal = null;
                    }

                    /** validations **/

                    $_validations = Option::get( $_fieldInfo, 'validation' );

                    if ( !static::validateFieldValue( $_name, $_fieldVal, $_validations, $for_update, $_fieldInfo ) )
                    {
                        unset( $_keys[$_pos] );
                        unset( $_values[$_pos] );
                        continue;
                    }

                    $_parsed[$_name] = $_fieldVal;
                    unset( $_keys[$_pos] );
                    unset( $_values[$_pos] );
                }

                // add or override for specific fields
                switch ( $_type )
                {
                    case 'timestamp_on_create':
                        if ( !$for_update )
                        {
                            $_parsed[$_name] = time();
                        }
                        break;
                    case 'timestamp_on_update':
                        $_parsed[$_name] = time();
                        break;
                    case 'user_id_on_create':
                        if ( !$for_update )
                        {
                            $userId = Session::getCurrentUserId();
                            if ( isset( $userId ) )
                            {
                                $_parsed[$_name] = $userId;
                            }
                        }
                        break;
                    case 'user_id_on_update':
                        $userId = Session::getCurrentUserId();
                        if ( isset( $userId ) )
                        {
                            $_parsed[$_name] = $userId;
                        }
                        break;
                }
            }
        }

        if ( !empty( $filter_info ) )
        {
            $this->validateRecord( $_parsed, $filter_info, $for_update, $old_record );
        }

        return $_parsed;
    }

    /**
     * @param array $record
     * @param array $filter_info
     * @param bool  $for_update
     * @param array $old_record
     *
     * @throws \Exception
     */
    protected function validateRecord( $record, $filter_info, $for_update = false, $old_record = null )
    {
        $_filters = Option::get( $filter_info, 'filters' );

        if ( empty( $_filters ) || empty( $record ) )
        {
            return;
        }

        $_combiner = Option::get( $filter_info, 'filter_op', 'and' );
        foreach ( $_filters as $_filter )
        {
            $_filterField = Option::get( $_filter, 'name' );
            $_operator = Option::get( $_filter, 'operator' );
            $_filterValue = Option::get( $_filter, 'value' );
            $_filterValue = static::interpretFilterValue( $_filterValue );
            $_foundInRecord = ( is_array( $record ) ) ? array_key_exists( $_filterField, $record ) : false;
            $_recordValue = Option::get( $record, $_filterField );
            $_foundInOld = ( is_array( $old_record ) ) ? array_key_exists( $_filterField, $old_record ) : false;
            $_oldValue = Option::get( $old_record, $_filterField );
            $_compareFound = ( $_foundInRecord || ( $for_update && $_foundInOld ) );
            $_compareValue = $_foundInRecord ? $_recordValue : ( $for_update ? $_oldValue : null );

            $_reason = null;
            if ( $for_update && !$_compareFound )
            {
                // not being set, filter on update will check old record
                continue;
            }

            if ( !static::compareByOperator( $_operator, $_compareFound, $_compareValue, $_filterValue ) )
            {
                $_reason = "Denied access to some of the requested fields.";
            }

            switch ( strtolower( $_combiner ) )
            {
                case 'and':
                    if ( !empty( $_reason ) )
                    {
                        // any reason is a good reason to bail
                        throw new ForbiddenException( $_reason );
                    }
                    break;
                case 'or':
                    if ( empty( $_reason ) )
                    {
                        // at least one was successful
                        return;
                    }
                    break;
                default:
                    throw new InternalServerErrorException( 'Invalid server configuration detected.' );

            }
        }
    }

    /**
     * @param $operator
     * @param $left_found
     * @param $left
     * @param $right
     *
     * @return bool
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     */
    public static function compareByOperator( $operator, $left_found, $left, $right )
    {
        switch ( $operator )
        {
            case DbFilterOperators::EQ:
                return ( $left == $right );
            case DbFilterOperators::NE:
                return ( $left != $right );
            case DbFilterOperators::GT:
                return ( $left > $right );
            case DbFilterOperators::LT:
                return ( $left < $right );
            case DbFilterOperators::GE:
                return ( $left >= $right );
            case DbFilterOperators::LE:
                return ( $left <= $right );
            case DbFilterOperators::STARTS_WITH:
                return static::startsWith( $left, $right );
            case DbFilterOperators::ENDS_WITH:
                return static::endswith( $left, $right );
            case DbFilterOperators::CONTAINS:
                return ( false !== strpos( $left, $right ) );
            case DbFilterOperators::IN:
                return Utilities::isInList( $right, $left );
            case DbFilterOperators::NOT_IN:
                return !Utilities::isInList( $right, $left );
            case DbFilterOperators::IS_NULL:
                return is_null( $left );
            case DbFilterOperators::IS_NOT_NULL:
                return !is_null( $left );
            case DbFilterOperators::DOES_EXIST:
                return ( $left_found );
            case DbFilterOperators::DOES_NOT_EXIST:
                return ( !$left_found );
            default:
                throw new InternalServerErrorException( 'Invalid server configuration detected.' );
        }
    }

    /**
     * @param      $name
     * @param      $value
     * @param      $validations
     * @param bool $for_update
     * @param null $field_info
     *
     * @return bool
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected static function validateFieldValue( $name, $value, $validations, $for_update = false, $field_info = null )
    {
        if ( is_array( $validations ) )
        {
            foreach ( $validations as $_key => $_config )
            {
                $_onFail = Option::get( $_config, 'on_fail' );
                $_throw = true;
                $_msg = null;
                if ( !empty( $_onFail ) )
                {
                    if ( 0 == strcasecmp( $_onFail, 'ignore_field' ) )
                    {
                        $_throw = false;
                    }
                    else
                    {
                        $_msg = $_onFail;
                    }
                }

                switch ( $_key )
                {
                    case 'api_read_only':
                        if ( $_throw )
                        {
                            if ( empty( $_msg ) )
                            {
                                $_msg = "Field '$name' is read only.";
                            }
                            throw new BadRequestException( $_msg );
                        }

                        return false;
                        break;
                    case 'create_only':
                        if ( $for_update )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' can only be set during record creation.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'not_null':
                        if ( is_null( $value ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value can not be null.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'not_empty':
                        if ( !is_null( $value ) && empty( $value ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value can not be empty.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'not_zero':
                        if ( !is_null( $value ) && empty( $value ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value can not be empty.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'email':
                        if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_EMAIL ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value must be a valid email address.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'url':
                        $_sections = Option::clean( Option::get( $_config, 'sections' ) );
                        $_flags = 0;
                        foreach ( $_sections as $_format )
                        {
                            switch ( strtolower( $_format ) )
                            {
                                case 'path':
                                    $_flags &= FILTER_FLAG_PATH_REQUIRED;
                                    break;
                                case 'query':
                                    $_flags &= FILTER_FLAG_QUERY_REQUIRED;
                                    break;
                            }
                        }
                        if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_URL, $_flags ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value must be a valid URL.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'int':
                        $_min = Option::getDeep( $_config, 'range', 'min' );
                        $_max = Option::getDeep( $_config, 'range', 'max' );
                        $_formats = Option::clean( Option::get( $_config, 'formats' ) );

                        $_options = array();
                        if ( is_int( $_min ) )
                        {
                            $_options['min_range'] = $_min;
                        }
                        if ( is_int( $_max ) )
                        {
                            $_options['max_range'] = $_max;
                        }
                        $_flags = 0;
                        foreach ( $_formats as $_format )
                        {
                            switch ( strtolower( $_format ) )
                            {
                                case 'hex':
                                    $_flags &= FILTER_FLAG_ALLOW_HEX;
                                    break;
                                case 'octal':
                                    $_flags &= FILTER_FLAG_ALLOW_OCTAL;
                                    break;
                            }
                        }
                        $_options = array('options' => $_options, 'flags' => $_flags);
                        if ( !is_null( $value ) && false === filter_var( $value, FILTER_VALIDATE_INT, $_options ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value is not in the valid range.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'float':
                        $_decimal = Option::get( $_config, 'decimal', '.' );
                        $_options['decimal'] = $_decimal;
                        $_options = array('options' => $_options);
                        if ( !is_null( $value ) && !filter_var( $value, FILTER_VALIDATE_FLOAT, $_options ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value is not an acceptable float value.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'boolean':
                        if ( !is_null( $value ) && !filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value is not an acceptable boolean value.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'match':
                        $_regex = Option::get( $_config, 'regexp' );
                        if ( empty( $_regex ) )
                        {
                            throw new InternalServerErrorException( "Invalid validation configuration: Field '$name' has no 'regexp'." );
                        }

                        $_regex = base64_decode( $_regex );
                        $_options = array('regexp' => $_regex);
                        if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_REGEXP, $_options ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value is invalid.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'picklist':
                        $_values = Option::get( $field_info, 'value' );
                        if ( empty( $_values ) )
                        {
                            throw new InternalServerErrorException( "Invalid validation configuration: Field '$name' has no 'value' in schema settings." );
                        }

                        if ( !empty( $value ) && ( false === array_search( $value, $_values ) ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value is invalid.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'multi_picklist':
                        $_values = Option::get( $field_info, 'value' );
                        if ( empty( $_values ) )
                        {
                            throw new InternalServerErrorException( "Invalid validation configuration: Field '$name' has no 'value' in schema settings." );
                        }

                        if ( !empty( $value ) )
                        {
                            $_delimiter = Option::get( $_config, 'delimiter', ',' );
                            $_min = Option::get( $_config, 'min', 1 );
                            $_max = Option::get( $_config, 'max' );
                            $value = DbUtilities::validateAsArray( $value, $_delimiter, true );
                            $_count = count( $value );
                            if ( $_count < $_min )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value does not contain enough selections.";
                                }
                                throw new BadRequestException( $_msg );
                            }
                            if ( !empty( $_max ) && ( $_count > $_max ) )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value contains too many selections.";
                                }
                                throw new BadRequestException( $_msg );
                            }
                            foreach ( $value as $_item )
                            {
                                if ( false === array_search( $_item, $_values ) )
                                {
                                    if ( $_throw )
                                    {
                                        if ( empty( $_msg ) )
                                        {
                                            $_msg = "Field '$name' value is invalid.";
                                        }
                                        throw new BadRequestException( $_msg );
                                    }

                                    return false;
                                }
                            }
                        }
                        break;
                }
            }
        }

        return true;
    }

    /**
     * @return int
     */
    protected static function getMaxRecordsReturnedLimit()
    {
        return intval( Pii::getParam( 'dsp.db_max_records_returned', static::MAX_RECORDS_RETURNED ) );
    }

    /**
     * @param mixed $handle
     *
     * @return bool
     */
    protected function initTransaction( $handle = null )
    {
        $this->_transactionTable = $handle;
        $this->_batchRecords = array();
        $this->_batchIds = array();
        $this->_rollbackRecords = array();

        return true;
    }

    /**
     * @param mixed      $record
     * @param mixed      $id
     * @param null|array $extras Additional items needed to complete the transaction
     * @param bool       $rollback
     * @param bool       $continue
     * @param bool       $single
     *
     * @throws \DreamFactory\Platform\Exceptions\NotImplementedException
     * @return null|array Array of output fields
     */
    protected function addToTransaction( $record = null, $id = null, /** @noinspection PhpUnusedParameterInspection */
        $extras = null, /** @noinspection PhpUnusedParameterInspection */
        $rollback = false, /** @noinspection PhpUnusedParameterInspection */
        $continue = false, /** @noinspection PhpUnusedParameterInspection */
        $single = false )
    {
        if ( !empty( $record ) )
        {
            $this->_batchRecords[] = $record;
        }
        if ( !empty( $id ) )
        {
            $this->_batchIds[] = $id;
        }

        return null;
    }

    /**
     * @param null|array $extras Additional items needed to complete the transaction
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array Array of output records
     */
    abstract protected function commitTransaction( $extras = null );

    /**
     * @param mixed $record
     *
     * @return bool
     */
    protected function addToRollback( $record )
    {
        if ( !empty( $record ) )
        {
            $this->_rollbackRecords[] = $record;
        }

        return true;
    }

    /**
     * @return bool
     */
    abstract protected function rollbackTransaction();

    /**
     * @param array        $record
     * @param string|array $include  List of keys to include in the output record
     * @param string|array $id_field Single or list of identifier fields
     *
     * @return array
     */
    protected static function cleanRecord( $record = array(), $include = '*', $id_field = null )
    {
        if ( '*' !== $include )
        {
            if ( !empty( $id_field ) && !is_array( $id_field ) )
            {
                $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
            }
            $id_field = Option::clean( $id_field );

            if ( !empty( $include ) && !is_array( $include ) )
            {
                $include = array_map( 'trim', explode( ',', trim( $include, ',' ) ) );
            }
            $include = Option::clean( $include );

            // make sure we always include identifier fields
            foreach ( $id_field as $id )
            {
                if ( false === array_search( $id, $include ) )
                {
                    $include[] = $id;
                }
            }

            // glean desired fields from record
            $_out = array();
            foreach ( $include as $_key )
            {
                $_out[$_key] = Option::get( $record, $_key );
            }

            return $_out;
        }

        return $record;
    }

    /**
     * @param array $records
     * @param mixed $include
     * @param mixed $id_field
     *
     * @return array
     */
    protected static function cleanRecords( $records, $include = '*', $id_field = null )
    {
        $_out = array();
        foreach ( $records as $_record )
        {
            $_out[] = static::cleanRecord( $_record, $include, $id_field );
        }

        return $_out;
    }

    /**
     * @param array $records
     * @param       $ids_info
     * @param null  $extras
     * @param bool  $on_create
     * @param bool  $remove
     *
     * @internal param string $id_field
     * @internal param bool $include_field
     *
     * @return array
     */
    protected static function recordsAsIds( $records, $ids_info, $extras = null, $on_create = false, $remove = false )
    {
        $_out = array();
        if ( !empty( $records ) )
        {
            foreach ( $records as $_record )
            {
                $_out[] = static::checkForIds( $_record, $ids_info, $extras, $on_create, $remove );
            }
        }

        return $_out;
    }

    /**
     * @param array  $record
     * @param string $id_field
     * @param bool   $include_field
     * @param bool   $remove
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    protected static function recordAsId( &$record, $id_field = null, $include_field = false, $remove = false )
    {
        if ( empty( $id_field ) )
        {
            return array();
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        if ( count( $id_field ) > 1 )
        {
            $_ids = array();
            foreach ( $id_field as $_field )
            {
                $_id = Option::get( $record, $_field, null, $remove );
                if ( empty( $_id ) )
                {
                    throw new BadRequestException( "Identifying field '$_field' can not be empty for record." );
                }
                $_ids[$_field] = $_id;
            }

            return $_ids;
        }
        else
        {
            $_field = $id_field[0];
            $_id = Option::get( $record, $_field, null, $remove );
            if ( empty( $_id ) )
            {
                throw new BadRequestException( "Identifying field '$_field' can not be empty for record." );
            }

            return ( $include_field ) ? array($_field => $_id) : $_id;
        }
    }

    /**
     * @param        $ids
     * @param string $id_field
     * @param bool   $field_included
     *
     * @return array
     */
    protected static function idsAsRecords( $ids, $id_field, $field_included = false )
    {
        if ( empty( $id_field ) )
        {
            return array();
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        $_out = array();
        foreach ( $ids as $_id )
        {
            $_ids = array();
            if ( ( count( $id_field ) > 1 ) && ( count( $_id ) > 1 ) )
            {
                foreach ( $id_field as $_index => $_field )
                {
                    $_search = ( $field_included ) ? $_field : $_index;
                    $_ids[$_field] = Option::get( $_id, $_search );
                }
            }
            else
            {
                $_field = $id_field[0];
                $_ids[$_field] = $_id;
            }

            $_out[] = $_ids;
        }

        return $_out;
    }

    /**
     * @param array $record
     * @param array $id_field
     */
    protected static function removeIds( &$record, $id_field )
    {
        if ( !empty( $id_field ) )
        {

            if ( !is_array( $id_field ) )
            {
                $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
            }

            foreach ( $id_field as $_name )
            {
                unset( $record[$_name] );
            }
        }
    }

    /**
     * @param      $record
     * @param null $id_field
     *
     * @return bool
     */
    protected static function _containsIdFields( $record, $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            return false;
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        foreach ( $id_field as $_field )
        {
            $_temp = Option::get( $record, $_field );
            if ( empty( $_temp ) )
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @param        $fields
     * @param string $id_field
     *
     * @return bool
     */
    protected static function _requireMoreFields( $fields, $id_field = null )
    {
        if ( ( '*' == $fields ) || empty( $id_field ) )
        {
            return true;
        }

        if ( false === $fields = DbUtilities::validateAsArray( $fields, ',' ) )
        {
            return false;
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        foreach ( $id_field as $_key => $_name )
        {
            if ( false !== array_search( $_name, $fields ) )
            {
                unset( $fields[$_key] );
            }
        }

        return !empty( $fields );
    }

    /**
     * @param        $first_array
     * @param        $second_array
     * @param string $id_field
     *
     * @return mixed
     */
    protected static function recordArrayMerge( $first_array, $second_array, $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            return array();
        }

        foreach ( $first_array as $_key => $_first )
        {
            $_firstId = Option::get( $_first, $id_field );
            foreach ( $second_array as $_second )
            {
                $_secondId = Option::get( $_second, $id_field );
                if ( $_firstId == $_secondId )
                {
                    $first_array[$_key] = array_merge( $_first, $_second );
                }
            }
        }

        return $first_array;
    }

    /**
     * @param $value
     *
     * @return bool|int|null|string
     */
    public static function interpretFilterValue( $value )
    {
        // all other data types besides strings, just return
        if ( !is_string( $value ) || empty( $value ) )
        {
            return $value;
        }

        $_end = strlen( $value ) - 1;
        // filter string values should be wrapped in matching quotes
        if ( ( ( 0 === strpos( $value, '"' ) ) && ( $_end === strrpos( $value, '"' ) ) ) ||
             ( ( 0 === strpos( $value, "'" ) ) && ( $_end === strrpos( $value, "'" ) ) )
        )
        {
            return substr( $value, 1, $_end - 1 );
        }

        // check for boolean or null values
        switch ( strtolower( $value ) )
        {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }

        if ( is_numeric( $value ) )
        {
            return $value + 0; // trick to get int or float
        }

        // the rest should be lookup keys, or plain strings
        Session::replaceLookups( $value );
        return $value;
    }

    /**
     * @param array $record
     *
     * @return array
     */
    public static function interpretRecordValues( $record )
    {
        if ( !is_array( $record ) || empty( $record ) )
        {
            return $record;
        }

        foreach ( $record as $_field => $_value )
        {
            Session::replaceLookups( $_value );
            $record[$_field] = $_value;
        }

        return $record;
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public static function startsWith( $haystack, $needle )
    {
        return ( substr( $haystack, 0, strlen( $needle ) ) === $needle );
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public static function endsWith( $haystack, $needle )
    {
        return ( substr( $haystack, -strlen( $needle ) ) === $needle );
    }

    /**
     * @return int|string
     */
    public function getResourceId()
    {
        return $this->_resourceId;
    }

    // Handle administrative options, table add, delete, etc

    /**
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleSchema()
    {
        $_result = false;

        $_tableName = $this->_resourceId;
        $_fieldName = Option::get( $this->_resourceArray, 2 );

        switch ( $this->_action )
        {
            case static::GET:
                $_refresh = Option::getBool( $this->_requestPayload, 'refresh' );
                if ( empty( $_tableName ) )
                {
                    $_tables = Option::get( $this->_requestPayload, 'names' );
                    if ( empty( $_tables ) )
                    {
                        $_tables = Option::get( $this->_requestPayload, 'table' );
                    }

                    if ( !empty( $_tables ) )
                    {
                        $_result = array('table' => $this->describeTables( $_tables, $_refresh ));
                    }
                    else
                    {
                        $_result = array('resource' => $this->describeDatabase( $this->_requestPayload, $_refresh ));
                    }
                }
                elseif ( empty( $_fieldName ) )
                {
                    $_result = $this->describeTable( $_tableName, $_refresh );
                }
                else
                {
                    $_result = $this->describeField( $_tableName, $_fieldName, $_refresh );
                }

                break;

            case static::POST:
                $_checkExist = Option::getBool( $this->_requestPayload, 'check_exist' );
                $_returnSchema = Option::getBool( $this->_requestPayload, 'return_schema' );
                if ( empty( $_tableName ) )
                {
                    $_tables = Option::get( $this->_requestPayload, 'table', $this->_requestPayload );
                    if ( empty( $_tables ) )
                    {
                        throw new BadRequestException( 'No data in schema create request.' );
                    }

                    $_result = array('table' => $this->createTables( $_tables, $_checkExist, $_returnSchema ));
                }
                elseif ( empty( $_fieldName ) )
                {
                    $_result = $this->createTable( $_tableName, $this->_requestPayload, $_checkExist, $_returnSchema );
                }
                elseif ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in schema create request.' );
                }
                else
                {
                    $_result = $this->createField( $_tableName, $_fieldName, $this->_requestPayload, $_checkExist, $_returnSchema );
                }

                break;

            case static::PUT:
                $_returnSchema = Option::getBool( $this->_requestPayload, 'return_schema' );
                if ( empty( $_tableName ) )
                {
                    $_tables = Option::get( $this->_requestPayload, 'table', $this->_requestPayload );
                    if ( empty( $_tables ) )
                    {
                        throw new BadRequestException( 'No data in schema update request.' );
                    }

                    $_result = array('table' => $this->updateTables( $_tables, true, $_returnSchema ));
                }
                elseif ( empty( $_fieldName ) )
                {
                    $_result = $this->updateTable( $_tableName, $this->_requestPayload, true, $_returnSchema );
                }
                elseif ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in schema update request.' );
                }
                else
                {
                    $_result = $this->updateField( $_tableName, $_fieldName, $this->_requestPayload, true, $_returnSchema );
                }

                break;

            case static::PATCH:
            case static::MERGE:
                $_returnSchema = Option::getBool( $this->_requestPayload, 'return_schema' );
                if ( empty( $_tableName ) )
                {
                    $_tables = Option::get( $this->_requestPayload, 'table', $this->_requestPayload );
                    if ( empty( $_tables ) )
                    {
                        throw new BadRequestException( 'No data in schema update request.' );
                    }

                    $_result = array('table' => $this->updateTables( $_tables, false, $_returnSchema ));
                }
                elseif ( empty( $_fieldName ) )
                {
                    $_result = $this->updateTable( $_tableName, $this->_requestPayload, false, $_returnSchema );
                }
                elseif ( empty( $this->_requestPayload ) )
                {
                    throw new BadRequestException( 'No data in schema update request.' );
                }
                else
                {
                    $_result = $this->updateField( $_tableName, $_fieldName, $this->_requestPayload, false, $_returnSchema );
                }

                break;

            case static::DELETE:
                if ( empty( $_tableName ) )
                {
                    $_tables = Option::get( $this->_requestPayload, 'names' );
                    if ( empty( $_tables ) )
                    {
                        $_tables = Option::get( $this->_requestPayload, 'table' );
                    }

                    if ( empty( $_tables ) )
                    {
                        throw new BadRequestException( 'No data in schema delete request.' );
                    }

                    $_result = $this->deleteTables( $_tables );

                    $_result = array('table' => $_result);
                }
                elseif ( empty( $_fieldName ) )
                {
                    $this->deleteTable( $_tableName );

                    $_result = array('success' => true);
                }
                else
                {
                    $this->deleteField( $_tableName, $_fieldName );

                    $_result = array('success' => true);
                }
                break;
        }

        return $_result;
    }

    /**
     * Check if the table exists in the database
     *
     * @param string $table_name Table name
     *
     * @return boolean
     * @throws \Exception
     */
    public function doesTableExist( $table_name )
    {
        try
        {
            $this->correctTableName( $table_name );

            return true;
        }
        catch ( \Exception $ex )
        {

        }

        return false;
    }

    /**
     * @param array | null $options
     * @param bool         $refresh Force a refresh of the schema from the database
     *
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     */
    public function describeDatabase( $options = null, $refresh = false )
    {
        $_namesOnly = Option::getBool( $options, 'names_only' );
        $_resources = array();

        try
        {
            $_result = static::_listTables( $refresh );
            foreach ( $_result as $_table )
            {
                if ( null != $_name = Option::get( $_table, 'name' ) )
                {
                    $_access = $this->getPermissions( static::SCHEMA_RESOURCE . '/' . $_name );
                    if ( !empty( $_access ) )
                    {
                        if ( $_namesOnly )
                        {
                            $_resources[] = $_name;
                        }
                        else
                        {
                            $_table['name'] = $_name;
                            $_table['access'] = $_access;
                            $_resources[] = $_table;
                        }
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
            throw new InternalServerErrorException( "Failed to list schema resources for this service.\n{$_ex->getMessage()}" );
        }
    }

    /**
     * Get multiple tables and their properties
     *
     * @param string | array $tables  Table names comma-delimited string or array
     * @param bool           $refresh Force a refresh of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function describeTables( $tables, $refresh = false )
    {
        $tables = DbUtilities::validateAsArray(
            $tables,
            ',',
            true,
            'The request contains no valid table names or properties.'
        );

        $_out = array();
        foreach ( $tables as $_table )
        {
            $_name = ( is_array( $_table ) ) ? Option::get( $_table, 'name' ) : $_table;
            $this->validateSchemaAccess( $_name );

            $_out[] = $this->describeTable( $_table, $refresh );
        }

        return $_out;
    }

    /**
     * Get any properties related to the table
     *
     * @param string | array $table   Table name or defining properties
     * @param bool           $refresh Force a refresh of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    abstract public function describeTable( $table, $refresh = false );

    /**
     * Get any properties related to the table field
     *
     * @param string $table   Table name
     * @param string $field   Table field name
     * @param bool   $refresh Force a refresh of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    abstract public function describeField( $table, $field, $refresh = false );

    /**
     * Create one or more tables by array of table properties
     *
     * @param string|array $tables
     * @param bool         $check_exist
     * @param bool         $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function createTables( $tables, $check_exist = false, $return_schema = false )
    {
        $tables = DbUtilities::validateAsArray(
            $tables,
            ',',
            true,
            'The request contains no valid table names or properties.'
        );

        $_out = array();
        foreach ( $tables as $_table )
        {
            $_name = ( is_array( $_table ) ) ? Option::get( $_table, 'name' ) : $_table;
            $_out[] = $this->createTable( $_name, $_table, $check_exist, $return_schema );
        }

        return $_out;
    }

    /**
     * Create a single table by name and additional properties
     *
     * @param string $table
     * @param array  $properties
     * @param bool   $check_exist
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     */
    abstract public function createTable( $table, $properties = array(), $check_exist = false, $return_schema = false );

    /**
     * Create a single table field by name and additional properties
     *
     * @param string $table
     * @param string $field
     * @param array  $properties
     * @param bool   $check_exist
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     */
    abstract public function createField( $table, $field, $properties = array(), $check_exist = false, $return_schema = false );

    /**
     * Update one or more tables by array of table properties
     *
     * @param array $tables
     * @param bool  $allow_delete_fields
     * @param bool  $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     */
    public function updateTables( $tables, $allow_delete_fields = false, $return_schema = false )
    {
        $tables = DbUtilities::validateAsArray(
            $tables,
            null,
            true,
            'The request contains no valid table properties.'
        );

        // update tables allows for create as well
        $_out = array();
        foreach ( $tables as $_table )
        {
            $_name = ( is_array( $_table ) ) ? Option::get( $_table, 'name' ) : $_table;
            if ( $this->doesTableExist( $_name ) )
            {
                $this->validateSchemaAccess( $_name, static::PATCH );
                $_out[] = $this->updateTable( $_name, $_table, $allow_delete_fields, $return_schema );
            }
            else
            {
                $this->validateSchemaAccess( null, static::POST );
                $_out[] = $this->createTable( $_name, $_table, false, $return_schema );
            }
        }

        return $_out;
    }

    /**
     * Update properties related to the table
     *
     * @param string $table
     * @param array  $properties
     * @param bool   $allow_delete_fields
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    abstract public function updateTable( $table, $properties, $allow_delete_fields = false, $return_schema = false );

    /**
     * Update properties related to the table
     *
     * @param string $table
     * @param string $field
     * @param array  $properties
     * @param bool   $allow_delete_parts
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    abstract public function updateField( $table, $field, $properties, $allow_delete_parts = false, $return_schema = false );

    /**
     * Delete multiple tables and all of their contents
     *
     * @param array $tables
     * @param bool  $check_empty
     *
     * @return array
     * @throws \Exception
     */
    public function deleteTables( $tables, $check_empty = false )
    {
        $tables = DbUtilities::validateAsArray(
            $tables,
            ',',
            true,
            'The request contains no valid table names or properties.'
        );

        $_out = array();
        foreach ( $tables as $_table )
        {
            $_name = ( is_array( $_table ) ) ? Option::get( $_table, 'name' ) : $_table;
            $this->validateSchemaAccess( $_name );
            $_out[] = $this->deleteTable( $_table, $check_empty );
        }

        return $_out;
    }

    /**
     * Delete a table and all of its contents by name
     *
     * @param string $table
     * @param bool   $check_empty
     *
     * @throws \Exception
     * @return array
     */
    abstract public function deleteTable( $table, $check_empty = false );

    /**
     * Delete a table field
     *
     * @param string $table
     * @param string $field
     *
     * @throws \Exception
     * @return array
     */
    abstract public function deleteField( $table, $field );

    /**
     * Delete all table entries but keep the table
     *
     * @param string $table
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function truncateTable( $table, $extras = array() )
    {
        // todo faster way?
        $_records = $this->retrieveRecordsByFilter( $table, null, null, $extras );

        if ( !empty( $_records ) )
        {
            $this->deleteRecords( $table, $_records, $extras );
        }

        return array('success' => true);
    }

}
