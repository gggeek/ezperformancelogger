<?php
/**
* An 'output filter' class that does not filter anything, but passes some info
* to apache that can log it in the access logs
*
* @todo add logging of nr. of queries
* @todo log total elapsed time (interesting to compare to Apache's own time measure)
* @todo add an ini file with the name of file to log to ('apache' for apache notes)
*/
class ezPerfLogger
{
    static function filter( $output )
    {
        apache_note( 'mem_usage', memory_get_peak_usage( true ) );
        return $output;
    }
}

?>