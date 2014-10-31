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
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BaseUserRestResource;
use DreamFactory\Platform\Yii\Models\User;
use Kisma\Core\Utility\Option;

/**
 * CustomSettings
 * DSP user custom settings
 */
class CustomSettings extends BaseUserRestResource
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
				'name'         => 'User Custom Settings',
				'service_name' => 'user',
				'type'         => 'System',
				'type_id'      => PlatformServiceTypes::SYSTEM_SERVICE,
				'api_name'     => 'custom',
				'description'  => 'Resource for a user to manage their custom settings.',
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
	 * @return array
	 */
	protected function _handleGet()
	{
		// check valid session,
		// using userId from session, get user_data attribute
		$_userId = Session::validateSession();

		return $this->getCustomSettings( $_userId, $this->_setting );
	}

	/**
	 * @return array
	 */
	protected function _handlePost()
	{
        $this->_triggerActionEvent( $this->_response );

		// check valid session,
		// using userId from session, get user_data attribute
		$_userId = Session::validateSession();

		return $this->setCustomSettings( $_userId, $this->_requestPayload, $this->_setting );
	}

	/**
	 * @return array
	 */
	protected function _handleDelete()
	{
        $this->_triggerActionEvent( $this->_response );

		// check valid session,
		// using userId from session, get user_data attribute
		$_userId = Session::validateSession();

		return $this->deleteCustomSettings( $_userId, $this->_setting );
	}

	//-------- User Operations ------------------------------------------------

	/**
	 * @param int    $user_id
	 * @param string $setting
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return array
	 */
	public static function getCustomSettings( $user_id, $setting = '' )
	{
		$_theUser = User::model()->findByPk( $user_id );
		if ( null === $_theUser )
		{
			// bad session
			throw new NotFoundException( "The user for the current session was not found in the system." );
		}

		try
		{
			$_data = $_theUser->getAttribute( 'user_data' );
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
			throw new InternalServerErrorException( "Error retrieving custom user settings.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param int    $user_id
	 * @param array  $data
	 * @param string $setting
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return array
	 */
	public static function setCustomSettings( $user_id, $data, $setting = '' )
	{
		if ( !empty( $setting ) )
		{
			throw new BadRequestException( 'Setting individual custom setting is not currently supported.' );
		}

		$_theUser = User::model()->findByPk( $user_id );
		if ( null === $_theUser )
		{
			// bad session
			throw new NotFoundException( "The user for the current session was not found in the system." );
		}

		try
		{
			$_old = $_theUser->getAttribute( 'user_data' );
			$_new = array_merge( $_old, $data );
			$_theUser->setAttribute( 'user_data', $_new );
			$_theUser->save();

			return array( 'success' => true );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error processing custom user settings update.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}

	/**
	 * @param int    $user_id
	 * @param string $setting
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 * @return array
	 */
	public static function deleteCustomSettings( $user_id, $setting = '' )
	{
		if ( empty( $setting ) )
		{
			throw new BadRequestException( 'Deleting all custom settings is not currently supported.' );
		}

		$_theUser = User::model()->findByPk( $user_id );
		if ( null === $_theUser )
		{
			// bad session
			throw new NotFoundException( "The user for the current session was not found in the system." );
		}

		try
		{
			$_data = $_theUser->getAttribute( 'user_data' );
			unset( $_data[$setting] );
			$_theUser->setAttribute( 'user_data', $_data );
			$_theUser->save();

			return array( 'success' => true );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error processing custom user settings delete.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}
}
