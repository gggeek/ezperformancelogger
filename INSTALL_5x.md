Installation instructions for eZ Publish 5.x
============================================

The following instructions apply when you are NOT running an eZP 5.x installation in "pure legacy mode". If this is the
case, use the installation instructions for version 4.
Pure-legacy mode is when the document root of your webserver points to the /ezpublish_legacy directory instead of the /web
one.
If you are using a "legacy siteaccess" by having set 'legacy_mode' in yml configuration, do follow these instructions.


1. Install the eZPerformanceLoggerBundle, using composer, and load the GGGeekEZPerformanceLoggerBundle in the kernel

2. Follow the installation and configuration instructions of this extension for eZ Publish 4 (the INSTALL file)

3. Edit extension/ezperformancelogger/settings/site.ini.append.php, and disable the preoutput event listener, by
   commenting away the following line:

       Listeners[]=response/preoutput@eZPerfLogger::preoutput

4. Edit your web/index.php file and add this at the top, if you want to log script execution time:

        global $scriptStartTime;
        $scriptStartTime = microtime( true );

5. If in ezperformancelogger.ini you have set up any loggers which needs to rewrite output, edit services.yml: ...

6. clear all caches (both eZ4 and eZ5 ones)
