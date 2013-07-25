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
namespace DreamFactory\Platform\Yii\Models;

use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Yii\Models\BaseFactoryModel;
use Kisma\Core\Exceptions\NotImplementedException;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Sql;

/**
 * BasePlatformModel.php
 *
 * Defines two "built-in" behaviors: DataFormat and TimeStamp
 *  - DataFormat automatically formats date/time values for the target database platform (MySQL, Oracle, etc.)
 *  - TimeStamp automatically updates create_date and lmod_date columns in tables upon save.
 *
 * @property int    $id
 * @property string $created_date
 * @property string $last_modified_date
 */
class BasePlatformModel extends BaseFactoryModel
{
	//*******************************************************************************
	//* Members
	//*******************************************************************************

	/**
	 * @var BasePlatformRestResource
	 */
	protected $_resourceClass = null;

	//********************************************************************************
	//* Methods
	//********************************************************************************

	/**
	 * @return array
	 */
	public function behaviors()
	{
		return array_merge(
			parent::behaviors(),
			array( //	Timestamper
				 'base_platform_model.timestamp_behavior' => array(
					 'class'                => '\\DreamFactory\\Yii\\Behaviors\\TimestampBehavior',
					 'createdColumn'        => array( 'create_date', 'created_date' ),
					 'createdByColumn'      => array( 'create_user_id', 'created_by_id' ),
					 'lastModifiedColumn'   => array( 'lmod_date', 'last_modified_date' ),
					 'lastModifiedByColumn' => array( 'lmod_user_id', 'last_modified_by_id' ),
					 'currentUserId'        => function ()
					 {
						 return Session::getCurrentUserId();
					 }
				 ),
			)
		);
	}

	/**
	 * Returns an array of all attribute labels.
	 *
	 * @param array $additionalLabels
	 *
	 * @return array
	 */
	public function attributeLabels( $additionalLabels = array() )
	{
		static $_cache;

		if ( null !== $_cache )
		{
			return $_cache;
		}

		//	Merge all the labels together
		return $_cache = array_merge(
		//	Mine
			array(
				 'id'                 => 'ID',
				 'create_date'        => 'Created Date',
				 'created_date'       => 'Created Date',
				 'last_modified_date' => 'Last Modified Date',
				 'lmod_date'          => 'Last Modified Date',
			),
			//	Subclass
			$additionalLabels
		);
	}

	//*******************************************************************************
	//* REST Methods
	//*******************************************************************************

	/**
	 * A mapping of model attributes to REST attributes.
	 * Only columns that are in this array are emitted when the model is requested
	 * as a REST resource.
	 *
	 * Example:
	 *
	 *    return array(
	 * //     Column Name              REST name
	 * //     ==================       =====================
	 *        'id'                  => 'id',
	 *        'some_column_name'    => 'rest_attribute_name',
	 *        'lmod_user_id'        => 'last_modified_by',
	 *        'auth_encrypted_text' => 'encrypted_data',
	 * );
	 *
	 * @param array $mappings
	 *
	 * @return array
	 */
	public function restMap( $mappings = array() )
	{
		static $_map;

		if ( null === $_map )
		{
			$_map = array( 'id', 'created_date', 'last_modified_date' );
			$_map = array_combine( $_map, $_map );
		}

		//	Include the default id, created_date, and last_modified_date
		$_all = $_map + $mappings;
		ksort( $_all );

		return $_all;
	}

	/**
	 * If a model has a REST mapping, attributes are mapped and returned in an array.
	 *
	 * @return array|null The resulting view
	 */
	public function getRestAttributes()
	{
		$_map = $this->restMap();

		if ( empty( $_map ) )
		{
			return null;
		}

		$_results = array();
		$_columns = $this->getSchema();

		foreach ( $this->restMap() as $_key => $_value )
		{
			$_attributeValue = $this->getAttribute( $_key );

			//	Apply formats
			switch ( $_columns[$_key]->dbType )
			{
				case 'date':
				case 'datetime':
				case 'timestamp':
					//	Avoid blanks and bogosity
					if ( -1 != date( 'Y', strtotime( $_attributeValue ) ) )
					{
						$_attributeValue = date( 'c', strtotime( $_attributeValue ) );
					}
					break;
			}

			$_results[$_value] = $_attributeValue;
		}

		return $_results;
	}

	/**
	 * Sets the values in the model based on REST attribute names
	 *
	 * @param array $attributeList
	 *
	 * @return BasePlatformModel
	 */
	public function setRestAttributes( array $attributeList = array() )
	{
		$_map = $this->restMap();

		if ( !empty( $_map ) )
		{
			foreach ( $attributeList as $_key => $_value )
			{
				if ( false !== ( $_mapKey = array_search( $_key, $_map ) ) )
				{
					$this->setAttribute( $_mapKey, $_value );
				}
			}
		}

		return $this;
	}

	/**
	 * @return array The model as a resource
	 */
	public function asResource()
	{
		return ResourceStore::buildResponsePayload( $this );
	}

	/**
	 * @param string $requested Comma-delimited list of requested fields
	 * @param array  $columns   Additional columns to add
	 * @param array  $hidden    Columns to hide from requested
	 *
	 * @throws \Kisma\Core\Exceptions\NotImplementedException
	 * @return array
	 */
	public function getRetrievableAttributes( $requested, $columns = array(), $hidden = array() )
	{
		//	Default implementation
		throw new NotImplementedException( 'This model is not compatible with the REST API.' );
	}

}