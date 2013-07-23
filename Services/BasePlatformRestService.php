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
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Interfaces\RestServiceLike;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Platform\Yii\Models\BasePlatformSystemModel;
use Kisma\Core\Enums\HttpMethod;
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
	//* Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const ACTION_TOKEN = '{action}';
	/**
	 * @var string The default pattern of dispatch methods. Action token embedded.
	 */
	const DEFAULT_HANDLER_PATTERN = '_handle{action}';

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
	/**
	 * @var bool If true, _handleResource() dispatches a call to _handle[Action]() methods if defined. For example, a GET request would be dispatched to _handleGet().
	 */
	protected $_autoDispatch = true;
	/**
	 * @var string The pattern to search for dispatch methods. The string {action} will be replaced by the inbound action (i.e. Get, Put, Post, etc.)
	 */
	protected $_autoDispatchPattern = self::DEFAULT_HANDLER_PATTERN;
	/**
	 * @var bool|array Array of verb aliases. Has no effect if $autoDispatch !== true
	 *
	 * Example:
	 *
	 * $this->_verbAliases = array(
	 *     static::Put => static::Post,
	 *     static::Patch => static::Post,
	 *     static::Merge => static::Post,
	 *
	 *     // Use a closure too!
	 *     static::Get => function($resource){
	 *    ...
	 *   },
	 * );
	 *
	 *    The result will be that handleResource() will dispatch a PUT, PATCH, or MERGE request to the POST handler.
	 */
	protected $_verbAliases;
	/**
	 * @var string REST verb of original request. Set after verb change from $verbAliases map
	 */
	protected $_originalAction = null;
	/**
	 * @var int
	 */
	protected $_serviceId = null;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param array $settings
	 */
	public function __construct( $settings = array() )
	{
		$this->_serviceId = Option::get( $settings, 'id', null, true );

		parent::__construct( $settings );
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
	 * @param string $resourceName
	 *
	 * @return \DreamFactory\Platform\Resources\BasePlatformRestResource|BasePlatformSystemModel
	 */
	public static function getNewResource( $resourceName = null )
	{
		return ResourceStore::resource( $resourceName );
	}

	/**
	 * @param string $resourceName
	 *
	 * @return BasePlatformSystemModel
	 */
	public static function getNewModel( $resourceName = null )
	{
		return ResourceStore::model( $resourceName );
	}

	/**
	 * @throws \DreamFactory\Platform\Exceptions\BadRequestException
	 * @return bool
	 */
	protected function _handleResource()
	{
		if ( !HttpMethod::defines( $this->_action ) )
		{
			throw new BadRequestException( 'The action "' . $this->_action . '" is not supported.' );
		}

		//	Check verb aliases
		if ( true === $this->_autoDispatch && null !== ( $_alias = Option::get( $this->_verbAliases, $this->_action ) ) )
		{
			//	A closure?
			if ( is_callable( $_alias ) )
			{
				return call_user_func( $_alias );
			}

			//	Swap 'em and dispatch
			$this->_originalAction = $this->_action;
			$this->_action = $_alias;
		}

		//	If we have a dedicated handler method, call it
		$_method = str_ireplace( static::ACTION_TOKEN, $this->_action, $this->_autoDispatchPattern );

		if ( $this->_autoDispatch && method_exists( $this, $_method ) )
		{
			return call_user_func( array( $this, $_method ) );
		}

		//	Otherwise just return false
		return false;
	}

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
	protected function _handleGet()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _handleMerge()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _handleOptions()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _handleCopy()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _handleConnect()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _handlePost()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _handlePut()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _handlePatch()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	protected function _handleDelete()
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
	 * @param string $operation
	 * @param string $service
	 * @param string $resource
	 *
	 * @return bool
	 */
	public static function checkPermission( $operation, $service, $resource = null )
	{
		return ResourceStore::checkPermission( $operation, $service, $resource );
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
	 *
	 * @return BasePlatformRestService
	 */
	protected function _setResource( $resourcePath = null )
	{
		$this->_resourcePath = rtrim( $resourcePath, '/' );
		$this->_resourceArray = ( !empty( $this->_resourcePath ) ) ? explode( '/', $this->_resourcePath ) : array();

		return $this;
	}

	/**
	 * @param string $action
	 *
	 * @return BasePlatformRestService
	 */
	protected function _setAction( $action = self::Get )
	{
		$this->_action = trim( strtoupper( $action ) );

		return $this;
	}

	/**
	 * @return string
	 */
	public function getAction()
	{
		return $this->_action;
	}

	/**
	 * @return string
	 */
	public function getResource()
	{
		return $this->_resource;
	}

	/**
	 * @return array
	 */
	public function getResourceArray()
	{
		return $this->_resourceArray;
	}

	/**
	 * @return string
	 */
	public function getResourcePath()
	{
		return $this->_resourcePath;
	}

	/**
	 * @param boolean $autoDispatch
	 *
	 * @return BasePlatformRestService
	 */
	public function setAutoDispatch( $autoDispatch )
	{
		$this->_autoDispatch = $autoDispatch;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getAutoDispatch()
	{
		return $this->_autoDispatch;
	}

	/**
	 * @param string $autoDispatchPattern
	 *
	 * @return BasePlatformRestService
	 */
	public function setAutoDispatchPattern( $autoDispatchPattern )
	{
		$this->_autoDispatchPattern = $autoDispatchPattern;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getAutoDispatchPattern()
	{
		return $this->_autoDispatchPattern;
	}

	/**
	 * @return string
	 */
	public function getOriginalAction()
	{
		return $this->_originalAction;
	}

	/**
	 * @param array|bool $verbAliases
	 *
	 * @return BasePlatformRestService
	 */
	public function setVerbAliases( $verbAliases )
	{
		$this->_verbAliases = $verbAliases;

		return $this;
	}

	/**
	 * @param string $verb
	 * @param string $alias
	 *
	 * @return BasePlatformRestService
	 */
	public function setVerbAlias( $verb, $alias )
	{
		Option::set( $this->_verbAliases, $verb, $alias );

		return $this;
	}

	/**
	 * @param string $verb Clear one, or all if $verb === null, verb alias mappings
	 *
	 * @return $this
	 */
	public function clearVerbAlias( $verb = null )
	{
		if ( null === $verb || empty( $this->_verbAliases ) )
		{
			$this->_verbAliases = array();
		}
		else
		{
			unset( $this->_verbAliases[$verb] );
		}

		return $this;
	}

	/**
	 * @return array|bool
	 */
	public function getVerbAliases()
	{
		return $this->_verbAliases;
	}

	/**
	 * @return string The action actually requested
	 */
	public function getRequestedAction()
	{
		return $this->_originalAction ? : $this->_action;
	}

	/**
	 * @return int
	 */
	public function getServiceId()
	{
		return $this->_serviceId;
	}
}
