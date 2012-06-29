<?php
/**
 * @author Gaetano Giunta
 * @copyright (c) 2009-2012 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$Module = array( 'name' => 'munin' );

$ViewList = array();

$ViewList['display'] = array(
    'script' => 'display.php',
    'functions' => 'view',
    'params' => array(),
    'unordered_params' => array(),
    'default_navigation_part' => 'ezsetupnavigationpart'
);

$FunctionList = array(
    'view' => array()
);

?>