<?php
/**
 * A helper class for starting/stopping XHProf profiling.
 * XHProf requires pecl/xhprof-beta PECL package (and graphviz application for results graphs)
 *
 * @author M. Romanovsky
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
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
            eZDebug::writeWarning( 'Extension xhprof not loaded, can not start profiling', __METHOD__ );
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
            eZDebug::writeWarning( 'Extension xhprof not loaded, can not stop profiling', __METHOD__ );
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
            file_put_contents( self::$logdir . "/$runId.info", eZPerfLogger::apacheLogLine( 'combined' ) );
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
}
