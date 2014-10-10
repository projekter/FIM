{extends file='/base.tpl'}
{block name='body'}
   <form action="{url '.' task=$task->id}" method="post">
      <p>{L ['tasks', 'delete', 'confirmation'] $task->completed $task->title}</p>
      <input type="submit" name="delete" value="{L ['global', 'yes']}" />
      <input type="submit" name="cancel" value="{L ['global', 'no']}" />
   </form>
{/block}