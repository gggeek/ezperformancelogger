<?php
/**
 * Class used to log data to pinba servers (like, why?)
 * Based on the pinba_php library - as the "real" pinba extension does not offer
 * us an API which we can use in the way we want
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2008-2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLoggerPinbaLogger extends pinba implements eZPerfLoggerLogger
{
    public static function supportedLogMethods()
    {
        return array( 'pinba' );
    }

    public static function doLog( $logmethod, array $data, &$output )
    {
        global $scriptStartTime;

        // Inject all the measured KPIs as "timers" into pinba, saving actual data in the "data" member
        self::$timers = array();
        foreach( $data as $varname => $value )
        {
            self::$timers[] = array(
                "value" => 0,
                "tags" => array( $varname ),
                "started" => false,
                "data" => $value
            );
        }
        // save other data as well
        self::$doc_size = strlen( $output );
        // the start time from ezdebug is most likely lower than the one from pinba lib
        if ( $scriptStartTime == 0 )
        {
            $debug = eZDebug::instance();
            $scriptStartTime = $debug->ScriptStart;
        }
        self::$start = $scriptStartTime;

        // last but not least, flush data to the server
        static::flush();
    }

    // *** reimplementation ***

    /**
    * Difference from base class: do not obey php.ini but eZ config instead
    */
    static function flush($script_name=null)
    {
        $struct = static::get_packet_info($script_name);
        $message = prtbfr::encode($struct, self::$message_proto);

        $server = eZPerfLoggerINI::variable( 'pinbaSettings', 'Server' );
        $port = 30002;
        if (count($parts = explode(':', $server)) > 1)
        {
            $port = $server[1];
            $server = $server[0];
        }
        $fp = fsockopen("udp://$server", $port, $errno, $errstr);
        if ($fp)
        {
            fwrite($fp, $message);
            fclose($fp);
        }
    }

    /**
    * For this class, whenever asked for timer values, show recorded data instead
    * of actual time
    */
    protected static function _timer_get_info($timer, $time)
    {
        if (isset(self::$timers[$timer]))
        {
            $timer = self::$timers[$timer];
            // NB: there is a high chance of breackage here, since we do not know
            //     what could have been set as KPI value, while pinba expects
            //     times (ms) as floating point
            if ( $timer["value"] == 0 )
            {
                $timer["value"] = $timer['data'];
            }
            return $timer;
        }
        return array();
    }
}

?>