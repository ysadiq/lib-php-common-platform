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

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\FilesystemCache;
use DreamFactory\Platform\Interfaces\StoreLike;
use DreamFactory\Platform\Utility\Platform;
use Kisma\Core\Components\Flexistore;
use Kisma\Core\Utility\Hasher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A store for the platform event system
 */
class EventStore extends Flexistore implements StoreLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const CACHE_NAMESPACE = 'dsp.event_store';
    /**
     * @type string
     */
    const CACHE_PATH = PlatformStore::STORE_CACHE_PATH;
    /**
     * @type string
     */
    const CACHE_EXTENSION = '.dfec';
    /**
     * @var CacheProvider
     */
    protected $_store = null;
    /**
     * @var string
     */
    protected $_storeId = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Constructor.
     *
     * @param string                                                      $storeId The ID to assign this store
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct( $storeId, EventDispatcherInterface $dispatcher )
    {
        $this->_store = new FilesystemCache(
            Platform::getPrivatePath( static::CACHE_PATH ), static::CACHE_EXTENSION
        );

        $this->_store->setNamespace( static::CACHE_NAMESPACE );

        //  Freshen up
        $this->_storeId = $storeId ? : spl_object_hash( $dispatcher );
        $this->flush( $dispatcher );
    }

    /**
     * Hashes the key for this store
     *
     * @param string $id
     *
     * @return string
     */
    protected function _obscureKey( $id )
    {
        return hash( 'sha256', parent::_obscureKey( $id ) );
    }

    /**
     * Deletes all items from the store
     *
     * @return bool
     */
    public function deleteAll()
    {
        return $this->_store->deleteAll();
    }
}
