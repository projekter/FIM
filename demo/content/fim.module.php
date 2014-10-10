<?php

namespace content;

class Module extends \Module {

   public function handleError($errno) {
      $this->errno = $errno;
      $this->displayTemplate('error.tpl');
   }

}
