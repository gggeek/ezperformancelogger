<?php /*

[ExtensionSettings]
DesignExtensions[]=ezperformancelogger

[JavaScriptSettings]

# Uncomment these setting to enable integration of xhprof with debug output:
# the debug output results will contain a link to see xhprof data of the current page

# eZP 4.3.0 and later
FrontendJavaScriptList[]=ezjsc::jqueryFrontendJavaScriptList[]=ezxhprof.js
# (for admin interface design, we can assume jQuery is already loaded)
BackendJavaScriptList[]=ezxhprof.js

# eZP 4.2.0 and earlier
# Make sure you have jQuery loaded, either via ezjscore or some other means
#JavaScriptList[]=ezxhprof.js

*/ ?>
