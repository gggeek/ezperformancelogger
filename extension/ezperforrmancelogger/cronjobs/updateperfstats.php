<?php
/**
 * Script originally copied over from updateviewcount.php
 */

set_time_limit( 0 );

if ( !$isQuiet )
    $cli->output( "Updating perf counters..."  );

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
    $cli->output( "Warning: Cannot decide which log-file to open for reading, please enable either apache-based logging or file-based logging." );
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
        if ( !$isQuiet )
            $cli->output( "$ok lines parsed in file $logFilePath" );
    }
}


if ( !$isQuiet )
    $cli->output( "Perf counters have been updated!" );

?>
