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

/**
 * BasePlatformRestService
 * A base class for all DSP REST Swagger services
 *
 * Some basic apis and models used in DSP REST interfaces
 *
 */
return array(
	'resourcePath' => '/{api_name}',
	'produces'     => array( 'application/json', 'application/xml' ),
	'consumes'     => array( 'application/json', 'application/xml' ),
	'apis'         =>
		array(
			array(
				'path'        => '/{api_name}',
				'operations'  =>
					array(),
				'description' => 'No operations currently defined for this service.',
			),
		),
	'models'       =>
		array(
			'Resources' =>
				array(
					'id'         => 'Resources',
					'properties' =>
						array(
							'resource' =>
								array(
									'type'        => 'Array',
									'description' => 'Array of resources available by this service.',
									'items'       =>
										array(
											'$ref' => 'Resource',
										),
								),
						),
				),
			'Resource'  =>
				array(
					'id'         => 'Resource',
					'properties' =>
						array(
							'name' =>
								array(
									'type'        => 'string',
									'description' => 'Name of the resource.',
								),
						),
				),
			'Success'   =>
				array(
					'id'         => 'Success',
					'properties' =>
						array(
							'success' =>
								array(
									'type'        => 'boolean',
									'description' => 'True when API call was successful, false or error otherwise.',
								),
						),
				),
		),
);