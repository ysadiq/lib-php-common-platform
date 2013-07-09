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
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Interfaces\RestServiceLike;
use Kisma\Core\Interfaces\HttpMethod;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;
use Swagger\Annotations as SWG;

/**
 * BasePlatformRestService
 * A base class for all DSP reset services
 *
 * Some basic models used in REST interfaces
 *
 * @SWG\Model(id="Resources",
 * @SWG\Property(name="resource",type="Array", items="$ref:Resource")
 * )
 * @SWG\Model(id="Resource",
 * @SWG\Property(name="name",type="string")
 * )
 * @SWG\Model(id="Success",
 * @SWG\Property(name="success",type="boolean")
 * )
 *
 */
abstract class BasePlatformRestService extends BasePlatformService implements RestServiceLike
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var string Full path coming from the URL of the REST call
	 */
	protected $_resourcePath = null;
	/**
	 * @var array Resource path broken into array by path divider ('/')
	 */
	protected $_resourceArray = null;
	/**
	 * @var string First piece of the resource path array
	 */
	protected $_resource = null;
	/**
	 * @var string REST verb to take action on
	 */
	protected $_action = self::Get;
	/**
	 * @var mixed The response to the request
	 */
	protected $_response = null;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * Apply the commonly used REST path members to the class
	 */
	protected function _detectResourceMembers()
	{
		$this->_resource = strtolower( Option::get( $this->_resourceArray, 0 ) );
	}

	/**
	 * @return void
	 */
	protected function _preProcess()
	{
		// throw exception here to stop processing
	}

	/**
	 * @return void
	 */
	protected function _postProcess()
	{
		//	Throw exception here to stop processing
	}

	/**
	 * @return bool
	 */
	protected function _handleResource()
	{
		return false;
	}

	/**
	 * List all possible resources accessible via this service,
	 * return false if this is not applicable
	 *
	 * @return array|boolean
	 */
	protected function _listResources()
	{
		return false;
	}

	/**
	 * @param string $resource
	 * @param string $action
	 *
	 * @return mixed
	 * @throws BadRequestException
	 */
	public function processRequest( $resource = null, $action = self::Get )
	{
		$this->_setResource( $resource );
		$this->_setAction( $action );
		$this->_detectResourceMembers();

		$this->_preProcess();

		//	Inherent failure?
		if ( false === ( $this->_response = $this->_handleResource() ) )
		{
			$_message = $this->_action . ' requests' .
						( !empty( $this->_resource ) ? ' for resource "' . $this->_resourcePath . '"' : ' without a resource' ) .
						' are not currently supported by the "' . $this->_apiName . '" service.';

			throw new BadRequestException( $_message );
		}

		$this->_postProcess();

		return $this->_response;
	}

	/**
	 * Adds criteria garnered from the query string from DataTables
	 *
	 * @param array|\CDbCriteria $criteria
	 * @param array              $columns
	 *
	 * @return array|\CDbCriteria
	 */
	protected function _buildCriteria( $columns, $criteria = null )
	{
		$criteria = $criteria ? : array();

		$_criteria = ( !( $criteria instanceof \CDbCriteria ) ? new \CDbCriteria( $criteria ) : $criteria );

		//	Columns
		$_criteria->select = ( !empty( $_columns ) ? implode( ', ', $_columns ) : array_keys( \Registry::model()->restMap() ) );

		//	Limits
		$_limit = FilterInput::get( INPUT_GET, 'iDisplayLength', -1, FILTER_SANITIZE_NUMBER_INT );
		$_limitStart = FilterInput::get( INPUT_GET, 'iDisplayStart', 0, FILTER_SANITIZE_NUMBER_INT );

		if ( -1 != $_limit )
		{
			$_criteria->limit = $_limit;
			$_criteria->offset = $_limitStart;
		}

		//	Sort
		$_order = array();

		if ( isset( $_GET['iSortCol_0'] ) )
		{
			for ( $_i = 0, $_count = FilterInput::get( INPUT_GET, 'iSortingCols', 0, FILTER_SANITIZE_NUMBER_INT ); $_i < $_count; $_i++ )
			{
				$_column = FilterInput::get( INPUT_GET, 'iSortCol_' . $_i, 0, FILTER_SANITIZE_NUMBER_INT );

				if ( isset( $_GET['bSortable_' . $_column] ) && 'true' == $_GET['bSortable_' . $_column] )
				{
					$_order[] = $columns[$_column] . ' ' . FilterInput::get( INPUT_GET, 'sSortDir_' . $_i, null, FILTER_SANITIZE_STRING );
				}
			}
		}

		if ( !empty( $_order ) )
		{
			$_criteria->order = implode( ', ', $_order );
		}

		return $_criteria;
	}

	/**
	 * @param mixed $response
	 *
	 * @return BasePlatformRestService
	 */
	public function setResponse( $response )
	{
		$this->_response = $response;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getResponse()
	{
		return $this->_response;
	}

	/**
	 * @param string $resourcePath
	 */
	protected function _setResource( $resourcePath = null )
	{
		$this->_resourcePath = $resourcePath;
		$this->_resourceArray = ( !empty( $this->_resourcePath ) ) ? explode( '/', $this->_resourcePath ) : array();
	}

	/**
	 * @param string $action
	 */
	protected function _setAction( $action = self::Get )
	{
		$this->_action = trim( strtoupper( $action ) );
	}

}
