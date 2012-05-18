/**
 * Patch the XHProf report, which is generated outside of eZ templating
 *
 * Requires JQuery
 */

(function( $ )
{
    $(document).ready(
        function()
        {
            $('#xhprofextrainfo dd').each(
                function( index )
                {
                    $('dl.phprof_report_info dt:nth-child(1)').after( '<dd>' + $(this).html() + '</dd>' );
                    $(this).remove();
                    //alert( 'Found an element to move ' + $(this).html() );
                }
            )
        }
    );

})(jQuery);
