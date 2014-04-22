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
    //* Constants
    //*************************************************************************

    /**
     * @var string
     */
    const DEFAULT_SERVICE_NAME = 'system';

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var array
     */
    protected $_resourceArray;
    /**
     * @var int
     */
    protected $_resourceId;
    /**
     * @var string
     */
    protected $_relatedResource;
    /**
     * @var array
     */
    protected $_fields;
    /**
     * @var array
     */
    protected $_extras;
    /**
     * @var bool
     */
    protected $_includeSchema = false;
    /**
     * @var bool
     */
    protected $_includeCount = false;

    //*************************************************************************
    //* Methods
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
            empty( $fields ) ? null : array( 'select' => $fields ),
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

        //	Most requests contain 'returned fields' parameter, all by default
        $this->_extras = array();
        $this->_fields = Option::get( $_REQUEST, 'fields', '*' );
        $this->_includeSchema = Option::getBool( $_REQUEST, 'include_schema', false );
        $this->_includeCount = Option::getBool( $_REQUEST, 'include_count', false );

        $_related = Option::get( $_REQUEST, 'related' );

        if ( !empty( $_related ) )
        {
            $_related = array_map( 'trim', explode( ',', $_related ) );

            foreach ( $_related as $_relative )
            {
                $this->_extras[] = array(
                    'name'   => $_relative,
                    'fields' => Option::get( $_REQUEST, $_relative . '_fields', '*' ),
                    'order'  => Option::get( $_REQUEST, $_relative . '_order' ),
                );
            }
        }

        ResourceStore::reset(
            array(
                'service'          => $this->_serviceName,
                'resource_name'    => $this->_apiName,
                'resource_id'      => $this->_resourceId,
                'resource_array'   => $this->_resourceArray,
                'related_resource' => $this->_relatedResource,
                'fields'           => $this->_fields,
                'extras'           => $this->_extras,
                'include_count'    => $this->_includeCount,
                'include_schema'   => $this->_includeSchema,
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
     * @param array $ids          IDs returned here
     * @param array $records      Records returned here
     * @param bool  $triggerEvent If true, the default, the action event will be triggered with the posted data
     *
     * @throws \Exception
     * @return array The payload operated upon
     */
    protected function _determineRequestedResource( &$ids = null, &$records = null, $triggerEvent = true )
    {
        //	Which payload do we love?
        $_payload = RestData::getPostedData( true, true );

        //	Use $_REQUEST instead of POSTed data
        if ( empty( $_payload ) )
        {
            $_payload = $_REQUEST;
        }

        //  Run the event on the payload before we pull data out...
        if ( $triggerEvent )
        {
            $this->_triggerActionEvent( $_payload );
        }

        //	Multiple resources by ID
        $ids = Option::get( $_payload, 'ids' );
        $records = Option::get( $_payload, 'record', Option::getDeep( $_payload, 'records', 'record' ) );

        return $_payload;
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

        $_singleRow = false;

        //  No event here, triggered in handleResource
        $_payload = $this->_determineRequestedResource( $_ids, $_records, false );

        //	Multiple resources by ID
        if ( !empty( $_ids ) )
        {
            return ResourceStore::bulkSelectById( $_ids );
        }

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

        if ( !empty( $this->_fields ) )
        {
            $_criteria['select'] = $this->_fields;
        }

        if ( null !== ( $_value = Option::get( $_payload, 'params' ) ) )
        {
            $_criteria['params'] = $_value;
        }

        if ( null !== ( $_value = Option::get( $_payload, 'filter' ) ) )
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

        if ( null !== ( $_value = Option::get( $_payload, 'limit' ) ) )
        {
            $_criteria['limit'] = $_value;

            if ( null !== ( $_value = Option::get( $_payload, 'offset' ) ) )
            {
                $_criteria['offset'] = $_value;
            }
        }

        if ( null !== ( $_value = Option::get( $_payload, 'order' ) ) )
        {
            $_criteria['order'] = $_value;
        }

        return ResourceStore::select(
            null,
            $_criteria,
            array(),
            $_singleRow,
            Option::getBool( $_payload, 'include_count' ),
            Option::getBool( $_payload, 'include_schema' )
        );
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
        $_payload = $this->_determineRequestedResource( $_ids, $_records );
        $_rollback = Option::getBool( $_payload, 'rollback' );
        $_continue = Option::getBool( $_payload, 'continue' );

        if ( !empty( $this->_resourceId ) )
        {
            return ResourceStore::bulkUpdateById( $this->_resourceId, $_payload, $_rollback, null, null, true, $_continue );
        }

        if ( !empty( $_ids ) )
        {
            return ResourceStore::bulkUpdateById( $_ids, $_payload, $_rollback, null, null, false, $_continue );
        }

        if ( !empty( $_records ) )
        {
            return ResourceStore::bulkUpdate( $_records, $_rollback, null, null, false, $_continue );
        }

        if ( empty( $_payload ) )
        {
            throw new BadRequestException( 'No record in PUT update request.' );
        }

        return ResourceStore::updateOne( $_payload );
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
        $_payload = $this->_determineRequestedResource( $_ids, $_records );

        if ( !empty( $_records ) )
        {
            $_rollback = Option::getBool( $_payload, 'rollback' );
            $_continue = Option::getBool( $_payload, 'continue' );

            return ResourceStore::insert( $_records, $_rollback, null, null, $_continue );
        }

        if ( empty( $_payload ) )
        {
            throw new BadRequestException( 'No record in POST create request.' );
        }

        return ResourceStore::insertOne( $_payload );
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
        $_payload = $this->_determineRequestedResource( $_ids, $_records );

        if ( !empty( $this->_resourceId ) )
        {
            return ResourceStore::bulkDeleteById( $this->_resourceId, false, null, null, true );
        }

        $_rollback = Option::getBool( $_payload, 'rollback' );
        $_continue = Option::getBool( $_payload, 'continue' );
        if ( !empty( $_ids ) )
        {
            return ResourceStore::bulkDeleteById( $_ids, $_rollback, null, null, false, $_continue );
        }

        if ( !empty( $_records ) )
        {
            return ResourceStore::delete( $_records, $_rollback, null, null, $_continue );
        }

        if ( empty( $_payload ) )
        {
            throw new BadRequestException( "Id list or record containing Id field required to delete $this->_apiName records." );
        }

        return ResourceStore::deleteOne( $_payload );
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
                $_data = JTablesFormatter::format( $_data, array( 'action' => $this->_action ) );
                break;
        }

        $this->_response = $_data;
    }

    /**
     * @param string $relatedResource
     *
     * @return BaseSystemRestResource
     */
    public function setRelatedResource( $relatedResource )
    {
        $this->_relatedResource = $relatedResource;

        return $this;
    }

    /**
     * @return string
     */
    public function getRelatedResource()
    {
        return $this->_relatedResource;
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
     * @param array $extras
     *
     * @return BaseSystemRestResource
     */
    public function setExtras( $extras )
    {
        $this->_extras = $extras;

        return $this;
    }

    /**
     * @return array
     */
    public function getExtras()
    {
        return $this->_extras;
    }

    /**
     * @param array $fields
     *
     * @return BaseSystemRestResource
     */
    public function setFields( $fields )
    {
        $this->_fields = $fields;

        return $this;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->_fields;
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
     * @param boolean $includeCount
     *
     * @return BaseSystemRestResource
     */
    public function setIncludeCount( $includeCount )
    {
        $this->_includeCount = $includeCount;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getIncludeCount()
    {
        return $this->_includeCount;
    }

    /**
     * @param boolean $includeSchema
     *
     * @return BaseSystemRestResource
     */
    public function setIncludeSchema( $includeSchema )
    {
        $this->_includeSchema = $includeSchema;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getIncludeSchema()
    {
        return $this->_includeSchema;
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