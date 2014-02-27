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
namespace DreamFactory\Platform\Resources\User;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Yii\Models\Device as DeviceModel;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\RestData;
use Kisma\Core\Utility\Option;

/**
 * Device
 * DSP user devices
 */
class Device extends BasePlatformRestResource
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
				 'name'           => 'User Device',
				 'service_name'   => 'user',
				 'type'           => 'System',
				 'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
				 'api_name'       => 'device',
				 'description'    => 'Resource for a user to manage their devices.',
				 'is_active'      => true,
				 'resource_array' => $resources,
			)
		);
	}

	// REST interface implementation

	/**
	 * @return bool
	 */
	protected function _handleGet()
	{
		// check valid session,
		// using userId from session, get device attributes
		$_userId = Session::validateSession();

		return static::getDevices( $_userId );
	}

	/**
	 * @return bool
	 */
	protected function _handlePost()
	{
		// check valid session,
		// using userId from session, get device attributes
		$_userId = Session::validateSession();

		$_data = RestData::getPostedData( false, true );

		return static::addDevice( $_userId, $_data );
	}

	//-------- User Operations ------------------------------------------------

	/**
	 * @param int $user_id
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return array
	 */
	public static function getDevices( $user_id )
	{
		$_models = DeviceModel::getDevicesByUser( $user_id );
		$_response = array();
		if ( !empty( $_models ) )
		{
			foreach ( $_models as $_model )
			{
				$_response[] = $_model->getAttributes();
			}
		}

		$_response = array( 'record' => $_response );

		return $_response;
	}

	public static function addDevice( $user_id, $data )
	{
		$_uuid = Option::get( $data, 'uuid' );
		// Registration, check for already existing device
		$_result = DeviceModel::getDeviceByUser( $user_id, $_uuid );
		if ( null === $_result )
		{
			$data['user_id'] = $user_id;
			try
			{
				$_model = new DeviceModel();
				$_model->setAttributes( $data );
				$_model->save();

			}
			catch ( \Exception $ex )
			{
				throw new InternalServerErrorException( "Failed to register user device.\n{$ex->getMessage()}", $ex->getCode() );
			}
		}

		return array( 'success' => true );
	}
}
