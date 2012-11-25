<?php
/**
 * A script used to extract urls from Apache access logs.
 *
 * Desired behaviour (not yet implemented)
 * . urls can be sorted by frequency, last/first access time, alphabetically...
 * . url parsing can include/exclude query string
 * . url parsing can include/exclude ez unordered view parameters
 * . urls to list can be filtered by regexp
 * . url list can be limited to "top N"
 * . one of the gathered stats can be listed for each url (min, max, avg)
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => 'A script used to extract urls from Apache access logs',
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions(
    '[logfile:][limit:][omit-querystring][omit-viewparams][sort:][alsostatic][filter:]',
    '',
    array() );
$script->initialize();

if ( $options['logfile'] == '' )
{
    $logFileIni = eZINI::instance( 'logfile.ini' );
    $logFilePath = $logFileIni->variable( 'AccessLogFileSettings', 'StorageDir' ) . '/' . $logFileIni->variable( 'AccessLogFileSettings', 'LogFileName' );
}
else
{
    $logFilePath = $options['logfile'];
}

if ( $logFilePath == '' || $logFilePath == '/' )
{
    $cli->error( 'Can not parse Apache log file: no file name given' );
    $script->shutdown( 1 );
}

if ( !is_file( $logFilePath ) || !is_readable( $logFilePath ) )
{
    $cli->error( "Can not parse Apache log file $logFilePath: not a file or not readable" );
    $script->shutdown( 1 );
}

$cli->output( "Parsing Apache log file $logFilePath, please be patient..." );

/// @todo set options to log parser
//eZPerfLoggerUrlExtractorStorage::setOptions();

$exclude = array();

$ok = eZPerfLoggerLogManager::updateStatsFromLogFile( $logFilePath, 'eZPerfLoggerApacheLogger', 'eZPerfLoggerUrlExtractorStorage', null, $exclude, $options['alsostatic'] !== null );

$stats = eZPerfLoggerUrlExtractorStorage::getStats();

/// @todo sort urls based on access time / name / frequency

foreach ($stats as $key => $row)
{
    $count[$key]  = $row['count'];
    $url[$key] = $row['url'];
}
// Sort the data with volume descending, edition ascending
// Add $data as the last parameter, to sort by the common key
array_multisort($count, SORT_DESC, $url, SORT_ASC, $stats);

$i = 0;
foreach ( $stats as $idx => $data )
{
    /// @todo allow omitting url frequency
    echo "[{$data['count']}] {$data['url']}\n";
    $i++;
    if ( $options['limit'] !== null && $i >= $options['limit'] )
    {
        break;
    }
}

$script->shutdown();

?>