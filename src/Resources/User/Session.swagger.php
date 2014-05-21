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

$_session = array();

$_session['apis'] = array(
	array(
		'path'        => '/{api_name}/session',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'getSession() - Retrieve the current user session information.',
				'nickname'         => 'getSession',
				'event_name'       => '{api_name}.session.read',
				'type'             => 'Session',
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
				'notes'            => 'Calling this refreshes the current session, or returns an error for timed-out or invalid sessions.',
			),
			array(
				'method'           => 'POST',
				'summary'          => 'login() - Login and create a new user session.',
				'nickname'         => 'login',
				'type'             => 'Session',
				'event_name'       => '{api_name}.session.create',
				'parameters'       => array(
					array(
						'name'          => 'body',
						'description'   => 'Data containing name-value pairs used for logging into the system.',
						'allowMultiple' => false,
						'type'          => 'Login',
						'paramType'     => 'body',
						'required'      => true,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Calling this creates a new session and logs in the user.',
			),
			array(
				'method'           => 'DELETE',
				'summary'          => 'logout() - Logout and destroy the current user session.',
				'nickname'         => 'logout',
				'type'             => 'Success',
				'event_name'       => '{api_name}.session.delete',
				'responseMessages' => array(
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

$_session['models'] = array(
	'Session'    => array(
		'id'         => 'Session',
		'properties' => array(
			'id'              => array(
				'type'        => 'string',
				'description' => 'Identifier for the current user.',
			),
			'email'           => array(
				'type'        => 'string',
				'description' => 'Email address of the current user.',
			),
			'first_name'      => array(
				'type'        => 'string',
				'description' => 'First name of the current user.',
			),
			'last_name'       => array(
				'type'        => 'string',
				'description' => 'Last name of the current user.',
			),
			'display_name'    => array(
				'type'        => 'string',
				'description' => 'Full display name of the current user.',
			),
			'is_sys_admin'    => array(
				'type'        => 'boolean',
				'description' => 'Is the current user a system administrator.',
			),
			'role'            => array(
				'type'        => 'string',
				'description' => 'Name of the role to which the current user is assigned.',
			),
			'last_login_date' => array(
				'type'        => 'string',
				'description' => 'Date timestamp of the last login for the current user.',
			),
			'app_groups'      => array(
				'type'        => 'Array',
				'description' => 'App groups and the containing apps.',
				'items'       => array(
					'$ref' => 'SessionApp',
				),
			),
			'no_group_apps'   => array(
				'type'        => 'Array',
				'description' => 'Apps that are not in any app groups.',
				'items'       => array(
					'$ref' => 'SessionApp',
				),
			),
			'session_id'      => array(
				'type'        => 'string',
				'description' => 'Id for the current session, used in X-DreamFactory-Session-Token header for API requests.',
			),
			'ticket'          => array(
				'type'        => 'string',
				'description' => 'Timed ticket that can be used to start a separate session.',
			),
			'ticket_expiry'   => array(
				'type'        => 'string',
				'description' => 'Expiration time for the given ticket.',
			),
		),
	),
	'Login'      => array(
		'id'         => 'Login',
		'properties' => array(
			'email'    => array(
				'type'     => 'string',
				'required' => true,
			),
			'password' => array(
				'type'     => 'string',
				'required' => true,
			),
            'duration' => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Duration of the session, Defaults to 0, which means until browser is closed.',
            ),
		),
	),
	'SessionApp' => array(
		'id'         => 'SessionApp',
		'properties' => array(
			'id'                      => array(
				'type'        => 'integer',
				'format'      => 'int32',
				'description' => 'Id of the application.',
			),
			'name'                    => array(
				'type'        => 'string',
				'description' => 'Displayed name of the application.',
			),
			'description'             => array(
				'type'        => 'string',
				'description' => 'Description of the application.',
			),
			'is_url_external'         => array(
				'type'        => 'boolean',
				'description' => 'Does this application exist on a separate server.',
			),
			'launch_url'              => array(
				'type'        => 'string',
				'description' => 'URL at which this app can be accessed.',
			),
			'requires_fullscreen'     => array(
				'type'        => 'boolean',
				'description' => 'True if the application requires fullscreen to run.',
			),
			'allow_fullscreen_toggle' => array(
				'type'        => 'boolean',
				'description' => 'True allows the fullscreen toggle widget to be displayed.',
			),
			'toggle_location'         => array(
				'type'        => 'string',
				'description' => 'Where the fullscreen toggle widget is to be displayed, defaults to top.',
			),
			'is_default'              => array(
				'type'        => 'boolean',
				'description' => 'True if this app is set to launch by default at sign in.',
			),
		),
	),
);

return $_session;
