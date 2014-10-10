{extends '/base.tpl'}
{block 'title'}{$title = {L ['error', 'title'] $errno Response::translateHTTPCode($errno)}}{/block}
{block 'body'}
   <p>{L ['error', 'message']}</p>
   <p><a href="{url '/tasks'}">{L ['tasks', 'title']}</a></p>
{/block}