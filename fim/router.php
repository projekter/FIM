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

   /**
    * @var Router[]
    */
   private static $instances = [];

   /**
    * Converts a path into an array. The resulting array will contain an
    * absolute path.
    * @param string $path
    * @param bool $fimStyle True: Maximum level of absoluteness is CodeDir<br />
    *    False: As expected from a filesystem normalizer
    * @return array
    */
   private static function convertPathToArray($path, $fimStyle) {
      if(empty($path))
         $path = '.';
      elseif(OS === 'Windows')
         $path = str_replace('\\', '/', $path);
      if($path[0] === '/') # absolute path
         if($fimStyle && @$path[1] !== '/')
            $return = [ResourceDir];
         else
            $return = [];
      elseif(!$fimStyle && OS === 'Windows' && preg_match('/^[a-z]:/i', $path) === 1) {
         $return = []; # absolute path on Windows
         # We will need to save the current drive, as on Windows, anything that
         # will delete this drive from the path array shall be regarded as
         # invalid
         $drive = $path[0];
      }else{ # relative path
         $return = getcwd();
         if($fimStyle) # in FIM style, trim everything beyond CodeDir
            $return = (string)substr($return, strlen(CodeDir));
         if($return === '')
            $return = [];
         else{
            if(OS === 'Windows') {
               $return = str_replace('\\', '/', $return);
               $drive = $return[0];
            }
            $return = explode('/', $return);
         }
      }
      foreach(explode('/', $path) as $part)
         if($part === '..') {
            array_pop($return);
            if(empty($return) && isset($drive))
               $return = ["$drive:"];
         }elseif($part !== '' && $part !== '.')
            $return[] = $part;
      return $return;
   }

   /**
    * Normalizes a path in FIM style. The returned path will start with two
    * slashes and will not end with a slash.
    * @param string $path
    * @param bool $returnArray
    */
   public static function normalizeFIM($path, $returnArray = false) {
      $pathArray = self::convertPathToArray($path, true);
      return $returnArray ? $pathArray : '//' . implode('/', $pathArray);
   }

   /**
    * Normalizes a path in filesystem style. The returned path will start with
    * a slash or drive letter and will not end with a slash.
    * @param string $path
    * @param bool $returnArray
    */
   public static function normalizeFilesystem($path, $returnArray = false) {
      $pathArray = self::convertPathToArray($path, false);
      if($returnArray)
         return $pathArray;
      else{
         $path = implode('/', $pathArray);
         if(OS === 'Windows' && preg_match('/^[a-z]:/i', $path))
            return $path;
         else
            return "/$path";
      }
   }

   /**
    * Converts a FIM path to an absolute path.
    * @param string $fimPath
    * @param bool $normalize If you set this to false, ensure that $fimPath is
    *    already a fully normalized path!
    * @return string
    */
   public static function convertFIMToFilesystem($fimPath, $normalize = true) {
      if($normalize)
         return CodeDir . implode('/', self::convertPathToArray($fimPath, true));
      else
         return CodeDir . substr($fimPath, 2);
   }

   /**
    * Converts a filesystem path to a FIM path, if possible
    * @param string $filesystemPath
    * @param bool $normalize If you set this to false, ensure that
    *    $filesystemPath is already a fully normalized path (-> forward
    *    slashes)!
    * @return false|string
    */
   public static function convertFilesystemToFIM($filesystemPath, $normalize = true) {
      if($normalize)
         $filesystemPath = self::normalizeFilesystem($filesystemPath);
      static $cdl = null;
      if($cdl === null)
         $cdl = strlen(CodeDir);
      $prePath = substr($filesystemPath, 0, $cdl);
      if(OS === 'Windows') {
         if(strcasecmp($prePath, CodeDir) !== 0)
            return false;
      }elseif($prePath !== CodeDir)
         return false;
      return '//' . substr($filesystemPath, $cdl);
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
    * Routing functions must have been applied to the path before!
    * @param string $path Required to start in content/ or script/
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
      $path = self::convertPathToArray($path, true);
      if(@$path[0] === 'script') {
         if($directory && !empty($path))
            $path[] = '';# close with trailing slash for directories
         $path = implode('/', $path);
         if(OS === 'Windows')
            return preg_replace('#(/fim\.)bypass\.#i', '$1', "/$path");
         else
            str_replace('/fim.bypass.', '/fim.', "/$path");
      }elseif(@$path[0] !== 'content')
         return false;
      $depth = Config::get('subdomainDepth');
      if(!CLI && $depth === 0) {
         # Speed up things a bit
         unset($path[0]);
         $result = BaseDir;
      }else{
         array_shift($path);
         $subdomainArray = array_splice($path, 0, $depth);
         if(!$directory && empty($path))
            $path = (array)array_pop($subdomainArray);
         $subdomainFolder = implode('/', $subdomainArray);
         if(CLI || $subdomainFolder !== CurrentSubdomain) {
            static $subdomainDefault = null;
            if($subdomainDefault === null)
               $subdomainDefault = Config::get('subdomainDefault');
            if($subdomainFolder === $subdomainDefault)
               $subdomainArray = [];
            else{
               # Maybe subdomainDefault prevents from reaching this address
               $defaultCount = $subdomainDefault === '' ? 0 : substr_count($subdomainDefault,
                     '.') + 1;
               if(count($subdomainArray) < $defaultCount)
               # We are at a deeper level of hierachy by default than requested
                  return false;
               unset($defaultCount);
            }
            if(empty($subdomainArray))
               $subdomainFolder = '';
            else
               $subdomainFolder = implode('/',
                     preg_replace('#^(fim\.)bypass\.#' . (OS === 'Windows' ? 'i' : ''),
                        '$1', array_reverse($subdomainArray))) . '.';
            static $https = null;
            if($https === null)
               $https = Request::isHTTPS();
            if($allServers) {
               $prefix = ($https ? 'https://' : 'http://') . $subdomainFolder;
               static $multipleBases = null;
               if($multipleBases === null)
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
      if($directory && !empty($path))
         $path[] = '';# close with trailing slash for directories
      if(isset($multipleBases) && $multipleBases) {
         if(OS === 'Windows')
            $result[] = preg_replace('#(/fim\.)bypass\.#i', '$1',
               '/' . implode('/', $path));
         else
            $result[] = str_replace('/fim.bypass.', '/fim.',
               '/' . implode('/', $path));
         return $result;
      }elseif(OS === 'Windows')
         return $result . preg_replace('#(/fim\.)bypass\.#i', '$1',
               implode('/', $path));
      else
         return $result . str_replace('/fim.bypass.', '/fim.',
               implode('/', $path));
   }

   /**
    * Splits a path with parameters in URL-like style in path and parameter
    * @param string $url Any url relative to the current working directory or
    *    absolute (= relative to CodeDir)
    * @param array &$parameters A reference array that will be filled with all
    *    parameters
    * @return array Each part of the path as an entry in the array
    */
   private static final function explodeURL($url, &$parameters) {
      $url = explode('?', $url, 2);
      $parameters = [];
      if(isset($url[1])) {
         $params = explode('&', $url[1]);
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
      return array_map('urldecode', self::convertPathToArray("//{$url[0]}", false));
   }

   /**
    * Converts an array into a valid parameter string
    * @param array $parameters
    * @return string A valid url parameter, starting with ? or an empty string
    */
   private static final function getParamString(array $parameters) {
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
    * Converts a given url to a normalized path respecing given routings.
    * @param string $url
    * @param array &$parameters Outgoing parameter array
    * @param bool &$noRouting This will be true when no routing will ever occur
    *    to this path
    * @param string|null &$failureAt This will be set with the last directory
    *    that could be mapped successfully. It will be null on complete failure
    * @return string|false False if the url was invalid, i.e. not mappable
    */
   public static final function mapURLToPath($url, &$parameters,
      &$noRouting = null, &$failureAt = null) {
      $url = self::explodeURL($url, $parameters);
      $noRouting = true;
      $dir = $namespace = ResourceDir;
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
            if($tmp === false) {
               $failureAt = "//$dir";
               return false;
            }elseif(is_array($tmp))
               $url = self::sanitizePathArray($tmp);
            else
               $url = self::convertPathToArray("/$tmp", false);
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
      if(OS === 'Windows')
         return preg_replace('#(/fim\.)#i', '$1bypass.', "//$dir");
      else
         return str_replace('/fim.', '/fim.bypass.', "//$dir");
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
    *    the content or script directory or not reachable due to subdomain
    *    default settings.
    */
   public static final function mapPathToURL($path, array $parameters = [],
      &$noRouting = null, $allServers = false) {
      $noRouting = true;
      $path = self::convertPathToArray($path, true);
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
               $dir = self::convertPathToArray("/$tmp", false);
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
      $paths = self::convertPathToURL('//' . implode('/', $dir), $isDir,
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
