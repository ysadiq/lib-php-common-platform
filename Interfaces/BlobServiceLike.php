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
namespace DreamFactory\Platform\Interfaces;

/**
 * BlobServiceLike.php
 * Interface for handling blob storage resources.
 */
interface BlobServiceLike
{
	/**
	 * @return array
	 * @throws \Exception
	 */
	public function listContainers();

	/**
	 * Check if a container exists
	 *
	 * @param  string $container Container name
	 *
	 * @return boolean
	 * @throws \Exception
	 */
	public function containerExists( $container = null );

	/**
	 * @param string $container
	 * @param array  $metadata
	 *
	 * @throws \Exception
	 */
	public function createContainer( $container = null, $metadata = array() );

	/**
	 * @param string $container
	 *
	 * @throws \Exception
	 */
	public function deleteContainer( $container = null );

	/**
	 * Check if a blob exists
	 *
	 * @param  string $container Container name
	 * @param  string $name      Blob name
	 *
	 * @return boolean
	 * @throws \Exception
	 */
	public function blobExists( $container = null, $name = null );

	/**
	 * @param string $container
	 * @param string $name
	 * @param string $blob
	 * @param string $type
	 *
	 * @throws \Exception
	 */
	public function putBlobData( $container = null, $name = null, $blob = null, $type = null );

	/**
	 * @param string $container
	 * @param string $name
	 * @param string $localFileName
	 * @param string $type
	 *
	 * @throws \Exception
	 */
	public function putBlobFromFile( $container = null, $name = null, $localFileName = null, $type = null );

	/**
	 * @param string $container
	 * @param string $name
	 * @param string $src_container
	 * @param string $src_name
	 *
	 * @throws \Exception
	 */
	public function copyBlob( $container = null, $name = null, $src_container = null, $src_name = null );

	/**
	 * Get blob
	 *
	 * @param  string $container     Container name
	 * @param  string $name          Blob name
	 * @param  string $localFileName Local file name to store downloaded blob
	 *
	 * @throws \Exception
	 */
	public function getBlobAsFile( $container = null, $name = null, $localFileName = null );

	/**
	 * @param string $container
	 * @param string $name
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function getBlobData( $container = null, $name = null );

	/**
	 * @param string $container
	 * @param string $name
	 *
	 * @throws \Exception
	 */
	public function deleteBlob( $container = null, $name = null );

	/**
	 * List blobs
	 *
	 * @param  string $container Container name
	 * @param  string $prefix    Optional. Filters the results to return only blobs whose name begins with the specified prefix.
	 * @param  string $delimiter Optional. Delimiter, i.e. '/', for specifying folder hierarchy
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function listBlobs( $container = null, $prefix = null, $delimiter = null );

	/**
	 * List blob
	 *
	 * @param  string $container Container name
	 * @param  string $name      Blob name
	 *
	 * @return array instance
	 * @throws \Exception
	 */
	public function listBlob( $container, $name );

	/**
	 * @param       $container
	 * @param       $blobName
	 * @param array $params
	 *
	 * @throws \Exception
	 */
	public function streamBlob( $container, $blobName, $params = array() );
}
