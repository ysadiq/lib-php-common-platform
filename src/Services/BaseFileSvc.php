<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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

use DreamFactory\Platform\Events\PlatformServiceEvent;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Interfaces\FileServiceLike;
use DreamFactory\Platform\Interfaces\ServiceOnlyResourceLike;
use DreamFactory\Platform\Utility\FileUtilities;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\RestData;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * BaseFileSvc
 * Base File Storage Service giving REST access to file storage.
 *
 */
abstract class BaseFileSvc extends BasePlatformRestService implements FileServiceLike, ServiceOnlyResourceLike
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var array Array of private path strings
     */
    public $privatePaths = array();

    /**
     * @var string Storage container name
     */
    protected $_container = null;

    /**
     * @var string Full folder path of the resource
     */
    protected $_folderPath = null;

    /**
     * @var string Full file path of the resource
     */
    protected $_filePath = null;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct( $settings = array() )
    {
        if ( null === Option::get( $settings, 'verb_aliases' ) )
        {
            //	Default verb aliases
            $settings['verb_aliases'] = array(
                static::PUT   => static::POST,
                static::MERGE => static::PATCH,
            );
        }

        parent::__construct( $settings );

        $_paths = Option::clean( Option::getDeep( $settings, 'credentials', 'private_paths' ) );
        foreach ( $_paths as &$_path )
        {
            // trim here for easy comparison later
            $_path = ltrim( $_path, '/' );
        }
        $this->privatePaths = $_paths;
    }

    /**
     * Setup container and paths
     *
     * @param string $resourcePath
     *
     * @return $this
     */
    protected function _detectResourceMembers( $resourcePath = null )
    {
        parent::_detectResourceMembers( $resourcePath );

        $this->_container = Option::get( $this->_resourceArray, 0 );

        if ( !empty( $this->_container ) )
        {
            $_temp = substr( $this->_resourcePath, strlen( $this->_container . '/' ) );
            if ( false !== $_temp )
            {
                // ending in / is folder
                if ( '/' == substr( $_temp, -1, 1 ) )
                {
                    if ( '/' !== $_temp )
                    {
                        $this->_folderPath = $_temp;
                    }
                }
                else
                {
                    $this->_folderPath = dirname( $_temp ) . '/';
                    $this->_filePath = $_temp;
                }
            }
        }

        return $this;
    }

    /**
     * List all possible resources accessible via this service,
     * return false if this is not applicable
     *
     * @return array|boolean
     */
    protected function _listResources()
    {
        $result = $this->listContainers();

        return array('resource' => $result);
    }

    /**
     * @return mixed
     */
    protected function _preProcess()
    {
        parent::_preProcess();

        $this->checkPermission( $this->getRequestedAction(), $this->_container );
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function _handleGet()
    {
        if ( empty( $this->_container ) )
        {
            // see if there is any direction from the wire
            $data = Option::clean( RestData::getPostedData( false, true ) );
            $data = array_merge( $data, $_REQUEST );

            $includeProps = Option::getBool( $data, 'include_properties' );
            $result = $this->listContainers($includeProps);

                $resources = array();
                if ( $asComponents = Option::getBool( $data, 'as_access_components' ) )
                {
                    $resources = array('', '*');
                }
                foreach ( $result as $container )
                {
                    $name = Option::get( $container, 'name' );
                    $access = $this->getPermissions( $name );
                    if ( !empty( $access ) )
                    {
                        if ( $asComponents )
                        {
                            $resources[] = $name;
                        }
                        else
                        {
                            $container['access'] = $access;
                            $resources[] = $container;
                        }
                    }
                }

            if ( !$includeProps )
            {
                return array('resource' => $resources);
            }

            $result = array('container' => $resources);
        }
        else if ( empty( $this->_folderPath ) )
        {
            // resource is a container
            $includeProperties = FilterInput::request( 'include_properties', false, FILTER_VALIDATE_BOOLEAN );
            $includeFiles = FilterInput::request( 'include_files', true, FILTER_VALIDATE_BOOLEAN );
            $includeFolders = FilterInput::request( 'include_folders', true, FILTER_VALIDATE_BOOLEAN );
            $fullTree = FilterInput::request( 'full_tree', false, FILTER_VALIDATE_BOOLEAN );
            $asZip = FilterInput::request( 'zip', false, FILTER_VALIDATE_BOOLEAN );

            if ( !empty( $asZip ) )
            {
                $zipFileName = $this->getFolderAsZip( $this->_container, '' );
                FileUtilities::sendFile( $zipFileName, true );
                unlink( $zipFileName );

                // output handled by file handler, short the response here
                $this->setOutputFormat( null );
                $result = null;
            }
            else
            {
                $result = $this->getContainer(
                    $this->_container,
                    $includeFiles,
                    $includeFolders,
                    $fullTree,
                    $includeProperties
                );
            }
        }
        else if ( empty( $this->_filePath ) )
        {
            // resource is a folder
            $includeProperties = FilterInput::request( 'include_properties', false, FILTER_VALIDATE_BOOLEAN );
            $includeFiles = FilterInput::request( 'include_files', true, FILTER_VALIDATE_BOOLEAN );
            $includeFolders = FilterInput::request( 'include_folders', true, FILTER_VALIDATE_BOOLEAN );
            $fullTree = FilterInput::request( 'full_tree', false, FILTER_VALIDATE_BOOLEAN );
            $asZip = FilterInput::request( 'zip', false, FILTER_VALIDATE_BOOLEAN );
            if ( $asZip )
            {
                $zipFileName = $this->getFolderAsZip( $this->_container, $this->_folderPath );
                FileUtilities::sendFile( $zipFileName, true );
                unlink( $zipFileName );

                // output handled by file handler, short the response here
                $this->setOutputFormat( null );
                $result = null;
            }
            else
            {
                $result = $this->getFolder(
                    $this->_container,
                    $this->_folderPath,
                    $includeFiles,
                    $includeFolders,
                    $fullTree,
                    $includeProperties
                );
            }
        }
        else
        {
            // resource is a file
            $includeProperties = FilterInput::request( 'include_properties', false, FILTER_VALIDATE_BOOLEAN );
            if ( $includeProperties )
            {
                // just properties of the file itself
                $content = FilterInput::request( 'content', false, FILTER_VALIDATE_BOOLEAN );
                $result = $this->getFileProperties( $this->_container, $this->_filePath, $content );
            }
            else
            {
                $download = FilterInput::request( 'download', false, FILTER_VALIDATE_BOOLEAN );
                // stream the file, exits processing
                $this->streamFile( $this->_container, $this->_filePath, $download );

                // output handled by file handler, short the response here
                $this->setOutputFormat( null );
                $result = null;
            }
        }

        return $result;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function _handlePost()
    {
        if ( empty( $this->_container ) )
        {
            // create one or more containers
            $checkExist = FilterInput::request( 'check_exist', false, FILTER_VALIDATE_BOOLEAN );
            $data = RestData::getPostedData( false, true );
            $containers = Option::get( $data, 'container' );
            if ( empty( $containers ) )
            {
                $containers = Option::getDeep( $data, 'containers', 'container' );
            }
            if ( !empty( $containers ) )
            {
                $result = $this->createContainers( $containers, $checkExist );
                $result = array('container' => $result);
            }
            else
            {
                $result = $this->createContainer( $data, $checkExist );
            }
        }
        else if ( empty( $this->_folderPath ) || empty( $this->_filePath ) )
        {
            // create folders and files
            // possible file handling parameters
            $extract = FilterInput::request( 'extract', false, FILTER_VALIDATE_BOOLEAN );
            $clean = FilterInput::request( 'clean', false, FILTER_VALIDATE_BOOLEAN );
            $checkExist = FilterInput::request( 'check_exist', false, FILTER_VALIDATE_BOOLEAN );
            $fileNameHeader = FilterInput::server( 'HTTP_X_FILE_NAME' );
            $folderNameHeader = FilterInput::server( 'HTTP_X_FOLDER_NAME' );
            $fileUrl = FilterInput::request( 'url', '', FILTER_SANITIZE_URL );
            if ( !empty( $fileNameHeader ) )
            {
                // html5 single posting for file create
                $content = RestData::getPostedData();
                $contentType = FilterInput::server( 'CONTENT_TYPE', '' );
                $result = $this->_handleFileContent(
                    $this->_folderPath,
                    $fileNameHeader,
                    $content,
                    $contentType,
                    $extract,
                    $clean,
                    $checkExist
                );
            }
            elseif ( !empty( $folderNameHeader ) )
            {
                // html5 single posting for folder create
                $fullPathName = $this->_folderPath . $folderNameHeader;
                $content = RestData::getPostedData( false, true );
                $this->createFolder( $this->_container, $fullPathName, $content );
                $result = array(
                    'folder' => array(
                        array(
                            'name' => $folderNameHeader,
                            'path' => $this->_container . '/' . $fullPathName
                        )
                    )
                );
            }
            elseif ( !empty( $fileUrl ) )
            {
                // upload a file from a url, could be expandable zip
                $tmpName = null;
                try
                {
                    $tmpName = FileUtilities::importUrlFileToTemp( $fileUrl );
                    $result = $this->_handleFile(
                        $this->_folderPath,
                        '',
                        $tmpName,
                        '',
                        $extract,
                        $clean,
                        $checkExist
                    );
                    unlink( $tmpName );
                }
                catch ( \Exception $ex )
                {
                    if ( !empty( $tmpName ) )
                    {
                        unlink( $tmpName );
                    }
                    throw $ex;
                }
            }
            elseif ( isset( $_FILES['files'] ) && !empty( $_FILES['files'] ) )
            {
                // older html multi-part/form-data post, single or multiple files
                $files = FileUtilities::rearrangePostedFiles( $_FILES['files'] );
                $result = $this->_handleFolderContentFromFiles( $files, $extract, $clean, $checkExist );
            }
            else
            {
                // possibly xml or json post either of files or folders to create, copy or move
                $data = RestData::getPostedData( false, true );
                if ( empty( $data ) )
                {
                    // create folder from resource path
                    $this->createFolder( $this->_container, $this->_folderPath );
                    $result = array('folder' => array(array('path' => $this->_container . '/' . $this->_folderPath)));
                }
                else
                {
                    $result = $this->_handleFolderContentFromData( $data, $extract, $clean, $checkExist );
                }
            }
        }
        else
        {
            // create the file
            // possible file handling parameters
            $extract = FilterInput::request( 'extract', false, FILTER_VALIDATE_BOOLEAN );
            $clean = FilterInput::request( 'clean', false, FILTER_VALIDATE_BOOLEAN );
            $checkExist = FilterInput::request( 'check_exist', false, FILTER_VALIDATE_BOOLEAN );
            $name = basename( $this->_filePath );
            $path = dirname( $this->_filePath );
            $files = Option::get( $_FILES, 'files' );
            if ( empty( $files ) )
            {
                $contentType = Option::get( $_SERVER, 'CONTENT_TYPE', '' );
                // direct load from posted data as content
                // or possibly xml or json post of file properties create, copy or move
                $content = RestData::getPostedData();
                $result = $this->_handleFileContent(
                    $path,
                    $name,
                    $content,
                    $contentType,
                    $extract,
                    $clean,
                    $checkExist
                );
            }
            else
            {
                // older html multipart/form-data post, should be single file
                $files = FileUtilities::rearrangePostedFiles( $_FILES['files'] );
                if ( 1 < count( $files ) )
                {
                    throw new BadRequestException( "Multiple files uploaded to a single REST resource '$name'." );
                }
                $file = Option::get( $files, 0 );
                if ( empty( $file ) )
                {
                    throw new BadRequestException( "No file uploaded to REST resource '$name'." );
                }
                $error = $file['error'];
                if ( UPLOAD_ERR_OK == $error )
                {
                    $tmpName = $file["tmp_name"];
                    $contentType = $file['type'];
                    $result = $this->_handleFile(
                        $path,
                        $name,
                        $tmpName,
                        $contentType,
                        $extract,
                        $clean,
                        $checkExist
                    );
                }
                else
                {
                    throw new InternalServerErrorException( "Failed to upload file $name.\n$error" );
                }
            }
        }

        return $result;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function _handlePatch()
    {
        if ( empty( $this->_container ) )
        {
            // nothing?
            $result = array();
        }
        else if ( empty( $this->_folderPath ) )
        {
            // update container properties
            $content = RestData::getPostedData( false, true );
            $this->updateContainerProperties( $this->_container, $content );
            $result = array('container' => array('name' => $this->_container));
        }
        else if ( empty( $this->_filePath ) )
        {
            // update folder properties
            $content = RestData::getPostedData( false, true );
            $this->updateFolderProperties( $this->_container, $this->_folderPath, $content );
            $result = array(
                'folder' => array(
                    'name' => basename( $this->_folderPath ),
                    'path' => $this->_container . '/' . $this->_folderPath
                )
            );
        }
        else
        {
            // update file properties?
            $content = RestData::getPostedData( false, true );
            $this->updateFileProperties( $this->_container, $this->_filePath, $content );
            $result = array(
                'file' => array(
                    'name' => basename( $this->_filePath ),
                    'path' => $this->_container . '/' . $this->_filePath
                )
            );
        }

        return $result;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function _handleDelete()
    {
        $force = FilterInput::request( 'force', false, FILTER_VALIDATE_BOOLEAN );
        $content = RestData::getPostedData( false, true );
        if ( empty( $this->_container ) )
        {
            $containers = Option::get( $content, 'container' );
            if ( empty( $containers ) )
            {
                $containers = Option::getDeep( $content, 'containers', 'container' );
            }
            if ( !empty( $containers ) )
            {
                // delete multiple containers
                $result = $this->deleteContainers( $containers, $force );
                $result = array('container' => $result);
            }
            else
            {
                $_name = Option::get( $content, 'name', trim( Option::get( $content, 'path' ), '/' ) );
                if ( empty( $_name ) )
                {
                    throw new BadRequestException( 'No name found for container in delete request.' );
                }
                $this->deleteContainer( $_name, $force );
                $result = array('name' => $_name, 'path' => $_name);
            }
        }
        else if ( empty( $this->_folderPath ) )
        {
            // delete whole container
            // or just folders and files from the container
            if ( empty( $content ) )
            {
                $this->deleteContainer( $this->_container, $force );
                $result = array('name' => $this->_container);
            }
            else
            {
                $result = $this->_deleteFolderContent( $content, '', $force );
            }
        }
        else if ( empty( $this->_filePath ) )
        {
            // delete directory of files and the directory itself
            // multi-file or folder delete via post data
            if ( empty( $content ) )
            {
                $this->deleteFolder( $this->_container, $this->_folderPath, $force );
                $result = array('folder' => array(array('path' => $this->_container . '/' . $this->_folderPath)));
            }
            else
            {
                $result = $this->_deleteFolderContent( $content, $this->_folderPath, $force );
            }
        }
        else
        {
            // delete file from permanent storage
            $this->deleteFile( $this->_container, $this->_filePath );
            $result = array('file' => array(array('path' => $this->_container . '/' . $this->_filePath)));
        }

        return $result;
    }

    /**
     * @param        $dest_path
     * @param        $dest_name
     * @param        $source_file
     * @param string $contentType
     * @param bool   $extract
     * @param bool   $clean
     * @param bool   $check_exist
     *
     * @throws \Exception
     * @return array
     */
    protected function _handleFile( $dest_path, $dest_name, $source_file, $contentType = '', $extract = false, $clean = false, $check_exist = false )
    {
        $ext = FileUtilities::getFileExtension( $source_file );
        if ( empty( $contentType ) )
        {
            $contentType = FileUtilities::determineContentType( $ext, '', $source_file );
        }
        if ( ( FileUtilities::isZipContent( $contentType ) || ( 'zip' === $ext ) ) && $extract )
        {
            // need to extract zip file and move contents to storage
            $zip = new \ZipArchive();
            if ( true === $zip->open( $source_file ) )
            {
                return $this->extractZipFile( $this->_container, $dest_path, $zip, $clean );
            }
            else
            {
                throw new InternalServerErrorException( 'Error opening temporary zip file.' );
            }
        }
        else
        {
            $name = ( empty( $dest_name ) ? basename( $source_file ) : $dest_name );
            $fullPathName = FileUtilities::fixFolderPath( $dest_path ) . $name;
            $this->moveFile( $this->_container, $fullPathName, $source_file, $check_exist );

            return array(
                'file' => array(
                    array(
                        'name' => $name,
                        'path' => $this->_container . '/' . $fullPathName
                    )
                )
            );
        }
    }

    /**
     * @param        $dest_path
     * @param        $dest_name
     * @param        $content
     * @param string $contentType
     * @param bool   $extract
     * @param bool   $clean
     * @param bool   $check_exist
     *
     * @throws \Exception
     * @return array
     */
    protected function _handleFileContent( $dest_path, $dest_name, $content, $contentType = '', $extract = false, $clean = false, $check_exist = false )
    {
        $ext = FileUtilities::getFileExtension( $dest_name );
        if ( empty( $contentType ) )
        {
            $contentType = FileUtilities::determineContentType( $ext, $content );
        }
        if ( ( FileUtilities::isZipContent( $contentType ) || ( 'zip' === $ext ) ) && $extract )
        {
            // need to extract zip file and move contents to storage
            $tempDir = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
            $tmpName = $tempDir . $dest_name;
            file_put_contents( $tmpName, $content );
            $zip = new \ZipArchive();
            $code = $zip->open( $tmpName );
            if ( true !== $code )
            {
                unlink( $tmpName );

                throw new InternalServerErrorException( 'Error opening temporary zip file. code = ' . $code );
            }

            $results = $this->extractZipFile( $this->_container, $dest_path, $zip, $clean );
            unlink( $tmpName );

            return $results;
        }
        else
        {
            $fullPathName = FileUtilities::fixFolderPath( $dest_path ) . $dest_name;
            $this->writeFile( $this->_container, $fullPathName, $content, false, $check_exist );

            return array(
                'file' => array(
                    array(
                        'name' => $dest_name,
                        'path' => $this->_container . '/' . $fullPathName
                    )
                )
            );
        }
    }

    /**
     * @param array $files
     * @param bool  $extract
     * @param bool  $clean
     * @param bool  $checkExist
     *
     * @return array
     * @throws \Exception
     */
    protected function _handleFolderContentFromFiles( $files, $extract = false, $clean = false, $checkExist = false )
    {
        $out = array();
        $err = array();
        foreach ( $files as $key => $file )
        {
            $name = $file['name'];
            $error = $file['error'];
            if ( $error == UPLOAD_ERR_OK )
            {
                $tmpName = $file['tmp_name'];
                $contentType = $file['type'];
                $tmp = $this->_handleFile(
                    $this->_folderPath,
                    $name,
                    $tmpName,
                    $contentType,
                    $extract,
                    $clean,
                    $checkExist
                );
                $out[$key] = ( isset( $tmp['file'] ) ? $tmp['file'] : array() );
            }
            else
            {
                $err[] = $name;
            }
        }
        if ( !empty( $err ) )
        {
            $msg =
                'Failed to upload the following files to folder ' . $this->_folderPath . ': ' . implode( ', ', $err );
            throw new InternalServerErrorException( $msg );
        }

        return array('file' => $out);
    }

    /**
     * @param array $data
     * @param bool  $extract
     * @param bool  $clean
     * @param bool  $checkExist
     *
     * @return array
     */
    protected function _handleFolderContentFromData( $data, /** @noinspection PhpUnusedParameterInspection */
        $extract = false, /** @noinspection PhpUnusedParameterInspection */
        $clean = false, /** @noinspection PhpUnusedParameterInspection */
        $checkExist = false )
    {
        $out = array('folder' => array(), 'file' => array());
        $folders = Option::get( $data, 'folder' );
        if ( empty( $folders ) )
        {
            $folders = Option::getDeep( $data, 'folders', 'folder' );
        }
        if ( !empty( $folders ) )
        {
            if ( !isset( $folders[0] ) )
            {
                // single folder, make into array
                $folders = array($folders);
            }
            foreach ( $folders as $key => $folder )
            {
                $name = Option::get( $folder, 'name', '' );
                $srcPath = Option::get( $folder, 'source_path' );
                if ( !empty( $srcPath ) )
                {
                    $srcContainer = Option::get( $folder, 'source_container', $this->_container );
                    // copy or move
                    if ( empty( $name ) )
                    {
                        $name = FileUtilities::getNameFromPath( $srcPath );
                    }
                    $fullPathName = $this->_folderPath . $name . '/';
                    $out['folder'][$key] = array('name' => $name, 'path' => $this->_container . '/' . $fullPathName);
                    try
                    {
                        $this->copyFolder( $this->_container, $fullPathName, $srcContainer, $srcPath, true );
                        $deleteSource = Option::getBool( $folder, 'delete_source' );
                        if ( $deleteSource )
                        {
                            $this->deleteFolder( $this->_container, $srcPath, true );
                        }
                    }
                    catch ( \Exception $ex )
                    {
                        $out['folder'][$key]['error'] = array('message' => $ex->getMessage());
                    }
                }
                else
                {
                    $fullPathName = $this->_folderPath . $name;
                    $content = Option::get( $folder, 'content', '' );
                    $isBase64 = Option::getBool( $folder, 'is_base64' );
                    if ( $isBase64 )
                    {
                        $content = base64_decode( $content );
                    }
                    $out['folder'][$key] = array('name' => $name, 'path' => $this->_container . '/' . $fullPathName);
                    try
                    {
                        $this->createFolder( $this->_container, $fullPathName, true, $content );
                    }
                    catch ( \Exception $ex )
                    {
                        $out['folder'][$key]['error'] = array('message' => $ex->getMessage());
                    }
                }
            }
        }
        $files = Option::get( $data, 'file' );
        if ( empty( $files ) )
        {
            $files = Option::getDeep( $data, 'files', 'file' );
        }
        if ( !empty( $files ) )
        {
            if ( !isset( $files[0] ) )
            {
                // single file, make into array
                $files = array($files);
            }
            foreach ( $files as $key => $file )
            {
                $name = Option::get( $file, 'name', '' );
                $srcPath = Option::get( $file, 'source_path' );
                if ( !empty( $srcPath ) )
                {
                    // copy or move
                    $srcContainer = Option::get( $file, 'source_container', $this->_container );
                    if ( empty( $name ) )
                    {
                        $name = FileUtilities::getNameFromPath( $srcPath );
                    }
                    $fullPathName = $this->_folderPath . $name;
                    $out['file'][$key] = array('name' => $name, 'path' => $this->_container . '/' . $fullPathName);
                    try
                    {
                        $this->copyFile( $this->_container, $fullPathName, $srcContainer, $srcPath, true );
                        $deleteSource = Option::getBool( $file, 'delete_source' );
                        if ( $deleteSource )
                        {
                            $this->deleteFile( $this->_container, $srcPath );
                        }
                    }
                    catch ( \Exception $ex )
                    {
                        $out['file'][$key]['error'] = array('message' => $ex->getMessage());
                    }
                }
                elseif ( isset( $file['content'] ) )
                {
                    $fullPathName = $this->_folderPath . $name;
                    $out['file'][$key] = array('name' => $name, 'path' => $this->_container . '/' . $fullPathName);
                    $content = Option::get( $file, 'content', '' );
                    $isBase64 = Option::getBool( $file, 'is_base64' );
                    if ( $isBase64 )
                    {
                        $content = base64_decode( $content );
                    }
                    try
                    {
                        $this->writeFile( $this->_container, $fullPathName, $content );
                    }
                    catch ( \Exception $ex )
                    {
                        $out['file'][$key]['error'] = array('message' => $ex->getMessage());
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param array  $data Array of sub-folder and file paths that are relative to the root folder
     * @param string $root root folder from which to delete
     * @param  bool  $force
     *
     * @return array
     */
    protected function _deleteFolderContent( $data, $root = '', $force = false )
    {
        $out = array('folder' => array(), 'file' => array());
        $folders = Option::get( $data, 'folder' );
        if ( empty( $folders ) )
        {
            $folders = Option::getDeep( $data, 'folders', 'folder' );
        }
        if ( !empty( $folders ) )
        {
            if ( !isset( $folders[0] ) )
            {
                // single folder, make into array
                $folders = array($folders);
            }
            $out['folder'] = $this->deleteFolders( $this->_container, $folders, $root, $force );
        }
        $files = Option::get( $data, 'file' );
        if ( empty( $files ) )
        {
            $files = Option::getDeep( $data, 'files', 'file' );
        }
        if ( !empty( $files ) )
        {
            if ( !isset( $files[0] ) )
            {
                // single file, make into array
                $files = array($files);
            }
            $out['files'] = $this->deleteFiles( $this->_container, $files, $root );
        }

        return $out;
    }

    /**
     * @param string               $eventName
     * @param PlatformServiceEvent $event
     * @param int                  $priority
     *
     * @return bool|PlatformServiceEvent
     */
    public function trigger( $eventName, $event = null, $priority = 0 )
    {
        if ( is_array( $event ) )
        {
            $event = new PlatformServiceEvent( $this->_apiName, $this->_resource, $event );
        }

        return Platform::trigger(
            str_ireplace(
                array('{api_name}', '{action}', '{container}', '{folder_path}', '{file_path}',),
                array(
                    $this->_apiName == 'system' ? $this->_resource : $this->_apiName,
                    strtolower( $this->_action ),
                    $this->_container,
                    $this->_folderPath,
                    $this->_filePath,
                ),
                $eventName
            ),
            $event ? : new PlatformServiceEvent( $this->_apiName, $this->_resource, $this->_response ),
            $priority
        );
    }

    /**
     * @param        $container
     * @param array  $folders Array of folder paths that are relative to the root directory
     * @param string $root    directory from which to delete
     * @param  bool  $force
     *
     * @return array
     */
    abstract public function deleteFolders( $container, $folders, $root = '', $force = false );

    /**
     * @return string
     */
    public function getContainerId()
    {
        return $this->_container;
    }

    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->_filePath;
    }

    /**
     * @return string
     */
    public function getFolderPath()
    {
        return $this->_folderPath;
    }

}
