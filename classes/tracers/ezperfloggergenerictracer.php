<?php
/**
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2013-2016
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Holds common code routines for the "tracer" classes
 */
class eZPerfLoggerGenericTracer
{
    /**
     * Take a list of accumulators, and for each return as KPI:
     * - the number of times it was executed
     * - the total time taken (rounded to millisecs)
     * - the time taken for the longest run (rounded to millisecs)
     * @param array $accumulators names of accumulators you want to trace
     * @param array $timeAccumulatorList full list of accumulators available, as returned by eZPerfLogger::TimeAccumulatorList()
     * @return array
     */
    static public function StdKPIsFromAccumulators( array $accumulators, array $timeAccumulatorList )
    {
        $measured = array();
        foreach( $accumulators as $name )
        {
            if ( isset( $timeAccumulatorList[$name] ) )
            {
                $measured[$name] = $timeAccumulatorList[$name]['count'];
                $measured[$name . '_t'] = round( $timeAccumulatorList[$name]['time'], 3 );
                $measured[$name . '_tmax'] = round( $timeAccumulatorList[$name]['maxtime'], 3 );
            }
            else
            {
                $measured[$name] = 0;
                $measured[$name . '_t'] = 0;
                $measured[$name . '_tmax'] = 0;
            }
        }
        return $measured;
    }
}