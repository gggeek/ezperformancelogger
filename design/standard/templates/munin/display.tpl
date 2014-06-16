{**
 *
 * @author G. Giunta
 * @copyright (C) eZ Systems AS 2012-2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 *
 * @todo fix height of iframe using css or js
 *}

{if is_set($error)}
    <p>{$error|wash()}</p>
{else}
<iframe src="{$url|wash()}" width="100%" height="800px" marginwidth="0" marginheight="0" frameborder="0" />
{/if}