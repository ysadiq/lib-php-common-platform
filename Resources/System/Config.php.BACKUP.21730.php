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
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Services\BasePlatformService;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Yii\Models\Provider;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;
use Swagger\Annotations as SWG;

/**
 * Config
 * DSP system administration manager
 *
 * @SWG\Resource(
 *   resourcePath="/system"
 * )
 *
 * @SWG\Model(id="Config",
 * @SWG\Property(name="dsp_version",type="string",description="Version of the DSP software."),
 * @SWG\Property(name="db_version",type="string",description="Version of the database schema."),
 * @SWG\Property(name="allow_open_registration",type="boolean",description="Allow guests to register for a user account."),
 * @SWG\Property(name="open_reg_role_id",type="int",description="Default Role Id assigned to newly registered users."),
 * @SWG\Property(name="allow_guest_user",type="boolean",description="Allow app access for non-authenticated users."),
 * @SWG\Property(name="guest_role_id",type="int",description="Role Id assigned for all guest sessions."),
 * @SWG\Property(name="editable_profile_fields",type="string",description="Comma-delimited list of fields the user is allowed to edit."),
 * @SWG\Property(name="allowed_hosts",type="Array",items="$ref:HostInfo",description="CORS whitelist of allowed remote hosts.")
 * )
 * @SWG\Model(id="HostInfo",
 * @SWG\Property(name="host",type="string",description="URL, server name, or * to define the CORS host."),
 * @SWG\Property(name="is_enabled",type="boolean",description="Allow this host's configuration to be used by CORS."),
 * @SWG\Property(name="verbs",type="Array",items="$ref:string",description="Allowed HTTP verbs for this host.")
 * )
 *
 */
class Config extends BaseSystemRestResource
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Constructor
	 *
	 * @param BasePlatformService $consumer
	 * @param array               $resourceArray
	 *
	 * @return Config
	 */
	public function __construct( $consumer = null, $resourceArray = array() )
	{
		parent::__construct(
			$consumer,
			array(
				 'name'           => 'Configuration',
				 'type'           => 'System',
				 'service_name'   => 'system',
				 'type_id'        => PlatformServiceTypes::SYSTEM_SERVICE,
				 'api_name'       => 'config',
				 'description'    => 'Service general configuration',
				 'is_active'      => true,
				 'resource_array' => $resourceArray,
				 'verb_aliases'   => array(
					 static::Patch => static::Put,
					 static::Merge => static::Put,
				 )
			)
		);
	}

	/**
	 * @SWG\Api(
	 *       path="/system/config", description="Operations for system configuration options.",
	 * @SWG\Operations(
	 * @SWG\Operation(
	 *       httpMethod="GET", summary="Retrieve system configuration options.",
	 *       notes="The retrieved properties control how the system behaves.",
	 *       responseClass="Config", nickname="getConfig"
	 *     )
	 *   )
	 * )
	 *
	 * @param string $fields
	 * @param bool   $includeSchema
	 * @param array  $extras
	 *
	 * @return array
	 */

	/**
	 * {@InheritDoc}
	 */
	protected function _postProcess()
	{
		//	Only return a single row, not in an array
		if ( null !== ( $_record = Option::getDeep( $this->_response, 'record', 0 ) ) )
		{
			if ( 1 == sizeof( $this->_response['record'] ) )
			{
				$this->_response = $_record;
			}
		}

		$this->_response['dsp_version'] = DSP_VERSION;
<<<<<<< HEAD
		if ( false !== ( $this->_response['allow_remote_logins'] = Pii::getParam( 'dsp.allow_remote_logins' ) ) )
=======

		$this->_response['allow_remote_logins'] = ( Pii::getParam( 'dsp.allow_remote_logins', false ) && $this->_response['allow_open_registration'] );

		if ( false !== $this->_response['allow_remote_logins'] )
>>>>>>> feature/remote-login
		{
			$_rows = Sql::findAll( 'select id, api_name, provider_name from df_sys_provider order by 1', array(), Pii::pdo() );

			if ( !empty( $_rows ) )
			{
				$this->_response['remote_login_providers'] = array();

				foreach ( $_rows as $_row )
				{
					$_name = $_row['provider_name'];
					if ( empty( $_name ) )
					{
						$_name = $_row['api_name'];
					}
					$this->_response['remote_login_providers'][] = $_name;
				}
			}
			else
			{
				//	No providers, no remote logins
				$this->_response['allow_remote_logins'] = false;
			}
		}

		parent::_postProcess();
	}
}
