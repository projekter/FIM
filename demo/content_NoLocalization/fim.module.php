<?php

namespace content;

class Module extends \Module {

   public function handleError($errno) {
      $this->title = "Error $errno (" . \Response::translateHTTPCode($errno) . ')';
      $this->displayTemplate('error.tpl');
   }

}
