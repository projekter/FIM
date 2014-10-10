<?php

namespace content;

class Router extends \Router {

   protected function rewritePath(array $path, array &$parameters,
      &$stopRewriting) {
      if(!empty($path) && $path[0] === 'tasks')
         $path[0] = \I18N::getLocale()->getLanguageId();
      else
         array_unshift($path, \I18N::getLocale()->getLanguageId());
      return $path;
   }

   protected function rewriteURL(array $url, array &$parameters, &$stopRewriting) {
      $language = array_shift($url);
      if(empty($language)) {
         \I18N::setLocale(\I18N::detectBrowserLocale());
         \Response::set('Location',
            Router::mapPathToURL('/' . implode('/', $url), $parameters));
      }else
         \I18N::setLocale($language);
      if(empty($url) || $url[0] === 'tasks' || is_dir("fim://tasks/{$url[0]}"))
         return array_merge(['tasks'], $url);
      else
         return $url;
   }

}
