{extends file='/base.tpl'}
{block name='body'}
   <form action="{url '.' task=$task->id}" method="post">
      <p>Do you want to delete the{if $task->completed} completed{/if} task &raquo;{$task->title}&laquo;?</p>
      <input type="submit" name="delete" value="Yes" />
      <input type="submit" name="cancel" value="No" />
   </form>
{/block}