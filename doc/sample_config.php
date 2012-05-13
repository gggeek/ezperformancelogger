<?php

if ( isset( $_GET['STARTXHPROF'] ) )
{
    /// NB: php class autoloading is not yet set up at this point
    include( 'extension/ezperformancelogger/classes/ezxhproflogger.php' );
    eZXHProfLogger::start();
}
