<?php
/**
 * Class used to store performance data.
 *
 * The idea is to store one row for each access to the site (if it had perf data associated);
 * additional "summary" tables might be created later on.
 * @see piwik_log_action and piwik_log_link_visit_action tables for a possible implementation
 *
 * @todo we could introduce an Interface and an "instance" method later on to make storage configurable
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2008-2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
class eZPerfLoggerStorage extends eZPersistentObject
{

    static function definition()
    {
        return array(
            'fields' => array(),
            'class' => 'eZPerfLoggerStorage',
            'name' => ''
        );
    }

    /**
     * Adds in the db new data points (one per line)
     *
     * @param array $data Format for array of data:
     *        'url' => string,
     *        'time' => int,
     *        'ip' => string,
     *        'counters' => array
     */
    public static function updateStats( $data )
    {
        /// @todo

    }

    /**
     * Deletes all stats data
     */
    public static function resetStats()
    {
        /// @todo
    }
}

?>