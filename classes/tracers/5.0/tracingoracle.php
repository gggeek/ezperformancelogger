<?php
/**
 * Extends the eZDFSFileHandlerOracleBackend class to add tracing points
 */

class eZDFSFileHandlerTracing50OracleBackend extends eZDFSFileHandlerOracleBackend
{
    /**
     * Connects to the database.
     *
     * @return void
     * @throw eZClusterHandlerDBNoConnectionException
     * @throw eZClusterHandlerDBNoDatabaseException
     **/
    public function _connect()
    {
        if ( !function_exists( 'oci_connect' ) )
            throw new eZClusterHandlerDBNoDatabaseException( "PECL oci8 extension (http://pecl.php.net/package/oci8) is required to use Oracle clustering functionality." );

        // DB Connection setup
        // This part is not actually required since _connect will only be called
        // once, but it is useful to run the unit tests. So be it.
        // @todo refactor this using eZINI::setVariable in unit tests
        if ( self::$dbparams === null )
        {
            $siteINI = eZINI::instance( 'site.ini' );
            $fileINI = eZINI::instance( 'file.ini' );

            self::$dbparams = array();
            //self::$dbparams['host']       = $fileINI->variable( 'eZDFSClusteringSettings', 'DBHost' );
            //self::$dbparams['port']       = $fileINI->variable( 'eZDFSClusteringSettings', 'DBPort' );
            //self::$dbparams['socket']     = $fileINI->variable( 'eZDFSClusteringSettings', 'DBSocket' );
            self::$dbparams['dbname']     = $fileINI->variable( 'eZDFSClusteringSettings', 'DBName' );
            self::$dbparams['user']       = $fileINI->variable( 'eZDFSClusteringSettings', 'DBUser' );
            self::$dbparams['pass']       = $fileINI->variable( 'eZDFSClusteringSettings', 'DBPassword' );

            self::$dbparams['max_connect_tries'] = $fileINI->variable( 'eZDFSClusteringSettings', 'DBConnectRetries' );
            self::$dbparams['max_execute_tries'] = $fileINI->variable( 'eZDFSClusteringSettings', 'DBExecuteRetries' );

            self::$dbparams['sql_output'] = $siteINI->variable( "DatabaseSettings", "SQLOutput" ) == "enabled";

            self::$dbparams['cache_generation_timeout'] = $siteINI->variable( "ContentSettings", "CacheGenerationTimeout" );

            self::$dbparams['persistent_connection'] = $fileINI->hasVariable( 'eZDFSClusteringSettings', 'DBPersistentConnection' ) ? ( $fileINI->variable( 'eZDFSClusteringSettings', 'DBPersistentConnection' ) == 'enabled' ) : false;
        }

        $maxTries = self::$dbparams['max_connect_tries'];
        $tries = 0;
        eZPerfLogger::accumulatorStart( 'oracle_cluster_connect', 'Oracle Cluster', 'Cluster database connection' );
        while ( $tries < $maxTries )
        {
            if ( self::$dbparams['persistent_connection'] )
            {
                if ( $this->db = oci_pconnect( self::$dbparams['user'], self::$dbparams['pass'], self::$dbparams['dbname'] ) )
                    break;
            }
            else
            {
                if ( $this->db = oci_connect( self::$dbparams['user'], self::$dbparams['pass'], self::$dbparams['dbname'] ) )
                    break;
            }
            ++$tries;
        }
        eZPerfLogger::accumulatorStop( 'oracle_cluster_connect' );
        if ( !$this->db )
            throw new eZClusterHandlerDBNoConnectionException( self::$dbparams['dbname'], self::$dbparams['user'], self::$dbparams['pass'] );

        // DFS setup
        if ( $this->dfsbackend === null )
        {
            $this->dfsbackend = new eZDFSFileHandlerDFSBackend();
        }
    }

    /**
     * Disconnects the handler from the database
     */
    public function _disconnect()
    {
        if ( $this->db !== null )
        {
            if ( !self::$dbparams['persistent_connection'] )
                oci_close( $this->db );
            $this->db = null;
        }
    }

     protected function _deleteByDirListInner( $dirList, $commonPath, $commonSuffix, $fname )
    {
        $result = true;
        $this->error = false;
        $like = ''; // not sure it is necessary to initialize, but in case...
        $sql = self::$deletequery . "WHERE name LIKE :alike" ;
        /// @todo !important test that oci_parse went ok, and oci_bind_by_name too
        $statement = oci_parse( $this->db, $sql );
        oci_bind_by_name( $statement, ':alike', $like, 4000 );

        foreach ( $dirList as $dirItem )
        {
            $like = "$commonPath/$dirItem/$commonSuffix%";

            if ( !@oci_execute( $statement, OCI_DEFAULT ) )
            {
                $this->error = oci_error( $statement );
                $this->_error( $sql, $fname, false );
                $result = false;
                break;
            }
        }

        oci_free_statement( $statement );

        /*if ( $result )
           oci_commit( $this->db );
           else
           oci_rollback( $this->db );*/

        return $result;
    }

    /**
     * Fetches the file $filePath from the database to its own name
     *
     * Saving $filePath locally with its original name, or $uniqueName if given
     *
     * @param string $filePath
     * @param string $uniqueName Alternative name to save the file to
     * @return string|bool the file physical path, or false if fetch failed
     **/
    public function _fetch( $filePath, $uniqueName = false )
    {
        $fname = "_fetch($filePath)";
        $metaData = $this->_fetchMetadata( $filePath, $fname );
        if ( !$metaData )
        {
            // @todo Throw an exception
            eZDebug::writeError( "File '$filePath' does not exist while trying to fetch.", __METHOD__ );
            return false;
        }
        //$contentLength = $metaData['size'];

        // create temporary file
        if ( strrpos( $filePath, '.' ) > 0 )
            $tmpFilePath = substr_replace( $filePath, getmypid().'tmp', strrpos( $filePath, '.' ), 0  );
        else
            $tmpFilePath = $filePath . '.' . getmypid().'tmp';
        $this->__mkdir_p( dirname( $tmpFilePath ) );

        // copy DFS file to temporary FS path
        // @todo Throw an exception
        if ( !$this->dfsbackend->copyFromDFS( $filePath, $tmpFilePath ) )
        {
            eZDebug::writeError("Failed copying DFS://$filePath to FS://$tmpFilePath ");
            return false;
        }

        // Make sure all data is written correctly
        clearstatcache();
        $tmpSize = filesize( $tmpFilePath );
        // @todo Throw an exception
        if ( $tmpSize != $metaData['size'] )
        {
            eZDebug::writeError( "Size ($tmpSize) of written data for file '$tmpFilePath' does not match expected size " . $metaData['size'], __METHOD__ );
            return false;
        }

        if ( $uniqueName !== true )
        {
            eZFile::rename( $tmpFilePath, $filePath, false, eZFile::CLEAN_ON_FAILURE | eZFile::APPEND_DEBUG_ON_FAILURE );
        }
        else
        {
            $filePath = $tmpFilePath;
        }

        return $filePath;
    }

    /**
     * Handles a DB error, displaying it as an eZDebug error
     * @see eZDebug::writeError
     * @param string $msg Message to display
     * @param string $sql SQL query to display error for
     * @return void
     **/
    protected function _die( $msg, $sql = null )
    {
        if ( $this->db )
        {
            $error = oci_error( $this->db );
        }
        else
        {
            $error = oci_error();
        }
        eZDebug::writeError( $sql, "$msg: " . $error['message'] );
        eZDebug::writeError( self::$dbparams, "$msg: " . $error['message'] );
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
     **/
    protected function _selectOne( $query, $fname, $error = false, $debug = false, $bindparams = array(), $fetchOpts=OCI_BOTH )
    {
        eZPerfLogger::accumulatorStart( 'oracle_cluster_query', 'oracle_cluster_total', 'Oracle_cluster_queries' );
        $time = microtime( true );

        $res = false;
        $this->error = false;
        if ( $statement = oci_parse( $this->db, $query ) )
        {
            foreach( $bindparams as $name => $val )
            {
                if ( !oci_bind_by_name( $statement, $name, $bindparams[$name], -1 ) )
                {
                    $this->error = oci_error( $statement );
                    $this->_error( $query, $fname, $error );
                }
            }
            if ( $res = oci_execute( $statement, OCI_DEFAULT ) )
            {
                $row = oci_fetch_array( $statement, $fetchOpts );
                $row2 = $row ? oci_fetch_array( $statement, $fetchOpts ) : false;
            }

            oci_free_statement( $statement );
        }
        else
        {
            // trick used for error reporting
            $statement = $this->db;
        }
        eZPerfLogger::accumulatorStop( 'oracle_cluster_query' );
        if ( !$res )
        {
            $this->error = oci_error( $statement );
            $this->_error( $query, $fname, $error );
            return false;
        }

        if ( $row2 !== false )
        {
            $this->_error( $query, $fname, "Duplicate entries found." );
            // For PHP 5 throw an exception.
        }

        // Convert column names to lowercase.
        if ( $row && ( $fetchOpts & OCI_ASSOC ) )
        {
            foreach ( $row as $key => $val )
            {
                $row[strtolower( $key )] = $val;
                unset( $row[$key] );
            }
        }

        if ( $debug )
            $query = "SQL for _selectOne:\n" . $query . "\n\nRESULT:\n" . var_export( $row, true );

        $time = microtime( true ) - $time;

        $this->_report( $query, $fname, $time );
        return $row;
    }

    /**
     * Stops a current transaction and commits the changes by executing a COMMIT call.
     * If the current transaction is a sub-transaction nothing is executed.
     **/
    protected function _commit( $fname = false )
    {
        if ( $fname )
            $fname .= "::_commit";
        else
            $fname = "_commit";
        $this->transactionCount--;
        if ( $this->transactionCount == 0 )
           oci_commit( $this->db );
    }

    /**
     * Stops a current transaction and discards all changes by executing a
     * ROLLBACK call.
     * If the current transaction is a sub-transaction nothing is executed.
     **/
    protected function _rollback( $fname = false )
    {
        if ( $fname )
            $fname .= "::_rollback";
        else
            $fname = "_rollback";
        $this->transactionCount--;
        if ( $this->transactionCount == 0 )
            oci_rollback( $this->db );
    }

    /**
     * Performs mysql query and returns mysql result.
     * Times the sql execution, adds accumulator timings and reports SQL to
     * debug.
     * @param string $fname The function name that started the query, should
     *                      contain relevant arguments in the text.
     **/
    protected function _query( $query, $fname = false, $reportError = true, $bindparams = array(), $return_type = self::RETURN_BOOL )
    {
        eZPerfLogger::accumulatorStart( 'oracle_cluster_query', 'oracle_cluster_total', 'Oracle_cluster_queries' );
        $time = microtime( true );

        $this->error = null;
        $res = false;
        if ( $statement = oci_parse( $this->db, $query ) )
        {
            foreach( $bindparams as $name => $val )
            {
                if ( !oci_bind_by_name( $statement, $name, $bindparams[$name], -1 ) )
                {
                    $this->error = oci_error( $statement );
                    $this->_error( $query, $fname, $error );
                }
            }

            if ( ! $res = oci_execute( $statement, OCI_DEFAULT ) )
            {
                $this->error = oci_error( $statement );
            }
            else
            {
                if ( $return_type == self::RETURN_COUNT )
                {
                    $res = oci_num_rows( $statement );
                }
                else if ( $return_type == self::RETURN_DATA )
                {
                    oci_fetch_all( $statement, $res, 0, 0, OCI_FETCHSTATEMENT_BY_ROW+OCI_ASSOC );
                }
                else if ( $return_type == self::RETURN_DATA_BY_COL )
                {
                    oci_fetch_all( $statement, $res, 0, 0, OCI_FETCHSTATEMENT_BY_COLUMN+OCI_ASSOC );
                }
            }

            oci_free_statement( $statement );
        }
        else
        {
            $this->error = oci_error( $this->db );
        }

        // take care: 0 might be a valid result if RETURN_COUNT is used
        if ( $res === false && $reportError )
        {
            $this->_error( $query, $fname, false, $statement );
        }


        //$numRows = mysql_affected_rows( $this->db );

        $time = microtime( true ) - $time;
        eZPerfLogger::accumulatorStop( 'oracle_cluster_query' );

        $this->_report( $query, $fname, $time, 0 );
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
     **/
    public function _checkCacheGenerationTimeout( $generatingFilePath, $generatingFileMtime )
    {
        $fname = "_checkCacheGenerationTimeout( $generatingFilePath, $generatingFileMtime )";
        //eZDebugSetting::writeDebug( 'kernel-clustering', "Checking for timeout of '$generatingFilePath' with mtime $generatingFileMtime", $fname );

        // reporting
        eZPerfLogger::accumulatorStart( 'oracle_cluster_query', 'oracle_cluster_total', 'Oracle_cluster_queries' );
        $time = microtime( true );

        $nameHash = "'" . md5( $generatingFilePath ) . "'";
        $newMtime = time();

        // The update query will only succeed if the mtime wasn't changed in between
        $query = "UPDATE " . self::TABLE_METADATA . " SET mtime = $newMtime WHERE name_hash = $nameHash AND mtime = $generatingFileMtime";
        $numRows = $this->_query( $query, $fname, false, array(), self::RETURN_COUNT );
        if ( $numRows === false )
        {
            /// @todo Throw an exception
            $this->_error( $query, $fname );
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
        foreach( array( 'oracle_cluster_query', 'oracle_cluster_connect' ) as $name )
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