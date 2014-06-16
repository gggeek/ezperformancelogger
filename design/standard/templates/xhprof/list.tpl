{**
 *
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 *}

{* Details window. *}
<div class="context-block">

{* DESIGN: Header START *}<div class="box-header"><div class="box-tc"><div class="box-ml"><div class="box-mr"><div class="box-tl"><div class="box-tr">

<h2 class="context-title">Available XHProf runs: {$count}</h2>

{* DESIGN: Subline *}<div class="header-subline"></div>

{* DESIGN: Header END *}</div></div></div></div></div></div>

{* DESIGN: Content START *}<div class="box-bc"><div class="box-ml"><div class="box-mr"><div class="box-bl"><div class="box-br"><div class="box-content">

<table class="list" cellspacing="0">
<tbody>
    <tr>
        <th>Run</th>
        <th>Date</th>
        <th>Client IP</th>
        <th>Url</th>
    </tr>
    {foreach $runs_list as $run_id => $desc sequence array('bglight','bgdark') as $bgColor}
    <tr class="{$bgColor}">
        <td><a href={concat('/xhprof/view/?run=', $run_id)|ezurl()}>{$run_id|wash()}</a></td>
        <td>{$desc.time|l10n('datetime')}</td>
        <td>{$desc.ip|wash()}</td>
        <td><a href="{$desc.url|wash()}">{$desc.url|wash()}</a></td>
    </tr>
    {/foreach}
</tbody>
</table>

{include name = Navigator
         uri = 'design:navigator/google.tpl'
         page_uri = '/xhprof/list'
         item_count = $count
         view_parameters = $view_parameters
         item_limit = $limit}

{* DESIGN: Content END *}</div></div></div></div></div></div>

{* DESIGN: /context-block *}</div>