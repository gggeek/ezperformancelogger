<?php
/**
 * Extends the base dfs backend to add tracing points
 */

class eZDFSFileHandlerTracing50DFSBackend extends eZDFSFileHandlerDFSBackend
{

    /**
     * Overrides methods from parent to always trace data even when debug is off
     */
    protected function accumulatorStart()
    {
        eZPerfLogger::accumulatorStart( 'mysql_cluster_dfs_operations', 'MySQL Cluster', 'DFS operations' );
    }

    /**
     * Overrides methods from parent to always trace data even when debug is off
     */
    protected function accumulatorStop()
    {
        eZPerfLogger::accumulatorStop( 'mysql_cluster_dfs_operations' );
    }

    ### perf tracing stuff

    static public function measure()
    {
        $timeAccumulatorList = eZPerfLogger::TimeAccumulatorList();

        $measured = array();
        foreach( array( 'mysql_cluster_dfs_operations' ) as $name )
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
?>
