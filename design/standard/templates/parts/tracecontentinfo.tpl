{* Example template used to trace data from module_result.
   Using this template (including it from pagelayout) is necessary when you are on eZP >= 5.0 and you want to either
   trace such data, or use the statsd logger
*}
{$module_result|make_global('moduleResult')}