{extends '/base.tpl'}
{block 'body'}
   <p><a href="{url 'add'}">{L ['tasks', 'add', 'title']}</a></p>
   <table class="table">
      <tr>
         <th>{L ['tasks', 'details', 'task']}</th>
         <th>{L ['tasks', 'details', 'created']}</th>
         <th>{L ['tasks', 'details', 'completed']}</th>
         <th>&nbsp;</th>
      </tr>
      {foreach $tasks as $task}
         <tr>
            <td>
               <a href="{url 'edit' task=$task->id}">{$task->title}</a>
            </td>
            <td>{L formatDate=$task->created}</td>
            <td>{if $task->completed}{L ['global', 'yes']}{else}{L ['global', 'no']}{/if}</td>
            <td>
               <a href="{url 'delete' task=$task->id}">{L ['tasks', 'details', 'delete']}</a>
            </td>
         </tr>
      {/foreach}
   </table>
{/block}