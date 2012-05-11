<?php
/**
 * @author Gaetano Giunta
 * @copyright (c) 2009-2012 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$Module = array( 'name' => 'xhprof' );

$ViewList = array();

$ViewList['list'] = array(
    'script' => 'list.php',
    'functions' => 'view',
    'params' => array(),
    'unordered_params' => array( 'offset' => 'Offset' ),
    'default_navigation_part' => 'ezsetupnavigationpart'
);

$ViewList['view'] = array(
    'script' => 'view.php',
    'functions' => 'view',
    'params' => array( 'alterview' ), // for ease of development, we use GET params for everything
    'default_navigation_part' => 'ezsetupnavigationpart'
);

$ViewList['callgraph.php'] = array(
    'script' => 'callgraph.php',
    'functions' => 'view',
    'params' => array(),
    'default_navigation_part' => 'ezsetupnavigationpart'
);

$ViewList['typeahead.php'] = array(
    'script' => 'typeahead.php',
    'functions' => 'view',
    'params' => array(),
    'default_navigation_part' => 'ezsetupnavigationpart'
);

$FunctionList = array(
    'view' => array()
);

?>