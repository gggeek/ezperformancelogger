<?php
/**
 * Class used to store performance data in a csv file.
 *
 * The idea is to store data in a format more friendly to spreadsheets than the web server access log
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2008-2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
class eZPerfLoggerCSVStorage implements eZPerfLoggerStorage
{

    /**
     *
     */
    public static function insertStats( $data )
    {
        $ini = eZINI::instance( 'ezperformancelogger.ini' );
        $csvfile = $ini->variable( 'csvSettings', 'FileName' );
        $separator = $ini->variable( 'csvSettings', 'Separator' );
        $quotes = $ini->variable( 'csvSettings', 'Quotes' );
        $fp = fopen( $csvfile, 'a' );
        if ( !$fp )
        {
            return false;
        }
        foreach( $data as $line )
        {
            $data = $line['counters'];
            $data[] = date( 'd/M/Y:H:i:s O', $line['time'] );
            $data[] = $line['ip'];
            $data[] = $quotes . str_replace( $quotes, $quotes . $quotes, $line['url'] ). $quotes;
            fwrite( $fp, implode( $separator, $data ) . "\n" );
        }
        fclose( $fp );
        return true;
    }
}

?>