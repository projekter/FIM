{extends '/base.tpl'}
{block 'body'}
   {if isset($failed)}
      <p class="alert">{L ['login', 'failed']}</p>
   {/if}
   <p>{L ['login', 'description']}</p>
   <form action="{Request::getFullURL()}" method="post">
      <table>
         <tr>
            <th><label for="password">{L ['login', 'password']}</label></th>
            <td><input type="password" name="password" id="password" value="" /></td>
         </tr>
      </table>
      <input type="submit" value="{L ['login', 'submit']}" />
   </form>
{/block}