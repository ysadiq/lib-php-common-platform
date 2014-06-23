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
namespace DreamFactory\Platform\Interfaces;

/**
 * Something that can execute scripts
 */
interface ScriptingEngineLike
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Handle setup for global/all instances of engine
     *
     * @param array $options
     *
     * @return mixed
     */
    public static function startup( $options = null );

    /**
     * Process a single script
     *
     * @param string $script          The string to execute
     * @param string $scriptId        A string identifying this script
     * @param array  $eventInfo       An array of information about the event triggering this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @internal param string $eventName
     * @internal param \DreamFactory\Platform\Events\PlatformEvent $event
     * @internal param \DreamFactory\Platform\Events\EventDispatcher $dispatcher
     * @return mixed
     */
    public function executeString( $script, $scriptId, $eventInfo, array $engineArguments = array() );

    /**
     * Process a single script
     *
     * @param string $script          The path/to/the/script to read and execute
     * @param string $scriptId        A string identifying this script
     * @param array  $eventInfo       An array of information about the event triggering this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    public function executeScript( $script, $scriptId, $eventInfo, array $engineArguments = array() );

    /**
     * Handle cleanup for global/all instances of engine
     *
     * @return mixed
     */
    public static function shutdown();
}
