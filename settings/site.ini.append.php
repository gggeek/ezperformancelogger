<?php /*

# This is the main mechanism used by this extension to trace all performance indicators.
# Do not disable this line.
[OutputSettings]
OutputFilterName=eZPerfLogger

# An extra type cache, where we store data of profiling runs.
# It is only used when integrating with XHProf, not for standard performance tracing
[Cache_xhprof]
name=eZPerformanceLogger xhprof traces cache
path=xhprof

[Event]
Listeners[]=content/cache@ezPerfLoggerEventListener::recordContentCache
Listeners[]=image/alias@ezPerfLoggerEventListener::recordImageAlias


# WARNING - HERE BE LIONS - WE EAT KITTENS FOR BREAKFAST

# In order to enable tracing of the number of db queries executed per page, even
# when debug output is disabled, we use a different db-connection php class.
# Within this extension are provided 3 such files, one for each of ez 4.5, 4.6 and 4.7
# (only for installations using the mysqli db connector).
# You can enable one when needed, and use more PKIs in your traced variables list
# (those are detailed in ezperformancelogger.ini)

[DatabaseSettings]
#ImplementationAlias[ezmysqli]=eZMySQLiTracing45DB
#ImplementationAlias[ezmysqli]=eZMySQLiTracing46DB
#ImplementationAlias[ezmysqli]=eZMySQLiTracing47DB

*/ ?>