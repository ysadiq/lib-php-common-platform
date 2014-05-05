<?php
/**
 * EventObserver.php
 *
 * @copyright Copyright (c) 2014 DreamFactory Software, Inc.
 * @link      DreamFactory Software, Inc. <http://www.dreamfactory.com>
 * @package   web-csp
 * @filesource
 */
namespace DreamFactory\Events\Interfaces;

use DreamFactory\Platform\Events\EventDispatcher;
use DreamFactory\Platform\Events\PlatformEvent;

/**
 * EventObserverLike
 * Something that likes to listen in on events
 */
interface EventObserverLike
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Process
     *
     * @param string          $eventName  The name of the event
     * @param PlatformEvent   $event      The event that occurred
     * @param EventDispatcher $dispatcher The source dispatcher
     *
     * @return mixed
     */
    public function handleEvent( $eventName, &$event = null, $dispatcher = null );
}
