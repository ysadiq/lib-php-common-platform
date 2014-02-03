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
use DreamFactory\Yii\Controllers\BaseWebController;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * aciTree data formatter
 */
class AciTreeFormatter implements FormatterLike
{
	/**
	 * @param mixed $dataToFormat
	 * @param array $options Any formatter-specific options
	 *
	 * @return mixed The formatted data
	 */
	public static function format( $dataToFormat, $options = array() )
	{
		$_response = array();

		if ( null === ( $_data = Option::get( $dataToFormat, 'resource' ) ) )
		{
			if ( null === ( $_data = Option::get( $dataToFormat, 'record' ) ) )
			{
				$_data = $dataToFormat;
			}
		}

		foreach ( $_data as $_item )
		{
			$_response[] = array(
				'id'      => $_item->id,
				'label'   => Option::get( $options, 'label', Option::get( $_item, 'api_name', 'A Resource' ) ),
				'inode'   => false,
				'my-hash' => BaseWebController::hashId( $_item->id ),
				'my-url'  => null,
			);
		}

		//	expected format
		return $_response;
	}
}