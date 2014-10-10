<?php

namespace content\tasks;

class Module extends \Module {

   public function execute() {
      $this->title = 'My task list';
      $this->tasks = \data\task_item::fetchAll();
      $this->displayTemplate('tasks.tpl');
   }
}