<?php /*

[PublishingSettings]

# Uncomment the two lines below to allow tracing of the async publishing operations (one collection per publication)
#AsynchronousPublishingQueueReader=eZPerfLoggerContentPublishingQueue
#AsynchronousPublishingPostHandlingHooks[]=eZPerfLoggerAsyncPubTracer::postHandlingHook

# This new hook is only triggered when using the custom queue reader above
AsynchronousPublishingPreHandlingHooks[]=eZPerfLoggerAsyncPubTracer::preHandlingHook

