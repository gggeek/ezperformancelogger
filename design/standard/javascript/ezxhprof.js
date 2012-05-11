/**
 * Utilities for usage with the ezperformancelogger extension:
 * . patch the debug output report to add links to xhprof visualizations
 *
 * Requires JQuery
 */

(function( $ )
{
    $(document).ready(
        function()
        {
            // avoid slowing down production sites
            if ( $('#debug').length == 0 )
            {
                return;
            }
            var tokens = jQuery('body').html().match( /<!-- XHProf runs: \S+ -->/g );
            if ( tokens.length )
            {

                // since this js is not passed the correct path from template, we have
                // to find out what the prefix is to build eZ urls from scratch
                // And it has to work in all possible configs)
                var prefix = '';
                if ( $('#debug #clearcache').length > 0 )
                {
                    prefix = $('#debug #clearcache').attr( "action" ).replace( '/setup/cachetoolbar', '' );
                }
                else if ( $('#debug #templateusage' ).length > 0 )
                {
                    prefix = $('#debug #templateusage tr:nth-child(2) td:nth-child(2) a').attr( "href" ).replace( /\/visual\/templateview\/.*/, '' );
                }
                else
                {
                    // @bug will not work if in non-vhost mode but with no index.php in path
                    // @bug does not work with default (hidden) siteaccess
                    if ( document.location.pathname.indexOf( 'index.php' ) != -1 )
                    {
                        var pos = document.location.pathname.indexOf( '/', document.location.pathname.indexOf( 'index.php' ) + 10 );
                        if ( pos == -1 )
                        {
                            // no sa
                            prefix = document.location.pathname.substr( 0, document.location.pathname.indexOf( 'index.php' ) + 9 );
                        }
                        else
                        {
                            // with sa
                            prefix = document.location.pathname.substr( 0, pos );
                        }
                    }
                }

                tokens = tokens.join().replace( '<!-- XHProf runs: ', '' ).replace( ' -->', '' );
                $('#debug').append( '<h3>XHProf Profiling Run for this page</h3><table class="debug_resource_usage"><tr><td> <a href="' + prefix + '/xhprof/view?run=' + tokens + '">' + tokens + '</a> </td></tr></table>' );
            }
        }
    );

})(jQuery);
