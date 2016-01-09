Installation instructions
=========================

1. enable the extension; clear all caches (at least the ini and template caches)


For performance logging
-----------------------

2. edit ezperformancelogger.ini to decide the data you want to log:
   parameter [GeneralSettings]/TrackVariables
   See file README for more details about available data.
   Note that depending on the variables you want to log, you might have to alter
   the [GeneralSettings]/VariableProviders parameter as well.
   NB: db_queries logging by default only works with ez debug on.


3. edit ezperformancelogger.ini to decide how to log it:

    a. using Apache
      to add perf data to the Apache access log, customize your Apache configuration
      taking as example the sample_httpd.conf file in the doc directory.
      It is recommended not to enable collection of execution time via
      TrackVariables when using Apache, but to rely on the more precise native
      measure obtained with %D
      Remember to restart Apache after changing its configuration

    b. using odoscope, piwik or google analytics
      the perf data is logged directly to the analytics engine database, via usage
      of "custom variables" (ie. we add the perf data to the js call done for visit
      tracking). For all of these tools, you will need to insert in your pagelayout
      template the standard javascript tracking code; the extension will transform
      it as appropriate to add the extra data.
      For ga, only the async tag is supported.
      NB: data reported via odoscope, piwik or google analytics will not be accurate
      if your website is using a caching reverse proxy or cdn. Please use tha apache-log
      configuration in such case.

    c. using a separate log file
      this is useful if your webserver is Nginx, Lighttpd or IIS. In that case, the
      extension can log perf data all by itself to a separate log. The name of that
      file has to be configured in ezperformancelogger.ini; it can be written either
      using the same format as Apache "extended" log, with perf counters data at the
      end of the line, or in csv format

    d. using statsd and graphite
      this is useful if you have a lot of traffic, and want to be able to see nice
      graphs, with drill-down / group / filter capability.
      Steps are:
      I. set up a graphite+statsd server
      II. set up ezperformancelogger.ini
      III. if using ezp >= 5, add a specific template tag in your pagelayout (see provided
           tpl in the design/admin folder in this extension)
      The main difference between graphite and other KPI graphing tools is the extensive
      support for grouping data. To get most value out of it, the extension "rewrites" the
      names of the measured KPIs when sending them to statsd, injecting by default the
      content-class name and node-id. This way the graphite user could drill down, to see
      f.e. the avg page-load-time across the whole site, then across all pages displaying
      articles, then only for the article with node-id 635.
      The way KPI names are rewritten for graphite can also be configured via ini settings.

    e. using monolog, syslog etc...
      read comments in ezperformancelogger.ini to find out more details about those


4. once logging of data is active, we recommend using a tool like httrack or
   wget to scan your complete website and get an overview of the performance
   of your web pages and identify the most resource-hungry ones.

   A good idea is to run the scan both with eZ Publish caches on and off, to
   measure the effectiveness of cache configuration.

   NB: the extension does not provide any means to visualize the logged data.
   You can use any tool from excel to matlab for that.


5. to log db performances with Debug Output disabled, uncomment the
   appropriate line in settings/site.ini.append.php and use one of the db
   connectors provided by the extension (currently only for eZP 4.4 to 5.3 on mysqli)

   NB: this works unless you have the same value set in a configuration file
   with higher priority, such as settings/override/site.ini.append.php, in
   which case you need to edit that one. NB: do not forget to undo your changes
   before you disable the extension, if you do.


6. to log cluster-db performances with Debug Output disabled, uncomment the two
   appropriate lines in settings/file.ini.append.php and use one of the cluster db
   connectors provided by the extension (currently only for eZP 4.4 to 5.3 on mysqli)

   This works unless unless you have the same values set in a configuration file
   with higher priority, such as settings/override/file.ini.append.php, which is
   quite common with cluster configuration. In this case, edit the values in the
   higher-priority file. NB: do not forget to undo your changes before you disable
   the extension, if you do.


7. to log performance data from image conversion using Imagemagick, uncomment the
    appropriate line in settings/image.ini.append.php and use one of the handlers
    provided by the extension.

   NB: this works unless you have the same value set in a configuration file
   with higher priority, such as settings/override/image.ini.append.php, in
   which case you need to edit that one. NB: do not forget to undo your changes
   before you disable the extension, if you do.


8. to log performance data even on web pages which end prematurely or redirect
   you need to patch the index.php file and add somewhere the following line:
      eZPerfLogger::registerShutdownPerfLogger();
   a good candidate location is next to the existing eZExecution::addCleanupHandler call.
   For eZPublish 5.x, patch ezpkernelweb.php instead of index.php;
   please note that in this case the measured data can be lower than expected,
   you should also patch ezpkernelweb::runCallback() to avoid measuring data
   via calls to eZPerfLogger::disable() and reenable().

   If you are using a custom database connector to measure database performances
   (see point 5 above), you can obtain the same result without hacking the kernel.
   Just set
       AlwaysRegisterShutdownPerfLogger=enabled
   in ezperformacelogger.ini.
   Note that if you do so, you will automatically measure all cli scripts you execute.


9. to log performance data of publication events when using asynchronous publication,
   uncomment the lines in settings/comment.ini.append.php, then restart the async
   publishing cli script.

   NB: to log performance data of async publication events, custom queue handlers are injected
   into the publishing process.
   There is no guarantee that they will work at all if you
   - are not on eZPublish 4.7, or
   - have a customized async publication mechanism


10. you can also decide not to log data for every single request received, but only for
   some of them. To this end, use the LogFilters option in ezperformancelogger.ini.
   Filters provided within the extension allow to log slow pages, memory-hungry pages,
   or a random subset of all pages.


For graphing performance indicators with Munin
----------------------------------------------

1. make sure you have a valid munin-node installation on your webserver

2. configure eZPerformanceLogger to record as many variables as you want (see
   paragraph above).
   The only constraint is that LOGGING TO CSV FILE HAS TO BE ENABLED (see point 4.c)

3. customize how the variables recorded by eZPerformanceLogger will show up
   in Munin graphs by editing ini settings in the [MuninSettings] section of
   ezperformancelogger.ini

4. activate the munin plugin provided within the extension.
   Detailed instructions for this step you will find in the file

   bin/scripts/ezmuninperflogger_


For graphing performance indicators with Graphite
-------------------------------------------------

See point 3.d above


For profiling
-------------

1. make sure the XHProf PECL extension is installed and active

2. give to the user account that will be used for viewing profiling data permissions
   to execute hxprof/view

3. edit your config.php file and add the following lines at the top:
   (if you miss the config.php file, copy config.php-RECOMMENDED into config.php)

   include( 'extension/ezperformancelogger/classes/ezxhproflogger.php' );
   eZXHProfLogger::start();

   in alternative, you can start profiling from anywhere in your php code

4. edit design.ini in extension/ezperformacelogger/settings, and uncomment the
   lines corresponding to your eZ version

5. view any website page: a link to its profiling info is at the bottom of the debug output

   Take care that there are no javascript errors related to jquery or jquery plugins.
   If any errors happen, it is up to you to make sure you properly load xhprof.js

6. in the Admin Interface, in the Setup tab, you will find a link to a page listing
   all available profiling data for viewing.

7. all profiling data is stored permanently on disk, in var/log/xhprof. To avoid
   filling the hard-disk, please schedule execution of the removexhprofdata cronjob
