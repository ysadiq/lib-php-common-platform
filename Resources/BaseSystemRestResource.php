<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Services\BasePlatformRestService;
use DreamFactory\Platform\Services\BasePlatformService;
use DreamFactory\Platform\Utility\ResourceStore;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Seed;
use Kisma\Core\Utility\Option;
use Platform\Resources\UserSession;

/**
 * BaseSystemRestResource
 * A base service resource class to handle service resources of various kinds.
 */
abstract class BaseSystemRestResource extends BasePlatformRestResource
{
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

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * Create a new service
	 *
	 * @param BasePlatformService $consumer
	 * @param array               $settings      configuration array
	 * @param array               $resourceArray Or you can pass in through $settings['resource_array'] = array(...)
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( $consumer, $settings = array(), $resourceArray = array() )
	{
		$this->_resourceArray = empty( $resourceArray ) ? Option::get( $settings, 'resource_array', array(), true ) : array();

		parent::__construct( $consumer, $settings );
	}

	/**
	 * Apply the commonly used REST path members to the class
	 */
	protected function _detectResourceMembers()
	{
		parent::_detectResourceMembers();

		$this->_resourceId = Option::get( $this->_resourceArray, 1 );
	}

	/**
	 * @return bool
	 */
	protected function _preProcess()
	{
		//	Most requests contain 'returned fields' parameter, all by default
		$this->_extras = array();
		$this->_fields = Option::get( $_REQUEST, 'fields', '*' );

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
				 'resource_name'    => $this->_apiName,
				 'resource_array'   => $this->_resourceArray,
				 'resource_id'      => $this->_resourceId,
				 'related_resource' => $this->_relatedResource,
				 'fields'           => $this->_fields,
				 'extras'           => $this->_extras,
			)
		);
	}

	/**
	 * @param array $ids     IDs returned here
	 * @param array $records Records returned here
	 *
	 * @return array The payload operated upon
	 */
	protected function _determineRequestedResource( &$ids = null, &$records = null )
	{
		//	Which payload do we love?
		$_payload = RestRequest::getPostDataAsArray();

		//	Use $_REQUEST instead of POSTed data
		if ( empty( $_payload ) )
		{
			$_payload = $_REQUEST;
		}

		//	Multiple resources by ID
		$ids = Option::get( $_payload, 'ids' );
		$records = Option::get( $_payload, 'record', Option::getDeep( $_payload, 'records', 'record' ) );

		return $_payload;
	}

	/**
	 * @return bool
	 */
	protected function _handleGet()
	{
		//	Single resource by ID
		if ( !empty( $this->_resourceId ) )
		{
			return ResourceStore::select( $this->_resourceId );
		}

		$_payload = $this->_determineRequestedResource( $_ids, $_records );

		//	Multiple resources by ID
		if ( !empty( $_ids ) )
		{
			return ResourceStore::bulkSelectById( $_ids );
		}

		if ( !empty( $_records ) )
		{
			$_pk = ResourceStore::model()->primaryKey;
			$_ids = array();

			foreach ( $_records as $_record )
			{
				$_ids[] = Option::get( $_record, $_pk );
			}

			return ResourceStore::bulkSelectById( implode( ',', $_ids ) );
		}

		//	Otherwise return the resources
		return ResourceStore::select(
			null,
			array(
				 'condition' => Option::get( $_payload, 'filter' ),
				 'limit'     => Option::get( $_payload, 'limit', 0 ),
				 'order'     => Option::get( $_payload, 'order' ),
				 'offset'    => Option::get( $_payload, 'offset', 0 ),
			),
			array(),
			Option::getBool( $_payload, 'include_count' ),
			Option::getBool( $_payload, 'include_schema' )
		);
	}

	/**
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return bool
	 */
	protected function _handlePut()
	{
		$_payload = $this->_determineRequestedResource( $_ids, $_records );
		$_rollback = Option::getBool( $_payload, 'rollback' );

		if ( !empty( $this->_resourceId ) )
		{
			return ResourceStore::bulkUpdateById( $this->_resourceId, $_payload, $_rollback );
		}

		if ( !empty( $_ids ) )
		{
			return ResourceStore::bulkUpdateById( $_ids, $_payload, $_rollback );
		}

		if ( !empty( $_records ) )
		{
			return ResourceStore::bulkUpdate( $_records, $_rollback );
		}

		if ( empty( $_payload ) )
		{
			throw new BadRequestException( 'No record in PUT update request.' );
		}

		return ResourceStore::update( $_payload );
	}

	/**
	 * @return bool
	 */
	protected function _handlePatch()
	{
		return $this->_handlePut();
	}

	/**
	 * @return bool
	 */
	protected function _handleMerge()
	{
		return $this->_handlePut();
	}

	/**
	 * @return array|bool
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _handlePost()
	{
		$_payload = $this->_determineRequestedResource( $_ids, $_records );

		if ( !empty( $_records ) )
		{
			return ResourceStore::bulkInsert( $_records, Option::getBool( $_payload, 'rollback' ) );
		}

		if ( empty( $_payload ) )
		{
			throw new BadRequestException( 'No record in POST create request.' );
		}

		return ResourceStore::insert( $_payload );
	}

	/**
	 * @return bool|void
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected function _handleDelete()
	{
		if ( !empty( $this->_resourceId ) )
		{
			return ResourceStore::bulkDeleteById( $this->_resourceId );
		}

		$_payload = $this->_determineRequestedResource( $_ids, $_records );

		if ( !empty( $_ids ) )
		{
			return ResourceStore::bulkDeleteById( $_ids );
		}

		if ( !empty( $_records ) )
		{
			return ResourceStore::bulkDelete( $_records );
		}

		if ( empty( $_payload ) )
		{
			throw new BadRequestException( "Id list or record containing Id field required to delete $this->_apiName records." );
		}

		return ResourceStore::delete( $_payload );
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
}