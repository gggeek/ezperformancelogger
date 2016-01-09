<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2014-2016
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLoggerContentPublishingProcess extends ezpContentPublishingProcess
{
    /**
     * Make sure that eZPersistentObject can build instances of this class
     * @return array
     */
    static public function definition()
    {
        $definition = parent::definition();
        $definition['class_name'] = 'eZPerfLoggerContentPublishingProcess';
        return $definition;
    }

    public function publish()
    {
        $contentObjectId = $this->version()->attribute( 'contentobject_id' );
        $contentObjectVersion = $this->version()->attribute( 'version' );

        // $processObject = ezpContentPublishingProcess::fetchByContentObjectVersion( $contentObjectId, $contentObjectVersion );
        $this->setAttribute( 'status', self::STATUS_WORKING );
        $this->store( array( 'status' ) );

        // prepare the cluster file handler for the fork
        eZClusterFileHandler::preFork();

        $pid = pcntl_fork();

        // force the DB connection closed
        $db = eZDB::instance();
        $db->close();
        $db = null;
        eZDB::setInstance( null );

        // Force the cluster DB connection closed if the cluster handler is DB based
        $cluster = eZClusterFileHandler::instance();

        // error, cancel
        if ( $pid == -1 )
        {
            $this->setAttribute( 'status', self::STATUS_PENDING );
            $this->store( array( 'status' ) );
            return false;
        }
        else if ( $pid )
        {
            return $pid;
        }

        // child process
        try
        {
            $myPid = getmypid();
            pcntl_signal( SIGCHLD, SIG_IGN );

            $this->setAttribute( 'pid', $myPid );
            $this->setAttribute( 'started', time() );
            $this->store( array( 'pid', 'started' ) );

            ezpContentPublishingQueue::signals()->emit( 'preHandling', $contentObjectId, $contentObjectVersion, $myPid );

            // login the version's creator to make sure publishing happens as if ran synchronously
            $creatorId = $this->version()->attribute( 'creator_id' );
            $creator = eZUser::fetch( $creatorId );
            eZUser::setCurrentlyLoggedInUser( $creator, $creatorId );
            unset( $creator, $creatorId );

            $operationResult = eZOperationHandler::execute( 'content', 'publish',
                array( 'object_id' => $contentObjectId, 'version' => $contentObjectVersion  ) );

            // Statuses other than CONTINUE require special handling
            if ( $operationResult['status'] != eZModuleOperationInfo::STATUS_CONTINUE )
            {
                if ( $operationResult['status'] == eZModuleOperationInfo::STATUS_HALTED )
                {
                    // deferred to crontab
                    if ( strpos( $operationResult['result']['content'], 'Deffered to cron' ) !== false )
                        $processStatus = self::STATUS_DEFERRED;
                    else
                        $processStatus = self::STATUS_UNKNOWN;
                }
                else
                {
                    $processStatus = self::STATUS_UNKNOWN;
                }
            }
            else
            {
                $processStatus = self::STATUS_FINISHED;
            }

            // mark the process as completed
            $this->setAttribute( 'pid', 0 );
            $this->setAttribute( 'status', $processStatus );
            $this->setAttribute( 'finished', time() );
            $this->store( array( 'status', 'finished', 'pid' ) );

            // Call the postProcessing hook
            ezpContentPublishingQueue::signals()->emit( 'postHandling', $contentObjectId, $contentObjectVersion, $processStatus );
        }
        catch( eZDBException $e )
        {
            $this->reset();
        }
        eZScript::instance()->shutdown();
        exit;
    }

}