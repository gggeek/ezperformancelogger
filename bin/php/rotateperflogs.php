<?php
/**
 * Rotate log files produced by this extension - all logfiles are rotated once NOW, regardless of size
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 *
 * @todo encapsulate logic into php classes
 * @todo add options to allow caller to specify which logs to rotate
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
}

// csv logs
if ( $ini->variable( 'csvSettings', 'RotateFiles' ) == 'enabled' )
{
    $logFile = $ini->variable( 'csvSettings', 'FileName' );
    eZPerfLogger::rotateLogs( dirname( $logFile ), basename( $logFile ),
        0, $ini->variable( 'logfileSettings', 'MaxLogrotateFiles' ) );
}

if ( $script->verboseOutputLevel() > 0 )
    $cli->output( "Log files rotated" );

$script->shutdown();

?>
