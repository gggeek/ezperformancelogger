<?php
/**
 * Class used to log data to Statsd.
 *
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLoggerStatsdLogger implements eZPerfLoggerLogger
{
    static $prefix = null;
    static $postfix = null;

    public static function supportedLogMethods()
    {
        return array( 'statsd' );
    }

    /**
     * Code inspired by https://github.com/etsy/statsd/blob/master/examples/php-example.php
     */
    public static function doLog( $logMethod, array $data, &$output )
    {
        list( $host, $port, $types ) = eZPerfLoggerINI::variableMulti( 'StatsdSettings', array( 'Host', 'Port', 'VariableTypes' ) );

        // Failures in any of this should be silently ignored
        try
        {
            $strings = array();
            foreach ( $data as $varName => $value )
            {
                $type = ( isset( $types[$varName] ) && $types[$varName] != '' ) ? $types[$varName] : 'ms';
                $strings[] = static::transformVarName( $varName ) . ":{$value}|$type";
            }

            $fp = fsockopen( "udp://$host", (int)$port, $errNo, $errStr );
            if ( !$fp )
            {
                eZPerfLoggerDebug::writeWarning( "Could not open udp socket to $host:$port - $errStr", __METHOD__ );
                return;
            }
            if ( eZPerfLoggerINI::variable( 'StatsdSettings', 'SendMetricsInSinglePacket' ) == 'enabled' )
            {
                fwrite( $fp, implode( "\n", $strings ) );
            }
            else
            {
                foreach ( $strings as $string )
                {
                    fwrite( $fp, $string );
                }
            }
            fclose( $fp );
        } catch ( Exception $e )
        {
        }
    }

    /**
     * For statsd, we use a different logic than for other loggers:
     * in the name of the KPI we embed some variable data, such as f.e.
     * content-class name. This allows better grouping and filtering of data
     * in the Graphite console.
     *
     * @see ezperformancelogger.ini
     *
     * We cache internally prefix and postfix for optimal performances
     */
    public static function transformVarName( $var, $default=null )
    {
        if ( self::$prefix === null || self::$postfix === null )
        {
            $strip = ( eZPerfLoggerINI::variable( 'StatsdSettings', 'RemoveEmptyTokensInVariable' ) == 'enabled' );
            foreach( array( eZPerfLoggerINI::variable( 'StatsdSettings', 'VariablePrefix' ), eZPerfLoggerINI::variable( 'StatsdSettings', 'VariablePostfix' ) ) as $i => $string )
            {
                if ( strpos( $string, '$' ) !== false )
                {
                    $tokens = explode( '.', $string );
                    foreach( $tokens as $j => &$token )
                    {
                        if ( strlen( $token ) && $token[0] == '$' )
                        {
                            $token = str_replace( '.', '_', eZPerfLogger::getModuleData( substr( $token, 1 ), $default ) );
                            if ( $strip && $token == '' )
                            {
                                unset( $tokens[$j] );
                            }
                        }
                    }
                    $string = implode( '.', $tokens );
                }
                if ( $i )
                {
                    self::$postfix = $string;
                }
                else
                {
                    self::$prefix = $string;
                }
            }
        }
        return self::$prefix . $var . self::$postfix;
    }
}
