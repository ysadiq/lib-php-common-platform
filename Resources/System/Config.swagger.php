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

$_base = array();

$_base['apis'] = array(
	array(
		'path'        => '/{api_name}/config',
		'operations'  =>
		array(
			array(
				'method'   => 'GET',
				'summary'  => 'getConfig() - Retrieve system configuration properties.',
				'nickname' => 'getConfig',
				'type'     => 'ConfigResponse',
				'notes'    => 'The retrieved properties control how the system behaves.',
			),
			array(
				'method'           => 'POST',
				'summary'          => 'setConfig() - Update one or more system configuration properties.',
				'nickname'         => 'setConfig',
				'type'             => 'ConfigResponse',
				'parameters'       =>
				array(
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs of properties to set.',
						'allowMultiple' => false,
						'type'          => 'ConfigRequest',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' =>
				array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Post data should be an array of properties.',
			),
		),
		'description' => 'Operations for system configuration options.',
	),
);

$_commonProperties = array(
	'allow_open_registration' =>
	array(
		'type'        => 'boolean',
		'description' => 'Allow guests to register for a user account.',
	),
	'open_reg_role_id'        =>
	array(
		'type'        => 'integer',
		'description' => 'Default Role Id assigned to newly registered users.',
	),
	'allow_guest_user'        =>
	array(
		'type'        => 'boolean',
		'description' => 'Allow app access for non-authenticated users.',
	),
	'guest_role_id'           =>
	array(
		'type'        => 'integer',
		'description' => 'Role Id assigned for all guest sessions.',
	),
	'editable_profile_fields' =>
	array(
		'type'        => 'string',
		'description' => 'Comma-delimited list of fields the user is allowed to edit.',
	),
	'allowed_hosts'           =>
	array(
		'type'        => 'Array',
		'description' => 'CORS whitelist of allowed remote hosts.',
		'items'       =>
		array(
			'$ref' => 'HostInfo',
		),
	),
);

$_base['models'] = array(
	'ConfigRequest'  =>
	array(
		'id'         => 'ConfigRequest',
		'properties' => $_commonProperties,
	),
	'ConfigResponse' =>
	array(
		'id'         => 'ConfigResponse',
		'properties' =>
		array_merge(
			$_commonProperties,
			array(
				 'dsp_version' =>
				 array(
					 'type'        => 'string',
					 'description' => 'Version of the DSP software.',
				 ),
				 'db_version'  =>
				 array(
					 'type'        => 'string',
					 'description' => 'Version of the database schema.',
				 ),
			)
		),
	),
	'HostInfo'       =>
	array(
		'id'         => 'HostInfo',
		'properties' =>
		array(
			'host'       =>
			array(
				'type'        => 'string',
				'description' => 'URL, server name, or * to define the CORS host.',
			),
			'is_enabled' =>
			array(
				'type'        => 'boolean',
				'description' => 'Allow this host\'s configuration to be used by CORS.',
			),
			'verbs'      =>
			array(
				'type'        => 'Array',
				'description' => 'Allowed HTTP verbs for this host.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
		),
	),
);

return $_base;
