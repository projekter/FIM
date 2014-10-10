<?php

namespace content\tasks;

class Router extends \Router {

   private $useParameters = true;

   protected function rewritePath(array $path, array &$parameters,
      &$stopRewriting) {
      if(empty($path) || $path[0] === 'add')
         return;
      if(!$this->useParameters && isset($parameters['task'])) {
         array_push($path, $parameters['task']);
         unset($parameters['task']);
      }
      return $path;
   }

   protected function rewriteURL(array $url, array &$parameters, &$stopRewriting) {
      if(empty($url) || $url[0] === 'add')
         return;
      if(!($this->useParameters = isset($parameters['task'])) && isset($url[1])) {
         $parameters['task'] = $url[1];
         unset($url[1]);
      }
      return $url;
   }

}
