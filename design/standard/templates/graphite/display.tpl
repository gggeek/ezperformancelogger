{**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2013
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 *
 * @todo fix height of iframe using css or js
 * @todo fix: with ez5 admin design, we push left menu into uncomfy position
 *}

{if is_set($error)}
    <p>{$error|wash()}</p>
{else}
{*<div style="margin: 0; padding: 0; border:  none; position: relative; background-color: yellow; overflow: scroll;">*}
<iframe src="{$url|wash()}" width="100%" height="800px" marginwidth="0" marginheight="0" frameborder="0" />
{*</div>*}
{/if}