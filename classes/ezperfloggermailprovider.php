<?php
/**
 *
 * @author gaetano.giunta
 */

class eZPerfLoggerMailProvider implements eZPerfLoggerProvider
{

    static public function measure( $output, $returnCode=null )
    {
        return eZPerfLoggerGenericTracer::StdKPIsFromAccumulators( array(
                'mail_sent'
            ),  eZPerfLogger::TimeAccumulatorList()
        );
    }

    public static function supportedVariables()
    {
        return array(
            'mail_sent' => 'integer',
            'mail_sent_t' => 'float (secs, rounded to msec)',
            'mail_sent_tmax' => 'float (secs, rounded to msec)',
        );
    }
}