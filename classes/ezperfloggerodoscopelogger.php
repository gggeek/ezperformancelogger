<?php
/**
 * Class used to log data to Odoscope.
 * It does not take advantage of odoscope integration capability but does tag
 * rewriting on its own, to be able to also add custom params for the <noscript> tag
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZPerfLoggerOdoscopeLogger implements eZPerfLoggerLogger
{
    public static function supportedLogMethods()
    {
        return array( 'odoscope' );
    }

    public static function doLog( $logmethod, array $data, &$output )
    {
        self::logByRewrite( $data, $output );
    }

    protected static function logByRewrite( array $data, &$output )
    {
        $prefix = eZPerfLoggerINI::variable( 'OdoscopeSettings', 'VariablePrefix' );
        $text = "";
        foreach( eZPerfLoggerINI::variable( 'GeneralSettings', 'TrackVariables' ) as $var )
        {
            $text .= "&amp;$prefix" . urlencode( $var ) . "=" . urlencode( $data[$var] );
        }
        $output = preg_replace( '#(images/osc\.gif\?[^"]+)"#', '$1' . $text . '"', $output );
        $output = preg_replace( "#(osc\.img\('[^']+)'\)#", '$1' . $text . "')", $output );
    }

    protected static function logByJSEvents( array $data, &$output )
    {
        $output = preg_replace( '#</body>#', '<script type="text/javascript">' . "\n" . self::generateJSEventsFunctionCalls( $data ) . "</script>\n</body>", $output );
    }

    protected static function generateJSEventsFunctionCalls( $data )
    {
        $prefix = eZPerfLoggerINI::variable( 'OdoscopeSettings', 'VariablePrefix' );
        $text = "";
        foreach( eZPerfLoggerINI::variable( 'GeneralSettings', 'TrackVariables' ) as $var )
        {
            /// @todo proper js escaping; do not add quotes for numeric values?
            $text .= "osc.evt( '" . $prefix . urlencode( $var ) . "', '" . urlencode( $data[$var] ) . "' );\n";
        }
        return $text;
    }
}

?>