<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

if ( !$isQuiet )
    $cli->output( "Removing XHProf saved runs data..."  );

eZXHProfLogger::removeSavedRuns();

if ( !$isQuiet )
    $cli->output( "XHProf saved runs data removed" );

?>
