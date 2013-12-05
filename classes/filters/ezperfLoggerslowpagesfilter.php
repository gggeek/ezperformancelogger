<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
class eZPerfLoggerSlowpagesFilter implements eZPerfLoggerFilter
{
    public static function shouldLog( array $data, $output )
    {
        if ( !isset( $data['execution_time'] ) || $data['execution_time'] >= eZPerfLoggerINI::variable( 'LogFilterSettings', 'SlowpagesFilterLimit' ) )
        {
            return true;
        }
        return false;
    }
}
