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
namespace DreamFactory\Platform\Plugins;

use DreamFactory\Platform\Resources\System\ProviderUser;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Events\SeedEvent;
use Kisma\Core\Interfaces\PublisherLike;
use Kisma\Core\Interfaces\SubscriberLike;

/**
 * PluginManager
 * Manages DSP plugins
 */
class PluginManager implements PublisherLike, SubscriberLike
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct( $settings = array() )
    {
    }

    /**
     * @param string    $eventName
     * @param SeedEvent $event
     *
     * @return PluginEvent
     */
    public function trigger( $eventName, $event = null )
    {
        //  If event data sent, create the event
        if ( $event && !( $event instanceof PluginEvent ) && is_array( $event ) )
        {
            $event = new PluginEvent( $this, $event );
        }

        return Pii::trigger( $eventName, $event );
    }

    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string   $eventName            The event to listen on
     * @param callable $listener             The listener
     * @param integer  $priority             The higher this value, the earlier an event
     *                                       listener will be triggered in the chain (defaults to 0)
     */
    public function on( $eventName, $listener, $priority = 0 )
    {
        Pii::on( $eventName, $listener, $priority );
    }

    /**
     * Turn off/unbind/remove $listener from an event
     *
     * @param string   $eventName
     * @param callable $listener
     *
     * @return void
     */
    public function off( $eventName, $listener )
    {
        Pii::off( $eventName, $listener );
    }
}
