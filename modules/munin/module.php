<?php
/**
 * @author Gaetano Giunta
 * @copyright  (C) eZ Systems AS 2009-2016
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
