<?php

namespace content;

class Router extends \Router {

   protected function rewritePath(array $path, array &$parameters,
      &$stopRewriting) {
      if(!empty($path) && $path[0] === 'tasks')
         return array_slice($path, 1);
   }

   protected function rewriteURL(array $url, array &$parameters, &$stopRewriting) {
      if(empty($url) || $url[0] === 'tasks' || is_dir("fim://tasks/{$url[0]}"))
         return array_merge(['tasks'], $url);
   }

}
