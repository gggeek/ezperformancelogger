<?php /*

[ExtensionSettings]
DesignExtensions[]=ezperformancelogger

[JavaScriptSettings]

# Uncomment these setting to enable integration of xhprof with debug output:
# the debug output results will contain a link to see xhprof data of the current page.

# NB: take care with JavaScriptList/FrontendJavaScriptList, as when using custom frontend designs which load jquery in
# unexpected ways, enabling the settings below might break the loading of jquery.
# It is up to you to make sure that the necessary javascript is correctly loaded.

# eZP 4.3.0 and later
#FrontendJavaScriptList[]=ezjsc::jquery
#FrontendJavaScriptList[]=ezxhprof.js
# (for admin interface design, we can assume jQuery is already loaded)
BackendJavaScriptList[]=ezxhprof.js

# eZP 4.2.0 and earlier
# Make sure you have jQuery loaded, either via ezjscore or some other means
#JavaScriptList[]=ezxhprof.js

