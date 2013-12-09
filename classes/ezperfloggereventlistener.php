<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2013
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
        self::$events[$evName]++;
    }

    // methods implementing event handlers

    static public function recordContentCache( $nodeList )
    {
        self::recordEvent( 'events/content/cache' );
        return $nodeList;
    }

    static public function recordImageAlias()
    {
        self::recordEvent( 'events/image/alias' );
    }

    // support for the interface we expose

    static public function measure( $output, $returnCode=null )
    {
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