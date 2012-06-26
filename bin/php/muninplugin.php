<?php
/**
 * Munin plugin script
 *
 * This script implements a munin wildcard plugin.
 * Designed to be invoked via the shell script ezmuninperflogger_
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 *
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
$variable = preg_replace( '/$ezmuninperflogger_/', '', $variable );

// default munin range: 5 minutes
$range = $options['range'] ? $options['range'] : 60 * 5;

$ini = eZINI::instance( 'ezperformancelogger.ini' );

switch ( $command )
{
    case 'autoconf':
        /// @todo
        $siteIni = eZINI::instance();
        if ( !in_array( 'ezperformancelogger', $siteIni->variable( 'ExtensionSettings', 'ActiveExtensions' ) ) )
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
        // this command is called by munin to get a list of graphs that this plugin supports
        // see http://munin-monitoring.org/wiki/ConcisePlugins
        foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $var )
        {
            echo "$var\n";
        }
        break;

    case 'config':
        echo "graph_category eZ Performance Logger\n";
        $title = false;
        foreach( array( '', '_avg', '_min', '_max' ) as $suffix )
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
                }
            }
        }
        if ( !$title )
        {
            echo "graph_title $variable\n";

        }
        break;

    case 'fetch':
    default:
        if ( $variable == '' || !in_array( $variable, $ini->variable( 'GeneralSettings', 'TrackVariables' ) ) )
        {
            $cli->output( "Error: '$variable' is not a tracked perf. variable" );
            $script->shutdown( -1 );
        }

        $logMethods = $ini->variable( 'GeneralSettings', 'LogMethods' );
        if ( in_array( 'csv', $logMethods ) )
        {
            $samples = 0;
            $values = array( 'total' => 0 );

            if ( file_exists( $logfile = $ini->variable( 'csvSettings', 'FileName' ) ) )
            {
                $fp = fopen( $logfile, 'r' );
                if ( $fp )
                {
                    $now = time();
                    $then = $now - $range;
                    $separator = $ini->variable( 'csvSettings', 'Separator' );
                    // we count from 1, as 1st element in csv is timestamp
                    $i = 1;
                    foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $varname )
                    {
                        if ( $varname == $variable )
                        {
                            $pos = $i;
                            break;
                        }
                        ++$i;
                    }

                    while ( ( $buffer = fgets( $fp, 8192 ) ) !== false )
                    {
                        $ts = substr( $buffer, 0, strpos( $buffer, $separator ) );
                        /// @todo !important optimization: do not check ts for lower bound after 1st valid one found
                        if ( $ts >= $then )
                        {
                            if ( $ts > $now )
                            {
                                break;
                            }

                            ++$samples;
                            $data = explode( $separator, $buffer );
                            $val = $data[$pos];
                            if ( !isset( $values['min'] ) || $val < $values['min'] )
                            {
                                $values['min'] = $val;
                            }
                            if ( !isset( $values['max'] ) || $val > $values['max'] )
                            {
                                $values['max'] = $val;
                            }
                            $values['total'] = $values['total'] + $val;
                        }
                    }
                    fclose( $fp );
                }
            }

            if ( $samples )
            {
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

        }
        else if ( in_array( 'logfile', $logMethods ) || in_array( 'apache', 'logmethods' ) )
        {
            /// @todo
            $cli->output( "Error: can not report variables because ezperflogger set to write data to Apache-formatted log files. Only csv files supported so far" );
            $script->shutdown( -1 );
        }
        else
        {
            $cli->output( "Error: can not report variables because ezperflogger is not set to write data to log files" );
            $script->shutdown( -1 );
        }
}

$script->shutdown();

?>
