<?php
/**
 * The class implements methods allowing to read/write Apache-style logfiled
 *
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
class eZPerfLoggerLogManager
{

    /**
     * Parse a log file, retrieve performance values from it and store them in a storage provider
     * @param string $logFilePath
     * @param string $logParserClass class used to parse the parsed, must implement interface: eZPerfLoggerLogParser
     * @param string $storageClass class used to parse the lines of the log file, must implement interface: eZPerfLoggerStorage
     * @param string $tokenFileName a "token" file, stored in var/<vardir>/log, where we save on every invocation the last parsed line. Pass in NULL to always parse the full log
     * @param array $excludeRegexps
     * @param bool $omitCounters
     * @param array $options extra options for initializing the log parser class
     * @return mixed false|array array with stats of lines parsed, false on error
     */
    static public function updateStatsFromLogFile( $logFilePath, $logParserClass, $storageClass, $tokenFileName = '', $excludeRegexps = array(), $omitCounters=false, $options= array() )
    {
        if ( $tokenFileName === null )
        {
            $startLine = false;
        }
        else
        {
            if ( $tokenFileName == '' )
            {
                $tokenFileName = basename( $logFilePath ) . '-parsing.log';
            }
            $startLine = self::readUpdateToken( $tokenFileName );

            if ( $startLine )
            {
                eZPerfLoggerDebug::writeDebug( "Found state of previous run. Log file parsing will skip some lines: $logFilePath", __METHOD__ );
            }
            else
            {
                eZPerfLoggerDebug::writeDebug( "State of previous run not found. Parsing the whole log file: $logFilePath", __METHOD__ );
            }
        }

        // initialize the log parser class with custom options if needed
        // mmm, DIC where are thou?
        if ( count( $options ) )
        {
            call_user_func( array( $storageClass, 'setOptions' ), $options );
        }

        $contentArray = array();
        $lastLine = "";
        $startTime = time();
        $count = 0;
        $skipped = 0;
        $total = 0;
        $parsed = 0;
        $empty = 0;

        if ( is_file( $logFilePath ) )
        {
            $handle = fopen( $logFilePath, "r" );
            if ( $handle )
            {
                if ( $omitCounters )
                {
                    $noteVars = array();
                }
                else
                {
                    $noteVars = eZPerfLoggerINI::variable( 'GeneralSettings', 'TrackVariables' );
                }
                //$noteVarsCount = count( $noteVars );
                $startParse = ( $startLine === false );
                $stopParse = false;
                while ( !feof( $handle ) and !$stopParse )
                {
                    $line = fgets( $handle, 4096 );
                    $total++;
                    if ( !empty( $line ) )
                    {
                        $lastLine = $line;

                        if ( $startParse )
                        {
                            $parsed++;

                            $parsedLine = call_user_func( array( $logParserClass, 'parseLogLine' ), $line, $noteVars, $excludeRegexps ); //self::parseApacheLogLine( $line, $noteVars, $excludeRegexps );
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
                eZPerfLoggerDebug::writeWarning( "Cannot open log-file '$logFilePath' for reading, please check permissions and try again.", __METHOD__ );
                return false;
            }
        }
        else
        {
            /// demoted to a Debug message, as after rotation this can happen
            eZPerfLoggerDebug::writeDebug( "Log-file '$logFilePath' doesn't exist, please check your ini-settings and try again.", __METHOD__ );
            return false;
        }

        if ( count( $contentArray ) )
        {
            /// @todo log error if storage class does not implement correct interface
            // when we deprecate php 5.2, we will be able to use $storageClass::insertStats...
            call_user_func( array( $storageClass, 'insertStats' ), $contentArray );
        }

        if ( $tokenFileName !== null )
        {
            self::writeUpdateToken( $tokenFileName, $lastLine );
        }

        /*eZPerfLoggerDebug::writeDebug( 'Empty lines: ' . $empty );
           eZPerfLoggerDebug::writeDebug( 'Skipped lines: ' . $skipped );
           eZPerfLoggerDebug::writeDebug( 'Parsed lines: ' . $parsed );
           eZPerfLoggerDebug::writeDebug( 'Total lines: ' . $total );*/

        return array( 'empty' => $empty, 'skipped' => $skipped, 'parsed' => $parsed, 'counted' => $count, 'total' => $total );
    }

    /**
     * Writes a "token file"
     * This is useful whenever there are log files which get parse based on cronjobs
     * and we have to remember the last line which has been found
     */
    protected static function writeUpdateToken( $tokenFile, $tokenLine )
    {
        $sys = eZSys::instance();
        $updateViewLogPath = $sys->varDirectory() . "/" . eZPerfLoggerINI::variable( 'FileSettings', 'LogDir', 'site.ini' ) . "/" . $tokenFile;
        $dt = new eZDateTime();
        if ( !file_put_contents(
            $updateViewLogPath,
            "# Finished at " . $dt->toString() . "\n" .
            "# Last updated entry:" . "\n" .
            $tokenLine . "\n" ) )
        {
            eZPerfLoggerDebug::writeError( "Could not store last date of perf-log file parsing in $updateViewLogPath, double-counting might occur", __METHOD__ );
        }
    }

    /**
     * Reads the data previously saved in the token file
     * @return mixed string|false
     */
    protected static function readUpdateToken( $tokenFile )
    {
        $sys = eZSys::instance();
        $updateViewLogPath = $sys->varDirectory() . "/" . eZPerfLoggerINI::variable( 'FileSettings', 'LogDir', 'site.ini' ) . "/" . $tokenFile;
        if ( is_file( $updateViewLogPath ) )
        {
            // nb: we need the newline at the end of the saved line for a proper comparison
            $lines = file( $updateViewLogPath, FILE_SKIP_EMPTY_LINES );
            return isset( $lines[2] ) ? $lines[2] : false;
        }
        return false;
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
                eZPerfLoggerDebug::writeWarning( "Could not rotate log file $filepath", __METHOD__ );
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
                    eZPerfLoggerDebug::writeWarning( "Could not remove log file $oldest", __METHOD__ );
                }
            }
        }
    }

}

?>