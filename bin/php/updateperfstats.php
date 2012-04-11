<?php
/**
 * Same as cronjob, for one-shot runs
 */

require 'autoload.php';

$cli = eZCLI::instance();
$endl = $cli->endlineString();

$script = eZScript::instance( array( 'description' => '...',
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions(
    '',
    '', //'[logfile]',
    array() );
$script->initialize();

set_time_limit( 0 );

if ( $script->verboseOutputLevel() > 0 )
    $cli->output( "Updating perf counters..."  );

$dt = new eZDateTime();
$year = $dt->year();
$month = date( 'M', time() );
$day = $dt->day();
$hour = $dt->hour();
$minute = $dt->minute();
$second = $dt->second();
$startTime = $day . "/" . $month . "/" . $year . ":" . $hour . ":" . $minute . ":" . $second;

$cli->output( "Started at " . $dt->toString()  );

$contentArray = array();
$logFilePath = '';
$plIni = eZINI::instance( 'ezperformancelogger.ini' );
$logTo = $plIni->variable( 'GeneralSettings', 'LogMethods' );
if ( in_array( 'apache', $logTo ) && !in_array( 'logfile', $logTo ) )
{
    $logFileIni = eZINI::instance( 'logfile.ini' );
    $logFilePath = $logFileIni->variable( 'AccessLogFileSettings', 'StorageDir' ) . '/' . $logFileIni->variable( 'AccessLogFileSettings', 'LogFileName' );
}
else if ( !in_array( 'apache', $logTo ) && in_array( 'logfile', $logTo ) )
{
    $logFilePath = $plIni->variable( 'GeneralSettings', 'PerfLogFileName' );
}
else
{
    $cli->error( "Cannot decide which log-file to open for reading, please enable either apache-based logging or file-based logging." );
    $script->shutdown( 1 );
    return;
}

if ( $logFilePath != '' )
{
    $ok = eZPerfLogger::parseLog( $logFilePath );
    if ( $ok === false )
    {
        $cli->output( "Error parsing file $logFilePath. Please run cronjob in debug mode for more info" );
    }
    else
    {
        $cli->output( "$ok lines parsed in file $logFilePath" );
    }
}


$dt = new eZDateTime();
$cli->output( "Finished at " . $dt->toString() . "\n"  );
if ( $script->verboseOutputLevel() > 0 )
    $cli->output( "Perf counters have been updated!\n" );

$script->shutdown();

?>
