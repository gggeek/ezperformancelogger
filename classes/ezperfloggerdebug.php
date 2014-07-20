<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2013-2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * A class which aims to help isolating us from ez4/ez5 config pains (read: allow code to run in ez5 context and do not
 * switch back to ez4 context just to log a message)
 */
class eZPerfLoggerDebug
{
    static function writeError( $string, $label = '', $backgroundClass = '' )
    {
        eZDebug::writeError( $string, $label, $backgroundClass );
    }

    static function writeWarning( $string, $label = '', $backgroundClass = '' )
    {
        eZDebug::writeError( $string, $label, $backgroundClass );
    }

    static function writeDebug( $string, $label = '', $backgroundClass = '' )
    {
        eZDebug::writeError( $string, $label, $backgroundClass );
    }

    static function isDebugEnabled()
    {
        return eZDebug::isDebugEnabled();
    }
}
