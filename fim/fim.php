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

if(version_compare(PHP_VERSION, '5.4.0', '<'))
   die('This framework requires at least PHP 5.4 (currently in use: ' .
      PHP_VERSION . ')');

error_reporting(E_ALL | E_STRICT);
/**
 * FrameworkPath contains the directory in which the FIM files are located,
 * including a trailing slash.
 * @const FrameworkPath FIM path
 */
define('FrameworkPath', __DIR__ . '/');

$basedir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
$basedir = (string)substr($basedir, strlen($_SERVER['DOCUMENT_ROOT']));
if(substr($basedir, -1) !== '/')
   $basedir .= '/';
if(@$basedir[0] !== '/')
   $basedir = "/$basedir";
/**
 * BaseDir contains the directory of the index.php file relative to the
 * document root, including a starting and trailing slash.
 * @const BaseDir web directory
 */
define('BaseDir', $basedir);
/**
 * CLI defines whther FIM was loaded via the command line prompt with php-cli
 * or normally by a webserver.
 * @const CLI
 */
define('CLI', !isset($_SERVER['SERVER_SOFTWARE']));
if(CLI)
   chdir(dirname($GLOBALS['argv'][0]));# php-cli doesn't set our working dir
/**
 * ResourceDir is the name of the directory that is supposed to contain all
 * data that is exposed to the user. This will be either 'content' (HTTP
 * mode) or 'script' (CLI mode)
 * @const ResourceDir
 */
define('ResourceDir', CLI ? 'script' : 'content');
$os = strtolower(explode(' ', php_uname('s'), 2)[0]);
$osses = ['windows' => 'Windows', 'winnt' => 'Windows',
   'wince' => 'Windows', 'darwin' => 'Mac', 'linux' => 'Linux'];
/**
 * OS is the general name of the current operating system. It will hold one
 * of the values 'Windows', 'Mac', 'Linux' or 'Unknown'
 * @const OS
 */
define('OS', isset($osses[$os]) ? $osses[$os] : 'Unknown');
/**
 * CodeDir contains the absolute directory of the index.php file, including
 * a trailing slash
 * @const CodeDir main directory
 */
define('CodeDir', str_replace('\\', '/', getcwd()) . '/');
unset($basedir, $os, $osses);

/**
 * This function starts the FIM layer. It must only be called once at the very
 * beginning of the application.
 * @param array $config
 */
function fimInitialize(array $config) {
   if($config === false)
      $config = [];
   elseif(!is_array($config))
      die('Error in configuration: Configuration file must return an array.');

   require FrameworkPath . 'autoloader.php';

   Config::initialize($config);

   # FIM can be set up in a web-only or console-only manner
   if(CLI && !file_exists(CodeDir . 'script/'))
      die('Console access is not set up.');
   elseif(!CLI && !file_exists(CodeDir . 'content/'))
      die('Web access is not set up.');

   # We definitely need to call isHTTPS() to fill the cache
   if(!Request::isHTTPS() && !CLI && Config::get('requireSecure')) {
      header("Location: https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
      exit;
   }
   fim\sessionInitialize();

   fim\Executor::execute();

   Response::doSend();
}
