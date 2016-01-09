<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2016
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Interface implemented by classes which can be used to parse logs with performace data
 */
interface eZPerfLoggerLogParser
{
    static public function parseLogLine( $line, $counters = array(), $excludeRegexps = array() );

    static public function setOptions( array $opts );
}
