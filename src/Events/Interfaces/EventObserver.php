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

/**
 * EventObserver
 * Something that likes to observe events
 */
interface EventObserver
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    public function dispatch();
}
