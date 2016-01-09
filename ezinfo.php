<?php

class eZperformanceloggerInfo
{
    static function info()
    {
        return array( 'Name' => "<a href=\"http://projects.ez.no/ezperformancelogger\">ezperformancelogger</a>",
                      'Version' => "0.12.0",
                      'Copyright' => "Copyright (C) 2010-2016 eZ Systems AS",
                      'License' => "GNU General Public License v2.0",
                      '3rdparty_software' =>
                            array ( 'name' => 'XHProf',
                                    'Version' => '0.9.2',
                                    'copyright' => 'Facebook, Inc.',
                                    'license' => 'Apache License, Version 2.0',
                                    'info_url' => 'http://pecl.php.net/package/xhprof' )
                     );
    }
}
