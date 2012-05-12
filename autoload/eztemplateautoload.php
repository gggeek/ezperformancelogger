<?php

// Operator autoloading

$eZTemplateOperatorArray = array();

$eZTemplateOperatorArray[] =
    array
    (
        'script' => 'extension/ezperformancelogger/autoloads/ezperformanceloggeroperators.php',
        'class' => 'eZPerformanceLoggerOperators',
        'operator_names' => array_keys( eZPerformanceLoggerOperators::$operators )
    );

?>