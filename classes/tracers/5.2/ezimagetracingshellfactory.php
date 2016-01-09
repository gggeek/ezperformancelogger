<?php

class eZImageTracing52ShellFactory extends eZImageShellFactory
{
    /*!
     Initializes the factory with the name \c 'shell'
    */
    function eZImageTracing52ShellFactory()
    {
        $this->eZImageFactory( 'shell' );
    }

    /*!
     Creates eZImageShellHandler objects and returns them.
    */
    static function produceFromINI( $iniGroup, $iniFilename = false )
    {
        return eZImageTracing52ShellHandler::createFromINI( $iniGroup, $iniFilename );
    }
}

?>
