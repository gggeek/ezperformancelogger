<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
interface eZPerfLoggerProvider
{
    /**
     * This method is called to allow this class to provide data for the measurements
     * it caters to.
     * It does so by calling eZPerfLogger::recordValue
     */
    public static function measure();
}

?>