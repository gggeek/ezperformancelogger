<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012-2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Interface implemented by classes which can be used to measure timing points
 * when eZDebug is off
 */
interface eZPerfLoggerTimeMeasurer
{
    public static function accumulatorStart( $id, $group = false, $label = false, $data = null  );

    public static function accumulatorStop( $id );

    public static function TimeAccumulatorList();
}

?>