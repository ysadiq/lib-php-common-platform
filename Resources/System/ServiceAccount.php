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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Services\BasePlatformRestService;
use DreamFactory\Platform\Services\BasePlatformService;
use DreamFactory\Platform\Utility\ResourceStore;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Sql;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Common\Utility\DataFormat;
use DreamFactory\Platform\Utility\SqlDbUtilities;

/**
 * ServiceAccount
 * DSP service/provider interface
 *
 * @SWG\Resource(
 *   resourcePath="/system"
 * )
 *
 * @SWG\Model(id="ServiceAccounts",
 * @SWG\Property(name="record",type="Array",items="$ref:ServiceAccount",description="Array of system service account records of the given resource.")
 * )
 * @SWG\Model(id="ServiceAccount",
 * @SWG\Property(name="id",type="int",description="Identifier of this account."),
 * @SWG\Property(name="user_id",type="int",description="The user who owns this account."),
 * @SWG\Property(name="provider_id",type="int",description="The provider who issued this account."),
 * @SWG\Property(name="account_type",type="int",description="The type of account."),
 * @SWG\Property(name="auth_text",type="string",description="The authorization for this account/provider."),
 * @SWG\Property(name="created_date",type="string",description="Date this application group was created."),
 * @SWG\Property(name="created_by_id",type="int",description="User Id of who created this application group."),
 * @SWG\Property(name="last_modified_date",type="string",description="Date this application group was last modified."),
 * @SWG\Property(name="last_modified_by_id",type="int",description="User Id of who last modified this application group.")
 */
class ServiceAccount extends BaseSystemRestResource
{
	/**
	 * Constructor
	 *
	 * @param BasePlatformService $consumer
	 * @param array               $resourceArray
	 *
	 * @return \DreamFactory\Platform\Resources\System\ServiceAccount
	 */
	public function __construct( $consumer = null, $resourceArray = array() )
	{
		parent::__construct(
			$consumer,
			array(
				 'name'           => 'Service Account',
				 'type'           => 'Service',
				 'service_name'   => 'system',
				 'type_id'        => PlatformServiceTypes::LOCAL_WEB_SERVICE,
				 'api_name'       => 'service_account',
				 'description'    => 'Service provider account configuration.',
				 'is_active'      => true,
				 'resource_array' => $resourceArray,
				 'verb_aliases'   => array(
					 static::Patch => static::Post,
				 )
			)
		);
	}
	/**
	 *
	 * @SWG\Api(
	 *     path="/system/service_account", description="Operations for service account administration.",
	 * @SWG\Operations(
	 * @SWG\Operation(
	 *         httpMethod="GET", summary="Retrieve multiple service accounts.",
	 *         notes="Use the 'ids' or 'filter' parameter to limit records that are returned. Use the 'fields' and 'related' parameters to limit properties returned for each record. By default, all fields and no relations are returned for all records.",
	 *         responseClass="ServiceAccounts", nickname="getServiceAccounts",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="ids", description="Comma-delimited list of the identifiers of the records to retrieve.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="filter", description="SQL-like filter to limit the records to retrieve.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="limit", description="Set to limit the filter results.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="int"
	 *           ),
	 * @SWG\Parameter(
	 *             name="order", description="SQL-like order containing field and direction for filter results.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="offset", description="Set to offset the filter results to a particular record count.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="int"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="include_count", description="Include the total number of filter results.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="boolean"
	 *           ),
	 * @SWG\Parameter(
	 *             name="include_schema", description="Include the schema of the table queried.",
	 *             paramType="query", required="false", allowMultiple=false, dataType="boolean"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       ),
	 * @SWG\Operation(
	 *         httpMethod="POST", summary="Create one or more service accounts.",
	 *         notes="Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use 'fields' and 'related' to return more info.",
	 *         responseClass="Success", nickname="createServiceAccounts",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="record", description="Data containing name-value pairs of records to create.",
	 *             paramType="body", required="true", allowMultiple=false, dataType="ServiceAccounts"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       ),
	 * @SWG\Operation(
	 *         httpMethod="PUT", summary="Update one or more service accounts.",
	 *         notes="Post data should be a single record or an array of records (shown). By default, only the id property of the record is returned on success, use 'fields' and 'related' to return more info.",
	 *         responseClass="Success", nickname="updateServiceAccounts",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="record", description="Data containing name-value pairs of records to update.",
	 *             paramType="body", required="true", allowMultiple=false, dataType="ServiceAccounts"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       ),
	 * @SWG\Operation(
	 *         httpMethod="DELETE", summary="Delete one or more service accounts.",
	 *         notes="Use 'ids' or post data should be a single record or an array of records (shown) containing an id. By default, only the id property of the record is returned on success, use 'fields' and 'related' to return more info.",
	 *         responseClass="Success", nickname="deleteServiceAccounts",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="ids", description="Comma-delimited list of the identifiers of the records to retrieve.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="record", description="Data containing name-value pairs of records to delete.",
	 *             paramType="body", required="false", allowMultiple=false, dataType="ServiceAccounts"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       )
	 *     )
	 *   )
	 *
	 * @SWG\Api(
	 *     path="/system/service_account/{id}", description="Operations for individual service account administration.",
	 * @SWG\Operations(
	 * @SWG\Operation(
	 *         httpMethod="GET", summary="Retrieve one service account by identifier.",
	 *         notes="Use the 'fields' and/or 'related' parameter to limit properties that are returned. By default, all fields and no relations are returned.",
	 *         responseClass="ServiceAccount", nickname="getServiceAccount",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="id", description="Identifier of the record to retrieve.",
	 *             paramType="path", required="true", allowMultiple=false, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       ),
	 * @SWG\Operation(
	 *         httpMethod="PUT", summary="Update one service account.",
	 *         notes="Post data should be an array of fields for a single record. Use the 'fields' and/or 'related' parameter to return more properties. By default, the id is returned.",
	 *         responseClass="Success", nickname="updateServiceAccount",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="id", description="Identifier of the record to retrieve.",
	 *             paramType="path", required="true", allowMultiple=false, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="record", description="Data containing name-value pairs of records to update.",
	 *             paramType="body", required="true", allowMultiple=false, dataType="ServiceAccount"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       ),
	 * @SWG\Operation(
	 *         httpMethod="DELETE", summary="Delete one service account.",
	 *         notes="Use the 'fields' and/or 'related' parameter to return deleted properties. By default, the id is returned.",
	 *         responseClass="Success", nickname="deleteServiceAccount",
	 * @SWG\Parameters(
	 * @SWG\Parameter(
	 *             name="id", description="Identifier of the record to retrieve.",
	 *             paramType="path", required="true", allowMultiple=false, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="fields", description="Comma-delimited list of field names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           ),
	 * @SWG\Parameter(
	 *             name="related", description="Comma-delimited list of related names to retrieve for each record.",
	 *             paramType="query", required="false", allowMultiple=true, dataType="string"
	 *           )
	 *         ),
	 * @SWG\ErrorResponses(
	 * @SWG\ErrorResponse(code="400", reason="Bad Request - Request does not have a valid format, all required parameters, etc."),
	 * @SWG\ErrorResponse(code="401", reason="Unauthorized Access - No currently valid session available."),
	 * @SWG\ErrorResponse(code="500", reason="System Error - Specific reason is included in the error message.")
	 *         )
	 *       )
	 *     )
	 *   )
	 *
	 * @return array|bool
	 */
}
