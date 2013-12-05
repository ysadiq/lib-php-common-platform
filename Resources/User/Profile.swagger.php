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

$_profile = array();

$_profile['apis'] = array(
	array(
		'path'        => '/{api_name}/profile',
		'operations'  =>
		array(
			array(
				'method'           => 'GET',
				'summary'          => 'getProfile() - Retrieve the current user\'s profile information.',
				'nickname'         => 'getProfile',
				'type'             => 'ProfileResponse',
				'responseMessages' =>
				array(
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'A valid current session is required to use this API. ' .
									  'This profile, along with password, is the only things that the user can directly change.',
			),
			array(
				'method'           => 'POST',
				'summary'          => 'updateProfile() - Update the current user\'s profile information.',
				'nickname'         => 'updateProfile',
				'type'             => 'Success',
				'parameters'       =>
				array(
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs for the user profile.',
						'allowMultiple' => false,
						'type'          => 'ProfileRequest',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' =>
				array(
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Update the display name, phone, etc., as well as, security question and answer.',
			),
		),
		'description' => 'Operations on a user\'s profile.',
	),
);

$_commonProfile = array(
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
);

$_profile['models'] = array(
	'ProfileRequest'  =>
	array(
		'id'         => 'ProfileRequest',
		'properties' => array_merge(
			$_commonProfile,
			array(
				 'security_answer' =>
				 array(
					 'type'        => 'string',
					 'description' => 'Answer to the security question.',
				 ),
			)
		),
	),
	'ProfileResponse' =>
	array(
		'id'         => 'ProfileResponse',
		'properties' => $_commonProfile,
	),
);

return $_profile;
