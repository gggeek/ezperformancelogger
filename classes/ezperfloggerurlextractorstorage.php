<?php
/**
 * A class devised to hold in memory stats of urls accessed
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2008-2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLoggerUrlExtractorStorage implements eZPerfLoggerStorage
{
    protected static $urls = array();
    protected static $options = array(
        'keep_query_string' => false,
        'keep_view_params' => false,
    );

    /**
     * Stores in memory (static var) all data. Calculates max, min, total
     *
     * @todo convert int numbers to floats to avoid overflows in totals
     * @todo allow to extract a different stat than view count
     */
    public static function insertStats( array $data )
    {
        foreach( $data as $datapoint)
        {

            $url = $datapoint['url'];
            // always remove fragments
            $url = preg_replace( '/#.*^/', '', $url );
            // optionally remove query string
            if ( self::$options['keep_query_string'] == false )
            {
                $url = preg_replace( '/\?.*^/', '', $url );
            }
            // optionally remove unordered view parameters
            if ( self::$options['keep_view_params'] == false )
            {
                $url = preg_replace( '!/\([^?]*!', '', $url );
            }
            $idx = md5( $url );
            if ( isset( self::$urls[$idx] ) )
            {
                self::$urls[$idx]['count'] = self::$urls[$idx]['count'] + 1;
                // we assume increasing monotonic time
                self::$urls[$idx]['last'] = $datapoint['time'];
            }
            else
            {
                self::$urls[$idx] = array(
                    'url' => $url,
                    'count' => 1,
                    'first' => $datapoint['time'],
                    'last' => $datapoint['time']
                );
            }
        }
    }

    public static function getStats( $url='' )
    {
        $idx = md5( $url );
        return $url == '' ? self::$urls : ( isset( self::$urls[$idx] ) ? self::$urls[$idx] : false );
    }

    public static function resetStats()
    {
        self::$urls = array();
    }

    public static function setOptions( $opts )
    {
        self::$options = array_merge( self::$options, $opts );
    }
}

?>