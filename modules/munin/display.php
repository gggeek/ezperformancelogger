<?php
/**
 * @author Gaetano Giunta
 * @copyright  (C) eZ Systems AS 2009-2016
 * @license code licensed under the GPL License: see README
 */

$ini = eZINI::instance( 'ezperformancelogger.ini' );
$muninUrl = $ini->variable( 'MuninSettings', 'MuninURL' );

$tpl = eZTemplate::factory();
if ( $muninUrl != '' )
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

    $tpl->setVariable( 'url', $muninUrl );
}
else
{
    $tpl->setVariable( 'error', "Munin not enabled, nothing to see here" );
}

$Result['content'] = $tpl->fetch( 'design:munin/display.tpl' );
$Result['path'] = array(
    array(
        'text'   => 'Munin',
        'url'    => 'munin/display' )
);
