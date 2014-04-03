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
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * BaseDbSvc
 *
 * A base service class to handle generic db services accessed through the REST API.
 */
abstract class BaseDbSvc extends BasePlatformRestService
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default record identifier field
     */
    const DEFAULT_ID_FIELD = 'id';

    /**
     * Default maximum records returned on filter request
     */
    const DB_MAX_RECORDS_RETURNED = 1000;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var int|string
     */
    protected $_resourceId = null;
    /**
     * @var array
     */
    protected $_requestData = null;
    /**
     * @var boolean
     */
    protected $_useBlendFormat = true;

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
                static::PATCH => static::MERGE,
            );
        }

        parent::__construct( $settings );
    }

    /**
     *
     */
    protected function _detectResourceMembers( $resourcePath = null )
    {
        parent::_detectResourceMembers( $resourcePath );

        $this->_resourceId = ( isset( $this->_resourceArray, $this->_resourceArray[1] ) ) ? $this->_resourceArray[1] : '';
        $this->_requestData = RestData::getPostedData( true, true );
    }

    /**
     * @param null|array $post_data
     *
     * @return array
     */
    protected function _gatherExtrasFromRequest( $post_data = null )
    {
        // most DBs support the following filter extras from url or posted data
        $_extras = array();

        // Most requests contain 'returned fields' parameter
        $_fields = static::getFromPostedData( $post_data, 'fields', FilterInput::request( 'fields' ) );
        $_fieldsDefault = ( static::GET == $this->_action ) ? '*' : null;
        $_extras['fields'] = ( empty( $_fields ) ) ? $_fieldsDefault : $_fields;

        // means to override the default identifier fields for a table
        // or supply one when there is no default designated
        $_idField = static::getFromPostedData( $post_data, 'id_field', FilterInput::request( 'id_field' ) );
        $_extras['id_field'] = ( empty( $_idField ) ) ? static::DEFAULT_ID_FIELD : $_idField;

        if ( null != $_ssFilters = Session::getServiceFilters( $this->_apiName, $this->_resource ) )
        {
            $_extras['ss_filters'] = $_ssFilters;
        }

        // look for limit, accept top as well as limit
        $_limit = FilterInput::request(
            'limit',
            FilterInput::request(
                'top',
                Option::get( $post_data, 'limit', Option::get( $post_data, 'top' ) ),
                FILTER_VALIDATE_INT
            ),
            FILTER_VALIDATE_INT
        );
        $_extras['limit'] = $_limit;

        // accept skip as well as offset
        $_offset = FilterInput::request(
            'offset',
            FilterInput::request(
                'skip',
                Option::get( $post_data, 'offset', Option::get( $post_data, 'skip' ) ),
                FILTER_VALIDATE_INT
            ),
            FILTER_VALIDATE_INT
        );
        $_extras['offset'] = $_offset;

        // accept sort as well as order
        $_order = FilterInput::request(
            'order',
            FilterInput::request(
                'sort',
                Option::get( $post_data, 'order', Option::get( $post_data, 'sort' ) )
            )
        );
        $_extras['order'] = $_order;

        // include count in metadata tag
        $_includeCount = FilterInput::request(
            'include_count',
            Option::getBool( $post_data, 'include_count', false ),
            FILTER_VALIDATE_BOOLEAN
        );
        $_extras['include_count'] = $_includeCount;

        return $_extras;
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

        $_action = ( empty( $action ) ) ? $this->getRequestedAction() : $action;

        // finally check that the current user has privileges to access this table
        $this->checkPermission( $_action, $table );
    }

    /**
     * {@InheritDoc}
     */
    protected function _preProcess()
    {
        parent::_preProcess();

        //	Do validation here
        if ( !empty( $this->_resource ) )
        {
            $this->validateTableAccess( $this->_resource );
        }
        else
        {
            // listing and getting table properties are checked by table
            if ( static::GET != $_action = $this->getRequestedAction() )
            {
                $this->checkPermission( $_action );
            }
        }
    }

    /**
     * @return array|bool
     */
    protected function _handleResource()
    {
        if ( empty( $this->_resource ) )
        {
            return $this->_handleAdmin();
        }

        return parent::_handleResource();
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleAdmin()
    {
        $_result = false;

        switch ( $this->_action )
        {
            case static::GET:
                $_ids = static::getFromPostedData( $this->_requestData, 'names', FilterInput::request( 'names' ) );
                if ( empty( $_ids ) )
                {
                    return $this->_listResources();
                }

                $_result = $this->getTables( $_ids );
                $_result = array( 'table' => $_result );
                break;

            case static::POST:
                $_tables = static::getFromPostedData( $this->_requestData, 'table' );

                if ( empty( $_tables ) )
                {
                    $_result = $this->createTable( $this->_requestData );
                }
                else
                {
                    $_result = $this->createTables( $_tables );
                    $_result = array( 'table' => $_result );
                }
                break;

            case static::PUT:
            case static::PATCH:
            case static::MERGE:
                $_tables = static::getFromPostedData( $this->_requestData, 'table' );

                if ( empty( $_tables ) )
                {
                    $_result = $this->updateTable( $this->_requestData );
                }
                else
                {
                    $_result = $this->updateTables( $_tables );
                    $_result = array( 'table' => $_result );
                }
                break;

            case static::DELETE:
                $_ids = static::getFromPostedData( $this->_requestData, 'names', FilterInput::request( 'names' ) );
                if ( !empty( $_ids ) )
                {
                    $_result = $this->deleteTables( $_ids );
                    $_result = array( 'table' => $_result );
                }
                else
                {
                    $_tables = static::getFromPostedData( $this->_requestData, 'table' );

                    if ( empty( $_tables ) )
                    {
                        $_result = $this->deleteTable( $this->_requestData );
                    }
                    else
                    {
                        $_result = $this->deleteTables( $_tables );
                        $_result = array( 'table' => $_result );
                    }
                }
                break;
        }

        return $_result;
    }

    /**
     * @return array
     */
    protected function _handleGet()
    {
        $_extras = $this->_gatherExtrasFromRequest( $this->_requestData );

        if ( empty( $this->_resourceId ) )
        {
            $_ids = static::getFromPostedData( $this->_requestData, 'ids', FilterInput::request( 'ids' ) );
            if ( !empty( $_ids ) )
            {
                $_result = $this->retrieveRecordsByIds( $this->_resource, $_ids, $_extras );
                $_result = array( 'record' => $_result );
            }
            else
            {
                $_records = static::getFromPostedData( $this->_requestData, 'record' );

                if ( !empty( $_records ) )
                {
                    // passing records to have them updated with new or more values, id field required
                    $_result = $this->retrieveRecords( $this->_resource, $_records, $_extras );
                    $_result = array( 'record' => $_result );
                }
                else
                {
                    $_filter = Option::get( $this->_requestData, 'filter', FilterInput::request( 'filter' ) );
                    if ( empty( $_filter ) && !empty( $this->_requestData ) )
                    {
                        // query by record map
                        $_result = $this->retrieveRecord( $this->_resource, $this->_requestData, $_extras );
                    }
                    else
                    {
                        $_params = Option::get( $this->_requestData, 'params', array() );
                        $_result = $this->retrieveRecordsByFilter( $this->_resource, $_filter, $_params, $_extras );
                        if ( isset( $_result['meta'] ) )
                        {
                            $_meta = $_result['meta'];
                            unset( $_result['meta'] );
                            $_result = array( 'record' => $_result, 'meta' => $_meta );
                        }
                        else
                        {
                            $_result = array( 'record' => $_result );
                        }
                    }
                }
            }
        }
        else
        {
            // single entity by id
            $_result = $this->retrieveRecordById( $this->_resource, $this->_resourceId, $_extras );
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handlePost()
    {
        if ( empty( $this->_requestData ) )
        {
            throw new BadRequestException( 'No record(s) in create request.' );
        }

        $_extras = $this->_gatherExtrasFromRequest( $this->_requestData );
        $_records = static::getFromPostedData( $this->_requestData, 'record' );

        if ( empty( $_records ) )
        {
            $_result = $this->createRecord( $this->_resource, $this->_requestData, $_extras );
        }
        else
        {
            $_result = $this->createRecords( $this->_resource, $_records, $_extras );
            $_result = array( 'record' => $_result );
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handlePut()
    {
        if ( empty( $this->_requestData ) )
        {
            throw new BadRequestException( 'No record(s) in update request.' );
        }

        $_extras = $this->_gatherExtrasFromRequest( $this->_requestData );
        if ( empty( $this->_resourceId ) )
        {
            $_records = static::getFromPostedData( $this->_requestData, 'record' );

            $_ids = static::getFromPostedData( $this->_requestData, 'ids', FilterInput::request( 'ids' ) );
            if ( !empty( $_ids ) )
            {
                $_result = $this->updateRecordsByIds( $this->_resource, $_records, $_ids, $_extras );
                $_result = array( 'record' => $_result );
            }
            else
            {
                $_filter = Option::get( $this->_requestData, 'filter', FilterInput::request( 'filter' ) );
                if ( !empty( $_filter ) )
                {
                    $_params = Option::get( $this->_requestData, 'params', array() );
                    $_result = $this->updateRecordsByFilter( $this->_resource, $_records, $_filter, $_params, $_extras );
                    $_result = array( 'record' => $_result );
                }
                else
                {
                    if ( !empty( $_records ) )
                    {
                        $_result = $this->updateRecords( $this->_resource, $_records, $_extras );
                        $_result = array( 'record' => $_result );
                    }
                    else
                    {
                        $_result = $this->updateRecord( $this->_resource, $this->_requestData, $_extras );
                    }
                }
            }
        }
        else
        {
            $_result = $this->updateRecordById( $this->_resource, $this->_requestData, $this->_resourceId, $_extras );
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleMerge()
    {
        if ( empty( $this->_requestData ) )
        {
            throw new BadRequestException( 'No record(s) in merge request.' );
        }

        $_extras = $this->_gatherExtrasFromRequest( $this->_requestData );
        if ( empty( $this->_resourceId ) )
        {
            $_records = static::getFromPostedData( $this->_requestData, 'record' );

            $_ids = static::getFromPostedData( $this->_requestData, 'ids', FilterInput::request( 'ids' ) );
            if ( !empty( $_ids ) )
            {
                $_result = $this->mergeRecordsByIds( $this->_resource, $_records, $_ids, $_extras );
                $_result = array( 'record' => $_result );
            }
            else
            {
                $_filter = Option::get( $this->_requestData, 'filter', FilterInput::request( 'filter' ) );
                if ( !empty( $_filter ) )
                {
                    $_params = Option::get( $this->_requestData, 'params', array() );
                    $_result = $this->mergeRecordsByFilter( $this->_resource, $_records, $_filter, $_params, $_extras );
                    $_result = array( 'record' => $_result );
                }
                else
                {
                    if ( !empty( $_records ) )
                    {
                        $_result = $this->mergeRecords( $this->_resource, $_records, $_extras );
                        $_result = array( 'record' => $_result );
                    }
                    else
                    {
                        $_result = $this->mergeRecord( $this->_resource, $this->_requestData, $_extras );
                    }
                }
            }
        }
        else
        {
            $_result = $this->mergeRecordById( $this->_resource, $this->_requestData, $this->_resourceId, $_extras );
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleDelete()
    {
        $_extras = $this->_gatherExtrasFromRequest( $this->_requestData );
        if ( empty( $this->_resourceId ) )
        {
            $_ids = static::getFromPostedData( $this->_requestData, 'ids', FilterInput::request( 'ids' ) );
            if ( !empty( $_ids ) )
            {
                $_result = $this->deleteRecordsByIds( $this->_resource, $_ids, $_extras );
                $_result = array( 'record' => $_result );
            }
            else
            {
                $_records = static::getFromPostedData( $this->_requestData, 'record' );
                if ( !empty( $_records ) )
                {
                    $_result = $this->deleteRecords( $this->_resource, $_records, $_extras );
                    $_result = array( 'record' => $_result );
                }
                else
                {
                    $_filter = Option::get( $this->_requestData, 'filter', FilterInput::request( 'filter' ) );
                    if ( !empty( $_filter ) )
                    {
                        $_params = Option::get( $this->_requestData, 'params', array() );
                        $_result = $this->deleteRecordsByFilter( $this->_resource, $_filter, $_params, $_extras );
                        $_result = array( 'record' => $_result );
                    }
                    else
                    {
                        if ( empty( $this->_requestData ) )
                        {
                            if ( !FilterInput::request( 'force', false, FILTER_VALIDATE_BOOLEAN ) )
                            {
                                throw new BadRequestException( 'No filter or records given for delete request.' );
                            }

                            $_result = $this->truncateTable( $this->_resource, $_extras );
                        }
                        else
                        {
                            $_result = $this->deleteRecord( $this->_resource, $this->_requestData, $_extras );
                        }
                    }
                }
            }
        }
        else
        {
            $_result = $this->deleteRecordById( $this->_resource, $this->_resourceId, $_extras );
        }

        return $_result;
    }

    // Handle administrative options, table add, delete, etc

    /**
     * Get multiple tables and their properties
     *
     * @param string | array $tables Table names comma-delimited string or array
     *
     * @return array
     * @throws \Exception
     */
    public function getTables( $tables = array() )
    {
        $_tables = static::checkIncomingData(
            $tables,
            ',',
            true,
            'Request requires a table name, or a list or array of tables names or properties.'
        );

        $_out = array();
        foreach ( $_tables as $_table )
        {
            $_name = ( is_array( $_table ) ) ? Option::get( $_table, 'name' ) : $_table;
            $this->validateTableAccess( $_name );

            $_out[] = $this->getTable( $_table );
        }

        return $_out;
    }

    /**
     * Get any properties related to the table
     *
     * @param string | array $table Table name or defining properties
     *
     * @return array
     * @throws \Exception
     */
    abstract public function getTable( $table );

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
        $_out = array();
        foreach ( $tables as $_table )
        {
            $_out[] = $this->createTable( $_table );
        }

        return $_out;
    }

    /**
     * Create a single table by name and additional properties
     *
     * @param array $properties
     *
     * @throws \Exception
     */
    abstract public function createTable( $properties = array() );

    /**
     * Update one or more tables by array of table properties
     *
     * @param array $tables
     *
     * @return array
     * @throws \Exception
     */
    public function updateTables( $tables = array() )
    {
        $_out = array();
        foreach ( $tables as $_table )
        {
            $_name = ( is_array( $_table ) ) ? Option::get( $_table, 'name' ) : $_table;
            $this->validateTableAccess( $_name );
            $_out[] = $this->updateTable( $_table );
        }

        return $_out;
    }

    /**
     * Update properties related to the table
     *
     * @param array $properties
     *
     * @return array
     * @throws \Exception
     */
    abstract public function updateTable( $properties = array() );

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
        $_tables = static::checkIncomingData(
            $tables,
            ',',
            true,
            'Request requires a table name, or a list or array of tables names or properties.'
        );

        $_out = array();
        foreach ( $_tables as $_table )
        {
            $_name = ( is_array( $_table ) ) ? Option::get( $_table, 'name' ) : $_table;
            $this->validateTableAccess( $_name );
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
     * Delete all table entries but keep the table
     *
     * @param string $table
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    abstract public function truncateTable( $table, $extras = array() );

    // Handle table record operations

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    abstract public function createRecords( $table, $records, $extras = array() );

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
        $_records = static::checkIncomingData( $record, null, true, 'There are no fields in the record.' );

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
    abstract public function updateRecords( $table, $records, $extras = array() );

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
        $_records = static::checkIncomingData( $record, null, true, 'There are no fields in the record.' );

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
    abstract public function updateRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() );

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @return array
     */
    abstract public function updateRecordsByIds( $table, $record, $ids, $extras = array() );

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
        $record = static::checkIncomingData( $record, null, false, 'There are no fields in the record.' );

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
    abstract public function mergeRecords( $table, $records, $extras = array() );

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function mergeRecord( $table, $record, $extras = array() )
    {
        $_records = static::checkIncomingData( $record, null, true, 'There are no fields in the record.' );

        $_results = $this->mergeRecords( $table, $_records, $extras );

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
    abstract public function mergeRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() );

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    abstract public function mergeRecordsByIds( $table, $record, $ids, $extras = array() );

    /**
     * @param string $table
     * @param array  $record
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function mergeRecordById( $table, $record, $id, $extras = array() )
    {
        $record = static::checkIncomingData( $record, null, false, 'There are no fields in the record.' );

        $_results = $this->mergeRecordsByIds( $table, $record, $id, $extras );

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
    abstract public function deleteRecords( $table, $records, $extras = array() );

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
        $record = static::checkIncomingData( $record, null, false, 'There are no fields in the record.' );

        $_results = $this->deleteRecords( $table, array( $record ), $extras );

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
    abstract public function deleteRecordsByFilter( $table, $filter, $params = array(), $extras = array() );

    /**
     * @param string $table
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    abstract public function deleteRecordsByIds( $table, $ids, $extras = array() );

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
    abstract public function retrieveRecords( $table, $records, $extras = array() );

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
        $record = static::checkIncomingData( $record, null, false, 'There are no fields in the record.' );

        $_results = $this->retrieveRecords( $table, array( $record ), $extras );

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
    abstract public function retrieveRecordsByIds( $table, $ids, $extras = array() );

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
     * @return int
     */
    protected static function getMaxRecordsReturnedLimit()
    {
        return intval( Pii::getParam( 'dsp.db_max_records_returned', static::DB_MAX_RECORDS_RETURNED ) );
    }

    /**
     * @param array        $record
     * @param string|array $include List of keys to include in the output record
     * @param string       $id_field
     *
     * @return array
     */
    protected static function cleanRecord( $record, $include = '*', $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            $id_field = static::DEFAULT_ID_FIELD;
        }
        if ( '*' !== $include )
        {
            $_out = array();
            if ( empty( $include ) )
            {
                $include = $id_field;
            }
            if ( !is_array( $include ) )
            {
                $include = array_map( 'trim', explode( ',', trim( $include, ',' ) ) );
            }
            if ( false === array_search( $id_field, $include ) )
            {
                $include[] = $id_field;
            }
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
     * @param array  $records
     * @param string $id_field
     *
     * @return array
     * @throws BadRequestException
     */
    protected static function recordsAsIds( $records, $id_field = null )
    {
        $id_field = ( empty( $id_field ) ) ? static::DEFAULT_ID_FIELD : $id_field;
        $_ids = array();
        foreach ( $records as $_key => $_record )
        {
            $_id = Option::get( $_record, $id_field );
            if ( empty( $_id ) )
            {
                throw new BadRequestException( "Identifying field '$id_field' can not be empty for record index '$_key' request." );
            }

            $_ids[] = $_id;
        }

        return $_ids;
    }

    /**
     * @param        $ids
     * @param string $id_field
     *
     * @return array
     */
    protected static function idsAsRecords( $ids, $id_field = null )
    {
        $id_field = ( empty( $id_field ) ) ? static::DEFAULT_ID_FIELD : $id_field;
        $_out = array();
        foreach ( $ids as $_id )
        {
            $_out[] = array( $id_field => $_id );
        }

        return $_out;
    }

    protected static function removeIds( &$record, $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            $id_field = static::DEFAULT_ID_FIELD;
        }

        $id_field = Option::clean( $id_field );
        foreach ( $id_field as $_field )
        {
            unset( $record[$_field] );
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
        $id_field = ( empty( $id_field ) ) ? static::DEFAULT_ID_FIELD : $id_field;
        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }
        foreach ( $id_field as $_name )
        {
            $_temp = Option::get( $record, $_name );
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
        if ( false === $fields = static::checkIncomingData( $fields, ',' ) )
        {
            return false;
        }

        $id_field = ( empty( $id_field ) ) ? static::DEFAULT_ID_FIELD : $id_field;
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
        $id_field = ( empty( $id_field ) ) ? static::DEFAULT_ID_FIELD : $id_field;
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

        // filter string values should be wrapped in matching quotes
        if ( ( ( 0 === strpos( $value, '"' ) ) && ( ( strlen( $value ) - 1 ) === strrpos( $value, '"' ) ) ) ||
             ( ( 0 === strpos( $value, "'" ) ) && ( ( strlen( $value ) - 1 ) === strrpos( $value, "'" ) ) )
        )
        {
            return substr( $value, 1, ( strlen( $value ) - 1 ) );
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

        // the rest should be lookup keys
        if ( Session::getLookupValue( $value, $_result ) )
        {
            return $_result;
        }

        // todo Should we error here?
        return $value;
    }

    /**
     * @param array $array
     *
     * @return array
     */
    public static function one_index_array( $array )
    {
        if ( !is_array( $array ) || empty( $array ) )
        {
            return array();
        }

        array_unshift( $array, '' );
        unset( $array[0] );

        return $array;
    }

    /**
     * @param array | string $data          Array to check or comma-delimited string to convert
     * @param string | null  $str_delimiter Delimiter to check for string to array mapping, no op if null
     * @param boolean        $check_single  Check if single (associative) needs to be made multiple (numeric)
     * @param string | null  $on_fail       Error string to deliver in thrown exception
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array | boolean If requirements not met then throws exception if
     * $on_fail string given, or returns false. Otherwise returns valid array
     */
    public static function checkIncomingData( $data, $str_delimiter = null, $check_single = false, $on_fail = null )
    {
        if ( !empty( $data ) && !is_array( $data ) && ( is_string( $str_delimiter ) && !empty( $str_delimiter ) ) )
        {
            $data = array_map( 'trim', explode( $str_delimiter, trim( $data, $str_delimiter ) ) );
        }

        if ( !is_array( $data ) || empty( $data ) )
        {
            if ( !is_string( $on_fail ) || empty( $on_fail ) )
            {
                return false;
            }

            throw new BadRequestException( $on_fail );
        }

        if ( $check_single )
        {
            if ( !isset( $data[0] ) )
            {
                // single record possibly passed in without wrapper array
                $data = array( $data );
            }
        }

        return $data;
    }

    public static function getFromPostedData( $data, $marker, $default = null )
    {
        $_found = Option::get( $data, $marker );
        if ( empty( $_found ) )
        {
            // xml to array conversion leaves them in plural wrapper
            $_found = Option::getDeep( $data, Inflector::pluralize( $marker ), $marker );
        }

        return ( empty( $_found ) ) ? $default : $_found;
    }

    public static function startsWith( $haystack, $needle )
    {
        return ( substr( $haystack, 0, strlen( $needle ) ) === $needle );
    }

    public static function endsWith( $haystack, $needle )
    {
        return ( substr( $haystack, -strlen( $needle ) ) === $needle );
    }
}
