<?php
/**
 * Munin plugin script
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 *
 * @todo in verbose mode state how many log files were rotated
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => '...',
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions(
    '',
    '',
    array() );
$script->initialize();

if ( $script->verboseOutputLevel() > 0 )
    $cli->output( "Rotating ezperformancelogger log files..."  );

$ini = eZINI::instance( 'ezperformancelogger.ini' );

// std logs
if ( $ini->variable( 'logfileSettings', 'RotateFiles' ) == 'enabled' )
{
    $logFile = $ini->variable( 'logfileSettings', 'FileName' );
    eZPerfLogger::rotateLogs( dirname( $logFile ), basename( $logFile ),
        0, $ini->variable( 'logfileSettings', 'MaxLogrotateFiles' ) );
    if ( $script->verboseOutputLevel() > 0 )
        $cli->output( "Log files rotated" );
}

// csv logs
if ( $ini->variable( 'csvSettings', 'RotateFiles' ) == 'enabled' )
{
    $logFile = $ini->variable( 'csvSettings', 'FileName' );
    eZPerfLogger::rotateLogs( dirname( $logFile ), basename( $logFile ),
        0, $ini->variable( 'logfileSettings', 'MaxLogrotateFiles' ) );
    if ( $script->verboseOutputLevel() > 0 )
        $cli->output( "Csv files rotated" );
}

$script->shutdown();

?>
