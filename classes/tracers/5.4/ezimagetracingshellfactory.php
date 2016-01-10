<?php

class eZImageTracing54ShellFactory extends eZImageShellFactory
{
    /*!
     Initializes the factory with the name \c 'shell'
    */
    function eZImageTracing54ShellFactory()
    {
        $this->eZImageFactory( 'shell' );
    }

    /*!
     Creates eZImageShellHandler objects and returns them.
    */
    static function produceFromINI( $iniGroup, $iniFilename = false )
    {
        return eZImageTracing54ShellHandler::createFromINI( $iniGroup, $iniFilename );
    }
}

?>
