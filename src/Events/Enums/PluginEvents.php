<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Events\Enums;

/**
 * The base events raised by the plugin system
 */
class PluginEvents extends PlatformEvents
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string
     */
    const PLUGIN_INSTALLED = 'plugin.installed';
    /**
     * @type string
     */
    const PLUGIN_UNINSTALLED = 'plugin.uninstalled';
    /**
     * @type string
     */
    const PLUGIN_ENABLED = 'plugin.enabled';
    /**
     * @type string
     */
    const PLUGIN_DISABLED = 'plugin.disabled';
    /**
     * @type string
     */
    const PLUGIN_LOADED = 'plugin.loaded';
    /**
     * @type string
     */
    const PLUGIN_ALL_LOADED = 'plugin.all_loaded';
}
