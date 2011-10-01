<?php
/**
* An 'output filter' class that does not filter anything, but logs some perf values
* to different "log" types
*
* @todo log total cluster queries (see code in ezdebug extension)
*/
class ezPerfLogger
{
    static function filter( $output )
    {
        global $scriptStartTime;

        $ini = eZINI::instance( 'ezperformancelogger.ini' );

        $values = array();
        foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
        {
            switch( $var )
            {
                case 'mem_usage':
                    $values[$i] = round( memory_get_peak_usage( true ), -3 );
                    break;
                case 'execution_time':
                    $values[$i] = round( microtime( true ) - $scriptStartTime, 3 ); /// @todo test if $scriptStartTime is set
                    break;
                case 'db_queries':
                    // (nb: only works when debug is enabled?)
                    $dbini = eZINI::instance();
                    // we cannot use $db->databasename() because we get the same for mysql and mysqli
                    $type = preg_replace( '/^ez/', '', $dbini->variable( 'DatabaseSettings', 'DatabaseImplementation' ) );
                    $type .= '_query';
                    // read accumulator
                    $debug = eZDebug::instance();
                    if ( isset( $debug->TimeAccumulatorList[$type] ) )
                    {
                        $values[$i] = $debug->TimeAccumulatorList[$type]['count'];
                    }
                    else
                    {
                        $values[$i] = "0"; // can not tell between 0 reqs per page and no debug...
                    }
                    break;
            }
        }

        foreach( $ini->variable( 'GeneralSettings', 'LogMethods' ) as $method )
        {
            switch( $method )
            {
                case 'apache':
                    foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                    {
                        apache_note( $var, $values[$i] );
                    }
                    break;

                case 'piwik':
                    $text = '';
                    foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                    {
                        $text .= "\npiwikTracker.setCustomVariable( $i, \"$var\", \"{$values[$i]}\", \"page\" );";
                    }
                    $text .= "\npiwikTracker.trackPageView();";
                    $output = preg_replace( '/piwikTracker\.trackPageView\( *\);?/', $text, $output );
                    break;

                case 'googleanalytics':
                    $text = '';
                    foreach( $ini->variable( 'GeneralSettings', 'TrackVariables' ) as $i => $var )
                    {
                        $text .= "\n_gaq.push([$i, '$var', '{$values[$i]}', 3]);";
                    }
                    $text .= "\n_gaq.push(['_trackPageview']);";
                    $output = preg_replace( "/_gaq.push\( *[ *['\"]_trackPageview['\"] *] *\);?/", $text, $output );
                    break;

                /// @todo
                /*case 'logfile':
                    ;
                    break;
                case 'database':
                    ;
                    break;*/

            }
        }

        return $output;
    }
}

?>