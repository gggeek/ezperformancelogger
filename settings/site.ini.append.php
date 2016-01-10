<?php /*

[TemplateSettings]
ExtensionAutoloadPath[]=ezperformancelogger

# This is the main mechanism used by this extension to trace all performance indicators (up to eZP 4.7).
# Do not disable this line.
[OutputSettings]
###OutputFilterName=eZPerfLogger

# An extra type cache, where we store data of profiling runs.
# It is only used when integrating with XHProf, not for standard performance tracing
[Cache_xhprof]
name=eZPerformanceLogger xhprof traces cache
path=xhprof

[Event]
Listeners[]=content/cache@ezPerfLoggerEventListener::recordContentCache
Listeners[]=image/alias@ezPerfLoggerEventListener::recordImageAlias
# the following is an alternative to OutputFilterName=eZPerfLogger for ezpublish 5.0 LS and later
Listeners[]=response/preoutput@eZPerfLogger::preoutput

# WARNING - HERE BE LIONS - WE EAT KITTENS FOR BREAKFAST

# In order to enable tracing of the number of db queries executed per page, even
# when debug output is disabled, we use a different db-connection php class.
# Within this extension are provided 4 such files, one for each of ez 4.5, 4.6, 4.7 and 5.0
# (only for installations using the mysqli db connector).
# You can enable one when needed, and use more PKIs in your traced variables list
# (those are detailed in ezperformancelogger.ini)

[DatabaseSettings]
# ONLY UNCOMMENT ONE LINE
# NB: for eZP 5.0 and later, replace the 'ezmysqli' key below with 'mysql'
#ImplementationAlias[ezmysqli]=eZMySQLiTracing45DB
#ImplementationAlias[ezmysqli]=eZMySQLiTracing46DB
#ImplementationAlias[ezmysqli]=eZMySQLiTracing47DB
#ImplementationAlias[ezmysqli]=eZMySQLiTracing50DB
#ImplementationAlias[ezmysqli]=eZMySQLiTracing51DB

[MailSettings]
# ONLY UNCOMMENT ONE LINE PER PHP VERSION
#TransportAlias[smtp]=eZSMTPTracing47Transport
#TransportAlias[sendmail]=eZSendmailTracing47Transport
