<?php
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// XHProf: A Hierarchical Profiler for PHP
//
// XHProf has two components:
//
//  * This module is the UI/reporting component, used
//    for viewing results of XHProf runs from a browser.
//
//  * Data collection component: This is implemented
//    as a PHP extension (XHProf).
//
//
//
// @author(s)  Kannan Muthukkaruppan
//             Changhao Jiang
//

// patched by GG to become an ezp view

// we need to play tricky games to obey the std url convention of xhprof lib...
if ( $Params['alterview'] == 'callgraph.php' || $Params['alterview'] == 'typeahead.php' )
{
    $Module = $Params['Module'];
    //$Module->redirectToView( $Params['alterview'] );
    foreach( $_GET as $k => $v )
    {
        $_GET[$k] = "$k=$v";
    }
    $Module->redirectTo( $Module->redirectionURIForModule( $Module, $Params['alterview'] ). '?' . implode( '&', $_GET ) );
}

$error = "";

// by default assume that xhprof_html & xhprof_lib directories
// are at the same level.
$GLOBALS['XHPROF_LIB_ROOT'] = __DIR__ . '/../../lib/xhprof';

require_once $GLOBALS['XHPROF_LIB_ROOT'].'/display/xhprof.php';

// patch the base path
$GLOBALS['base_path'] = '/xhprof/view';
eZURI::transformURI( $GLOBALS['base_path'] );
//$GLOBALS['base_path'] =  //$base_path;

// param name, its type, and default value
$params = array('run'        => array(XHPROF_STRING_PARAM, ''),
                'wts'        => array(XHPROF_STRING_PARAM, ''),
                'symbol'     => array(XHPROF_STRING_PARAM, ''),
                'sort'       => array(XHPROF_STRING_PARAM, 'wt'), // wall time
                'run1'       => array(XHPROF_STRING_PARAM, ''),
                'run2'       => array(XHPROF_STRING_PARAM, ''),
                'source'     => array(XHPROF_STRING_PARAM, 'xhprof'),
                'all'        => array(XHPROF_UINT_PARAM, 0),
                );

ob_start();

// pull values of these params, and create named globals for each param
try
{
    xhprof_param_init($params);
    ////ez_xhprof_param_init($params);
}
catch( exception $e )
{
    $error = $e->getMessage();
}


/* reset params to be a array of variable names to values
   by the end of this page, param should only contain values that need
   to be preserved for the next page. unset all unwanted keys in $params.
 */
foreach ($params as $k => $v) {
  $params[$k] = @$GLOBALS[$k];

  // unset key from params that are using default values. So URLs aren't
  // ridiculously long.
  if ($params[$k] == $v[1]) {
    unset($params[$k]);
  }
}

////echo "<html>";

////echo "<head><title>XHProf: Hierarchical Profiler Report</title>";
////xhprof_include_js_css();
////echo "</head>";

////echo "<body>";

$vbar  = ' class="vbar"';
$vwbar = ' class="vwbar"';
$vwlbar = ' class="vwlbar"';
$vbbar = ' class="vbbar"';
$vrbar = ' class="vrbar"';
$vgbar = ' class="vgbar"';

$xhprof_runs_impl = new XHProfRuns_Default( eZXHProfLogger::logDir() );

displayXHProfReport( $xhprof_runs_impl, $params, $GLOBALS['source'], $GLOBALS['run'], $GLOBALS['wts'],
                    $GLOBALS['symbol'], $GLOBALS['sort'], $GLOBALS['run1'], $GLOBALS['run2'] );


////echo "</body>";
////echo "</html>";

$body = ob_get_clean();

$info = false;
$infoFile = eZXHProfLogger::logDir() . "/{$GLOBALS['run']}.info";
if ( file_exists( $infoFile ) )
{
    $info = eZPerfLoggerApacheLogger::parseLogLine( file_get_contents( $infoFile ) );
}

$tpl = eZTemplate::factory();
$tpl->setVariable( 'body', $body );
$tpl->setVariable( 'error', $error );
$tpl->setVariable( 'run', $GLOBALS['run'] );
$tpl->setVariable( 'info', $info );
$Result['content'] = $tpl->fetch( 'design:xhprof/view.tpl' );
$Result['path'] = array(
    array(
        'text'   => 'XHProf',
        'url'    => 'xhprof/list' ),
    array(
        'text'   => 'Run: ' . $GLOBALS['run'],
        'url'    => 'xhprof/view?run=' . $GLOBALS['run'] ),
);
