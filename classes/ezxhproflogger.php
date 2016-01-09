<?php
/**
 * A helper class for starting/stopping XHProf profiling.
 * XHProf requires pecl/xhprof-beta PECL package (and graphviz application for results graphs)
 *
 * @author M. Romanovsky
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2016
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZXHProfLogger /*extends XHProfRuns_Default*/
{
    /// @todo make this variable come from an ini file
    static protected $logdir = 'var/log/xhprof';
    static protected $profilingRunning = false;
    static protected $runs = array();

    /**
     * Starts XHPRof profiling
     *
     * @return bool
     *
     */
    static public function start( $flags = 0, $options = array() )
    {
        if ( !extension_loaded( 'xhprof' ) )
        {
            eZPerfLoggerDebug::writeWarning( 'Extension xhprof not loaded, can not start profiling', __METHOD__ );
            return false;
        }
        xhprof_enable( $flags + XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY, $options );
        self::$profilingRunning = true;
        return true;
    }

    /**
     * Stops XHProf profiling and saves profile data in var/log/xhprof
     *
     * @return mixed false|string (the run_id)|true (when nosave==true)
     */
    static public function stop( $dosave=true )
    {
        if ( !extension_loaded( 'xhprof' ) )
        {
            eZPerfLoggerDebug::writeWarning( 'Extension xhprof not loaded, can not stop profiling', __METHOD__ );
            return false;
        }

        if ( !self::$profilingRunning )
        {
            return false;
        }

        $xhprofData = xhprof_disable();
        self::$profilingRunning = false;

        if ( !$dosave )
        {
            return true;
        }

        if ( !is_dir( self::$logdir ) )
        {
            mkdir( self::$logdir );
        }
        $logger = new XHProfRuns_Default( self::$logdir );
        $runId = $logger->save_run( $xhprofData, "xhprof" );
        if ( $runId )
        {
            // beside profiling data, save extra info in another file to make it more useful later
            file_put_contents( self::$logdir . "/$runId.info", eZPerfLoggerApacheLogger::apacheLogLine( 'combined' ) );
            self::$runs[] = $runId;
        }
        return $runId;
    }

    static public function logDir()
    {
        return self::$logdir;
    }

    static public function isRunning()
    {
        return self::$profilingRunning;
    }

    /**
    * Returns the list of (terminated and saved) profiling runs
    * @return array
    */
    static public function runs()
    {
        return self::$runs;
    }

    /**
     * Returns list of saved runs
     * @return array
     *     'list' => array( '<runkey>' => array( 'time' => <run timestamp>, ... ) )
     *     'count' => total available runs
     */
    static public function savedRuns( $offset = 0, $limit = 0 )
    {
        $runsList = array();
        $count = 0;

        if ( !is_dir( self::logDir() ) )
        {
            return array( $runsList, $count );
        }

        // nb: PHP_SCANDIR_SORT_DESCENDING === 1, but only defined since php 5.4.0
        foreach( scandir( self::logDir(), 1 ) as $file )
        {
            $fullfile = self::logDir() . "/" . $file;
            if ( is_file( $fullfile ) && substr( $file, -7 ) == '.xhprof' )
            {
                $count++;
                if ( $count >= $offset  && ( $limit <= 0 || count( $runsList ) <= $limit ) )
                {
                    $run = substr( $file, 0, -7 );
                    $runsList[$run] = array(
                        // start out by taking run time as file time (if info file is there, itwill overwrite this value)
                        'time' => filemtime( $fullfile )
                    );
                    if ( is_file( $infoFile = self::logDir() . "/$run.info" ) )
                    {
                        if ( is_array( $info = eZPerfLoggerApacheLogger::parseLogLine( file_get_contents( $infoFile ) ) ) )
                        {
                            $runsList[$run] = $info;
                        }
                    }
                }
            }
        }
        return array( $runsList, $count );
    }

    /**
     * @todo allow to have a variable passed so that we can keep data younger than X seconds
     */
    static public function removeSavedRuns()
    {
        foreach( scandir( self::logDir(), 1 ) as $file )
        {
            $fullfile = self::logDir() . "/" . $file;
            if ( is_file( $fullfile ) && ( substr( $file, -7 ) == '.xhprof' || substr( $file, -5 ) == '.info' ) )
            {
                unlink( $fullfile );
            }
        }
    }
}
