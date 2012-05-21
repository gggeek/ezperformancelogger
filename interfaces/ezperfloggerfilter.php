<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Interface implemented by classes which can be used to decide whether
 * perf. data should be logged at all.
 */
interface eZPerfLoggerFilter
{
    /**
     * This method gets called by the framework for all classes regsitered in
     * LogFilters in ezperformancelogger.ini.
     * As soon as one class returns true, logging is activated
     *
     * @param array $data $varname => $value All the perf counters measured
     * @string $output Current page output
     * @return bool
     */
    public static function shouldLog( array $data, $output );
}

?>