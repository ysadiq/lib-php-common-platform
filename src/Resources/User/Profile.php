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
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Yii\Models\User;

/**
 * Profile
 * DSP user profile
 */
class Profile extends BasePlatformRestResource
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
				'name'           => 'User Profile',
				'service_name'   => 'user',
				'type'           => 'System',
				'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
				'api_name'       => 'profile',
				'description'    => 'Resource for a user to manage their profile.',
				'is_active'      => true,
				'resource_array' => $resources,
				'verb_aliases'   => array(
					static::PUT   => static::POST,
					static::PATCH => static::POST,
					static::MERGE => static::POST,
				)
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
		// using userId from session, get profile attributes
		$_userId = Session::validateSession();

		return $this->getProfile( $_userId );
	}

	/**
	 * @return array|bool|void
	 */
	protected function _handlePost()
	{
		// check valid session,
		// using userId from session, get profile attributes
		$_userId = Session::validateSession();
		$_data = RestData::getPostedData( false, true );

		return $this->changeProfile( $_userId, $_data );
	}

	//-------- User Operations ------------------------------------------------

	/**
	 * @param int $user_id
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return array
	 */
	public static function getProfile( $user_id )
	{
		$_theUser = User::model()->findByPk( $user_id );
		if ( null === $_theUser )
		{
			// bad session
			throw new NotFoundException( "The user for the current session was not found in the system." );
		}

		try
		{
			$_fields = $_theUser->getAttributes( User::getProfileAttributes() );

			return $_fields;
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error retrieving profile.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param int   $user_id
	 * @param array $record
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return bool
	 */
	public static function changeProfile( $user_id, $record )
	{
		$_theUser = User::model()->findByPk( $user_id );
		if ( null === $_theUser )
		{
			// bad session
			throw new NotFoundException( "The user for the current session was not found in the system." );
		}

		$_allow = User::getProfileAttributes( true );
		foreach ( $record as $_key => $_value )
		{
			if ( false === array_search( $_key, $_allow ) )
			{
				throw new InternalServerErrorException( "Attribute '$_key' can not be updated through profile change." );
			}
		}

		try
		{
			$_theUser->setAttributes( $record );
			$_theUser->save();

			return array( 'success' => true );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error processing profile change.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}
}
