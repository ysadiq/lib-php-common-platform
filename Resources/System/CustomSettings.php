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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Yii\Models\User;
use Kisma\Core\Utility\Option;

/**
 * CustomSettings
 * DSP system custom settings
 */
class CustomSettings extends BasePlatformRestResource
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
	 * @param array                                               $resources
	 */
	public function __construct( $consumer, $resources = array() )
	{
		parent::__construct(
			$consumer,
			array(
				 'name'           => 'System Custom Settings',
				 'service_name'   => 'system',
				 'type'           => 'System',
				 'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
				 'api_name'       => 'custom',
				 'description'    => 'Resource for an admin to manage custom system settings.',
				 'is_active'      => true,
				 'resource_array' => $resources,
				 'verb_aliases'   => array(
					 static::Put   => static::Post,
					 static::Patch => static::Post,
					 static::Merge => static::Post,
				 )
			)
		);
	}

	// REST interface implementation

	/**
	 * Override for GET of public system data
	 *
	 * @param string $operation
	 * @param null   $resource
	 *
	 * @return bool
	 */
	public function checkPermission( $operation, $resource = null )
	{
		if ( 'read' == $operation )
		{
			return true;
		}

		return ResourceStore::checkPermission( $operation, $this->_serviceName, $resource );
	}

	/**
	 * @return mixed
	 */
	protected function _handleGet()
	{
		if ( !empty( $this->_resourceId ) )
		{
			return $this->getCustomSettings( $this->_resourceId );
		}

		return $this->getCustomSettings();
	}

	/**
	 * @return array|bool|void
	 */
	protected function _handlePost()
	{
		$_data = RestData::getPostDataAsArray();
		if ( !empty( $this->_resourceId ) )
		{
			return $this->setCustomSettings( $_data );
		}

		return false; // don't allow individual update due to content type
	}

	/**
	 * @return array|bool|void
	 */
	protected function _handleDelete()
	{
		if ( !empty( $this->_resourceId ) )
		{
			return $this->deleteCustomSettings( $this->_resourceId );
		}

		return false; // don't allow mass delete
	}

	//-------- User Operations ------------------------------------------------

	/**
	 * @param string $setting
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return array
	 */
	public static function getCustomSettings( $setting = '' )
	{
		$_config = Config::model()->find();
		if ( null === $_config )
		{
			throw new NotFoundException( "The system configuration was not able to be retrieved." );
		}

		try
		{
			$_data = $_config->getAttribute( 'custom_settings' );
			if ( !empty( $setting ) )
			{
				return Option::get( $_data, $setting, array() );
			}

			return $_data;
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error retrieving custom system settings.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param array $data
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return bool
	 */
	public static function setCustomSettings( $data )
	{
		$_config = Config::model()->find();
		if ( null === $_config )
		{
			throw new NotFoundException( "The system configuration was not able to be retrieved." );
		}

		try
		{
			$_old = $_config->getAttribute( 'custom_settings' );
			$_new = array_merge( $_old, $data );
			$_config->setAttribute( 'custom_settings', $_new );
			$_config->save();

			return array( 'success' => true );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error processing custom system settings update.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param string $setting
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return bool
	 */
	public static function deleteCustomSettings( $setting )
	{
		$_config = Config::model()->find();
		if ( null === $_config )
		{
			throw new NotFoundException( "The system configuration was not able to be retrieved." );
		}

		try
		{
			$_data = $_config->getAttribute( 'custom_settings' );
			unset( $_data[ $setting ] );
			$_config->setAttribute( 'custom_settings', $_data );
			$_config->save();

			return array( 'success' => true );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error processing custom system settings delete.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}
}
