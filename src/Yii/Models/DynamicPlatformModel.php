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
namespace DreamFactory\Platform\Yii\Models;

/**
 * DynamicPlatformModel
 * A dynamic model
 */
class DynamicPlatformModel extends BasePlatformModel
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var string The name of the table
	 */
	protected static $_tableName;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param string $tableName
	 * @param string $scenario
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( $tableName, $scenario = 'insert' )
	{
		if ( empty( static::$_tableName ) && empty( $tableName ) )
		{
			throw new \InvalidArgumentException( 'The value for "$tableName" is invalid.' );
		}

		if ( empty( $tableName ) )
		{
			$scenario = null;
		}
		else
		{
			static::$_tableName = $tableName;
		}

		return parent::__construct( $scenario );
	}

	/**
	 * @param string $tableName
	 * @param string $class
	 *
	 * @throws \InvalidArgumentException
	 *
	 * @return DynamicPlatformModel|void
	 */
	public static function model( $tableName = null, $class = null )
	{
		$_tableName = $tableName ? : static::$_tableName;

		if ( empty( $_tableName ) )
		{
			throw new \InvalidArgumentException( 'No table name has been set or specified.' );
		}

		static::$_tableName = $_tableName;

		return $_model = parent::model( $class ? : get_called_class() );
	}

	/**
	 * @return string
	 */
	public function tableName()
	{
		return static::$_tableName;
	}

	/**
	 * @param $tableName
	 *
	 * @return string
	 */
	public static function setTableName( $tableName )
	{
		return static::$_tableName = $tableName;
	}

	/**
	 * @return string
	 */
	public function getTableName()
	{
		return static::$_tableName;
	}
}