<?php
/**
 * @author Gaetano Giunta
 * @copyright (c) 2009-2012 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$limit = 25;
$offset = (int) $Params['Offset'];

$runsList = array();
$count = 0;
// nb: PHP_SCANDIR_SORT_DESCENDING === 1, but only defined since php 5.4.0
foreach( scandir( eZXHProfLogger::logDir(), 1 ) as $file )
{
    $fullfile = eZXHProfLogger::logDir() . "/" . $file;
    if ( is_file( $fullfile ) && substr( $file, -7 ) == '.xhprof' )
    {
        $count++;
        if ( $count >= $offset  && count( $runsList ) <= $limit )
        {
            $run = substr( $file, 0, -7 );
            $runsList[$run] = array(
                // start out by taking run time as file time (if info file is there, itwill overwrite this value)
                'time' => filemtime( $fullfile )
            );
            if ( is_file( $infoFile = eZXHProfLogger::logDir() . "/$run.info" ) )
            {
                if ( is_array( $info = eZPerfLogger::parseLogLine( file_get_contents( $infoFile ) ) ) )
                {
                    $runsList[$run] = $info;
                }
            }
        }
    }
}

$tpl = eZTemplate::factory();
$tpl->setVariable( 'runs_list', $runsList );
$tpl->setVariable( 'limit', $limit );
$tpl->setVariable( 'count', $count );
$tpl->setVariable( 'view_parameters', array( 'offset' => $offset ) );

$Result['content'] = $tpl->fetch( 'design:xhprof/list.tpl' );
$Result['path'] = array(
    array(
        'text'   => 'XHProf',
        'url'    => 'xhprof/list' )
);
