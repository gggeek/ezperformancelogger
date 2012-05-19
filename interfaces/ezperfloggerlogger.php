<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
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
    * This method gets called to actually log data
    * @param string $logmethod
    * @param array $data $varname => $value
    * @param string $output passed here in case logger wants to examine it more
    *
    * @todo could add a bool return val in case logging fails
    */
    public static function doLog( $logmethod, $data );
}

?>