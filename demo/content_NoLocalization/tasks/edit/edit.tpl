{extends file='/base.tpl'}
{block name='body'}
   {if isset($emptyTitle)}
      <p class="alert">Please enter a task name.</p>
   {/if}
   <form action="{url '.' task=$task->id}" method="post">
      <table>
         <tr>
            <th><label for="title">Title</label></th>
            <td><input type="text" name="taskTitle" id="title" value="{$task->title}" maxlength="100" required="required" /></td>
         </tr>
         <tr>
            <th><label for="completed">Completed?</label></th>
            <td><input type="checkbox" name="taskCompleted" id="completed" value="1"{if $task->completed} checked="checked"{/if} /></td>
         </tr>
      </table>
      <input type="submit" value="Save changes" />
   </form>
{/block}