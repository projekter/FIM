<?php

namespace content\tasks;

class Rules extends \Rules {

   /**
    * @param string $fileName
    * @param string $fullName
    * @param bool $authenticated session
    */
   public function checkReading($fileName, $fullName, $authenticated) {
      if($fileName !== '.' && !$authenticated)
         return false;
   }
}