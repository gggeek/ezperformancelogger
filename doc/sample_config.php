<?php

// This is a sample of php code which can be added at the start of your
// config.php file to activate profiling with XHPROF.
// Profiling of a web page will only be triggered when the url used to acces it
// contains in the query string ?STARTXHPROF=1
//
// NOTE: maybe it is not a good idea to use this code in production: you would be giving your end users the possibility to slow down
// your site and fill your hard disk just by using a "hacked" url.
//
// Another useful workflow could be to enable profiling on 1 page every 100, using a random-variable test.
// This way you would be able to get statistically useful data from real-life users while avoiding slowing down every sigle page

if ( isset( $_GET['STARTXHPROF'] ) )
{
    /// NB: php class autoloading is not yet set up at this point
    include( 'extension/ezperformancelogger/classes/ezxhproflogger.php' );
    eZXHProfLogger::start();
}
