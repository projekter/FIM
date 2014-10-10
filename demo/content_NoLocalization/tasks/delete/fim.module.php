<?php

namespace content\tasks\delete;

class Module extends \Module {

   public function execute(\data\task_item $task) {
      if(\Request::isPost()) {
         if(\Request::hasPost('delete'))
            $task->delete();
         $this->redirect('/tasks');
      }
      $this->title = 'Delete task';
      $this->task = $task;
      $this->displayTemplate('delete.tpl');
   }

}
