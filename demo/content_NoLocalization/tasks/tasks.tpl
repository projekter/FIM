{extends '/base.tpl'}
{block 'body'}
   <p><a href="{url 'add'}">Add new item</a></p>
   <table class="table">
      <tr>
         <th>Task</th>
         <th>Created</th>
         <th>Completed?</th>
         <th>&nbsp;</th>
      </tr>
      {foreach $tasks as $task}
         <tr>
            <td>
               <a href="{url 'edit' task=$task->id}">{$task->title}</a>
            </td>
            <td>{L formatDate=$task->created}</td>
            <td>{if $task->completed}Yes{else}No{/if}</td>
            <td>
               <a href="{url 'delete' task=$task->id}">Delete</a>
            </td>
         </tr>
      {/foreach}
   </table>
{/block}