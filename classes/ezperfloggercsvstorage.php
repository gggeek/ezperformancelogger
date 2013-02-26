<?php
/**
 * Class used to store performance data in a csv file.
 *
 * The idea is to store data in a format more friendly to spreadsheets than the web server access log
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2008-2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
class eZPerfLoggerCSVStorage implements eZPerfLoggerStorage, eZPerfLoggerLogParser
{

    /**
     * @see eZPerfLoggerStorage::insertStats
     * @see http://tools.ietf.org/html/rfc4180
     * @param array $data
     */
    public static function insertStats( array $data )
    {
        $ini = eZINI::instance( 'ezperformancelogger.ini' );
        $csvfile = $ini->variable( 'csvSettings', 'FileName' );
        $separator = $ini->variable( 'csvSettings', 'Separator' );
        $quotes = $ini->variable( 'csvSettings', 'Quotes' );
        $addheader = false;
        if ( !file_exists( $csvfile ) )
        {
            $addheader = true;
        }
        $fp = fopen( $csvfile, 'a' );
        if ( !$fp )
        {
            return false;
        }
        if ( $addheader )
        {
            fwrite( $fp, "Timestamp{$separator}" );
            fwrite( $fp, implode( $separator, $ini->variable( 'GeneralSettings', 'TrackVariables' ) ) );
            fwrite( $fp, "{$separator}Date{$separator}IP Address{$separator}Response Status{$separator}Response size{$separator}URL\n" );
        }
        foreach( $data as $line )
        {
            $data = array_merge( array( $line['time'] ), $line['counters'] );
            $data[] = date( 'd/M/Y:H:i:s O', $line['time'] );
            $data[] = $line['ip'];
            $data[] = $line['response_status'];
            $data[] = $line['response_size'];
            $data[] = $quotes . str_replace( $quotes, $quotes . $quotes, $line['url'] ). $quotes;
            fwrite( $fp, implode( $separator, $data ) . "\n" );
        }
        fclose( $fp );
        return true;
    }

    static public function parseLogLine( $line, $counters = array(), $excludeRegexps = array() )
    {
        $countersCount = count( $counters );

        $ini = eZINI::instance( 'ezperformancelogger.ini' );
        $separator = $ini->variable( 'csvSettings', 'Separator' );

        $logPartArray = explode( $separator, $line, $countersCount + 6 );
        if ( count( $logPartArray ) < $countersCount + 6 )
        {
            return false;
        }

        $url = rtrim( $logPartArray[$countersCount + 5], "\n" );
        foreach( $excludeRegexps as $regexp )
        {
            if ( preg_match( $regexp, $url ) )
            {
                return true;
            }
        }

        return array(
            'url' => $url,
            'time' => $logPartArray[0],
            'ip' => $logPartArray[$countersCount + 2],
            'response_status' => $logPartArray[$countersCount + 3],
            'response_size' => $logPartArray[$countersCount + 4],
            'counters' => array_slice( $logPartArray, 1, $countersCount ) );
    }

    /**
     * This one is empty, as we take all options from ini files.
     *
     * @todo !important refactor so that we pass thorugh here instead
     */
    static public function setOptions( array $opts )
    {
    }
}

?>