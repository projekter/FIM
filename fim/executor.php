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

namespace fim;

if(@FrameworkPath === 'FrameworkPath')
   die('Please do not call this file directly.');

/**
 * This class provides all services that are required to process the information
 * of a request and display its response to the user.
 */
abstract class Executor {

   private static $executedModules = false;
   private static $executedPath = null;

   private final function __construct() {

   }

   private static $executeCall = false;

   /**
    * Runs the framework by using the current url
    * @return
    */
   public static final function execute() {
      # First we have to determine the routing which says where to look for data
      # Normalization should not be needed in most cases, but requests from
      # broken browsers shall not be able to break our subdomain structure.
      $url = \Request::getURL();
      $trailingSlash = substr($url, -1) === '/';
      $url = \Router::normalizeFilesystem("/$url");
      $params = \Request::getParameterString();
      $chdir = new chdirHelper(CodeDir);
      if(($path = \Router::mapURLToPath(CurrentSubdomain . $url . $params,
            $_GET, $unused, $failureAt)) === false) {
         self::$executedPath = ['path' => $path, 'parameters' => $_GET];
         self::error(404, isset($failureAt) ? substr($failureAt, 2) : '.');
         return;
      }
      self::$executedPath = ['path' => $path, 'parameters' => $_GET];
      $path = substr($path, 2);
      try {
         $ioPath = \fileUtils::encodeFilename($path);
      }catch(\UnexpectedValueException $e) {
         self::error(404, $path);
         return;
      }
      if(is_dir($ioPath)) {
         if(!$trailingSlash) {
            \Response::set('Location', substr(BaseDir, 0, -1) . "$url/$params");
            return;
         }
      }elseif($trailingSlash) {
         \Response::set('Location', BaseDir . substr($url, 1, -1) . $params);
         return;
      }
      self::$executeCall = true;
      self::handlePath($ioPath, true, true);
   }

   /**
    * Checks a given path for its existence and calls the correct handler
    * functions.
    * @param string $path
    * @param boolean $outside Defines whether the url was typed by the user, so
    *    access to fim. files will map to their respective bypass files and
    *    modules will be performed instead of files if applicable
    * @param boolean $checkRules Allows to disable any checking for rules. Note
    *    that the rule's output will not be ignored but it will not even be
    *    calculated!
    */
   public static final function handlePath($path, $outside, $checkRules) {
      \FIMErrorException::$last = null; # framework-defined error
      if(!self::$executeCall) {
         try {
            $fimPath = \fileUtils::encodeFilename(\Router::normalizeFIM($path));
         }catch(\UnexpectedValueException $e) {
            self::error(404, $path);
            return;
         }
         $path = substr($fimPath, 2);
         $chdir = new chdirHelper(CodeDir);
      }else{
         self::$executeCall = false;
         $fimPath = "//$path";
      }
      if(is_dir($path)) {
         $filename = '';
         $dir = $path;
      }else{
         $filename = basename($path);
         if(!is_dir($dir = dirname($path))) {
            self::error(404, $path);
            return;
         }
      }
      $fimDir = "//$dir";
      # We will use is_file instead of fileUtils::isReadable, as it is faster.
      # is_file will only be wrong for files larger than 4 GB. We will assume
      # that one single file with _source code_ can never grow as big as this.
      if(($outside && is_file("$dir/fim.module.php")) || #
         (!$outside && ($filename === '' || ($filename === 'fim.module.php')) && is_file("$dir/fim.module.php"))) {
         if($outside && $filename !== '') {
            self::error(404, $dir);
            return;
         }
         if($checkRules)
            if(!\Rules::check($fimDir, \Rules::CHECK_EXISTENCE)) {
               self::error(404, $dir);
               return;
            }elseif(!\Rules::check($fimDir, \Rules::CHECK_READING)) {
               self::error(401, $dir);
               return;
            }
         self::executeModule('\\' . str_replace('/', '\\', $dir) . '\\Module');
      }else{
         if($filename === '') {
            if(is_file("$dir/index.html")) # HTML is source code as well
               $filename = 'index.html';
            elseif(is_file("$dir/index.htm"))
               $filename = 'index.htm';
         }elseif(!\fileUtils::isReadable($path)) { # But here, we don't know the
            # file type
            self::error(404, $path);
            return;
         }
         if($checkRules)
            if(!\Rules::check($fimPath, \Rules::CHECK_EXISTENCE)) {
               self::error(404, $path);
               return;
            }elseif($checkRules && !\Rules::check($fimPath,
                  \Rules::CHECK_READING | #
                  (($filename === '') ? \Rules::CHECK_LISTING : 0))) {
               self::error(401, $path);
               return;
            }
         if($filename === '')
            self::listDirectory($fimDir);
         else{
            if(!$checkRules || !\Rules::checkFilterExistence($fimPath))
               if(\Config::get('production'))
                  \Response::cache(time() + 2592000, false);# One month
               else
                  \Response::cache(time() + 604800, true);# One week
            self::passFile($fimPath);
         }
      }
   }

   /**
    * Executes the code of a module, given the full name of the module class
    * @param string $className
    */
   private static final function executeModule($className) {
      self::$executedModules = true;
      ob_start();
      $module = new $className();
      $path = substr($module->getModulePath(), 2);
      try {
         $chdir = new chdirHelper($path);
         \Database::setActiveConnection('.');
         if(!method_exists($module, 'execute')) {
            chdir(CodeDir);
            self::error(404, $path);
         }else
            Autoboxing::callMethod($module, 'execute');
      }catch(\ForwardException $e) {
         # not an exception
      }catch(\FIMErrorException $e) {
         chdir(CodeDir);
         self::error($e->getCode(), $path, $e->details);
      }catch(\Exception $e) {
         chdir(CodeDir);
         self::exception($path, $e);
      }
      \Response::$responseText .= ob_get_clean();
   }

   /**
    * Returns whether at least one single module was executed
    * @return boolean
    */
   public static final function hasExecutedModules() {
      return self::$executedModules;
   }

   /**
    * Returns the path that was executed by the current URL
    * @return array ['path' => string, 'parameters' => array]
    */
   public static final function getExecutedPath() {
      return self::$executedPath;
   }

   /**
    * Lists all the accessible content of a certain directory in the fim style
    * @param string $path Paths outside of ResourceDir/ are not allowed.
    */
   public static final function listDirectory($path) {
      $path = \Router::normalizeFIM($path, true);
      $chdir = new chdirHelper(CodeDir);
      if(empty($path) || $path[0] !== ResourceDir) {
         self::error(404, implode('/', $path));
         return;
      }
      $path = implode('/', $path);
      $it = new \fimDirectoryIterator($path);
      $intLanguage = \I18N::getInternalLanguage();
      $language = \I18N::getLocale();
      $resultFile = $resultDir = [];
      $details = \Config::equals('directoryListing',
            \Config::DIRECTORY_LISTING_DETAIL);
      if(CLI && $details)
         $maxLength = [0, 0, 0, 0, 0];
      $strpos = (OS === 'Windows') ? 'stripos' : 'strpos';
      $ownURL = \Router::mapPathToURL("//$path");
      $ownURLLen = strlen($ownURL);
      foreach($it as $fileInfo) {
         $fn = $fileInfo->getFilename();
         if($fn === '..' && $path === ResourceDir)
            continue;
         if($fn === '.') {
            $displayName = $url = '.';
            $class = 'folder';
         }else{
            if($strpos($fn, 'fim.') === 0 && $strpos($fn, 'fim.bypass.') !== 0)
               continue;
            $fileName = "//$path/$fn";
            if($fn !== '..' && !\Rules::check($fileName,
                  \Rules::CHECK_EXISTENCE | \Rules::CHECK_LISTING))
               continue;
            if(($url = \Router::mapPathToURL($fileName)) === false)
               continue;
            if(!CLI) {
               $url = htmlspecialchars($url, ENT_QUOTES, 'utf-8');
               if(strpos($url, $ownURL) === 0)
                  $url = substr($url, $ownURLLen);
            }
            $displayName = $fn === '..' ? '..' : basename($url);
            $class = $fileInfo->isDir() ? 'folder' : 'file';
         }
         if($details)
            if(CLI) {
               $str = [$displayName, $language->formatSize($fileInfo->getSize()),
                  $language->formatDateTime($fileInfo->getMTime()),
                  $language->formatDateTime($fileInfo->getCTime()),
                  $language->formatDateTime($fileInfo->getATime())];
               foreach($maxLength as $idx => &$len)
                  if(($newLen = strlen($str[$idx])) > $len)
                     $len = $newLen;
            }else
               $str = "\r\n<tr><th class=\"$class\"><a href=\"$url\">$displayName</a></th>" .
                  '<td>' . $language->formatSize($fileInfo->getSize()) . '</td>' .
                  '<td>' . $language->formatDateTime($fileInfo->getMTime()) . '</td>' .
                  '<td>' . $language->formatDateTime($fileInfo->getCTime()) . '</td>' .
                  '<td>' . $language->formatDateTime($fileInfo->getATime()) . '</td></tr>';
         elseif(CLI)
            $str = $fn;
         else
            $str = "\r\n<li class=\"$class\"><a href=\"$url\">$displayName</a></li>";
         if($class === 'folder')
            $resultDir[$fn] = $str;
         else
            $resultFile[$fn] = $str;
      }
      if(CLI) {
         $result = $intLanguage->get(['executor', 'directoryListing', 'title']);
         if($details) {
            $result .= "\r\n\r\n";
            $captions = [$intLanguage->get(['executor', 'directoryListing', 'filename']),
               $intLanguage->get(['executor', 'directoryListing', 'size']),
               $intLanguage->get(['executor', 'directoryListing', 'mtime']),
               $intLanguage->get(['executor', 'directoryListing', OS === 'Windows'
                        ? 'creationTime' : 'ctime']),
               $intLanguage->get(['executor', 'directoryListing', 'atime'])];
            foreach($captions as $idx => $txt) {
               if(($newLen = strlen($txt)) > $maxLength[$idx])
                  $maxLength[$idx] = $newLen;
               $result .= $txt . str_repeat(' ',
                     ($maxLength[$idx] - $newLen + 3));
            }
         }
         if(!empty($resultDir)) {
            $str = $intLanguage->get(['executor', 'directoryListing', 'directories']);
            $result .= "\r\n\r\n$str\r\n" . str_repeat('=', strlen($str));
            if($details)
               foreach($resultDir as $dir) {
                  $result .= "\r\n";
                  foreach($dir as $idx => $element)
                     $result .= $element . str_repeat(' ',
                           ($maxLength[$idx] - strlen($element) + 3));
               }#
            else
               foreach($resultDir as $dir)
                  $result .= "\r\n$dir";
         }
         if(!empty($resultFile)) {
            $str = $intLanguage->get(['executor', 'directoryListing', 'files']);
            $result .= "\r\n\r\n$str\r\n" . str_repeat('=', strlen($str));
            if($details)
               foreach($resultFile as $dir) {
                  $result .= "\r\n";
                  foreach($dir as $idx => $element)
                     $result .= $element . str_repeat(' ',
                           ($maxLength[$idx] - strlen($element) + 3));
               }#
            else
               foreach($resultFile as $dir)
                  $result .= "\r\n$dir";
         }elseif(empty($resultDir))
            $result .= "\r\n\r\n" . $intLanguage->get(['executor', 'directoryListing',
                  'empty']);
         \Response::$responseText = $result;
      }else{
         header('Content-Type: text/html');
         $result = implode('', $resultDir) . implode('', $resultFile);
         if($result === '')
            $result = '<tr><td colspan="5">' . $intLanguage->get(['executor', 'directoryListing',
                  'empty']) . '</td></tr>';
         if($details)
            $result = '<table><thead><tr><th>' .
               htmlspecialchars($intLanguage->get(['executor', 'directoryListing',
                     'filename'])) . '</th><td>' .
               htmlspecialchars($intLanguage->get(['executor', 'directoryListing',
                     'size'])) . '</td><td>' .
               htmlspecialchars($intLanguage->get(['executor', 'directoryListing',
                     'mtime'])) . '</td><td>' .
               htmlspecialchars($intLanguage->get(['executor', 'directoryListing',
                     OS === 'Windows' ? 'creationTime' : 'ctime'])) . '</td><td>' .
               htmlspecialchars($intLanguage->get(['executor', 'directoryListing',
                     'atime'])) .
               "</td></thead><tbody>$result</tbody></table>";
         else
            $result = "<ul>$result</ul>";
         $title = htmlspecialchars($intLanguage->get(['executor', 'directoryListing',
               'title']));
         \Response::$responseText .= // <editor-fold desc="Directory Listing code" defaultstate="collapsed">
            <<<listing
<!DOCTYPE html>
<html>
   <head>
      <title>$title</title>
      <style type="text/css">
         html, body {
            color: #000;
            background-color: #FFF;
            font-family: 'Palatino Linotype','Book Antiqua',Palatino,'Trebuchet MS',Verdana,Geneva,sans-serif;
            font-size: 12pt;
         }
         a {
            color: #00F;
            text-decoration: none;
         }
         a:hover, a:focus {
            text-decoration: underline;
         }
         a:visited {
            color: #8B008B;
         }
         table {
            border-collapse: collapse;
         }
         td, th {
            margin: 0;
            padding: 3px;
         }
         th {
            border-right: 3px double #000;
            font-weight: normal;
            text-align: left;
         }
         thead th {
            font-weight: bold;
         }
         tbody tr td, tbody tr th {
            border-top: 1px solid #000;
         }
         td {
            border-right: 1px solid #000;
         }
         td:last-child {
            border-right-style: none;
         }
         tbody tr:hover td:not([colspan="5"]) {
            background-color: #FFF8DC;
         }
         tbody tr:hover th {
            background-color: #FFEBCD;
         }
         thead tr td, thead tr th {
            background-color: #E6E6FA;
         }
         li.file {
            list-style-image: url("data:image/gif;base64,R0lGODlhCgANAMIFABMTExQUFBUVFf39/f7+/v///////////yH5BAEKAAcALAAAAAAKAA0AAAMnCKrXDaMUAdyBEpT64o4c9hHb5VFKoHhSu7bwC2fiLNsQoe/b4i8JADs=");
         }
         th.file {
            background: url("data:image/gif;base64,R0lGODlhCgANAMIFABMTExQUFBUVFf39/f7+/v///////////yH5BAEKAAcALAAAAAAKAA0AAAMnCKrXDaMUAdyBEpT64o4c9hHb5VFKoHhSu7bwC2fiLNsQoe/b4i8JADs=") no-repeat scroll 2px center;
            padding-left: 20px;
         }
         li.folder {
            list-style-image: url("data:image/gif;base64,R0lGODlhCwAJAMIFABMTExQUFBUVFf39/f7+/v///////////yH5BAEKAAcALAAAAAALAAkAAAMYCKrXvss1EGqNB4zCO5iEAI1kWWbmlzYJADs=");
         }
         th.folder {
            background: url("data:image/gif;base64,R0lGODlhCwAJAMIFABMTExQUFBUVFf39/f7+/v///////////yH5BAEKAAcALAAAAAALAAkAAAMYCKrXvss1EGqNB4zCO5iEAI1kWWbmlzYJADs=") no-repeat scroll 2px center;
            padding-left: 20px;
         }
         h1 {
            margin: 0;
         }
      </style>
   </head>
   <body>
      <h1>$title</h1>
      $result
   </body>
</html>
listing;
         //</editor-fold>
         \Response::cache(time() + 30, true);
      }
   }

   private static function prepareFileHeaders($fileName) {
      $name = basename($fileName);
      $name = substr($name, 0, 11) === 'fim.bypass.' ? 'fim.' . substr($name, 11)
            : $name;
      $mime = \Response::getMIMEType($name);
      if((substr($mime, 0, 5) === 'text/') && !\Response::has('Content-Type')) {
         if(($handle = @fopen($fileName, 'rb')) !== false) {
            $mime .= '; charset=';
            $bom = (string)@fread($handle, 4);
            if(!\Config::equals('x-sendfile', \Config::XSENDFILE_NONE)) {
               @fclose($handle);
               unset($handle);
            }
            # Thanks to http://us.php.net/manual/en/function.mb-detect-encoding.php#91051 for the BOM definitions
            if(substr($bom, 0, 3) === "\xEF\xBB\xBF")
               $mime .= 'UTF-8';
            elseif(($first4 = substr($bom, 0, 4)) === "\x00\x00\xFE\xFF")
               $mime .= 'UTF-32BE';
            elseif($first4 === "\xFF\xFE\x00\x00")
               $mime .= 'UTF-32LE';
            elseif(($first2 = substr($bom, 0, 2)) === "\xFE\xFF")
               $mime .= 'UTF-16BE';
            elseif($first2 === "\xFF\xFE")
               $mime .= 'UTF-16LE';
            else
               $mime .= 'ISO-8859-1';
         }
      }elseif(\Config::equals('x-sendfile', \Config::XSENDFILE_NONE))
         $handle = @fopen($fileName, 'rb');
      \Response::set('Content-Type', $mime, false);
      if((substr($mime, 0, 5) === 'text/') || (substr($mime, 0, 6) === 'image/'))
         \Response::set('Content-Disposition', "inline; filename=$name", false);
      else
         \Response::set('Content-Disposition', "attachment; filename=$name",
            false);
      if(\Config::equals('x-sendfile', \Config::XSENDFILE_APACHE,
            \Config::XSENDFILE_CHEROKEE, \Config::XSENDFILE_NGINX))
      # Apache/Cherokee/nginx will do the rest
         return true;
      $chg = @filemtime($fileName);
      \Response::set('Last-Modified', gmdate('D, d M Y H:i:s \\G\\M\\T', $chg),
         false);
      if((!isset($_SERVER['HTTP_CACHE_CONTROL'])) || ($_SERVER['HTTP_CACHE_CONTROL'] !== 'no-cache'))
         if(!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $date = explode(' ', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
            $his = explode(':', $date[4]);
            $months = ['Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4,
               'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8, 'Sep' => 9,
               'Oct' => 10, 'Nov' => 11, 'Dec' => 12]; // Do not localize.
            $date = gmmktime($his[0], $his[1], $his[2], $months[$date[2]],
               $date[1], $date[3]);
            if($date >= $chg)
               \Response::$responseCode = 304;
         }
      return isset($handle) ? $handle : true;
   }

   /**
    * Serves a file to the client without parsing it. Supports ranges and
    * acceleration with X-Sendfile/X-Accel-Redirect if available.
    * @param string $fileName Paths outside of ResourceDir/ are not allowed.
    */
   public static final function passFile($fileName) {
      static $buffer = 8192;
      \Response::set('Accept-Ranges', 'bytes');
      $fileName = \Router::normalizeFIM($fileName, true);
      $chdir = new chdirHelper(CodeDir);
      if(empty($fileName) || $fileName[0] !== ResourceDir) {
         self::error(404, implode('/', $fileName));
         return;
      }
      $fileName = implode('/', $fileName);
      if(!\fileUtils::isReadable($fileName)) {
         self::error(404, $fileName);
         return;
      }
      if(\Config::equals('x-sendfile', \Config::XSENDFILE_NGINX)) {
         # nginx XAccelRedirect does quite everything: supporting ranges,
         # settings headers and caching as well. The only thing missing is
         # Content-Type and Content-Disposition, which is done manually.
         # Sadly, rewriting the urls to index.php will also have effect on
         # XAccelRedirect. This requires us to reserve an internal directory
         # /fim.InternalGetContent/ which will serve all files without
         # rewriting.
         self::prepareFileHeaders($fileName);
         \Response::set('X-Accel-Redirect', "/fim.InternalGetContent/$fileName");
         return;
      }
      if(\Config::equals('x-sendfile', \Config::XSENDFILE_APACHE,
            \Config::XSENDFILE_CHEROKEE)) {
         # Apache/Cherokee XSendfile module does quite everything: supporting
         # ranges, settings headers and caching as well. The only thing missing
         # is Content-Type and Content-Disposition, which is done manually
         self::prepareFileHeaders($fileName);
         \Response::set('X-Sendfile', CodeDir . $fileName);
         return;
      }
      if(\Request::isGet() && (preg_match('/^bytes=(?:(\d++)-(\d++)?+|-(\d++))/',
            @$_SERVER['HTTP_RANGE'], $matches) === 1)) {
         # Ranges were requested
         if(($length = \fileUtils::size($fileName)) === false) {
            self::error(500, $fileName); # File exists but filesize fails...
            return;
         }
         # We now have the problem that our filesize might get very large,
         # bigger than PHP_INT_MAX (at least on 32 bit systems or Windows). So
         # first check whether we have native integer support for the required
         # size. If this is not the case, the file size will be converted to
         # double which might result in a loss of precision (for an 8 Exibytes
         # file (2^63), this would be as small as one Kibibyte). This is
         # acceptable when dealing with ranges as we can specify or output range
         # ourselves and don't have to be absolutely precise.
         if((double)$length > PHP_INT_MAX) {
            $length = (double)$length;
            if(!empty($matches[1]))
               $matches[1] = (double)$matches[1];
            if(!empty($matches[2]))
               $matches[2] = (double)$matches[2];
            if(!empty($matches[3]))
               $matches[3] = (double)$matches[3];
         }else{
            $length = (int)$length;
            if(!empty($matches[1]))
               $matches[1] = (int)$matches[1];
            if(!empty($matches[2]))
               $matches[2] = (int)$matches[2];
            if(!empty($matches[3]))
               $matches[3] = (int)$matches[3];
         }
         if(empty($matches[1])) {
            if($matches[3] < 1) {
               # We could check for another range which might be valid, but this
               # is not worth the effort.
               \Response::$responseCode = 416;
               \Response::set('Content-Range', "bytes */$length");
               return;
            }
            $startPos = max(0, $length - $matches[3]);
            $endPos = $length - 1;
         }else{
            $startPos = $matches[1];
            if(!empty($matches[2]))
               $endPos = min($matches[2], $length - 1);
            else
               $endPos = $length - 1;
            if(($startPos >= $length) || ($endPos < $startPos)) {
               \Response::$responseCode = 416;
               \Response::set('Content-Range', "bytes */$length");
               return;
            }
         }
         if(($str = self::prepareFileHeaders($fileName)) === false)
            return;
         if(\Response::$responseCode === 304) { # Already in cache
            if($str !== false)
               @fclose($str);
            return;
         }
         \Response::$responseCode = 206;
         \Response::set('Content-Range', "bytes $startPos-$endPos/$length");
         # We might come into some trouble here: We cannot really make sure that
         # this calculation will not be inexact and thus give a wrong length.
         # According to the PHP manual, the maxium float error is 1.11*10^-16.
         # In order to get an error that is at least one, we thus need at least
         # an ending position that is greater than 9009009009009009. As this
         # calculation is a bit optimistic (there are three elemetary operations
         # involved), we will say that this operation is safe when $endPos is
         # smaller than 3003003003003003. If it is greater, we will simply omit
         # Content-Length. This is not nice and not standard compliant, but the
         # browsers shall life with it. The complete size is given in
         # Content-Range, so stop complaining...
         if($endPos <= 3003003003003003)
            \Response::set('Content-Length', $endPos - $startPos + 1);
         # It is impossible to return more than one continuous range with php...
         # But try to speed up the process by not using php
         if(\Config::equals('x-sendfile', \Config::XSENDFILE_LIGHTTPD))
            \Response::set('X-Sendfile2',
               str_replace(',', '%2c', rawurlencode(CodeDir . $fileName)) . " $startPos-$endPos");
         else{
            # No help by webserver. Seems we have to do it ourselves
            # All headers are set. Send them, then start the output.
            \Response::doSend();
            # Sending the file may take some time
            set_time_limit(0);
            # And now the data in chunks. A valid stream handle was given
            # back by prepareFileHeaders
            if(@fseek($str, $startPos) === -1) {
               self::error(500, $fileName);
               return;
            }
            for(; $startPos <= $endPos && !@feof($str); $startPos += $buffer)
               echo @fread($str, $buffer);
            @fclose($str);
         }
      }else{
         if(($str = self::prepareFileHeaders($fileName)) === false)
            return;
         if(\Response::$responseCode === 304) {
            if($str !== false)
               @fclose($str);
            return;
         }
         \Response::set('Content-Length', \fileUtils::size($fileName));
         # No range required. Try to speed up by using webserver functions
         if(\Config::equals('x-sendfile', \Config::XSENDFILE_LIGHTTPD))
            \Response::set('X-Sendfile', $fileName);
         else{
            # No help by webserver. Seems we have to do it ourselves
            # All headers are set. Send them, then start the output.
            \Response::doSend();
            # Sending the file may take some time
            set_time_limit(0);
            # And now the data in chunks. A valid stream handle was given
            # back by prepareFileHeaders, just ensure it's at the start
            if(@fseek($str, 0) === -1) {
               self::error(500, $fileName);
               return;
            }
            while(!@feof($str))
               echo @fread($str, $buffer);
            @fclose($str);
         }
      }
   }

   private static function error($errno, $fileName, array $details = []) {
      $handleSpecific = "handleError$errno";
      if(!self::lookFor($fileName,
            function($className) use ($errno, $handleSpecific, $details) {
            if(method_exists($className, $handleSpecific)) {
               $module = new $className();
               # Speed up call_user_func_array with this monster
               if(empty($details))
                  $ret = $module->$handleSpecific();
               elseif(isset($details[2])) {
                  if(isset($details[4])) {
                     if(isset($details[6])) {
                        if(isset($details[8]))
                           $ret = call_user_func_array([$module, $handleSpecific],
                              $details);
                        elseif(isset($details[7]))
                           $ret = $module->$handleSpecific($details[0],
                              $details[1], $details[2], $details[3],
                              $details[4], $details[5], $details[6], $details[7]);
                        else
                           $ret = $module->$handleSpecific($details[0],
                              $details[1], $details[2], $details[3],
                              $details[4], $details[5], $details[6]);
                     }elseif(isset($details[5]))
                        $ret = $module->$handleSpecific($details[0],
                           $details[1], $details[2], $details[3], $details[4],
                           $details[5]);
                     else
                        $ret = $module->$handleSpecific($details[0],
                           $details[1], $details[2], $details[3], $details[4]);
                  }elseif(isset($details[3]))
                     $ret = $module->$handleSpecific($details[0], $details[1],
                        $details[2], $details[3]);
                  else
                     $ret = $module->$handleSpecific($details[0], $details[1],
                        $details[2]);
               }elseif(isset($details[1]))
                  $ret = $module->$handleSpecific($details[0], $details[1]);
               else
                  $ret = $module->$handleSpecific($details[0]);
               # End speed up
               if($ret === \Module::PROPAGATE_UP)
                  return false;
               elseif($ret !== \Module::PROPAGATE) {
                  if(is_int($ret))
                     \Response::$responseCode = $ret;
                  return true;
               }
            }
            if(method_exists($className, 'handleError')) {
               if(!isset($module))
                  $module = new $className();
               # Speed up call_user_func_array with this monster
               if(empty($details))
                  $ret = $module->handleError($errno);
               elseif(isset($details[2])) {
                  if(isset($details[4])) {
                     if(isset($details[6])) {
                        if(isset($details[8])) {
                           array_unshift($details, $errno);
                           $ret = call_user_func_array([$module, 'handleError'],
                              $details);
                        }elseif(isset($details[7]))
                           $ret = $module->handleError($errno, $details[0],
                              $details[1], $details[2], $details[3],
                              $details[4], $details[5], $details[6], $details[7]);
                        else
                           $ret = $module->handleError($errno, $details[0],
                              $details[1], $details[2], $details[3],
                              $details[4], $details[5], $details[6]);
                     }elseif(isset($details[5]))
                        $ret = $module->handleError($errno, $details[0],
                           $details[1], $details[2], $details[3], $details[4],
                           $details[5]);
                     else
                        $ret = $module->handleError($errno, $details[0],
                           $details[1], $details[2], $details[3], $details[4]);
                  }elseif(isset($details[3]))
                     $ret = $module->handleError($errno, $details[0],
                        $details[1], $details[2], $details[3]);
                  else
                     $ret = $module->handleError($errno, $details[0],
                        $details[1], $details[2]);
               }elseif(isset($details[1]))
                  $ret = $module->handleError($errno, $details[0], $details[1]);
               else
                  $ret = $module->handleError($errno, $details[0]);
               # End speed up
               if($ret !== \Module::PROPAGATE && $ret !== \Module::PROPAGATE_UP) {
                  if($ret === null)
                     $ret = $errno;
                  if(is_int($ret) && \Response::translateHTTPCode($ret) !== null)
                     \Response::$responseCode = $ret;
                  return true;
               }else
                  return false;
            }else
               return false;
         })) {
         # No handler found; change the status code and echo the error or a
         # generic phrase if in production.
         \Response::$responseCode = (\Response::translateHTTPCode($errno) !== null)
               ? $errno : 500;
         \Response::set('Content-Type', 'text/plain');
         $language = \I18N::getInternalLanguage();
         $logStr = $language->get(['executor', 'error', 'log'],
            [$errno, \Request::getFullURL(),
            empty($details) ? 'none' : implode(', ', $details),
            isset(\FIMErrorException::$last) ?
               \FIMErrorException::$last->getTraceAsString() : 'none']);
         if(ob_get_level() === 0)
            if(!\Config::get('production'))
               \Response::$responseText .= $language->get(['executor', 'error', 'production'],
                  [$errno]);
            else
               \Response::$responseText .= $logStr;
         elseif(!\Config::get('production')) {
            echo $language->get(['executor', 'error', 'production'], [$errno]);
            \Log::reportError($logStr, true);
         }else
            echo $logStr;
      }
   }

   private static function exception($fileName, \Exception $e) {
      if(!self::lookFor($fileName,
            function($className) use($e) {
            if(method_exists($className, 'handleException')) {
               $module = new $className;
               $ret = $module->handleException($e);
               return $ret !== \Module::PROPAGATE && $ret !== \Module::PROPAGATE_UP;
            }else
               return false;
         })) # No handler found; throw the exception and let the logger do the job
         throw $e;
   }

   private static function lookFor($fileName, callable $callback) {
      # When this function is called, it is assured that cwd === CodeDir
      if(\fileUtils::isReadable($fileName))
         $fileName = dirname($fileName);
      elseif(!is_dir($fileName)) {
         $fileNameArray = \Router::normalizeFilesystem("/$fileName", true);
         $fileName = array_shift($fileNameArray);
         if($fileName !== ResourceDir)
            return false;
         foreach($fileNameArray as $cur) {
            $newFileName = "$fileName/$cur";
            if(is_dir($newFileName))
               $fileName = $newFileName;
            else
               break;
         }
      }else
         $fileName = substr(\Router::normalizeFilesystem("/$fileName"), 1);
      # Now we know that $fileName points at a valid directory. Let's check
      # whether we find modules at any level of hierarchy which implement any
      # of the functions
      for(; $fileName !== '' && $fileName !== '.' && $fileName !== '/' && $fileName !== '\\'; $fileName =
         dirname($fileName)) {
         if(is_file("$fileName/fim.module.php")) {
            $className = '\\' . str_replace('/', '\\', $fileName) . '\\Module';
            try {
               chdir($fileName);
               if($callback($className))
                  return true;
               chdir(CodeDir);
            }catch(\ForwardException $e) {
               return true;
            }
         }
      }
      return false;
   }

}
