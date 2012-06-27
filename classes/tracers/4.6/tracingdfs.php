<?php
/**
 * Extends the base dfs backend to add tracing points
 */

class eZDFSFileHandlerTracing46DFSBackend extends eZDFSFileHandlerDFSBackend
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
}
?>
