<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2008-2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
interface eZPerfLoggerStorage
{
    /**
     * @param array $data Format for array of data:
     *        'url' => string,
     *        'time' => int,
     *        'ip' => string,
     *        'counters' => array
     * @return bool false on error
     */
    public static function insertStats( $data );
}

?>