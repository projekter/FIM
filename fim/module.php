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
 * This is the base class for all kinds of modules.
 */
abstract class Module {

   /**
    * This is FIM's internal smarty object
    * @var Smarty
    */
   public static $smarty;
   private $moduleName, $modulePath;
   private static $templateVars = [];

   /**
    * Return value that can be used within handleError() and handleErrorX()
    * functions. This return value will cause the error handler to continue
    * to look for the next handler in hierarchy.
    * @see self::PROPAGATE_UP
    */
   const PROPAGATE = -1;

   /**
    * Return value that can be used within handleError() and handleErrorX()
    * functions. This return value will cause the error handler to continue
    * to look for the next handler in hierarchy. If given back in
    * handleErrorX(), the handleError() function of the current module will
    * be skipped.
    * @see self::PROPAGATE
    */
   const PROPAGATE_UP = -2;

   public final function __construct() {
      $r = new ReflectionClass($this);
      $this->modulePath = dirname(substr($r->getFileName(), strlen(CodeDir)));
      $this->moduleName = basename($this->modulePath);
      $this->modulePath = '//' . str_replace('\\', '/', $this->modulePath) . '/';
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

   /**
    * Returns the path of the file the module was declared in
    * @return string
    */
   public final function getModulePath() {
      return $this->modulePath;
   }

   /**
    * Gathers the output of a template file.
    * @param string $templateFile The filename of the template file, relative
    *    to the module's path
    * @return string
    */
   protected final function fetchTemplate($templateFile) {
      if(!defined('ServerEncoded'))
      /**
       * This constant contains the Server contant with applied
       * htmlspecialchars
       */
         define('ServerEncoded',
            htmlspecialchars(Server, ENT_QUOTES, Smarty::$_CHARSET));
      $templateFile = Router::normalizeFIM($templateFile);
      $chdir = new \fim\chdirHelper(Router::convertFIMToFilesystem(dirname($templateFile)));
      try {
         return self::$smarty->clearAllAssign()
               ->assign(self::$templateVars)
               ->assignGlobal('__module', $this->moduleName)
               ->assignGlobal('__modulePath', $this->modulePath)
               ->assignGlobal('__modulePathArray',
                  $ma = Router::normalizeFIM($this->modulePath, true))
               ->assignGlobal('__moduleResource',
                  '/' . implode('/', $ma = array_slice($ma, 1)))
               ->assignGlobal('__moduleResourceArray', $ma)
               ->fetch($templateFile);
      }catch(SmartyException $e) {
         throw new ModuleException(I18N::getInternalLocale()->get(['module',
            'templateException'], [$templateFile]), 0, $e);
      }
   }

   /**
    * Outputs a template file. Any existing buffer data will be cleared
    * before it is filled with the template data
    * @param string $templateFile The filename of the template file, relative
    *    to the module's path
    */
   protected final function displayTemplate($templateFile) {
      ob_clean();
      echo $this->fetchTemplate($templateFile);
   }

   /**
    * Changes the Content-Type header. You may also change the charset.
    * By default the charset is left unchanged. This charset is also applied
    * to Smarty.
    * @param string|array $newMIMEType Pass an array in order to perform
    *    Content Negotiation
    * @param string|null $newCharset
    * @see \Response::contentNegotiation()
    */
   protected final function setContentType($newMIMEType, $newCharset = null) {
      if($newCharset !== null)
         $encoding = "; charset=$newCharset";
      else{
         $encoding = Response::get('Content-Type',
               '; charset=' . Config::get('defaultEncoding'));
         if(preg_match('#; charset=(.*)#', $encoding, $matches) === 0)
            $newCharset = Config::get('defaultEncoding');
         else
            $newCharset = $matches[1];
         $encoding = "; charset=$newCharset";
      }
      if(is_array($newMIMEType))
         Response::contentNegotiation($newMIMEType, $newCharset);
      else{
         if(strpos($newMIMEType, '/') === false)
            $newMIMEType = Response::getMIMEType(".$newMIMEType");
         Response::set('Content-Type', "$newMIMEType$encoding");
      }
      Smarty::$_CHARSET = $newCharset;
   }

   /**
    * Outputs a simple file without parsing. Any existing buffer data will be
    * cleared before it is filled with the template data. This is the same as
    * if the file had been requested by the user by entering the url;
    * however, no further rules will be applied. You may also forward to the
    * file by using $this->forward; however, execution of your module will
    * only be continued when you use this function. Furthermore, it allows a
    * better control of access checks.
    * @param string $fileName The filename of the file, relative to the
    *    module's path
    * @param int $checkAccess A bitmask combination of
    *    \Rules::CHECK_EXISTENCE and \Rules::CHECK_READING constants. The
    *    given access checks will be performed. If they fail, error() will be
    *    called.
    * @param string $requireSubdirectory It is possible to restrict file
    *    access to a subdirectory of the current module. If the requested
    *    file name is located outside of this directory or one of its
    *    subdirectories, a 404 error will rise. It is also possible to go up
    *    in hierarchy or to use the current directory with '.' or '..';
    *    absolute paths (relative to CodeDir) may also be passed. By default,
    *    there is no restriction.
    */
   protected final function displayFile($fileName, $checkAccess = 0,
      $requireSubdirectory = null) {
      $fileName = Router::normalizeFIM($fileName);
      if($requireSubdirectory !== null) {
         $requireSubdirectory = Router::normalizeFIM($requireSubdirectory);
         if(substr($fileName, 0, strlen($requireSubdirectory)) !== $requireSubdirectory)
            $this->errorInternal(404);
      }
      if(!fileUtils::isReadable(Router::convertFIMToFilesystem($fileName, false)))
         $this->errorInternal(404);
      else{
         if(($checkAccess & Rules::CHECK_EXISTENCE) !== 0)
            if(!Rules::check($fileName, Rules::CHECK_EXISTENCE))
               $this->errorInternal(404);
         if(($checkAccess & Rules::CHECK_READING) !== 0)
            if(!Rules::check($fileName, Rules::CHECK_READING))
               $this->errorInternal(401);
      }
      Response::$responseText = '';
      ob_clean(); # Avoid adding any data that was echoed before
      \fim\Executor::passFile($fileName);
   }

   private final function errorInternal($errno) {
      throw new FIMErrorException($this->modulePath . 'fim.module.php',
      [$errno], true);
   }

   /**
    * Returns an error. Execution of the current module code is stopped. You
    * may wish to implement error handling routines. Error handling
    * propagates.
    * @param int $errno A number of the error
    * @param mixed ... You may pass as many parameters as you wish; they will
    *    be forwarded to the error handling routines.
    */
   protected final function error($errno) {
      throw new FIMErrorException($this->modulePath . 'fim.module.php',
      func_get_args());
   }

   /**
    * Returns information about when the error() function was called for the
    * last time or null if there was not an error that was defined by the
    * programmer. The error function might however been called by the
    * framework.
    * @return null|array('code' => error code,
    *    'file' => module file name relative to the CodeDir,
    *    'line' => line of calling error, 'trace' => stacktrace array)
    */
   protected final function getErrorDetails() {
      if(!isset(FIMErrorException::$last))
         return null;
      $trace = FIMErrorException::$last->getTrace();
      # The module file name is stored in the message; getLine() is not
      # senseful, as it would always give the line of \Module::error(). We're
      # interested in the call line of the error function... But if $internal
      # is set to true, we are acually interested in the first but one line
      return ['code' => FIMErrorException::$last->getCode(),
         'file' => FIMErrorException::$last->getMessage(),
         'line' => @$trace[(int)FIMErrorException::$lastInternal]['line'],
         'trace' => $trace];
   }

   /**
    * Performs an internal redirection to another location (the user will not
    * notice a thing). Execution of the current code is aborted.
    * @param string $to A valid file/path. Note that no routing will apply to
    *    this path; this makes it possible to access resources that are
    *    inaccessible by direct input.
    * @param array $parameters An associative array that contains all
    *    potential parameters for the new function. Leave it to null if the
    *    current parameters shall not be changed.
    * @param boolean $keepTemplateVars False means that all template
    *    variables that were set in the current module will be deleted
    * @param boolean $checkRules False means that it is possible to access
    *    modules/files that were originally not allowed to.
    * @see \Module::redirect()
    */
   protected final function forward($to, array $parameters = null,
      $keepTemplateVars = true, $checkRules = true) {
      if(!$keepTemplateVars)
         self::$templateVars = [];
      if($parameters !== null)
         $_GET = $parameters;
      \fim\Executor::handlePath($to, false, $checkRules);
      throw new ForwardException();
   }

   /**
    * Performs an external redirection to another location (the user's
    * browser has to perform the redirect). Execution of the current code is
    * aborted.
    * @param string $to A valid file/path as it would be given to the
    *    template function url. If no url could be constructed, a module
    *    exception is raised.
    * @param array $parameters An associative array that contains all
    *    potential parameters for the new function
    * @see \Module::forward()
    */
   protected final function redirect($to, array $parameters = []) {
      if(($to = Router::mapPathToURL($to, $parameters)) === false)
         throw new ModuleException(I18N::getInternalLocale()->get(['module',
            'redirectInvalid'], [$to]));
      Response::set('Location', $to);
      throw new ForwardException();
   }

}

# The private variable Rules::$templateVars shall be a reference to
# Module::$templateVars, which requires this little hack
$setRulesTemplateReference = Closure::bind(function() {
      $setRules = Closure::bind(function(&$val) {
            self::$templateVars = &$val;
         }, null, 'Rules');
      $setRules(self::$templateVars);
   }, null, 'Module');
$setRulesTemplateReference();
unset($setRulesTemplateReference);
