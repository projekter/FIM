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
    * This is the base class for all rule objects.
    */
   class Rules {

      const CHECK_EXISTENCE = 0b001;
      const CHECK_READING = 0b010;
      const CHECK_LISTING = 0b100;
      const CHECK_ACCESS = 0b111;

      private static $cacheListing = [], $cacheExistence = [], $cacheReading = [],
         $singletons = [];

      /**
       * Reference to Module::$templateVars
       * @var array
       */
      private static $templateVars = [];
      private $checkExistence, $checkReading, $checkListing, $directory;

      /**
       * Creates a new Rules object. This constructor shall only be called
       * within a fim.rules.php. It expects three closures (might be null) that
       * are triggered when the respective check is performed.<br />
       * The closured may require at most two parameters, the first one being
       * only the name of the file, the second one being its complete file path
       * and name, starting from CodeDir.
       * @param Closure $checkExistence
       * @param Closure $checkReading
       * @param Closure $checkListing
       */
      public final function __construct(Closure $checkExistence = null,
         Closure $checkReading = null, Closure $checkListing = null) {
         if(isset($checkExistence))
            $this->checkExistence = $checkExistence->bindTo($this, $this);
         if(isset($checkReading))
            $this->checkReading = $checkReading->bindTo($this, $this);
         if(isset($checkListing))
            $this->checkListing = $checkListing->bindTo($this, $this);
         if(func_num_args() < 4) {
            $this->directory = dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,
                  1)[0]['file']);
            static $cdl = null;
            if($cdl === null)
               $cdl = strlen(CodeDir);
            $this->directory = '//' . substr($this->directory, $cdl);
         }else
            $this->directory = func_get_arg(3);
      }

      public final function __get($name) {
         return self::$templateVars[$name];
      }

      public final function __set($name, $value) {
         self::$templateVars[$name] = $value;
      }

      public final function __unset($name) {
         unset(self::$templateVars[$name]);
      }

      public final function __isset($name) {
         return isset(self::$templateVars[$name]);
      }

      public final function __call($name, array $arguments) {
         switch($name) {
            case 'checkExistence':
               $closure = $this->checkExistence;
               break;
            case 'checkReading':
               $closure = $this->checkReading;
               break;
            case 'checkListing':
               $closure = $this->checkListing;
               break;
            default:
               throw new RulesException(I18N::getInternalLanguage()->get(['rules',
                  'callNotFound'], [$name]));
         }
         return isset($closure) ? \fim\Autoboxing::callFunction($closure,
               ['fileName' => $arguments[0], 'fullName' => $arguments[1]]) : null;
      }

      /**
       * Checks the recommendation that the simple rules file in the same folder
       * as the rules script gives for a certain file name
       * @param string $fileName Must start in ResourceDir.
       * @param int $checkFor A bitmask of the Rules::CHECK_-constants
       * @return int|boolean|null Null if there is no rules file
       */
      protected final function checkSimpleAccess($fileName, $checkFor) {
         if(($simpleInstance = self::buildSimpleCache("{$this->directory}/fim.rules.txt")) === null)
            return null;
         $fullName = Router::normalizeFIM($fileName);
         if($fileName !== '.')
            $fileName = basename($fullName);
         # Simple overwriting scheme: Forbid with the greatest weight, allow
         #    with the smallest weight. If one single entry says forbidden, even
         #    the weightiest allowance cannot dominate - not even true.
         $val = 0;
         if(($checkFor & self::CHECK_EXISTENCE) !== 0)
            if(($val = $simpleInstance->checkExistence($fileName, $fullName)) === false)
               return false;
            elseif($val === null)
               $val = 0;
         if(($checkFor & self::CHECK_READING) !== 0) {
            if(($newVal = $simpleInstance->checkReading($fileName, $fullName)) === false)
               return false;
            elseif($newVal != 0) # or null
               if($val === 0)
                  $val = $newVal;
               elseif($newVal !== true && ($val === true || (int)$newVal < (int)$val))
                  $val = $newVal;
               elseif($newVal === true && $val === 0)
                  $val = true;
         }
         if(($checkFor & self::CHECK_LISTING) !== 0) {
            if(($newVal = $simpleInstance->checkListing($fileName, $fullName)) === false)
               return false;
            elseif($newVal !== 0) # or null
               if($val === 0)
                  $val = $newVal;
               elseif($newVal !== true && ($val === true || (int)$newVal < (int)$val))
                  $val = $newVal;
               elseif($newVal === true && $val === 0)
                  $val = true;
         }
         return $val;
      }

      /**
       * Checks the access for a certain file with respect to cached values
       * @param string $folder
       * @param int $checkFor A bitmask
       * @param string $fileName
       * @return int|boolean
       */
      private static function checkAccessFileCache($folder, $checkFor, $fileName) {
         $cacheStr = "$folder/$fileName";
         # Simple overwriting scheme: Forbid with the greatest weight, allow
         #    with the smallest weight. If one single entry says forbidden, even
         #    the weightiest allowance cannot dominate - not even true.
         $val = 0;
         if(($checkFor & self::CHECK_EXISTENCE) !== 0) {
            if(isset(self::$cacheExistence[$cacheStr])) {
               if(($val = self::$cacheExistence[$cacheStr]) === false)
                  return false;
            }elseif(($val = self::$cacheExistence[$cacheStr] = self::checkAccessFile($folder,
                  self::CHECK_EXISTENCE, $fileName)) === false)
               return false;
            elseif($val === null)
               $val = self::$cacheExistence[$cacheStr] = 0;
         }
         if(($checkFor & self::CHECK_READING) !== 0) {
            if(isset(self::$cacheReading[$cacheStr])) {
               if(($newVal = self::$cacheReading[$cacheStr]) === false)
                  return false;
            }elseif(($newVal = self::$cacheReading[$cacheStr] = self::checkAccessFile($folder,
                  self::CHECK_READING, $fileName)) === false)
               return false;
            elseif($newVal === null)
               $newVal = self::$cacheReading[$cacheStr] = 0;
            if($newVal != 0) # or null
               if($val === 0)
                  $val = $newVal;
               elseif($newVal !== true && ($val === true || (int)$newVal < (int)$val))
                  $val = $newVal;
               elseif($newVal === true && $val === 0)
                  $val = true;
         }
         if(($checkFor & self::CHECK_LISTING) !== 0) {
            if(isset(self::$cacheListing[$cacheStr])) {
               if(($newVal = self::$cacheListing[$cacheStr]) === false)
                  return false;
            }elseif(($newVal = self::$cacheListing[$cacheStr] = self::checkAccessFile($folder,
                  self::CHECK_LISTING, $fileName)) === false)
               return false;
            elseif($newVal === null)
               $newVal = self::$cacheListing[$cacheStr] = 0;
            if($newVal != 0) # or null
               if($val === 0)
                  $val = $newVal;
               elseif($newVal !== true && ($val === true || (int)$newVal < (int)$val))
                  $val = $newVal;
               elseif($newVal === true && $val === 0)
                  $val = true;
         }
         return $val;
      }

      /**
       * Checks the access for a certain file by calculating the value.
       * @param string $folder
       * @param int $checkFor One of CHECK_EXISTENCE, CHECK_READING or
       *    CHECK_LISTING; not a combination of them
       * @param string $fileName
       * @return int|boolean|null
       */
      private static function checkAccessFile($folder, $checkFor, $fileName) {
         $obj = "$folder/fim.rules.php";
         $absObj = Router::convertFIMToFilesystem($obj, false);
         if(!isset(self::$singletons[$obj]))
            if(is_file($absObj))
               self::$singletons[$obj] = fim\requireClosure($absObj);
            else
               self::$singletons[$obj] = false;
         if(($obj = self::$singletons[$obj]) === false)
            if(($obj = self::buildSimpleCache("$folder/fim.rules.txt")) === null)
               return null;
         $fullName = ($fileName === '.') ? $folder : "$folder/$fileName";
         static $functions = [self::CHECK_EXISTENCE => 'checkExistence',
            self::CHECK_READING => 'checkReading', self::CHECK_LISTING => 'checkListing'];
         $chdir = new fim\chdirHelper($folder);
         $previousConnection = Database::getActiveConnection(false);
         \Database::setActiveConnection('.');
         $ret = $obj->{$functions[$checkFor]}($fileName, $fullName);
         Database::restoreConnection($previousConnection);
         return $ret;
      }

      /**
       * Checks whether a bitmask of rights can be applied to a certain file or
       * folder. The result will be cached.
       * @param string $fileName Must start in ResourceDir
       * @param int $checkFor Bitmask of CHECK_-constants
       * @return boolean
       */
      public static final function check($fileName, $checkFor) {
         $hierarchy = Router::normalizeFIM($fileName, true);
         if(empty($hierarchy) || $hierarchy[0] !== ResourceDir)
            throw new RulesException(I18N::getInternalLanguage()->get(['rules', 'invalidFilenameScope'],
               [$fileName, CLI ? 'script' : 'content']));
         $folder = '//' . ResourceDir;
         $absFolder = \Router::convertFIMToFilesystem($folder, false);
         if(($currentState = self::checkAccessFileCache($folder, $checkFor, '.')) === false)
            return false;
         array_shift($hierarchy);
         foreach($hierarchy as $cur) {
            # first check what the parent says to the folder
            $newState = self::checkAccessFileCache($folder, $checkFor, $cur);
            if($newState === false)
               return false;
            elseif($newState === true || $currentState === 0)
               $currentState = $newState;
            elseif(($currentState !== true) && ($newState !== 0) && # abs($newState) >= abs($currentState) is the same but 15x slower
               (($newState < 0 ? -$newState : $newState) >= #
               ($currentState < 0 ? -$currentState : $currentState)))
               $currentState = $newState;
            $folder .= "/$cur";
            $absFolder .= "/$cur";
            if(is_dir($absFolder)) {
               # then check what the folder says to itself
               $newState = self::checkAccessFileCache($folder, $checkFor, '.');
               if($newState === false)
                  return false;
               elseif($newState === true || $currentState === 0)
                  $currentState = $newState;
               elseif(($currentState !== true) && ($newState !== 0) && #
                  (($newState < 0 ? -$newState : $newState) >= #
                  ($currentState < 0 ? -$currentState : $currentState)))
                  $currentState = $newState;
            }
         }
         if($currentState === 0) {
            # No recommendation; check defaults
            if(($checkFor & (self::CHECK_EXISTENCE | self::CHECK_READING)) !== 0)
               if(!Config::get('defaultAccessGranted'))
                  return false;
            if(($checkFor & self::CHECK_LISTING) !== 0)
               if(Config::equals('directoryListing',
                     Config::DIRECTORY_LISTING_NONE))
                  return false;
            return true;
         }elseif($currentState === true)
            return true;
         else
            return $currentState > 0;
      }

      /**
       * Checks whether for a specific location a filter will ever be triggered.
       * This function only checks for the existence of fim.rules-files, not
       * what these files will do
       * @param string $fileName Must start in ResourceDir
       * @return boolean
       */
      public static final function checkFilterExistence($fileName) {
         $hierarchy = Router::normalizeFIM($fileName, true);
         if(empty($hierarchy) || $hierarchy[0] !== ResourceDir)
            throw new RulesException(I18N::getInternalLanguage()->get(['rules', 'invalidFilenameScope'],
               [$fileName, CLI ? 'script' : 'content']));
         $folder = CodeDir . ResourceDir;
         array_shift($hierarchy);
         if(is_dir($fileName))
            $hierarchy[] = '.';
         foreach($hierarchy as $cur) {
            if(is_file("$folder/fim.rules.php") || is_file("$folder/fim.rules.txt"))
               return true;
            $folder .= "/$cur";
         }
         return false;
      }

      /**
       * Clears the cache for checked rules. Calling this might be necessary after
       * an update of login credentials or similar operators.
       * @param int $checkCache A bitmask of which caches to clear. Might be any
       *    combination of the \Rules::CHECK_-constants. Clears all caches by
       *    default.
       */
      public static final function clearCache($checkCache = self::CHECK_ACCESS) {
         if((self::$cacheExistence & $checkCache) !== 0)
            self::$cacheExistence = [];
         if((self::$cacheReading & $checkCache) !== 0)
            self::$cacheReading = [];
         if((self::$cacheListing & $checkCache) !== 0)
            self::$cacheListing = [];
      }

      private static $currentFileName, $parsingCache;

      private static function buildSimpleCache($rulesFile) {
         $absRulesFile = \Router::convertFIMToFilesystem($rulesFile, false);
         if(!is_file($absRulesFile))
            return null;
         $cacheFile = 'cache/rules/' . hash('md5', $rulesFile) . '.php';
         if(isset(self::$singletons[$cacheFile]))
            return self::$singletons[$cacheFile];
         $absCacheFile = CodeDir . $cacheFile;
         if(!is_dir(CodeDir . 'cache/rules') && !mkdir(CodeDir . 'cache/rules',
               0700, true))
            throw new FIMInternalException(I18N::getInternalLanguage()->get(['rules',
               'cache', 'writeError', 'directory']));
         $rulesTimestamp = filemtime($absRulesFile);
         $cacheTimestamp = @filemtime($absCacheFile);
         if($cacheTimestamp === $rulesTimestamp)
            return self::$singletons[$cacheFile] = fim\requireClosure($absCacheFile);
         $now = date('Y-m-d H:i:s');
         $cacheContent = <<<Cache
<?php

/* This is an autogenerated cache file. Do not change this file, it might be
 * overwritten at any time without notification.
 * The original file is $rulesFile.
 * Date of generation: $now.
 */

return new Rules(
Cache;
         $rulesContent = file($absRulesFile, FILE_SKIP_EMPTY_LINES);
         if(isset($rulesContent[0]) && strcasecmp(trim($rulesContent[0]),
               'delete') === 0) {
            if(is_file($absCacheFile) && !@unlink($absCacheFile))
               Log::reportInternalError(I18N::getInternalLanguage()->get(['rules',
                     'cache', 'unlinkError', 'cache'], [$cacheFile]));
            if(!@unlink($absRulesFile))
               Log::reportInternalError(I18N::getInternalLanguage()->get(['rules',
                     'cache', 'unlinkError', 'rules'], [$rulesFile]));
            return null;
         }
         static $sections = ['Existence', 'Reading', 'Listing'];
         self::$currentFileName = $rulesFile;
         self::$parsingCache = [];
         $directory = "'" . addcslashes(dirname($rulesFile), "\\'") . "'";
         foreach($sections as $section) {
            $sectionValue = self::parseRulesSection($rulesContent, $section);
            if($sectionValue === '')
               $cacheContent .= "
   null,";
            else
               $cacheContent .= "
   function(\$fileName, \$fullName) {{$sectionValue}
   },";
         }
         $cacheContent .= "
   $directory);";
         if(@file_put_contents($absCacheFile, $cacheContent) === false)
            throw new FIMInternalException(I18N::getInternalLanguage()->get(['rules',
               'cache',
               'writeError', 'content'], [$cacheFile]));
         if(!touch($absCacheFile, $rulesTimestamp))
            throw new FIMInternalException(I18N::getInternalLanguage()->get(['rules',
               'cache',
               'writeError', 'timestamp'], [$cacheFile]));
         # Now try to call all three functions to see whether there is a regex
         # error or something similar. An exception will be thrown. If there is a
         # parsing error, make use of the shutdown function which will write this
         # error to the log
         $shutdownKey = Config::registerShutdownFunction(function() use ($cacheFile, $rulesFile) {
               Log::errorHandler(E_ERROR,
                  I18N::getInternalLanguage()->get(['rules', 'cache', 'syntaxError',
                     'hard'], [$rulesFile, $cacheFile]), $cacheFile, 0, null,
                  true);
            });
         try {
            $obj = fim\requireClosure($absCacheFile);
            $obj->checkExistence('', '');
            $obj->checkReading('', '');
            $obj->checkListing('', '');
         }catch(Exception $e) {
            # No parsing error, only an exception
            Config::unregisterShutdownFunction($shutdownKey);
            throw $e;
         }
         Config::unregisterShutdownFunction($shutdownKey);
         self::$singletons[$cacheFile] = $obj;
         return $obj;
      }

      private static function parseRulesSection(array $fullContent, $sectionName) {
         if(isset(self::$parsingCache[$lowerName = strtolower($sectionName)]))
            if(self::$parsingCache[$lowerName] === true)
               throw new RulesException(I18N::getInternalLanguage()->get(['rules',
                  'cache', 'semanticError', 'recursion'],
                  [self::$currentFileName, $lowerName]));
            else
               return self::$parsingCache[$lowerName];
         else
            self::$parsingCache[$lowerName] = true;
         $startIdx = -1;
         foreach($fullContent as $idx => $line) {
            $line = trim($line);
            if($line === '')
               continue;
            elseif($line[0] === '[') {
               if($startIdx !== -1)
                  return self::$parsingCache[$lowerName] = self::parseRulesArray(array_slice($fullContent,
                           $startIdx, $idx - $startIdx), $fullContent);
               elseif(strcasecmp($line, "[$sectionName]") === 0)
                  $startIdx = $idx + 1;
            }
         }
         if($startIdx !== -1)
            return self::$parsingCache[$lowerName] = self::parseRulesArray(array_slice($fullContent,
                     $startIdx), $fullContent);
      }

      private static function parseRulesArray(array $content, array $fullContent) {
         $result = '';
         for($i = 0, $len = count($content); $i < $len; ++$i) {
            $line = rtrim($content[$i]);
            if($line === '')
               continue;
            elseif(preg_match('/^\s*(-?+\d++|true|false|match)\s*+=\s*+(c|r|e)\s*+(.++)$/i',
                  $line, $group) === 1) {
               if($group[2] !== 'e' && $group[2] !== 'E')
                  $cmp = "'" . addcslashes(trim($group[3]), "\\'") . "'";
               $cmpTo = ($group[2] === 'c' || $group[2] === 'r' || $group[2] === 'e')
                     ? '$fullName' : '$fileName';
               $isMatch = strcasecmp($group[1], 'match') === 0;
               switch($group[2]) {
                  case 'c':
                  case 'C':
                     if($isMatch)
                        throw new RulesException(I18N::getInternalLanguage()->get(['rules',
                           'cache', 'syntaxError', 'matchC'],
                           [self::$currentFileName, $line]));
                     $result .= "
      if($cmpTo === $cmp)";
                     break;
                  case 'r':
                  case 'R':
                     if(@preg_match(trim($group[3]), '') === false)
                        throw new RulesException(I18N::getInternalLanguage()->get(['rules',
                           'cache', 'semanticError', 'invalidRegex'],
                           [self::$currentFileName, $line]));
                     $result .= "
      if(preg_match($cmp, $cmpTo" . ($isMatch ? ', $match' : '') . ') === 1)';
                     $indent = '   ';
                     break;
                  case 'e':
                  case 'E':
                     $indent = '';
                     $result .= "
      \$file = &$cmpTo;
      " . ($isMatch ? "\$match = {$group[3]};" : "if({$group[3]})");
                     break;
               }
               if($isMatch) {
                  $elseif = false;
                  for(++$i; $i < $len; ++$i) {
                     $line = rtrim($content[$i]);
                     if($line === '')
                        continue;
                     elseif(!preg_match('/^\s++(-?+\d++|true|false)\s*+=\s*+(.++)$/i',
                           $line, $group)) {
                        --$i;
                        break;
                     }else{
                        $result .= "
      $indent" . ($elseif ? 'else' : '') . "if({$group[2]})
      $indent   return {$group[1]};";
                        $elseif = true;
                     }
                  }
               }else
                  $result .= "
         return {$group[1]};";
            }elseif(preg_match('/^\s*clone\s*=\s*(listing|existence|reading)\s*$/i',
                  $line, $group) === 1) {
               $result .= self::parseRulesSection($fullContent, $group[1]);
            }else{
               throw new RulesException(I18N::getInternalLanguage()->get(['rules',
                  'cache', 'syntaxError', 'general'],
                  [self::$currentFileName, $line]));
            }
         }
         return $result;
      }

   }

}

namespace fim {

   /**
    * This helper function only requires a given file. It is only needed due to
    * a bug in PHP.
    * @param string $file
    * @return mixed
    */
   function requireClosure($file) {
      return require $file;
   }

}