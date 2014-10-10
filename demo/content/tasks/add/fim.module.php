<?php

namespace content\tasks\add;

class Module extends \Module {

   public function execute() {
      if(\Request::isPost()) {
         $taskTitle = trim(\Request::post('taskTitle'));
         if($taskTitle === '')
            $this->emptyTitle = true;
         else{
            \data\task_item::create($taskTitle,
               \Request::boolPost('taskCompleted'));
            $this->redirect('/tasks');
         }
      }
      $this->displayTemplate('add.tpl');
   }

}
