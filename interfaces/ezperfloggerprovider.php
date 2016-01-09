<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2016
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Interface implemented by classes which provide performance data measurements.
 * To make use of such classes, declare them in ezperformancelogger.ini
 */
interface eZPerfLoggerProvider
{
    /**
     * This method is called (by the framework) to allow this class to provide
     * values for the variables it caters to.
     * @param string $output current page output
     * @param int $returnCode current http response code
     * @return array variable name => value
     */
    public static function measure( $output, $returnCode=null );

    /**
     * Returns the list of variables this Provider can measure
     * @return array varname => type
     */
    public static function supportedVariables();

    /**
     * Not mandatory (for backwards compatibility we prefer not make it such), but recommended:
     *
     * public static function resetMeasures();
     *
     * Resets all timers/resource consumption measures.
     * It is not called often, but might be useful to trace scripts which use f.e. forking.
     * NB: if your tracer uses eZPerfLogger to measure execution times, it does not need to implement this call,
     * as eZPerfLogger will clear all the timing points by itself.
     */
}
