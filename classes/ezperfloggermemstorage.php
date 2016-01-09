<?php
/**
 * A class devised to hold in memory perf stats parsed from logs
 *
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2008-2016
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLoggerMemStorage implements eZPerfLoggerStorage
{
    protected static $stats = array();
    protected static $statsCount = 0;

    /**
     * Stores in memory (static var) all data. Calculates max, min, total
     *
     * @todo convert int numbers to floats to avoid overflows in totals
     */
    public static function insertStats( array $data )
    {
        $vars = array_values( eZPerfLoggerINI::variable( 'GeneralSettings', 'TrackVariables' ) );
        foreach( $data as $datapoint )
        {
            foreach( $datapoint['counters'] as $i => $value )
            {
                $varname = $vars[$i];
                if ( !isset( self::$stats[$varname]['min'] ) || self::$stats[$varname]['min'] > $value )
                {
                    self::$stats[$varname]['min'] = $value;
                }
                if ( !isset( self::$stats[$varname]['max'] ) || self::$stats[$varname]['max'] < $value )
                {
                    self::$stats[$varname]['max'] = $value;
                }
                self::$stats[$varname]['total'] = self::$stats[$varname]['total'] + $value;
            }
        }
        self::$statsCount = self::$statsCount + count( $data );
    }

    public static function getStats( $varName = '' )
    {
        return $varName == '' ? self::$stats : self::$stats[$varName];
    }

    public static function getStatsCount()
    {
        return self::$statsCount;
    }

    public static function resetStats()
    {
        self::$statsCount = 0;
        self::$stats = array();
        foreach( eZPerfLoggerINI::variable( 'GeneralSettings', 'TrackVariables' ) as $var )
        {
            self::$stats[$var] = array( 'total' => 0 );
        }
    }
}
