<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Utility;

use Composer\Autoload\ClassLoader;
use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Services\BasePlatformRestService;
use DreamFactory\Platform\Yii\Models\BasePlatformSystemModel;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Seed;
use Kisma\Core\SeedUtility;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use DreamFactory\Platform\Resources\System\UserSession;

/**
 * ResourceStore
 * A base service resource class to handle service resources of various kinds.
 *
 * This object DOES NOT check permissions.
 */
class ResourceStore extends SeedUtility
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const DEFAULT_MODEL_NAMESPACE = 'DreamFactory\\Platform\\Yii\\Models\\';
	/**
	 * @var string
	 */
	const DEFAULT_RESOURCE_NAMESPACE = 'DreamFactory\\Platform\\Resources\\System\\';

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
		static::$_service = Option::get( $settings, 'service' );
		static::$_fields = Option::get( $settings, 'fields' );
		static::$_extras = Option::get( $settings, 'extras' );

		if ( empty( static::$_resourceName ) )
		{
			throw new BadRequestException( 'Resource name can not be empty.' );
		}
	}

	/**
	 * Individual Methods
	 */

	/**
	 * @param array  $record
	 * @param bool   $rollback
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @return array
	 */
	public static function insert( $record, $rollback = false, $fields = null, $extras = null )
	{
		return static::bulkInsert( array( $record ), $rollback, $fields, $extras );
	}

	/**
	 * @param array  $record
	 * @param bool   $rollback
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @return array
	 */
	public static function update( $record, $rollback = false, $fields = null, $extras = null )
	{
		return static::bulkUpdate( array( $record ), $rollback, $fields, $extras );
	}

	/**
	 * @param array $record
	 *
	 * @param null  $fields
	 * @param null  $extras
	 *
	 * @return array
	 */
	public static function delete( $record, $fields = null, $extras = null )
	{
		return static::bulkDelete( array( $record ) );
	}

	/**
	 * @param int                       $id        Optional ID
	 * @param array|\CDbCriteria|string $criteria  An array of criteria, a criteria object, or a comma-delimited list of columns to select
	 * @param array                     $params    Bind variable values
	 * @param bool                      $singleRow If true, only a single row will be queried
	 *
	 * @return array
	 */
	public static function select( $id = null, $criteria = null, $params = array(), $singleRow = false )
	{
		//	Passed in a comma-delimited string of ids...
		if ( $criteria && is_string( $criteria ) )
		{
			$criteria = array( 'select' => $criteria );
		}

		return static::bulkSelectById( null !== $id ? array( $id ) : null, $criteria, $params, $singleRow );
	}

	/**
	 * BULK Methods
	 */

	/**
	 * @param array  $records
	 * @param bool   $rollback
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	public static function bulkInsert( $records, $rollback = false, $fields = null, $extras = null )
	{
		static::_validateRecords( $records );

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
					$_response[] = static::_insertInternal( $_record, $fields, $extras );
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
	 * @param string $ids
	 * @param array  $record
	 * @param bool   $rollback
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	public static function bulkUpdateById( $ids, $record, $rollback = false, $fields = null, $extras = null )
	{
		static::_validateRecords( $record );

		if ( empty( $record ) )
		{
			throw new BadRequestException( 'There is no record in the request.' );
		}

		$_ids = is_array( $ids ) ? $ids : ( explode( ',', $ids ? : static::$_resourceId ) );

		$_records = array();
		$_pk = static::model()->tableSchema->primaryKey;

		foreach ( $_ids as $_id )
		{
			$_record = array_merge( $record, array( $_pk, trim( $_id ) ) );
			$_records[] = $_record;
		}

		return static::bulkUpdate( $_records, $rollback, $fields, $extras );
	}

	/**
	 * @param array $records
	 * @param bool  $rollback
	 *
	 * @param null  $fields
	 * @param null  $extras
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	public static function bulkUpdate( $records, $rollback = false, $fields = null, $extras = null )
	{
		static::_validateRecords( $records );

		$_response = array();
		$_transaction = null;

		//	Start a transaction
		if ( false !== $rollback )
		{
			$_transaction = Pii::db()->beginTransaction();
		}

		$_pk = static::model()->tableSchema->primaryKey;

		foreach ( $records as $_record )
		{
			try
			{
				$_response[] = static::_updateInternal( Option::get( $_record, $_pk ), $_record, $fields, $extras );
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
	 * @param string $ids
	 * @param bool   $rollback
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @return array
	 */
	public static function bulkDeleteById( $ids, $rollback = false, $fields = null, $extras = null )
	{
		$_ids = is_array( $ids ) ? $ids : ( explode( ',', $ids ? : static::$_resourceId ) );

		$_records = array();
		$_pk = static::model()->tableSchema->primaryKey;

		foreach ( $_ids as $_id )
		{
			$_records[] = array( $_pk => $_id );
		}

		return static::bulkDelete( $_records, $rollback, $fields, $extras );
	}

	/**
	 * @param array $records
	 * @param bool  $rollback
	 *
	 * @param null  $fields
	 * @param null  $extras
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	public static function bulkDelete( $records, $rollback = false, $fields = null, $extras = null )
	{
		static::_validateRecords( $records );

		$_response = array();
		$_transaction = null;

		//	Start a transaction
		if ( false !== $rollback )
		{
			$_transaction = Pii::db()->beginTransaction();
		}

		$_pk = static::model()->tableSchema->primaryKey;

		foreach ( $records as $_record )
		{
			try
			{
				$_response[] = static::_deleteInternal( Option::get( $_record, $_pk ), $fields, $extras );
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
	 * @param string $ids
	 * @param mixed  $criteria
	 * @param array  $params
	 * @param bool   $single If true, will return a single array instead of an array of one row
	 *
	 * @return array|array[]
	 */
	public static function bulkSelectById( $ids, $criteria = null, $params = array(), $single = false )
	{
		if ( empty( $ids ) || array( null ) == $ids )
		{
			$ids = null;
		}
		else
		{
			$_ids = is_array( $ids ) ? $ids : ( explode( ',', $ids ? : static::$_resourceId ) );
		}

		$_pk = static::model()->tableSchema->primaryKey;

		$_criteria = new \CDbCriteria( $criteria );

		if ( !empty( $_ids ) )
		{
			$_criteria->addInCondition( $_pk, $_ids );
		}

		$_response = array();

		//	Only one row
		if ( false !== $single )
		{
			if ( null !== ( $_model = static::_find( $_criteria, $params ) ) )
			{
				$_response = static::buildResponsePayload( $_model, false );
			}
		}
		//	Multiple rows
		else
		{
			$_models = static::_findAll( $_criteria, $params );

			if ( !empty( $_models ) )
			{
				foreach ( $_models as $_model )
				{
					$_response[] = static::buildResponsePayload( $_model, false );
				}
			}
		}

		return $_response;
	}

	/**
	 * @param BasePlatformSystemModel $resource
	 * @param bool                    $refresh
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	public static function buildResponsePayload( $resource, $refresh = true )
	{
		if ( empty( $resource ) )
		{
			return array();
		}

		if ( empty( static::$_fields ) && empty( static::$_extras ) )
		{
			$_pk = static::model()->tableSchema->primaryKey;

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

			if ( !empty( $_relations ) )
			{
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
					if ( !empty( $_relations ) )
					{
						$_relations = Option::clean( $_relations );
						$_relative = current( $_relations );

						if ( !empty( $_relative ) )
						{
							$_relatedFields = $_relative->getRetrievableAttributes( $_extraFields );

							foreach ( $_relations as $_relation )
							{
								$_payload[] = $_relation->getAttributes( $_relatedFields );
								unset( $_relation );
							}

							$_relatedData[$_extraName] = $_payload;
						}

						unset( $_relatedFields );
					}

					unset( $_extra, $_relations, $_relative, $_extraFields );
				}

				if ( !empty( $_relatedData ) )
				{
					$_payload += $_relatedData;
				}
			}
		}

		return $_payload;
	}

	/**
	 * @param string $resourceName
	 * @param array  $resources
	 *
	 * @return BasePlatformRestResource|BasePlatformSystemModel
	 */
	public static function resource( $resourceName = null, $resources = array() )
	{
		return static::model( $resourceName, true, $resources );
	}

	/**
	 * @param string $resourceName
	 * @param bool   $returnResource If true, a RestResource-based class will returned instead of a model
	 * @param array  $resources
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return BasePlatformSystemModel
	 */
	public static function model( $resourceName = null, $returnResource = false, $resources = array() )
	{
		/** @var ClassLoader $_loader */
		static $_loader;

		if ( !$_loader )
		{
			$_loader = \Kisma::get( 'app.autoloader' );
		};

		$_resourceName = $resourceName ? : static::$_resourceName;

		//	Try dynamic system models first
		$_className = \ucwords( $_resourceName );
		$_name = ucfirst( Inflector::deneutralize( $_resourceName ) );

		if ( !class_exists( $_className, false ) )
		{
			$_className = null;

			//	Set KNOWN names...
			switch ( strtolower( $_resourceName ) )
			{
				case 'app':
					$_name = 'App';
					break;

				case 'app_group':
				case 'appgroup':
					$_name = 'AppGroup';
					break;

				case 'role':
					$_name = 'Role';
					break;

				case 'service':
					$_name = 'Service';
					break;

				case 'user':
					$_name = 'User';
					break;

				case 'email_template':
					$_name = 'EmailTemplate';
					break;
			}
		}

		//	Does the resource have a class?
		if ( class_exists( $_resourceName, false ) || $_loader->loadClass( $_resourceName ) )
		{
			$_className = $_resourceName;
		}
		//	Does the cleaned name have a class?
		else if ( class_exists( $_name, false ) || $_loader->loadClass( $_name ) )
		{
			$_className = $_name;
		}
//		else if ( class_exists( '\\' . $_name, false ) || $_loader->loadClass( '\\' . $_name ) )
//		{
//			$_className = '\\' . $_name;
//		}

		$_namespace = ( false !== $returnResource ? static::DEFAULT_RESOURCE_NAMESPACE : static::DEFAULT_MODEL_NAMESPACE );

		//	Is it in the namespace?
		if ( class_exists( $_namespace . $_name, false ) || $_loader->loadClass( $_namespace . $_name ) )
		{
			$_className = $_namespace . $_name;
		}

		//	So, still not found, just let the SPL autoloader have a go and give up.
		if ( empty( $_className ) || ( !empty( $_className ) && !class_exists( $_className, false ) ) )
		{
			throw new InternalServerErrorException( 'Invalid ' . ( $returnResource ? 'resource' : 'model' ) . ' type \'' . $_resourceName . '\' requested.' );
		}

		//	Return a resource
		if ( false !== $returnResource )
		{
			try
			{
				return new $_className( null, $resources );
			}
			catch ( \Exception $_ex )
			{
				Log::error( 'Invalid resource class identified: ' . $_className . ' Error: ' . $_ex->getMessage() );
			}
		}

		try
		{
			return call_user_func( array( $_className, 'model' ) );
		}
		catch ( \Exception $_ex )
		{
			Log::error( 'Invalid model class identified: ' . $_className . ' Error: ' . $_ex->getMessage() );
		}
	}

	/**
	 * Generic permission checker
	 *
	 * @param string $operation
	 * @param string $service
	 * @param string $resource
	 *
	 * @return bool
	 */
	public static function checkPermission( $operation, $service = null, $resource = null )
	{
		UserSession::checkSessionPermission( $operation, $service ? : static::$_service, $resource ? : static::$_resourceName );

		return true;
	}

	/**
	 * @param array $records
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 */
	protected static function _validateRecords( &$records )
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
	}

	//*************************************************************************
	//	Internal Provider Specific Methods
	//*************************************************************************

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
		if ( null === ( $_resources = static::model()->findAll( $criteria, $params ) ) )
		{
			throw new NotFoundException();
		}

		return $_resources;
	}

	/**
	 * @param int    $id
	 * @param array  $record
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @return array
	 */
	protected static function _updateByPk( $id, $record, $fields = null, $extras = null )
	{
		return static::_updateInternal( $id, $record, $fields, $extras );
	}

	/**
	 * @param array $record
	 *
	 * @param null  $fields
	 * @param null  $extras
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException|\Exception
	 * @return array
	 */
	protected static function _insertInternal( $record, $fields = null, $extras = null )
	{
		if ( empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record to create . ' );
		}

		//	Create record
		$_resource = static::model();
		$_resource->setAttributes( $record );

		try
		{
			$_resource->save();
		}
		catch ( \Exception $_ex )
		{
			throw new InternalServerErrorException( 'Failed to create resource "' . static::$_resourceName . '":' . $_ex->getMessage() );
		}

		//	Set related and return
		try
		{
			$_id = $_resource->tableSchema->primaryKey;

			if ( empty( $_id ) )
			{
				Log::error( 'Failed to get primary key from created resource "' . static::$_resourceName . '": ' . print_r( $_resource, true ) );

				throw new InternalServerErrorException( 'Failed to get primary key from created user . ' );
			}

			$_resource->setRelated( $record, $_resource->getAttribute( $_id ) );

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
	 * @param null  $fields
	 * @param null  $extras
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	protected static function _updateInternal( $id, $record, $fields = null, $extras = null )
	{
		if ( empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record to create . ' );
		}

		if ( empty( $id ) )
		{
			throw new BadRequestException( 'Identifying field "id" can not be empty for update request . ' );
		}

		$_model = static::_findByPk( $id );

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
	 * @param int    $id
	 * @param string $fields
	 * @param array  $extras
	 *
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return array
	 */
	protected static function _deleteInternal( $id, $fields = null, $extras = null )
	{
		if ( empty( $id ) )
		{
			throw new BadRequestException( "Identifying field 'id' can not be empty for delete request." );
		}

		$_model = static::_findByPk( $id );

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

	//*************************************************************************
	//	Properties
	//*************************************************************************

	/**
	 * @param string $relatedResource
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

	/**
	 * @param string $service
	 */
	public static function setService( $service )
	{
		self::$_service = $service;
	}

	/**
	 * @return string
	 */
	public static function getService()
	{
		return self::$_service;
	}
}