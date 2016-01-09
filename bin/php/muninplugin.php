<?php
/**
 * Munin plugin script
 *
 * This script implements a munin wildcard plugin.
 * Designed to be invoked via the shell script ezmuninperflogger_
 *
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2016
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 *
 * @todo add support for "install" and "uninstall" commands
 * @todo add support for using apache-formatted log as source for munin
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => 'Makes available perf. variables for munin graphs',
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions(
    '[variable:][range:]', // options
    '[command]', // params
    array() );
$script->initialize();

if ( count( $options['arguments'] ) )
{
    $command = $options['arguments'][0];
}
else
{
    $command = 'fetch';
}

// the way that munin wildcard plugins work is that many symlinks are created to the
// plugin, appending graph name to the original plugin file name. The shell script
// will pass us its own filename in the 'variable' option
$variable = isset( $options['variable'] ) ? $options['variable'] : '';
$variable = preg_replace( '/^ezmuninperflogger_/', '', $variable );

// default munin range: 5 minutes
$range = $options['range'] ? $options['range'] : 60 * 5;

$ini = eZINI::instance( 'ezperformancelogger.ini' );

switch ( $command )
{
    case 'autoconf':
        // This command is called by munin to know if all config needed by this plugin has been done right.
        // If the php script can actually run succesfully, this means it has (config is needed to tell
        // Munin where php is and where this script is)

        $siteIni = eZINI::instance();
        if ( !eZPerfLogger::isEnabled() )
        {
            $cli->output( "no (extension ezperformancelogger not enabled)" );
            $script->shutdown();
        }
        if ( !in_array( 'csv', $ini->variable( 'GeneralSettings', 'LogMethods' ) ) )
        {
            $cli->output( "no (extension ezperformancelogger is not logging data to csv log files)" );
            $script->shutdown();
        }
        $cli->output( "yes" );
        $script->shutdown();
        break;

    case 'suggest':
        // This command is called by munin to get a list of graphs that this plugin supports
        // See http://munin-monitoring.org/wiki/ConcisePlugins
        foreach( array_merge( $ini->variable( 'GeneralSettings', 'TrackVariables' ), array( 'pageviews' ) ) as $var )
        {
            echo "$var\n";
        }
        break;

    case 'config':
        // This command is called by munin to get info about how to graph the measured values
        // (it is called every 5 minutes, just before 'fetch')
        echo "graph_category " . $ini->variable( 'MuninSettings', 'GroupName' ) . "\n";
        $pageviews = false;
        $title = false;
        // these loops are a bit convoluted. They allow the user to specify both generic params
        // and per-munin-variable params as well
        $muninvariables = ( $variable == 'pageviews' ? array( '', '_count' ) : array( '', '_tot', '_min', '_max' ) );
        foreach( $muninvariables as $suffix )
        {
            if ( $ini->hasVariable( 'MuninSettings', 'VariableDescription_' . $variable . $suffix ) )
            {
                foreach( $ini->variable( 'MuninSettings', 'VariableDescription_' . $variable . $suffix ) as $item => $value )
                {
                    echo $suffix == '' ? "$item $value\n" : "{$variable}{$suffix}.$item $value\n";
                    if ( $item == 'graph_title' )
                    {
                       $title = true;
                    }
                    if ( $item == 'pageviews.graph' )
                    {
                        $pageviews = true;
                    }
                }
            }
        }
        if ( !$title )
        {
            echo "graph_title $variable\n";
        }
        if ( !$pageviews && $variable != 'pageviews' )
        {
            echo "pageviews.graph no\n";
        }
        break;

    case 'fetch':
    default:
        if ( $variable == '' )
        {
            $cli->output( "Error: you are using the ezmuninperflogger_ script as munin plugin. You should create symlinks named ezmuninperflogger_\$varname instead" );
            $script->shutdown( -1 );
        }
        if ( $variable != 'pageviews' && !in_array( $variable, $ini->variable( 'GeneralSettings', 'TrackVariables' ) ) )
        {
            $cli->output( "Error: '$variable' is not a tracked perf. variable" );
            $script->shutdown( -1 );
        }

        $logMethods = $ini->variable( 'GeneralSettings', 'LogMethods' );
        if ( in_array( 'csv', $logMethods ) )
        {
            eZPerfLoggerMemStorage::resetStats();
            $ok = eZPerfLoggerLogManager::updateStatsFromLogFile( $ini->variable( 'csvSettings', 'FileName' ), 'eZPerfLoggerCSVStorage', 'eZPerfLoggerMemStorage', "muninplugin-$variable-csv.log", array( '/^URL$/' ) );
            if ( $samples = eZPerfLoggerMemStorage::getStatsCount() )
            {
                if ( $variable != 'pageviews' )
                {
                    $values = eZPerfLoggerMemStorage::getStats( $variable );
                    //$values['avg'] = $values['total'] / $samples;
                    echo "{$variable}_tot.value {$values['total']}\n";
                    echo "{$variable}_min.value {$values['min']}\n";
                    echo "{$variable}_max.value {$values['max']}\n";
                    echo "pageviews.value $samples\n";
                }
                else
                {
                    echo "pageviews_count.value $samples\n";
                }
            }
            else
            {
                if ( $variable != 'pageviews' )
                {
                    // Either no logfile or no samples in range

                    // Slight difference between reporting 0 and U (unknown == null):
                    // . U means no point in the graph
                    // . 0 will be graphed as 0
                    // We are not reporting a "per page" values, but "pe interval" ones,
                    // it thus makes sense to report 0 when there are no pageviews in the interval.
                    // This subject is probably still subject to debate...
                    $val = ( $ok ? '0' : 'U' );
                    echo "{$variable}_tot.value $val\n";
                    echo "{$variable}_min.value $val\n";
                    echo "{$variable}_max.value $val\n";
                    echo "pageviews.value $val\n";
                }
                else
                {
                    echo "pageviews_count.value " . ( $ok ? '0' : 'U' ) . "\n";
                }
            }

        }
        else if ( in_array( 'logfile', $logMethods ) || in_array( 'apache', 'logmethods' ) )
        {
            $cli->output( "Error: can not report variables because ezperflogger set to write data to Apache-formatted log files. Only csv files supported so far" );
            $script->shutdown( -1 );
            /// @todo
            /*
            $logFile = ...;
            eZPerfLoggerMemStorage::resetStats();
            $ok = eZPerfLoggerLogManager::updateStatsFromLogFile( $logFile, 'eZPerfLoggerApacheLogger', 'eZPerfLoggerMemStorage', "muninplugin-$variable-apache.log" );
            if ( eZPerfLoggerMemStorage::getStatsCount() )
            {
                $values = eZPerfLoggerMemStorage::getStats( $variable );
                $values['avg'] = $values['total'] / $samples;
                echo "{$variable}_avg.value {$values['avg']}\n";
                echo "{$variable}_min.value {$values['min']}\n";
                echo "{$variable}_max.value {$values['max']}\n";
            }
            else
            {
                // no logfile or no samples in range
                echo "{$variable}_avg.value U\n";
                echo "{$variable}_min.value U\n";
                echo "{$variable}_max.value U\n";
            }
            */
        }
        else
        {
            $cli->output( "Error: can not report variables because ezperflogger is not set to write data to log files" );
            $script->shutdown( -1 );
        }
}

$script->shutdown();
