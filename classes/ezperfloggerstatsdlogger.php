<?php
/**
 * Class used to log data to Statsd.
 * It does not take advantage of odoscope integration capability but does tag
 * rewriting on its own, to be able to also add custom params for the <noscript> tag
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLoggerStatsdLogger implements eZPerfLoggerLogger
{
    public static function supportedLogMethods()
    {
        return array( 'statsd' );
    }

    /**
    * Code inspired by https://github.com/etsy/statsd/blob/master/examples/php-example.php
    */
    public static function doLog( $logmethod, array $data, &$output )
    {
        $ini = eZINI::instance( 'ezperformancelogger.ini' );
        $host = $ini->variable( 'StatsdSettings', 'Host' );
        $port = $ini->variable( 'StatsdSettings', 'Port' );
        $types = $ini->variable( 'StatsdSettings', 'VariableTypes' );
        // Failures in any of this should be silently ignored
        try
        {
            $fp = fsockopen( "udp://$host", $port, $errno, $errstr );
            if ( !$fp )
            {
                eZDebug::writeWarning( "Could not open udp socket to $host:$port", __METHOD__ )
                return;
            }
            foreach ( $sampledData as $stat => $value )
            {
                $type = isset( $types[$var] ) ? $types[$var] : 'ms';
                fwrite( $fp, "$prefix$var:{$data[$var]}|$type" );
            }
            fclose( $fp );
        } catch ( Exception $e )
        {
        }
    }
}

?>