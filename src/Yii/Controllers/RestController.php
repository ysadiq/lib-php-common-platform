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
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Utility\DataFormatter;
use DreamFactory\Platform\Utility\RestResponse;
use DreamFactory\Platform\Utility\ServiceHandler;
use DreamFactory\Platform\Yii\Models\Service;
use DreamFactory\Yii\Controllers\BaseFactoryController;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * RestController
 * REST API router and controller
 */
class RestController extends BaseFactoryController
{
	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var string service to direct call to
	 */
	protected $_service;
	/**
	 * @var string resource to be handled by service
	 */
	protected $_resource;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * All authorization handled by services
	 *
	 * @return array
	 */
	public function accessRules()
	{
		return array();
	}

	/**
	 * /rest/index
	 */
	public function actionIndex()
	{
		try
		{
			$_result = array( 'service' => Service::available( false, array( 'id', 'api_name' ) ) );
			$_outputFormat = RestResponse::detectResponseFormat( null, $_internal = null );
			$_result = DataFormatter::reformatData( $_result, null, $_outputFormat );

			RestResponse::sendResults( $_result, RestResponse::Ok, $_outputFormat );
		}
		catch ( \Exception $_ex )
		{
			RestResponse::sendErrors( $_ex );
		}
	}

	/**
	 * {@InheritDoc}
	 */
	public function actionGet()
	{
		$this->_handleAction( HttpMethod::Get );
	}

	/**
	 * {@InheritDoc}
	 */
	public function actionPost()
	{
		$_action = HttpMethod::Post;

		try
		{
			//	Check for verb tunneling
			$_tunnelMethod = FilterInput::server( 'HTTP_X_HTTP_METHOD', null, FILTER_SANITIZE_STRING );

			if ( empty( $_tunnelMethod ) )
			{
				$_tunnelMethod = FilterInput::request( 'method', null, FILTER_SANITIZE_STRING );
			}

			if ( !empty( $_tunnelMethod ) )
			{
				$_tunnelMethod = strtoupper( $_tunnelMethod );
				switch ( $_tunnelMethod )
				{
					case HttpMethod::Post:
					case HttpMethod::Get:
					case HttpMethod::Put:
					case HttpMethod::Merge:
					case HttpMethod::Patch:
					case HttpMethod::Delete:
						$_action = $_tunnelMethod;
						break;

					default:
						throw new BadRequestException( 'Unknown tunneling verb "' . $_tunnelMethod . '" in request.' );
				}
			}

			$this->_handleAction( $_action );
		}
		catch ( \Exception $ex )
		{
			RestResponse::sendErrors( $ex );
		}
	}

	/**
	 * {@InheritDoc}
	 */
	public function actionMerge()
	{
		$this->_handleAction( HttpMethod::Merge );
	}

	/**
	 * {@InheritDoc}
	 */
	public function actionPut()
	{
		$this->_handleAction( HttpMethod::Put );
	}

	/**
	 * {@InheritDoc}
	 */
	public function actionDelete()
	{
		$this->_handleAction( HttpMethod::Delete );
	}

	/**
	 * Generic action handler
	 *
	 * @param string $action
	 */
	protected function _handleAction( $action )
	{
		try
		{
			$svcObj = ServiceHandler::getService( $this->_service );
			$svcObj->processRequest( $this->_resource, $action );
		}
		catch ( \Exception $ex )
		{
			RestResponse::sendErrors( $ex );
		}
	}

	/**
	 * Override base method to do some processing of incoming requests
	 *
	 * @param \CAction $action
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function beforeAction( $action )
	{
		// fix the slash at the end, Yii removes trailing slash by default,
		// but it is needed in some APIs to determine file vs folder, etc.
		// 'rest/<service:[_0-9a-zA-Z-]+>/<resource:[_0-9a-zA-Z-\/. ]+>'
		$path = Option::get( $_GET, 'path', '' );
		$slashIndex = strpos( $path, '/' );
		if ( false === $slashIndex )
		{
			$this->_service = $path;
		}
		else
		{
			$this->_service = substr( $path, 0, $slashIndex );
			$this->_resource = substr( $path, $slashIndex + 1 );
			// fix removal of trailing slashes from resource
			if ( !empty( $this->_resource ) )
			{
				$requestUri = Yii::app()->request->requestUri;
				if ( ( false === strpos( $requestUri, '?' ) && '/' === substr( $requestUri, strlen( $requestUri ) - 1, 1 ) ) ||
					 ( '/' === substr( $requestUri, strpos( $requestUri, '?' ) - 1, 1 ) )
				)
				{
					$this->_resource .= '/';
				}
			}
		}

		return parent::beforeAction( $action );
	}

	/**
	 * @param string $resource
	 *
	 * @return RestController
	 */
	public function setResource( $resource )
	{
		$this->_resource = $resource;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getResource()
	{
		return $this->_resource;
	}

	/**
	 * @param string $service
	 *
	 * @return RestController
	 */
	public function setService( $service )
	{
		$this->_service = $service;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getService()
	{
		return $this->_service;
	}
}
