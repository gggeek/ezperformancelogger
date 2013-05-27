<?php

class eZImageTracing51ShellFactory extends eZImageShellFactory
{
    /*!
     Initializes the factory with the name \c 'shell'
    */
    function eZImageTracing51ShellFactory()
    {
        $this->eZImageFactory( 'shell' );
    }

    /*!
     Creates eZImageShellHandler objects and returns them.
    */
    static function produceFromINI( $iniGroup, $iniFilename = false )
    {
        return eZImageTracing51ShellHandler::createFromINI( $iniGroup, $iniFilename );
    }
}

?>
