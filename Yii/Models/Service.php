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

use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Yii\Utility\Pii;

/**
 * Service.php
 * The system service model for the DSP
 *
 * Columns:
 *
 * @property integer             $id
 * @property string              $name
 * @property string              $api_name
 * @property string              $description
 * @property integer             $is_active
 * @property integer             $is_system
 * @property string              $type
 * @property int                 $type_id
 * @property string              $storage_name
 * @property string              $storage_type
 * @property string              $credentials
 * @property string              $native_format
 * @property string              $base_url
 * @property string              $parameters
 * @property string              $headers
 *
 * Related:
 *
 * @property RoleServiceAccess[] $role_service_accesses
 * @property App[]               $apps
 * @property Role[]              $roles
 */
class Service extends BasePlatformSystemModel
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var bool Is this service a system service that should not be deleted or modified in certain ways, i.e. api name and type.
	 */
	protected $is_system = false;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return static::tableNamePrefix() . 'service';
	}

	/**
	 * Down and dirty service cache which includes the DSP default services.
	 * Clears when saves to services are made
	 *
	 * @param bool $bust If true, bust the cache
	 *
	 * @return array
	 */
	public static function available( $bust = false )
	{
		if ( false !== $bust || null === ( $_serviceCache = Pii::getState( 'dsp.service_cache' ) ) )
		{
			Log::debug( 'Service cache reloaded.' );

			$_serviceCache = Pii::getParam( 'dsp.default_services', array() );
			$_tableName = static::model()->tableName();

			//	List all available services from db
			$_pdo = Pii::pdo();

			$_sql
				= <<<MYSQL
SELECT
	`api_name`,
	`name`
FROM
	{$_tableName}
ORDER BY
	api_name
MYSQL;

			$_services = Sql::findAll( $_sql, null, $_pdo );

			$_serviceCache = array_merge(
				$_serviceCache,
				$_services ? : array()
			);

			Pii::setState( 'dsp.service_cache', $_serviceCache );
		}

		return $_serviceCache;
	}

	/**
	 * Named scope that filters by api_name
	 *
	 * @param string $name
	 *
	 * @return Service
	 */
	public function byApiName( $name )
	{
		$this->getDbCriteria()->mergeWith(
			array(
				 'condition' => 'api_name = :api_name',
				 'params'    => array( ':api_name' => $name ),
			)
		);

		return $this;
	}

	/**
	 * Retrieves the record of the particular service
	 *
	 * @param string $api_name
	 *
	 * @return array The service record array
	 * @throws \Exception if retrieving of service is not possible
	 */
	public static function getRecordByName( $api_name )
	{
		/** @noinspection PhpUndefinedMethodInspection */
		/** @var $_model BasePlatformModel */
		if ( null === ( $_model = static::model()->byApiName( $api_name )->find() ) )
		{
			return array();
		}

		return $_model->getAttributes();
	}

	/**
	 * Retrieves the record of the particular service
	 *
	 * @param int $id
	 *
	 * @return array The service record array
	 * @throws \Exception if retrieving of service is not possible
	 */
	public static function getRecordById( $id )
	{
		if ( null === ( $_model = static::model()->findByPk( $id ) ) )
		{
			return array();
		}

		return $_model->getAttributes();
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		$_rules = array(
			array( 'name, api_name, type', 'required' ),
			array( 'name, api_name', 'unique', 'allowEmpty' => false, 'caseSensitive' => false ),
			array( 'is_active, type_id, storage_type_id, native_format_id', 'numerical', 'integerOnly' => true ),
			array( 'name, api_name, type, storage_type, native_format', 'length', 'max' => 64 ),
			array( 'storage_name', 'length', 'max' => 80 ),
			array( 'base_url', 'length', 'max' => 255 ),
			array( 'description, credentials, parameters, headers', 'safe' ),
			array(
				'id, name, api_name, is_active, type, type_id, storage_type_id, native_format_id, storage_name, storage_type',
				'safe',
				'on' => 'search'
			),
		);

		return array_merge( parent::rules(), $_rules );
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		$_relations = array(
			'role_service_accesses' => array( static::HAS_MANY, __NAMESPACE__ . '\\RoleServiceAccess', 'service_id' ),
			'apps'                  => array( static::MANY_MANY, __NAMESPACE__ . '\\App', 'df_sys_app_to_service(app_id, service_id)' ),
			'roles'                 => array( static::MANY_MANY, __NAMESPACE__ . '\\Role', 'df_sys_role_service_access(service_id, role_id)' ),
		);

		return array_merge( parent::relations(), $_relations );
	}

	/**
	 * @param array $additionalLabels
	 *
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels( $additionalLabels = array() )
	{
		return parent::attributeLabels(
			array_merge(
				array(
					 'name'          => 'Name',
					 'api_name'      => 'API Name',
					 'description'   => 'Description',
					 'is_active'     => 'Is Active',
					 'is_system'     => 'Is System',
					 'type'          => 'Type',
					 'type_id'       => 'Type ID',
					 'storage_name'  => 'Storage Name',
					 'storage_type'  => 'Storage Type',
					 'credentials'   => 'Credentials',
					 'native_format' => 'Native Format',
					 'base_url'      => 'Base Url',
					 'parameters'    => 'Parameters',
					 'headers'       => 'Headers',
				),
				$additionalLabels
			)
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * @param mixed $criteria
	 *
	 * @return \CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search( $criteria = null )
	{
		$_criteria = $criteria ? : new \CDbCriteria();

		$_criteria->compare( 'name', $this->name, true );
		$_criteria->compare( 'api_name', $this->api_name, true );
		$_criteria->compare( 'is_active', $this->is_active );
		$_criteria->compare( 'type', $this->type, true );
		$_criteria->compare( 'storage_name', $this->storage_name, true );
		$_criteria->compare( 'storage_type', $this->storage_type, true );

		return parent::search( $_criteria );
	}

	/**
	 * {@InheritDoc}
	 */
	public function setAttributes( $values, $safeOnly = true )
	{
		if ( !$this->isNewRecord )
		{
			$_type = Option::get( $values, 'type' );

			if ( !empty( $_type ) && 0 !== strcasecmp( $this->type, $_type ) )
			{
				throw new BadRequestException( 'Service type cannot be changed after creation.' );
			}

			if ( null === ( $_typeId = Option::get( $values, 'type_id', $this->type_id ) ) )
			{
				$this->type_id = PlatformServiceTypes::defines( $_type, true );
			}

			$_apiName = Option::get( $values, 'api_name' );

			if ( ( 0 == strcasecmp( 'app', $this->api_name ) ) && !empty( $_apiName ) && 0 != strcasecmp( $this->api_name, $values['api_name'] ) )
			{
				throw new BadRequestException( 'Service API name currently can not be modified after creation.' );
			}
		}

		parent::setAttributes( $values, $safeOnly );
	}

	/**
	 * @param array $values
	 * @param       $id
	 */
	public function setRelated( $values, $id )
	{
		if ( null !== ( $_apps = Option::get( $values, 'apps' ) ) )
		{
			$this->assignManyToOneByMap( $id, 'app', 'app_to_service', 'service_id', 'app_id', $_apps );
		}

		if ( null !== ( $_roles = Option::get( $values, 'roles' ) ) )
		{
			$this->assignManyToOneByMap( $id, 'role', 'role_service_access', 'service_id', 'role_id', $_roles );
		}
	}

	/**
	 * {@InheritDoc}
	 */
	protected function beforeValidate()
	{
		//	Correct data type
		$this->is_active = DataFormat::boolval( $this->is_active ) ? 1 : 0;

		return parent::beforeValidate();
	}

	/**
	 * {@InheritDoc}
	 */
	protected function beforeSave()
	{
		$_salt = Pii::db()->password;

		$this->credentials = empty( $this->credentials ) ? null : ( Hasher::encryptString( json_encode( $this->credentials ), $_salt ) ? : $this->credentials );
		$this->parameters = empty( $this->parameters ) ? null : ( Hasher::encryptString( json_encode( $this->parameters ), $_salt ) ? : $this->parameters );
		$this->headers = empty( $this->headers ) ? null : ( Hasher::encryptString( json_encode( $this->headers ), $_salt ) ? : $this->headers );

		return parent::beforeSave();
	}

	/**
	 * {@InheritDoc}
	 */
	protected function afterDelete()
	{
		//	Bust cache
		static::available( true );

		parent::afterDelete();
	}

	/**
	 * {@InheritDoc}
	 */
	protected function afterSave()
	{
		//	Bust cache
		static::available( true );

		parent::afterSave();
	}

	/**
	 * {@InheritDoc}
	 */
	protected function beforeDelete()
	{
		switch ( $this->type_id )
		{
			case PlatformServiceTypes::LOCAL_SQL_DB:
			case PlatformServiceTypes::LOCAL_SQL_DB_SCHEMA:
				throw new BadRequestException( 'System generated database services can not be deleted.' );

			case PlatformServiceTypes::LOCAL_FILE_STORAGE:
				switch ( $this->api_name )
				{
					case 'app':
						throw new BadRequestException( 'System generated application storage service can not be deleted.' );
				}
				break;
		}

		return parent::beforeDelete();
	}

	/**
	 * {@InheritDoc}
	 */
	public function afterFind()
	{
		//	Correct data type
		$this->is_active = ( 0 != $this->is_active );

		//	Add fake field for client
		switch ( $this->type_id )
		{
			case PlatformServiceTypes::LOCAL_SQL_DB:
			case PlatformServiceTypes::LOCAL_SQL_DB_SCHEMA:
				$this->is_system = true;
				break;

			case PlatformServiceTypes::LOCAL_FILE_STORAGE:
			case PlatformServiceTypes::REMOTE_FILE_STORAGE:
				switch ( $this->api_name )
				{
					case 'app':
						$this->is_system = true;
						break;
				}
				break;

			default:
				$this->is_system = false;
				break;
		}

		//	Decrypt our stuff
		$_salt = Pii::db()->password;

		$this->credentials = empty( $this->credentials )
			? array()
			: ( json_decode( Hasher::decryptString( $this->credentials, $_salt ), true )
				? : $this->credentials );

		$this->parameters = empty( $this->parameters )
			? array()
			: ( json_decode( Hasher::decryptString( $this->parameters, $_salt ), true )
				? : $this->parameters );

		$this->headers = empty( $this->headers ) ? array() : ( json_decode( Hasher::decryptString( $this->headers, $_salt ), true ) ? : $this->headers );

		parent::afterFind();
	}

	/**
	 * @param string $requested
	 * @param array  $columns
	 * @param array  $hidden
	 *
	 * @return array
	 */
	public function getRetrievableAttributes( $requested, $columns = array(), $hidden = array() )
	{
		return parent::getRetrievableAttributes(
			$requested,
			array_merge(
				array(
					 'name',
					 'api_name',
					 'description',
					 'is_active',
					 'type',
					 'type_id',
					 'is_system',
					 'storage_name',
					 'storage_type',
					 'credentials',
					 'native_format',
					 'base_url',
					 'parameters',
					 'headers',
				),
				$columns
			),
			$hidden
		);
	}
}