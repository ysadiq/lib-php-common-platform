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

use DreamFactory\Platform\Enums\DataFormats;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Utility\Platform;
use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;

/**
 * Script.php
 * Script service
 */
class Script extends BasePlatformRestService
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /** @type string */
    const DEFAULT_SCRIPT_PATH = '/config/scripts';
    /** @type string */
    const DEFAULT_SCRIPT_PATTERN = '/*.js';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string The path to script storage area
     */
    protected $_scriptPath = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct( $settings = array() )
    {
        //	Pull out our settings before calling daddy
        $_settings = array_merge(
            array(
                'name'          => 'Script',
                'description'   => 'A sandboxed script management service.',
                'api_name'      => 'script',
                'type_id'       => PlatformServiceTypes::SYSTEM_SERVICE,
                'is_active'     => true,
                'native_format' => DataFormats::NATIVE,
            ),
            $settings
        );

        parent::__construct( $_settings );

        $this->_scriptPath = Platform::getPrivatePath( static::DEFAULT_SCRIPT_PATH );
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _listResources()
    {
        if ( empty( $this->_scriptPath ) )
        {
            throw new BadRequestException( 'The storage path for scripts has not yet been configured.' );
        }

        return FileSystem::glob( $this->_scriptPath . static::DEFAULT_SCRIPT_PATTERN, GlobFlags::GLOB_NODOTS );
    }

    protected function _handleGet()
    {
        if ( empty( $this->_resource ) )
        {
            return $this->_listResources();
        }

        if ( empty( $this->_scriptPath ) )
        {
            throw new BadRequestException( 'The storage path for scripts has not yet been configured.' );
        }

        $_path = $this->_scriptPath . '/' . trim( $this->_resource, '/ ' ) . '.js';

        if ( !file_exists( $_path ) )
        {
            throw new NotFoundException( 'The script "' . $this->_resource . '" was not found.' );
        }

        $_body = @file_get_contents( $_path );

        return array( 'script_id' => $this->_resource, 'script_body' => $_body );
    }

    protected function _handlePut()
    {
        if ( empty( $this->_resource ) )
        {
            return $this->_listResources();
        }

        if ( empty( $this->_scriptPath ) )
        {
            throw new BadRequestException( 'The storage path for scripts has not yet been configured.' );
        }

        $_path = $this->_scriptPath . '/' . trim( $this->_resource, '/ ' ) . '.js';

        $_scriptId = Options::get( $this->_requestPayload, 'script_id' );
        $_scriptBody = Options::get( $this->_requestPayload, 'script_body' );

        Log::debug( print_r( $this->_requestPayload, true ) );

        if ( empty( $_scriptId ) || empty( $_scriptBody ) )
        {
            throw new BadRequestException( 'You must supply both a "script_id" and a "script_body".' );
        }

        $_body = @file_get_contents( $_path );

        return array( 'script_id' => $this->_resource, 'script_body' => $_body );
    }

    protected function _handlePost()
    {
        if ( !extension_loaded( 'v8js' ) )
        {
            throw new InternalServerErrorException  ( 'This system does not support server-side scripts.' );
        }

        $_runner = new \V8Js();
        $_data = array( 'a' => 1, 'b' => 2, 'c' => 3 );
        $_runner->request = $_data;

        $_script = <<< EOT
print( PHP.request.a);
EOT;

        try
        {
            //  Don't show output
            ob_start();
            $_runner->executeString( $_script, $this->_resource . '.js' );
            $_result = ob_get_clean();

            return array( 'response' => $_result );
        }
        catch ( \V8JsException $_ex )
        {
            ob_end_clean();
            Log::error( 'Exception executing javascript: ' . $_ex->getMessage() );
            throw new InternalServerErrorException( $_ex->getMessage() );
        }

    }
}
