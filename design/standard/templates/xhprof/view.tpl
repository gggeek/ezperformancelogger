{**
 *
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 *}

{ezcss_require( array( 'jquery.tooltip.css', 'jquery.autocomplete.css', 'xhprof.css' ) )}

{* @todo load jquery tooltips and autocomplete *}
{ezscript_require( array( 'jquery.tooltip.js', 'jquery.autocomplete.js', 'xhprof_report.js', 'ezxhprof_report.js' ) )}

<dl id="xhprofextrainfo">
<dd><b>Client IP</b> {$info.ip|wash()}</dd>
<dd><b>URL</b> <a href="{$info.url|wash()}">{$info.url|wash()}</a></dd>
<dd><b>Date</b> {$info.time|l10n('datetime')}</dd>
</dl>

{$body}