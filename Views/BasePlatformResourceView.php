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
namespace DreamFactory\Platform\Views;

use DreamFactory\Platform\Enums\ResponseFormats;
use DreamFactory\Platform\Interfaces\ResourceViewLike;
use DreamFactory\Platform\Resources\BasePlatformRestResource;
use DreamFactory\Platform\Services\BasePlatformRestService;
use Kisma\Core\Exceptions\NotImplementedException;
use Kisma\Core\Seed;

/**
 * BasePlatformResourceView
 * A base resource view class
 */
abstract class BasePlatformResourceView extends Seed implements ResourceViewLike
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var BasePlatformRestResource
	 */
	protected $_resource;
	/**
	 * @var array The view structure
	 */
	protected $_schema;
	/**
	 * @var int The way to format the schema
	 */
	protected $_responseFormat = ResponseFormats::JTABLE;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * Create a new service
	 *
	 * @param BasePlatformRestResource $resource
	 * @param array                    $settings configuration array
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( $resource, $settings = array() )
	{
		$this->_resource = $resource;
		parent::__construct( $settings );

		if ( empty( $this->_resource ) )
		{
			throw new \InvalidArgumentException( 'The resource specified is invalid.' );
		}

		$this->_generateSchema();
	}

	/**
	 * @return bool|string
	 */
	protected function _generateSchema()
	{
		switch ( $this->_responseFormat )
		{
			case ResponseFormats::DATATABLES:
				return $this->_generateDataTablesSchema();

			case ResponseFormats::JTABLE:
				return $this->_generateJTableSchema();

			default:
				return false;
		}
	}

	/**
	 * Generates a json schema for jTable
	 *
	 * @throws NotImplementedException
	 * @return string
	 */
	protected function _generateJTableSchema()
	{
		throw new NotImplementedException();
	}

	/**
	 * Generates a json schema for datatables
	 *
	 * @throws NotImplementedException
	 * @return string
	 */
	protected function _generateDataTablesSchema()
	{
		throw new NotImplementedException();
	}

	/**
	 * @param \DreamFactory\Platform\Resources\BasePlatformRestResource $resource
	 *
	 * @return BasePlatformResourceView
	 */
	public function setResource( $resource )
	{
		$this->_resource = $resource;

		return $this;
	}

	/**
	 * @return \DreamFactory\Platform\Resources\BasePlatformRestResource
	 */
	public function getResource()
	{
		return $this->_resource;
	}

	/**
	 * @param array $schema
	 *
	 * @return BasePlatformResourceView
	 */
	public function setSchema( $schema )
	{
		$this->_schema = $schema;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getSchema()
	{
		return $this->_schema;
	}

	/**
	 * @param int $responseFormat
	 *
	 * @return BasePlatformResourceView
	 */
	public function setResponseFormat( $responseFormat )
	{
		$this->_responseFormat = $responseFormat;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getResponseFormat()
	{
		return $this->_responseFormat;
	}
}
