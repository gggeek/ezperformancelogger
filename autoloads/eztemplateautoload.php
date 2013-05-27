<?php

// Operator autoloading

$eZTemplateOperatorArray = array();

$eZTemplateOperatorArray[] =
    array
    (
        'class' => 'eZPerformanceLoggerOperators',
        'operator_names' => array_keys( eZPerformanceLoggerOperators::$operators )
    );

?>