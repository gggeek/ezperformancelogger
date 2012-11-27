<?php
/**
 * A script used to extract urls from Apache access logs.
 *
 * Desired behaviour (not yet fully implemented)
 * . urls can be sorted by frequency, last/first access time, alphabetically...  (currently: only count or access time)
 * . url parsing can include/exclude query string (default: exclude)
 * . url parsing can include/exclude ez unordered view parameters (default: exclude)
 * . urls to list can include/exclude static resources (default: exclude)
 * . urls to list can be filtered by regexp
 * . url list can be limited to "top N"
 * . one of the gathered stats can be listed for each url (min, max, avg) (currently: only count)
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
    '[logfile:][limit:][sort:][excludefilter:][data:][keep-querystring][keep-viewparams][alsostatic]',
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

// set options to log parser
$opts = array();
if ( $options['keep-querystring'] !== null )
{
    $opts['keep_query_string'] = true;
}
if ( $options['keep-viewparams'] !== null )
{
    $opts['keep_view_params'] = true;
}
eZPerfLoggerUrlExtractorStorage::setOptions( $opts );

$exclude = array();
if ( $options['alsostatic'] == null )
{
    // remove all known static paths - list taken from .htaccess
    /// @todo we should try to understand if we have to anchor regexp to url root (ie if we are in vhost mode)
    $exclude[] = '#.*/var/([^/]+/)?storage/images(-versioned)?/.*#';
    $exclude[] = '#.*/var/([^/]+/)?cache/(texttoimage|public)/.*#';
    $exclude[] = '#.*/design/[^/]+/(stylesheets|images|javascript)/.*#';
    $exclude[] = '#.*/share/icons/.*#';
    $exclude[] = '#.*/extension/[^/]+/design/[^/]+/(stylesheets|flash|images|lib|javascripts?)/.*#';
    $exclude[] = '#.*/packages/styles/.+/thumbnail/.*#';
    $exclude[] = '#.*/var/storage/packages/.*#';
}

if ( $options['excludefilter'] !== null )
{
    $exclude[] = '#' . str_replace(  '#', '\#', $options['excludefilter'] ) . '#';
}

$ok = eZPerfLoggerLogManager::updateStatsFromLogFile( $logFilePath, 'eZPerfLoggerApacheLogger', 'eZPerfLoggerUrlExtractorStorage', null, $exclude, true );

$stats = eZPerfLoggerUrlExtractorStorage::getStats();

/// @todo sort urls based on inverse access time / name / frequency
if ( $options['sort'] == '' || $options['sort'] == 'count' )
{
    foreach ($stats as $key => $row)
    {
        $count[$key]  = $row['count'];
        $url[$key] = $row['url'];
    }
    // Add $data as the last parameter, to sort by the common key
    array_multisort( $count, SORT_DESC, $url, SORT_ASC, $stats );
}

$i = 0;
foreach ( $stats as $idx => $data )
{
    /// @todo shall we avoid printing empty lines?

    if ( $options['data'] == '' || $options['data'] == 'count' )
    {
        echo "[{$data['count']}] ";
    }

    echo "{$data['url']}\n";

    $i++;
    if ( $options['limit'] !== null && $i >= $options['limit'] )
    {
        break;
    }
}

$script->shutdown();

?>