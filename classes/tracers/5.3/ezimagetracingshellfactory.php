<?php

class eZImageTracing53ShellFactory extends eZImageShellFactory
{
    /*!
     Initializes the factory with the name \c 'shell'
    */
    function eZImageTracing53ShellFactory()
    {
        $this->eZImageFactory( 'shell' );
    }

    /*!
     Creates eZImageShellHandler objects and returns them.
    */
    static function produceFromINI( $iniGroup, $iniFilename = false )
    {
        return eZImageTracing53ShellHandler::createFromINI( $iniGroup, $iniFilename );
    }
}

?>
