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

return array(
	'resourcePath' => '/{api_name}',
	'apis'         =>
	array(
		array(
			'path'        => '/{api_name}',
			'operations'  =>
			array(
				array(
					'method'           => 'POST',
					'summary'          => 'sendEmail() - Send an email created from posted data.',
					'nickname'         => 'sendEmail',
					'type'             => 'EmailResponse',
					'parameters'       =>
					array(
						array(
							'name'          => 'template',
							'description'   => 'Optional template to base email on.',
							'allowMultiple' => false,
							'type'          => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						array(
							'name'          => 'data',
							'description'   => 'Data containing name-value pairs used for provisioning emails.',
							'allowMultiple' => false,
							'type'          => 'EmailRequest',
							'paramType'     => 'body',
							'required'      => false,
						),
					),
					'responseMessages' =>
					array(
						array(
							'message' => 'Bad Request - Request is not complete or valid.',
							'code'    => 400,
						),
						array(
							'message' => 'Unauthorized Access - No currently valid session available.',
							'code'    => 401,
						),
						array(
							'message' => 'Not Found - Email template or system resource not found.',
							'code'    => 404,
						),
						array(
							'message' => 'System Error - Specific reason is included in the error message.',
							'code'    => 500,
						),
					),
					'notes'            => 'If a template is not used with all required fields, then they must be included in the request. '.
										  'If the \'from\' address is not provisioned in the service, then it must be included in the request.',
				),
			),
			'description' => 'Operations on a email service.',
		),
	),
	'models'       =>
	array(
		'EmailResponse' =>
		array(
			'id'         => 'EmailResponse',
			'properties' =>
			array(
				'count' =>
				array(
					'type'        => 'integer',
					'description' => 'Number of emails successfully sent.',
				),
			),
		),
		'EmailRequest'         =>
		array(
			'id'         => 'Email',
			'properties' =>
			array(
				'template'       =>
				array(
					'type'        => 'string',
					'description' => 'Email Template to base email on.',
				),
				'to'             =>
				array(
					'type'        => 'Array',
					'description' => 'Required single or multiple receiver addresses.',
					'items'       =>
					array(
						'$ref' => 'EmailAddress',
					),
				),
				'cc'             =>
				array(
					'type'        => 'Array',
					'description' => 'Optional CC receiver addresses.',
					'items'       =>
					array(
						'$ref' => 'EmailAddress',
					),
				),
				'bcc'            =>
				array(
					'type'        => 'Array',
					'description' => 'Optional BCC receiver addresses.',
					'items'       =>
					array(
						'$ref' => 'EmailAddress',
					),
				),
				'subject'        =>
				array(
					'type'        => 'string',
					'description' => 'Text only subject line.',
				),
				'body_text'      =>
				array(
					'type'        => 'string',
					'description' => 'Text only version of the body.',
				),
				'body_html'      =>
				array(
					'type'        => 'string',
					'description' => 'Escaped HTML version of the body.',
				),
				'from_name'      =>
				array(
					'type'        => 'string',
					'description' => 'Required sender name.',
				),
				'from_email'     =>
				array(
					'type'        => 'string',
					'description' => 'Required sender email.',
				),
				'reply_to_name'  =>
				array(
					'type'        => 'string',
					'description' => 'Optional reply to name.',
				),
				'reply_to_email' =>
				array(
					'type'        => 'string',
					'description' => 'Optional reply to email.',
				),
			),
		),
		'EmailAddress'  =>
		array(
			'id'         => 'EmailAddress',
			'properties' =>
			array(
				'name'  =>
				array(
					'type'        => 'string',
					'description' => 'Optional name displayed along with the email address.',
				),
				'email' =>
				array(
					'type'        => 'string',
					'description' => 'Required email address.',
				),
			),
		),
	),
);