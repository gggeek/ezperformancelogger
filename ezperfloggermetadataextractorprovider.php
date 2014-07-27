<?php
/**
 *
 * @author gaetano.giunta
 */

class eZPerfLoggerMetadataExtractorProvider implements eZPerfLoggerProvider
{

    static public function measure( $output, $returnCode=null )
    {
        return eZPerfLoggerGenericTracer::StdKPIsFromAccumulators( array(
                'binaryfile_metadataextractions'
            ),  eZPerfLogger::TimeAccumulatorList()
        );
    }

    public static function supportedVariables()
    {
        return array(
            'binaryfile_metadataextractions' => 'integer',
            'binaryfile_metadataextractions_t' => 'float (secs, rounded to msec)',
            'binaryfile_metadataextractions_tmax' => 'float (secs, rounded to msec)',
        );
    }
}