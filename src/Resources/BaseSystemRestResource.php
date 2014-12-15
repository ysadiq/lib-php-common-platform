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
namespace DreamFactory\Platform\Resources;

use DreamFactory\Platform\Components\DataTablesFormatter;
use DreamFactory\Platform\Components\JTablesFormatter;
use DreamFactory\Platform\Enums\ResponseFormats;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Interfaces\RestResourceLike;
use DreamFactory\Platform\Interfaces\RestServiceLike;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\DataFormatter;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\SqlDbUtilities;
use DreamFactory\Platform\Yii\Models\BasePlatformSystemModel;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Option;

/**
 * BaseSystemRestResource
 * A base service resource class to handle service resources of various kinds.
 */
abstract class BaseSystemRestResource extends BasePlatformRestResource
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string
     */
    const DEFAULT_SERVICE_NAME = 'system';
    /**
     * Default maximum records returned on filter request
     */
    const MAX_RECORDS_RETURNED = 1000;
    /**
     * Default record wrapping tag for single or array of records
     */
    const RECORD_WRAPPER = 'record';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var int|string
     */
    protected $_resourceId = null;

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
     * Create a new service
     *
     * @param RestServiceLike|RestResourceLike $consumer
     * @param array                            $settings      configuration array
     * @param array                            $resourceArray Or you can pass in through $settings['resource_array'] = array(...)
     *
     * @throws \InvalidArgumentException
     */
    public function __construct( $consumer, $settings = array(), $resourceArray = array() )
    {
        $this->_resourceArray = $resourceArray ?: Option::get( $settings, 'resource_array', array(), true );

        //	Default service name if not supplied. Should work for subclasses by defining the constant in your class
        $settings['service_name'] = $this->_serviceName ?: Option::get( $settings, 'service_name', static::DEFAULT_SERVICE_NAME, true );

        //	Default verb aliases for all system resources
        $settings['verb_aliases'] = $this->_verbAliases
            ?: array_merge(
                array(
                    static::PATCH => static::PUT,
                    static::MERGE => static::PUT,
                ),
                Option::get( $settings, 'verb_aliases', array(), true )
            );

        parent::__construct( $consumer, $settings );
    }

    /**
     * @param string $resource
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return BasePlatformSystemModel
     */
    public static function model( $resource = null )
    {
        return ResourceStore::model( $resource );
    }

    /**
     * @param string $ids
     * @param string $fields
     * @param array  $extras
     * @param bool   $singleRow
     * @param bool   $includeSchema
     * @param bool   $includeCount
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \CDbException
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return array
     */
    public static function select( $ids = null, $fields = null, $extras = array(), $singleRow = false, $includeSchema = false, $includeCount = false )
    {
        return ResourceStore::bulkSelectById(
            $ids,
            empty( $fields ) ? null : array('select' => $fields),
            $extras,
            $singleRow,
            $includeSchema,
            $includeCount
        );
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
        $_result = parent::_detectResourceMembers( $resourcePath );

        $this->_resourceId = Option::get( $this->_resourceArray, 1 );

        return $_result;
    }

    /**
     * Apply the commonly used REST path members to the class
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return $this
     */
    protected function _detectRequestMembers()
    {
        // override - don't call parent class here
        $_posted = Option::clean( RestData::getPostedData( true, true ) );
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

        if ( 'config' !== $this->_resource )
        {
            // Add server side filtering properties
            if ( null != $_ssFilters = Session::getServiceFilters( $this->_action, $this->_apiName, $this->_resource ) )
            {
                $this->_requestPayload['ss_filters'] = $_ssFilters;
            }
        }

        // look for limit, accept top as well as limit
        if ( !isset( $this->_requestPayload['limit'] ) && ( $_limit = Option::get( $this->_requestPayload, 'top' ) ) )
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
        if ( !isset( $this->_requestPayload['order'] ) && ( $_order = Option::get( $this->_requestPayload, 'sort' ) ) )
        {
            $this->_requestPayload['order'] = $_order;
        }

        // All calls can request related data to be returned
        $_related = Option::get( $this->_requestPayload, 'related' );
        if ( !empty( $_related ) && is_string( $_related ) && ( '*' !== $_related ) )
        {
            $_relations = array();
            if ( !is_array( $_related ) )
            {
                $_related = array_map( 'trim', explode( ',', $_related ) );
            }
            foreach ( $_related as $_relative )
            {
                $_extraFields = Option::get( $this->_requestPayload, $_relative . '_fields', '*' );
                $_extraOrder = Option::get( $this->_requestPayload, $_relative . '_order', '' );
                $_relations[] = array('name' => $_relative, 'fields' => $_extraFields, 'order' => $_extraOrder);
            }

            $this->_requestPayload['related'] = $_relations;
        }

        return $this;
    }

    /**
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
     * @return bool
     */
    protected function _preProcess()
    {
        parent::_preProcess();

        //	Do validation here
        $this->checkPermission( $this->_action, $this->_resource );

        ResourceStore::reset(
            array(
                'service'        => $this->_serviceName,
                'resource_name'  => $this->_apiName,
                'resource_id'    => $this->_resourceId,
                'resource_array' => $this->_resourceArray,
                'payload'        => $this->_requestPayload,
            )
        );
    }

    /**
     * @param string $operation
     * @param string $resource
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
     * @throws \Exception
     * @return bool
     */
    public function checkPermission( $operation, $resource = null )
    {
        return ResourceStore::checkPermission( $operation, $this->_serviceName, $resource );
    }

    /**
     * Default GET implementation
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \Exception
     * @return bool
     */
    protected function _handleGet()
    {
        //	Single resource by ID
        if ( !empty( $this->_resourceId ) )
        {
            return ResourceStore::select( $this->_resourceId, null, array(), true );
        }

        $_ids = Option::get( $this->_requestPayload, 'ids' );

        //	Multiple resources by ID
        if ( !empty( $_ids ) )
        {
            return ResourceStore::bulkSelectById( $_ids );
        }

        $_records = Option::get( $this->_requestPayload, static::RECORD_WRAPPER );

        if ( !empty( $_records ) )
        {
            $_pk = static::model()->primaryKey;
            $_ids = array();

            foreach ( $_records as $_record )
            {
                $_ids[] = Option::get( $_record, $_pk );
            }

            return ResourceStore::bulkSelectById( $_ids );
        }

        //	Build our criteria
        $_criteria = array(
            'params' => array(),
        );

        if ( null !== ( $_value = Option::get( $this->_requestPayload, 'fields' ) ) )
        {
            $_criteria['select'] = $_value;
        }

        if ( null !== ( $_value = Option::get( $this->_requestPayload, 'params' ) ) )
        {
            $_criteria['params'] = $_value;
        }

        if ( null !== ( $_value = Option::get( $this->_requestPayload, 'filter' ) ) )
        {
            $_criteria['condition'] = $_value;

            //	Add current user ID into parameter array if in condition, but not specified.
            if ( false !== stripos( $_value, ':user_id' ) )
            {
                if ( !isset( $_criteria['params'][':user_id'] ) )
                {
                    $_criteria['params'][':user_id'] = Session::getCurrentUserId();
                }
            }
        }

        $_value = intval( Option::get( $this->_requestPayload, 'limit' ) );
        $_maxAllowed = intval( Pii::getParam( 'dsp.db_max_records_returned', static::MAX_RECORDS_RETURNED ) );
        if ( ( $_value < 1 ) || ( $_value > $_maxAllowed ) )
        {
            // impose a limit to protect server
            $_value = $_maxAllowed;
        }
        $_criteria['limit'] = $_value;

        if ( null !== ( $_value = Option::get( $this->_requestPayload, 'offset' ) ) )
        {
            $_criteria['offset'] = $_value;
        }

        if ( null !== ( $_value = Option::get( $this->_requestPayload, 'order' ) ) )
        {
            $_criteria['order'] = $_value;
        }

        return ResourceStore::select( null, $_criteria, array(), false );
    }

    /**
     * Default POST implementation
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array|bool
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

        if ( $this->_singleRecordAmnesty )
        {
            return ResourceStore::bulkInsert( $_records, $this->_requestPayload, true );
        }

        return ResourceStore::insert( $_records, $this->_requestPayload );
    }

    /**
     * Default PUT implementation
     *
     * @throws \InvalidArgumentException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     * @throws \CDbException
     * @throws \LogicException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return bool
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

            return ResourceStore::updateById( $this->_resourceId, $_record, $this->_requestPayload );
        }

        $_ids = Option::get( $this->_requestPayload, 'ids' );

        if ( !empty( $_ids ) )
        {
            $_record = Option::get( $_records, 0, $_records );

            return ResourceStore::updateByIds( $_ids, $_record, $this->_requestPayload );
        }

        if ( $this->_singleRecordAmnesty )
        {
            return ResourceStore::bulkUpdate( $_records, $this->_requestPayload, true );
        }

        return ResourceStore::update( $_records, $this->_requestPayload );
    }

    /**
     * Default PATCH implementation
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return bool
     */
    protected function _handlePatch()
    {
        throw new BadRequestException();
    }

    /**
     * Default MERGE implementation
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return bool
     */
    protected function _handleMerge()
    {
        throw new BadRequestException();
    }

    /**
     * Default DELETE implementation
     *
     * @throws \InvalidArgumentException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     * @throws \CDbException
     * @throws \LogicException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return bool|void
     */
    protected function _handleDelete()
    {
        $this->_triggerActionEvent( $this->_response );

        if ( !empty( $this->_resourceId ) )
        {
            return ResourceStore::deleteById( $this->_resourceId, $this->_requestPayload );
        }

        $_ids = Option::get( $this->_requestPayload, 'ids' );
        if ( !empty( $_ids ) )
        {
            return ResourceStore::deleteByIds( $_ids, $this->_requestPayload );
        }

        $_records = Option::get( $this->_requestPayload, static::RECORD_WRAPPER );
        if ( !empty( $_records ) )
        {
            return ResourceStore::delete( $_records, $this->_requestPayload );
        }

        return ResourceStore::deleteOne( $this->_requestPayload );
    }

    /**
     * Formats the output
     */
    protected function _formatResponse()
    {
        parent::_formatResponse();

        $_data = $this->_response;

        try
        {
            $_schema = ResourceStore::getSchemaForPayload( ResourceStore::model() );
            $_schemaFields = Option::get( $_schema, 'field', array() );
            if ( !empty( $_schemaFields ) && !empty( $_data ) )
            {
                //  Additional formatting needed?
                foreach ( $_data as $_key => &$_row )
                {
                    if ( is_array( $_row ) )
                    {
                        if ( isset( $_row[0] ) )
                        {
                            //  Multi-row set, dig a little deeper
                            foreach ( $_row as &$_sub )
                            {
                                if ( is_array( $_sub ) )
                                {
                                    foreach ( $_sub as $_name => $_value )
                                    {
                                        if ( !is_null( $_value ) && !is_array( $_value ) )
                                        {
                                            if ( false !== $_fieldInfo = SqlDbUtilities::getFieldFromDescribe( $_name, $_schemaFields ) )
                                            {
                                                if ( null !== $_type = Option::get( $_fieldInfo, 'type' ) )
                                                {
                                                    $_type = SqlDbUtilities::determinePhpConversionType( $_type );
                                                    $_sub[$_name] = SqlDbUtilities::formatValue( $_value, $_type );
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        else
                        {
                            foreach ( $_row as $_name => $_value )
                            {
                                if ( !is_null( $_value ) && !is_array( $_value ) )
                                {
                                    if ( false !== $_fieldInfo = SqlDbUtilities::getFieldFromDescribe( $_name, $_schemaFields ) )
                                    {
                                        if ( null !== $_type = Option::get( $_fieldInfo, 'type' ) )
                                        {
                                            $_type = SqlDbUtilities::determinePhpConversionType( $_type );
                                            $_row[$_name] = SqlDbUtilities::formatValue( $_value, $_type );
                                        }
                                    }
                                }
                            }
                        }
                    }
                    elseif ( !is_null( $_row ) && !is_array( $_row ) )
                    {
                        if ( false !== $_fieldInfo = SqlDbUtilities::getFieldFromDescribe( $_key, $_schemaFields ) )
                        {
                            if ( null !== $_type = Option::get( $_fieldInfo, 'type' ) )
                            {
                                $_type = SqlDbUtilities::determinePhpConversionType( $_type );
                                $_row = SqlDbUtilities::formatValue( $_row, $_type );
                            }
                        }
                    }
                }
            }
        }
        catch (\Exception $_ex)
        {
            // do nothing, not a model with schema
        }

        switch ( $this->_responseFormat )
        {
            case ResponseFormats::DATATABLES:
                $_data = DataTablesFormatter::format( $_data );
                break;

            case ResponseFormats::JTABLE:
                $_data = JTablesFormatter::format( $_data, array('action' => $this->_action) );
                break;
        }

        $this->_response = $_data;
    }

    /**
     * @param int $resourceId
     *
     * @return BaseSystemRestResource
     */
    public function setResourceId( $resourceId )
    {
        $this->_resourceId = $resourceId;

        return $this;
    }

    /**
     * @return int
     */
    public function getResourceId()
    {
        return $this->_resourceId;
    }

    /**
     * @param BasePlatformSystemModel $resource
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \CDbException
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @throws \Exception
     * @return mixed
     */
    public function getSchema( $resource )
    {
        return SqlDbUtilities::describeTable(
            null,
            $resource->getDb(),
            $resource->tableName(),
            $resource->tableNamePrefix()
        );
    }
}