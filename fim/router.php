<?php

/*
 * Copyright (c) 2014, Benjamin Desef
 * All rights reserved.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This program is licensed under the Creative Commons
 * Attribution-NonCommercial-ShareAlike 4.0 International License. You should
 * have received a copy of this license along with this program. If not,
 * see <http://creativecommons.org/licenses/by-nc-sa/4.0/>.
 */

if(@FrameworkPath === 'FrameworkPath')
   die('Please do not call this file directly.');

/**
 * This class provides helper functions to convert between urls and internal
 * mappings.
 */
abstract class Router {

   private static $instances = [];

   /**
    * Converts a given relative path to an array
    * @param string $path
    * @return string[]
    */
   private static function arrayize($path) {
      if($path == '')
         $path = '.';
      else
         $path = str_replace('\\', '/', $path);
      $parts = array_filter(explode('/', $path));
      if($path[0] === '/')
         $relToCodeDir = [];
      else{
         $relToCodeDir = str_replace('\\', '/',
            (string)substr(getcwd(), strlen(CodeDir)));
         $relToCodeDir = $relToCodeDir === '' ? [] : explode('/', $relToCodeDir);
      }
      foreach($parts as $part)
         if($part !== '.')
            if($part === '..')
               array_pop($relToCodeDir);
            else
               $relToCodeDir[] = $part;
      return $relToCodeDir;
   }

   /**
    * Cleans a path array by eleminiating relative structures.
    * @param array $path
    * @return array
    */
   private static function sanitizePathArray(array $path) {
      $newPath = [];
      foreach($path as $part)
         if($part !== '' && $part !== '.')
            if($part === '..')
               array_pop($newPath);
            else
               $newPath[] = $part;
      return $newPath;
   }

   /**
    * Converts a path to a correct url, respecting all subdomain settings.
    * @param string $path Any path relative to the current working directory or
    *    absolute (= relative to CodeDir) - must lie in content/ or script/
    *    subdirectory!
    * @param bool $directory Indicates whether the source is a directory
    *    (module)
    * @param bool $allServers If the url would differ according to the current
    *    server, a two-entries array is returned. [0]: The part before the
    *    current server, [1]: The part after the current server. If the urls
    *    would be the same, a string is returned as usual.
    * @return string|boolean|array False if the path was invalid i.e. not within
    *    the content directory or not reachable due to subdomain default
    *    settings.
    */
   private static final function convertPathToURL($path, $directory, $allServers) {
      $relToCodeDir = self::arrayize($path);
      if(@$relToCodeDir === 'script') {
         $impl = implode('/', $relToCodeDir);
         if(is_dir(CodeDir . $impl) && !empty($relToCodeDir))
            $relToCodeDir[] = '';# close with trailing slash for directories
         return "/$impl";
      }elseif(@$relToCodeDir[0] !== 'content')
         return false;
      $depth = Config::get('subdomainDepth');
      if(!CLI && $depth === 0) {
         # Speed up things a bit
         unset($relToCodeDir[0]);
         $result = BaseDir;
      }else{
         array_shift($relToCodeDir);
         $subdomainArray = array_splice($relToCodeDir, 0, $depth);
         $subdomainFolder = implode('/', $subdomainArray);
         if(!$directory && empty($relToCodeDir)) {
            # We could either check for is_file or !is_dir. But as modules can
            # emulate files while they cannot emulate directories, lets treat
            # everything that is not a dir - also non-existing stuff - as a file...
            $relToCodeDir = (array)array_pop($subdomainArray);
            $subdomainFolder = implode('/', $subdomainArray);
         }
         if(CLI || $subdomainFolder !== CurrentSubdomain) {
            static $subdomainDefault = null;
            if(!isset($subdomainDefault))
               $subdomainDefault = Config::get('subdomainDefault');
            if($subdomainFolder === $subdomainDefault)
               $subdomainArray = [];
            else{
               # Maybe subdomainDefault prevents from reaching this address..
               $defaultCount = $subdomainDefault === '' ? 0 : substr_count($subdomainDefault,
                     '.') + 1;
               if(count($subdomainArray) < $defaultCount)
               # We are at a deeper level of hierachy by default than requested
                  return false;
               unset($defaultCount);
            }
            $subdomainFolder = empty($subdomainArray) ? '' : implode('.',
                  array_reverse($subdomainArray)) . '.';
            static $https;
            if(!isset($https))
               $https = Request::isHTTPS();
            if($allServers) {
               $prefix = ($https ? 'https://' : 'http://') . $subdomainFolder;
               static $multipleBases = null;
               if(!isset($multipleBases))
                  $multipleBases = count(Config::get('subdomainBase')) > 1;
               if($multipleBases)
                  $result = [$prefix];
               else
                  $result = $prefix . Server . '/';
            }else
               $result = ($https ? 'https://' : 'http://') . $subdomainFolder . Server . '/';
         }else
            $result = '/';
      }
      if($directory && !empty($relToCodeDir))
         $relToCodeDir[] = '';# close with trailing slash for directories
      if(isset($multipleBases) && $multipleBases) {
         $result[] = '/' . implode('/', $relToCodeDir);
         return $result;
      }else
         return $result . implode('/', $relToCodeDir);
   }

   /**
    * Converts a relative or absolute path into a sensitive path.
    * @param string $path Any path relative to the current working directory or
    *    absolute (= relative to CodeDir)
    * @param boolean $returnArray Does not return the path as a string but as an
    *    array, each hierarchy level as an own entry
    * @param boolean $relativeToCodeDir Removes any path before CodeDir
    * @return string|array If not $relativeToCodeDir:<br />
    *    absolute path, starting with slash on non-Windows systems, no closing /
    * <br />If $relativeToCodeDir:<br />
    *    relative path, starting without slash, no closing /
    * <br />If $returnArray:<br />
    *    no distinction between $relativeToCodeDir and not possible without
    *    knowing its value
    */
   public static final function normalize($path, $returnArray = false,
      $relativeToCodeDir = true) {
      $relToCodeDir = self::arrayize($path);
      if($returnArray)
         if($relativeToCodeDir)
            return $relToCodeDir;
         else
            return array_filter(array_merge(explode('/', CodeDir), $relToCodeDir));
      $ret = implode('/', $relToCodeDir);
      if($relativeToCodeDir)
         return $ret;
      elseif($ret === '')
         return substr(CodeDir, 0, -1);
      else
         return CodeDir . $ret;
   }

   /**
    * Converts a relative or absolute url into a sensitive url with regards
    * to url parameters and escaping
    * @param string $path Any url relative to the current working directory or
    *    absolute (= relative to CodeDir)
    * @param array &$parameters A reference array that will be filled with all
    *    parameters
    * @return array Each part of the path as an entry in the array
    */
   public static final function normalizeURL($path, &$parameters) {
      $path = explode('?', $path, 2);
      $parameters = [];
      if(isset($path[1])) {
         $params = explode('&', $path[1]);
         foreach($params as $p) {
            $p = array_map('urldecode', explode('=', $p, 2));
            if(isset($p[1])) {
               $key = explode('[]', $p[0]);
               if(isset($key[1]) && $key[1] === '' && !isset($key[2]))
                  if(isset($parameters[$key[0]]))
                     if(is_array($parameters[$key[0]]))
                        $parameters[$key[0]][] = $p[1];
                     else
                        $parameters[$key[0]] = [$parameters[$key[0]], $p[1]];
                  else
                     $parameters[$key[0]] = [$p[1]];
               else
                  $parameters[$key[0]] = $p[1];
            }else
               $parameters[$p[0]] = true;
         }
      }
      return array_map('urldecode', self::normalize($path[0], true));
   }

   /**
    * Converts an array into a valid parameter string
    * @param array $parameters
    * @return string A valid url parameter, starting with ? or an empty string
    */
   public static final function getParamString(array $parameters) {
      if(empty($parameters))
         return '';
      $result = '';
      foreach($parameters as $key => $value) {
         $key = urlencode($key);
         if(is_array($value))
            foreach($value as $e)
               $result .= "&{$key}[]=" . urlencode($e);
         else
            $result .= "&{$key}=" . urlencode($value);
      }
      $result[0] = '?';
      return $result;
   }

   /**
    * Converts a given url to a valid path respecing given routings. The result
    * will be relative to CodeDir and neither start nor end with a slash
    * @param string $url
    * @param array &$parameters Outgoing parameter array
    * @param bool &$noRouting This will be true when no routing will ever occur
    *    to this path
    * @return string|false False if the url was invalid, i.e. not mappable
    */
   public static final function mapURLToPath($url, &$parameters,
      &$noRouting = null) {
      $url = self::normalizeURL('/' . ResourceDir . "/$url", $parameters);
      $noRouting = true;
      if(empty($url) || $url[0] !== ResourceDir)
         return false;
      $dir = $namespace = array_shift($url);
      $absDir = CodeDir . $dir;
      while(true) {
         $stopRewriting = false;
         if(isset(self::$instances[$absDir]))
            $tmp = self::$instances[$absDir]->rewriteURL($url, $parameters,
               $stopRewriting);
         elseif(is_file("$absDir/fim.router.php")) {
            $class = "$namespace\\Router";
            self::$instances[$absDir] = new $class;
            $tmp = self::$instances[$absDir]->rewriteURL($url, $parameters,
               $stopRewriting);
         }
         if(isset($tmp)) {
            $noRouting = false;
            if($tmp === false)
               return false;
            elseif(is_array($tmp))
               $url = self::sanitizePathArray($tmp);
            else
               $url = self::arrayize("/$tmp");
            unset($tmp);
         }
         if(empty($url))
            break;
         if($stopRewriting) {
            if(!isset($tmp))
               $dir .= '/' . implode('/', $url);
            break;
         }
         $new = array_shift($url);
         $dir .= "/$new";
         $absDir .= "/$new";
         $namespace .= "\\$new";
      }
      return $dir;
   }

   /**
    * Converts a given path with parameters to a url.
    * @param string $path
    * @param array $parameters
    * @param bool &$noRouting This will be true when no routing will ever occur
    *    to this path
    * @param bool $allServers If the url would differ according to the current
    *    server, a two-entries array is returned. [0]: The part before the
    *    current server, [1]: The part after the current server. If the urls
    *    would be the same, a string is returned as usual.
    * @return string|boolean|array False if the path was invalid i.e. not within
    *    the content directory or not reachable due to subdomain default
    *    settings.
    */
   public static final function mapPathToURL($path, array $parameters = [],
      &$noRouting = null, $allServers = false) {
      $noRouting = true;
      $path = self::normalize($path, true);
      if(empty($path) || ($path[0] !== 'content' && $path[0] !== 'script'))
         return false;
      $dir = [];
      $namespace = implode('\\', $path);
      $absDir = CodeDir . implode('/', $path);
      $isDir = is_dir($absDir);
      $noRouting = true;
      while(true) {
         $stopRewriting = false;
         if(isset(self::$instances[$absDir]))
            $tmp = self::$instances[$absDir]->rewritePath($dir, $parameters,
               $stopRewriting);
         elseif(is_file("$absDir/fim.router.php")) {
            $class = "$namespace\\Router";
            self::$instances[$absDir] = new $class;
            $tmp = self::$instances[$absDir]->rewritePath($dir, $parameters,
               $stopRewriting);
         }
         if(isset($tmp)) {
            $noRouting = false;
            if($tmp === false)
               return false;
            elseif(is_array($tmp))
               $dir = self::sanitizePathArray($tmp);
            else
               $dir = self::arrayize("/$tmp");
            unset($tmp);
         }
         $new = array_pop($path);
         array_unshift($dir, $new);
         if(empty($path))
            break;
         if($stopRewriting) {
            if(!empty($path))
               $dir = array_merge($path, $dir);
            break;
         }
         $namespace = implode('\\', $path);
         $absDir = CodeDir . implode('/', $path);
      }
      $paths = self::convertPathToURL('/' . implode('/', $dir), $isDir,
            $allServers);
      if($paths === false)
         return false;
      elseif(is_array($paths))
         return [$paths[0], $paths[1] . self::getParamString($parameters)];
      else
         return $paths . self::getParamString($parameters);
   }

   /**
    * This function is internally triggered when a URL has to be rewritten to
    * a path.
    * @param array $url Each folder of the url is an own entry
    * @param array &$parameters Associative array of GET-parameters; this is
    *    also the outgoing array for parameters after the mapping. You MUST NOT
    *    change this parameter if you return null.
    * @param bool $stopRewriting Determines whether no recursive rewriting
    *    functions shall be applied after this one
    * @return false|string|array|null Return false if the given url is not
    *    valid with any routing delivered by this function.
    *    Return a string that contains the url as it should have been to access
    *    the resource (but do not escape the url); return an array that contains
    *    the path segments of the should-be path as the <b>preferred way</b>.
    *    Don't return anything if this function does not alter the current
    *    mapping.
    */
   protected abstract function rewriteURL(array $url, array &$parameters,
      &$stopRewriting);

   /**
    * This function is internally triggered when a path has to be rewritten to
    * a URL.
    * @param array $path Each folder of the url is an own entry
    * @param array $parameters Associative array of GET-parameters; this is
    *    also the outgoing array for parameters after the mapping. You MUST NOT
    *    change this parameter if you return null
    * @param bool $stopRewriting Determines whether no recursive rewriting
    *    functions shall be applied after this one
    * @return false|string|array|null Return false if the given path is not
    *    available with any routing delivered by this function.
    *    Return a string that contains the path (not url, no escaping) as it
    *    should have been to access the resource; return an array that contains
    *    the segments of the should-be path as an alternative. Don't
    *    return anything if this function does not alter the current mapping.
    */
   protected abstract function rewritePath(array $path, array &$parameters,
      &$stopRewriting);
}
