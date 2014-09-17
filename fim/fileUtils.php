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
 * This class enhances the features of PHP's file handling functions by
 * providing some functions that go beyond the native size of integer and the
 * native ASCII character set
 */
abstract class fileUtils {

   private final function __construct() {

   }

   /**
    * Returns information about the given file or directory by calling the
    * helper application delivered with FIM. This is done either with COM or
    * the exec command, if accessable. This function is only called on Windows.
    * @param string $fileName
    * @boolean false|null|array False if the file did not exist; null if the
    *    helper application was not available
    */
   private static function getFileDetails($fileName) {
      static $hasExec = null;
      if(!isset($hasExec))
         $hasExec = function_exists('exec');
      $commandLine = FrameworkPath . 'FileHelper.exe ' . base64_encode($fileName) .
         ' ' . base64_encode(getcwd());
      if($hasExec)
         exec($commandLine, $return);
      else{
         static $com = null;
         if(!isset($com))
            $com = class_exists('COM') ? new COM('WScript.Shell') : false;
         # Scripting.FileSystemObject would be an alternative, but it would then
         # not be possible to resolve symlinks. As we want constistent
         # behaviour, regardless which background implementation is relied on,
         # we will always call our helper.
         if($com === false)
            return null;
         $return = explode("\r\n",
            trim((string)$com->Exec($commandLine)->StdOut->ReadAll()));
      }
      if(!isset($return[8]))
         if(isset($return[0]) && $return[0] === 'notfound')
            return false;
         else
            throw new FileUtilsException(I18N::getInternalLanguage()->get('fileutils.corruptHelper'));
      $sub = array_slice($return, 9);
      foreach($sub as &$value)
         $value = base64_decode($value);
      return [
         'fileName' => base64_decode($return[0]),
         'resolvedFileName' => base64_decode($return[1]),
         'creationTime' => (int)$return[2],
         'accessTime' => (int)$return[3],
         'modificationTime' => (int)$return[4],
         'executable' => (bool)$return[5],
         'size' => (string)$return[6],
         'readOnly' => (bool)$return[7],
         'type' => (string)$return[8], # 'file' or 'dir'
         'sub' => $sub,
      ];
   }

   /**
    * Calculates the size of file even for files that are greater than two
    * or four GB correctly. Symbolic links will be resolved.
    * @param int $fileName
    * @return string|bool False only if none of the extensions cURL or COM is
    *    available, exec is disabled and the file is inaccessible.
    */
   public static function size($fileName) {
      $fileName = Router::normalize($fileName, false, false);
      # Implementation destilled from
      # https://github.com/jkuchar/BigFileTools/blob/master/class/BigFileTools.php
      # Original copyright:
      /**
       * Copyright (c) 2013, Jan Kuchar
       * All rights reserved.
       *
       * Redistribution and use in source and binary forms, with or without
       * modification, are permitted provided that the following conditions are
       * met:
       * - Redistributions of source code must retain the above copyright
       *   notice, this list of conditions and the following disclaimer.
       * - Redistributions in binary form must reproduce the above copyright
       *   notice, this list of conditions and the following disclaimer in the
       *   documentation and/or other materials provided with the distribution.
       * - Neither the name of the Jan Kuchar nor the names of its contributors
       *   may be used to endorse or promote products derived from this software
       *   without specific prior written permission.
       *
       * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
       * IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
       * TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
       * PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL Jan Kuchar BE
       * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
       * CONSEQUENTIAL DAMAGES, INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
       * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
       * BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
       * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
       * OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN
       * IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
       */
      static $hasCURL = null;
      if(!isset($hasCURL))
         $hasCURL = function_exists('curl_init');
      if($hasCURL && ($cURL = curl_init('file:///' . rawurlencode($fileName))) !== false) {
         if(curl_setopt_array($cURL,
               [CURLOPT_NOBODY => true,
               CURLOPT_RETURNTRANSFER => true,
               CURLOPT_HEADER => true]))
            $data = curl_exec($cURL);
         curl_close($cURL);
         if(isset($data) && $data !== false && preg_match('/Content-Length: (\\d+)/',
               $data, $match))
            return (string)$match[1];
      }
      try {
         $codedName = self::encodeFilename($fileName);
      }catch(UnexpectedValueException $e) {
         # We cannot deal with native functions. However, we have not tried our
         # helper application yet.
         goto helper;
      }
      if(($handle = @fopen($codedName, 'rb')) !== false) {
         flock($handle, LOCK_SH);
         if(fseek($handle, 0, SEEK_END) === -1) {
            # This will only be executed if the file is at maximum 4294967294
            # large.
            $pos = ftell($handle);
            flock($handle, LOCK_UN);
            fclose($handle);
            if($pos >= 0) # Only for speed
               return (string)$pos;
            else
            # So now we know the size is greater than 2 GB but less than 4 GB
               return sprintf('%u', $pos);
         }
         flock($handle, LOCK_UN);
         fclose($handle);
      }
      helper:
      if(OS !== 'Windows') {
         # We are not on Windows, so we are happy to work with the operating
         # system's abilities. We will not have any problems such as CodePages
         # and can therefore make use of stat. However, we want to work on
         # symlink's targets, not on the links themselves, so we first have to
         # resolve them.
         while(($newFN = @readlink($fileName)) !== false && $newFN !== $fileName)
            $fileName = $newFN;
         static $hasExec = null;
         if(!isset($hasExec))
            $hasExec = function_exists('exec');
         if($hasExec) {
            $size = trim(exec('stat -Lc%s ' . escapeshellarg($fileName)));
            if($size && ctype_digit($size))
               return (string)$size;
         }
      }else{
         # So on Windows, we cannot resolve symbolic links in every occasion,
         # as we do not know whether they might point to a file that lies beyond
         # our current CodePage. We cannot access every file as well, even if
         # there is no junction. So we have to make use of our helper
         # application.
         if(($info = self::getFileDetails($fileName)) === false)
            return false;# We know for sure the file does not exist
         elseif($info !== null)
            return $info['size'];
      }
      # Now we are quite desperate. We could only read the file in chunks and
      # hope that at least a big int library is enabled. But this would be
      # terribly slow and is thus not implemented. The only thing left is to
      # call the native filesize - which could of course be wrong. We have to do
      # the assumption that every file is not bigger than 4 GB. This is better
      # than the usual limitation of 2 GB, but it will stil product invalid
      # results for files that exceed the 4 GB limit.
      # It's the webmaster's responsibility to add at least one of the reliable
      # functionalities used above, so give them a clue by logging this action.
      Log::reportError(I18N::getInternalLanguage()->get('fileUtils.size', true));
      if(($size = @filesize($codedName)) === false)
         return false;
      elseif($size >= 0)
         return (string)$size;
      else
         return sprintf('%u', $size);
   }

   /**
    * Checks whether a file exists and is readable even for files that are
    * greater than four GB.
    * @param string $fileName
    */
   public static function isReadable($fileName) {
      if(($handle = @fopen($fileName, 'r')) === false)
         return false;
      fclose($handle);
      return true;
   }

   /**
    * Checks whether a file or directory exists even for files that are greater
    * than four GB.
    * @param string $fileName
    */
   public static function fileExists($fileName) {
      if(@file_exists($fileName))
         return true;
      if(($handle = @fopen($fileName, 'r')) === false)
         return false;
      fclose($handle);
      return true;
   }

   # Important parts of the CodePage encoding are destilled and improved from
   # phplint. The original copyright of these parts is:
   /**
    * Copyright (c) 2005, Umberto Salsi, icosaedro.it di Umberto Salsi
    * All rights reserved.
    *
    * Redistribution and use in source and binary forms, with or without
    * modification, are permitted provided that the following conditions are
    * met:
    * - Redistributions of source code must retain the above copyright notice,
    *   this list of conditions and the following disclaimer
    * - Redistributions in binary form must reproduce the above copyright
    *   notice, this list of conditions and the following disclaimer in the
    *   documentation and/or other materials provided with the distribution.
    * - Neither the name of the icosaedro.it di Umberto Salsi nor the names of
    *   its contributors may be used to endorse or promote products derived from
    *   this software without specific prior written permission.
    * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
    * IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
    * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
    * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
    * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
    * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
    * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
    * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
    * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
    * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
    */

   private static $codepage, $reverseCodepage;

   /**
    * This function initializes the internal CodePage table. As some
    * CodePages are rather large, memory usage may increase heavily...
    */
   private static function initCodepage() {
      # We need to reset the CodePage to the system's default for several
      # reasons. First, reading its current value with setlocale(..., 0)
      # doesn't work (any more?) in newer PHP versions. Second, even if
      # we change our CodePage to a different value, Windows doesn't care
      # and will make use of the default one.
      $codepage = setlocale(LC_CTYPE, '');
      if($codepage === false) {
         self::$codepage = false;
         return;
      }
      $codepage = explode('.', $codepage, 2);
      if(!isset($codepage[1])) {
         self::$codepage = false;
         return;
      }
      $codepage = explode('@', $codepage[1], 2)[0];
      # Now we are ready to load our CodePage. We will first check the
      # current memory limit and extend it, if necessary. The limit value
      # is oriented at the memory size that PHP needs to hold two copies of
      # the largest CodePage (CP936 ~ 7MiB). We could check for the current
      # memory usage, but that would be an overkill, so let's assume 20 MiB
      # should be enough for FIM, including all loaded data (hopefully not
      # complete files...). We'll increase the limit by at least 7MiB
      $currentMemoryLimit = Config::ini_get('memory_limit');
      if($currentMemoryLimit != -1 && $currentMemoryLimit < 20971520)
         @ini_set('memory_limit', max(20971520, $currentMemoryLimit + 7340032));
      if((self::$codepage = @include (FrameworkPath . "codepages/$codepage.php")) !== false)
         self::$reverseCodepage = array_flip(self::$codepage);
      else
         self::$reverseCodepage = false;
      # We could have a bit more suited CodePage files which do not map integers
      # to integers but integers to strings (in both directions). This would
      # speed up the process of encoding. However, we would then need to have
      # two CodePage arrays that are not just flipped, but have a more complex
      # relationship. If we produced the reverse CodePage on-the-fly, this
      # would impact speed on loading the CodePage (and since we expect our
      # CodePages to be bigger than all strings that are to be transformed -
      # which is only a proper assumption for the large CodePages -, this would
      # impact speed more than converting while encoding). Even if we created
      # the CodePages files in the manner that they already comprise our
      # transformations, our memory would suffer, as strings need more RAM than
      # integers.
   }

   /**
    * This function tries to encode a Unicode filename in a representation
    * in the current CodePage. If the correct CodePage could not be found,
    * the filename is returned unaltered. If a character does not exist in
    * the current CodePage, an exception is thrown.<br />
    * If the current operating system is not Windows, the input string will be
    * given back.
    * @param string $utf8Filename
    * @param boolean $mask (default false) Set this to true if you wish to
    *    replace all illegal characters by a question mark instead of throwing
    *    an exception.
    * @return string
    * @throws UnexpectedValueException
    */
   public static function encodeFilename($utf8Filename, $mask = false) {
      if(OS !== 'Windows')
         return $utf8Filename;
      # If the filename is plain ASCII, we can return it without having to do
      # any of the conversions or CodePage stuff
      if(preg_match("/[\x80-\xff]/", $utf8Filename) === 0)
         return $utf8Filename;
      if(!isset(self::$codepage))
         self::initCodepage();
      if(($codepage = self::$codepage) === false)
         return $utf8Filename;
      # Now we have to transform our filename, which is supposed to be UTF-8
      # encoded, to a valid CodePage equivalent.
      $encodedPath = '';
      $len = strlen($utf8Filename);
      for($i = 0; $i < $len; ++$i) {
         $b1 = ord($utf8Filename[$i]);
         if(($b1 & 0x80) === 0)
            $val = $b1;
         elseif(($b1 & 0xE0) == 0xC0)
            $val = (($b1 & 0x1F) << 6) | (ord($utf8Filename[++$i]) & 0x3F);
         else{
            $b2 = ord($utf8Filename[++$i]);
            $val = (($b1 & 0x0F) << 12) | (($b2 & 0x3F) << 6) | (ord($utf8Filename[++$i]) & 0x3F);
         }
         if(isset($codepage[$val]))
            $encodedPath .= chr($codepage[$val]);
         elseif($mask)
            $encodedPath .= '?';
         else
         # Now we know for sure that our native PHP methods will fail,
         # we cannot even tell whether the file exists.
            throw new UnexpectedValueException("Filename beyond allowed character range: $utf8Filename");
      }
      return $encodedPath;
   }

   /**
    * This function decodes a filename that is encoded in the current
    * CodePage into a Unicode filename. If a character does not exist in
    * the current CodePage, an exception is thrown.<br />
    * If the current operating system is not Windows, the input string will be
    * given back.
    * @param string $codepageFilename
    * @return string
    * @throws UnexpectedValueException
    */
   public static function decodeFilename($codepageFilename) {
      if(OS !== 'Windows')
         return $codepageFilename;
      # If the filename is plain ASCII, we can return it without having to do
      # any of the conversions or CodePage stuff
      if(preg_match("/[\x80-\xff]/", $codepageFilename) === 0)
         return $codepageFilename;
      if(!isset(self::$codepage))
         self::initCodepage();
      if(($codepage = self::$reverseCodepage) === false)
         return $codepageFilename;
      $len = strlen($codepageFilename);
      $decodedPath = '';
      for($i = 0; $i < $len; ++$i) {
         $b = ord($codepageFilename[$i]);
         if(!isset($codepage[$b]) && ( ++$i < $len)) {
            $b = ($b << 4) | ord($codepageFilename[$i]);
            if(!isset($codepage[$b]))
               throw new UnexpectedValueException("Filename beyond allowed character range: $codepageFilename");
         }
         $code = $codepage[$b];
         if($code < 0 || $code > 65535)
            throw new OutOfRangeException($code);
         if($code < 128)
            $decodedPath .= chr($code);
         elseif($code < 2048)
            $decodedPath .= chr(0xC0 | ($code >> 6)) .
               chr(0x80 | ($code & 0x3F));
         else
            $decodedPath .= chr(0xE0 | ($code >> 12)) .
               chr(0x80 | ($code >> 6) & 0x3F) .
               chr(0x80 | ($code & 0x3F));
      }
      return $decodedPath;
   }

}

if(OS === 'Windows') {

   /**
    * This class is a replacement for DirectoryIterator which is Unicode-aware.
    */
   class fimDirectoryIterator extends SplFileInfo implements SeekableIterator {

      private $path, $codedPath, $data, $subItems;
      private static $getFileDetails, $innerCreation = false;

      /**
       * (PHP 5)<br/>
       * Constructs a new directory iterator from a path
       * @link http://php.net/manual/en/directoryiterator.construct.php
       * @param $path
       * @throws UnexpectedValueException when the file cannot be found or is
       *    not accessible with the means available to FIM
       */
      public function __construct($path) {
         # We need to get as much information on the file as possible.
         # Therefore, we can't do any work without using FIM's helper
         # application. This is of course not very nice if you traverse a huge
         # directory; but remember this limitation is only due to the Windows
         # operating system. Production webservers shall not run on Windows
         # either, so we do this only for developers who can then migrate
         # between the different OSes without having to worry about differing
         # behaviour.
         if(!isset(self::$getFileDetails))
            self::$getFileDetails = Closure::bind(function($fileName) {
                  return self::getFileDetails($fileName);
               }, null, 'fileUtils');
         $getDetails = self::$getFileDetails;
         $details = $getDetails($path);
         if($details === false)
            throw new UnexpectedValueException("File not found: $path");
         elseif($details !== null) {
            if($details['type'] === 'dir') {
               $perms = 040777;
               if(!self::$innerCreation)
                  $details['fileName'] .= '\\.';
               else{
                  $paths = explode('\\', str_replace('/', '\\', $path));
                  if(array_pop($paths) === '..') {
                     do
                        $add = array_pop($paths);while($add === '.');
                     $details['fileName'] .= "\\$add\\..";
                  }
               }
            }elseif($details['readOnly'])
               if($details['executable'])
                  $perms = 0100555;
               else
                  $perms = 0100444;
            elseif($details['executable'])
               $perms = 0100777;
            else
               $perms = 0100666;
            $this->data = ['atime' => $details['accessTime'], 'ctime' => $details['creationTime'],
               'mtime' => $details['modificationTime'], 'perms' => $perms,
               'size' => $details['size'], 'sub' => $details['sub'],
               'target' => $details['resolvedFileName'],
               'type' => $details['type'], 'readable' => true,
               'writable' => !$details['readOnly']];
            $this->path = $details['fileName'];
            return;
         }
         # So we cannot offer native Unicode support. We have one last chance:
         # The given path name could lie in our current CodePage. So we have to
         # find all invalid characters and convert them to their CodePage
         # counterparts.
         $this->codedPath = fileUtils::encodeFilename($path);
         # We now either have a pathname that is converted in such a way that
         # our native PHP functions can cope with it or we could not get any
         # CodePage to convert the filename. However, even without CodePage,
         # any ASCII filename would be recognized correctly, so this is not
         # necessarily an error. But we cannot differ between a file not found
         # and an existing, but inaccessible file.
         $sfi = new SplFileInfo($this->codedPath);
         # $sfi will still be valid, even if the file didn't exists...
         try {
            $this->data = ['atime' => $sfi->getATime(), 'ctime' => $sfi->getCTime(),
               'mtime' => $sfi->getMTime(), 'perms' => $sfi->getPerms(),
               'size' => sprintf('%u', $sfi->getSize()), 'target' => fileUtils::decodeFilename($sfi->getLinkTarget()),
               'type' => $sfi->getType(),
               'readable' => $sfi->isReadable(), 'writable' => $sfi->isWritable()];
            $this->path = fileUtils::decodeFilename($sfi->getRealPath());
            if($this->data['type'] === 'dir') {
               if(!self::$innerCreation)
                  $this->path .= '\\.';
               else{
                  $paths = explode('\\', str_replace('/', '\\', $path));
                  if(array_pop($paths) === '..') {
                     do
                        $add = array_pop($paths);while($add === '.');
                     $this->path .= "\\$add\\..";
                  }
               }
            }
         }catch(RuntimeException $e) {
            # ...but will raise an exception, if any of the get...-functions
            # above fail.
            # It's the webmaster's responsibility to add at least one of the
            # reliable functionalities used above, so give them a clue by
            # logging this not-found error which may be wrong.
            Log::reportError(I18N::getInternalLanguage()->get('fileUtils.directoryIterator',
                  true));
            throw new UnexpectedValueException("File not found: $path");
         }
      }

      private function initSubItems() {
         if($this->data['type'] !== 'dir') {
            $this->subItems = [];
            return;
         }
         # Interestingly, PHP's DirectoryIterator will have the filename . for
         # directories that are created by the user, but the correct directory
         # name for subiterators.
         self::$innerCreation = true;
         # We should already have a list of fully-qualified names of sub-items
         # stored in our data array. These have to be converted to
         # DirectoryIterator objects.
         if(isset($this->data['sub'])) {
            $this->subItems = $this->data['sub'];
            foreach($this->subItems as &$sub)
               $sub = new fimDirectoryIterator($sub);
         }else{
            # However, if we were forced to use PHP's internal functions, we
            # did not yet gather the list of items. For this purpose, we will
            # now use PHP's own DirectoryIterator. Note that this is a drawback
            # which will not allow us to get items beyond the CodePage!
            $iterators = new DirectoryIterator($this->codedPath);
            $si = [];
            $failed = false;
            foreach($iterators as $iterator)
               if($iterator->isDot())
                  continue;
               else
                  try {
                     $si[] = new fimDirectoryIterator(fileUtils::decodeFilename($iterator->getPathname()));
                  }catch(UnexpectedValueException $e) {
                     $failed = true;
                  }
            $this->subItems = $si;
            if($failed) # We got at least one file or directory that cannot be
            # accessed. Do not throw an exception, as most of the process may
            # have been successful, but report this incident.
               Log::reportError(I18N::getInternalLanguage()->get('fileUtils.directoryIterator',
                     true));
         }
         array_unshift($this->subItems,
            new fimDirectoryIterator($this->path . '\\..'));
         array_unshift($this->subItems, $this);
         reset($this->subItems);
         self::$innerCreation = false;
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets last access time of the file
       * @link http://php.net/manual/en/splfileinfo.getatime.php
       * @return int the time the file was last accessed.
       */
      public function getATime() {
         return $this->data['atime'];
      }

      /**
       * (PHP 5 &gt;= 5.2.2)<br/>
       * Get base name of current fimDirectoryIterator item.
       * @link http://php.net/manual/en/directoryiterator.getbasename.php
       * @param string $suffix [optional] <p>
       * If the base name ends in <i>suffix</i>, this will be cut.
       * </p>
       * @return string The base name of the current <b>fimDirectoryIterator</b> item.
       */
      public function getBasename($suffix = null) {
         return basename($this->path, $suffix);
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets the inode change time
       * @link http://php.net/manual/en/splfileinfo.getctime.php
       * @return int The last change time, in a Unix timestamp.
       */
      public function getCTime() {
         return $this->data['ctime'];
      }

      /**
       * (PHP 5 &gt;= 5.3.6)<br/>
       * Gets the file extension
       * @link http://php.net/manual/en/directoryiterator.getextension.php
       * @return string a string containing the file extension, or an
       * empty string if the file has no extension.
       */
      public function getExtension() {
         return pathinfo($this->path, PATHINFO_EXTENSION);
      }

      /**
       * (PHP 5)<br/>
       * Return file name of current fimDirectoryIterator item.
       * @link http://php.net/manual/en/directoryiterator.getfilename.php
       * @return string the file name of the current <b>fimDirectoryIterator</b> item.
       */
      public function getFilename() {
         return pathinfo($this->path, PATHINFO_BASENAME);
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets the file group
       * @link http://php.net/manual/en/splfileinfo.getgroup.php
       * @return int The group id in numerical format.
       */
      public function getGroup() {
         return 0;
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets the inode for the file
       * @link http://php.net/manual/en/splfileinfo.getinode.php
       * @return int the inode number for the filesystem object.
       */
      public function getInode() {
         return 0;
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets the last modified time
       * @link http://php.net/manual/en/splfileinfo.getmtime.php
       * @return int the last modified time for the file, in a Unix timestamp.
       */
      public function getMTime() {
         return $this->data['mtime'];
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets the owner of the file
       * @link http://php.net/manual/en/splfileinfo.getowner.php
       * @return int The owner id in numerical format.
       */
      public function getOwner() {
         return 0;
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets the path without filename
       * @link http://php.net/manual/en/splfileinfo.getpath.php
       * @return string the path to the file.
       */
      public function getPath() {
         return pathinfo($this->path, PATHINFO_DIRNAME);
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets the path to the file
       * @link http://php.net/manual/en/splfileinfo.getpathname.php
       * @return string The path to the file.
       */
      public function getPathname() {
         return $this->path;
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets file permissions
       * @link http://php.net/manual/en/splfileinfo.getperms.php
       * @return int the file permissions.
       */
      public function getPerms() {
         return $this->data['perms'];
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets file size
       * @link http://php.net/manual/en/splfileinfo.getsize.php
       * @return int The filesize in bytes.
       */
      public function getSize() {
         return $this->data['size'];
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets file type
       * @link http://php.net/manual/en/splfileinfo.gettype.php
       * @return string A string representing the type of the entry.
       * May be one of file, link,
       * or dir
       */
      public function getType() {
         return $this->data['type'];
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Tells if the file is a directory
       * @link http://php.net/manual/en/splfileinfo.isdir.php
       * @return bool <b>TRUE</b> if a directory, <b>FALSE</b> otherwise.
       */
      public function isDir() {
         return $this->data['type'] === 'dir';
      }

      /**
       * (PHP 5)<br/>
       * Determine if current fimDirectoryIterator item is '.' or '..'
       * @link http://php.net/manual/en/directoryiterator.isdot.php
       * @return bool <b>TRUE</b> if the entry is . or .., otherwise <b>FALSE</b>
       */
      public function isDot() {
         $basename = basename($this->path);
         return ($basename === '.') || ($basename === '..');
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Tells if the file is executable
       * @link http://php.net/manual/en/splfileinfo.isexecutable.php
       * @return bool <b>TRUE</b> if executable, <b>FALSE</b> otherwise.
       */
      public function isExecutable() {
         return $this->data['type'] & 0777 === 0777;
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Tells if the object references a regular file
       * @link http://php.net/manual/en/splfileinfo.isfile.php
       * @return bool <b>TRUE</b> if the file exists and is a regular file (not a link), <b>FALSE</b> otherwise.
       */
      public function isFile() {
         return $this->data['type'] === 'file';
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Tells if the file is a link
       * @link http://php.net/manual/en/splfileinfo.islink.php
       * @return bool <b>TRUE</b> if the file is a link, <b>FALSE</b> otherwise.
       */
      public function isLink() {
         return $this->data['type'] === 'link';
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Tells if file is readable
       * @link http://php.net/manual/en/splfileinfo.isreadable.php
       * @return bool <b>TRUE</b> if readable, <b>FALSE</b> otherwise.
       */
      public function isReadable() {
         return $this->data['readable'];
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Tells if the entry is writable
       * @link http://php.net/manual/en/splfileinfo.iswritable.php
       * @return bool <b>TRUE</b> if writable, <b>FALSE</b> otherwise;
       */
      public function isWritable() {
         return $this->data['writable'];
      }

      /**
       * (PHP 5)<br/>
       * Get file name as a string
       * @link http://php.net/manual/en/directoryiterator.tostring.php
       * @return string the file name of the current <b>fimDirectoryIterator</b> item.
       */
      public function __toString() {
         return pathinfo($this->path, PATHINFO_FILENAME);
      }

      private $getInfoClass = 'SplFileInfo';

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets an SplFileInfo object for the file
       * @link http://php.net/manual/en/splfileinfo.getfileinfo.php
       * @param string $class_name [optional] <p>
       * Name of an <b>SplFileInfo</b> derived class to use.
       * </p>
       * @return SplFileInfo An <b>SplFileInfo</b> object created for the file.
       * @throws UnexpectedValueException This exceptions is thrown if the
       *    file name contains special characters that prevent FIM from
       *    accessing the file.
       */
      public function getFileInfo($class_name = null) {
         if(!isset($this->codedPath))
            $this->codedPath = fileUtils::encodeFilename($this->path);
         if($class_name === null)
            $class_name = $this->getInfoClass;
         return new $class_name($this->codedName);
      }

      /**
       * (PHP 5 &gt;= 5.2.2)<br/>
       * Gets the target of a link
       * @link http://php.net/manual/en/splfileinfo.getlinktarget.php
       * @return string the target of the filesystem link.
       */
      public function getLinkTarget() {
         return $this->data['target'];
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets an SplFileInfo object for the path
       * @link http://php.net/manual/en/splfileinfo.getpathinfo.php
       * @param string $class_name [optional] <p>
       * Name of an <b>SplFileInfo</b> derived class to use.
       * </p>
       * @return SplFileInfo an <b>SplFileInfo</b> object for the parent path of the file.
       * @throws UnexpectedValueException This exceptions is thrown if the
       *    file name contains special characters that prevent PHP from
       *    accessing the file.
       */
      public function getPathInfo($class_name = null) {
         if(!isset($this->codedPath))
            $this->codedPath = fileUtils::encodeFilename($this->path);
         if($class_name === null)
            $class_name = $this->getInfoClass;
         return new $class_name(pathinfo($this->codedPath, PATHINFO_DIRNAME));
      }

      /**
       * (PHP 5 &gt;= 5.2.2)<br/>
       * Gets absolute path to file
       * @link http://php.net/manual/en/splfileinfo.getrealpath.php
       * @return string the path to the file.
       */
      public function getRealPath() {
         return $this->path;
      }

      private $openFileClass = 'SplFileObject';

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Gets an SplFileObject object for the file
       * @link http://php.net/manual/en/splfileinfo.openfile.php
       * @param string $open_mode [optional] <p>
       * The mode for opening the file. See the <b>fopen</b>
       * documentation for descriptions of possible modes. The default
       * is read only.
       * </p>
       * @param bool $use_include_path [optional] <p>
       * When set to <b>TRUE</b>, the filename is also
       * searched for within the include_path
       * </p>
       * @param resource $context [optional] <p>
       * Refer to the context
       * section of the manual for a description of contexts.
       * </p>
       * @return SplFileObject The opened file as an <b>SplFileObject</b> object.
       * @throws UnexpectedValueException This exceptions is thrown if the
       *    file name contains special characters that prevent PHP from
       *    accessing the file.
       */
      public function openFile($open_mode = 'r', $use_include_path = false,
         $context = null) {
         if(!isset($this->codedPath))
            $this->codedPath = fileUtils::encodeFilename($this->path);
         return new $this->openFileClass($this->codedPath, $open_mode,
            $use_include_path, $context);
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Sets the class name used with <b>SplFileInfo::openFile</b>
       * @link http://php.net/manual/en/splfileinfo.setfileclass.php
       * @param string $class_name [optional] <p>
       * The class name to use when openFile() is called.
       * </p>
       * @return void No value is returned.
       */
      public function setFileClass($class_name = null) {
         $this->openFileClass = $class_name === null ? 'SplFileObject' : (string)$class_name;
      }

      /**
       * (PHP 5 &gt;= 5.1.2)<br/>
       * Sets the class used with getFileInfo and getPathInfo
       * @link http://php.net/manual/en/splfileinfo.setinfoclass.php
       * @param string $class_name [optional] <p>
       * The class name to use.
       * </p>
       * @return void No value is returned.
       */
      public function setInfoClass($class_name = null) {
         $this->getInfoClass = $class_name === null ? 'SplFileInfo' : (string)$class_name;
      }

      /**
       * (PHP 5)<br/>
       * Return the current fimDirectoryIterator item.
       * @link http://php.net/manual/en/directoryiterator.current.php
       * @return fimDirectoryIterator The current <b>fimDirectoryIterator</b> item.
       */
      public function current() {
         if(!isset($this->subItems))
            return false;
         else
            return current($this->subItems);
      }

      /**
       * (PHP 5)<br/>
       * Return the key for the current fimDirectoryIterator item
       * @link http://php.net/manual/en/directoryiterator.key.php
       * @return string The key for the current <b>fimDirectoryIterator</b> item.
       */
      public function key() {
         if(!isset($this->subItems))
            $this->initSubItems();
         return key($this->subItems);
      }

      /**
       * (PHP 5)<br/>
       * Move forward to next fimDirectoryIterator item
       * @link http://php.net/manual/en/directoryiterator.next.php
       * @return void No value is returned.
       */
      public function next() {
         if(!isset($this->subItems))
            $this->initSubItems();
         return next($this->subItems);
      }

      /**
       * (PHP 5)<br/>
       * Rewind the fimDirectoryIterator back to the start
       * @link http://php.net/manual/en/directoryiterator.rewind.php
       * @return void No value is returned.
       */
      public function rewind() {
         if(!isset($this->subItems))
            $this->initSubItems();
         else
            reset($this->subItems);
      }

      /**
       * (PHP 5 &gt;= 5.3.0)<br/>
       * Seek to a fimDirectoryIterator item
       * @link http://php.net/manual/en/directoryiterator.seek.php
       * @param int $position <p>
       * The zero-based numeric position to seek to.
       * </p>
       * @return void No value is returned.
       */
      public function seek($position) {
         if(!isset($this->subItems))
            $this->initSubItems();
         if(($position -= key($this->subItems)) > 0)
            do {
               next($this->subItems);
            }while(--$position > 0);
         else
            while($position < 0)
               prev($this->subItems);
      }

      /**
       * (PHP 5)<br/>
       * Check whether current fimDirectoryIterator position is a valid file
       * @link http://php.net/manual/en/directoryiterator.valid.php
       * @return bool <b>TRUE</b> if the position is valid, otherwise <b>FALSE</b>
       */
      public function valid() {
         if(!isset($this->subItems))
            $this->initSubItems();
         return key($this->subItems) !== null;
      }

      /**
       * This function returns a filename that can be used to access the file
       * with PHP's file utils if this is possible. When no access to the file
       * can be granted whatsoever, the function will return false.<br />
       * This function is an addition to PHP's DirectoryIterator.
       * @return boolean
       */
      public function getIOPath() {
         try {
            if(!isset($this->codedPath))
               $this->codedPath = fileUtils::encodeFilename($this->path);
            return $this->codedPath;
         }catch(UnexpectedValueException $e) {
            return false;
         }
      }

   }

}else{

   class fimDirectoryIterator extends DirectoryIterator {

   }

}