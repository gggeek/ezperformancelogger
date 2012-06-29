<?php
/**
 * Rotate log files produced by this extension
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 *
 * @todo encapsulate logic into php classes
 */

if ( !$isQuiet )
    $cli->output( "Rotating ezperformancelogger log files..."  );

$ini = eZINI::instance( 'ezperformancelogger.ini' );

// std logs
if ( $ini->variable( 'logfileSettings', 'RotateFiles' ) == 'enabled' )
{
    $logFile = $ini->variable( 'logfileSettings', 'PerfLogFileName' );
    eZPerfLoggerLogManager::rotateLogs( dirname( $logFile ), basename( $logFile ),
        $ini->variable( 'logfileSettings', 'MaxLogSize' ), $ini->variable( 'logfileSettings', 'MaxLogrotateFiles' ) );
}

// csv logs
if ( $ini->variable( 'csvSettings', 'RotateFiles' ) == 'enabled' )
{
    $logFile = $ini->variable( 'csvSettings', 'FileName' );
    eZPerfLoggerLogManager::rotateLogs( dirname( $logFile ), basename( $logFile ),
        $ini->variable( 'logfileSettings', 'MaxLogSize' ), $ini->variable( 'logfileSettings', 'MaxLogrotateFiles' ) );
}

if ( !$isQuiet )
    $cli->output( "Log files rotated" );

?>
