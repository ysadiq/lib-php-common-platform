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

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );

$_base['apis'] = array(
	0 =>
	array(
		'path'        => '/{api_name}',
		'operations'  =>
		array(
			0 =>
			array(
				'method'   => 'GET',
				'summary'  => 'List resources available for user session management.',
				'nickname' => 'getResources',
				'type'     => 'Resources',
				'notes'    => 'See listed operations for each resource available.',
			),
		),
		'description' => 'Operations available for user session management.',
	),
	1 =>
	array(
		'path'        => '/{api_name}/challenge',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve the security challenge question for the given user.',
				'nickname'         => 'getChallenge',
				'type'             => 'Question',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'email',
						'description'   => 'User email used to request security question.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'query',
						'required'      => true,
						'defaultValue'  => 'user@mycompany.com',
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					1 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Use this question to challenge the user..',
			),
			1 =>
			array(
				'method'           => 'POST',
				'summary'          => 'Answer the security challenge question for the given user.',
				'nickname'         => 'answerChallenge',
				'type'             => 'Session',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'answer',
						'description'   => 'Answer to the security question.',
						'allowMultiple' => false,
						'type'          => 'Answer',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					1 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Use this to gain temporary access to change password.',
			),
		),
		'description' => 'Operations on a user\'s security challenge.',
	),
	2 =>
	array(
		'path'        => '/{api_name}/confirm',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'POST',
				'summary'          => 'Confirm a new user registration or password change request.',
				'nickname'         => 'confirmUser',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'confirmation',
						'description'   => 'Data containing name-value pairs for new user confirmation.',
						'allowMultiple' => false,
						'type'          => 'Confirm',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					1 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'The new user is confirmed and assumes the role given by system admin.',
			),
		),
		'description' => 'Operations on a user\'s confirmation.',
	),
	3 =>
	array(
		'path'        => '/{api_name}/password',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'POST',
				'summary'          => 'Update the current user\'s password.',
				'nickname'         => 'changePassword',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'credentials',
						'description'   => 'Data containing name-value pairs for password change.',
						'allowMultiple' => false,
						'type'          => 'Password',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					1 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'A valid session is required to change the password through this API.',
			),
		),
		'description' => 'Operations on a user\'s password.',
	),
	4 =>
	array(
		'path'        => '/{api_name}/profile',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve the current user\'s profile information.',
				'nickname'         => 'getProfile',
				'type'             => 'Profile',
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					1 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'This profile, along with password, is the only things that the user can directly change.',
			),
			1 =>
			array(
				'method'           => 'POST',
				'summary'          => 'Update the current user\'s profile information.',
				'nickname'         => 'changeProfile',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'profile',
						'description'   => 'Data containing name-value pairs for the user profile.',
						'allowMultiple' => false,
						'type'          => 'Profile',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					1 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Update the security question and answer through this api, as well as, display name, email, etc.',
			),
		),
		'description' => 'Operations on a user\'s profile.',
	),
	5 =>
	array(
		'path'        => '/{api_name}/register',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'POST',
				'summary'          => 'Register a new user in the system.',
				'nickname'         => 'registerUser',
				'type'             => 'Success',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'registration',
						'description'   => 'Data containing name-value pairs for new user registration.',
						'allowMultiple' => false,
						'type'          => 'Register',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					1 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'The new user is created and sent an email for confirmation.',
			),
		),
		'description' => 'Operations on a user\'s security challenge.',
	),
	6 =>
	array(
		'path'        => '/{api_name}/session',
		'operations'  =>
		array(
			0 =>
			array(
				'method'           => 'GET',
				'summary'          => 'Retrieve the current user session information.',
				'nickname'         => 'getSession',
				'type'             => 'Session',
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					1 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Calling this refreshes the current session, or returns an error for timed-out or invalid sessions.',
			),
			1 =>
			array(
				'method'           => 'POST',
				'summary'          => 'Login and create a new user session.',
				'nickname'         => 'login',
				'type'             => 'Session',
				'parameters'       =>
				array(
					0 =>
					array(
						'name'          => 'credentials',
						'description'   => 'Data containing name-value pairs used for logging into the system.',
						'allowMultiple' => false,
						'type'          => 'Login',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					1 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					2 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Calling this creates a new session and logs in the user.',
			),
			2 =>
			array(
				'method'           => 'DELETE',
				'summary'          => 'Logout and destroy the current user session.',
				'nickname'         => 'logout',
				'type'             => 'Success',
				'responseMessages' =>
				array(
					0 =>
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					1 =>
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Calling this deletes the current session and logs out the user.',
			),
		),
		'description' => 'Operations on a user\'s session.',
	),
);

$_models = array(
	'Session'  =>
	array(
		'id'         => 'Session',
		'properties' =>
		array(
			'id'              =>
			array(
				'type'        => 'string',
				'description' => 'Identifier for the current user.',
			),
			'email'           =>
			array(
				'type'        => 'string',
				'description' => 'Email address of the current user.',
			),
			'first_name'      =>
			array(
				'type'        => 'string',
				'description' => 'First name of the current user.',
			),
			'last_name'       =>
			array(
				'type'        => 'string',
				'description' => 'Last name of the current user.',
			),
			'display_name'    =>
			array(
				'type'        => 'string',
				'description' => 'Full display name of the current user.',
			),
			'is_sys_admin'    =>
			array(
				'type'        => 'boolean',
				'description' => 'Is the current user a system administrator.',
			),
			'last_login_date' =>
			array(
				'type'        => 'string',
				'description' => 'Date and time of the last login for the current user.',
			),
			'app_groups'      =>
			array(
				'type'        => 'Array',
				'description' => 'App groups and the containing apps.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'no_group_apps'   =>
			array(
				'type'        => 'Array',
				'description' => 'Apps that are not in any app groups.',
				'items'       =>
				array(
					'type' => 'string',
				),
			),
			'ticket'          =>
			array(
				'type'        => 'string',
				'description' => 'Timed ticket that can be used to start a separate session.',
			),
			'ticket_expiry'   =>
			array(
				'type'        => 'string',
				'description' => 'Expiration time for the given ticket.',
			),
		),
	),
	'Login'    =>
	array(
		'id'         => 'Login',
		'properties' =>
		array(
			'email'    =>
			array(
				'type' => 'string',
			),
			'password' =>
			array(
				'type' => 'string',
			),
		),
	),
	'Register' =>
	array(
		'id'         => 'Register',
		'properties' =>
		array(
			'email'        =>
			array(
				'type'        => 'string',
				'description' => 'Email address of the current user.',
			),
			'first_name'   =>
			array(
				'type'        => 'string',
				'description' => 'First name of the current user.',
			),
			'last_name'    =>
			array(
				'type'        => 'string',
				'description' => 'Last name of the current user.',
			),
			'display_name' =>
			array(
				'type'        => 'string',
				'description' => 'Full display name of the current user.',
			),
		),
	),
	'Confirm'  =>
	array(
		'id'         => 'Confirm',
		'properties' =>
		array(
			'email'        =>
			array(
				'type' => 'string',
			),
			'new_password' =>
			array(
				'type' => 'string',
			),
		),
	),
	'Question' =>
	array(
		'id'         => 'Question',
		'properties' =>
		array(
			'security_question' =>
			array(
				'type' => 'string',
			),
		),
	),
	'Answer'   =>
	array(
		'id'         => 'Answer',
		'properties' =>
		array(
			'email'           =>
			array(
				'type' => 'string',
			),
			'security_answer' =>
			array(
				'type' => 'string',
			),
		),
	),
	'Password' =>
	array(
		'id'         => 'Password',
		'properties' =>
		array(
			'old_password' =>
			array(
				'type' => 'string',
			),
			'new_password' =>
			array(
				'type' => 'string',
			),
		),
	),
	'Profile'  =>
	array(
		'id'         => 'Profile',
		'properties' =>
		array(
			'email'             =>
			array(
				'type'        => 'string',
				'description' => 'Email address of the current user.',
			),
			'first_name'        =>
			array(
				'type'        => 'string',
				'description' => 'First name of the current user.',
			),
			'last_name'         =>
			array(
				'type'        => 'string',
				'description' => 'Last name of the current user.',
			),
			'display_name'      =>
			array(
				'type'        => 'string',
				'description' => 'Full display name of the current user.',
			),
			'phone'             =>
			array(
				'type'        => 'string',
				'description' => 'Phone number.',
			),
			'security_question' =>
			array(
				'type'        => 'string',
				'description' => 'Question to be answered to initiate password reset.',
			),
			'default_app_id'    =>
			array(
				'type'        => 'integer',
				'description' => 'Id of the application to be launched at login.',
			),
		),
	),
);

$_base['models'] = array_merge( $_base['models'], $_models );

return $_base;
