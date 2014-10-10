<?php

namespace content\tasks\edit;

class Module extends \Module {

   public function execute(\data\task_item $task) {
      if(\Request::isPost()) {
         $task->completed = \Request::post('taskCompleted');
         $taskTitle = trim(\Request::post('taskTitle'));
         if($taskTitle === '')
            $this->emptyTitle = true;
         else{
            $task->title = $taskTitle;
            $this->redirect('/tasks');
         }
      }
      $this->task = $task;
      $this->displayTemplate('edit.tpl');
   }

}
