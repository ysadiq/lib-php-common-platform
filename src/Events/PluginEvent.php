<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
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
namespace DreamFactory\Platform\Events;

/**
 * PluginEvent
 * Encapsulates the events thrown by the plugin system
 */
class PluginEvent extends PlatformEvent
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var PluginLike
     */
    protected $_plugin = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param PluginLike $plugin
     * @param array      $eventData
     */
    public function __construct( PluginLike $plugin, $eventData = array() )
    {
        parent::__construct( $eventData );

        $this->_plugin = $plugin;
    }

    /**
     * @return PluginLike
     */
    public function getPlugin()
    {
        return $this->_plugin;
    }

}