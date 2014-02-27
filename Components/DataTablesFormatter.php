<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * DataTablesFormatter
 * A simple data formatter
 */
class DataTablesFormatter implements FormatterLike
{
	/**
	 * @param mixed $dataToFormat
	 * @param array $options Any formatter-specific options
	 *
	 * @return mixed The formatted data
	 */
	public static function format( $dataToFormat, $options = array() )
	{
		if ( null === ( $_data = Option::get( $dataToFormat, 'resource' ) ) )
		{
			if ( null === ( $_data = Option::get( $dataToFormat, 'record' ) ) )
			{
				$_key = null;
				$_data = $dataToFormat;
			}
		}

		$_count = 0;
		$_echo = FilterInput::get( $_GET, 'sEcho', FILTER_SANITIZE_NUMBER_INT );
		$_response = array();

		if ( !empty( $_data ) )
		{
			foreach ( $_data as $_row )
			{
				//	DataTables just gets the values, not the keys
				$_response[] = array_values( $_row );
				$_count++;

				unset( $_row );
			}

			unset( $_rows );
		}

		//	DT expected format
		return array(
			'sEcho'                => $_echo,
			'iTotalRecords'        => $_count,
			'iTotalDisplayRecords' => $_count,
			'aaData'               => $_response,
		);
	}
}