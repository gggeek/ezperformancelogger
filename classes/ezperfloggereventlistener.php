<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Tracing events.
 * These methods are not supposed to be called by end users - they are hooked up
 * as an event listener using settings in site.ini/Events/Listeners
 *
 * @todo allow end users to easily log their custom events (via pure ini settings, no php code)
 */
class ezPerfLoggerEventListener implements eZPerfLoggerProvider
{
    // internal stuff

    static protected $events = array(
        'events/content/cache' => 0,
        'events/image/alias' => 0
    );

    static protected function recordEvent( $evName )
    {
file_put_contents( 'd:/temp/x.log', "Record: $evName\n", FILE_APPEND );
        self::$events[$evName]++;
        //echo "bbb";
        //var_dump(self::$events);
        //die('x');
    }

    // methods implementing event handlers

    static public function recordContentCache()
    {
        self::recordEvent( 'events/content/cache' );
    }

    static public function recordImageAlias()
    {
        self::recordEvent( 'events/image/alias' );
    }

    // support for the interface we expose

    static public function measure( $output )
    {
        //echo "aaa";
        //var_dump(self::$events);
        //die('y');
file_put_contents( 'd:/temp/x.log', "Measuring...\n", FILE_APPEND );
        return self::$events;
    }

    public static function supportedVariables()
    {
        $desc = self::$events;
        foreach( $desc as $key => $value )
        {
            $desc[$key] = 'int (number of times event is fired)';
        }
        return $desc;
    }
}

?>