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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\DataFormats;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Enums\ScriptLanguages;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Scripting\Api;
use DreamFactory\Platform\Scripting\ScriptEngine;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Platform\Utility\Fabric;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Exceptions\FileSystemException;
use Kisma\Core\Interfaces\HttpResponse;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Script.php
 * Script service
 */
class Script extends BaseSystemRestResource
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /** @type string */
    const DEFAULT_SCRIPT_PATH = '/scripts';
    /** @type string */
    const DEFAULT_USER_SCRIPT_PATH = '/scripts.user';
    /** @type string */
    const DEFAULT_SCRIPT_PATTERN = '/*.+({extension_list})';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array Supported script extensions
     */
    protected $_extensions = array();
    /**
     * @var string The path to script storage area
     */
    protected $_scriptPath = null;
    /**
     * @type bool True if user scripts are enabled
     */
    protected $_enableUserScripts = false;
    /**
     * @var string The path to user script storage area
     */
    protected $_userScriptPath = null;
    /**
     * @var int The maximum time (in ms) to allow scripts to run
     */
    protected static $_scriptTimeout = 60000;
    /**
     * @var ScriptEngine
     */
    protected static $_scriptEngine = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param \DreamFactory\Platform\Interfaces\RestResourceLike|\DreamFactory\Platform\Interfaces\RestServiceLike $consumer
     * @param array                                                                                                $resources
     *
     * @throws \Kisma\Core\Exceptions\FileSystemException
     * @throws \InvalidArgumentException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @internal param array $settings
     *
     */
    public function __construct( $consumer, $resources = array() )
    {
        //	Pull out our settings before calling daddy
        $_config = array(
            'name'          => 'Script',
            'description'   => 'A sandboxed script manager endpoint',
            'api_name'      => 'script',
            'service_name'  => 'system',
            'type'          => 'System',
            'type_id'       => PlatformServiceTypes::SYSTEM_SERVICE,
            'is_active'     => true,
            'native_format' => DataFormats::NATIVE,
        );

        parent::__construct( $consumer, $_config, $resources );

        $this->checkAvailability();
    }

    /**
     * Checks to make sure all our ducks are in a row...
     *
     * @return bool
     * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     */
    public function checkAvailability()
    {
        $_message = false;

        if ( Fabric::fabricHosted() )
        {
            throw new ForbiddenException( 'This resource is not available on a free-hosted DSP.' );
        }

        //  User scripts are stored here
        $this->_enableUserScripts = Pii::getParam( 'dsp.enable_user_scripts', false );
        $this->_scriptPath = Platform::getPrivatePath( static::DEFAULT_SCRIPT_PATH );
        $this->_userScriptPath = Platform::getPrivatePath( static::DEFAULT_USER_SCRIPT_PATH );

        if ( empty( $this->_scriptPath ) || empty( $this->_userScriptPath ) )
        {
            $_message = 'Empty script path';
        }
        else
        {
            if ( !is_dir( $this->_scriptPath ) )
            {
                if ( false === @mkdir( $this->_scriptPath, 0777, true ) )
                {
                    $_message = 'File system error creating scripts path: ' . $this->_scriptPath;
                }
            }
            else if ( !is_writable( $this->_scriptPath ) )
            {
                $_message = 'Scripts path not writable: ' . $this->_scriptPath;
            }

            if ( $this->_enableUserScripts )
            {
                if ( !is_dir( $this->_userScriptPath ) )
                {
                    if ( false === @mkdir( $this->_userScriptPath, 0777, true ) )
                    {
                        $_message = 'File system error creating user scripts path: ' . $this->_userScriptPath;
                    }
                }
                else if ( !is_writable( $this->_userScriptPath ) )
                {
                    $_message = 'User scripts path not writable: ' . $this->_userScriptPath;
                }
            }
            else
            {
                if ( Option::get( $this->_requestPayload, 'is_user_script', false ) )
                {
                    throw new ForbiddenException( 'User scripts are not allowed on this server.' );
                }
            }
        }

        if ( $_message )
        {
            Log::error( $_message );

            throw new RestException(
                HttpResponse::ServiceUnavailable, 'This service is not available. Storage path, area, and/or required libraries are missing.'
            );
        }

        $this->_extensions = ScriptEngine::getSupportedExtensions();

        return true;
    }

    /**
     * @param string $language           The language of the scripts to return. Defaults to "*", or all
     * @param bool   $includeUserScripts If true, any user scripts are included in the list
     * @param bool   $onlyUserScripts    IF true, only user scripts are returned
     *
     * @return array|bool
     */
    protected function _getScriptList( $language = ScriptLanguages::JAVASCRIPT, $includeUserScripts = true, $onlyUserScripts = false )
    {
        $_resources = array(
            'event' => $this->_scriptPath . '/*' . ( ScriptLanguages::ALL === $language ? null : '.' . $language ),
            'user'  => $this->_userScriptPath . '/*' . ( ScriptLanguages::ALL === $language ? null : '.' . $language ),
        );

        //  If user scripts are disabled, remove from list of paths to check
        if ( !$this->_enableUserScripts )
        {
            unset( $_resources['user'] );
        }

        $_response = array();

        foreach ( $_resources as $_key => $_resource )
        {
            $_response[$_key] = array();
            $_files = FileSystem::glob( $_resource, GlobFlags::GLOB_NODOTS );

            if ( !empty( $_files ) )
            {
                foreach ( $_files as $_file )
                {
                    if ( in_array( pathinfo( $_file, PATHINFO_EXTENSION ), $this->_extensions ) )
                    {
                        $_response[$_key][] = $_file;
                    }
                }
            }
        }

        if ( $this->_enableUserScripts && ( $includeUserScripts && $onlyUserScripts ) )
        {
            unset( $_response['event'] );
        }
        else if ( !$this->_enableUserScripts || !$includeUserScripts )
        {
            unset( $_response['user'] );
        }

        return $_response;
    }

    /**
     * @param array $scripts
     * @param bool  $userScripts
     * @param bool  $includeBody
     *
     * @return array
     */
    protected function _buildScriptArray( $scripts = array(), $userScripts = false, $includeBody = false )
    {
        $_response = array();
        $_scriptPath = $this->_getScriptPath( null, null, $userScripts );

        foreach ( $scripts as $_script )
        {
            $_scriptId = rtrim( str_ireplace( $this->_extensions, null, $_script ), '.' );
            $_fullScriptPath = $_scriptPath . DIRECTORY_SEPARATOR . $_script;

            $_resource = array(
                'script_id'      => $_scriptId,
                'is_user_script' => $userScripts,
                'script'         => $_script,
                'file_name'      => $_script,
                'file_path'      => $_fullScriptPath,
                'file_mtime'     => filemtime( $_fullScriptPath ),
                'event_name'     => $_scriptId,
                'language'       => trim( str_replace( $_scriptId, null, $_script ), '.' ),
            );

            if ( $includeBody )
            {
                $_resource['script_body'] = @file_get_contents( $_scriptPath . '/' . $_script );
            }

            $_response[] = $_resource;
            unset( $_resource, $_eventName );
        }

        return $_response;
    }

    /**
     * LIST all scripts
     *
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _listResources()
    {
        $_includeBody = Option::getBool( $this->_requestPayload, 'include_script_body', false );
        $_includeUserScripts = !$this->_enableUserScripts ? false : Option::getBool( $this->_requestPayload, 'include_user_scripts', true );
        $_onlyUserScripts = Option::getBool( $this->_requestPayload, 'include_only_user_scripts', false );
        $_language = Option::get( $this->_requestPayload, 'language', ScriptLanguages::ALL );

        $_scripts = $this->_getScriptList( $_language, $_includeUserScripts, $_onlyUserScripts );

        $_response = array();

        if ( !empty( $_scripts ) )
        {
            $_response = $this->_buildScriptArray( Option::get( $_scripts, 'event', array() ), false, $_includeBody );

            if ( isset( $_scripts['user'] ) && !empty( $_scripts['user'] ) )
            {
                $_response += $this->_buildScriptArray( Option::get( $_scripts, 'user', array() ), true, $_includeBody );
            }
        }

        ksort( $_response );

        return array('resource' => $_response);
    }

    /**
     * GET a script
     *
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleGet()
    {
        if ( empty( $this->_resourceId ) )
        {
            return $this->_listResources();
        }

        $_includeBody = Option::getBool( $this->_requestPayload, 'include_script_body', true );
        $_user = Option::getBool( $this->_requestPayload, 'is_user_script', false );
        $_language = Option::get( $this->_requestPayload, 'language', ScriptLanguages::JAVASCRIPT );
        $_path = $this->_getScriptPath( null, $_language, $_user );
        $_script = basename( $_path );

        if ( !file_exists( $_path ) )
        {
            throw new NotFoundException( '"' . $this->_resourceId . '" was not found.' );
        }

        $_body = $_includeBody ? @file_get_contents( $_path ) : false;

        return array(
            'script_id'      => $this->_resourceId,
            'script'         => $_script,
            'script_body'    => $_body,
            'is_user_script' => $_user,
            'language'       => $_language,
            'file_name'      => $_script,
            'file_path'      => $_path,
            'file_mtime'     => filemtime( $_path ),
            'event_name'     => $_user ? false : $this->_resourceId,
        );
    }

    /**
     * WRITE a script
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @return array|bool
     */
    protected function _handlePut()
    {
        if ( empty( $this->_resourceId ) )
        {
            throw new BadRequestException( 'No resource id specified.' );
        }

        $_params = $this->_getRequestData();

        $_path = $this->_getScriptPath( trim( $this->_resourceId, '/ ' ), $_params['language'], $_params['is_user_script'] );
        $_script = basename( $_path );

        Log::debug( print_r( $_params, true ) . PHP_EOL . print_r( $_SERVER, true ) . PHP_EOL . print_r( $_REQUEST, true ) );

        if ( is_array( $_params['request_body'] ) )
        {
            $_params['request_body'] = current( $_params['request_body'] );
        }

        if ( empty( $_params['request_body'] ) )
        {
            throw new BadRequestException( 'You must supply a "script_body".' );
        }

        if ( false === ( $_bytes = @file_put_contents( $_path, $_params['request_body'] ) ) )
        {
            throw new FileSystemException( 'Error writing file to storage area: ' . $_path );
        }

        //  Clear the swagger cache...
        SwaggerManager::clearCache();

        return array(
            'script_id'      => $this->_resourceId,
            'script'         => $_script,
            'script_body'    => $_params['request_body'],
            'is_user_script' => $_params['is_user_script'],
            'language'       => $_params['language'],
            'file_name'      => $_script,
            'file_path'      => $_path,
            'file_mtime'     => filemtime( $_path ),
            'event_name'     => $_params['is_user_script'] ? false : $this->_resourceId,
            'bytes_written'  => $_bytes,
        );
    }

    /**
     * DELETE an existing script
     * This is a permanent/destructive action.
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     * @return bool|void
     */
    protected function _handleDelete()
    {
        if ( empty( $this->_resourceId ) )
        {
            throw new BadRequestException( 'No script ID specified.' );
        }

        $_user = Option::get( $this->_requestPayload, 'is_user_script', false );
        $_language = Option::get( $this->_requestPayload, 'language', ScriptLanguages::JAVASCRIPT );
        $_path = $this->_getScriptPath( trim( $this->_resourceId, '/ ' ), $_language, $_user );
        $_script = basename( $_path );

        if ( !file_exists( $_path ) )
        {
            throw new NotFoundException();
        }

        $_body = @file_get_contents( $_path );

        if ( false === @unlink( $_path ) )
        {
            throw new InternalServerErrorException( 'Unable to delete script ID "' . $this->_resourceId . '"' );
        }

        //  Clear the swagger cache...
        SwaggerManager::clearCache();

        return array(
            'script_id'      => $this->_resourceId,
            'script'         => $_script,
            'script_body'    => $_body,
            'is_user_script' => $_user,
            'language'       => $_language,
            'file_name'      => $_script,
            'file_path'      => $_path,
            'file_mtime'     => false,
            'event_name'     => $_user ? false : $this->_resourceId,
        );
    }

    /**
     * RUN a script
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @return array
     */
    protected function _handlePost()
    {
        if ( empty( $this->_resourceId ) )
        {
            throw new BadRequestException();
        }

        if ( !extension_loaded( 'v8js' ) )
        {
            throw new RestException(
                HttpResponse::ServiceUnavailable, 'This DSP cannot run server-side javascript scripts. The "v8js" is not available.'
            );
        }

        $_user = Option::get( $this->_requestPayload, 'is_user_script', false );
        $_language = Option::get( $this->_requestPayload, 'language', ScriptLanguages::JAVASCRIPT );

        $_api = array(
            'api'     => Api::getScriptingObject(),
            'config'  => Config::getCurrentConfig(),
            'session' => Session::generateSessionDataFromUser( Session::getCurrentUserId() )
        );

        return ScriptEngine::runScript(
            $this->_getScriptPath( $this->_resourceId, $_language, $_user ),
            $this->_resource . '.' . $this->_resourceId,
            $this->_requestPayload,
            $_api
        );
    }

    /**
     * @param string $eventName
     *
     * @return bool
     */
    public static function existsForEvent( $eventName )
    {
        $_scripts = FileSystem::glob(
            Platform::getPrivatePath( static::DEFAULT_SCRIPT_PATH ) . static::DEFAULT_SCRIPT_PATTERN,
            GlobFlags::GLOB_NODOTS
        );

        foreach ( $_scripts as $_script )
        {
            if ( $eventName == str_replace( '.js', null, $_script ) )
            {
                return $_script;
            }
        }

        return false;
    }

    /**
     * @param array $keys Additional parameter keys to include in the defaults
     *
     * @return array
     */
    protected function _getRequestData( array $keys = array() )
    {
        $_postData = RestData::getPostedData( false, true );

        $_result = array(
            'is_user_script'            => Option::getBool( $_postData, 'is_user_script', false ),
            'include_script_body'       => Option::getBool( $_postData, 'include_script_body', false ),
            'include_user_scripts'      => !$this->_enableUserScripts ? false : Option::getBool( $_postData, 'include_user_scripts', true ),
            'include_only_user_scripts' => Option::getBool( $_postData, 'include_only_user_scripts', false ),
            'language'                  => Option::get( $_postData, 'language', ScriptLanguages::JAVASCRIPT ),
            'request_body'              => Option::getDeep( $_postData, 'record', 0 ),
        );

        if ( !empty( $keys ) )
        {
            foreach ( $keys as $_key )
            {
                $_result[$_key] = Option::get( $this->_requestPayload, $_key );
            }
        }

        return $_result;
    }

    /**
     * @param array $payload
     *
     * @return \stdClass
     */
    protected static function _preparePayload( $payload = array() )
    {
        //  Quick and dirty object conversion
        return json_decode( json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
    }

    /**
     * Constructs the full path to a server-side script
     *
     * @param string $scriptName The script name or null if $this->_resourceId is to be used
     * @param string $language   The script language
     * @param bool   $userScript True if you want a user script's path
     *
     * @return string
     */
    protected function _getScriptPath( $scriptName = null, $language = ScriptLanguages::JAVASCRIPT, $userScript = false )
    {
        return
            ( $userScript ? $this->_userScriptPath : $this->_scriptPath ) .
            '/' .
            trim( $scriptName ?: $this->_resourceId, '/ ' ) .
            '.' .
            $language;
    }

    /**
     * @param int $scriptTimeout
     */
    public static function setScriptTimeout( $scriptTimeout )
    {
        script::$_scriptTimeout = $scriptTimeout;
    }

    /**
     * @return int
     */
    public static function getScriptTimeout()
    {
        return script::$_scriptTimeout;
    }

    /**
     * @param string $scriptPath
     *
     * @return Script
     */
    public function setScriptPath( $scriptPath )
    {
        $this->_scriptPath = $scriptPath;

        return $this;
    }

    /**
     * @return string
     */
    public function getScriptPath()
    {
        return $this->_scriptPath;
    }

    /**
     * @return string
     */
    public function getUserScriptPath()
    {
        return $this->_userScriptPath;
    }

    /**
     * @param string $userScriptPath
     *
     * @return Script
     */
    public function setUserScriptPath( $userScriptPath )
    {
        $this->_userScriptPath = $userScriptPath;

        return $this;
    }

}
