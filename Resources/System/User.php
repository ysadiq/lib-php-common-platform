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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Services\EmailSvc;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Utility\ServiceHandler;
use DreamFactory\Platform\Yii\Models\Config;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * User
 * DSP system administration manager
 *
 */
class User extends BaseSystemRestResource
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
	 * @param array                                               $resources
	 */
	public function __construct( $consumer, $resources = array() )
	{
		$config = array(
			'service_name' => 'system',
			'name'         => 'User',
			'api_name'     => 'user',
			'type'         => 'System',
			'type_id'      => PlatformServiceTypes::SYSTEM_SERVICE,
			'description'  => 'System user administration.',
			'is_active'    => true,
		);

		parent::__construct( $consumer, $config, $resources );
	}

	/**
	 * {@InheritDoc}
	 */
	protected function _postProcess()
	{
		switch ( $this->_action )
		{
			case static::Post:
			case static::Put:
			case static::Patch:
				if ( Option::getBool( $_REQUEST, 'send_invite' ) )
				{
					if ( is_array( $this->_response ) )
					{
						if ( null !== ( $_records = Option::get( $this->_response, 'record' ) ) )
						{
							if ( 1 == count( $_records ) )
							{
								$_record = Option::get( $_records, 0 );
								if ( $_record )
								{
									$_id = Option::get( $_record, 'id' );
									static::_sendInvite( $_id, ( static::Post == $this->_action ) );
								}
							}
							else
							{
								foreach ( $_records as $_record )
								{
									$_id = Option::get( $_record, 'id' );
									try
									{
										static::_sendInvite( $_id );
									}
									catch ( \Exception $_ex )
									{
										//	Log it but don't error on batch
										Log::error( 'Error processing user invitation: ' . $_ex->getMessage() );
									}
								}
							}
						}
						else
						{
							// should be one
							$_id = Option::get( $this->_response, 'id' );
							static::_sendInvite( $_id, ( static::Post == $this->_action ) );
						}
					}
				}
				break;
		}

		parent::_postProcess();
	}

	/**
	 * @param int  $user_id
	 * @param bool $delete_on_error
	 *
	 * @throws \DreamFactory\Platform\Exceptions\NotFoundException
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
	 */
	protected static function _sendInvite( $user_id, $delete_on_error = false )
	{
		$_model = ResourceStore::model( 'user' );

		$_theUser = $_model->findByPk( $user_id );

		if ( null === $_theUser )
		{
			throw new NotFoundException( "No database entry exists for a user with id '$user_id'." );
		}

		// if already a confirmed user, error out
		if ( 'y' == $_theUser->confirm_code )
		{
			throw new BadRequestException( 'User with this identifier has already confirmed this account.' );
		}

		try
		{
			// otherwise, is email confirmation required?
			/** @var $_config Config */
			$_fields = 'invite_email_service_id, invite_email_template_id';
			if ( null === ( $_config = Config::model()->find( array( 'select' => $_fields ) ) ) )
			{
				throw new InternalServerErrorException( 'Unable to load system configuration.' );
			}

			$_serviceId = $_config->invite_email_service_id;
			if ( empty( $_serviceId ) )
			{
				throw new InternalServerErrorException( 'No email service configured for user invite. See system configuration.' );
			}

			/** @var EmailSvc $_emailService */
			$_emailService = ServiceHandler::getServiceObject( $_serviceId );
			if ( !$_emailService )
			{
				throw new InternalServerErrorException( "Bad service identifier '$_serviceId' for configured user invite email service." );
			}

			$_data = array();
			$_template = $_config->invite_email_template_id;
			if ( !empty( $_template ) )
			{
				$_data['template_id'] = $_template;
			}
			else
			{
				$_defaultPath = __DIR__ . '/../../templates/email/confirm_user_invitation.json';
				if ( !file_exists( $_defaultPath ) )
				{
					throw new InternalServerErrorException( "No default email template for user invite." );
				}

				$_data = file_get_contents( $_defaultPath );
				$_data = json_decode( $_data, true );
				if ( empty( $_data ) || !is_array( $_data ) )
				{
					throw new InternalServerErrorException( "No data found in default email template for user invite." );
				}
			}

			$_code = Hasher::generateUnique( $_theUser->email, 32 );
			try
			{
				$_theUser->setAttribute( 'confirm_code', $_code );
				$_theUser->save();

				$_data['to'] = $_theUser->email;
				$_userFields = array( 'first_name', 'last_name', 'display_name', 'confirm_code' );
				$_data = array_merge( $_data, $_theUser->getAttributes( $_userFields ) );
			}
			catch ( \Exception $ex )
			{
				throw new InternalServerErrorException( "Error creating user invite.\n{$ex->getMessage()}", $ex->getCode() );
			}

			$_emailService->sendEmail( $_data );
		}
		catch ( \Exception $ex )
		{
			if ( $delete_on_error )
			{
				$_model->deleteByPk( $user_id );
			}
			throw new InternalServerErrorException( "Error processing user invite.\n{$ex->getMessage()}", $ex->getCode() );
		}
	}
}
