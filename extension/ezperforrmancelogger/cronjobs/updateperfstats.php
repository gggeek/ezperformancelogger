<?php
/**
 * Script copied over from updateviewcount.php of eZP 4.5 and patched
 */

set_time_limit( 0 );

if ( !$isQuiet )
    $cli->output( "Updating perf counters..."  );


$dt = new eZDateTime();
$year = $dt->year();
$month = date( 'M', time() );
$day = $dt->day();
$hour = $dt->hour();
$minute = $dt->minute();
$second = $dt->second();
$startTime = $day . "/" . $month . "/" . $year . ":" . $hour . ":" . $minute . ":" . $second;

$cli->output( "Started at " . $dt->toString() . "\n"  );
//$nodeIDArray = array();

//$pathArray = array();

$contentArray = array();

//$nonContentArray = array();

//$ini = eZINI::instance();
$logFileIni = eZINI::instance( 'logfile.ini' );
$plIni = eZINI::instance( 'ezperformacelogger.ini' );
$logTo = $plIni->variable( 'GeneralSettings', 'LogMethods' );
if ( in_array( 'apache', $logTo ) && !in_array( 'logfile', $logTo ) )
{
    $logFilePath = $logFileIni->variable( 'AccessLogFileSettings', 'StorageDir' ) . '/' . $logFileIni->variable( 'AccessLogFileSettings', 'LogFileName' );

}
else if ( !in_array( 'apache', $logTo ) && in_array( 'logfile', $logTo ) )
{
    $logFilePath = $plIni->variable( 'GeneralSettings', 'PerfLogFileName' );
}
else
{
    $cli->output( "Warning: Cannot decide which log-file to open for reading, please enable either apache-based logging or file-based logging." );
}

$ok = eZPerfLogger::parseLog( $logFilePath );

if ( $ok === false )
{
    $cli->output( "Error parsing file $logFilePath. Please run cronjob in debug mode for more info" );
}
else
{
    $cli->output( "$ok lines parsed in file $logFilePath" );
}

$dt = new eZDateTime();
$cli->output( "Finished at " . $dt->toString() . "\n"  );
if ( !$isQuiet )
    $cli->output( "Perf counters have been updated!\n" );

?>
