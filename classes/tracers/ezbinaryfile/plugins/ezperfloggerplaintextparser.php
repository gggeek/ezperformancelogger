<?php
/**
 * @author gaetano.giunta
 * @copyright (C) eZ Systems AS 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLoggerPlainTextParser extends eZPlainTextParser
{
    function parseFile( $fileName )
    {
        eZPerfLogger::accumulatorStart( 'binaryfile_metadataextractions' );
        $result = parent::parseFile( $fileName );
        eZPerfLogger::accumulatorStop( 'binaryfile_metadataextractions' );
        return $result;
    }
}