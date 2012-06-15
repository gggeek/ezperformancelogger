<?php
/**
 * File containing the eZDFSFileHandlerMySQLiBackend class.
 *
 * @copyright Copyright (C) 1999-2011 eZ Systems AS. All rights reserved.
 * @license http://ez.no/eZPublish/Licenses/eZ-Business-Use-License-Agreement-eZ-BUL-Version-2.0 eZ Business Use License Agreement Version 2.0
 * @version 4.6.0
 * @package kernel
 */

/*
This is the structure / SQL CREATE for the DFS database table.
It can be created anywhere, in the same database on the same server, or on a
distinct database / server.

CREATE TABLE ezdfsfile (
  `name` text NOT NULL,
  name_trunk text NOT NULL,
  name_hash varchar(34) NOT NULL DEFAULT '',
  datatype varchar(60) NOT NULL DEFAULT 'application/octet-stream',
  scope varchar(25) NOT NULL DEFAULT '',
  size bigint(20) unsigned NOT NULL DEFAULT '0',
  mtime int(11) NOT NULL DEFAULT '0',
  expired tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (name_hash),
  KEY ezdfsfile_name (`name`(250)),
  KEY ezdfsfile_name_trunk (name_trunk(250)),
  KEY ezdfsfile_mtime (mtime),
  KEY ezdfsfile_expired_name (expired,`name`(250))
) ENGINE=InnoDB;
 */

class eZDFSFileHandlerTracing46MySQLiBackend extends eZDFSFileHandlerMySQLiBackend
{

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
        foreach( array( 'mysql_cluster_query' ) as $name )
        {
            if ( isset( $timeAccumulatorList[$name] ) )
            {
                $measured[$name] = $timeAccumulatorList[$name]['count'];
                $measured[$name . '_t'] = $timeAccumulatorList[$name]['time'];
                $measured[$name . '_tmax'] = $timeAccumulatorList[$name]['maxtime'];
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

    /**
     * DB connexion handle
     * @var handle
     */
    public $db = null;

    /**
     * DB connexion parameters
     * @var array
     */
    protected static $dbparams = null;

    /**
     * Amount of executed queries, for debugging purpose
     * @var int
     */
    protected $numQueries = 0;

    /**
     * Current transaction level.
     * Will be used to decide wether we can BEGIN (if it's the first BEGIN call)
     * or COMMIT (if we're commiting the last running transaction
     * @var int
     */
    protected $transactionCount = 0;

    /**
     * DB file table name
     * @var string
     */
    const TABLE_METADATA = 'ezdfsfile';

    /**
     * Distributed filesystem backend
     * @var eZDFSFileHandlerDFSBackend
     */
    protected $dfsbackend = null;
}

?>
