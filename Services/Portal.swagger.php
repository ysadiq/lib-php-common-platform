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
	array(
		'path'        => '/{api_name}/{portal_name}/{portal_request}',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'Make an HTTP GET to the portal service.',
				'parameters'       => array(
					array(
						'name'          => 'flow_type',
						'description'   => 'Set the authentication flow to be server-side (0) or client-side (1)',
						'allowMultiple' => false,
						'type'          => 'integer',
						'paramType'     => 'query',
						'required'      => false,
						'defaultValue'  => 1,
					),
				),
				'responseMessages' => array(
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
				'notes'            => '',
			),
			array(
				'method'           => 'POST',
				'summary'          => 'Make an HTTP POST to the portal service.',
				'parameters'       => array(
					array(
						'name'          => 'body',
						'description'   => 'Data to post to service',
						'allowMultiple' => false,
						'type'          => 'Request',
						'paramType'     => 'body',
						'required'      => true,
					),
					array(
						'name'          => 'X-HTTP-METHOD',
						'description'   => 'Override request using POST to tunnel other http request, such as DELETE.',
						'enum'          => array( 'GET', 'PUT', 'PATCH', 'DELETE' ),
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'header',
						'required'      => false,
					),
				),
				'responseMessages' => array(
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
				'notes'            => '',
			),
			array(
				'method'           => 'DELETE',
				'summary'          => 'Make an HTTP DELETE to the portal service.',
				'responseMessages' => array(
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
				'notes'            => '',
			),
		),
		'description' => 'Operations available for the Portal service.',
	),
);

$_commonContainer = array(
	'name'       => array(
		'type'        => 'string',
		'description' => 'Identifier/Name for the container.',
	),
	'path'       => array(
		'type'        => 'string',
		'description' => 'Same as name for the container, for consistency.',
	),
	'_property_' => array(
		'type'        => 'string',
		'description' => 'Storage type specific properties.',
	),
	'metadata'   => array(
		'type'        => 'Array',
		'description' => 'An array of name-value pairs.',
		'items'       => array(
			'type' => 'string',
		),
	),
);

$_commonFolder = array(
	'name'       => array(
		'type'        => 'string',
		'description' => 'Identifier/Name for the folder, localized to requested resource.',
	),
	'path'       => array(
		'type'        => 'string',
		'description' => 'Full path of the folder, from the service including container.',
	),
	'_property_' => array(
		'type'        => 'string',
		'description' => 'Storage type specific properties.',
	),
	'metadata'   => array(
		'type'        => 'Array',
		'description' => 'An array of name-value pairs.',
		'items'       => array(
			'type' => 'string',
		),
	),
);

$_commonFile = array(
	'name'         => array(
		'type'        => 'string',
		'description' => 'Identifier/Name for the file, localized to requested resource.',
	),
	'path'         => array(
		'type'        => 'string',
		'description' => 'Full path of the file, from the service including container.',
	),
	'content_type' => array(
		'type'        => 'string',
		'description' => 'The media type of the content of the file.',
	),
	'_property_'   => array(
		'type'        => 'string',
		'description' => 'Storage type specific properties.',
	),
	'metadata'     => array(
		'type'        => 'Array',
		'description' => 'An array of name-value pairs.',
		'items'       => array(
			'type' => 'string',
		),
	),
);

return $_base;
