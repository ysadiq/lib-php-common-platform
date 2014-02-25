<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Components;

use DreamFactory\Platform\Interfaces\FormatterLike;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\Option;

/**
 * JTablesFormatter
 * A simple data formatter
 */
class JTablesFormatter implements FormatterLike
{
	/**
	 * @param mixed $dataToFormat
	 * @param array $options Any formatter-specific options
	 *
	 * @return mixed The formatted data
	 */
	public static function format( $dataToFormat, $options = array() )
	{
		$_key = 'Records';

		$_response = array(
			'Result' => isset( $_data, $_data['error'] ) ? 'ERROR' : 'OK',
		);

		if ( null === ( $_data = Option::get( $dataToFormat, 'resource' ) ) )
		{
			if ( null === ( $_data = Option::get( $dataToFormat, 'record' ) ) )
			{
				$_data = $dataToFormat;
			}
		}

		$_action = strtoupper( Option::get( $options, 'action' ) );

		switch ( $_action )
		{
			case HttpMethod::Delete:
				//	Result is all that is required.
				break;

			case HttpMethod::Get:
				$_response['Records'] = $_data;
				break;

			case HttpMethod::Put:
			case HttpMethod::Post:
			case HttpMethod::Merge:
				$_response['Record'] = isset( $_data[0] ) ? current( $_data ) : $_data;
				break;
		}

		//	expected format
		return $_response;
	}
}