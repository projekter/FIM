{extends file='/base.tpl'}
{block name='body'}
   {if isset($emptyTitle)}
      <p class="alert">{L ['tasks', 'details', 'nameRequired']}</p>
   {/if}
   <form action="{url '.'}" method="post">
      <table>
         <tr>
            <th><label for="title">{L ['tasks', 'details', 'title']}</label></th>
            <td><input type="text" name="taskTitle" id="title" value="" maxlength="100" required="required" /></td>
         </tr>
         <tr>
            <th><label for="completed">{L ['tasks', 'details', 'completed']}</label></th>
            <td><input type="checkbox" name="taskCompleted" id="completed" value="1" /></td>
         </tr>
      </table>
      <input type="submit" value="{L ['tasks', 'add', 'submit']}" />
   </form>
{/block}