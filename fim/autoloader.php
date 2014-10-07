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
 * This helper class changes the current working directory as long as the object
 * exists, then reverts it back automatically
 */
class chdirHelper {

   private $oldDir;

   public function __construct($newDir) {
      $this->oldDir = getcwd();
      chdir($newDir);
   }

   public function __destruct() {
      chdir($this->oldDir);
   }

}

$chdir = new chdirHelper(__DIR__);
require './exceptions.php';
require './config.php';
require './i18n.php';
require './log.php';

require './request.php';
require './response.php';
require './router.php';
require './rules.php';

require './serialization.php';
require './semaphore.php';
require './session.php';

require './reflectionFile.php';
require './autoboxing.php';
require './module.php';
require './smarty.php';
require './executor.php';

require './database.php';
require './databaseConnection.php';
require './table.php';
require './primaryTable.php';

require './fileUtils.php';
if(!function_exists('array_column'))
   require './array_column/array_column.php';
unset($chdir);

spl_autoload_register(function($class) {
   $pathInfo = pathinfo(str_replace('\\', '/', $class) . '.php');
   $dirname = $pathInfo['dirname'];
   if($dirname === 'content' || $dirname === 'script' || strpos($dirname,
         'content/') === 0 || strpos($dirname, 'script/') === 0) {
      $basename = $pathInfo['basename'];
      unset($pathInfo);
      $basename[0] = strtolower($basename[0]);
      @include (CodeDir . "$dirname/fim.$basename");
   }elseif($dirname === 'data' || strpos($dirname, 'data/') === 0) {
      $basename = $pathInfo['basename'];
      unset($pathInfo);
      $basename[0] = strtolower($basename[0]);
      require (CodeDir . "$dirname/$basename");
   }elseif(($lower = strtolower($class)) === 'phpmailer' || $lower === 'pop3' || $lower === 'smtp')
      require (FrameworkPath . "PHPMailer/class.$lower.php");
   else{
      static $plugins = null;
      if($plugins === null)
         $plugins = \Config::get('plugins');
      if(isset($plugins[$lower])) {
         $path = $plugins[$lower];
         if($path[0] === '#')
            require substr($path, 1);
         else{
            $cwd = new chdirHelper(CodeDir . 'plugins');
            require \Router::convertFIMToFilesystem($path);
         }
      }else
         @include (CodeDir . "plugins/$dirname/{$pathInfo['basename']}");
   }
});
