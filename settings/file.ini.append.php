<?php/*

# WARNING - HERE BE LIONS - WE EAT KITTENS FOR BREAKFAST

# We can we use a set of "tracing" cluster file handlers to get PKIs related to
# cluster database activity, even when DebugOutput is disabled.
# Uncomment two of the following lines for that, according to your version of
# eZ Publish
# If you have other eZP versions than 4.5, 4.6 or 4.7, take example from the code
# given in classes/tracers/4.x, and create your own

[ClusteringSettings]
#FileHandler=eZDFSTracing45FileHandler
#FileHandler=eZDFSTracing46FileHandler
#FileHandler=eZDFSTracing47FileHandler

[eZDFSClusteringSettings]
#DBBackend=eZDFSFileHandlerTracing45MySQLiBackend
#DBBackend=eZDFSFileHandlerTracing46MySQLiBackend
#DBBackend=eZDFSFileHandlerTracing47MySQLiBackend

*/?>