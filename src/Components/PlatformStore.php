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
namespace DreamFactory\Platform\Components;

use DreamFactory\Platform\Utility\Platform;
use Kisma\Core\Components\Flexistore;
use Kisma\Core\Enums\CacheTypes;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * A simple store class that use's the Kisma store
 * @method bool setNamespace( string $namespace )
 *
 * @deprecated in v1.7.11, to be removed in v2.0.0
 */
class PlatformStore extends Flexistore
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const DEFAULT_NAMESPACE = 'df.platform';
    /**
     * @type string
     */
    const STORE_CACHE_PATH = '/app.store';
    /**
     * @type string The session key
     */
    const PERSISTENT_STORAGE_KEY = 'df.session_key';
    /**
     * @type int Platform store items only live for five minutes
     */
    const DEFAULT_TTL = 300;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Constructor.
     *
     * @param array|string $type
     * @param array        $data An array of key value pairs with which to initialize storage
     */
    public function __construct( $type = CacheTypes::FILE_SYSTEM, array $data = array() )
    {
        $_storePath = Platform::getPrivatePath( static::STORE_CACHE_PATH );
        Log::debug( 'Private cache path set to: ' . $_storePath );

        parent::__construct(
            $type,
            array(
                'namespace' => static::DEFAULT_NAMESPACE,
                'arguments' => array($_storePath, '.df'),
            ),
            false
        );

        //  Load it up
        foreach ( $data as $_key => $_value )
        {
            $this->_store->save( $_key, $_value, static::DEFAULT_TTL );
        }
    }

    /**
     * @param string $addendum Additional data to add to key
     *
     * @return string
     */
    public static function buildCacheKey( $addendum = null )
    {
        $_key =
            PHP_SAPI .
            '.' .
            Option::server( 'REMOTE_ADDR', gethostname() ) .
            '.' .
            Option::server( 'HTTP_HOST', gethostname() ) .
            ( $addendum ? '.' . $addendum : null );

        return $_key;
    }
}
