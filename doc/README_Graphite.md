Statsd + Graphite make a powerful combination.
With great power comes great responsibility.
And a little learning too :-)
Let's dive into proper usage of these tools.

1. Understanding displayed data
  - "buckets"
  - aggregation intervals
  - metric types
  
2. Setup
  - installing graphite
  - installing statsd
  - custom configuration: set up the graphite address in eZ
    . set graphite retention to 10 secs

3. Using the Graphite console
  - Line mode: use "connected line" to avoid "holes" in graphs when a given url is not requested for a certain time (which is bound to happen, even in high-traffic sites)
  - Stacked lines: 