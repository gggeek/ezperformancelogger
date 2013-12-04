<?php
/**
 * Extends the eZDFSFileHandler class by adding tracing points
 */

class eZDFSTracing44FileHandler extends eZDFSFileHandler
{

    /**
    * Same code as parent, with one extra eZPerfLogger::accumulatorStart/Stop call
    */
    function processCache( $retrieveCallback, $generateCallback = null, $ttl = null, $expiry = null, $extraData = null )
    {
        $forceDB   = false;
        $timestamp = null;
        $curtime   = time();
        $tries     = 0;
        $noCache   = false;

        if ( $expiry < 0 )
            $expiry = null;
        if ( $ttl < 0 )
            $ttl = null;

        // Main loop
        while ( true )
        {
            // Start read checks
            // Note: The while loop is used to make it easier to break out of the "read" code
            while ( true )
            {
                // No retrieve method so go directly to generate+store
                if ( $retrieveCallback === null || !$this->filePath )
                    break;

                if ( !self::LOCAL_CACHE )
                {
                    $forceDB = true;
                }
                else
                {
                    if ( $this->isLocalFileExpired( $expiry, $curtime, $ttl ) )
                    {
                        // if we are in stale cache mode, we only forceDB if the
                        // file does not exist at all
                        if ( $this->useStaleCache )
                        {
                            if ( !file_exists( $this->filePath ) )
                            {
                                eZDebugSetting::writeDebug( 'kernel-clustering', "Local file '{$this->filePath}' does not exist and can not be used for stale cache. Checking with DB", __METHOD__ );
                                $forceDB = true;

                                // forceDB + useStaleCache means that we should check for the DB file.
                            }
                        }
                        else
                        {
                            // Local file is older than global timestamp, check with DB
                            eZDebugSetting::writeDebug( 'kernel-clustering', "Local file (mtime=" . @filemtime( $this->filePath ) . ") is older than timestamp ($expiry) and ttl($ttl), check with DB", __METHOD__ );
                            $forceDB = true;
                        }
                    }
                }

                if ( !$forceDB )
                {
                    // check if DB file is deleted
                    if ( !$this->useStaleCache && ( $this->metaData === false || $this->metaData['mtime'] < 0 ) )
                    {
                        if ( $generateCallback !== false )
                            eZDebugSetting::writeDebug( 'kernel-clustering', "Database file is deleted, need to regenerate data", __METHOD__ );
                        else
                            eZDebugSetting::writeDebug( 'kernel-clustering', "Database file is deleted, cannot get data", __METHOD__ );
                        break;
                    }

                    // check if FS file is older than DB file
                    if ( !$this->useStaleCache && $this->isLocalFileExpired( $this->metaData['mtime'], $curtime, $ttl ) )
                    {
                        eZDebugSetting::writeDebug( 'kernel-clustering', "Local file (mtime=" . @filemtime( $this->filePath ) . ") is older than DB, checking with DB", __METHOD__ );
                        $forceDB = true;
                    }
                    else
                    {
                        if ( $this->useStaleCache )
                        {
                            // to get the retrieve callback to accept the cache file,
                            // we force its mtime to the current time
                            $mtime = $curtime;
                            eZDebugSetting::writeDebug( 'kernel-clustering', "Processing local stale cache file {$this->filePath}", __METHOD__ );
                        }
                        else
                        {
                            $mtime = filemtime( $this->filePath );
                            eZDebugSetting::writeDebug( 'kernel-clustering', "Processing local cache file {$this->filePath}", __METHOD__ );
                        }

                        $args = array( $this->filePath, $mtime );
                        if ( $extraData !== null )
                            $args[] = $extraData;
                        $retval = call_user_func_array( $retrieveCallback, $args );
                        if ( $retval instanceof eZClusterFileFailure )
                        {
                            break;
                        }
                        return $retval;
                    }
                }

                if ( $forceDB )
                {
                    // stale cache, and no DB or FS file available
                    if ( $this->useStaleCache && $this->metaData === false )
                    {
                        // configuration says we have to generate our own version
                        if ( $this->nonExistantStaleCacheHandling[ $this->cacheType ] == 'generate' )
                        {
                            // no cache available, but a generate callback exists, skip to generation
                            if ( $generateCallback !== false )
                            {
                                eZDebugSetting::writeDebug( 'kernel-clustering', "Database file is deleted, need to regenerate data" );
                                break;
                            }
                            // if no generate callback exists, we can directly skip the main loop
                            else
                            {
                                eZDebugSetting::writeDebug( 'kernel-clustering', "Database file is deleted, cannot get data" );
                                break 2;
                            }
                        }
                        // wait for the generating process to be finished (or timedout)
                        else
                        {
                            eZPerfLogger::accumulatorStart( 'mysql_cluster_cache_waits', 'MySQL Cluster', 'Cache waits' );
                            while ( $this->remainingCacheGenerationTime-- >= 0 )
                            {
                                // we don't know if the file gets generated on the current
                                // frontend or not. However, we can still try the FS cache
                                // first, then the DB cache if FS is not found, since this
                                // will be much more efficient
                                if ( !file_exists( $this->filePath ) )
                                {
                                    $this->loadMetaData( true );
                                    if ( $this->metaData === false )
                                    {
                                        sleep( 1 );
                                        continue;
                                    }
                                    else
                                    {
                                        break;
                                    }
                                }
                                else
                                {
                                    break;
                                }
                            }
                            eZPerfLogger::accumulatorStop( 'mysql_cluster_cache_waits' );
                            // if we reached this point, it means that we are over the estimated timeout value
                            // we try to take the generation over by starting the cache generation. IF this
                            // fails again, it is probably because another waiting process has taken the generation
                            // over. Maybe add a counter here to prevent some kind of death loop ?
                            eZDebugSetting::writeDebug( 'kernel-clustering', "Checking if {$this->filePath} was generating during the wait loop", __METHOD__ );
                            $this->loadMetaData( true );
                            $this->useStaleCache = false;
                            $this->remainingCacheGenerationTime = false;
                            $forceDB = false;

                            // this continues to the main loop 'while (true)'
                            continue 2;
                        }
                    }
                    // no stale cache, and expired DB file
                    elseif ( !$this->useStaleCache && ( $this->metaData === false || $this->isDBFileExpired( $expiry, $curtime, $ttl ) ) ) // no stalecache, and no DB file, generation is required
                    {
                        // no cache available, but a generate callback exists, skip to generation
                        if ( $generateCallback !== false )
                        {
                            eZDebugSetting::writeDebug( 'kernel-clustering', "Database file is deleted, need to regenerate data", __METHOD__ );

                            // we break out of one loop so that the generateCallback is called
                            break;
                        }
                        // if no generate callback exists, we can directly skip the main loop
                        else
                        {
                            eZDebugSetting::writeDebug( 'kernel-clustering', "Database file is deleted, cannot get data", __METHOD__ );

                            // we break out of two loops so that we directly exit the method and have
                            // the rest of execution generate the data
                            break 2;
                        }
                    }
                    else
                    {
                        eZDebugSetting::writeDebug( 'kernel-clustering', "Callback from DB file {$this->filePath}", __METHOD__ );
                        if ( self::LOCAL_CACHE )
                        {
                            $this->fetch();

                            // Figure out which mtime to use for new file, must be larger than
                            // mtime in DB at least.
                            $mtime = $this->metaData['mtime'] + 1;
                            $localmtime = @filemtime( $this->filePath );
                            $mtime = max( $mtime, $localmtime );
                            touch( $this->filePath, $mtime, $mtime );
                            clearstatcache(); // Needed because of touch() call

                            $args = array( $this->filePath, $mtime );
                            if ( $extraData !== null )
                                $args[] = $extraData;
                            $retval = call_user_func_array( $retrieveCallback, $args );
                            if ( $retval instanceof eZClusterFileFailure )
                            {
                                break;
                            }
                            return $retval;
                        }
                        else
                        {
                            $uniquePath = $this->fetchUnique();

                            $args = array( $uniquePath, $this->metaData['mtime'] );
                            if ( $extraData !== null )
                                $args[] = $extraData;
                            $retval = call_user_func_array( $retrieveCallback, $args );
                            $this->fileDeleteLocal( $uniquePath );
                            if ( $retval instanceof eZClusterFileFailure )
                                break;
                            return $retval;
                        }
                    }
                    eZDebugSetting::writeDebug( 'kernel-clustering', "Database file does not exist, need to regenerate data", __METHOD__ );
                    break;
                }
            }

            if ( $tries >= 2 )
            {
                eZDebugSetting::writeDebug( 'kernel-clustering', "Reading was retried $tries times and reached the maximum, returning null", __METHOD__ );
                return null;
            }

            // Generation part starts here
            if ( isset( $retval ) && $retval instanceof eZClusterFileFailure )
            {
                // This error means that the retrieve callback told
                // us NOT to enter generation mode and therefore NOT to store this
                // cache.
                // This parameter will then be passed to the generate callback,
                // and this will set store to false
                if ( $retval->errno() == 3 )
                {
                    $noCache = true;
                }

                // check for non-expiry error codes
                elseif ( $retval->errno() != 1 )
                {
                    eZDebug::writeError( "Failed to retrieve data from callback", __METHOD__ );
                    return null;
                }
                $message = $retval->message();
                if ( strlen( $message ) > 0 )
                {
                    eZDebugSetting::writeDebug( 'kernel-clustering', $retval->message(), __METHOD__ );
                }
                // the retrieved data was expired so we need to generate it, let's continue
            }

            // We need to lock if we have a generate-callback or
            // the generation is deferred to the caller.
            // Note: false means no generation, while null means that generation
            // is deferred to the processing that follows (f.i. cache-blocks)
            if ( $generateCallback !== false && $this->filePath )
            {
                if ( !$this->useStaleCache && !$noCache )
                {
                    $res = $this->startCacheGeneration();
                    if ( $res !== true )
                    {
                        eZDebugSetting::writeDebug( 'kernel-clustering', "{$this->filePath} is being generated, switching to staleCache mode", __METHOD__ );
                        $this->useStaleCache = true;
                        $this->remainingCacheGenerationTime = $res;
                        $forceDB = false;
                        continue;
                    }
                }

                // File in DB is outdated or non-existing, call write-callback to generate content
                if ( $generateCallback )
                {
                    $args = array( $this->filePath );
                    if ( $noCache )
                        $extraData['noCache'] = $noCache;
                    if ( $extraData !== null )
                        $args[] = $extraData;
                    $fileData = call_user_func_array( $generateCallback, $args );
                    return $this->storeCache( $fileData );
                }
            }

            break;
        } // End main loop

        return new eZClusterFileFailure( 2, "Manual generation of file data is required, calling storeCache is required" );
    }

    static public function measure()
    {
        return eZPerfLoggerGenericTracer::StdKPIsFromAccumulators( array(
                'mysql_cluster_cache_waits'
            ),  eZPerfLogger::TimeAccumulatorList()
        );
    }

    public static function supportedVariables()
    {
        return array(
            'mysql_cluster_cache_waits' => 'integer',
            'mysql_cluster_cache_waits_t' => 'float (secs, rounded to msec)',
            'mysql_cluster_cache_waits_tmax' => 'float (secs, rounded to msec)',
        );
    }
}
?>
