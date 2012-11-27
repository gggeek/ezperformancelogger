<?php

class eZImageTracing45ShellFactory extends eZImageShellFactory
{
    /*!
     Initializes the factory with the name \c 'shell'
    */
    function eZImageTracing45ShellFactory()
    {
        $this->eZImageFactory( 'shell' );
    }

    /*!
     Creates eZImageShellHandler objects and returns them.
    */
    static function produceFromINI( $iniGroup, $iniFilename = false )
    {
        return eZImageTracing45ShellHandler::createFromINI( $iniGroup, $iniFilename );
    }
}

?>
