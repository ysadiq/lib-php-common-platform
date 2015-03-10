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
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Platform\Scripting;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\CacheProvider;
use DreamFactory\Library\Utility\IfSet;
use Kisma\Core\Components\Flexistore;

/**
 * V8Js scripting session object
 */
class ScriptSession
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type int
     */
    const SCRIPT_SESSION_TTL = 15;

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type Flexistore|CacheProvider|Cache|\CCache
     */
    protected $_store;
    /**
     * @type array|mixed
     */
    protected $_data = array();

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * @param string                                 $id The session ID
     * @param Flexistore|CacheProvider|Cache|\CCache $store
     */
    public function __construct( $id, $store )
    {
        $this->_id = $id;
        $this->_store = $store;
        $this->_data = $store->get( sha1( $id ), array() );
    }

    /**
     * Destruction
     */
    public function __destruct()
    {
        $this->_store->set( sha1( $this->_id ), $this->_data, static::SCRIPT_SESSION_TTL );
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function set( $key, $value )
    {
        $this->_data[$key] = $value;
    }

    /**
     * @param      $key
     * @param null $defaultValue
     *
     * @return mixed
     */
    public function get( $key, $defaultValue = null )
    {
        return IfSet::get( $this->_data, $key, $defaultValue );
    }
}
