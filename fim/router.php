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

namespace {

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
       * @param bool $fimStyle True: Maximum level of absoluteness is CodeDir
       *    <br />False: As expected from a filesystem normalizer
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
            # We will need to save the current drive, as on Windows, anything
            # that will delete this drive from the path array shall be regarded
            # as invalid
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
       * Normalizes a path in filesystem style. The returned path will start
       * with a slash or drive letter and will not end with a slash. This
       * function understands the two protocols fim:// and file://, if given.
       * @param string $path
       * @param bool $returnArray
       */
      public static function normalizeFilesystem($path, $returnArray = false) {
         $fim = false;
         if(isset($path[5]) && $path[0] === 'f' && $path[1] === 'i') {
            if($path[2] === 'm' && $path[3] === ':' && $path[4] === '/' && $path[5] === '/') {
               if(isset($path[6]) && $path[6] === '.')
                  $path = substr($path, 6);
               else
                  $path = substr($path, 5);
               $fim = true;
            }elseif($path[2] === 'l' && $path[3] === 'e' && $path[4] === ':' && $path[5] === '/' && $path[6] === '/')
               $path = substr($path[6]);# We need at least one slash at the
            # beginning, as paths can't be relative when file:// or file:/// is
            # specified.
         }
         $pathArray = self::convertPathToArray($path, $fim);
         if($returnArray)
            return $pathArray;
         else{
            $path = implode('/', $pathArray);
            if(OS === 'Windows' && preg_match('/^[a-z]:/i', $path) === 1)
               return $path;
            else
               return "/$path";
         }
      }

      /**
       * Converts a FIM path to an absolute path.
       * @param string $fimPath
       * @param bool $normalize If you set this to false, ensure that $fimPath
       *    is already a fully normalized path!
       * @return string
       */
      public static function convertFIMToFilesystem($fimPath, $normalize = true) {
         if($normalize)
            return CodeDir . implode('/',
                  self::convertPathToArray($fimPath, true));
         else
            return CodeDir . substr($fimPath, 2);
      }

      /**
       * Converts a filesystem path to a FIM path, if possible
       * @param string $filesystemPath
       * @param bool $normalize If you set this to false, ensure that
       *    $filesystemPath is already a fully normalized path (-> forward
       *    slashes, no protocol)!
       * @return false|string
       */
      public static function convertFilesystemToFIM($filesystemPath,
         $normalize = true) {
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
       * @param bool $allServers If the url would differ according to the
       *    current server, a two-entries array is returned. [0]: The part
       *    before the current server, [1]: The part after the current server.
       *    If the urls would be the same, a string is returned as usual.
       * @return string|boolean|array False if the path was invalid i.e. not
       *    within the content directory or not reachable due to subdomain
       *    default settings.
       */
      private static final function convertPathToURL($path, $directory,
         $allServers) {
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
                  # We are at a deeper level of hierachy by default than
                  # requested
                     return false;
                  unset($defaultCount);
               }
               if(empty($subdomainArray))
                  $subdomainFolder = '';
               else
                  $subdomainFolder = implode('/',
                        preg_replace('#^(fim\.)bypass\.#' . (OS === 'Windows' ? 'i'
                                 : ''), '$1', array_reverse($subdomainArray))) . '.';
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
       * @param array &$parameters A reference array that will be filled with
       *    all parameters
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
         return array_map('urldecode',
            self::convertPathToArray("//{$url[0]}", false));
      }

      /**
       * Converts an array into a valid parameter string
       * @param array $parameters
       * @return string A valid url parameter, starting with ? or an empty
       *    string
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
       * Converts a given url to a normalized path respecting given routings.
       * @param string $url
       * @param array &$parameters Outgoing parameter array
       * @param bool &$noRouting This will be true when no routing will ever
       *    occur to this path
       * @param string|null &$failureAt This will be set with the last directory
       *    that could be mapped successfully. It will be null on complete
       *    failure
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
       * @param bool &$noRouting This will be true when no routing will ever
       *    occur to this path
       * @param bool $allServers If the url would differ according to the
       *    current server, a two-entries array is returned. [0]: The part
       *    before the current server, [1]: The part after the current server.
       *    If the urls would be the same, a string is returned as usual.
       * @return string|boolean|array False if the path was invalid i.e. not
       *    within the content or script directory or not reachable due to
       *    subdomain default settings.
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
       *    also the outgoing array for parameters after the mapping. You MUST
       *    NOT change this parameter if you return null.
       * @param bool $stopRewriting Determines whether no recursive rewriting
       *    functions shall be applied after this one
       * @return false|string|array|null Return false if the given url is not
       *    valid with any routing delivered by this function.
       *    Return a string that contains the url as it should have been to
       *    access the resource (but do not escape the url); return an array
       *    that contains the path segments of the should-be path as the
       *    <b>preferred way</b>.<br />
       *    Don't return anything if this function does not alter the current
       *    mapping.
       */
      protected abstract function rewriteURL(array $url, array &$parameters,
         &$stopRewriting);

      /**
       * This function is internally triggered when a path has to be rewritten
       * to a URL.
       * @param array $path Each folder of the url is an own entry
       * @param array $parameters Associative array of GET-parameters; this is
       *    also the outgoing array for parameters after the mapping. You MUST
       *    NOT change this parameter if you return null
       * @param bool $stopRewriting Determines whether no recursive rewriting
       *    functions shall be applied after this one
       * @return false|string|array|null Return false if the given path is not
       *    available with any routing delivered by this function.
       *    Return a string that contains the path (not url, no escaping) as it
       *    should have been to access the resource; return an array that
       *    contains the segments of the should-be path as an alternative. Don't
       *    return anything if this function does not alter the current mapping.
       */
      protected abstract function rewritePath(array $path, array &$parameters,
         &$stopRewriting);
   }

}

namespace fim {

   /**
    * This class defines a fully-featured protocol handler that allows to handle
    * the fim:// protocol.
    */
   class fimStream {

      /**
       * @var resource $context
       * @var resource $handle
       */
      public $context;
      private $handle;

      private function convert($path) {
         # Format: fim://<content>
         if(isset($path[6]) && $path[6] === '.')
            return \Router::convertFIMToFilesystem(substr($path, 6));
         else
            return \Router::convertFIMToFilesystem(substr($path, 5));
      }

      /**
       * Close directory handle
       * @return bool Returns TRUE on success or FALSE on failure.
       */
      public function dir_closedir() {
         return closedir($this->handle);
      }

      /**
       * Open directory handle
       * @param string $path Specifies the URL that was passed to opendir().
       * @param int $options Whether or not to enforce safe_mode (0x04).
       * @return bool Returns TRUE on success or FALSE on failure.
       */
      public function dir_opendir($path, $options) {
         # safe_mode has been removed from PHP 5.4
         return ($this->handle = opendir($this->convert($path), $this->context)) !== false;
      }

      /**
       * Read entry from directory handle
       * @return string|false Should return string representing the next
       *    filename, or FALSE if there is no next file.
       */
      public function dir_readdir() {
         return readdir($this->handle);
      }

      /**
       * Rewind directory handle
       * @return bool Returns TRUE on success or FALSE on failure.
       */
      public function dir_rewinddir() {
         return rewinddir($this->handle);
      }

      /**
       * Create a directory
       * @param string $path Directory which should be created.
       * @param int $mode The value passed to {@see mkdir()}.
       * @param int $options A bitwise mask of values, such as
       *    STREAM_MKDIR_RECURSIVE.
       * @return bool Returns TRUE on success or FALSE on failure.
       */
      public function mkdir($path, $mode, $options) {
         return mkdir($this->convert($path), $mode,
            (bool)($options & STREAM_MKDIR_RECURSIVE), $this->context);
      }

      /**
       * Renames a file or directory
       * @param string $path_from The URL to the current file.
       * @param string $path_to The URL which the $path_from should be renamed
       *    to.
       * @return bool Returns TRUE on success or FALSE on failure.
       */
      public function rename($path_from, $path_to) {
         return rename($this->convert($path_from), $this->convert($path_to),
            $this->context);
      }

      /**
       * Removes a directory
       * @param string $path The directory URL which should be removed.
       * @param int $options A bitwise mask of values, such as
       *    STREAM_MKDIR_RECURSIVE.
       * @return bool Returns TRUE on success or FALSE on failure.
       */
      public function rmdir($path, $options) {
         # rmdir doesn't support options, so we don't need to handle them
         return rmdir($this->convert($path), $this->context);
      }

      /**
       * Retrieve the underlaying resource
       * @param int $cast_as Can be STREAM_CAST_FOR_SELECT when stream_select()
       *    is calling stream_cast() or STREAM_CAST_AS_STREAM when stream_cast()
       *    is called for other uses.
       * @return resource Should return the underlying stream resource used by
       *    the wrapper, or FALSE.
       */
      public function stream_cast($cast_as) {
         return $this->handle;
      }

      /**
       * Close a resource
       */
      public function stream_close() {
         fclose($this->handle);
      }

      /**
       * Tests for end-of-file on a file pointer
       * @return bool Should return TRUE if the read/write position is at the
       *    end of the stream and if no more data is available to be read, or
       *    FALSE otherwise.
       */
      public function stream_eof() {
         return feof($this->handle);
      }

      /**
       * Flushes the output
       * @return bool Should return TRUE if the cached data was successfully
       *    stored (or if there was no data to store), or FALSE if the data
       *    could not be stored.
       */
      public function stream_flush() {
         return fflush($this->handle);
      }

      /**
       * Advisory file locking
       * @param int $operation operation is one of the following:<ul>
       *    <li><b>LOCK_SH</b> to acquire a shared lock (reader).</li>
       *    <li><b>LOCK_EX</b> to acquire an exclusive lock (writer).</li>
       *    <li><b>LOCK_UN</b> to release a lock (shared or exclusive).</li>
       *    <li><b>LOCK_NB</b> if you don't want flock() to block while locking.
       *        (not supported on Windows)</li></ul>
       * @return bool Returns TRUE on success or FALSE on failure.
       */
      public function stream_lock($operation) {
         return flock($this->handle, $operation);
      }

      /**
       * Change stream options
       * @param string $path The file path or URL to set metadata. Note that in
       *    the case of a URL, it must be a :// delimited URL. Other URL forms
       *    are not supported.
       * @param int $option One of:<ul>
       *    <li><b>STREAM_META_TOUCH</b> (The method was called in response to
       *        touch())</li>
       *    <li><b>STREAM_META_OWNER_NAME</b> (The method was called in response
       *        to chown() with string parameter)</li>
       *    <li><b>STREAM_META_OWNER</b> (The method was called in response to
       *        chown())</li>
       *    <li><b>STREAM_META_GROUP_NAME</b> (The method was called in response
       *        to chgrp())</li>
       *    <li><b>STREAM_META_GROUP</b> (The method was called in response to
       *        chgrp())</li>
       *    <li><b>STREAM_META_ACCESS</b> (The method was called in response to
       *        chmod())</li></ul>
       * @param int $value If option is<ul>
       *    <li><b>STREAM_META_TOUCH:</b> Array consisting of two arguments of
       *        the touch() function.</li>
       *    <li><b>STREAM_META_OWNER_NAME</b> or
       *        <b>PHP_STREAM_META_GROUP_NAME:</b> The name of the owner
       *        user/group as string.</li>
       *    <li><b>STREAM_META_OWNER</b> or <b>PHP_STREAM_META_GROUP:</b> The
       *        value owner user/group argument as integer.</li>
       *    <li><b>STREAM_META_ACCESS:</b> The argument of the chmod() as
       *        integer.</li></ul>
       * @return bool Returns TRUE on success or FALSE on failure. If option is
       *    not implemented, FALSE should be returned.
       */
      public function stream_metadata($path, $option, $value) {
         $path = $this->convert($path);
         switch($option) {
            case STREAM_META_TOUCH:
               return touch($path, $value[0], $value[1]);
            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
               return chown($path, $value);
            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
               return chgrp($path, $value);
            case STREAM_META_ACCESS:
               return chmod($path, $value);
            default:
               return false;
         }
      }

      /**
       * Opens file or URL
       * @param string $path Specifies the URL that was passed to the original
       *    function.
       * @param string $mode The mode used to open the file, as detailed for
       *    fopen().
       * @param int $options Holds additional flags set by the streams API.
       *    It can hold one or more of the following values OR'd together.
       *    <table>
       *       <tr>
       *          <td>STREAM_USE_PATH</td>
       *          <td>If path is relative, search for the resource using the
       *              include_path.</td>
       *       </tr>
       *       <tr>
       *          <td>STREAM_REPORT_ERRORS</td>
       *          <td>If this flag is set, you are responsible for raising
       *              errors using trigger_error() during opening of the stream.
       *              If this flag is not set, you should not raise any
       *              errors.</td>
       *       </tr>
       *    </table>
       * @param string $opened_path If the path is opened successfully, and
       *    STREAM_USE_PATH is set in options, opened_path should be set to the
       *    full path of the file/resource that was actually opened.
       * @return bool Returns TRUE on success or FALSE on failure.
       */
      public function stream_open($path, $mode, $options, &$opened_path) {
         $opened_path = $this->convert($path);
         if($options & STREAM_REPORT_ERRORS)
            $this->handle = fopen($opened_path, $mode, false, $this->context);
         else
            $this->handle = @fopen($opened_path, $mode, false, $this->context);
         return $this->handle !== false;
      }

      /**
       * Read from stream
       * @param int $count How many bytes of data from the current position
       *    should be returned.
       * @return string If there are less than count bytes available, return as
       *    many as are available. If no more data is available, return either
       *    FALSE or an empty string.
       */
      public function stream_read($count) {
         return fread($this->handle, $count);
      }

      /**
       * Seeks to specific location in a stream
       * @param int $offset The stream offset to seek to.
       * @param int $whence Possible values:<ul>
       *    <li><b>SEEK_SET</b> - Set position equal to offset bytes.</li>
       *    <li><b>SEEK_CUR</b> - Set position to current location plus
       *        offset.</li>
       *    <li><b>SEEK_END</b> - Set position to end-of-file plus
       *        offset.</li></ul>
       * @return bool Return TRUE if the position was updated, FALSE otherwise.
       */
      public function stream_seek($offset, $whence = SEEK_SET) {
         return fseek($this->handle, $offset, $whence);
      }

      /**
       * This method is called to set options on the stream.
       * @param int $option One of:<ul>
       *    <li><b>STREAM_OPTION_BLOCKING</b> (The method was called in response
       *        to stream_set_blocking())</li>
       *    <li><b>STREAM_OPTION_READ_TIMEOUT</b> (The method was called in
       *        response to stream_set_timeout())</li>
       *    <li><b>STREAM_OPTION_WRITE_BUFFER</b> (The method was called in
       *        response to stream_set_write_buffer())</li></ul>
       * @param int $arg1 If option is<ul>
       *    <li><b>STREAM_OPTION_BLOCKING:</b> requested blocking mode (1
       *        meaning block 0 not blocking).</li>
       *    <li><b>STREAM_OPTION_READ_TIMEOUT:</b> the timeout in seconds.</li>
       *    <li><b>STREAM_OPTION_WRITE_BUFFER:</b> buffer mode
       *        (STREAM_BUFFER_NONE or STREAM_BUFFER_FULL).</li></ul>
       * @param int $arg2 If option is<ul>
       *    <li><b>STREAM_OPTION_BLOCKING:</b> This option is not set.</li>
       *    <li><b>STREAM_OPTION_READ_TIMEOUT:</b> the timeout in
       *        microseconds.</li>
       *    <li><b>STREAM_OPTION_WRITE_BUFFER:</b> the requested buffer
       *        size.</li></ul>
       * @return bool Returns TRUE on success or FALSE on failure. If option is
       *    not implemented, FALSE should be returned.
       */
      public function stream_set_option($option, $arg1, $arg2) {
         switch($option) {
            case STREAM_OPTION_BLOCKING:
               return stream_set_blocking($this->handle, $arg1);
            case STREAM_OPTION_READ_TIMEOUT:
               return stream_set_timeout($this->handle, $arg1, $arg2);
            case STREAM_OPTION_WRITE_BUFFER:
               return stream_set_write_buffer($this->handle,
                  $arg1 === STREAM_BUFFER_NONE ? 0 : $arg2);
            default:
               return false;
         }
      }

      /**
       * Retrieve information about a file resource
       * @return array See stat()
       */
      public function stream_stat() {
         return fstat($this->handle);
      }

      /**
       * Retrieve the current position of a stream
       * @return int Should return the current position of the stream.
       */
      public function stream_tell() {
         return ftell($this->handle);
      }

      /**
       * Truncate stream
       * @param int $new_size The new size.
       * @return bool Returns TRUE on success or FALSE on failure
       */
      public function stream_truncate($new_size) {
         return ftruncate($this->handle, $new_size);
      }

      /**
       * Write to stream
       * @param string $data Should be stored into the underlying stream.
       * @return int Should return the number of bytes that were successfully
       *    stored, or 0 if none could be stored.
       */
      public function stream_write($data) {
         return fwrite($this->handle, $data);
      }

      /**
       * Delete a file
       *
       * @param string $path The file URL which should be deleted.
       * @return boolReturns TRUE on success or FALSE on failure.
       */
      public function unlink($path) {
         return unlink($this->convert($path), $this->context);
      }

      /**
       * Retrieve information about a file
       * @param string $path The file path or URL to stat. Note that in the case
       *    of a URL, it must be a :// delimited URL. Other URL forms are not
       *    supported.
       * @param int $flags Holds additional flags set by the streams API. It can
       *    hold one or more of the following values OR'd together.
       *    <table>
       *       <tr>
       *          <td>STREAM_URL_STAT_LINK</td>
       *          <td>For resources with the ability to link to other resource
       *              (such as an HTTP Location: forward, or a filesystem
       *              symlink). This flag specified that only information about
       *              the link itself should be returned, not the resource
       *              pointed to by the link. This flag is set in response to
       *              calls to lstat(), is_link(), or filetype().</td>
       *       </tr>
       *       <tr>
       *          <td>STREAM_URL_STAT_QUIET</td>
       *          <td>If this flag is set, your wrapper should not raise any
       *              errors. If this flag is not set, you are responsible for
       *              reporting errors using the trigger_error() function during
       *              stating of the path.</td>
       *       </tr>
       *    </table>
       * @return array Should return as many elements as stat() does. Unknown or
       *    unavailable values should be set to a rational value (usually 0).
       */
      public function url_stat($path, $flags) {
         $function = ($flags & STREAM_URL_STAT_LINK) ? 'lstat' : 'stat';
         if($flags & STREAM_URL_STAT_QUIET)
            return @$function($this->convert($path));
         else
            return $function($this->convert($path));
      }

   }

   stream_wrapper_register('fim', '\\fim\\fimStream');
}