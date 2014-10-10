<?php

namespace content\tasks;

class Module extends \Module {

   public function execute() {
      $this->tasks = \data\task_item::fetchAll();
      $this->displayTemplate('tasks.tpl');
   }

   public function handleError401() {
      if(\Request::isPost()) {
         if(\Request::post('password') === 'admin') {
            \Session::set('authenticated', true);
            \Request::restoreURL();
            return;
         }else
            $this->failed = true;
      }else
         \Request::saveURL();
      $this->displayTemplate('login.tpl');
   }
}