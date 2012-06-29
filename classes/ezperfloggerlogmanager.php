<?php
/**
 * The class implements methods allowing other code to parse perf-data from Apache-formatted log files, and to create Apache-formatted logs
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
class eZPerfLoggerLogManager
{

    /**
     * Parse a log file (apache "extended" format expected, with perf. values at the end),
     * retrieve performance values from it and store them in a storage provider
     * @param string $logFilePath
     */
    static public function updatePerfStatsFromApacheLog( $logFilePath )
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

                            $parsedLine = self::parseApacheLogLine( $line, $noteVars, $excludeRegexps );
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
    static public function parseApacheLogLine( $line, $counters = array(), $excludeRegexps = array() )
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
     * Returns a string corresponding to Apache log format
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

    /**
     * Rotates a log file, if it's bigger than  $maxSize bytes.
     * Rotated files get a .20120612_122359 stringa ppended to their name
     * If $maxFiles is > 0, only $maxFiles files are kept, other eliminated
     */
    public static function rotateLogs( $dir, $filename, $maxSize=0, $maxFiles=0 )
    {
        $filepath = "$dir/$filename";
        if ( is_file( $filepath ) && filesize( $filepath ) > $maxSize )
        {
            if ( !rename( $filepath, $filepath . "." . strftime( '%Y%m%d_%H%M%S' ) ) )
            {
                eZDebug::writeWarning( "Could not rotate log file $filepath", __METHOD__ );
            }
        }
        if ( $maxFiles )
        {
            $files = array();
            foreach( scandir( $dir ) as $afile )
            {
                if ( is_file( "$dir/$afile" ) && strpos( $afile, $filename ) === 0 )
                {
                    $ext = substr( strrchr( $afile, "." ), 1 );
                    $files[$ext] = $afile;
                }
            }
            if ( count( $files ) > $maxFiles )
            {
                ksort( $files );
                $oldest = "$dir/" . reset( $files );
                if ( !unlink( $oldest ) )
                {
                    eZDebug::writeWarning( "Could not remove log file $oldest", __METHOD__ );
                }
            }
        }
    }
}

?>