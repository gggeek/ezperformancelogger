<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012-2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Interface implemented by classes which can be used to log performace data
 */
interface eZPerfLoggerLogger
{
    /**
     * This method lists the type of logging which this class does. In short:
     * if your class can log to different media / formats / whatever, give a name
     * to each, then use it in LogMethods in ezperformanceLogger.ini.
     * @return array tag
     */
    public static function supportedLogMethods();

    /**
     * This method will get called to actually log data (depending on configuration
     * of LogMethods in ezperformanceLogger.ini)
     * @param string $logmethod
     * @param array $data $varname => $value The perf counters to log
     * @param string $output Current page output; passed here in case logger wants to examine it more ormaybe even modify it
     *
     * @todo could add a bool return val in case logging fails?
     */
    public static function doLog( $logmethod, array $data, &$ouput );
}

?>