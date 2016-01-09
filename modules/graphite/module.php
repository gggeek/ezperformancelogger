<?php
/**
 * @author Gaetano Giunta
 * @copyright (c) 2013-2016 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$Module = array( 'name' => 'graphite' );

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
