<?php
/**
* Modified db connection class that traces execution times even with debug off
*/

class eZMySQLiTracing54DB extends eZMySQLiDB
{
    /**
     * Take advantage of the fact that db connector is always instantiated to register per logging at shutdown time
     * @param array $parameters
     */
    function __construct( $parameters )
    {
        eZPerfLogger::registerShutdownPerfLogger( true );
        self::eZMySQLiDB( $parameters );
    }

    /*!
     \private
     Opens a new connection to a MySQL database and returns the connection
    */
    function connect( $server, $db, $user, $password, $socketPath, $charset = null, $port = false )
    {
        $connection = false;

        if ( $socketPath !== false )
        {
            ini_set( "mysqli.default_socket", $socketPath );
        }

        if ( $this->UsePersistentConnection == true )
        {
            // Only supported on PHP 5.3 (mysqlnd)
            if ( version_compare( PHP_VERSION, '5.3' ) > 0 )
                $this->Server = 'p:' . $server;
            else
                eZDebug::writeWarning( 'mysqli only supports persistent connections when using php 5.3 and higher', __METHOD__ );
        }

        $oldHandling = eZDebug::setHandleType( eZDebug::HANDLE_EXCEPTION );
        eZPerfLogger::accumulatorStart( 'mysqli_connection', 'mysqli_total', 'Database connection' );
        try {
            $connection = mysqli_connect( $server, $user, $password, null, (int)$port, $socketPath );
        } catch( ErrorException $e ) {}
        eZPerfLogger::accumulatorStop( 'mysqli_connection' );
        eZDebug::setHandleType( $oldHandling );

        $maxAttempts = $this->connectRetryCount();
        $waitTime = $this->connectRetryWaitTime();
        $numAttempts = 1;
        while ( !$connection && $numAttempts <= $maxAttempts )
        {
            sleep( $waitTime );

            $oldHandling = eZDebug::setHandleType( eZDebug::HANDLE_EXCEPTION );
            eZPerfLogger::accumulatorStart( 'mysqli_connection', 'mysqli_total', 'Database connection' );
            try {
                $connection = mysqli_connect( $server, $user, $password, null, (int)$this->Port, $this->SocketPath );
            } catch( ErrorException $e ) {}
            eZPerfLogger::accumulatorStop( 'mysqli_connection' );
            eZDebug::setHandleType( $oldHandling );

            $numAttempts++;
        }
        $this->setError();

        $this->IsConnected = true;

        if ( !$connection )
        {
            eZDebug::writeError( "Connection error: Couldn't connect to database server. Please try again later or inform the system administrator.\n{$this->ErrorMessage}", __CLASS__ );
            $this->IsConnected = false;
            throw new eZDBNoConnectionException( $server, $this->ErrorMessage, $this->ErrorNumber );
        }

        if ( $this->IsConnected && $db != null )
        {
            eZPerfLogger::accumulatorStart( 'mysqli_selectdb', 'mysqli_total', 'Database selection' );
            $ret = mysqli_select_db( $connection, $db );
            eZPerfLogger::accumulatorStop( 'mysqli_selectdb' );
            if ( !$ret )
            {
                $this->setError( $connection );
                eZDebug::writeError( "Connection error: Couldn't select the database. Please try again later or inform the system administrator.\n{$this->ErrorMessage}", __CLASS__ );
                $this->IsConnected = false;
            }
        }

        if ( $charset !== null )
        {
            $originalCharset = $charset;
            $charset = eZCharsetInfo::realCharsetCode( $charset );
        }

        if ( $this->IsConnected and $charset !== null )
        {
            eZPerfLogger::accumulatorStart( 'mysqli_setcharset', 'mysqli_total', 'Charset selection' );
            $status = mysqli_set_charset( $connection, eZMySQLCharset::mapTo( $charset ) );
            eZPerfLogger::accumulatorStop( 'mysqli_setcharset' );
            if ( !$status )
            {
                $this->setError();
                eZDebug::writeWarning( "Connection warning: " . mysqli_errno( $connection ) . ": " . mysqli_error( $connection ), "eZMySQLiDB" );
            }
        }

        return $connection;
    }

    function query( $sql, $server = false )
    {
        if ( $this->IsConnected )
        {
            eZPerfLogger::accumulatorStart( 'mysqli_query', 'mysqli_total', 'Mysqli_queries' );
            $orig_sql = $sql;
            // The converted sql should not be output
            if ( $this->InputTextCodec )
            {
                eZPerfLogger::accumulatorStart( 'mysqli_conversion', 'mysqli_total', 'String conversion in mysqli' );
                $sql = $this->InputTextCodec->convertString( $sql );
                eZPerfLogger::accumulatorStop( 'mysqli_conversion' );
            }

            if ( $this->OutputSQL )
            {
                $this->startTimer();
            }

            $sql = trim( $sql );

            // Check if we need to use the master or slave server by default
            if ( $server === false )
            {
                $server = strncasecmp( $sql, 'select', 6 ) === 0 && $this->TransactionCounter == 0 ?
                    eZDBInterface::SERVER_SLAVE : eZDBInterface::SERVER_MASTER;
            }

            $connection = ( $server == eZDBInterface::SERVER_SLAVE ) ? $this->DBConnection : $this->DBWriteConnection;

            $analysisText = false;
            // If query analysis is enable we need to run the query
            // with an EXPLAIN in front of it
            // Then we build a human-readable table out of the result
            if ( $this->QueryAnalysisOutput )
            {
                $analysisResult = mysqli_query( $connection, 'EXPLAIN ' . $sql );
                if ( $analysisResult )
                {
                    $numRows = mysqli_num_rows( $analysisResult );
                    $rows = array();
                    if ( $numRows > 0 )
                    {
                        for ( $i = 0; $i < $numRows; ++$i )
                        {
                            if ( $this->InputTextCodec )
                            {
                                $tmpRow = mysqli_fetch_array( $analysisResult, MYSQLI_ASSOC );
                                $convRow = array();
                                foreach( $tmpRow as $key => $row )
                                {
                                    $convRow[$key] = $this->OutputTextCodec->convertString( $row );
                                }
                                $rows[$i] = $convRow;
                            }
                            else
                                $rows[$i] = mysqli_fetch_array( $analysisResult, MYSQLI_ASSOC );
                        }
                    }

                    // Figure out all columns and their maximum display size
                    $columns = array();
                    foreach ( $rows as $row )
                    {
                        foreach ( $row as $col => $data )
                        {
                            if ( !isset( $columns[$col] ) )
                                $columns[$col] = array( 'name' => $col,
                                                        'size' => strlen( $col ) );
                            $columns[$col]['size'] = max( $columns[$col]['size'], strlen( $data ) );
                        }
                    }

                    $delimiterLine = array();
                    $colLine = array();
                    // Generate the column line and the vertical delimiter
                    // The look of the table is taken from the MySQL CLI client
                    // It looks like this:
                    // +-------+-------+
                    // | col_a | col_b |
                    // +-------+-------+
                    // | txt   |    42 |
                    // +-------+-------+
                    foreach ( $columns as $col )
                    {
                        $delimiterLine[] = str_repeat( '-', $col['size'] + 2 );
                        $colLine[] = ' ' . str_pad( $col['name'], $col['size'], ' ', STR_PAD_RIGHT ) . ' ';
                    }
                    $delimiterLine = '+' . join( '+', $delimiterLine ) . "+\n";
                    $analysisText = $delimiterLine;
                    $analysisText .= '|' . join( '|', $colLine ) . "|\n";
                    $analysisText .= $delimiterLine;

                    // Go trough all data and pad them to create the table correctly
                    foreach ( $rows as $row )
                    {
                        $rowLine = array();
                        foreach ( $columns as $col )
                        {
                            $name = $col['name'];
                            $size = $col['size'];
                            $data = isset( $row[$name] ) ? $row[$name] : '';
                            // Align numerical values to the right (ie. pad left)
                            $rowLine[] = ' ' . str_pad( $data, $size, ' ',
                                                        is_numeric( $data ) ? STR_PAD_LEFT : STR_PAD_RIGHT ) . ' ';
                        }
                        $analysisText .= '|' . join( '|', $rowLine ) . "|\n";
                        $analysisText .= $delimiterLine;
                    }

                    // Reduce memory usage
                    unset( $rows, $delimiterLine, $colLine, $columns );
                }
            }

            $result = mysqli_query( $connection, $sql );

            if ( $this->RecordError and !$result )
                $this->setError();

            if ( $this->OutputSQL )
            {
                $this->endTimer();

                if ($this->timeTaken() > $this->SlowSQLTimeout)
                {
                    $num_rows = mysqli_affected_rows( $connection );
                    $text = $sql;

                    // If we have some analysis text we append this to the SQL output
                    if ( $analysisText !== false )
                        $text = "EXPLAIN\n" . $text . "\n\nANALYSIS:\n" . $analysisText;

                    $this->reportQuery( __CLASS__ . '[' . $connection->host_info . ( $server == eZDBInterface::SERVER_MASTER ? ', on master' : '' ) . ']', $text, $num_rows, $this->timeTaken() );
                }
            }
            eZPerfLogger::accumulatorStop( 'mysqli_query' );
            if ( $result )
            {
                return $result;
            }
            else
            {
                $errorMessage = 'Query error (' . mysqli_errno( $connection ) . '): ' . mysqli_error( $connection ) . '. Query: ' . $sql;
                eZDebug::writeError( $errorMessage, __CLASS__  );
                $oldRecordError = $this->RecordError;
                // Turn off error handling while we unlock
                $this->RecordError = false;
                mysqli_query( $connection, 'UNLOCK TABLES' );
                $this->RecordError = $oldRecordError;

                $this->reportError();

                // This is to behave the same way as other RDBMS PHP API as PostgreSQL
                // functions which throws an error with a failing request.
                if ( $this->errorHandling == eZDB::ERROR_HANDLING_STANDARD )
                {
                    trigger_error( "mysqli_query(): $errorMessage", E_USER_ERROR );
                }
                else
                {
                    throw new eZDBException( $this->ErrorMessage, $this->ErrorNumber );
                }

                return false;
            }
        }
        else
        {
            eZDebug::writeError( "Trying to do a query without being connected to a database!", __CLASS__ );
        }


    }

    function arrayQuery( $sql, $params = array(), $server = false )
    {
        $retArray = array();
        if ( $this->IsConnected )
        {
            $limit = false;
            $offset = 0;
            $column = false;
            // check for array parameters
            if ( is_array( $params ) )
            {
                if ( isset( $params["limit"] ) and is_numeric( $params["limit"] ) )
                    $limit = $params["limit"];

                if ( isset( $params["offset"] ) and is_numeric( $params["offset"] ) )
                    $offset = $params["offset"];

                if ( isset( $params["column"] ) and ( is_numeric( $params["column"] ) or is_string( $params["column"] ) ) )
                    $column = $params["column"];
            }

            if ( $limit !== false and is_numeric( $limit ) )
            {
                $sql .= "\nLIMIT $offset, $limit ";
            }
            else if ( $offset !== false and is_numeric( $offset ) and $offset > 0 )
            {
                $sql .= "\nLIMIT $offset, 18446744073709551615"; // 2^64-1
            }
            $result = $this->query( $sql, $server );

            if ( $result == false )
            {
                $this->reportQuery( __CLASS__, $sql, false, false );
                return false;
            }

            eZPerfLogger::accumulatorStart( 'mysqli_loop', 'mysqli_total', 'Looping result' );
            $numRows = mysqli_num_rows( $result );
            if ( $numRows > 0 )
            {
                if ( !is_string( $column ) )
                {
                    //eZPerfLogger::accumulatorStart( 'mysqli_loop', 'mysqli_total', 'Looping result' );
                    for ( $i=0; $i < $numRows; $i++ )
                    {
                        if ( $this->InputTextCodec )
                        {
                            $tmpRow = mysqli_fetch_array( $result, MYSQLI_ASSOC );
                            $convRow = array();
                            foreach( $tmpRow as $key => $row )
                            {
                                eZPerfLogger::accumulatorStart( 'mysqli_conversion', 'mysqli_total', 'String conversion in mysqli' );
                                $convRow[$key] = $this->OutputTextCodec->convertString( $row );
                                eZPerfLogger::accumulatorStop( 'mysqli_conversion' );
                            }
                            $retArray[$i + $offset] = $convRow;
                        }
                        else
                            $retArray[$i + $offset] = mysqli_fetch_array( $result, MYSQLI_ASSOC );
                    }
                    eZPerfLogger::accumulatorStop( 'mysqli_loop' );

                }
                else
                {
                    //eZPerfLogger::accumulatorStart( 'mysqli_loop', 'mysqli_total', 'Looping result' );
                    for ( $i=0; $i < $numRows; $i++ )
                    {
                        $tmp_row = mysqli_fetch_array( $result, MYSQLI_ASSOC );
                        if ( $this->InputTextCodec )
                        {
                            eZPerfLogger::accumulatorStart( 'mysqli_conversion', 'mysqli_total', 'String conversion in mysqli' );
                            $retArray[$i + $offset] = $this->OutputTextCodec->convertString( $tmp_row[$column] );
                            eZPerfLogger::accumulatorStop( 'mysqli_conversion' );
                        }
                        else
                            $retArray[$i + $offset] =& $tmp_row[$column];
                    }
                    eZPerfLogger::accumulatorStop( 'mysqli_loop' );
                }
            }
            else
            {
                eZPerfLogger::accumulatorStop( 'mysqli_loop' );
            }
        }
        return $retArray;
    }

    ### perf tracing stuff

    static public function measure()
    {
        return eZPerfLoggerGenericTracer::StdKPIsFromAccumulators( array(
                'mysqli_connection', 'mysqli_query', 'mysqli_loop', 'mysqli_conversion'
            ),  eZPerfLogger::TimeAccumulatorList()
        );
    }

    public static function supportedVariables()
    {
        return array(
            'mysqli_connection' => 'integer',
            'mysqli_connection_t' => 'float (secs, rounded to msec)',
            'mysqli_connection_tmax' => 'float (secs, rounded to msec)',
            'mysqli_query' => 'integer',
            'mysqli_query_t' => 'float (secs, rounded to msec)',
            'mysqli_query_tmax' => 'float (secs, rounded to msec)',
            'mysqli_loop' => 'integer',
            'mysqli_loop_t' => 'float (secs, rounded to msec)',
            'mysqli_loop_tmax' => 'float (secs, rounded to msec)',
            'mysqli_conversion' => 'integer',
            'mysqli_conversion_t' => 'float (secs, rounded to msec)',
            'mysqli_conversion_tmax' => 'float (secs, rounded to msec)',
        );
    }
}

?>
