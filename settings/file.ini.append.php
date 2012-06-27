<?php/*

# WARNING - HERE BE LIONS - WE EAT KITTENS FOR BREAKFAST

# We can we use a set of "tracing" cluster file handlers to get PKIs related to
# cluster database activity, even when DebugOutput is disabled.
# Uncomment the following lines for that.
# If you have other eZP versions than 4.6, take example from the code given in
# classes/tracers/4.6, and create your own

[ClusteringSettings]
#FileHandler=eZDFSTracing46FileHandler

[eZDFSClusteringSettings]
#DBBackend=eZDFSFileHandlerTracing46MySQLiBackend

*/?>