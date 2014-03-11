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

$_register = array();

$_register['apis'] = array(
	array(
		'path'        => '/{api_name}/register',
		'operations'  => array(
			array(
				'method'           => 'POST',
				'summary'          => 'register() - Register a new user in the system.',
				'nickname'         => 'register',
				'type'             => 'Success',
				'event_name'       => 'user.create',
				'parameters'       => array(
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs for new user registration.',
						'allowMultiple' => false,
						'type'          => 'Register',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            =>
					'The new user is created and, if required, sent an email for confirmation. ' .
					'This also handles the registration confirmation by posting email, ' .
					'confirmation code and new password.',
			),
		),
		'description' => 'Operations to register a new user.',
	),
);

$_register['models'] = array(
	'Register' => array(
		'id'         => 'Register',
		'properties' => array(
			'email'        => array(
				'type'        => 'string',
				'description' => 'Email address of the new user.',
				'required'    => true,
			),
			'first_name'   => array(
				'type'        => 'string',
				'description' => 'First name of the new user.',
			),
			'last_name'    => array(
				'type'        => 'string',
				'description' => 'Last name of the new user.',
			),
			'display_name' => array(
				'type'        => 'string',
				'description' => 'Full display name of the new user.',
			),
			'new_password' => array(
				'type'        => 'string',
				'description' => 'Password for the new user.',
			),
			'code'         => array(
				'type'        => 'string',
				'description' => 'Code required with new_password when using email confirmation.',
			),
		),
	),
);

return $_register;
