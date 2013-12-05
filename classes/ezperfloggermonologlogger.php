<?php
/**
 * Class used to log KPI data via Monolog
 *
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

use Monolog\Logger;

class eZPerfLoggerMonologLogger implements eZPerfLoggerLogger
{
    public static function supportedLogMethods()
    {
        return array( 'monolog' );
    }

    public static function doLog( $logmethod, array $data, &$output )
    {
        $log = new Logger( 'ezperflogger' );

        // constructor args for the specific handler can be set via ini
        /// @todo how to create resources instead?
        foreach( eZPerfLoggerINI::variable( 'MonologSettings', 'LogHandlers' ) as $handlerName )
        {
            $handlerClass = 'Monolog\Handler\\' . $handlerName . "Handler";
            if ( eZPerfLoggerINI::hasVariable( 'MonologSettings', 'LogHandler_' . $handlerName ) )
            {
                $r = new ReflectionClass( $handlerClass );
                $handler = $r->newInstanceArgs( eZPerfLoggerINI::variable( 'MonologSettings', 'LogHandler_' . $handlerName ) );
            }
            else
            {
                $handler = new $handlerClass();
            }
            $log->pushHandler( $handler );
        }

        // the default severity level: taken from config file
        $level =  (int)eZPerfLoggerINI::variable( 'MonologSettings', 'SeverityLevel' );
        // either coalesce messages or not: taken from config file
        if ( eZPerfLoggerINI::variable( 'MonologSettings', 'Coalescevariables' ) == 'enabled' )
        {
            /// @todo allow via ini file the specification of custom formatters?
            $msg = array();
            foreach( $data as $varname => $value )
            {
                $msg[] = "$varname: $value";
            }
            $log->addRecord( $level, implode( ', ', $msg ) );
        }
        else
        {
            foreach( $data as $varname => $value )
            {
                $log->addRecord( $level, "$varname: $value" );
            }
        }
    }
}

?>