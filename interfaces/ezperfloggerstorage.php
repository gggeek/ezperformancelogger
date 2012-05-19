<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Interface implemented by classes which can be used to store the performance data
 * (usually via cronjob after it has been parsed from logs)
 */
interface eZPerfLoggerStorage
{
    /**
     * @param array $data Format for array of data:
     *        'url' => string,
     *        'time' => int (unix timestamp),
     *        'ip' => string (client's ip),
     *        'response_status' => string (eg. 200),
     *        'response_size => int (bytes),
     *        'counters' => array
     * @return bool false on error
     */
    public static function insertStats( $data );
}

?>