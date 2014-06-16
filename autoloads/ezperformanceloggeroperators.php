<?php
/**
 * @author G. Giunta
 * @copyright (C) 2012-2014 G. Giunta
 * @license code licensed under the GPL License: see LICENSE file
 **/

class eZPerformanceLoggerOperators {

     static $operators = array(
         'xhprof_start' => array(
            'flags' => array(
                 'type' => 'integer',
                 'required' => false,
                 'default' => 0
             ),
             'options' => array(
                 'type' => 'array',
                 'required' => false,
                 'default' => array()
             ),
         ),
         'xhprof_stop' => array(
             'dosave' => array(
                 'type' => 'boolean',
                 'required' => false,
                 'default' => true
             ),
         ),
         'record_value' => array(
             'name' => array(
                 'type' => 'string',
                 'required' => true
             )
         ),
         'make_global' => array(
             'value' => array(
                 'type' => 'mixed',
                 'required' => true
             ),
             'name' => array(
                 'type' => 'string',
                 'required' => true
             )
         )
     );

    /**
     Returns the operators in this class.
     @return array
    */
    function operatorList()
    {
        return array_keys( self::$operators );
    }

    /**
     @return true to tell the template engine that the parameter list
     exists per operator type; this is needed for operator classes
     that have multiple operators.
    */
    function namedParameterPerOperator() {
        return true;
    }

    /**
     @see eZTemplateOperator::namedParameterList()
     @return array
    */
    function namedParameterList() {
        return self::$operators;
    }

    /**
     Executes the needed operator(s).
     Checks operator names, and calls the appropriate functions.
    */
    function modify( $tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, &$operatorValue, $namedParameters ) {
        switch ($operatorName)
        {
            case 'xhprof_start':
                eZXHProfLogger::start( $namedParameters['flags'], $namedParameters['options'] );
                $operatorValue = null;
                break;
            case 'xhprof_stop':
                eZXHProfLogger::stop( $namedParameters['dosave'] );
                $operatorValue = null;
                break;
            case 'record_value':
                eZPerfLogger::recordValue( $namedParameters['name'], $operatorValue );
                $operatorValue = null;
                break;
            case 'make_global':
                /// @todo investigate: shal we use copy if $operatorValue is an object?
                $GLOBALS[$namedParameters['name']] = $namedParameters['value'];
                $operatorValue = null;
        }
    }

}
?>