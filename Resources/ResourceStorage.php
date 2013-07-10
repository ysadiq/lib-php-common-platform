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
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Services\BasePlatformRestService;
use DreamFactory\Platform\Yii\Models\BasePlatformSystemModel;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Seed;
use Kisma\Core\SeedUtility;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Platform\Resources\UserSession;

/**
 * ResourceStorage
 * A base service resource class to handle service resources of various kinds.
 */
class ResourceStorage extends SeedUtility
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var array
	 */
	protected static $_resourceArray;
	/**
	 * @var int
	 */
	protected static $_resourceId;
	/**
	 * @var string
	 */
	protected static $_resourceName;
	/**
	 * @var string
	 */
	protected static $_relatedResource;
	/**
	 * @var array
	 */
	protected static $_fields;
	/**
	 * @var array
	 */
	protected static $_extras;
	/**
	 * @var string Our service name
	 */
	protected static $_service = 'system';

	//************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param array $settings The settings to reset to
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	public static function reset( $settings = array() )
	{
		static::$_resourceArray = Option::get( $settings, 'resource_array' );
		static::$_resourceId = Option::get( $settings, 'resource_id' );
		static::$_resourceName = Option::get( $settings, 'resource_name' );
		static::$_relatedResource = Option::get( $settings, 'related_resource' );
		static::$_fields = Option::get( $settings, 'fields' );
		static::$_extras = Option::get( $settings, 'extras' );

		if ( empty( static::$_resourceName ) )
		{
			throw new BadRequestException( 'Resource name can not be empty.' );
		}
	}

	/**
	 * @param        $records
	 * @param bool   $rollback
	 *
	 * @throws BadRequestException
	 * @return array
	 */
	public static function bulkInsert( $records, $rollback = false )
	{
		if ( empty( $records ) )
		{
			throw new BadRequestException( 'There are no record sets in the request.' );
		}

		if ( !isset( $records[0] ) )
		{
			// conversion from xml can pull single record out of array format
			$records = array( $records );
		}

		static::_permissionCheck( 'create' );

		$_response = array();
		$_transaction = null;

		try
		{
			//	Start a transaction
			if ( false !== $rollback )
			{
				$_transaction = Pii::db()->beginTransaction();
			}

			foreach ( $records as $_record )
			{
				try
				{
					$_response[] = static::_insertInternal( $_record );
				}
				catch ( \Exception $_ex )
				{
					$_response[] = array( 'error' => array( 'message' => $_ex->getMessage(), 'code' => $_ex->getCode() ) );
				}
			}
		}
		catch ( \Exception $_ex )
		{
			$_response[] = array( 'error' => array( 'message' => $_ex->getMessage(), 'code' => $_ex->getCode() ) );

			if ( false !== $rollback && $_transaction )
			{
				//	Rollback
				$_transaction->rollback();

				return array( 'record' => $_response );
			}
		}

		//	Commit
		if ( $_transaction )
		{
			$_transaction->commit();
		}

		return array( 'record' => $_response );
	}

	/**
	 * @param array $record
	 *
	 * @throws BadRequestException
	 * @return array
	 */
	public static function insert( $record )
	{
		static::_permissionCheck( 'create' );

		return static::_insertInternal( $record );
	}

	/**
	 * @param array $records
	 * @param bool  $rollback
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	public static function bulkUpdate( $records, $rollback = false )
	{
		if ( empty( $records ) )
		{
			throw new BadRequestException( 'There are no record sets in the request.' );
		}

		if ( !isset( $records[0] ) )
		{
			// conversion from xml can pull single record out of array format
			$records = array( $records );
		}

		static::_permissionCheck( 'update' );

		$_response = array();
		$_transaction = null;

		//	Start a transaction
		if ( false !== $rollback )
		{
			$_transaction = Pii::db()->beginTransaction();
		}

		$_pk = static::model()->primaryKey;

		foreach ( $records as $_record )
		{
			try
			{
				$_response[] = static::_updateInternal( Option::get( $_record, $_pk ), $_record );
			}
			catch ( \CDbException $_ex )
			{
				$_response[] = array( 'error' => array( 'message' => $_ex->getMessage(), 'code' => $_ex->getCode() ) );

				if ( false !== $rollback && $_transaction )
				{
					//	Rollback
					$_transaction->rollback();

					return array( 'record' => $_response );
				}
			}
		}

		//	Commit
		if ( $_transaction )
		{
			$_transaction->commit();
		}

		return array( 'record' => $_response );
	}

	/**
	 * @param $record
	 *
	 * @return array
	 */
	public static function update( $record )
	{
		static::_permissionCheck( 'update' );

		return static::_updateByPk( Option::get( $record, static::model()->primaryKey ), $record );
	}

	/**
	 * @param string $ids
	 * @param array  $record
	 * @param bool   $rollback
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	public static function bulkUpdateById( $ids, $record, $rollback = false )
	{
		if ( empty( $record ) )
		{
			throw new BadRequestException( 'There is no record in the request.' );
		}

		$_ids = explode( ',', $ids );

		$_records = array();
		$_pk = static::model()->primaryKey;

		foreach ( $_ids as $_id )
		{
			$_record = array_merge( $record, array( $_pk, trim( $_id ) ) );
			$_records[] = $_record;
		}

		return static::bulkUpdate( $_records );
	}

	/**
	 * @param $id
	 * @param $record
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	protected static function _updateByPk( $id, $record )
	{
		return static::_updateInternal( $id, $record );
	}

	/**
	 * @param array $records
	 * @param bool  $rollback
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	public static function bulkDelete( $records, $rollback = false )
	{
		if ( empty( $records ) )
		{
			throw new BadRequestException( 'There are no record sets in the request.' );
		}

		if ( !isset( $records[0] ) )
		{
			//	Conversion from xml can pull single record out of array format
			$records = array( $records );
		}

		static::_permissionCheck( 'delete' );

		$_response = array();
		$_transaction = null;

		//	Start a transaction
		if ( false !== $rollback )
		{
			$_transaction = Pii::db()->beginTransaction();
		}

		$_pk = static::model()->primaryKey;

		foreach ( $records as $_record )
		{
			try
			{
				$_response[] = static::_deleteInternal( Option::get( $_record, $_pk ) );
			}
			catch ( \CDbException $_ex )
			{
				$_response[] = array( 'error' => array( 'message' => $_ex->getMessage(), 'code' => $_ex->getCode() ) );

				if ( false !== $rollback && $_transaction )
				{
					//	Rollback
					$_transaction->rollback();

					return array( 'record' => $_response );
				}
			}
		}

		//	Commit
		if ( $_transaction )
		{
			$_transaction->commit();
		}

		return array( 'record' => $_response );
	}

	/**
	 * @param array $record
	 *
	 * @throws BadRequestException
	 * @return array
	 */
	public static function delete( $record )
	{
		return static::bulkDelete( array( $record ) );
	}

	/**
	 * @param        $ids
	 *
	 * @throws BadRequestException
	 * @return array
	 */
	public static function bulkDeleteById( $ids )
	{
		static::_permissionCheck( 'delete' );

		$_ids = array_map( 'trim', explode( ',', $ids ) );

		$_response = array();

		foreach ( $_ids as $_id )
		{
			try
			{
				$_response[] = static::_deleteInternal( $_id );
			}
			catch ( \Exception $_ex )
			{
				$_response[] = array( 'error' => array( 'message' => $_ex->getMessage(), 'code' => $_ex->getCode() ) );
			}
		}

		return array( 'record' => $_response );
	}

	/**
	 * @param int $id
	 *
	 * @throws BadRequestException
	 * @return array
	 */
	public static function deleteRecordById( $id )
	{
		return static::bulkDeleteById( $id );
	}

	/**
	 * @param string $ids
	 *
	 * @throws \Exception
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws NotFoundException
	 * @return array
	 */
	public static function bulkSelectById( $ids )
	{
		if ( empty( $record ) )
		{
			throw new BadRequestException( 'There is no record in the request.' );
		}

		$_ids = explode( ',', $ids );
		$_pk = static::model()->primaryKey;

		$_models = static::_find( $_pk . ' in (' . implode( ',', $_ids ) . ')' );
		$_response = array();

		if ( !empty( $_models ) )
		{
			foreach ( $_models as $_model )
			{
				$_response[] = static::buildResponsePayload( $_model );
			}
		}

		return $_response;
	}

	/**
	 * @param int    $id Optional ID
	 * @param string $criteria
	 * @param array  $params
	 *
	 * @return array
	 */
	public static function select( $id = null, $criteria = null, $params = array() )
	{
		return static::bulkSelectById( $id );
	}

	/**
	 * @param int   $id Optional ID
	 * @param mixed $criteria
	 * @param array $params
	 *
	 * @throws NotFoundException
	 * @return \DreamFactory\Platform\Yii\Models\BasePlatformSystemModel
	 */
	protected static function _findByPk( $id = null, $criteria = null, $params = array() )
	{
		static::_permissionCheck( 'read' );

		if ( null === ( $_resource = static::model()->findByPk( $id ? : static::$_resourceId, $criteria, $params ) ) )
		{
			throw new NotFoundException();
		}

		return $_resource;
	}

	/**
	 * @param mixed $criteria
	 * @param array $params
	 *
	 * @throws NotFoundException
	 * @internal param int $id Optional ID
	 *
	 * @return \DreamFactory\Platform\Yii\Models\BasePlatformSystemModel
	 */
	protected static function _find( $criteria = null, $params = array() )
	{
		static::_permissionCheck( 'read' );

		if ( null === ( $_resource = static::model()->find( $criteria, $params ) ) )
		{
			throw new NotFoundException();
		}

		return $_resource;
	}

	/**
	 * @param mixed $criteria
	 * @param array $params
	 *
	 * @throws NotFoundException
	 * @internal param int $id Optional ID
	 *
	 * @return \DreamFactory\Platform\Yii\Models\BasePlatformSystemModel
	 */
	protected static function _findAll( $criteria = null, $params = array() )
	{
		static::_permissionCheck( 'read' );

		if ( null === ( $_resources = static::model()->findAll( $criteria, $params ) ) )
		{
			throw new NotFoundException();
		}

		return $_resources;
	}

	/**
	 * @param BasePlatformSystemModel $resource
	 * @param bool                    $refresh
	 *
	 * @return array
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException * @return array
	 */
	public static function buildResponsePayload( $resource, $refresh = true )
	{
		if ( empty( static::$_fields ) && empty( static::$_extras ) )
		{
			$_pk = $resource->primaryKey;

			return array( $_pk => $resource->getAttribute( $_pk ) );
		}

		//	Refresh requested?
		if ( true === $refresh )
		{
			$resource->refresh();
		}

		static::$_fields = $resource->getRetrievableAttributes( static::$_fields );
		$_payload = $resource->getAttributes( static::$_fields );

		if ( !empty( static::$_extras ) )
		{
			$_relations = $resource->relations();
			$_relatedData = array();

			/**
			 * @var BasePlatformSystemModel[] $_relations
			 */
			foreach ( static::$_extras as $_extra )
			{
				$_extraName = $_extra['name'];

				if ( !isset( $_relations[$_extraName] ) )
				{
					throw new BadRequestException( 'Invalid relation "' . $_extraName . '" requested . ' );
				}

				$_extraFields = $_extra['fields'];
				$_relations = $resource->getRelated( $_extraName, true );

				//	Got relations?
				if ( !is_array( $_relations ) && !empty( $_relations ) )
				{
					$_relations = array( $_relations );
				}

				$_relatedFields = $_relations[0]->getRetrievableAttributes( $_extraFields );

				foreach ( $_relations as $_relative )
				{
					$_payload[] = $_relative->getAttributes( $_relatedFields );
				}

				$_relatedData[$_extraName] = $_payload;
			}

			if ( !empty( $_relatedData ) )
			{
				$_payload = array_merge( $_payload, $_relatedData );
			}
		}

		return $_payload;
	}

	/**
	 * @param string $resourceName
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return BasePlatformSystemModel
	 */
	public static function model( $resourceName = null )
	{
		$_resourceName = $resourceName ? : static::$_resourceName;

		//	Try dynamic system models first
		$_className = \ucwords( $_resourceName );

		if ( !class_exists( $_className ) )
		{
			$_className = null;

			switch ( strtolower( $_resourceName ) )
			{
				case 'app':
					$_className = '\\App';
					break;

				case 'app_group':
				case 'appgroup':
					$_className = '\\AppGroup';
					break;

				case 'role':
					$_className = '\\Role';
					break;

				case 'service':
					$_className = '\\Service';
					break;

				case 'user':
					$_className = '\\User';
					break;

				case 'email_template':
					$_className = '\\EmailTemplate';
					break;
			}
		}

		if ( empty( $_className ) )
		{
			throw new InternalServerErrorException( 'Invalid resource model "' . $_resourceName . '" requested . ' );
		}

		return call_user_func( array( $_className, 'model' ) );
	}

	/**
	 * Generic permission checker
	 *
	 * @param string $operation
	 * @param string $service
	 * @param string $resource
	 */
	protected static function _permissionCheck( $operation, $service = null, $resource = null )
	{
		UserSession::checkSessionPermission( $operation, $service ? : static::$_service, $resource ? : static::$_resourceName );
	}

	/**
	 * @param array $record
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException|\Exception
	 * @return array
	 */
	protected static function _insertInternal( $record )
	{
		if ( empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record to create . ' );
		}

		//	Create record
		try
		{
			$_resource = static::model();
			$_resource->setAttributes( $record );
			$_resource->save();
		}
		catch ( \Exception $_ex )
		{
			throw new InternalServerErrorException( 'Failed to create resource "' . static::$_resourceName . '":' . $_ex->getMessage() );
		}

		//	Set related and return
		try
		{
			$_id = $_resource->primaryKey;

			if ( empty( $_id ) )
			{
				Log::error( 'Failed to get primary key from created resource "' . static::$_resourceName . '": ' . print_r( $_resource, true ) );

				throw new InternalServerErrorException( 'Failed to get primary key from created user . ' );
			}

			$_resource->setRelated( $record, $_id );

			//	Return requested data
			return static::buildResponsePayload( $_resource );
		}
		catch ( BadRequestException $_ex )
		{
			//	Delete the above table entry and clean up
			if ( isset( $_resource ) && !$_resource->getIsNewRecord() )
			{
				$_resource->delete();
			}

			throw $_ex;
		}
	}

	/**
	 * @param int   $id
	 * @param array $record
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	protected static function _updateInternal( $id, $record )
	{
		if ( empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record to create . ' );
		}

		if ( empty( $id ) )
		{
			throw new BadRequestException( 'Identifying field "id" can not be empty for update request . ' );
		}

		$_model = static::findByPk( $id );

		//	Remove the PK from the record since this is an update
		Option::remove( $record, $_model->tableSchema->primaryKey );

		try
		{
			$_model->setAttributes( $record );
			$_model->save();

			$_model->setRelated( $record, $id );

			return static::buildResponsePayload( $_model );
		}
		catch ( \Exception $_ex )
		{
			throw new InternalServerErrorException( 'Failed	to update resource: ' . $_ex->getMessage() );
		}
	}

	/**
	 * @param int $id
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	protected static function _deleteInternal( $id )
	{
		if ( empty( $id ) )
		{
			throw new BadRequestException( "Identifying field 'id' can not be empty for delete request." );
		}

		$_model = static::findByPk( $id );

		try
		{
			$_model->delete();

			return static::buildResponsePayload( $_model );
		}
		catch ( \Exception $_ex )
		{
			throw new InternalServerErrorException( 'Failed to delete "' . static::$_resourceName . '" record:' . $_ex->getMessage() );
		}
	}

	/**
	 * @param string $relatedResource
	 *
	 * @return ResourceStorage
	 */
	public static function setRelatedResource( $relatedResource )
	{
		static::$_relatedResource = $relatedResource;
	}

	/**
	 * @return string
	 */
	public static function getRelatedResource()
	{
		return static::$_relatedResource;
	}

	/**
	 * @param array $resourceArray
	 *
	 * @return ResourceStorage
	 */
	public static function setResourceArray( $resourceArray )
	{
		static::$_resourceArray = $resourceArray;
	}

	/**
	 * @return array
	 */
	public static function getResourceArray()
	{
		return static::$_resourceArray;
	}

	/**
	 * @param int $resourceId
	 *
	 * @return ResourceStorage
	 */
	public static function setResourceId( $resourceId )
	{
		static::$_resourceId = $resourceId;
	}

	/**
	 * @return int
	 */
	public static function getResourceId()
	{
		return static::$_resourceId;
	}

	/**
	 * @param array $extras
	 */
	public static function setExtras( $extras )
	{
		static::$_extras = $extras;
	}

	/**
	 * @return array
	 */
	public static function getExtras()
	{
		return static::$_extras;
	}

	/**
	 * @param array $fields
	 */
	public static function setFields( $fields )
	{
		static::$_fields = $fields;
	}

	/**
	 * @return array
	 */
	public static function getFields()
	{
		return static::$_fields;
	}

	/**
	 * @param string $resourceName
	 */
	public static function setResourceName( $resourceName )
	{
		static::$_resourceName = $resourceName;
	}

	/**
	 * @return string
	 */
	public static function getResourceName()
	{
		return static::$_resourceName;
	}
}