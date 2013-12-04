<?php
/**
 * Extends the base dfs backend to add tracing points
 */

class eZDFSFileHandlerTracing44DFSBackend extends eZDFSFileHandlerDFSBackend
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
        return eZPerfLoggerGenericTracer::StdKPIsFromAccumulators( array(
                'mysql_cluster_dfs_operations'
            ),  eZPerfLogger::TimeAccumulatorList()
        );
    }

    public static function supportedVariables()
    {
        return array(
            'mysql_cluster_dfs_operations' => 'integer',
            'mysql_cluster_dfs_operations_t' => 'float (secs, rounded to msec)',
            'mysql_cluster_dfs_operations_tmax' => 'float (secs, rounded to msec)',
        );
    }
}
?>
