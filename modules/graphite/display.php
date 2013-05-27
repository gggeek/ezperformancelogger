<?php
/**
 * @author Gaetano Giunta
 * @copyright (c) 2009-2013 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$ini = eZINI::instance( 'ezperformancelogger.ini' );
$graphiteUrl = $ini->variable( 'StatsdSettings', 'GraphiteURL' );

$tpl = eZTemplate::factory();
if ( $graphiteUrl != '' )
{
    /// @todo do not give user direct access to munin, use eZ to get it,
    ///       acting as reverse proxy
    /*$out = eZHTTPTool::getDataByURL( $muninUrl );
       if ( $out === false )
       {
       $tpl->setVariable( 'error', "Error retrieving Munin pages" );
       }
       else
       {
       $body = preg_replace( '/^.+<body>/', '', $out );
       $body = preg_replace( '#</body>.+$#', '', $body );
       $tpl->setVariable( 'body', $body );
       }*/

    $tpl->setVariable( 'url', $graphiteUrl );
}
else
{
    $tpl->setVariable( 'error', "Graphite not enabled, nothing to see here" );
}

$Result['content'] = $tpl->fetch( 'design:graphite/display.tpl' );
$Result['path'] = array(
    array(
        'text'   => 'Graphite',
        'url'    => 'graphite/display' )
);
