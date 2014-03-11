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
use DreamFactory\Yii\Controllers\BaseWebController;
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
		$_startId = Option::get( $options, 'start_id', 2 );
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
				'id-hash' => BaseWebController::hashId( $_item->id ),
				'label'   => Option::get( $options, 'label', Option::get( $_item, 'api_name', 'A Resource' ) ),
				'inode'   => false,
			);
		}

		//	expected format
		return $_response;
	}

	/**
	 * Compiles swagger JSON files into event map format
	 */
	protected function _compileSwagger( $startId = 2 )
	{
		$_id = $startId ? : 2;
		$_swaggerPath = Platform::getSwaggerPath();
		$_masterPath = $_swaggerPath . '/_.json';
		$_compiled = $_swaggerPath . '/.event-map.json';

		if ( !file_exists( $_masterPath ) )
		{
			return false;
		}

		if ( false === ( $_master = json_decode( file_get_contents( $_masterPath ) ) ) )
		{
			return false;
		}

		$_apis = array();

		foreach ( $_master->apis as $_api )
		{
			$_label = trim( $_api->path, '/' );

			$_apis[] = array(
				'id'     => $_id++,
				'label'  => $_label,
				'inode'  => true,
				'open'   => false,
				'branch' => $this->_loadSwaggerFile( $_label, $_swaggerPath . '/' . $_label . '.json', $_id ),
			);
		}

		$_result = array(
			'id'     => 2,
			'label'  => 'platform',
			'inode'  => true,
			'open'   => true,
			'branch' => $_apis,
		);

		return file_put_contents( $_compiled, json_encode( array( $_result ), JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT ) );
	}

}