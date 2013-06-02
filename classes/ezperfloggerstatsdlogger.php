<?php
/**
 * Class used to log data to Statsd.
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2013
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
        $ini = eZINI::instance( 'ezperformancelogger.ini' );
        $host = $ini->variable( 'StatsdSettings', 'Host' );
        $port = $ini->variable( 'StatsdSettings', 'Port' );
        $types = $ini->variable( 'StatsdSettings', 'VariableTypes' );

        // Failures in any of this should be silently ignored
        try
        {
            $fp = fsockopen( "udp://$host", (int)$port, $errNo, $errStr );
            if ( !$fp )
            {
                eZDebug::writeWarning( "Could not open udp socket to $host:$port - $errStr", __METHOD__ );
                return;
            }
            foreach ( $data as $varName => $value )
            {
                $type = isset( $types[$varName] ) ? $types[$varName] : 'ms';
                fwrite( $fp, static::transformVarName( $varName ) . ":{$value}|$type" );
            }
            fclose( $fp );
        } catch ( Exception $e )
        {
        }
    }

    /**
    * We cache internally prefix and postfix for optimal performances
    */
    public static function transformVarName( $var, $default=null )
    {
        if ( self::$prefix === null || self::$postfix === null )
        {
            $ini = eZINI::instance( 'ezperformancelogger.ini' );
            $strip = ( $ini->variable( 'StatsdSettings', 'RemoveEmptyTokensInVariable' ) == 'enabled' );
            foreach( array( $ini->variable( 'StatsdSettings', 'VariablePrefix' ), $ini->variable( 'StatsdSettings', 'VariablePostfix' ) ) as $i => $string )
            {
                if ( strpos( $string, '$' ) !== false )
                {
                    $tokens = explode( '.', $string );
                    foreach( $tokens as $j => &$token )
                    {
                        if ( strlen( $token) && $token[0] == '$' )
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
