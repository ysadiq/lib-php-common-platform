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

use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Components\DataTablesFormatter;
use DreamFactory\Platform\Components\JTablesFormatter;
use DreamFactory\Platform\Enums\ResponseFormats;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Interfaces\RestResourceLike;
use DreamFactory\Platform\Interfaces\RestServiceLike;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\SqlDbUtilities;
use DreamFactory\Platform\Yii\Models\BasePlatformSystemModel;
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
     * Default record wrapping tag for single or array of records
     */
    const RECORD_WRAPPER = 'record';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array
     */
    protected $_resourceArray;
    /**
     * @var int|string
     */
    protected $_resourceId = null;
    /**
     * @var array
     */
    protected $_requestData = null;

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
        $this->_resourceArray = $resourceArray ? : Option::get( $settings, 'resource_array', array(), true );

        //	Default service name if not supplied. Should work for subclasses by defining the constant in your class
        $settings['service_name'] = $this->_serviceName ? : Option::get( $settings, 'service_name', static::DEFAULT_SERVICE_NAME, true );

        //	Default verb aliases for all system resources
        $settings['verb_aliases'] = $this->_verbAliases
            ? : array_merge(
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
     * @return $this|void
     */
    protected function _detectResourceMembers( $resourcePath = null )
    {
        parent::_detectResourceMembers( $resourcePath );

        $this->_resourceId = Option::get( $this->_resourceArray, 1 );

        $_posted = Option::clean( RestData::getPostedData( true, true ) );
        if ( !empty( $this->_resourceId ) )
        {
            if ( !empty( $_posted ) )
            {
                // single records don't use the record wrapper, so wrap it
                $_posted = array(static::RECORD_WRAPPER => $_posted);
            }
        }
        elseif ( DataFormat::isArrayNumeric( $_posted ) )
        {
            // import from csv, etc doesn't include a wrapper, so wrap it
            $_posted = array(static::RECORD_WRAPPER => $_posted);
        }

        // MERGE URL parameters with posted data, posted data takes precedence
        $this->_requestData = array_merge( $_REQUEST, $_posted );

        // Add server side filtering properties
        if ( null != $_ssFilters = Session::getServiceFilters( $this->_apiName, $this->_resource ) )
        {
            $this->_requestData['ss_filters'] = $_ssFilters;
        }

        // look for limit, accept top as well as limit
        if ( !isset( $this->_requestData['limit'] ) && ( $_limit = Option::get( $this->_requestData, 'top' ) ) )
        {
            $this->_requestData['limit'] = $_limit;
        }

        // accept skip as well as offset
        if ( !isset( $this->_requestData['offset'] ) && ( $_offset = Option::get( $this->_requestData, 'skip' ) ) )
        {
            $this->_requestData['offset'] = $_offset;
        }

        // accept sort as well as order
        if ( !isset( $this->_requestData['order'] ) && ( $_order = Option::get( $this->_requestData, 'sort' ) ) )
        {
            $this->_requestData['order'] = $_order;
        }

        // All calls can request related data to be returned
        $_related = Option::get( $this->_requestData, 'related' );
        if ( !empty( $_related ) && is_string( $_related ) && ( '*' !== $_related ) )
        {
            $_relations = array();
            if ( !is_array( $_related ) )
            {
                $_related = array_map( 'trim', explode( ',', $_related ) );
            }
            foreach ( $_related as $_relative )
            {
                $_extraFields = Option::get( $this->_requestData, $_relative . '_fields', '*' );
                $_extraOrder = Option::get( $this->_requestData, $_relative . '_order', '' );
                $_relations[] = array('name' => $_relative, 'fields' => $_extraFields, 'order' => $_extraOrder);
            }

            $this->_requestData['related'] = $_relations;
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
        $this->checkPermission( $this->getRequestedAction(), $this->_resource );

        ResourceStore::reset(
            array(
                'service'        => $this->_serviceName,
                'resource_name'  => $this->_apiName,
                'resource_id'    => $this->_resourceId,
                'resource_array' => $this->_resourceArray,
                'payload'        => $this->_requestData,
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
        // default for GET should be "return all fields"
        $_fields = Option::get( $this->_requestData, 'fields' );
        if ( empty( $_fields ) )
        {
            $this->_requestData['fields'] = '*';
            ResourceStore::setPayload($this->_requestData);
        }

        //	Single resource by ID
        if ( !empty( $this->_resourceId ) )
        {
            return ResourceStore::select( $this->_resourceId, null, array(), true );
        }

        $_ids = Option::get( $this->_requestData, 'ids', null, true );

        //	Multiple resources by ID
        if ( !empty( $_ids ) )
        {
            return ResourceStore::bulkSelectById( $_ids );
        }

        $_records = Option::get( $this->_requestData, static::RECORD_WRAPPER, null, true );

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

        if ( null !== ( $_value = Option::get( $this->_requestData, 'fields' ) ) )
        {
            $_criteria['select'] = $_value;
        }

        if ( null !== ( $_value = Option::get( $this->_requestData, 'params' ) ) )
        {
            $_criteria['params'] = $_value;
        }

        if ( null !== ( $_value = Option::get( $this->_requestData, 'filter' ) ) )
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

        if ( null !== ( $_value = Option::get( $this->_requestData, 'limit' ) ) )
        {
            $_criteria['limit'] = $_value;

            if ( null !== ( $_value = Option::get( $this->_requestData, 'offset' ) ) )
            {
                $_criteria['offset'] = $_value;
            }
        }

        if ( null !== ( $_value = Option::get( $this->_requestData, 'order' ) ) )
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
        $_amnesty = false;
        $_records = Option::get( $this->_requestData, static::RECORD_WRAPPER, null, true );
        if ( empty( $_records ) )
        {
            // amnesty for the illegals, single records don't use the record wrapper, so wrap it
            $_records = Option::clean( RestData::getPostedData( true, true ) );
            if ( empty( $_records ) )
            {
                throw new BadRequestException( 'No record(s) detected in request.' );
            }

            // stuff it back in for event
            $_amnesty = true;
            $this->_requestData[static::RECORD_WRAPPER] = $_records;
        }

        $this->_triggerActionEvent( $this->_requestData );

        if ( !empty( $this->_resourceId ) )
        {
            throw new BadRequestException( 'Create record by identifier not currently supported.' );
        }

        if ( $_amnesty )
        {
            return ResourceStore::insertOne( $_records, $this->_requestData );
        }

        return ResourceStore::insert( $_records, $this->_requestData );
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
        $_amnesty = false;
        $_records = Option::get( $this->_requestData, static::RECORD_WRAPPER, null, true );
        if ( empty( $_records ) )
        {
            // amnesty for the illegals, single records don't use the record wrapper, so wrap it
            $_records = Option::clean( RestData::getPostedData( true, true ) );
            if ( empty( $_records ) )
            {
                throw new BadRequestException( 'No record(s) detected in request.' );
            }

            // stuff it back in for event
            $_amnesty = true;
            $this->_requestData[static::RECORD_WRAPPER] = $_records;
        }

        $this->_triggerActionEvent( $this->_requestData );

        if ( !empty( $this->_resourceId ) )
        {
            return ResourceStore::updateById( $this->_resourceId, $_records, $this->_requestData );
        }

        $_ids = Option::get( $this->_requestData, 'ids', null, true );

        if ( !empty( $_ids ) )
        {
            return ResourceStore::updateByIds( $_ids, $_records, $this->_requestData );
        }

        if ( $_amnesty )
        {
            return ResourceStore::updateOne( $_records, $this->_requestData );
        }

        return ResourceStore::update( $_records, $this->_requestData );
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
        $this->_triggerActionEvent( $this->_requestData );

        if ( !empty( $this->_resourceId ) )
        {
            return ResourceStore::deleteById( $this->_resourceId, $this->_requestData );
        }

        $_ids = Option::get( $this->_requestData, 'ids', null, true );
        if ( !empty( $_ids ) )
        {
            return ResourceStore::deleteByIds( $_ids, $this->_requestData );
        }

        $_records = Option::get( $this->_requestData, static::RECORD_WRAPPER, null, true );
        if ( !empty( $_records ) )
        {
            return ResourceStore::delete( $_records, $this->_requestData );
        }

        return ResourceStore::deleteOne( $this->_requestData );
    }

    /**
     * Formats the output
     */
    protected function _formatResponse()
    {
        parent::_formatResponse();

        $_data = $this->_response;

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
     * @param array $resourceArray
     *
     * @return BaseSystemRestResource
     */
    public function setResourceArray( $resourceArray )
    {
        $this->_resourceArray = $resourceArray;

        return $this;
    }

    /**
     * @return array
     */
    public function getResourceArray()
    {
        return $this->_resourceArray;
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
     * @param int $responseFormat
     *
     * @return BaseSystemRestResource
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
        return SqlDbUtilities::describeTable( $resource->getDb(), $resource->tableName(), $resource->tableNamePrefix() );
    }
}