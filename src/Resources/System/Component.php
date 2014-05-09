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
namespace DreamFactory\Platform\Resources\System;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Utility\FileUtilities;
use DreamFactory\Platform\Utility\Packager;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Option;

/**
 * Component
 * DSP system administration manager
 *
 */
class Component extends BaseSystemRestResource
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param \DreamFactory\Platform\Services\BasePlatformService $consumer
     * @param array                                               $resources
     */
    public function __construct( $consumer, $resources = array() )
    {
        $_config = array(
            'service_name' => 'system',
            'name'         => 'Component',
            'api_name'     => 'component',
            'type'         => 'System',
            'type_id'      => PlatformServiceTypes::SYSTEM_SERVICE,
            'description'  => 'System component administration.',
            'is_active'    => true,
        );

        parent::__construct( $consumer, $_config, $resources );
    }



    /**
     * @return array|bool
     * @throws \Exception
     */
    protected function _handlePost()
    {


        //	You can import an component package file, local or remote, but nothing else
        $_importUrl = FilterInput::request( 'url' );

        // Check for an import url
        if ( !empty( $_importUrl ) )
        {
            // get the extension and make sure it's lowercase
            $_extension = strtolower( pathinfo( $_importUrl, PATHINFO_EXTENSION ) );

            Log::debug('Import URL');
            // Is the extension '.dfpkg'
            if ( 'dfpkg' == $_extension )
            {
                // It is.
                // Set a var to hold the file
                $_filename = null;

                // Let's try getting the file
                try
                {
                    // need to download and extract zip file and move contents to storage
                    $_filename = FileUtilities::importUrlFileToTemp( $_importUrl );
                    $_results = Packager::importComponentFromPackage( $_filename, $_importUrl );
                }

                    // There was an error.  Let's clean everything up.
                catch ( \Exception $ex )
                {
                    // Was there a file stored
                    if ( !empty( $_filename ) )
                    {
                        // Yes.  Delete it.
                        unlink( $_filename );
                    }

                    // THrow the error.
                    throw new InternalServerErrorException( "Failed to import component package $_importUrl.\n{$ex->getMessage()}" );
                }


                // We're done getting the file.  We imoprted the package successfully.
                if ( !empty( $_filename ) )
                {
                    // clean it up.
                    unlink( $_filename );
                }
            }
            else
            {
                // The file extension was not correct.  Throw error
                throw new BadRequestException( "Only component package files ending with 'dfpkg' are allowed for import." );
            }
        }


        // We're uploading this package file directly.
        elseif ( null !== ( $_files = Option::get( $_FILES, 'files' ) ) )
        {

            //	Older html multi-part/form-data post, single or multiple files
            if ( is_array( $_files['error'] ) )
            {

                throw new \Exception( "Only a single component package file is allowed for import." );
            }

            // was there an error uploading the file
            if ( UPLOAD_ERR_OK !== ( $_error = $_files['error'] ) )
            {
                // there was.  throw error.
                throw new InternalServerErrorException( 'Failed to receive upload of "' . $_files['name'] . '": ' . $_error );
            }

            // Temp place to hold uploaded files
            $_filename = $_files['tmp_name'];

            // Get the extension
            $_extension = strtolower( pathinfo( $_files['name'], PATHINFO_EXTENSION ) );

            // is it a 'dfpkg'
            if ( 'dfpkg' == $_extension )
            {
                try
                {
                    // run the importer
                    $_results = Packager::importComponentFromPackage( $_filename );
                }
                catch ( \Exception $ex )
                {
                    // Error with importer packager thingy
                    throw new InternalServerErrorException( "Failed to import component package " . $_files['name'] . "\n{$ex->getMessage()}" );
                }
            }
            else
            {
                // the extension was wrong.
                // throw error
                throw new BadRequestException( "Only component package files ending with 'dfpkg' are allowed for import." );
            }
        }
        else
        {

            // for some reason let the parent handle all other stuff
            $_results = parent::_handlePost();
        }


        // I'm assuming this handles some init data
        $_records = Option::get( $_results, 'record' );

        // Do we have records
        /*       if ( empty( $_records ) )
               {
                   // It's one record.
                   static::initHostedAppStorage( $_results );
               }
               else
               {

                   // it's more than one.  Loop through.
                   foreach ( $_records as $_record )
                   {
                       static::initHostedAppStorage( $_record );
                   }
               }*/

        // Obvious :)
        return $_results;
    }
}