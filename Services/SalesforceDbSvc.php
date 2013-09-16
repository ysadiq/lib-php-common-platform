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
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\NotImplementedException;
use DreamFactory\Platform\Services\BaseDbSvc;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;
use Phpforce\SoapClient as SoapClient;
use Guzzle\Http\Client as GuzzleClient;

/**
 * SalesforceDbSvc.php
 * A service to handle SQL database services accessed through the REST API.
 *
 */
class SalesforceDbSvc extends BaseDbSvc
{
	//*************************************************************************
	//	Constants
	//*************************************************************************

	/**
	 * Default record identifier field
	 */
	const DEFAULT_ID_FIELD = 'Id';

	//*************************************************************************
	//	Members
	//*************************************************************************

	/**
	 * @var SoapClient\Client
	 */
	protected $_soapClient;
	/**
	 * @var array
	 */
	protected $_fieldCache;

	//*************************************************************************
	//	Methods
	//*************************************************************************

	/**
	 * Create a new SalesforceDbSvc
	 *
	 * @param array $config
	 *
	 * @throws \InvalidArgumentException
	 * @return \DreamFactory\Platform\Services\SalesforceDbSvc
	 */
	public function __construct( $config )
	{
		if ( null === Option::get( $config, 'verb_aliases' ) )
		{
			//	Default verb aliases
			$config['verb_aliases'] = array(
				static::Patch => static::Put,
				static::Merge => static::Put,
			);
		}

		parent::__construct( $config );

		$this->_fieldCache = array();
		$this->_relatedCache = array();

		$_credentials = Option::get( $config, 'credentials' );

		if ( null === ( $user = Option::get( $_credentials, 'username' ) ) )
		{
			throw new \InvalidArgumentException( 'DB admin name can not be empty.' );
		}

		if ( null === ( $password = Option::get( $_credentials, 'password' ) ) )
		{
			throw new \InvalidArgumentException( 'DB admin password can not be empty.' );
		}

		if ( null === ( $token = Option::get( $_credentials, 'security_token' ) ) )
		{
			throw new \InvalidArgumentException( 'DB admin security token can not be empty.' );
		}

		$_scanPath = Pii::getParam( 'base_path' ) . '/vendor/dreamfactory/lib-php-common-platform/DreamFactory/Platform/Services/';

		$_builder = new SoapClient\ClientBuilder(
			$_scanPath . 'salesforce.enterprise.wsdl.xml',
			$user,
			$password,
			$token
		);

		$this->_soapClient = $_builder->build();
	}

	/**
	 * Perform call to Salesforce REST API
	 *
	 * @param string $method
	 * @param string $uri
	 * @param array  $parameters
	 * @param mixed  $body
	 *
	 * @internal param array $arguments
	 *
	 * @return array The JSON response as an array
	 */
	protected function callGuzzle( $method = 'GET', $uri = null, $parameters = array(), $body = null, $client = null )
	{
		$_options = array();
		if ( !isset( $client ) )
		{
			$client = $this->getGuzzleClient();
		}
		$request = $client->createRequest( $method, $uri, null, $body, $_options );
		$request->setHeader( 'Authorization', 'Bearer ' . $this->getLoginResult()->getSessionId() );
		if ( !empty( $body ) )
		{
			$request->setHeader( 'Content-Type', 'application/json' );
		}
		if ( !empty( $parameters ) )
		{
			$request->getQuery()->merge( $parameters );
		}

		$response = $request->send();

		return $response->json();
	}

	/**
	 * Get Guzzle client
	 *
	 * @param string $version
	 *
	 * @return \Guzzle\Http\Client
	 */
	protected function getGuzzleClient( $version = 'v28.0' )
	{
		return new GuzzleClient(
			sprintf(
				'https://%s.salesforce.com/services/data/%s/',
				$this->getLoginResult()->getServerInstance(),
				$version
			)
		);
	}

	/**
	 * Get login result from SOAP client
	 *
	 * @return SoapClient\Result\LoginResult
	 */
	protected function getLoginResult()
	{
		return $this->_soapClient->getLoginResult();
	}

	/**
	 * Object destructor
	 */
	public function __destruct()
	{
	}

	/**
	 * @throws \Exception
	 */
	protected function checkConnection()
	{
		if ( !isset( $this->_soapClient ) )
		{
			throw new InternalServerErrorException( 'Database client has not been initialized.' );
		}
	}

	/**
	 * @param null|array $post_data
	 *
	 * @return array
	 */
	protected function _gatherExtrasFromRequest( $post_data = null )
	{
		$_extras = parent::_gatherExtrasFromRequest( $post_data );

		// continue batch processing if an error occurs, if applicable
		$_continue = FilterInput::request( 'continue', false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( empty( $_continue ) && !empty( $post_data ) )
		{
			$_continue = Option::getBool( $post_data, 'continue' );
		}
		$_extras['continue'] = $_continue;

		return $_extras;
	}

	// REST service implementation

	/**
	 * @throws \Exception
	 * @return array
	 */
	protected function _getSObjectsArray()
	{
		try
		{
			$_result = $this->callGuzzle( 'GET', 'sobjects/' );

			return Option::get( $_result, 'sobjects' );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error describing database tables.\n{$ex->getMessage()}" );
		}
	}

	protected function _getAllFields( $table, $as_array = false )
	{
		try
		{
			$_result = $this->getTable( $table );
			$_result = Option::get( $_result, 'fields' );
			if ( empty( $_result ) )
			{
				return array();
			}

			$_fields = array();
			foreach ( $_result as $_field )
			{
				$_fields[] = Option::get( $_field, 'name' );
			}

			if ( $as_array )
			{
				return $_fields;
			}

			return implode( ',', $_fields );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Error describing database table '$table'.\n{$ex->getMessage()}" );
		}
	}

	/**
	 * @throws \Exception
	 * @return array
	 */
	protected function _listResources()
	{
		$_out = array();
		$_result = $this->_getSObjectsArray();
		foreach ( $_result as $_table )
		{
			$_out[] = array( 'name' => Option::get( $_table, 'name' ), 'label' => Option::get( $_table, 'label' ) );
		}

		return array( 'resource' => $_out );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTables( $tables = array() )
	{
		if ( empty( $tables ) )
		{
			return $this->_getSObjectsArray();
		}

		return parent::getTables( $tables );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTable( $table )
	{
		if ( is_array( $table ) )
		{
			$table = Option::get( $table, 'name' );
		}
		if ( empty( $table ) )
		{
			throw new BadRequestException( "No 'name' field in data." );
		}

		try
		{
			$result = $this->callGuzzle( 'GET', 'sobjects/' . $table . '/describe' );

			return $result;
		}
		catch ( \Exception $ex )
		{
			// todo better handling of Guzzle response exceptions
			throw new InternalServerErrorException( "Failed to get sobject info on Salesforce service.\n" . $ex->getMessage() );
		}
	}

	//-------- Table Records Operations ---------------------
	// records is an array of field arrays

	/**
	 * {@inheritdoc}
	 */
	public function createRecords( $table, $records, $fields = null, $extras = array() )
	{
		if ( empty( $records ) || !is_array( $records ) )
		{
			throw new BadRequestException( 'There are no record sets in the request.' );
		}
		if ( !isset( $records[0] ) )
		{
			// single record possibly passed in without wrapper array
			$records = array( $records );
		}

		$_client = $this->getGuzzleClient();
		$_isSingle = ( 1 == count( $records ) );
		$_continue = Option::getBool( $extras, 'continue', false );
		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$_idField = static::DEFAULT_ID_FIELD;
		}

		try
		{
			$_ids = array();
			$_errors = array();

			foreach ( $records as $_key => $_record )
			{
				try
				{
					$_result = $this->callGuzzle( 'POST', 'sobjects/' . $table . '/', null, json_encode( $_record ), $_client );
					if ( !Option::getBool( $_result, 'success', false ) )
					{
						$_msg = json_encode( Option::get( $_result, 'errors' ) );
						throw new InternalServerErrorException( "Record insert failed for table '$table'.\n" . $_msg );
					}
					$_ids[$_key] = Option::get( $_result, 'id' );
				}
				catch ( \Exception $ex )
				{
					if ( $_isSingle )
					{
						throw $ex;
					}

					$_errors[$_key] = $ex->getMessage();
					if ( !$_continue )
					{
						break;
					}
				}
			}

			if ( !empty( $_errors ) )
			{
				$_msg = array( 'errors' => $_errors, 'ids' => $_ids );
				throw new BadRequestException( "Batch Error: " . json_encode( $_msg ) );
			}

			$_results = array();
			if ( !static::_requireMoreFields( $fields, $_idField ) )
			{
				foreach ( $_ids as $_id )
				{
					$_results[] = array( $_idField => $_id );
				}
			}
			else
			{
				$_results = $this->retrieveRecordsByIds( $table, $_ids, $fields, $extras );
			}

			return $_results;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateRecords( $table, $records, $fields = null, $extras = array() )
	{
		if ( empty( $records ) || !is_array( $records ) )
		{
			throw new BadRequestException( 'There are no record sets in the request.' );
		}
		if ( !isset( $records[0] ) )
		{
			// single record possibly passed in without wrapper array
			$records = array( $records );
		}

		$_client = $this->getGuzzleClient();
		$_isSingle = ( 1 == count( $records ) );
		$_continue = Option::getBool( $extras, 'continue', false );
		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$_idField = static::DEFAULT_ID_FIELD;
		}

		try
		{
			$_ids = array();
			$_errors = array();

			foreach ( $records as $_key => $_record )
			{
				try
				{
					$_id = Option::get( $_record, $_idField );
					if ( empty( $_id ) )
					{
						throw new BadRequestException( "Identifying field '$_idField' can not be empty for update record [$_key] request." );
					}

					$_record = Utilities::removeOneFromArray( $_idField, $_record );

					$_result = $this->callGuzzle( 'PATCH', 'sobjects/' . $table . '/' . $_id, null, json_encode( $_record ), $_client );
					if ( !Option::getBool( $_result, 'success', false ) )
					{
						$msg = Option::get( $_result, 'errors' );
						throw new InternalServerErrorException( "Record update failed for table '$table'.\n" . $msg );
					}
					$_ids[$_key] = $_id;
				}
				catch ( \Exception $ex )
				{
					if ( $_isSingle )
					{
						throw $ex;
					}

					$_errors[$_key] = $ex->getMessage();
					if ( !$_continue )
					{
						break;
					}
				}
			}

			if ( !empty( $_errors ) )
			{
				$_msg = array( 'errors' => $_errors, 'ids' => $_ids );
				throw new BadRequestException( "Batch Error: " . json_encode( $_msg ) );
			}

			$_results = array();
			if ( !static::_requireMoreFields( $fields, $_idField ) )
			{
				foreach ( $_ids as $_id )
				{
					$_results[] = array( $_idField => $_id );
				}
			}
			else
			{
				$_results = $this->retrieveRecordsByIds( $table, $_ids, $fields, $extras );
			}

			return $_results;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateRecordsByFilter( $table, $record, $filter = null, $fields = null, $extras = array() )
	{
		if ( !is_array( $record ) || empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record.' );
		}

		if ( empty( $filter ) )
		{
			throw new BadRequestException( "Filter for delete request can not be empty." );
		}

		$_records = $this->retrieveRecordsByFilter( $table, $filter, null, $extras );

		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$_idField = static::DEFAULT_ID_FIELD;
		}
		$_ids = array();

		foreach ( $_records as $_key => $_record )
		{
			$_id = Option::get( $_record, $_idField );
			if ( empty( $_id ) )
			{
				throw new BadRequestException( "Identifying field '$_idField' can not be empty for update record [$_key] request." );
			}

			$_ids[$_key] = $_id;
		}

		return $this->updateRecordsByIds( $table, $record, $_ids, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateRecordsByIds( $table, $record, $ids, $fields = null, $extras = array() )
	{
		if ( !is_array( $record ) || empty( $record ) )
		{
			throw new BadRequestException( "No record fields were passed in the request." );
		}

		$_client = $this->getGuzzleClient();
		$_continue = Option::getBool( $extras, 'continue', false );
		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$_idField = static::DEFAULT_ID_FIELD;
		}

		if ( !is_array( $ids ) )
		{
			$ids = array_map( 'trim', explode( ',', trim( $ids, ',' ) ) );
		}
		if ( empty( $ids ) )
		{
			throw new BadRequestException( "Identifying values for '$_idField' can not be empty for update request." );
		}

		$_isSingle = ( 1 == count( $ids ) );

		try
		{
			$record = Utilities::removeOneFromArray( $_idField, $record );

			$_errors = array();

			foreach ( $ids as $_key => $_id )
			{
				try
				{
					if ( empty( $_id ) )
					{
						throw new BadRequestException( "Identifying field '$_idField' can not be empty for update record [$_key] request." );
					}

					$_result = $this->callGuzzle( 'PATCH', 'sobjects/' . $table . '/' . $_id, null, json_encode( $record ), $_client );
					if ( !Option::getBool( $_result, 'success', false ) )
					{
						$msg = Option::get( $_result, 'errors' );
						throw new InternalServerErrorException( "Record update failed for table '$table'.\n" . $msg );
					}
				}
				catch ( \Exception $ex )
				{
					if ( $_isSingle )
					{
						throw $ex;
					}

					$_errors[$_key] = $ex->getMessage();
					if ( !$_continue )
					{
						break;
					}
				}
			}

			if ( !empty( $_errors ) )
			{
				$_msg = array( 'errors' => $_errors, 'ids' => $ids );
				throw new BadRequestException( "Batch Error: " . json_encode( $_msg ) );
			}

			$_results = array();
			if ( !static::_requireMoreFields( $fields, $_idField ) )
			{
				foreach ( $ids as $_id )
				{
					$_results[] = array( $_idField => $_id );
				}
			}
			else
			{
				$_results = $this->retrieveRecordsByIds( $table, $ids, $fields, $extras );
				}

			return $_results;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function mergeRecords( $table, $records, $fields = null, $extras = array() )
	{
		// currently the same as update here
		return $this->updateRecords( $table, $records, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function mergeRecordsByFilter( $table, $record, $filter = null, $fields = null, $extras = array() )
	{
		// currently the same as update here
		return $this->updateRecordsByFilter( $table, $record, $filter, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function mergeRecordsByIds( $table, $record, $ids, $fields = null, $extras = array() )
	{
		// currently the same as update here
		return $this->updateRecordsByIds( $table, $record, $ids, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteRecords( $table, $records, $fields = null, $extras = array() )
	{
		if ( !is_array( $records ) || empty( $records ) )
		{
			throw new BadRequestException( 'There are no record sets in the request.' );
		}
		if ( !isset( $records[0] ) )
		{
			// single record possibly passed in without wrapper array
			$records = array( $records );
		}

		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$_idField = static::DEFAULT_ID_FIELD;
		}

		$_ids = array();
		foreach ( $records as $_key => $_record )
		{
			$_id = Option::get( $_record, $_idField );
			if ( empty( $_id ) )
			{
				throw new BadRequestException( "Identifying field '$_idField' can not be empty for delete record [$_key] request." );
			}
			$_ids[$_key] = $_id;
		}

		return $this->deleteRecordsByIds( $table, $_ids, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteRecordsByFilter( $table, $filter, $fields = null, $extras = array() )
	{
		if ( empty( $filter ) )
		{
			throw new BadRequestException( "Filter for delete request can not be empty." );
		}

		$_records = $this->retrieveRecordsByFilter( $table, $filter, null, $extras );

		return $this->deleteRecords( $table, $_records, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteRecordsByIds( $table, $ids, $fields = null, $extras = array() )
	{
		$_client = $this->getGuzzleClient();
		$_continue = Option::getBool( $extras, 'continue', false );
		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$_idField = static::DEFAULT_ID_FIELD;
		}

		if ( !is_array( $ids ) )
		{
			$ids = array_map( 'trim', explode( ',', trim( $ids, ',' ) ) );
		}
		if ( empty( $ids ) )
		{
			throw new BadRequestException( "Identifying values for '$_idField' can not be empty for delete request." );
		}

		$_isSingle = ( 1 == count( $ids ) );

		try
		{
			$_errors = array();

			// get the returnable fields first, then issue delete
			$_outResults = array();
			if ( static::_requireMoreFields( $fields, $_idField ) )
			{
				$_outResults = $this->retrieveRecordsByIds( $table, $ids, $fields, $extras );
			}

			foreach ( $ids as $_key => $_id )
			{
				try
				{
					if ( empty( $_id ) )
					{
						throw new BadRequestException( "Identifying field '$_idField' can not be empty for delete record [$_key] request." );
					}

					$_result = $this->callGuzzle( 'DELETE', 'sobjects/' . $table . '/' . $_id, null, null, $_client );
					if ( !Option::getBool( $_result, 'success', false ) )
					{
						$msg = Option::get( $_result, 'errors' );
						throw new InternalServerErrorException( "Record delete failed for table '$table'.\n" . $msg );
					}
				}
				catch ( \Exception $ex )
				{
					if ( $_isSingle )
					{
						throw $ex;
					}

					$_errors[$_key] = $ex->getMessage();
					if ( !$_continue )
					{
						break;
					}
				}
			}

			if ( !empty( $_errors ) )
			{
				$_msg = array( 'errors' => $_errors, 'ids' => $ids );
				throw new BadRequestException( "Batch Error: " . json_encode( $_msg ) );
			}

			$_results = array();
			if ( !static::_requireMoreFields( $fields, $_idField ) )
			{
				foreach ( $ids as $_id )
				{
					$_results[] = array( $_idField => $_id );
				}
			}
			else
			{
				$_results = $_outResults;
			}

			return $_results;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function retrieveRecordsByFilter( $table, $filter = null, $fields = null, $extras = array() )
	{
		// build query string
		if ( empty( $fields ) )
		{
			throw new BadRequestException( 'There are no fields specified in the request.' );
		}
		elseif ( '*' == $fields )
		{
			$fields = $this->_getAllFields( $table );
		}
		elseif ( is_array( $fields ) )
		{
			$fields = implode( ',', $fields );
		}

		$_query = 'SELECT ' . $fields . ' FROM ' . $table;

		if ( !empty( $filter ) )
		{
			$_query .= ' WHERE ' . $filter;
		}

		$_order = Option::get( $extras, 'order' );
		if ( !empty( $_order ) )
		{
			$_query .= ' ORDER BY ' . $_order;
		}

		$_offset = intval( Option::get( $extras, 'offset', 0 ) );
		if ( $_offset > 0 )
		{
			$_query .= ' OFFSET ' . $_offset;
		}

		$_limit = intval( Option::get( $extras, 'limit', 0 ) );
		if ( $_limit > 0 )
		{
			$_query .= ' LIMIT ' . $_limit;
		}

		$this->checkConnection();

		try
		{
			$_result = $this->callGuzzle( 'GET', 'query', array( 'q' => $_query ) );

			$_data = Option::get( $_result, 'records', array() );

			$_includeCount = Option::getBool( $extras, 'include_count', false );
			$_moreToken = Option::get( $_result, 'nextRecordsUrl' );
			if ( $_includeCount || $_moreToken )
			{
				// count total records
				$_data['meta']['count'] = intval( Option::get( $_result, 'totalSize' ) );
				if ( $_moreToken )
				{
					$_data['meta']['next'] = substr( $_moreToken, strrpos( $_moreToken, '/' ) + 1 );
				}
			}

			return $_data;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function retrieveRecords( $table, $records, $fields = null, $extras = array() )
	{
		if ( empty( $records ) || !is_array( $records ) )
		{
			throw new BadRequestException( 'There are no record sets in the request.' );
		}
		if ( !isset( $records[0] ) )
		{
			// single record
			$records = array( $records );
		}

		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$_idField = static::DEFAULT_ID_FIELD;
		}

		$_ids = array();
		foreach ( $records as $_key => $_record )
		{
			$_id = Option::get( $_record, $_idField );
			if ( empty( $_id ) )
			{
				throw new BadRequestException( "Identifying field '$_idField' can not be empty for retrieve record [$_key] request." );
			}
			$_ids[] = $_id;
		}

		return $this->retrieveRecordsByIds( $table, $_ids, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function retrieveRecord( $table, $record, $fields = null, $extras = array() )
	{
		if ( !is_array( $record ) || empty( $record ) )
		{
			throw new BadRequestException( 'There are no fields in the record.' );
		}

		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$_idField = static::DEFAULT_ID_FIELD;
		}

		$_id = Option::get( $record, $_idField );
		if ( empty( $_id ) )
		{
			throw new BadRequestException( "Identifying field '$_idField' can not be empty for retrieve record request." );
		}

		return $this->retrieveRecordById( $table, $_id, $fields, $extras );
	}

	/**
	 * {@inheritdoc}
	 */
	public function retrieveRecordsByIds( $table, $ids, $fields = null, $extras = array() )
	{
		if ( empty( $ids ) )
		{
			return array();
		}

		if ( !is_array( $ids ) )
		{
			$ids = array_map( 'trim', explode( ',', $ids ) );
		}

		$_idField = Option::get( $extras, 'id_field' );
		if ( empty( $_idField ) )
		{
			$_idField = static::DEFAULT_ID_FIELD;
		}

		if ( empty( $fields ) )
		{
			throw new BadRequestException( 'There are no fields specified in the request.' );
		}
		elseif ( '*' == $fields )
		{
			$fields = $this->_getAllFields( $table );
		}
		elseif ( is_array( $fields ) )
		{
			$fields = implode( ',', $fields );
		}

		if ( !is_array( $ids ) )
		{
			$ids = explode( ',', $ids );
		}

		$ids = "('" . implode( "','", $ids ) . "')";

		$this->checkConnection();
		try
		{
			$_query = 'SELECT ' . $fields . ' FROM ' . $table . ' WHERE ' . $_idField . ' IN ' .$ids;
			$_result = $this->callGuzzle( 'GET', 'query', array( 'q' => $_query ) );

			$_data = Option::get( $_result, 'records', array() );

			return $_data;
		}
		catch ( \Exception $ex )
		{
			throw $ex;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function retrieveRecordById( $table, $id, $fields = null, $extras = array() )
	{
		if ( empty( $id ) )
		{
			return array();
		}

		if ( empty( $fields ) )
		{
			throw new BadRequestException( 'There are no fields specified in the request.' );
		}
		elseif ( '*' == $fields )
		{
			$fields = $this->_getAllFields( $table );
		}
		elseif ( is_array( $fields ) )
		{
			$fields = implode( ',', $fields );
		}

		$this->checkConnection();
		try
		{
			$_result = $this->callGuzzle( 'GET', 'sobjects/' . $table . '/' . $id, array( 'fields' => $fields ) );
		}
		catch ( \Exception $ex )
		{
			throw new InternalServerErrorException( "Failed to get item '$table/$id' on Salesforce service.\n" . $ex->getMessage() );
		}

		if ( empty( $_result ) )
		{
			throw new NotFoundException( "Record with id '$id' was not found." );
		}

		return $_result;
	}

	// Handle administrative options, table add, delete, etc

	/**
	 * Create one or more tables by array of table properties
	 *
	 * @param array $tables
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function createTables( $tables = array() )
	{
		throw new NotImplementedException( "Metadata actions currently not supported." );
	}

	/**
	 * Create a single table by name, additional properties
	 *
	 * @param array $properties
	 *
	 * @throws \Exception
	 */
	public function createTable( $properties = array() )
	{
		throw new NotImplementedException( "Metadata actions currently not supported." );
	}

	/**
	 * Update properties related to the table
	 *
	 * @param array $tables
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function updateTables( $tables = array() )
	{
		throw new NotImplementedException( "Metadata actions currently not supported." );
	}

	/**
	 * Update properties related to the table
	 *
	 * @param array $properties
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function updateTable( $properties = array() )
	{
		throw new NotImplementedException( "Metadata actions currently not supported." );
	}

	/**
	 * Delete multiple tables and all of their contents
	 *
	 * @param array $tables
	 * @param bool  $check_empty
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function deleteTables( $tables = array(), $check_empty = false )
	{
		throw new NotImplementedException( "Metadata actions currently not supported." );
	}

	/**
	 * Delete the table and all of its contents
	 *
	 * @param string $table
	 * @param bool   $check_empty
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function deleteTable( $table, $check_empty = false )
	{
		throw new NotImplementedException( "Metadata actions currently not supported." );
	}

}
