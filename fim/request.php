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
 * This class provides methods to deal with incoming requests.
 */
abstract class Request {

   private static $browser = null, $https = null;

   private final function __construct() {

   }

   /**
    * Returns a GET parameter
    * @param string $param The name of the parameter
    * @param string $default A default value that will be returned if the
    *    parameter is not set
    * @return string|null The value of the parameter or the default value or
    *    null if no default value was specified.
    */
   public static final function get($param, $default = null) {
      return isset($_GET[$param]) ? $_GET[$param] : $default;
   }

   /**
    * Returns a POST parameter
    * @param string $param The name of the parameter
    * @param string $default A default value that will be returned if the
    *    parameter is not set
    * @return string|null The value of the parameter or the default value or
    *    null if no default value was specified.
    */
   public static final function post($param, $default = null) {
      return isset($_POST[$param]) ? $_POST[$param] : $default;
   }

   private static $bools = ['0' => false, '1' => true,
      'false' => false, 'true' => true, 'FALSE' => false, 'TRUE' => true,
      'off' => false, 'on' => true, 'OFF' => false, 'ON' => true,
      'no' => false, 'yes' => true, 'NO' => false, 'YES' => true,
      'wrong' => false, 'right' => true, 'WRONG' => false, 'RIGHT' => true];

   /**
    * Returns a boolean GET parameter
    * @param string $param The name of the parameter
    * @param bool $default A default value that will be returned if the
    *    parameter is not set
    * @return bool|null The value of the parameter or the default value or
    *    null if no default value was specified.
    */
   public static final function boolGet($param, $default = false) {
      return (bool)@self::$bools[(string)(isset($_GET[$param]) ? $_GET[$param] : $default)];
   }

   /**
    * Returns a boolean POST parameter
    * @param string $param The name of the parameter
    * @param bool $default A default value that will be returned if the
    *    parameter is not set
    * @return bool|null The value of the parameter or the default value or
    *    null if no default value was specified.
    */
   public static final function boolPost($param, $default = false) {
      return (bool)@self::$bools[(string)(isset($_POST[$param]) ? $_POST[$param]
               : $default)];
   }

   /**
    * Checks whether a GET parameter exists
    * @param string $param The name of the parameter
    * @return bool
    */
   public static final function hasGet($param) {
      return isset($_GET[$param]);
   }

   /**
    * Checks whether a POST parameter exists
    * @param string $param The name of the parameter
    * @return bool
    */
   public static final function hasPost($param) {
      return isset($_POST[$param]);
   }

   /**
    * Checks whether a specific file was uploaded
    * @param string $param The name of the field that hold the file
    * @param int $checkSuccess Set this to TRUE in order to check whether the
    *    file upload was finished successfully.
    * @return bool
    */
   public static final function hasFile($param, $checkSuccess = false) {
      if($checkSuccess)
         return isset($_FILES[$param]) && ($_FILES[$param]['error'] === UPLOAD_ERR_OK);
      else
         return isset($_FILES[$param]);
   }

   /**
    * Returns an array of all parameter entries
    * @param string $filter (default null) Set this to any regular expression
    *    so that only keys that match the expression will be returned.
    * @param bool $post (default true) Set this to TRUE to check for parameters
    *    transferred with POST. Set this to FALSE to check for the ones
    *    transferred via GET. If there were files, they will be included in the
    *    POST version.
    * @param bool $iterator (default false) Set this to TRUE to return an
    *    iterator object instead of an array. This makes sense if you do apply
    *    a filter to the function as it prevents the function from recreating
    *    an array whilst it is not recommended when using the default (all
    *    capturing) filter string.
    * @param int $matchFlag (default RegexIterator::MATCH) Set this flag if you
    *    want the RegexIterator that does the filtering to perform in a
    *    different operation mode.
    * @return array
    */
   public static final function itemize($filter = null, $post = true,
      $iterator = false, $matchFlag = RegexIterator::MATCH) {
      if($post)
         $ret = empty($_FILES) ? $_POST : array_merge($_POST, $_FILES);
      else
         $ret = $_GET;
      if(isset($filter)) {
         $ri = new RegexIterator(new ArrayIterator($ret), $filter, $matchFlag,
            RegexIterator::USE_KEY);
         if(!$iterator)
            return iterator_to_array($ri, true);
         else
            return $ri;
      }elseif($iterator)
         return new ArrayIterator($ret);
      else
         return $ret;
   }

   /**
    * Retrieves the error code that was given while transferring a file. If
    * $param is no valid file form field, UPLOAD_ERR_NO_FILE is returned.
    * @param string $param The name of the parameter
    * @return int One of the UPLOAD_ERR constants
    * @link http://php.net/manual/en/features.file-upload.errors.php
    * @see Request::getFileDetails()
    */
   public static final function getFileError($param) {
      if(isset($_FILES[$param]))
         return $_FILES[$param]['error'];
      else
         return UPLOAD_ERR_NO_FILE;
   }

   /**
    * Stores a file upload to a given location
    * @param string $param The name of the parameter
    * @param string $to A valid target file path+name
    * @return boolean
    */
   public static final function saveFileUpload($param, $to) {
      if(self::hasFile($param, true))
         return @move_uploaded_file($_FILES[$param]['tmp_name'], $to);
      else
         return false;
   }

   /**
    * Retrieves the content of a file as a string value
    * @param string $param The name of the parameter
    * @return string|false
    */
   public static final function getFileContent($param) {
      if(self::hasFile($param, true))
         return file_get_contents($_FILES[$param]['tmp_name']);
      else
         return false;
   }

   /**
    * Retrieves a valid fopen handle to a given file. As this is a temporary
    * file it will be opened with 'rb' privileges.
    * @param string $param The name of the parameter
    * @return resource|false
    */
   public static final function getFileResource($param) {
      if(self::hasFile($param, true))
         return fopen($_FILES[$param]['tmp_name'], 'rb');
      else
         return false;
   }

   /**
    * Retrieves the user-defined name of a file upload
    * @param string $param The name of the parameter
    * @param string $default A default value that will be returned if the
    *    parameter is not set
    * @param boolean $encodeName If set (default), FIM will assure that the
    *    name that is given back can be used as an actual filename on the
    *    file system. For that purpose, illegal characters in the filename may
    *    become masked.
    * @return string|null The name of the file or the default value or null if
    *    no default value was specified.
    * @see Request::getFileDetails()
    */
   public static final function getFileName($param, $default = null,
      $encodeName = true) {
      if(isset($_FILES[$param]))
         return $encodeName ? fileUtils::encodeFilename($_FILES[$param]['name'],
               true) : $_FILES[$param]['name'];
      else
         return $default;
   }

   /**
    * Retrieves the file size of a file upload in bytes
    * @param string $param The name of the parameter
    * @param string $default A default value that will be returned if the
    *    parameter is not set
    * @return string|null The name of the file or the default value or null if
    *    no default value was specified.
    * @see Request::getFileDetails()
    */
   public static final function getFileSize($param, $default = null) {
      if(isset($_FILES[$param]))
         return $_FILES[$param]['size'];
      else
         return $default;
   }

   /**
    * Retrieves the mime type of a file upload. Warning: This value is not
    * reliable as it is determined by the client and not validated internally!
    * @param string $param The name of the parameter
    * @param string $default A default value that will be returned if the
    *    parameter is not set
    * @return string|null The mime type of the file or the default value or null
    *    if no default value was specified.
    * @see Request::getFileDetails()
    */
   public static final function getFileType($param, $default = null) {
      if(isset($_FILES[$param]))
         return $_FILES[$param]['type'];
      else
         return $default;
   }

   /**
    * Retrieves the temporary name of a file upload.
    * @param string $param The name of the parameter
    * @param string $default A default value that will be returned if the
    *    parameter is not set
    * @return string|null The file name of the upload or the default value or
    *    null if no default value was specified.
    * @see Request::getFileDetails()
    */
   public static final function getFileTemporaryName($param, $default = null) {
      if(isset($_FILES[$param]))
         return $_FILES[$param]['tmp_name'];
      else
         return $default;
   }

   /**
    * Retrieves an associative array holding all details regarding a specific
    * file upload. Use this function instead of calling getFileError,
    * getFileName, getFileSize or getFileTypes one after the other.
    * @param string $param The name of the parameter
    * @param boolean $encodeName If set (default), FIM will assure that the
    *    name-index that is given back can be used as an actual filename on the
    *    file system. For that purpose, illegal characters in the filename may
    *    become masked.
    * @return array|false ['name' => getFileName, 'type' => getFileType,
    *    'size' => getFileSize, 'error' => getFileError,
    *    'tmp_name' => getFileTemporaryName]
    * @see Request::getFileName()
    * @see Request::getFileType()
    * @see Request::getFileSize()
    * @see Request::getFileError()
    * @see Request::getFileTemporaryName()
    */
   public static final function getFileDetails($param, $encodeName = true) {
      if(isset($_FILES[$param])) {
         if($encodeName) {
            $result = $_FILES[$param];
            $result['name'] = fileUtils::encodeFilename($result['name'], true);
            return $result;
         }else
            return $_FILES[$param];
      }else
         return false;
   }

   /**
    * Gets the current uri that was requested. This does not include subdomains
    * or the server name and no parameters as well. BaseDir will be truncated if
    * necessary. The URL will start with a slash.
    * @param bool $parameters (default false) Set this to true if GET parameters
    *    shall be included
    * @return string
    * @see \Request::getFullURL()
    */
   public static final function getURL($parameters = false) {
      if(CLI) {
         global $argv;
         $path = isset($argv[1]) ? $argv[1] : '/';
         if($parameters) {
            $params = array_slice($argv, 2);
            $params = empty($params) ? '' : ' ' . implode(' ', $params);
         }else
            $params = '';
         if($path == '')
            return "/$params";
         elseif($path[0] !== '/')
            return "/$path$params";
         else
            return $path . $params;
      }
      $array = explode('?', $_SERVER['REQUEST_URI'], 2);
      $dir = $array[0];
      if($parameters)
         $params = isset($array[1]) ? "?{$array[1]}" : '';
      else
         $params = '';
      if(BaseDir !== '')
         $dir = substr($dir, strlen(BaseDir));
      if($dir === '')
         return "/$params";
      elseif($dir[0] !== '/')
         return "/$dir$params";
      else
         return $dir . $params;
   }

   /**
    * Gets the current parameter string that is appended to the url, starting
    * with the delimiter character. An empty string if there were no
    * parameters.
    * @return string
    */
   public static final function getParameterString() {
      if(CLI) {
         global $argv;
         $params = array_slice($argv, 2);
         return empty($params) ? '' : ' ' . implode(' ', $params);
      }
      $ruri = explode('?', $_SERVER['REQUEST_URI'], 2);
      return isset($ruri[1]) ? "?{$ruri[1]}" : '';
   }

   /**
    * Determines whether the request was sent via HTTPS
    * @return bool
    */
   public static final function isHTTPS() {
      # This function is called very early in FIM's initialization process. We
      # can rely on self::$https having a value in any other function
      if(self::$https === null)
         self::$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
      return self::$https;
   }

   /**
    * Determines whether the request method was POST
    * @return bool
    */
   public static final function isPost() {
      return CLI ? false : ($_SERVER['REQUEST_METHOD'] === 'POST');
   }

   /**
    * Determines whether the request method was GET
    * @return bool
    */
   public static final function isGet() {
      return CLI ? false : ($_SERVER['REQUEST_METHOD'] === 'GET');
   }

   /**
    * Determines whether a POST request was started but the maximum POST size
    * was exceeded. This can happen when there were too large file uploads and
    * it means that no POST variable is available.
    * @return bool
    * @see Request::getMaxUploadSize()
    */
   public static final function postSizeExceeded() {
      return CLI ? false :
         (($_SERVER['REQUEST_METHOD'] === 'POST') && empty($_POST) && empty($_FILES));
   }

   /**
    * Saves the current url so that it can be restored in further processing.
    * As this will be saved for new requests until it is restored, this function
    * can e.g. be used to redirect to an inaccessible page after login.
    */
   public static final function saveURL() {
      Session::set('__OLD_URL', self::getFullURL());
   }

   /**
    * Performs a redirect to the url that was saved with ::saveURL(). Execution
    * will not be halted!
    * @return boolean false if there was no available url
    */
   public static final function restoreURL() {
      $url = Session::get('__OLD_URL');
      if($url === null)
         return false;
      else{
         Session::delete('__OLD_URL');
         Response::set('Location', $url);
         return true;
      }
   }

   /**
    * Determines the browser the user used according to the user agent. If used
    * in client mode, 'userAgent' and 'name' will be null, 'version' will be any
    * os-dependent version string and 'platform' will contain the os's name on
    * which the php process is executed.
    * @return array ['userAgent' => user agent string,
    *                'name' => 'MSIE|Firefox|Chrome|Safari|Opera|NetSurf|Lynx|Arachne|Contiki|Netscape',
    *                'version' => browser version,
    *                'platform' => 'Android|Linux|Mac|Windows|Contiki|DOS']
    */
   public static final function getBrowser() {
      if(self::$browser !== null)
         return self::$browser;
      if(CLI)
         return self::$browser = ['userAgent' => null, 'name' => null, 'version' => php_uname('v'),
            'platform' => OS];
      # Thanks to http://www.php.net/manual/function.get-browser.php#101125
      $userAgent = @$_SERVER['HTTP_USER_AGENT'];
      $platform = 'Unknown';
      $version = 0;
      if(preg_match('/windows|win32/i', $userAgent))
         $platform = 'Windows';
      elseif(preg_match('/linux/i', $userAgent))
         $platform = 'Linux';
      elseif(preg_match('/macintosh|mac os x/i', $userAgent))
         $platform = 'Mac';
      elseif(preg_match('/android/i', $userAgent))
         $platform = 'Android';
      elseif(preg_match('/contiki/i', $userAgent))
         $platform = 'Contiki';
      elseif(preg_match('/dos/i', $userAgent))
         $platform = 'DOS';
      static $browsers = ['Opera', 'OPR', 'MSIE', 'Firefox', 'Chrome', 'Safari',
         'Trident', 'NetSurf', 'Lynx', 'Arachne', 'Contiki', 'Netscape'];
      $userBrowser = 'Unknown';
      foreach($browsers as $browser)
         if(preg_match("/$browser/i", $userAgent)) {
            $userBrowser = $browser;
            break;
         }
      $pattern = "#(?<browser>Version|$userBrowser|other)[/ ]+(?<version>[0-9.|a-zA-Z.]*)#";
      if(preg_match_all($pattern, $userAgent, $matches))
         if(count($matches['browser']) !== 1)
            if(strripos($userAgent, 'Version') < strripos($userAgent,
                  $userBrowser))
               $version = $matches['version'][0];
            else
               $version = $matches['version'][1];
         else
            $version = $matches['version'][0];
      if($userBrowser === 'Trident') {
         if(preg_match('#; rv:(\\d+)#', $userAgent, $matches))
            $version = $matches[1];
         $userBrowser = 'MSIE';
      }elseif($userBrowser === 'OPR')
         $userBrowser = 'Opera';
      return self::$browser = ['userAgent' => $userAgent, 'name' => $userBrowser,
         'version' => (int)$version, 'platform' => $platform];
   }

   /**
    * Returns the port that is used for the current request.
    * @param bool $insertable Returns a string of format ":{port number}" or an
    *    empty string when the port is the protocol's default port if true or
    *    an integer if false
    * @return string|int
    */
   public static final function getPort($insertable = false) {
      if(CLI)
         return $insertable ? ':0' : 0;
      $port = @$_SERVER['SERVER_PORT'];
      if($insertable)
         if((!self::$https && $port == 80) || (self::$https && $port == 443))
            return '';
         else
            return ":$port";
      else
         return (int)$port;
   }

   /**
    * Returns the full url with which the request was started, including
    * protocol, host and path.
    * @param bool $includePath Set this to false in order only to get the host.
    *    The url will not contain a trailing slash.
    * @return string
    * @see \Request::getURI()
    */
   public static final function getFullURL($includePath = true) {
      if(CLI)
         return "{$GLOBALS['argv'][0]} " . self::getURL();
      # see http://snipplr.com/view.php?codeview&id=2734
      $sp = strtolower($_SERVER['SERVER_PROTOCOL']);
      $protocol = substr($sp, 0, strpos($sp, '/')) . (self::$https ? 's' : '');
      $port = ((!self::$https && $_SERVER['SERVER_PORT'] == 80) || (self::$https && $_SERVER['SERVER_PORT'] == 443))
            ? '' : ":{$_SERVER['SERVER_PORT']}";
      return "$protocol://{$_SERVER['SERVER_NAME']}$port" .
         ($includePath ? $_SERVER['REQUEST_URI'] : '');
   }

   /**
    * Gets the maximum number of bytes that can be uploaded. This is the minimum
    * value of php.ini's upload_max_filesize, post_max_size and memory_limit
    * @return int
    */
   public static final function getMaxUploadSize() {
      $upload_max_filesize = Config::iniGet('upload_max_filesize');
      $post_max_size = Config::iniGet('post_max_size');
      $memory_limit = Config::iniGet('memory_limit');
      if($upload_max_filesize === '' || $upload_max_filesize === -1)
         $upload_max_filesize = PHP_INT_MAX;
      if($post_max_size === '' || $post_max_size === -1)
         $post_max_size = PHP_INT_MAX;
      if($memory_limit === '' || $memory_limit === -1)
         $memory_limit = PHP_INT_MAX;
      return min((int)$upload_max_filesize, (int)$post_max_size,
         (int)$memory_limit);
   }

}
