<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Used to hook up to asynchronous publishing events
 */
class eZPerfLoggerAsyncPubTracer
{
    /**
     * On the post-handling hook, we trigger logging of all recorded data.
     * We probably might just rely on eZPerfLogger::registerShutdownPerfLogger() instead, so that recording is done
     * a bit later in the execution cycle, which gives more accurate data
     *
     * @param $objectId
     * @param $objectVersion
     * @param $publicationStatus
     */
    public static function postHandlingHook( $objectId, $objectVersion, $publicationStatus )
    {
        eZPerfLogger::preoutput( '' );
    }

    /**
     * On the pre-handling hook, we need to reset all counters, otherwise we get data polluted from the daemon process
     *
     * @param $objectId
     * @param $objectVersion
     * @param $pid
     */
    public static function preHandlingHook( $objectId, $objectVersion, $pid )
    {
        eZPerfLogger::reset();
    }
}