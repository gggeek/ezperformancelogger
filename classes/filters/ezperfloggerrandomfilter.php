<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */
class eZPerfLoggerRandomFilter implements eZPerfLoggerFilter
{
    public static function shouldLog( array $data, $output )
    {
        if ( rand( 0, eZPerfLoggerINI::variable( 'LogFilterSettings', 'MemoryhungrypagesFilter' ) ) <= 1 )
        {
            return true;
        }
        return false;
    }
}
