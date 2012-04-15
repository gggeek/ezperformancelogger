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
class eZPerfLogger
{
    static protected $custom_variables = array();

    /**
     * Record a value associated with a given variable name.
     * The value will then be logged if in the ezperflogger.ini file that variable is set to be logged
     */
    static public function recordValue( $varName, $value )
    {
        self::$custom_variables[$varName] = $value;
    }

    static public function filter( $output )
    {
        global $scriptStartTime;

        $ini = eZINI::instance( 'ezperformancelogger.ini' );

        $values = array();
        foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
        {
            switch( $var )
            {
                case 'mem_usage':
                    $values[$i] = round( memory_get_peak_usage( true ), -3 );
                    break;
                case 'execution_time':
                    $values[$i] = round( microtime( true ) - $scriptStartTime, 3 ); /// @todo test if $scriptStartTime is set
                    break;
                case 'db_queries':
                    // (nb: only works when debug is enabled?)
                    $dbini = eZINI::instance();
                    // we cannot use $db->databasename() because we get the same for mysql and mysqli
                    $type = preg_replace( '/^ez/', '', $dbini->variable( 'DatabaseSettings', 'DatabaseImplementation' ) );
                    $type .= '_query';
                    // read accumulator
                    $debug = eZDebug::instance();
                    if ( isset( $debug->TimeAccumulatorList[$type] ) )
                    {
                        $values[$i] = $debug->TimeAccumulatorList[$type]['count'];
                    }
                    else
                    {
                        $values[$i] = "0"; // can not tell between 0 reqs per page and no debug...
                    }
                    break;

                default:
                    $values[$i] = isset( self::$custom_variables[$var] ) ? self::$custom_variables[$var] : null;
            }
        }

        foreach( $ini->variable( 'GeneralSettings', 'LogMethods' ) as $method )
        {
            switch( $method )
            {
                case 'apache':
                    foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                    {
                        /// @todo should remove any " or space chars in the value for proper parsing by updateperfstats.php
                        apache_note( $var, $values[$i] );
                    }
                    break;

                case 'piwik':
                    $text = '';
                    foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                    {
                        $text .= "\npiwikTracker.setCustomVariable( $i, \"$var\", \"{$values[$i]}\", \"page\" );";
                    }
                    $text .= "\npiwikTracker.trackPageView();";
                    $output = preg_replace( '/piwikTracker\.trackPageView\( *\);?/', $text, $output );
                    break;

                case 'googleanalytics':
                    $text = '';
                    foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                    {
                        $text .= "\n_gaq.push([$i, '$var', '{$values[$i]}', 3]);";
                    }
                    $text .= "\n_gaq.push(['_trackPageview']);";
                    $output = preg_replace( "/_gaq.push\( *[ *['\"]_trackPageview['\"] *] *\);?/", $text, $output );
                    break;

                case 'logfile':
                    /// same format as Apache "combined" by default:
                    /// LogFormat "%h %l %u %t \"%r\" % >s %b \"%{Referer}i\" \"%{User-Agent}i\"
                    /// @todo add values for %l (remote logname), %u (remote user)
                    /// @todo should use either %z or %Z depending on os...
                    /// @todo it's not always a 200 ok response...
                    $size = strlen( $output );
                    if ( $size == 0 )
                        $size = '-';
                    $text = $_SERVER["REMOTE_ADDR"] . ' - - [' . date( 'd/M/Y:H:i:s O' ) . '] "' . $_SERVER["REQUEST_METHOD"] . ' ' . $_SERVER["REQUEST_URI"]. ' ' . $_SERVER["SERVER_PROTOCOL"] . '" 200 ' . $size . ' "' . @$_SERVER["HTTP_REFERER"] . '" "' . @$_SERVER["HTTP_USER_AGENT"] . '" ';
                    foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                    {
                        /// @todo should remove any " or space chars in the value for proper parsing by updateperfstats.php
                        $text .= $values[$i] ." ";
                    }
                    $text .= "\n";
                    file_put_contents( $ini->variable( 'GeneralSettings', 'PerfLogFileName' ), $text, FILE_APPEND );
                    break;

                case 'database':
                    $counters = array();
                    foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                    {
                        $counters[$var] = $values[$i];
                    }
                    $storageClass = $ini->variable( 'GeneralSettings', 'StorageClass' );
                    /// @todo log error id storage class does not implement correct interface
                    // when we deprecate php 5.2, we will be able to use $storageClass::insertStats...
                    call_user_func( array( $storageClass, 'insertStats' ), array( array(
                        'url' => $_SERVER["REQUEST_URI"],
                        'ip' => $_SERVER["REMOTE_ADDR"],
                        'time' => time(),
                        'counters' => $counters
                    ) ) );
                    break;

            }
        }

        return $output;
    }

    static public function parseLog( $logFilePath )
    {

        $contentArray = array();

        $plIni = eZINI::instance( 'ezperformancelogger.ini' );

        $sys = eZSys::instance();
        $varDir = $sys->varDirectory();
        $updateViewLog = "updateperfstats.log";

        $startLine = "";
        $hasStartLine = false;

        $updateViewLogPath = $varDir . "/" . $logDir . "/" . $updateViewLog;
        if ( is_file( $updateViewLogPath ) )
        {
            $fh = fopen( $updateViewLogPath, "r" );
            if ( $fh )
            {
                while ( !feof ( $fh ) )
                {
                    $line = fgets( $fh, 1024 );
                    if ( preg_match( "/\[/", $line ) )
                    {
                        $startLine = $line;
                        $hasStartLine = true;
                    }
                }
                fclose( $fh );
            }
        }

        $lastLine = "";
        $startTime = time();
        $count = 0;
        $storageClass = $plIni->variable( 'GeneralSettings', 'StorageClass' );

        if ( is_file( $logFilePath ) )
        {
            $handle = fopen( $logFilePath, "r" );
            if ( $handle )
            {
                $noteVars = $plIni->variable( 'GeneralSettings', 'TrackVariables' );
                $noteVarsCount = count( $noteVars );
                $startParse = !$hasStartLine;
                $stopParse = false;
                while ( !feof( $handle ) and !$stopParse )
                {
                    $line = fgets( $handle, 1024 );
                    if ( !empty( $line ) )
                    {
                        $lastLine = $line;

                        if ( $startParse )
                        {
                            if ( !preg_match( '/([0-9.]+) +([^ ]+) +([^ ]+) +\[([^]]+)\] +(.+)/', $line, $matches ) )
                            {
                                /// @todo log warning
                                continue;
                            }
                            $datetime = DateTime::createFromFormat( 'd/M/Y:H:i:s O', $matches[4] );
                            if ( !$datetime )
                            {
                                /// @todo log warning
                                continue;
                            }
                            $time = $datetime->format( 'U' );
                            $ip = $matches[1];

                            if ( $time == $startTime )
                                $stopParse = true;

                            $logPartArray = explode( '"', $matches[5] ); //preg_split( "/[\"]+/", $line );
                            list( $requireMethod, $url, $protocol ) = explode( ' ', $logPartArray[1] );

                            /// NB: we assume there is no " in the 'perf counters' part
                            $notePart = ltrim( rtrim( $logPartArray[count( $logPartArray )-1], " \n\r" ), ' ' );
                            $notes = explode( ' ', $notePart );
                            if ( count( $notes ) < $noteVarsCount )
                            {
                                /// @todo log warning
                                continue;
                            }

                            $contentArray[] = array(
                                'url' => $url,
                                'time' => $time,
                                'ip' => $ip,
                                /// @todo if the number of elements found is bigger than the number expected, we will not add anything. Use array_slice
                                'counters' => array_combine( $noteVars, $notes ) );

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
                            if ( $line == $startLine )
                            {
                                $startParse = true;
                            }
                        }
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

        return $count;
    }
}

?>