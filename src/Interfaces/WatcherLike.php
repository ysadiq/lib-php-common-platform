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

use DreamFactory\Platform\Enums\INotify;

/**
 * Something that acts like a watcher
 */
interface WatcherLike
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Checks to see if this service is available
     *
     * @throws \DreamFactory\Platform\Exceptions\UnavailableExtensionException
     * @return bool
     */
    public static function available();

    /**
     * Watch a file/path
     *
     * @param string $path
     * @param int    $mask
     * @param bool   $overwrite If true, the mask will be added to any existing mask on the path. If false, the $mask
     *                          will replace an existing watch mask.
     *
     * @return int The ID of this watch
     */
    public function watch( $path, $mask = INotify::IN_ATTRIB, $overwrite = false );

    /**
     * Stop watching a file/path
     *
     * @param string $path The path to stop watching
     *
     * @return bool
     */
    public function unwatch( $path );

    /**
     * Checks the stream and fires appropriate events
     *
     * @param bool $trigger If true (default), a DspEvents::STORAGE_CHANGE event is fired
     *
     * @return array The array of triggered events
     */
    public function checkForEvents( $trigger = true );
}
