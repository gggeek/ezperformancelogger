<?php
/**
* An 'output filter' class that does not filter anything, but passes some info
* to apache that can log it in the access logs
*
* @todo add some ini setting deciding what to log
* @todo log total elapsed time (interesting to compare to Apache's own time measure)
* @todo log total cluster queries (see code in ezdebug extension)
* @todo add an ini file with the name of file to log to ('apache' for apache notes)
*/
class ezPerfLogger
{
    static function filter( $output )
    {
        apache_note( 'mem_usage', memory_get_peak_usage( true ) );

        /* add number of db queries too (nb: only works when debug is enabled? */
        /*$ini = eZINI::instance();
        // we cannot use $db->databasename() because we get the same for mysql and mysqli
        $type = preg_replace( '/^ez/', '', $ini->variable( 'DatabaseSettings', 'DatabaseImplementation' ) );
        $type .= '_query';
        // read accumulator
        $debug = eZDebug::instance();
        if ( isset( $debug->TimeAccumulatorList[$type] ) )
        {
            $num = $debug->TimeAccumulatorList[$type]['count'];
            apache_note( 'num_queries', $num );
        }*/

        return $output;
    }
}

?>