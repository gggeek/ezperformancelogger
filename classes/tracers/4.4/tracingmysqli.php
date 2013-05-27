<?php
/**
 * Extends the eZDFSFileHandlerMySQLiBackend class to add tracing points
 */

class eZDFSFileHandlerTracing44MySQLiBackend extends eZDFSFileHandlerMySQLiBackend
{

    /**
     * Reimplement parent's method to make use of a tracing dfs backend class
     */
    public function _connect()
    {
        $siteINI = eZINI::instance( 'site.ini' );
        // DB Connection setup
        // This part is not actually required since _connect will only be called
        // once, but it is useful to run the unit tests. So be it.
        // @todo refactor this using eZINI::setVariable in unit tests
        if ( parent::$dbparams === null )
        {
            $fileINI = eZINI::instance( 'file.ini' );

            parent::$dbparams = array();
            parent::$dbparams['host']       = $fileINI->variable( 'eZDFSClusteringSettings', 'DBHost' );
            $dbPort = $fileINI->variable( 'eZDFSClusteringSettings', 'DBPort' );
            parent::$dbparams['port']       = $dbPort !== '' ? $dbPort : null;
            parent::$dbparams['socket']     = $fileINI->variable( 'eZDFSClusteringSettings', 'DBSocket' );
            parent::$dbparams['dbname']     = $fileINI->variable( 'eZDFSClusteringSettings', 'DBName' );
            parent::$dbparams['user']       = $fileINI->variable( 'eZDFSClusteringSettings', 'DBUser' );
            parent::$dbparams['pass']       = $fileINI->variable( 'eZDFSClusteringSettings', 'DBPassword' );

            parent::$dbparams['max_connect_tries'] = $fileINI->variable( 'eZDFSClusteringSettings', 'DBConnectRetries' );
            parent::$dbparams['max_execute_tries'] = $fileINI->variable( 'eZDFSClusteringSettings', 'DBExecuteRetries' );

            parent::$dbparams['sql_output'] = $siteINI->variable( "DatabaseSettings", "SQLOutput" ) == "enabled";

            parent::$dbparams['cache_generation_timeout'] = $siteINI->variable( "ContentSettings", "CacheGenerationTimeout" );
        }

        $serverString = parent::$dbparams['host'];
        if ( parent::$dbparams['socket'] )
            $serverString .= ':' . parent::$dbparams['socket'];
        elseif ( parent::$dbparams['port'] )
            $serverString .= ':' . parent::$dbparams['port'];

        $maxTries = parent::$dbparams['max_connect_tries'];
        $tries = 0;
        while ( $tries < $maxTries )
        {
            if ( $this->db = mysqli_connect( parent::$dbparams['host'], parent::$dbparams['user'], parent::$dbparams['pass'], parent::$dbparams['dbname'], parent::$dbparams['port'] ) )
                break;
            ++$tries;
        }
        if ( !$this->db )
            throw new eZClusterHandlerDBNoConnectionException( $serverString, parent::$dbparams['user'], parent::$dbparams['pass'] );

        /*if ( !mysql_select_db( parent::$dbparams['dbname'], $this->db ) )
           throw new eZClusterHandlerDBNoDatabaseException( parent::$dbparams['dbname'] );*/

        // DFS setup
        if ( $this->dfsbackend === null )
        {
            $this->dfsbackend = new eZDFSFileHandlerTracing46DFSBackend();
        }

        $charset = trim( $siteINI->variable( 'DatabaseSettings', 'Charset' ) );
        if ( $charset === '' )
        {
            $charset = eZTextCodec::internalCharset();
        }

        if ( $charset )
        {
            if ( !mysqli_set_charset( $this->db, eZMySQLCharset::mapTo( $charset ) ) )
            {
                $this->_fail( "Failed to set Database charset to $charset." );
            }
        }
    }

    /**
     * Runs a select query, applying the $fetchCall callback to one result
     * If there are more than one row it will fail and exit, if 0 it returns false.
     *
     * @param string $fname The function name that started the query, should
     *                      contain relevant arguments in the text.
     * @param string $error Sent to _error() in case of errors
     * @param bool $debug If true it will display the fetched row in addition to the SQL.
     * @param callback $fetchCall The callback to fetch the row.
     * @return mixed
     */
    protected function _selectOne( $query, $fname, $error = false, $debug = false, $fetchCall )
    {
        eZPerfLogger::accumulatorStart( 'mysql_cluster_query', 'MySQL Cluster', 'DB queries' );
        $time = microtime( true );

        $res = mysqli_query( $this->db, $query );
        if ( !$res )
        {
            if ( mysqli_errno( $this->db ) == 1146 )
            {
                throw new eZDFSFileHandlerTableNotFoundException(
                    $query, mysqli_error( $this->db ) );
            }
            else
            {
                $this->_error( $query, $fname, $error );
                eZPerfLogger::accumulatorStop( 'mysql_cluster_query' );
                // @todo Throw an exception
                return false;
            }
        }

        // we test the return value of mysqli_num_rows and not mysql_fetch, unlike in the mysql handler,
        // since fetch will return null and not false if there are no results
        $nRows = mysqli_num_rows( $res );
        if ( $nRows > 1 )
        {
            eZDebug::writeError( 'Duplicate entries found', $fname );
            eZPerfLogger::accumulatorStop( 'mysql_cluster_query' );
            // @todo throw an exception instead. Should NOT happen.
        }
        elseif ( $nRows === 0 )
        {
            eZPerfLogger::accumulatorStop( 'mysql_cluster_query' );
            return false;
        }

        $row = $fetchCall( $res );
        mysqli_free_result( $res );
        if ( $debug )
            $query = "SQL for _selectOneAssoc:\n" . $query . "\n\nRESULT:\n" . var_export( $row, true );

        $time = microtime( true ) - $time;
        eZPerfLogger::accumulatorStop( 'mysql_cluster_query' );

        $this->_report( $query, $fname, $time );
        return $row;
    }

    /**
     * Performs mysql query and returns mysql result.
     * Times the sql execution, adds accumulator timings and reports SQL to
     * debug.
     * @param string $fname The function name that started the query, should
     *                      contain relevant arguments in the text.
     */
    protected function _query( $query, $fname = false, $reportError = true )
    {
        eZPerfLogger::accumulatorStart( 'mysql_cluster_query', 'MySQL Cluster', 'DB queries' );
        $time = microtime( true );

        $res = mysqli_query( $this->db, $query );
        if ( !$res && $reportError )
        {
            $this->_error( $query, $fname );
        }

        $numRows = mysqli_affected_rows( $this->db );

        $time = microtime( true ) - $time;
        eZPerfLogger::accumulatorStop( 'mysql_cluster_query' );

        $this->_report( $query, $fname, $time, $numRows );
        return $res;
    }

    /**
     * Checks if generation has timed out by looking for the .generating file
     * and comparing its timestamp to the one assigned when the file was created
     *
     * @param string $generatingFilePath
     * @param int    $generatingFileMtime
     *
     * @return bool true if the file didn't timeout, false otherwise
     */
    public function _checkCacheGenerationTimeout( $generatingFilePath, $generatingFileMtime )
    {
        $fname = "_checkCacheGenerationTimeout( $generatingFilePath, $generatingFileMtime )";

        // reporting
        eZPerfLogger::accumulatorStart( 'mysql_cluster_query', 'MySQL Cluster', 'DB queries' );
        $time = microtime( true );

        $nameHash = $this->_md5( $generatingFilePath );
        $newMtime = time();

        // The update query will only succeed if the mtime wasn't changed in between
        $query = "UPDATE " . self::TABLE_METADATA . " SET mtime = $newMtime WHERE name_hash = {$nameHash} AND mtime = $generatingFileMtime";
        $res = mysqli_query( $this->db, $query );
        if ( !$res )
        {
            // @todo Throw an exception
            $this->_error( $query, $fname );
            return false;
        }
        $numRows = mysqli_affected_rows( $this->db );

        // reporting. Manual here since we don't use _query
        $time = microtime( true ) - $time;
        $this->_report( $query, $fname, $time, $numRows );

        // no rows affected or row updated with the same value
        // f.e a cache-block which takes less than 1 sec to get generated
        // if a line has been updated by the same  values, mysqli_affected_rows
        // returns 0, and updates nothing, we need to extra check this,
        if( $numRows == 0 )
        {
            $query = "SELECT mtime FROM " . self::TABLE_METADATA . " WHERE name_hash = {$nameHash}";
            $res = mysqli_query( $this->db, $query );
            mysqli_fetch_row( $res );
            if ( $res and isset( $row[0] ) and $row[0] == $generatingFileMtime );
            {
                return true;
            }

            // @todo Check if an exception makes sense here
            return false;
        }
        // rows affected: mtime has changed, or row has been removed
        if ( $numRows == 1 )
        {
            return true;
        }
        else
        {
            eZDebugSetting::writeDebug( 'kernel-clustering', "No rows affected by query '$query', record has been modified", __METHOD__ );
            return false;
        }
    }

    ### perf tracing stuff

    static public function measure()
    {
        $timeAccumulatorList = eZPerfLogger::TimeAccumulatorList();

        $measured = array();
        foreach( array( 'mysql_cluster_query', 'mysql_cluster_dfs_operations', 'mysql_cluster_cache_waits' ) as $name )
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
