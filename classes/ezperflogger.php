<?php
/**
 * An 'output filter' class that does not filter anything, but logs some perf values
 * to different "log" types.
 *
 * @todo log total cluster queries (see code in ezdebug extension)
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2008-2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
class eZPerfLogger implements eZPerfLoggerProvider, eZPerfLoggerLogger
{
    static protected $custom_variables = array();
    static protected $outputSize = null;

    /**
     * Record a value associated with a given variable name.
     * The value will then be logged if in the ezperflogger.ini file that variable is set to be logged
     */
    static public function recordValue( $varName, $value )
    {
        self::$custom_variables[$varName] = $value;
    }

    /**
     * Record a list of value associated with a set of variables
     */
    static public function recordValues( array $vars )
    {
        foreach( $vars as $varName => $value )
        {
            self::$custom_variables[$varName] = $value;
        }
    }

    /**
     * This method is registered to be executed at end of page execution. It does
     * the actual logging of the performance variables values according to the
     * configuration in ezperformancelogger.ini, as well as the xhprof profile
     * dumping.
     * When xhprof is enabled, an html comment is added to page output
     * (this method runs before debug output is added to it, so we can not add it there)
     */
    static public function filter( $output )
    {
        // look up any perf data provider, and ask each one to record its values
        $ini = eZINI::instance( 'ezperformancelogger.ini' );

        $skip = false;

        $filters = $ini->variable( 'GeneralSettings', 'LogFilters' );
        // cater to 'array reset' situations
        foreach( $filters as $i => $v )
        {
            if ( $v == '' )
            {
                unset( $filters[$i] );
            }
        }
        if ( count( $filters ) )
        {
            $skip = true;
            foreach( $filters as $filterClass )
            {
                if ( call_user_func_array( array( $filterClass, 'shouldLog' ), array( $output ) ) )
                {
                    $skip = false;
                    break;
                }
            }
        }

        if ( ! $skip )
        {
            // get perf data from all registered providers
            foreach( $ini->variable( 'GeneralSettings', 'VariableProviders' ) as $measuringClass )
            {
                /// @todo !important check that $class exposes the correct interface
                $measured = call_user_func_array( array( $measuringClass, 'measure' ), array( $output ) );
                if ( is_array( $measured ) )
                {
                    self::recordValues( $measured );
                }
                else
                {
                    eZDebug::writeError( "Perf measuring class $class did not return an array of data", __METHOD__ );
                }
            }

            // build the array with the values we want to record in the logs -
            // only the ones corresponding to variables defined in the ini file,
            // not all the values measured so far
            /// @todo !important replace with a faster array_intersect?
            $values = array();
            foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
            {
                $values[$var] = isset( self::$custom_variables[$var] ) ? self::$custom_variables[$var] : null;
            }

            // for each logging type configured, log values to it
            foreach( $ini->variable( 'GeneralSettings', 'LogMethods' ) as $logMethod )
            {
                $logged = false;
                foreach( $ini->variable( 'GeneralSettings', 'LogProviders' ) as $loggerClass )
                {
                    /// @todo !important check that $class exposes the correct interface
                    if ( in_array( $logMethod, call_user_func( array( $loggerClass, 'supportedLogMethods' ) ) ) )
                    {

                        call_user_func_array( array( $loggerClass, 'doLog' ), array( $logMethod, $values ) );
                        $logged = true;
                        break;
                    }
                }
                if ( !$logged )
                {
                    eZDebug::writeError( "Could not log perf data to log '$logMethod', no logger class supports it", __METHOD__ );
                }
            }

        }

        if ( eZXHProfLogger::isRunning() )
        {
            eZXHProfLogger::stop();
        }
        if ( $ini->variable( 'XHProfSettings', 'AppendXHProfTag' ) == 'enabled' && $runs = eZXHProfLogger::runs() )
        {
            $xhtag = "<!-- XHProf runs: " . implode( ',', $runs ) . " -->";
            $output = preg_replace( "#</body>#", $xhtag . '</body>', $output, -1, $count );
            if ( $count == 0 )
            {
                $output .= $xhtag;
            }
        }

        return $output;
    }

    static public function supportedVariables()
    {
        return array( 'execution_time', 'mem_usage', 'db_queries', 'output_size', 'xhkprof_runs' );
    }

    /**
     * This method is called to allow this class to provide values for the measurements
     * variables it caters to.
     * In this case, it actually gets called by self::filter().
     */
    static public function measure( $output )
    {
        global $scriptStartTime;

        // This var we want to save as it is used for logs even when not in TrackVariables
        /// @todo this way of passing data around is not really beautiful...
        self::$outputSize = strlen( $output );

        $out = array();
        $ini = eZINI::instance( 'ezperformancelogger.ini' );
        $vars = $ini->variable( 'GeneralSettings', 'TrackVariables' );

        if ( in_array( 'ouput_size', $vars ) )
        {
            $out['execution_time'] = self::$outputSize;
        }

        if ( in_array( 'execution_time', $vars ) )
        {
            $out['execution_time'] = round( microtime( true ) - $scriptStartTime, 3 );
        }

        if ( in_array( 'mem_usage', $vars ) )
        {
            $out['mem_usage'] = round( memory_get_peak_usage( true ), -3 );
        }

        if ( in_array( 'db_queries', $vars ) )
        {
            // (nb: only works when debug is enabled?)
            $dbini = eZINI::instance();
            // we cannot use $db->databasename() because we get the same for mysql and mysqli
            $type = preg_replace( '/^ez/', '', $dbini->variable( 'DatabaseSettings', 'DatabaseImplementation' ) );
            $type .= '_query';
            // read accumulator
            $debug = eZDebug::instance();
            if ( isset( $debug->TimeAccumulatorList[$type] ) )
            {
                $queries= $debug->TimeAccumulatorList[$type]['count'];
            }
            else
            {
                 // NB: to tell diffrence between 0 db reqs per page and no debug we could look for ezini(debugoutput)...
                $queries = "0";
            }
            $out['db_queries'] = $queries;
        }

        if ( in_array( 'xhkprof_runs', $vars ) )
        {
            $out['xhkprof_runs'] = implode( ',', eZXHProfLogger::runs() );
        }

        return $out;
    }

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
    public static function doLog( $method, $values )
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
                $ini = eZINI::instance( 'ezperformancelogger.ini' );
                $text = '';
                foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                {
                    $text .= "\npiwikTracker.setCustomVariable( $i, \"$var\", \"{$values[$var]}\", \"page\" );";
                }
                $text .= "\npiwikTracker.trackPageView();";
                $output = preg_replace( '/piwikTracker\.trackPageView\( *\);?/', $text, $output );
                break;

            case 'googleanalytics':
                $ini = eZINI::instance( 'ezperformancelogger.ini' );
                $text = '';
                foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                {
                    $text .= "\n_gaq.push([$i, '$var', '{$values[$var]}', 3]);";
                }
                $text .= "\n_gaq.push(['_trackPageview']);";
                $output = preg_replace( "/_gaq.push\( *[ *['\"]_trackPageview['\"] *] *\);?/", $text, $output );
                break;

            case 'logfile':
            case 'syslog':
                /// same format as Apache "combined" by default
                /// @todo it's not always a 200 ok response...
                $size = self::$outputSize;
                if ( $size == 0 )
                    $size = '-';
                $text = self::apacheLogLine( 'combined', $size, 200 ) . ' ';
                foreach( $values as $varname => $value )
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
                    $ini = eZINI::instance( 'ezperformancelogger.ini' );
                    file_put_contents( $ini->variable( 'GeneralSettings', 'PerfLogFileName' ), $text, FILE_APPEND );
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
                    $storageClass = $ini->variable( 'ParsingSettings', 'StorageClass' );
                }

                /// @todo log error if storage class does not implement correct interface
                // when we deprecate php 5.2, we will be able to use $storageClass::insertStats...
                call_user_func( array( $storageClass, 'insertStats' ), array( array(
                    'url' => $_SERVER["REQUEST_URI"],
                    'ip' => $_SERVER["REMOTE_ADDR"],
                    'time' => time(),
                    /// @todo
                    'response_status' => "200",
                    'response_size' => self::$outputSize,
                    'counters' => $values
                ) ) );
                break;

            /// @todo !important log a warning for default case
        }
    }

    /**
     * Parse a log file (apache "extended" format expected, with perf. values at the end),
     * retrieve performance values from it and store them in a storage provider
     */
    static public function parseLog( $logFilePath )
    {

        $contentArray = array();

        $plIni = eZINI::instance( 'ezperformancelogger.ini' );
        $ini = eZINI::instance();
        $logDir = $ini->variable( 'FileSettings', 'LogDir' );

        $sys = eZSys::instance();
        $varDir = $sys->varDirectory();
        $updateViewLog = "updateperfstats.log";

        $startLine = "";
        $hasStartLine = false;

        /// @todo we should store name of apache log file in our token, so that if
        // it changes in ini file, we do not try to skip anything
        $updateViewLogPath = $varDir . "/" . $logDir . "/" . $updateViewLog;
        if ( is_file( $updateViewLogPath ) )
        {
            $fh = fopen( $updateViewLogPath, "r" );
            if ( $fh )
            {
                while ( !feof ( $fh ) )
                {
                    $line = fgets( $fh );
                    if ( preg_match( "/\[/", $line ) )
                    {
                        $startLine = $line;
                        $hasStartLine = true;
                    }
                }
                fclose( $fh );
            }
        }
        if ( $hasStartLine )
        {
            eZDebug::writeDebug( "Found state of previous run. Log file parsing will skip some lines" );
        }
        else
        {
            eZDebug::writeDebug( "State of previous run not found. Parsing the whole log file" );
        }

        $lastLine = "";
        $startTime = time();
        $count = 0;
        $storageClass = $plIni->variable( 'ParsingSettings', 'StorageClass' );
        $excludeRegexps= $plIni->variable( 'ParsingSettings', 'ExcludeUrls' );
        $skipped = 0;
        $total = 0;
        $parsed = 0;
        $empty = 0;

        if ( is_file( $logFilePath ) )
        {
            $handle = fopen( $logFilePath, "r" );
            if ( $handle )
            {
                $noteVars = $plIni->variable( 'GeneralSettings', 'TrackVariables' );
                //$noteVarsCount = count( $noteVars );
                $startParse = !$hasStartLine;
                $stopParse = false;
                while ( !feof( $handle ) and !$stopParse )
                {
                    $line = fgets( $handle, 1024 );
                    $total++;
                    if ( !empty( $line ) )
                    {
                        $lastLine = $line;

                        if ( $startParse )
                        {
                            $parsed++;

                            $parsedLine = self::parseLogLine( $line, $noteVars, $excludeRegexps );
                            if ( is_bool( $parsedLine ) )
                            {
                                // excluded or invalid line
                                continue;
                            }

                            if ( $parsedLine['time'] == $startTime )
                                $stopParse = true;

                            $contentArray[] = $parsedLine;

                            // if $contentArray grows too big, we're gonna go OOM, so we update db incrementally
                            $count++;
                            if ( ( $count % 1000 ) == 1 )
                            {
                                /// @todo log error if storage class does not implement correct interface
                                // when we deprecate php 5.2, we will be able to use $storageClass::insertStats...
                                call_user_func( array( $storageClass, 'insertStats' ), $contentArray );
                                $contentArray = array();
                            }
                        }
                        else
                        {
                            $skipped++;
                            if ( $line == $startLine )
                            {
                                $startParse = true;
                            }
                        }
                    }
                    else
                    {
                        $empty++;
                    }
                }
                fclose( $handle );
            }
            else
            {
                eZDebug::writeWarning( "Cannot open log-file '$logFilePath' for reading, please check permissions and try again.", __METHOD__ );
                return false;
            }
        }
        else
        {
            eZDebug::writeWarning( "Log-file '$logFilePath' doesn't exist, please check your ini-settings and try again.", __METHOD__ );
            return false;
        }

        if ( count( $contentArray ) )
        {
            /// @todo log error if storage class does not implement correct interface
            // when we deprecate php 5.2, we will be able to use $storageClass::insertStats...
            call_user_func( array( $storageClass, 'insertStats' ), $contentArray );
        }

        $dt = new eZDateTime();
        $fh = fopen( $updateViewLogPath, "w" );
        if ( $fh )
        {
            fwrite( $fh, "# Finished at " . $dt->toString() . "\n" );
            fwrite( $fh, "# Last updated entry:" . "\n" );
            fwrite( $fh, $lastLine . "\n" );
            fclose( $fh );
        }
        else
        {
            eZDebug::writeError( "Could not store last date up perf-log file parsing in $updateViewLogPath, double-counting might occur", __METHOD__ );
        }

        eZDebug::writeDebug( 'Empty lines: ' . $empty );
        eZDebug::writeDebug( 'Skipped lines: ' . $skipped );
        eZDebug::writeDebug( 'Parsed lines: ' . $parsed );
        eZDebug::writeDebug( 'Total lines: ' . $total );

        return $count;
    }

    /**
     * Parses a log line, expected to be in Apache "combined+" format:
     * - the line must begin with the combined format
     * - it is expected to contain N more values at the end correspoding to the $counters array
     * Expected log format: "%h %l %u %t \"%r\" % >s %b \"%{Referer}i\" \"%{User-Agent}i\" [CounterValue]*
     * @return mixed an array on success, false on failure, true if url matches an excluderegexp
     */
    static public function parseLogLine( $line, $counters = array(), $excludeRegexps = array() )
    {
        $countersCount = count( $counters );

        if ( !preg_match( '/([0-9.]+) +([^ ]+) +([^ ]+) +\[([^]]+)\] +(.+)/', $line, $matches ) )
        {
            /// @todo log warning
            return false;
        }

        $time = strtotime( implode( ' ', explode( ':', str_replace( '/', '.', $matches[4] ), 2 ) ) );
        if ( !$time )
        {
            /// @todo log warning
            return false;
        }

        $ip = $matches[1];

        $logPartArray = explode( '"', $matches[5] ); //preg_split( "/[\"]+/", $line );

        // there is no point in parsing this line further: we miss the perf-data part
        if ( count( $logPartArray ) < 4 && $countersCount )
        {
            return false;
        }

        // nb: generates a php warning when the url recorded by apache is too long.
        // In that case apache records a substring of the url in the access log, and here
        // we will find no protocol part
        list( $requireMethod, $url, $protocol ) = explode( ' ', $logPartArray[1] );

        foreach( $excludeRegexps as $regexp )
        {
            if ( preg_match( $regexp, $url ) )
            {
               return true;
            }
        }

        list( $respstatus, $respsize ) = explode( ' ', trim( $logPartArray[2], ' ' ) );

        if ( $countersCount )
        {
            /// NB: we assume there is no " in the 'perf counters' part
            $notePart = ltrim( rtrim( $logPartArray[count( $logPartArray )-1], " \n\r" ), ' ' );
            $notes = explode( ' ', $notePart );
            if ( count( $notes ) < $countersCount )
            {
                // could be any static resource
                return false;
            }
            else if ( count( $notes ) > $countersCount )
            {
                // The apache log might be set up to add extra stuff here, between the user agent's string and the perf logging data
                // so we just ignore it.
                // Note that this might also be a sign of a config error...
                $notes = array_slice( $notes, -1 * $countersCount );
            }

            $counters = array_combine( $counters, $notes );
        }
        else
        {
            $counters = null;
        }

        return array(
            'url' => $url,
            'time' => $time,
            'ip' => $ip,
            'response_status' => $respstatus,
            'response_size' => $respsize,
            'counters' => $counters );
    }

    /**
     * Returns a string corresponding to Apache
     */
    static function apacheLogLine( $format = 'common', $size='-', $httpReturn = '200' )
    {
        switch ( $format )
        {
            /// LogFormat "%h %l %u %t \"%r\" % >s %b \"%{Referer}i\" \"%{User-Agent}i\"
            /// @todo add values for %l (remote logname), %u (remote user)
            case 'combined':
                return $_SERVER["REMOTE_ADDR"] . ' - - [' . date( 'd/M/Y:H:i:s O' ) . '] "' . $_SERVER["REQUEST_METHOD"] . ' ' . $_SERVER["REQUEST_URI"]. ' ' . $_SERVER["SERVER_PROTOCOL"] . '" 200 ' . $size . ' "' . @$_SERVER["HTTP_REFERER"] . '" "' . @$_SERVER["HTTP_USER_AGENT"] . '"';
            case 'common':
            default:
                return $_SERVER["REMOTE_ADDR"] . ' - - [' . date( 'd/M/Y:H:i:s O' ) . '] "' . $_SERVER["REQUEST_METHOD"] . ' ' . $_SERVER["REQUEST_URI"]. ' ' . $_SERVER["SERVER_PROTOCOL"] . '" 200 ' . $size;
        }
    }

}

?>