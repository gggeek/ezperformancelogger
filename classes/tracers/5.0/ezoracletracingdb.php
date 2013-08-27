<?php
/**
 * Modified db connection class that traces execution times even with debug off
 */

class eZOracleTracing50DB extends eZOracleDB
{
    /**
     * Creates a new eZOracleDB object and connects to the database.
     */
    function eZOracleTracing50DB( $parameters )
    {
        $this->eZDBInterface( $parameters );

        if ( !extension_loaded( 'oci8' ) )
        {
            if ( function_exists( 'eZAppendWarningItem' ) )
            {
                eZAppendWarningItem( array( 'error' => array( 'type' => 'ezdb',
                                                              'number' => eZDBInterface::ERROR_MISSING_EXTENSION ),
                                            'text' => 'Oracle extension was not found, the DB handler will not be initialized.' ) );
                $this->IsConnected = false;
            }
            eZDebug::writeWarning( 'Oracle extension was not found, the DB handler will not be initialized.', 'eZOracleDB' );
            return;
        }

        //$server = $this->Server;
        $user = $this->User;
        $password = $this->Password;
        $db = $this->DB;

        $this->ErrorMessage = false;
        $this->ErrorNumber = false;
        $this->IgnoreTriggerErrors = false;

        $ini = eZINI::instance();

        if ( function_exists( "oci_connect" ) )
        {
            $this->Mode = OCI_COMMIT_ON_SUCCESS;

            // translate chosen charset to its Oracle analogue
            $oraCharset = null;
            if ( isset( $this->Charset ) && $this->Charset !== '' )
            {
                if ( array_key_exists( $this->Charset, $this->CharsetsMap ) )
                {
                     $oraCharset = $this->CharsetsMap[$this->Charset];
                }
            }

            $maxAttempts = $this->connectRetryCount();
            $waitTime = $this->connectRetryWaitTime();
            $numAttempts = 1;
            if ( $ini->variable( "DatabaseSettings", "UsePersistentConnection" ) == "enabled" )
            {
                eZDebugSetting::writeDebug( 'kernel-db-oracle', $ini->variable( "DatabaseSettings", "UsePersistentConnection" ), "using persistent connection" );
                eZPerfLogger::accumulatorStart( 'oracle_connection', 'oracle_total', 'Database connection' );
                $oldHandling = eZDebug::setHandleType( eZDebug::HANDLE_EXCEPTION );
                try {
                    $this->DBConnection = oci_pconnect( $user, $password, $db, $oraCharset );
                } catch( ErrorException $e ) {}
                eZPerfLogger::accumulatorStop( 'oracle_connection' );
                eZDebug::setHandleType( $oldHandling );
                while ( $this->DBConnection == false and $numAttempts <= $maxAttempts )
                {
                    sleep( $waitTime );
                    eZPerfLogger::accumulatorStart( 'oracle_connection', 'oracle_total', 'Database connection' );
                    $oldHandling = eZDebug::setHandleType( eZDebug::HANDLE_EXCEPTION );
                    try {
                        $this->DBConnection = oci_pconnect( $user, $password, $db, $oraCharset );
                    } catch( ErrorException $e ) {}
                    eZPerfLogger::accumulatorStop( 'oracle_connection' );
                    eZDebug::setHandleType( $oldHandling );
                    $numAttempts++;
                }
            }
            else
            {
                eZDebugSetting::writeDebug( 'kernel-db-oracle', "using real connection",  "using real connection" );
                $oldHandling = eZDebug::setHandleType( eZDebug::HANDLE_EXCEPTION );
                eZPerfLogger::accumulatorStart( 'oracle_connection', 'oracle_total', 'Database connection' );
                try {
                    $this->DBConnection = oci_connect( $user, $password, $db, $oraCharset );
                } catch( ErrorException $e ) {}
                eZPerfLogger::accumulatorStop( 'oracle_connection' );
                eZDebug::setHandleType( $oldHandling );
                while ( $this->DBConnection == false and $numAttempts <= $maxAttempts )
                {
                    sleep( $waitTime );
                    $oldHandling = eZDebug::setHandleType( eZDebug::HANDLE_EXCEPTION );
                    eZPerfLogger::accumulatorStart( 'oracle_connection', 'oracle_total', 'Database connection' );
                    try {
                        $this->DBConnection = @oci_connect( $user, $password, $db, $oraCharset );
                    } catch( ErrorException $e ) {}
                    eZPerfLogger::accumulatorStop( 'oracle_connection' );
                    eZDebug::setHandleType( $oldHandling );
                    $numAttempts++;
                }
            }

//            OCIInternalDebug(1);

            if ( $this->DBConnection === false )
            {
                $this->IsConnected = false;
            }
            else
            {
                $this->IsConnected = true;
                // make sure the decimal separator is the dot
                $this->query( "ALTER SESSION SET NLS_NUMERIC_CHARACTERS='. '" );
            }

            if ( $this->DBConnection === false )
            {
                $error = oci_error();

                // workaround for bug in PHP oci8 extension
                if ( $error === false && !getenv( "ORACLE_HOME" ) )
                {
                    $error = array( 'code' => -1, 'message' => 'ORACLE_HOME environment variable is not set' );
                }

                if ( $error['code'] != 0 )
                {
                    if ( $error['code'] == 12541 )
                    {
                        $error['message'] = 'No listener (probably the server is down).';
                    }
                    $this->ErrorMessage = $error['message'];
                    $this->ErrorNumber = $error['code'];
                    eZDebug::writeError( "Connection error(" . $error["code"] . "):\n". $error["message"] .  " ", "eZOracleDB" );
                }

                throw new eZDBNoConnectionException( $db, $this->ErrorMessage, $this->ErrorNumber );
            }
        }
        else
        {
            $this->ErrorMessage = "Oracle support not compiled in PHP";
            $this->ErrorNumber = -1;
            eZDebug::writeError( $this->ErrorMessage, "eZOracleDB" );
            $this->IsConnected = false;
        }

        eZDebug::createAccumulatorGroup( 'oracle_total', 'Oracle Total' );
    }

    function analyseQuery( $sql, $server = false )
    {
        $analysisText = false;
        // If query analysis is enable we need to run the query
        // with an EXPLAIN in front of it
        // Then we build a human-readable table out of the result
        if ( $this->QueryAnalysisOutput )
        {
            $stmtid = substr( md5( $sql ), 0, 30);
            $analysisStmt = oci_parse( $this->DBConnection, 'EXPLAIN PLAN SET STATEMENT_ID = \'' . $stmtid . '\' FOR ' . $sql );
            $analysisResult = oci_execute( $analysisStmt, $this->Mode );
            if ( $analysisResult )
            {
                // note: we might make the name of the explain plan table an ini variable...
                // note 2: since oracle 9, a package is provided that we could use to get nicely formatted explain plan output: DBMS_XPLAN.DISPLAY
                //         but we should check if it is installe or not
                //         "SELECT * FROM table (DBMS_XPLAN.DISPLAY('plan_table', '$stmtid'))";
                oci_free_statement( $analysisStmt );
                $analysisStmt = oci_parse( $this->DBConnection, "SELECT LPAD(' ',2*(LEVEL-1))||operation operation, options,
                                                                         object_name, position, cost, cardinality, bytes
                                                                  FROM plan_table
                                                                  START WITH id = 0 AND statement_id = '$stmtid'
                                                                  CONNECT BY PRIOR id = parent_id AND statement_id = '$stmtid'" );
                $analysisResult = oci_execute( $analysisStmt, $this->Mode );
                if ( $analysisResult )
                {
                    $rows = array();
                    $numRows = oci_fetch_all( $analysisStmt, $rows, 0, -1, OCI_ASSOC + OCI_FETCHSTATEMENT_BY_ROW );
                    if ( $this->OutputTextCodec )
                    {
                        for ( $i = 0; $i < $numRows; ++$i )
                        {
                            foreach( $rows[$i] as $key => $data )
                            {
                                $rows[$i][$key] = $this->OutputTextCodec->convertString( $data );
                            }
                        }
                    }

                    // Figure out all columns and their maximum display size
                    $columns = array();
                    foreach ( $rows as $row )
                    {
                        foreach ( $row as $col => $data )
                        {
                            if ( !isset( $columns[$col] ) )
                            {
                                $columns[$col] = array( 'name' => $col,
                                                        'size' => strlen( $col ) );
                            }
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
            oci_free_statement( $analysisStmt );
        }
        return $analysisText;
    }

    function query( $sql, $server = false )
    {
        // note: the other database drivers do not reset the error message here...
        $this->ErrorMessage = false;
        $this->ErrorNumber = false;

        if ( !$this->isConnected() )
        {
            eZDebug::writeError( "Trying to do a query without being connected to a database!", "eZOracleDB"  );
            // note: postgres returns a false in this case, mysql returns nothing...
            return null;
        }
        $result = true;

        eZPerfLogger::accumulatorStart( 'oracle_query', 'oracle_total', 'Oracle_queries' );
        // The converted sql should not be output
        if ( $this->InputTextCodec )
        {
             eZPerfLogger::accumulatorStart( 'oracle_conversion', 'oracle_total', 'String conversion in oracle' );
             $sql = $this->InputTextCodec->convertString( $sql );
             eZPerfLogger::accumulatorStop( 'oracle_conversion' );
        }

        if ( $this->OutputSQL )
        {
            $this->startTimer();
        }

        $analysisText = $this->analyseQuery( $sql, $server );

        $statement = oci_parse( $this->DBConnection, $sql );

        if ( $statement )
        {
            foreach ( $this->BindVariableArray as $bindVar )
            {
                oci_bind_by_name( $statement, $bindVar['dbname'], $bindVar['value'], -1 );
            }

            // was: we do not use $this->Mode here because we might have nested transactions
            // change was introduced in 2.0: we leave to parent class the handling
            // of nested transactions, and always use $this->Mode to commit
            // if needed
            $exec = @oci_execute( $statement, $this->Mode );
            if ( !$exec )
            {
                if ( $this->setError( $statement, 'query()' ) )
                {
                    $result = false;
                }
            }
            /*else
            {
                // small api change: we do not commit if exec fails and oci_error says no error.
                // previously we did commit anyway...

                // Commit when we are not in a transaction and we use an 'autocommit' mode.
                // This is done because we execute queries in non-autocomiit mode, while
                // by default the db driver works in autocommit
                if ( $this->Mode != OCI_DEFAULT && $this->TransactionCounter == 0)
                {
                    oci_commit( $this->DBConnection );
                }
            }*/

            oci_free_statement( $statement );

        }
        else
        {
            if ( $this->setError( $this->DBConnection, 'query()' ) )
            {
                $result = false;
            }
        }

        if ( $this->OutputSQL )
        {
            $this->endTimer();
            if ( $this->timeTaken() > $this->SlowSQLTimeout )
            {
                // If we have some analysis text we append this to the SQL output
                if ( $analysisText !== false )
                {
                    $sql = "EXPLAIN\n" . $sql . "\n\nANALYSIS:\n" . $analysisText;
                }

                $this->reportQuery( 'eZOracleDB', $sql, false, $this->timeTaken() );
            }
        }

        eZPerfLogger::accumulatorStop( 'oracle_query' );

        $this->BindVariableArray = array();

        // let std error handling happen here (eg: transaction error reporting)
        if ( !$result )
        {
            $this->reportError();

            if ( $this->errorHandling == eZDB::ERROR_HANDLING_EXCEPTIONS )
            {
                throw new eZDBException( $this->ErrorMessage, $this->ErrorNumber );
            }
        }

        return $result;
    }

    function arrayQuery( $sql, $params = false, $server = false )
    {
        $resultArray = array();

        if ( !$this->isConnected() )
        {
            return $resultArray;
        }

        $limit = -1;
        $offset = 0;
        $column = false;
        // check for array parameters
        if ( is_array( $params ) )
        {
            if ( isset( $params["limit"] ) and is_numeric( $params["limit"] ) )
            {
                $limit = $params["limit"];
            }
            if ( isset( $params["offset"] ) and is_numeric( $params["offset"] ) )
            {
                $offset = $params["offset"];
            }
            if ( isset( $params["column"] ) and ( is_numeric( $params["column"] ) or is_string( $params["column"]) ) )
            {
                $column = strtoupper( $params["column"] );
            }
        }
        eZPerfLogger::accumulatorStart( 'oracle_query', 'oracle_total', 'Oracle_queries' );
//        if ( $this->OutputSQL )
//            $this->startTimer();
        // The converted sql should not be output
        if ( $this->InputTextCodec )
        {
            eZPerfLogger::accumulatorStart( 'oracle_conversion', 'oracle_total', 'String conversion in oracle' );
            $sql = $this->InputTextCodec->convertString( $sql );
            eZPerfLogger::accumulatorStop( 'oracle_conversion' );
        }

        $analysisText = $this->analyseQuery( $sql, $server );

        if ( $this->OutputSQL )
        {
            $this->startTimer();
        }
        $statement = oci_parse( $this->DBConnection, $sql );
        //flush();
        if ( !@oci_execute( $statement, $this->Mode ) )
        {
            eZPerfLogger::accumulatorStop( 'oracle_query' );
            $error = oci_error( $statement );
            $hasError = true;
            if ( !$error['code'] )
            {
                $hasError = false;
            }
            if ( $hasError )
            {
                $result = false;
                $this->ErrorMessage = $error['message'];
                $this->ErrorNumber = $error['code'];
                if ( isset( $error['sqltext'] ) )
                    $sql = $error['sqltext'];
                $offset = false;
                if ( isset( $error['offset'] ) )
                    $offset = $error['offset'];
                $offsetText = '';
                if ( $offset !== false )
                {
                    $offsetText = ' at offset ' . $offset;
                    $sqlOffsetText = "\n\nStart of error:\n" . substr( $sql, $offset );
                }
                eZDebug::writeError( "Error (" . $error['code'] . "): " . $error['message'] . "\n" .
                                     "Failed query$offsetText:\n" .
                                     $sql .
                                     $sqlOffsetText, "eZOracleDB" );
                oci_free_statement( $statement );
                eZPerfLogger::accumulatorStop( 'oracle_query' );

                return $result;
            }
        }
        eZPerfLogger::accumulatorStop( 'oracle_query' );

        if ( $this->OutputSQL )
        {
            $this->endTimer();
            if ( $this->timeTaken() > $this->SlowSQLTimeout )
            {
                // If we have some analysis text we append this to the SQL output
                if ( $analysisText !== false )
                {
                    $sql = "EXPLAIN\n" . $sql . "\n\nANALYSIS:\n" . $analysisText;
                }
                $this->reportQuery( 'eZOracleDB', $sql, false, $this->timeTaken() );
            }
        }

        //$numCols = oci_num_fields( $statement );
        $results = array();

        eZPerfLogger::accumulatorStart( 'oracle_loop', 'oracle_total', 'Oracle looping results' );

        if ( $column !== false )
        {
            if ( is_numeric( $column ) )
            {
               $rowCount = oci_fetch_all( $statement, $results, $offset, $limit, OCI_FETCHSTATEMENT_BY_COLUMN + OCI_NUM );
            }
            else
            {
                $rowCount = oci_fetch_all( $statement, $results, $offset, $limit, OCI_FETCHSTATEMENT_BY_COLUMN + OCI_ASSOC );
            }

            // optimize to our best the special case: 1 row
            if ( $rowCount == 1 )
            {
                $resultArray[$offset] = $this->OutputTextCodec ? $this->OutputTextCodec->convertString( $results[$column][0] ) : $results[$column][0];
            }
            else if ( $rowCount > 0 )
            {
                $results = $results[$column];
                if ( $this->OutputTextCodec )
                {
                    array_walk( $results, array( 'eZOracleDB', 'arrayConvertStrings' ), $this->OutputTextCodec );
                }
                $resultArray = $offset == 0 ? $results : array_combine( range( $offset, $offset + $rowCount -1 ), $results );
            }
        }
        else
        {
            $rowCount = oci_fetch_all( $statement, $results, $offset, $limit, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC );
            // optimize to our best the special case: 1 row
            if ( $rowCount == 1 )
            {
                if ( $this->OutputTextCodec )
                {
                    array_walk( $results[0], array( 'eZOracleDB', 'arrayConvertStrings' ), $this->OutputTextCodec );
                }
                $resultArray[$offset] = array_change_key_case( $results[0] );
            }
            else if ( $rowCount > 0 )
            {
                $keys = array_keys( array_change_key_case( $results[0] ) );
                // this would be slightly faster, but we have to work around a php bug
                // with recursive array_walk present in 5.1 (eg. on red hat 5.2)
                //array_walk( $results, array( 'eZOracleDB', 'arrayChangeKeys' ), array( $this->OutputTextCodec, $keys ) );
                $arr = array( $this->OutputTextCodec, $keys );
                foreach( $results as  $key => &$val )
                {
                    self::arrayChangeKeys( $val, $key, $arr );
                }
                $resultArray = $offset == 0 ? $results : array_combine( range( $offset, $offset + $rowCount - 1 ), $results );
            }
        }

        eZPerfLogger::accumulatorStop( 'oracle_loop' );
        oci_free_statement( $statement );

        return $resultArray;
    }

    /**
     * We trust the eZDBInterface to count nested transactions and only call
     * this method when trans counter reaches 0
     */
    function commitQuery()
    {
        $result = oci_commit( $this->DBConnection );
        $this->Mode = OCI_COMMIT_ON_SUCCESS;
        if ( $this->OutputSQL )
        {
            $this->reportQuery( 'eZOracleDB', 'commit transaction', false, 0 );
        }
        return $result;
    }

    function rollbackQuery()
    {
        $result = oci_rollback( $this->DBConnection );
        $this->Mode = OCI_COMMIT_ON_SUCCESS;
        if ( $this->OutputSQL )
        {
            $this->reportQuery( 'eZOracleDB', 'rollback transaction', false, 0 );
        }
        return $result;
    }

    function close()
    {
        if ( $this->DBConnection !== false )
        {
            oci_close( $this->DBConnection );
            $this->DBConnection = false;
        }
        $this->IsConnected  = false;
    }

    ### perf tracing stuff

    static public function measure()
    {
        $timeAccumulatorList = eZPerfLogger::TimeAccumulatorList();

        $measured = array();
        foreach( array( 'oracle_connection', 'oracle_query', 'oracle_loop', 'oracle_conversion' ) as $name )
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
        //$measured['mysqli_offsets_s']  = "'" . ( is_array( @$timeAccumulatorList['mysqli_loop']['data'] ) ? implode( ',', $timeAccumulatorList['mysqli_loop']['data'] )  : '' ) . "'";

        return $measured;
    }
}

?>
