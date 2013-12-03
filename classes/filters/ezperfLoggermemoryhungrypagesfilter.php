<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
class eZPerfLoggerMemoryhungrypagesFilter implements eZPerfLoggerFilter
{
    public static function shouldLog( array $data, $output )
    {
        if ( !isset( $data['mem_usage'] ) || $data['mem_usage'] >= eZPerfLoggerINI::variable( 'LogFilterSettings', 'MemoryhungrypagesFilter' ) )
        {
            return true;
        }
        return false;
    }
}
