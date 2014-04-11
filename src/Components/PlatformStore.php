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

use DreamFactory\Common\Components\DataCache;
use DreamFactory\Platform\Interfaces\PersistentStoreLike;
use Kisma\Core\Enums\HashType;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Option;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * A simple store class that use's the Kisma store
 */
class PlatformStore extends ParameterBag implements PersistentStoreLike
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string The ID of this store
     */
    protected $_storeId;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Constructor.
     *
     * @param string $storeId
     * @param array  $data An array of key value pairs with which to initialize storage
     */
    public function __construct( $storeId = null, array $data = array() )
    {
        $this->_storeId = Option::get( $data, 'store_id', $storeId ) ? : $this->_buildStoreId();

        parent::__construct( $data );
    }

    /**
     * Make sure we flush the cache upon destruction
     */
    public function __destruct()
    {
        //  Close it up
        $this->flush();
    }

    /**
     * {@InheritDoc}
     */
    public function open()
    {
        DataCache::load( $this->_storeId, $this->all() );
    }

    /**
     * {@InheritDoc}
     */
    public function close()
    {
        DataCache::store( $this->_storeId, $this->all() );
    }

    /**
     * {@InheritDoc}
     */
    public function flush()
    {
        //  Closing, flushing: same thing here...
        $this->close();
    }

    /**
     * {@InheritDoc}
     */
    public function reset( $valuesOnly = false )
    {
        if ( !$valuesOnly )
        {
            $this->replace( array() );

            return;
        }

        foreach ( $this->all() as $_key => $_value )
        {
            $this->set( $_key, null );
        }
    }

    /**
     * @param string $storeId Hashes a store tag string into something unique enough
     *
     * @return string
     */
    protected function _buildStoreId( $storeId = null )
    {
        $_id = $storeId
            ? : (
                PHP_SAPI . '.' .
                Option::server( 'REMOTE_ADDR', gethostname() ) . '.' .
                Option::server( 'HTTP_HOST', gethostname() )
            );

        return Hasher::hash( $_id, 'sha256' );
    }

    /**
     * @return string
     */
    public function getStoreId()
    {
        return $this->_storeId;
    }

    /**
     * @param string $storeId
     *
     * @throws \InvalidArgumentException
     * @return PlatformStore
     */
    public function setStoreId( $storeId )
    {
        if ( empty( $storeId ) )
        {
            throw new \InvalidArgumentException( 'The $storeId cannot be empty.' );
        }

        $this->_storeId = $this->_buildStoreId( $storeId );

        return $this;
    }

}
