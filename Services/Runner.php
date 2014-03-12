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
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Enums\DataFormats;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;

/**
 * Runner.php
 * File storage service that can run sandboxed scripts
 */
class Runner extends BaseSystemRestService
{
	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * @param array $settings
	 */
	public function __construct( $settings = array() )
	{
		//	Pull out our settings before calling daddy
		$_settings = array_merge(
			array(
				'name'          => 'Runner',
				'description'   => 'A sandboxed script running service.',
				'api_name'      => 'runner',
				'type_id'       => PlatformServiceTypes::SYSTEM_SERVICE,
				'is_active'     => true,
				'native_format' => DataFormats::NATIVE,
			),
			$settings
		);

		parent::__construct( $_settings );
	}

	/**
	 * Handle a run request
	 *
	 * Comes in like this:
	 *
	 *    /rest/runner/{script_id}
	 *
	 *
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
	 * @return bool
	 */
	protected function _handleResource()
	{
		if ( empty( $this->_resource ) || $this->_action != HttpMethod::GET )
		{
			throw new BadRequestException( 'Only "GET" requests with a valid script ID are allowed.' );
		}

		$_runner = new \V8Js();

		$_script = <<< EOT
len = print('Hello' + ' ' + 'World!' + "\\n");
len;
EOT;

		try
		{
			$_runner->executeString( $_script, $this->_resource . '.js' );
		}
		catch ( \V8JsException $_ex )
		{
			Log::error( 'Exception executing javascript: ' . $_ex->getMessage() );
		}
	}

}
