<?php
/**
 * The class implementing most of the logic of performance logging:
 * - it is registered as an 'output filter' class that does not filter anything, but triggers
 *   perf. data measurement and logging at the end of page execution
 * - for eZP 5.0 and later (LS only), where output filters are removed, the triggering of perf. data measurement
 *   and logging is done via the event system
 * - it implements the 2 interfaces that we use to divide the workflow in: provider, logger.
 *   In other words, it supports a lot of default KPIs and logging methods
 * - it also implements methods allowing other code to easily record measured perf data, and various utility functions
 *
 * @todo !important separate the logger, provider, utility parts in separate classes
 *
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2008-2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLogger implements eZPerfLoggerProvider, eZPerfLoggerLogger, eZPerfLoggerTimeMeasurer
{
    static protected $custom_variables = array();
    static protected $outputSize = null;
    static protected $returnCode = 200;
    static protected $has_run = false;
    static protected $activated = true;
    static protected $timeAccumulatorList = array();
    static protected $nodeId = null;

    /*** Methods available to php code wanting to record perf data without hassles ***/

    /**
     * Record a perf. value associated with a given variable name.
     * The value will then be logged if in the ezperflogger.ini file that variable
     * is set to be logged in TrackVars.
     * Note that $value should be an integer/float by preference, as some loggers
     * might have problems with strings containing spaces, commas or other special chars
     *
     * @param string $varName
     * @param mixed $value
     *
     * @deprecated it is now recommended to set up a class of your own as VariableProvider, and let the
     *             extension call its measure() method
     */
    static public function recordValue( $varName, $value )
    {
        self::$custom_variables[$varName] = $value;
    }

    /**
     * Record a list of values associated with a set of variables in a single call
     *
     * @see recordValue
     *
     * @deprecated it is now recommended to set up a class of your own as VariableProvider, and let the
     *             extension call its measure() method
     */
    static public function recordValues( array $vars )
    {
        foreach( $vars as $varName => $value )
        {
            self::$custom_variables[$varName] = $value;
        }
    }

    /*** Methods to be hooked up to eZ page execution flow ***/

    /**
     * This method is meant to be registered for execution at end of page execution. It does
     * the actual logging of the performance variables values according to the
     * configuration in ezperformancelogger.ini, as well as the xhprof profile
     * dumping.
     * When xhprof is enabled, it adds an html comment to page output which can be used to link to pages displaying
     * profiling information
     * (this method runs before debug output is added to it, so we can not add it there)
     */
    static public function filter( $output, $returnCode=null )
    {
        if ( self::$activated )
        {
            self::$has_run = true;

            // perf logging: measure variables and log them according to configuration
            $values = self::getValues( true, $output, $returnCode );
            self::logIfNeeded( $values, $output );

            // profiling
            if ( eZXHProfLogger::isRunning() )
            {
                eZXHProfLogger::stop();
            }
            if ( eZPerfLoggerINI::variable( 'XHProfSettings', 'AppendXHProfTag' ) == 'enabled' && $runs = eZXHProfLogger::runs() )
            {
                $xhtag = "<!-- XHProf runs: " . implode( ',', $runs ) . " -->";
                $output = preg_replace( "#</body>#", $xhtag . '</body>', $output, -1, $count );
                if ( $count == 0 )
                {
                    $output .= $xhtag;
                }
            }
        }
        return $output;
    }

    /**
     * This function can be called at the end of every page, including the ones
     * that end via redirect (and thus do not call "filter").
     * In order to do so, you need to call at some point in your controller
     *   eZExecution::addCleanupHandler( array( 'eZPerfLogger', 'cleanup' ) );
     * NB: it only fires once, even if called many times
     */
    static public function cleanup( $output='', $returnCode=null )
    {
        if ( !self::$has_run )
        {
            // nb: since the adding of this function as cleanup handler is not automatically
            // disabled just by disabling this extension in site.ini (whereas 'filter' and 'event' are),
            // we just check here if extension is enabled or not.
            if ( self::isEnabled() )
            {
                 self::filter( $output, $returnCode );
            }
        }
    }

    /**
     * This function can be registered as event handler for response/preoutput
     * (mandatory since ezp 5.0 LS and later, as OutputFilter has been removed).
     * NB: it only fires once, even if called many times
     */
    static public function preoutput( $output, $returnCode=null )
    {
        if ( !self::$has_run )
        {
            return self::filter( $output, $returnCode );
        }
        return $output;
    }

    /*** Other methods **/

    /**
     * Courtesy method to allow callers to go through cleanup() calls many times; helpful f.e. for eZP 5.x and proper
     * tracing of redirecting pages.
     * Note that this by default only disables collecting/displaying data, it does not disable measuring timing points.
     * If you really want not to measure or log anything, pass TRUE as parameter.
     */
    static public function disable( $deactivate=false )
    {
        self::$has_run = true;
        if ( $deactivate )
        {
            self::$activated = false;
        }
    }

    /**
     * Courtesy method to allow callers to go through cleanup() calls many times; needed for eZP 5.x and proper
     * tracing of redirecting pages
     */
    static public function reenable( $reactivate=false )
    {
        self::$has_run = false;
        if ( $reactivate )
        {
            self::$activated = true;
        }
    }

    /**
     * Returns true when extension is active
     * @todo fix to run from eZ5 context
     * @todo !important rename to isactive ;-)
     */
    static public function isEnabled()
    {
        /// @todo look if eZExtension or similar class already has similar code, as we miss ActiveAccessExtensions here
        return in_array( 'ezperformancelogger', eZPerfLoggerINI::variable( 'ExtensionSettings', 'ActiveExtensions', 'site.ini' ) );
    }

    /**
     * Returns all the perf. values measured so far.
     * @param bool $domeasure If true, will trigger data collection from registered perf-data providers,
     *                        otherwise only data recorded via calls to recordValue(s) will be returned
     * @param string $output page output so far
     * @return array
     * @too !important split method in 2 methods, with one public and one protected?
     */
    public static function getValues( $domeasure, $output, $returnCode=null )
    {
        if ( $domeasure )
        {
            // look up any perf data provider, and ask each one to give us its values
            foreach( eZPerfLoggerINI::variable( 'GeneralSettings', 'VariableProviders' ) as $measuringClass )
            {
                /// @todo !important check that $measuringClass exposes the correct interface
                $measured = call_user_func_array( array( $measuringClass, 'measure' ), array( $output, $returnCode ) );
                if ( is_array( $measured ) )
                {
                    self::recordValues( $measured );
                }
                else
                {
                    eZPerfLoggerDebug::writeError( "Perf measuring class $measuringClass did not return an array of data", __METHOD__ );
                }
            }
        }
        return self::$custom_variables;
    }

    /**
     * Sends to the logging subsystem the perf data in $values.
     * - checking first if there is any logging filter class registered
     * - removing from $values all variables not defined in ezperformancelogger.ini/TrackVars
     *
     * @too !important split method in 2 methods, with one public and one protected?
     */
    protected static function logIfNeeded( array $values, &$output )
    {
        // check if there is any registered filter class. If there is, ask it whether we should log or not
        $skip = false;
        $filters = eZPerfLoggerINI::variable( 'GeneralSettings', 'LogFilters' );
        // cater to 'array reset' situations: only 1 empty val in the array
        if ( count( $filters ) > 1 || ( count( $filters ) == 1 && $filters[0] != '' ) )
        {
            $skip = true;
            foreach( $filters as $filterClass )
            {
                if ( call_user_func_array( array( $filterClass, 'shouldLog' ), array( $values, $output ) ) )
                {
                    $skip = false;
                    break;
                }
            }
        }

        if ( ! $skip )
        {
            // build the array with the values we want to record in the logs -
            // only the ones corresponding to variables defined in the ini file,
            // not all the values measured so far
            $toLog = array();
            foreach( eZPerfLoggerINI::variable( 'GeneralSettings', 'TrackVariables' ) as $varName )
            {
                $toLog[$varName] = isset( $values[$varName] ) ? $values[$varName] : null;
            }

            // for each logging type configured, log values to it, using the class which supports it
            foreach( eZPerfLoggerINI::variable( 'GeneralSettings', 'LogMethods' ) as $logMethod )
            {
                $logged = false;
                foreach( eZPerfLoggerINI::variable( 'GeneralSettings', 'LogProviders' ) as $loggerClass )
                {
                    /// @todo !important check that $loggerClass exposes the correct interface
                    if ( in_array( $logMethod, call_user_func( array( $loggerClass, 'supportedLogMethods' ) ) ) )
                    {

                        call_user_func_array( array( $loggerClass, 'doLog' ), array( $logMethod, $toLog, &$output ) );
                        $logged = true;
                        break;
                    }
                }
                if ( !$logged )
                {
                    eZPerfLoggerDebug::writeError( "Could not log perf data to log '$logMethod', no logger class supports it", __METHOD__ );
                }
            }

        }
    }

    /*** Handling of the perf variables this class can measure natively  ***/

    /**
     * Note: this list will be "untrue" when some other php code has called eZPerfLogger::recordVale(),
     * as there will be more variables available. Shall we add 'custom' or '*' here?
     */
    static public function supportedVariables()
    {
        $out = array(
            'execution_time' => 'float (seconds, rounded to 1msec)',
            'mem_usage' => 'int (bytes, rounded to 1000)',
            'output_size' => 'int (bytes)'
        );
        /// @todo fix to run from eZ5 context
        if ( eZDebug::isDebugEnabled() )
        {
            $out['db_queries'] = 'int';
            $out['accumulators/*'] = 'float (seconds, rounded to 1msec)';
            $out['accumulators/*/count'] = 'int (number of times operation executed)';
        }
        if ( extension_loaded( 'xhprof' ) )
        {
            $out['xhkprof_runs'] = 'string (csv list of identifiers)';
        }
        if ( isset( $_SERVER['UNIQUE_ID '] ) )
        {
            $out['unique_id'] = 'string (unique per-request identifier)';
        }
        /// @todo also take into account CP version numbers
        if ( version_compare( '4.7.0', eZPublishSDK::version() ) >= 0 )
        {
            $out['content/nodeid'] = 'int';
        }
        return $out;
    }

    /**
     * This method is called to allow this class to provide values for the perf
     * variables it caters to.
     * In this case, it actually gets called by self::filter().
     * To avoid unnecessary overhead, it cheats a little bit, and it does not provide
     * values for ALL variables it supports, but only for the ones it knows will
     * be logged.
     * @param string $output
     * @param $returnCode
     * @return array
     */
    static public function measure( $output, $returnCode=null )
    {
        global $scriptStartTime;

        // This var we want to save as it is used for logs even when not present in TrackVariables.
        // Also using ga / piwik logs do alter $output, making length calculation in doLog() unreliable
        /// @todo this way of passing data around is not really beautiful...
        self::$outputSize = strlen( $output );
        if ( $returnCode != null )
        {
            self::$returnCode = (int) $returnCode;
        }

        $out = array();
        $vars = eZPerfLoggerINI::variable( 'GeneralSettings', 'TrackVariables' );

        foreach ( $vars as $var )
        {
            switch( $var )
            {
                case 'output_size':
                // some bugs persist forever...
                case 'ouput_size':
                    $out[$var] = self::$outputSize;
                    break;

                case 'execution_time':
                    // This global var does not exist anymore in eZP LS 5.0.
                    // We prefer using it when available as it is slightly more accurate
                    if ( $scriptStartTime == 0 )
                    {
                        $debug = eZDebug::instance();
                        $scriptStartTime = $debug->ScriptStart;
                    }
                    $out[$var] = round( microtime( true ) - $scriptStartTime, 3 );
                    break;

                case 'mem_usage':
                    $out[$var] = round( memory_get_peak_usage( true ), -3 );
                    break;

                case 'db_queries':
                    // (nb: only works when debug is enabled.
                    // Also does most likely not work when logging is done directly from the eZ5 stack
                    /// @todo fix to run from eZ5 context
                    $dbini = eZINI::instance();
                    // we cannot use $db->databasename() because we get the same for mysql and mysqli
                    $type = preg_replace( '/^ez/', '', $dbini->variable( 'DatabaseSettings', 'DatabaseImplementation' ) );
                    $type .= '_query';
                    // read accumulator
                    $debug = eZDebug::instance();
                    if ( isset( $debug->TimeAccumulatorList[$type] ) )
                    {
                        $queries = $debug->TimeAccumulatorList[$type]['count'];
                    }
                    else
                    {
                        // NB: to tell difference between 0 db reqs per page and no debug we could look for ezdebug::isenabled,
                        // but what if it was enabled at some point and later disabled?...
                        $queries = "0";
                    }
                    $out[$var] = $queries;
                    break;

                case 'xhkprof_runs':
                    $out[$var] = implode( ',', eZXHProfLogger::runs() );
                    break;

                case 'unique_id':
                    $out[$var] = $_SERVER['UNIQUE_ID'];
                    break;

                //case 'content/nodeid':
                //    $out[$var] = self::$nodeId;
                //    break;

                default:
                    // wildcard-based naming:

                    // content-info things, useful to help group/filter recorded data
                    if ( strpos( $var, 'content_info/' ) === 0 || strpos( $var, 'module_result/' ) === 0 )
                    {
                        $out[$var] = self::getModuleResultData( $var );
                        break;
                    }

                    // standard accumulators
                    /// @todo fix to run from eZ5 context
                    if ( strpos( $var, 'accumulators/' ) === 0 )
                    {
                        $parts = explode( '/', $var, 3 );
                        $type = $parts[1];
                        $debug = eZDebug::instance();
                        if ( isset( $debug->TimeAccumulatorList[$type] ) )
                        {
                            if ( @$parts[2] === 'count' )
                            {
                                $out[$var] = $debug->TimeAccumulatorList[$type]['count'];
                            }
                            else
                            {
                                $out[$var] = round( $debug->TimeAccumulatorList[$type]['time'], 3 );
                            }
                        }
                        else
                        {
                            $out[$var] = -1;
                        }
                        break;
                    }
            }
        }

        return $out;
    }

    /*** Some utility methods relied-upon by other classes ***/

    /**
     * Encapsulates retrieval of module_result data, to make it available globally,
     * across all eZP versions.
     *
     * @param string $var var name, should start with content_info/, module_result/ or module_params/
     * @param mixed $default a value to return if desired data is not present in module_result
     * @return mixed
     */
    public static function getModuleData( $var, $default=null )
    {
        if ( strpos( $var, 'module_params/' ) === 0 )
        {
            return self::getModuleParamsData( $var, $default );
        }
        else  if ( strpos( $var, 'content_info/' ) === 0 || strpos( $var, 'module_result/' ) === 0 )
        {
            return self::getModuleResultData( $var, $default );
        }
        else
        {
            eZPerfLoggerDebug::writeWarning( "Can not recover module result variable $var", __METHOD__ );
            return $default;
        }
    }

    /**
     * eZ 4 only: we rely on data set by index.php
     */
    public static function getModuleResultData( $var, $default=null )
    {
        if ( isset( $GLOBALS['moduleResult'] ) )
        {
            $data = $GLOBALS['moduleResult'];
            if ( strpos( $var, 'content_info/' ) === 0 )
            {
                if ( !isset( $data['content_info'] ) )
                {
                    // no need to log warnings here, as content_info is not always set
                    return $default;
                }
                $data = $data['content_info'];
            }
            $parts = explode( '/', $var, 3 );
            $value = isset( $data[$parts[1]] ) ? $data[$parts[1]] : $default;
            if ( is_array( $value ) && isset( $parts[2] ) )
            {
                $value = $value[$parts[2]];
            }
            return $value;
        }
        else
        {
            eZPerfLoggerDebug::writeWarning( 'Can not recover module result data, global variable "moduleResult" not found. Are you on eZ 5.0 or later?', __METHOD__ );
            return $default;
        }
    }

    public static function getModuleParamsData( $var, $default=null )
    {
        // q: is here any chance this is not set?
        if ( isset( $GLOBALS['eZRequestedModuleParams'] ) )
        {
            $data = $GLOBALS['eZRequestedModuleParams'];
            $parts = explode( '/', $var, 3 );
            $value = isset( $data[$parts[1]] ) ? $data[$parts[1]] : $default;
            if ( is_array( $value ) && isset( $parts[2] ) )
            {
                $value = $value[$parts[2]];
            }
            return $value;
        }
        else
        {
            eZPerfLoggerDebug::writeWarning( 'Can not trace module params data, global variable "eZRequestedModuleParams" not found. Are you on eZ 5.0 or later?', __METHOD__ );
            return $default;
        }
    }

    /*** Handling the log targets this class provides support for ***/

    /**
     * This method gets called by self::filter()
     */
    public static function supportedLogMethods()
    {
        return array ( 'apache', 'piwik', 'googleanalytics', 'logfile', 'syslog', 'database', 'csv', 'storage' );
    }

    /**
     * This method gets called by self::filter()
     */
    public static function doLog( $method, array $values, &$output )
    {
        switch( $method )
        {
            case 'apache':
                foreach( $values as $varname => $value )
                {
                    /// @todo should remove any " or space chars in the value for proper parsing by updateperfstats.php
                    apache_note( $varname, $value );
                }
                break;

            case 'piwik':
                $text = '';
                foreach( eZPerfLoggerINI::variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                {
                    $text .= "\npiwikTracker.setCustomVariable( $i, \"$var\", \"{$values[$var]}\", \"page\" );";
                }
                $text .= "\npiwikTracker.trackPageView();";
                $output = preg_replace( '/piwikTracker\.trackPageView\( *\);?/', $text, $output );
                break;

            case 'googleanalytics':
                $text = '';
                foreach( eZPerfLoggerINI::variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                {
                    $text .= "\n_gaq.push([$i, '$var', '{$values[$var]}', 3]);";
                }
                $text .= "\n_gaq.push(['_trackPageview']);";
                $output = preg_replace( "/_gaq.push\( *[ *['\"]_trackPageview['\"] *] *\);?/", $text, $output );
                break;

            case 'logfile':
            case 'syslog':
                /// same format as Apache "combined" by default
                $size = self::$outputSize;
                if ( $size == 0 )
                    $size = '-';
                $text = eZPerfLoggerApacheLogger::apacheLogLine( 'combined', $size, self::$returnCode ) . ' ';
                foreach( $values as $value )
                {
                    // do same as apache does: replace nulls with "-"
                    if ( ((string)$value ) === '' )
                    {
                        $text .= "- ";
                    }
                    else
                    {
                        /// @todo should remove any " or space chars in the value for proper parsing by updateperfstats.php
                        $text .= $value . " ";
                    }
                }
                if ( $method == 'logfile' )
                {
                    $text .= "\n";
                    file_put_contents( eZPerfLoggerINI::variable( 'logfileSettings', 'FileName' ), $text, FILE_APPEND );
                }
                else
                {
                    // syslog: we use apache log format for lack of a better idea...
                    openlog( "eZPerfLog", LOG_PID, LOG_USER );
                    syslog( LOG_INFO, $text );
                }
                break;

            case 'database':
            case 'csv':
            case 'storage':
                if ( $method == 'csv' )
                {
                    $storageClass = 'eZPerfLoggerCSVStorage';
                }
                else if ( $method == 'database' )
                {
                    $storageClass = 'eZPerfLoggerDBStorage';
                }
                else
                {
                    $storageClass = eZPerfLoggerINI::variable( 'ParsingSettings', 'StorageClass' );
                }

                /// @todo log error if storage class does not implement correct interface
                // when we deprecate php 5.2, we will be able to use $storageClass::insertStats...
                call_user_func( array( $storageClass, 'insertStats' ), array( array(
                    'url' => $_SERVER["REQUEST_URI"],
                    'ip' => is_callable( 'eZSys::clientIP' ) ? eZSys::clientIP() : eZSys::serverVariable( 'REMOTE_ADDR' ), // ezp 4.5 or less
                    'time' => time(),
                    /// @todo
                    'response_status' => self::$returnCode,
                    'response_size' => self::$outputSize,
                    'counters' => $values
                ) ) );
                break;

            /// @todo !important log a warning for default case (unhandled log format)
        }
    }

    /*** Handling of time measurements when eZDebug is off - methods useful to custom code wishing to add its own timing points ***/

    public static function accumulatorStart( $val, $group = false, $label = false, $data = null  )
    {
        // allow 3rd party code to leave in perf. measuring hooks with minimal speed loss
        if ( !self::$activated )
            return false;

        $startTime = microtime( true );
        if ( eZPerfLoggerDebug::isDebugEnabled() )
        {
            eZDebug::accumulatorStart( $val, $group, $label );
        }
        if ( !isset( self::$timeAccumulatorList[$val] ) )
        {
            self::$timeAccumulatorList[$val] = array( 'group' => $group, 'data' => array(), 'time' => 0, 'maxtime' => 0 );
        }
        self::$timeAccumulatorList[$val]['temp_time'] = $startTime;
        if ( $data !== null )
        {
            self::$timeAccumulatorList[$val]['data'][] = $data;
        }
    }

    public static function accumulatorStop( $val )
    {
        // allow 3rd party code to leave in perf. measuring hooks with minimal speed loss
        if ( !self::$activated )
            return false;

        $stopTime = microtime( true );
        if ( eZPerfLoggerDebug::isDebugEnabled() )
        {
            eZDebug::accumulatorStop( $val );
        }
        if ( !isset( self::$timeAccumulatorList[$val]['count'] ) )
        {
            self::$timeAccumulatorList[$val]['count'] = 1;
        }
        else
        {
            self::$timeAccumulatorList[$val]['count'] = self::$timeAccumulatorList[$val]['count'] + 1;
        }
        $thisTime = $stopTime - self::$timeAccumulatorList[$val]['temp_time'];
        unset( self::$timeAccumulatorList[$val]['temp_time'] );
        self::$timeAccumulatorList[$val]['time'] = $thisTime + self::$timeAccumulatorList[$val]['time'];
        if ( $thisTime > self::$timeAccumulatorList[$val]['maxtime'] )
        {
            self::$timeAccumulatorList[$val]['maxtime'] = $thisTime;
        }
    }

    public static function timeAccumulatorList()
    {
        return self::$timeAccumulatorList;
    }

}
