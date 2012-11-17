<?php

// This is a sample of php code which can be added at the start of your
// config.php file to activate profiling with XHPROF.
// Profiling of a web page will only be triggered when the url used to acces it
// contains in the query string ?STARTXHPROF=1

if ( isset( $_GET['STARTXHPROF'] ) )
{
    /// NB: php class autoloading is not yet set up at this point
    include( 'extension/ezperformancelogger/classes/ezxhproflogger.php' );
    eZXHProfLogger::start();
}
