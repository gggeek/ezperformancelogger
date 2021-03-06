<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2016
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Interface implemented by classes which can be used to measure timing points
 * when eZDebug is off
 */
interface eZPerfLoggerTimeMeasurer
{
    /**
     * @param $id
     * @param bool $group
     * @param bool $label
     * @param null $data
     * @return null
     */
    public static function accumulatorStart( $id, $group = false, $label = false, $data = null  );

    /**
     * @param $id
     * @return null
     */
    public static function accumulatorStop( $id );

    /**
     * @return array
     */
    public static function TimeAccumulatorList();

    /**
     * Resets the measuring for one timing point, or all of them (when passed NULL).
     * @param null $id
     * @return mixed
     */
    public static function accumulatorReset( $id = null );
}
