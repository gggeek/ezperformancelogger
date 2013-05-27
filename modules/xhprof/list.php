<?php
/**
 * @author Gaetano Giunta
 * @copyright (c) 2009-2013 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$limit = 25;
$offset = (int) $Params['Offset'];

list( $runsList, $count ) = eZXHProfLogger::savedRuns( $offset, $limit );

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
