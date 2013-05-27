<?php
/**
* Modified db connection class that traces execution times even with debug off
*/

class eZMySQLiTracing44DB extends eZMySQLiDB
{
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
                $this->Server = 'p:' . $this->Server;
            else
                eZDebug::writeWarning( 'mysqli only supports persistent connections when using php 5.3 and higher', 'eZMySQLiDB::connect' );
        }

        self::accumulatorStart( 'mysqli_connection', 'mysqli_total', 'Database connection' );
        $connection = mysqli_connect( $server, $user, $password, null, (int)$port, $socketPath );

        $dbErrorText = mysqli_connect_error();
        self::accumulatorStop( 'mysqli_connection' );

        $maxAttempts = $this->connectRetryCount();
        $waitTime = $this->connectRetryWaitTime();
        $numAttempts = 1;
        while ( !$connection && $numAttempts <= $maxAttempts )
        {
            sleep( $waitTime );

            self::accumulatorStart( 'mysqli_connection', 'mysqli_total', 'Database connection' );
            $connection = mysqli_connect( $this->Server, $this->User, $this->Password, null, (int)$this->Port, $this->SocketPath );
            self::accumulatorStop( 'mysqli_connection' );

            $numAttempts++;
        }
        $this->setError();

        $this->IsConnected = true;

        if ( !$connection )
        {
            eZDebug::writeError( "Connection error: Couldn't connect to database. Please try again later or inform the system administrator.\n$dbErrorText", __CLASS__ );
            $this->IsConnected = false;
            throw new eZDBNoConnectionException( $server );
        }

        if ( $this->IsConnected && $db != null )
        {
            self::accumulatorStart( 'mysqli_connection', 'mysqli_total', 'Database connection' );
            $ret = mysqli_select_db( $connection, $db );
            self::accumulatorStop( 'mysqli_connection' );
            if ( !$ret )
            {
                //$this->setError();
                eZDebug::writeError( "Connection error: " . mysqli_errno( $connection ) . ": " . mysqli_error( $connection ), "eZMySQLiDB" );
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
            self::accumulatorStart( 'mysqli_connection', 'mysqli_total', 'Database connection' );
            $status = mysqli_set_charset( $connection, eZMySQLCharset::mapTo( $charset ) );
            self::accumulatorStop( 'mysqli_connection' );
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
            self::accumulatorStart( 'mysqli_query', 'mysqli_total', 'Mysqli_queries' );
            $orig_sql = $sql;
            // The converted sql should not be output
            if ( $this->InputTextCodec )
            {
                self::accumulatorStart( 'mysqli_conversion', 'mysqli_total', 'String conversion in mysqli' );
                $sql = $this->InputTextCodec->convertString( $sql );
                self::accumulatorStop( 'mysqli_conversion' );
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

                    $analysisText = '';
                    $delimiterLine = array();
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
                            $rowLine[] = ' ' . str_pad( $row[$name], $size, ' ',
                                                        is_numeric( $row[$name] ) ? STR_PAD_LEFT : STR_PAD_RIGHT ) . ' ';
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
            self::accumulatorStop( 'mysqli_query' );
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
                trigger_error( "mysqli_query(): $errorMessage", E_USER_ERROR );

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

            self::accumulatorStart( 'mysqli_loop', 'mysqli_total', 'Looping result' );
            $numRows = mysqli_num_rows( $result );
            if ( $numRows > 0 )
            {
                if ( !is_string( $column ) )
                {
                    //self::accumulatorStart( 'mysqli_loop', 'mysqli_total', 'Looping result' );
                    for ( $i=0; $i < $numRows; $i++ )
                    {
                        if ( $this->InputTextCodec )
                        {
                            $tmpRow = mysqli_fetch_array( $result, MYSQLI_ASSOC );
                            $convRow = array();
                            foreach( $tmpRow as $key => $row )
                            {
                                self::accumulatorStart( 'mysqli_conversion', 'mysqli_total', 'String conversion in mysqli' );
                                $convRow[$key] = $this->OutputTextCodec->convertString( $row );
                                self::accumulatorStop( 'mysqli_conversion' );
                            }
                            $retArray[$i + $offset] = $convRow;
                        }
                        else
                            $retArray[$i + $offset] = mysqli_fetch_array( $result, MYSQLI_ASSOC );
                    }
                    self::accumulatorStop( 'mysqli_loop' );

                }
                else
                {
                    //self::accumulatorStart( 'mysqli_loop', 'mysqli_total', 'Looping result' );
                    for ( $i=0; $i < $numRows; $i++ )
                    {
                        $tmp_row = mysqli_fetch_array( $result, MYSQLI_ASSOC );
                        if ( $this->InputTextCodec )
                        {
                            self::accumulatorStart( 'mysqli_conversion', 'mysqli_total', 'String conversion in mysqli' );
                            $retArray[$i + $offset] = $this->OutputTextCodec->convertString( $tmp_row[$column] );
                            self::accumulatorStop( 'mysqli_conversion' );
                        }
                        else
                            $retArray[$i + $offset] =& $tmp_row[$column];
                    }
                    self::accumulatorStop( 'mysqli_loop' );
                }
            }
            else
            {
                self::accumulatorStop( 'mysqli_loop' );
            }
        }
        return $retArray;
    }



    static $timeAccumulatorList = array();

    protected static function accumulatorStart( $val, $group = false, $label = false, $data = null  )
    {
        $startTime = microtime( true );
        if ( eZDebug::isDebugEnabled() )
        {
            eZDebug::accumulatorStart( $val, $group, $label );
        }
        if ( !isset( self::$timeAccumulatorList[$val] ) )
        {
            self::$timeAccumulatorList[$val] = array( 'group' => $group, 'data' => array(), 'time' => 0 );
        }
        self::$timeAccumulatorList[$val]['temp_time'] = $startTime;
        if ( $data !== null )
        {
            self::$timeAccumulatorList[$val]['data'][] = $data;
        }
    }

    protected static function accumulatorStop( $val )
    {
        $stopTime = microtime( true );
        if ( eZDebug::isDebugEnabled() )
        {
            eZDebug::accumulatorStop( $val );
        }
        if ( !isset( self::$timeAccumulatorList[$val]['count'] ) )
        {
            self::$timeAccumulatorList[$val]['count'] = 1;
        }
        else
        {
            self::$timeAccumulatorList[$val]['count'] = self::$timeAccumulatorList[$val]['count'] + 1;
        }
        self::$timeAccumulatorList[$val]['time'] = $stopTime - self::$timeAccumulatorList[$val]['temp_time'] + self::$timeAccumulatorList[$val]['time'];
    }

    public static function timeAccumulators()
    {
        return self::$timeAccumulatorList;
    }

    static public function measure()
    {
        foreach( array( 'mysqli_connection', 'mysqli_query', 'mysqli_loop', 'mysqli_conversion' ) as $name )
        {
            if ( isset( self::$timeAccumulatorList[$name] ) )
            {
                eZPerfLogger::recordValue( $name, self::$timeAccumulatorList[$name]['count'] );
                eZPerfLogger::recordValue( $name . '_t', self::$timeAccumulatorList[$name]['time'] );
            }
            else
            {
                eZPerfLogger::recordValue( $name, 0 );
                eZPerfLogger::recordValue( $name . '_t', 0 );
            }
        }
        eZPerfLogger::recordValue( 'mysqli_offsets_s', "'" . implode( ',', @self::$timeAccumulatorList['mysqli_loop']['data'] ) . "'" );
    }
}

?>