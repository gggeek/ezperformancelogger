<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLoggerContentPublishingQueue extends ezpContentPublishingQueue
{
    /**
     * InitHooks being "final", we work around it by way of a new function
     */
    public static function init()
    {
        parent::init();
        self::initExtraHooks();
    }

    protected final static function initExtraHooks()
    {
        if ( isset( $initExtra ) )
            return;

        static $initExtra = true;

        $ini = eZINI::instance( 'content.ini' );

        self::attachHooks( 'preHandling', $ini->variable( 'PublishingSettings', 'AsynchronousPublishingPreHandlingHooks' ) );
    }

    /**
     * Reimplemented, so that
     * - we give back a different type of item to the queue processor
     * - we only allow 1 concurrent publication per-object at a time
     */
    public static function next()
    {
        $queuedProcess = eZPersistentObject::fetchObjectList( eZPerfLoggerContentPublishingProcess::definition(),
            null,
            array( 'status' => ezpContentPublishingProcess::STATUS_PENDING ),
            array( 'created' => 'desc' ),
            array( 'offset' => 0, 'length' => 1 )
        );

        if ( count( $queuedProcess ) == 0 )
            return false;
        else
        {
            // check if another instance is being published of the same object
            $db = eZDB::instance();
            $processing = $db->arrayQuery( "SELECT count(*) AS other" .
                "FROM ezpublishingqueueprocesses p, ezcontentobject_version v " .
                "WHERE p.ezcontentobject_version_id = v.id AND p.status = 1 AND v.contentobject_id IN ( " .
                "SELECT contentobject_id FROM ezcontentobject_version v2 WHERE id = " . $queuedProcess[0]->attribute('ezcontentobject_version_id') . " )"
            );
            if ( $processing[0]['other'] )
            {
                return false;
            }
            return $queuedProcess[0];
        }
    }
}