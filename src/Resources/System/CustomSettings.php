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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\RestData;
use Kisma\Core\Utility\Option;

/**
 * CustomSettings
 * DSP system custom settings
 */
class CustomSettings extends BasePlatformRestResource
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var string
	 */
	protected $_setting = null;

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
				'name'         => 'System Custom Settings',
				'service_name' => 'system',
				'type'         => 'System',
				'type_id'      => PlatformServiceTypes::SYSTEM_SERVICE,
				'api_name'     => 'custom',
				'description'  => 'Resource for an admin to manage custom system settings.',
				'is_active'    => true,
				'verb_aliases' => array(
					static::PUT   => static::POST,
					static::PATCH => static::POST,
					static::MERGE => static::POST,
				)
			)
		);

		$this->_setting = Option::get( $resources, 1 );
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
        // clients use basic GET on global config to startup
        if ( static::GET == $operation )
		{
			return true;
		}

        return ResourceStore::checkPermission( $operation, $this->_serviceName, $resource, $this->_requestorType );
	}

	/**
	 * @return bool
	 */
	protected function _preProcess()
	{
		parent::_preProcess();

		//	Do validation here
		$this->checkPermission( $this->_action, 'config' );
	}

	/**
	 * @return array
	 */
	protected function _handleGet()
	{
		return $this->getCustomSettings( $this->_setting );
	}

	/**
	 * @return array
	 */
	protected function _handlePost()
	{
		$_data = RestData::getPostedData( true, true );

		return $this->setCustomSettings( $_data, $this->_setting );
	}

	/**
	 * @return array
	 */
	protected function _handleDelete()
	{
		return $this->deleteCustomSettings( $this->_setting );
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
		$_config = ResourceStore::model( 'config' )->find();
		if ( null === $_config )
		{
			throw new InternalServerErrorException( "Failed to retrieve the system configuration." );
		}

		try
		{
			$_data = $_config->getAttribute( 'custom_settings' );
			if ( empty( $_data ) )
			{
				return null;
			}

			if ( !empty( $setting ) )
			{
				return array( $setting => Option::get( $_data, $setting ) );
			}

			return $_data;
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error retrieving custom system settings.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param array  $data
	 * @param string $setting
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return array
	 */
	public static function setCustomSettings( $data, $setting = '' )
	{
		if ( !empty( $setting ) )
		{
			throw new BadRequestException( 'Setting individual custom setting is not currently supported.' );
		}

		$_config = ResourceStore::model( 'config' )->find();
		if ( null === $_config )
		{
			throw new InternalServerErrorException( "Failed to retrieve the system configuration." );
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
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return array
	 */
	public static function deleteCustomSettings( $setting = '' )
	{
		if ( empty( $setting ) )
		{
			throw new BadRequestException( 'Deleting all custom settings is not currently supported.' );
		}

		$_config = ResourceStore::model( 'config' )->find();
		if ( null === $_config )
		{
			throw new InternalServerErrorException( "Failed to retrieve the system configuration." );
		}

		try
		{
			$_data = $_config->getAttribute( 'custom_settings' );
			unset( $_data[$setting] );
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
