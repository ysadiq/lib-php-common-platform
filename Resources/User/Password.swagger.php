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

$_password = array();

$_password['apis'] = array(
	array(
		'path'        => '/{api_name}/password',
		'operations'  =>
		array(
			array(
				'method'           => 'POST',
				'summary'          => 'changePassword() - Change or reset the current user\'s password.',
				'nickname'         => 'changePassword',
				'type'             => 'PasswordResponse',
				'parameters'       =>
				array(
					array(
						'name'          => 'reset',
						'description'   => 'Set to true to perform password reset.',
						'allowMultiple' => false,
						'type'          => 'boolean',
						'paramType'     => 'query',
						'required'      => false,
					),
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs for password change.',
						'allowMultiple' => false,
						'type'          => 'PasswordRequest',
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
				'notes'            => 'A valid current session along with old and new password are required to change '.
									  'the password directly posting \'old_password\' and \'new_password\'. <br/>' .
									  'To request password reset, post \'email\' and set \'reset\' to true. <br/>' .
									  'To reset the password from an email confirmation, post \'email\', \'code\', and \'new_password\'. <br/>' .
									  'To reset the password from a security question, post \'email\', \'security_answer\', and \'new_password\'.',
			),
		),
		'description' => 'Operations on a user\'s password.',
	),
);

$_password['models'] = array(
	'PasswordRequest'  =>
	array(
		'id'         => 'PasswordRequest',
		'properties' =>
		array(
			'old_password' =>
			array(
				'type'        => 'string',
				'description' => 'Old password to validate change during a session.',
			),
			'new_password' =>
			array(
				'type'        => 'string',
				'description' => 'New password to be set.',
			),
			'email'        =>
			array(
				'type'        => 'string',
				'description' => 'User\'s email to be used with code to validate email confirmation.',
			),
			'code'         =>
			array(
				'type'        => 'string',
				'description' => 'Code required with new_password when using email confirmation.',
			),
		),
	),
	'PasswordResponse' =>
	array(
		'id'         => 'PasswordResponse',
		'properties' =>
		array(
			'security_question' =>
			array(
				'type'        => 'string',
				'description' => 'User\'s security question, returned on reset request when no email confirmation required.',
			),
			'success'           =>
			array(
				'type'        => 'boolean',
				'description' => 'True if password updated or reset request granted via email confirmation.',
			),
		),
	),
);

return $_password;
