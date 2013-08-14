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

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );

$_base['apis'] = array(
		0 =>
		array(
			'path'        => '/{api_name}',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'List all containers.',
					'nickname'       => 'getContainers',
					'responseClass'  => 'Containers',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'include_properties',
							'description'   => 'Return all properties of the container, if any.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'List the names of the available containers in this storage. Use \'include_properties\' to include any properties of the containers.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Create one or more containers.',
					'nickname'       => 'createContainers',
					'responseClass'  => 'Containers',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'data',
							'description'   => 'Array of containers to create.',
							'allowMultiple' => false,
							'dataType'      => 'Containers',
							'paramType'     => 'body',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'check_exist',
							'description'   => 'If true, the request fails when the container to create already exists.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single container definition or an array of container definitions.',
				),
				2 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one or more containers.',
					'nickname'       => 'deleteContainers',
					'responseClass'  => 'Containers',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'data',
							'description'   => 'Array of containers to delete.',
							'allowMultiple' => false,
							'dataType'      => 'Containers',
							'paramType'     => 'body',
							'required'      => true,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be a single container definition or an array of container definitions.',
				),
			),
			'description' => 'Operations available for File Storage Service.',
		),
		1 =>
		array(
			'path'        => '/{api_name}/{container}/',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'List the container\'s properties, including folders and files.',
					'nickname'       => 'getContainer',
					'responseClass'  => 'FoldersAndFiles',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'The name of the container you want to retrieve the contents.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'include_properties',
							'description'   => 'Return all properties of the container, if any.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						2 =>
						array(
							'name'          => 'include_folders',
							'description'   => 'Include folders in the returned listing.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => true,
						),
						3 =>
						array(
							'name'          => 'include_files',
							'description'   => 'Include files in the returned listing.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => true,
						),
						4 =>
						array(
							'name'          => 'full_tree',
							'description'   => 'List the contents of all sub-folders as well.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						5 =>
						array(
							'name'          => 'zip',
							'description'   => 'Return the zipped content of the folder.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use \'include_properties\' to get properties of the container. Use the \'include_folders\' and/or \'include_files\' to return a listing.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Add folders and/or files to the container.',
					'nickname'       => 'createContainer',
					'responseClass'  => 'FoldersAndFiles',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'The name of the container you want to put the contents.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'url',
							'description'   => 'The full URL of the file to upload.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						2 =>
						array(
							'name'          => 'extract',
							'description'   => 'Extract an uploaded zip file into the container.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						3 =>
						array(
							'name'          => 'clean',
							'description'   => 'Option when \'extract\' is true, clean the current folder before extracting files and folders.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						4 =>
						array(
							'name'          => 'check_exist',
							'description'   => 'If true, the request fails when the file or folder to create already exists.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						5 =>
						array(
							'name'          => 'data',
							'description'   => 'Array of folders and/or files.',
							'allowMultiple' => false,
							'dataType'      => 'FoldersAndFiles',
							'paramType'     => 'body',
							'required'      => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data as an array of folders and/or files.',
				),
				2 =>
				array(
					'httpMethod'     => 'PATCH',
					'summary'        => 'Update properties of the container.',
					'nickname'       => 'updateContainerProperties',
					'responseClass'  => 'Container',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'The name of the container you want to put the contents.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'data',
							'description'   => 'An array of container properties.',
							'allowMultiple' => false,
							'dataType'      => 'Container',
							'paramType'     => 'body',
							'required'      => true,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data as an array of container properties.',
				),
				3 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete the container or folders and/or files from the container.',
					'nickname'       => 'deleteContainer',
					'responseClass'  => 'FoldersAndFiles',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'The name of the container you want to delete from.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'data',
							'description'   => 'An array of folders and/or files to delete from the container.',
							'allowMultiple' => false,
							'dataType'      => 'FoldersAndFiles',
							'paramType'     => 'body',
							'required'      => true,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Careful, this deletes the requested container and all of its contents, unless there are posted specific folders and/or files.',
				),
			),
			'description' => 'Operations on containers.',
		),
		2 =>
		array(
			'path'        => '/{api_name}/{container}/{file_path}',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'Download the file contents and/or its properties.',
					'nickname'       => 'getFile',
					'responseClass'  => 'File',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'Name of the container where the file exists.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'file_path',
							'description'   => 'Path and name of the file to retrieve.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'include_properties',
							'description'   => 'Return properties of the file.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						3 =>
						array(
							'name'          => 'content',
							'description'   => 'Return the content as base64 of the file, only applies when \'include_properties\' is true.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						4 =>
						array(
							'name'          => 'download',
							'description'   => 'Prompt the user to download the file from the browser.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container, folder, or file does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'By default, the file is streamed to the browser. Use the \'download\' parameter to prompt for download.
             Use the \'include_properties\' parameter (optionally add \'content\' to include base64 content) to list properties of the file.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Create a new file.',
					'nickname'       => 'createFile',
					'responseClass'  => 'File',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'Name of the container where the file exists.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'file_path',
							'description'   => 'Path and name of the file to create.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'check_exist',
							'description'   => 'If true, the request fails when the file to create already exists.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'properties',
							'description'   => 'Properties of the file.',
							'allowMultiple' => false,
							'dataType'      => 'File',
							'paramType'     => 'body',
							'required'      => false,
						),
						4 =>
						array(
							'name'          => 'content',
							'description'   => 'The content of the file.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'body',
							'required'      => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container or folder does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be the contents of the file or an object with file properties.',
				),
				2 =>
				array(
					'httpMethod'     => 'PUT',
					'summary'        => 'Update content of the file.',
					'nickname'       => 'updateFile',
					'responseClass'  => 'File',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'Name of the container where the file exists.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'file_path',
							'description'   => 'Path and name of the file to update.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'content',
							'description'   => 'The content of the file.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'body',
							'required'      => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container, folder, or file does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be the contents of the file.',
				),
				3 =>
				array(
					'httpMethod'     => 'PATCH',
					'summary'        => 'Update properties of the file.',
					'nickname'       => 'updateFileProperties',
					'responseClass'  => 'File',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'Name of the container where the file exists.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'file_path',
							'description'   => 'Path and name of the file to update.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'properties',
							'description'   => 'Properties of the file.',
							'allowMultiple' => false,
							'dataType'      => 'File',
							'paramType'     => 'body',
							'required'      => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container, folder, or file does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data should be the file properties.',
				),
				4 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete the file.',
					'nickname'       => 'deleteFile',
					'responseClass'  => 'File',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'Name of the container where the file exists.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'file_path',
							'description'   => 'Path and name of the file to delete.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container, folder, or file does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Careful, this removes the given file from the storage.',
				),
			),
			'description' => 'Operations on individual files.',
		),
		3 =>
		array(
			'path'        => '/{api_name}/{container}/{folder_path}/',
			'operations'  =>
			array(
				0 =>
				array(
					'httpMethod'     => 'GET',
					'summary'        => 'List the folder\'s properties, or sub-folders and files.',
					'nickname'       => 'getFolder',
					'responseClass'  => 'FoldersAndFiles',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'The name of the container from which you want to retrieve contents.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'folder_path',
							'description'   => 'The path of the folder you want to retrieve. This can be a sub-folder, with each level separated by a \'/\'',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'include_properties',
							'description'   => 'Return all properties of the folder, if any.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						3 =>
						array(
							'name'          => 'include_folders',
							'description'   => 'Include folders in the returned listing.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => true,
						),
						4 =>
						array(
							'name'          => 'include_files',
							'description'   => 'Include files in the returned listing.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => true,
						),
						5 =>
						array(
							'name'          => 'full_tree',
							'description'   => 'List the contents of all sub-folders as well.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						6 =>
						array(
							'name'          => 'zip',
							'description'   => 'Return the zipped content of the folder.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container or folder does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Use with no parameters to get properties of the folder or use the \'include_folders\' and/or \'include_files\' to return a listing.',
				),
				1 =>
				array(
					'httpMethod'     => 'POST',
					'summary'        => 'Create one or more sub-folders and/or files.',
					'nickname'       => 'createFolder',
					'responseClass'  => 'FoldersAndFiles',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'The name of the container where you want to put the contents.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'folder_path',
							'description'   => 'The path of the folder where you want to put the contents. This can be a sub-folder, with each level separated by a \'/\'',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'url',
							'description'   => 'The full URL of the file to upload.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'query',
							'required'      => false,
						),
						3 =>
						array(
							'name'          => 'extract',
							'description'   => 'Extract an uploaded zip file into the folder.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						4 =>
						array(
							'name'          => 'clean',
							'description'   => 'Option when \'extract\' is true, clean the current folder before extracting files and folders.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						5 =>
						array(
							'name'          => 'check_exist',
							'description'   => 'If true, the request fails when the file or folder to create already exists.',
							'allowMultiple' => false,
							'dataType'      => 'boolean',
							'paramType'     => 'query',
							'required'      => false,
							'defaultValue'  => false,
						),
						6 =>
						array(
							'name'          => 'data',
							'description'   => 'Array of folders and/or files.',
							'allowMultiple' => false,
							'dataType'      => 'FoldersAndFiles',
							'paramType'     => 'body',
							'required'      => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data as an array of folders and/or files. Folders are created if they do not exist',
				),
				2 =>
				array(
					'httpMethod'     => 'PATCH',
					'summary'        => 'Update folder properties.',
					'nickname'       => 'updateFolderProperties',
					'responseClass'  => 'Folder',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'The name of the container where you want to put the contents.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'folder_path',
							'description'   => 'The path of the folder you want to update. This can be a sub-folder, with each level separated by a \'/\'',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'data',
							'description'   => 'Array of folder properties.',
							'allowMultiple' => false,
							'dataType'      => 'FoldersAndFiles',
							'paramType'     => 'body',
							'required'      => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container or folder does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Post data as an array of folder properties.',
				),
				3 =>
				array(
					'httpMethod'     => 'DELETE',
					'summary'        => 'Delete one or more sub-folders and/or files.',
					'nickname'       => 'deleteFolder',
					'responseClass'  => 'FoldersAndFiles',
					'parameters'     =>
					array(
						0 =>
						array(
							'name'          => 'container',
							'description'   => 'The name of the container where the folder exists.',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						1 =>
						array(
							'name'          => 'folder_path',
							'description'   => 'The path of the folder where you want to delete contents. This can be a sub-folder, with each level separated by a \'/\'',
							'allowMultiple' => false,
							'dataType'      => 'string',
							'paramType'     => 'path',
							'required'      => true,
						),
						2 =>
						array(
							'name'          => 'data',
							'description'   => 'Array of folder and files to delete.',
							'allowMultiple' => false,
							'dataType'      => 'FoldersAndFiles',
							'paramType'     => 'body',
							'required'      => false,
						),
					),
					'errorResponses' =>
					array(
						0 =>
						array(
							'reason' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
							'code'   => 400,
						),
						1 =>
						array(
							'reason' => 'Unauthorized Access - No currently valid session available.',
							'code'   => 401,
						),
						2 =>
						array(
							'reason' => 'Not Found - Requested container does not exist.',
							'code'   => 404,
						),
						3 =>
						array(
							'reason' => 'System Error - Specific reason is included in the error message.',
							'code'   => 500,
						),
					),
					'notes'          => 'Careful, this deletes the requested folder and all of its contents, unless there are posted specific sub-folders and/or files.',
				),
			),
			'description' => 'Operations on folders.',
		),
	);

$_models = array(
		'Containers'      =>
		array(
			'id'         => 'Containers',
			'properties' =>
			array(
				'container' =>
				array(
					'type'        => 'Array',
					'description' => 'An array of containers.',
					'items'       =>
					array(
						'$ref' => 'Container',
					),
				),
			),
		),
		'Container'       =>
		array(
			'id'         => 'Container',
			'properties' =>
			array(
				'name'          =>
				array(
					'type'        => 'string',
					'description' => 'Identifier/Name for the container.',
				),
				'path'          =>
				array(
					'type'        => 'string',
					'description' => 'Same as name for the container.',
				),
				'last_modified' =>
				array(
					'type'        => 'string',
					'description' => 'A GMT date timestamp of when the container was last modified.',
				),
				'_property_'    =>
				array(
					'type'        => 'string',
					'description' => 'Storage type specific properties.',
				),
				'metadata'      =>
				array(
					'type'        => 'Array',
					'description' => 'An array of name-value pairs.',
					'items'       =>
					array(
						'type' => 'string',
					),
				),
			),
		),
		'FoldersAndFiles' =>
		array(
			'id'         => 'FoldersAndFiles',
			'properties' =>
			array(
				'name'          =>
				array(
					'type'        => 'string',
					'description' => 'Identifier/Name for the current folder, localized to requested folder resource.',
				),
				'path'          =>
				array(
					'type'        => 'string',
					'description' => 'Full path of the folder, from the service including container.',
				),
				'container'     =>
				array(
					'type'        => 'string',
					'description' => 'Container for the current folder.',
				),
				'last_modified' =>
				array(
					'type'        => 'string',
					'description' => 'A GMT date timestamp of when the folder was last modified.',
				),
				'_property_'    =>
				array(
					'type'        => 'string',
					'description' => 'Storage type specific properties.',
				),
				'metadata'      =>
				array(
					'type'        => 'Array',
					'description' => 'An array of name-value pairs.',
					'items'       =>
					array(
						'type' => 'string',
					),
				),
				'folder'        =>
				array(
					'type'        => 'Array',
					'description' => 'An array of contained folders.',
					'items'       =>
					array(
						'$ref' => 'Folder',
					),
				),
				'file'          =>
				array(
					'type'        => 'Array',
					'description' => 'An array of contained files.',
					'items'       =>
					array(
						'$ref' => 'File',
					),
				),
			),
		),
		'Folder'          =>
		array(
			'id'         => 'Folder',
			'properties' =>
			array(
				'name'          =>
				array(
					'type'        => 'string',
					'description' => 'Identifier/Name for the folder, localized to requested folder resource.',
				),
				'path'          =>
				array(
					'type'        => 'string',
					'description' => 'Full path of the folder, from the service including container.',
				),
				'last_modified' =>
				array(
					'type'        => 'string',
					'description' => 'A GMT date timestamp of when the folder was last modified.',
				),
				'_property_'    =>
				array(
					'type'        => 'string',
					'description' => 'Storage type specific properties.',
				),
				'metadata'      =>
				array(
					'type'        => 'Array',
					'description' => 'An array of name-value pairs.',
					'items'       =>
					array(
						'type' => 'string',
					),
				),
			),
		),
		'File'            =>
		array(
			'id'         => 'File',
			'properties' =>
			array(
				'name'           =>
				array(
					'type'        => 'string',
					'description' => 'Identifier/Name for the file, localized to requested folder resource.',
				),
				'path'           =>
				array(
					'type'        => 'string',
					'description' => 'Full path of the file, from the service including container.',
				),
				'content_type'   =>
				array(
					'type'        => 'string',
					'description' => 'The media type of the content of the file.',
				),
				'content_length' =>
				array(
					'type'        => 'string',
					'description' => 'Size of the file in bytes.',
				),
				'last_modified'  =>
				array(
					'type'        => 'string',
					'description' => 'A GMT date timestamp of when the file was last modified.',
				),
				'_property_'     =>
				array(
					'type'        => 'string',
					'description' => 'Storage type specific properties.',
				),
				'metadata'       =>
				array(
					'type'        => 'Array',
					'description' => 'An array of name-value pairs.',
					'items'       =>
					array(
						'type' => 'string',
					),
				),
			),
		),
	);

$_base['models'] = array_merge( $_base['models'], $_models );

return $_base;
