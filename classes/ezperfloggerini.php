<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012-2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * A class which should isolate us from ini/yaml config pains (read: allow code to run in ez5 context and do not
 * switch back to ez4 context just to read a single setting)
 *
 * It duplicates code already found in ez5 kernel, but it is supposed to work in pure-legacy mode as well
 */
class eZPerfLoggerINI
{
    static function variable( $group, $var, $file='ezperformancelogger.ini' )
    {
        $ini = eZINI::instance( $file );
        return $ini->variable( $group, $var );
    }

    static function hasVariable( $group, $var, $file='ezperformancelogger.ini' )
    {
        $ini = eZINI::instance( $file );
        return $ini->hasVariable( $group, $var );
    }

    static function variableMulti( $group, array $vars, $file='ezperformancelogger.ini' )
    {
        $ini = eZINI::instance( $file );
        return $ini->variableMulti( $group, $vars );
    }
} 