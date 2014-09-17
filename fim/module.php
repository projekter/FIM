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
    * This is the base class for all kinds of modules.
    */
   abstract class Module {

      /**
       * This is FIM's internal smarty object
       * @var Smarty
       */
      public static $smarty;
      private $moduleName, $modulePath, $contentModulePath;
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
         $this->modulePath = str_replace('\\', '/', $this->modulePath) . '/';
         $this->contentModulePath = (string)substr($this->modulePath, 8);
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
       * Returns the path of the file the module was declared in, relative to
       * CodeDir, including the trailing slash
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
         $templateFile = Router::normalize($templateFile);
         chdir(CodeDir . dirname($templateFile));
         try {
            return self::$smarty->clearAllAssign()
                  ->assign(self::$templateVars)
                  ->assignGlobal('__module', $this->moduleName)
                  ->fetch(CodeDir . $templateFile);
         }catch(SmartyException $E) {
            chdir(CodeDir . $this->modulePath);
            throw new ModuleException(I18N::getInternalLanguage()->get('module.template.exception',
               [$templateFile]), 0, $E);
         }
         chdir(CodeDir . $this->modulePath);
      }

      /**
       * Outputs a template file. Any existing buffer data will be cleared
       * before it is filled with the template data
       * @param string $templateFile The filename of the template file, relative
       *    to the module's path
       */
      protected final function displayTemplate($templateFile) {
         Response::$responseText = $this->fetchTemplate($templateFile);
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
         $fileName = Router::normalize($fileName);
         if($requireSubdirectory !== null) {
            $requireSubdirectory = Router::normalize($requireSubdirectory);
            if(substr($fileName, 0, strlen($requireSubdirectory)) !== $requireSubdirectory)
               $this->errorInternal(404);
         }
         chdir(CodeDir);
         if(!fileUtils::isReadable($fileName))
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
         chdir($this->modulePath);
      }

      private final function errorInternal($errno) {
         throw new FIMErrorException($this->contentModulePath . 'fim.module.php',
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
         throw new FIMErrorException($this->contentModulePath . 'fim.module.php',
         func_get_args());
      }

      /**
       * Returns information about when the error() function was called for the
       * last time or null if there was not an error that was defined by the
       * programmer. The error function might however been called by the
       * framework.
       * @return null|array('code' => error code,
       *    'file' => module file name relative to the ResourceDir,
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
         $to = Router::normalize($to);
         try {
            $to = \fileUtils::encodeFilename($to);
         }catch(\UnexpectedValueException $e) {
            self::error(404, $to);
            return;
         }
         chdir(CodeDir);
         if(!$keepTemplateVars)
            self::$templateVars = [];
         if(isset($parameters))
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
         if(($to = Router::mapPathToURL(fim\parsePath($to), $parameters)) === false)
            throw new ModuleException(I18N::getInternalLanguage()->get('module.redirect.invalid',
               [$to]));
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

   # Now we only have to deal with Smarty. Include Smarty and define some
   # compiler functions which allow URL and language support
   require FrameworkPath . 'smarty/Smarty.class.php';
   Module::$smarty = new Smarty();
   Module::$smarty->setCompileDir(CodeDir . 'cache/templates');
   Module::$smarty->setTemplateDir([]);
   Module::$smarty->registerFilter('post', '\\fim\\fimRegisterLanguage');

   # We cannot simply define the functions smarty_compiler_url and
   # smarty_compiler_l which would be the "standard" way. But these functions
   # won't allow us to make use of the shorthand syntax; they would require the
   # template coder to give parameter names although this is really unnecessary.

   class Smarty_Internal_Compile_URL extends Smarty_Internal_CompileBase {

      public function compile($args, $compiler) {
         # copy pieces of the getAttribute() behavior. As we want to allow as
         # many named parameters and at least one unnamed, we cannot fully rely
         # on the given parent function.
         $parameters = [];
         $escape = $compiler->smarty->escape_html;
         foreach($args as $mixed) {
            # shorthand?
            if(!is_array($mixed)) {
               if(trim($mixed, '\'"') === 'nofilter')
                  $escape = false;
               elseif(!isset($path))
                  $path = $mixed;
               else
                  $compiler->trigger_template_error('too many shorthand attributes',
                     $compiler->lex->taglineno);
            }else{
               if(isset($mixed['nofilter']))
                  if(is_string($mixed['nofilter']) && in_array(trim($mixed['nofilter'],
                           '\'"'), ['true', 'false']))
                     $escape &= trim($mixed['nofilter'], '\'"') !== 'true';
                  elseif(is_numeric($mixed['nofilter']) && in_array($mixed['nofilter'],
                        [0, 1]))
                     $escape &= $mixed['nofilter'] != 1;
                  else
                     $compiler->trigger_template_error("illegal value of option flag \"nofilter\"",
                        $compiler->lex->taglineno);
               else{ // named attribute
                  $attr = each($mixed);
                  $parameters[$attr['key']] = $attr['value'];
               }
            }
         }
         unset($mixed);
         if(!isset($path))
            $compiler->trigger_template_error("missing url attribute",
               $compiler->lex->taglineno);
         $_current_file = $compiler->smarty->_current_file;
         $oldDir = getcwd();
         static $dirCaches = [];
         if(!isset($dirCaches[$_current_file])) {
            # We need to get the file name of the template that is actually
            # executed. $smarty->_current_file serves as a hint: It contains
            # either an absolute path (relative to /) or a relative path to cwd.
            # As the maximum level of "absoluteness" we work with in FIM is
            # CodeDir, we will first have to cut off everything that goes beyond
            # CodeDir.
            static $cdl = null;
            if(!isset($cdl))
               $cdl = strlen(CodeDir);
            $currentFile = str_replace('\\', '/', $_current_file);
            if(substr($currentFile, 0, $cdl) === CodeDir)
               $currentFile = substr($currentFile, $cdl - 1);# Keep trailing /
            $currentFile = Router::normalize($currentFile, false, false);
            $dirCaches[$_current_file] = dirname($currentFile);
            # This is not very performant, but remember we only have to do it
            # once while the template is being compiled.
         }
         chdir($dirCaches[$_current_file]);
         # Now our cwd is assured to be the template's directory.
         # We can only output the path and all parameters are not composed but a
         # static string. This is a very unlikely thing, but may occur in static
         # navigations. It's worth the effort to check. For this, we have to
         # parse the code and check for dynamic strings. Everything that is not
         # a string literal, a concatenation of constant strings or a whitespace
         # character is supposed to be dynamic.
         try {
            $tokens = token_get_all("<?php $path");
            static $validTokens = [T_OPEN_TAG => true, T_CONSTANT_ENCAPSED_STRING => true,
               T_ENCAPSED_AND_WHITESPACE => true, T_WHITESPACE => true];
            foreach($tokens as $token)
               if($token !== '.' && is_array($token))
                  if(!isset($validTokens[$token[0]]))
                     goto invalid;
            $path = @eval("return $path;");
            if($path !== '' && $path[0] === '/')
               if(@$path[1] === '/')
                  $path = substr($path, 1);
               else
                  $path = '/' . ResourceDir . $path;
         }catch(Exception $e) {
            invalid:
            # This is supposed not to happen very often. While parameters will
            # most likely be dynamic, the path itself should be static; so let's
            # accept this performance drawback here.
            $return = '<?php $oldDir=getcwd();chdir(' .
               var_export($dirCaches[$_current_file], true) .
               ');echo ';
            if($escape)
               $return .= 'htmlspecialchars(';
            $return .= "Router::mapPathToURL(fim\\parsePath($path)";
            if(!empty($parameters)) {
               $return .= ', [';
               foreach($parameters as $key => $param)
                  $return .= var_export($key, true) . "=>$param,";
               $return .= ']';
            }
            if($escape)
               $return .= "), ENT_QUOTES, '" . addcslashes(Smarty::$_CHARSET,
                     "\\'") . "'";
            $return .= ');chdir($oldDir);unset($oldDir);?>';
            chdir($oldDir);
            return $return;
         }
         try {
            $parsedParameters = [];
            foreach($parameters as $key => $param) {
               $tokens = token_get_all($param);
               foreach($tokens as $token)
                  if($token !== '.' && is_array($token))
                     if(!isset($validTokens[$token[0]]))
                        goto invalid2;
               $parsedParameters[$key] = @eval("return $param;");
            }
         }catch(Exception $e) {
            invalid2:
            # This is supposed to be the most likely case. We already have a
            # parsed, static path, but at least one of our parameters is
            # dynamic. We therefore do not need to do a chdir in the template,
            # as we already know the whole path.
            if($escape)
               $return = '<?php echo htmlspecialchars(Router::mapPathToURL(';
            else
               $return = '<?php echo Router::mapPathToURL(';
            $return .= var_export('/' . Router::normalize($path), true);
            if(!empty($parameters)) {
               $return .= ',[';
               foreach($parameters as $key => $param)
                  $return .= var_export($key, true) . '=>' . $param . ',';
               $return .= ']';
            }
            chdir($oldDir);
            return $escape ? ("$return), ENT_QUOTES, '" .
               addcslashes(Smarty::$_CHARSET, "\\'") . "');?>") : "$return);?>";
         }
         # Although this might not happen very often, we can also have urls that
         # are completely static and the url function is only used for
         # consistency and possibly differing subdomains. Now we know the path
         # and all parameters, so we can possibly do better caching.
         $urls = Router::mapPathToURL($path, $parsedParameters, $noRouting, true);
         if(!$noRouting) {
            # We cannot assure that our path will never change, so we cannot fully
            # cache the url.
            if($escape)
               $return = '<?php echo htmlspecialchars(Router::mapPathToURL(';
            else
               $return = '<?php echo Router::mapPathToURL(';
            $return .= var_export('/' . Router::normalize($path), true);
            if(!empty($parsedParameters))
               $return .= ',' . var_export($parsedParameters, true);
            chdir($oldDir);
            return $escape ? ("$return), ENT_QUOTES, '" .
               addcslashes(Smarty::$_CHARSET, "\\'") . "');?>") : "$return);?>";
         }
         # Caching gets even better. We now know that our routing will never
         # change, so we can really use the routing's result.
         chdir($oldDir); # This might be necessary for some bad guys which use
         # relative paths in templates without referencing them by "./".
         # $urls now is either a string (output directly) or an array
         if($urls === false) {
            Log::reportError(I18N::getInternalLanguage()->get('module.template.url',
                  [$path, $_current_file]));
            return '';
         }elseif(is_string($urls))
            if($escape)
               return htmlspecialchars($urls, ENT_QUOTES, Smarty::$_CHARSET);
            else
               return $urls;
         elseif($escape)
            return htmlspecialchars($urls[0], ENT_QUOTES, Smarty::$_CHARSET) .
               '<?php echo ServerEncoded;?>' .
               htmlspecialchars($urls[1], ENT_QUOTES, Smarty::$_CHARSET);
         else
            return "{$urls[0]}<?php echo Server;?>{$urls[1]}";
      }

   }

   # This class does mostly the same as the URL one, but takes special care of
   # language-dependent URLs
   class Smarty_Internal_Compile_LURL extends Smarty_Internal_CompileBase {

      public function compile($args, $compiler) {
         # copy pieces of the getAttribute() behavior. As we want to allow as
         # many named parameters and at least one unnamed, we cannot fully rely
         # on the given parent function.
         $parameters = [];
         $escape = $compiler->smarty->escape_html;
         foreach($args as $mixed) {
            # shorthand?
            if(!is_array($mixed)) {
               if(trim($mixed, '\'"') === 'nofilter')
                  $escape = false;
               elseif(!isset($path))
                  $path = $mixed;
               else
                  $compiler->trigger_template_error('too many shorthand attributes',
                     $compiler->lex->taglineno);
            }else{
               if(isset($mixed['nofilter']))
                  if(is_string($mixed['nofilter']) && in_array(trim($mixed['nofilter'],
                           '\'"'), ['true', 'false']))
                     $escape &= trim($mixed['nofilter'], '\'"') !== 'true';
                  elseif(is_numeric($mixed['nofilter']) && in_array($mixed['nofilter'],
                        [0, 1]))
                     $escape &= $mixed['nofilter'] != 1;
                  else
                     $compiler->trigger_template_error("illegal value of option flag \"nofilter\"",
                        $compiler->lex->taglineno);
               else{ // named attribute
                  $attr = each($mixed);
                  $parameters[$attr['key']] = $attr['value'];
               }
            }
         }
         unset($mixed);
         if(!isset($path))
            $compiler->trigger_template_error("missing url attribute",
               $compiler->lex->taglineno);
         $_current_file = $compiler->smarty->_current_file;
         $oldDir = getcwd();
         static $dirCaches = [];
         if(!isset($dirCaches[$_current_file])) {
            # We need to get the file name of the template that is actually
            # executed. $smarty->_current_file serves as a hint: It contains
            # either an absolute path (relative to /) or a relative path to cwd.
            # As the maximum level of "absoluteness" we work with in FIM is
            # CodeDir, we will first have to cut off everything that goes beyond
            # CodeDir
            static $cdl = null;
            if(!isset($cdl))
               $cdl = strlen(CodeDir);
            $currentFile = str_replace('\\', '/', $_current_file);
            if(substr($currentFile, 0, $cdl) === CodeDir)
               $currentFile = substr($currentFile, $cdl - 1);# Keep trailing /
            $currentFile = Router::normalize($currentFile, false, false);
            $dirCaches[$_current_file] = dirname($currentFile);
            # This is not very performant, but remember we only have to do it
            # once while the template is being compiled.
         }
         chdir($dirCaches[$_current_file]);
         # Now our cwd is assured to be the template's directory.
         # This function depends on the current language, so we can never output
         # static content. However, the path itself should be static, containing
         # the replacement <L>.
         try {
            $tokens = token_get_all("<?php $path");
            static $validTokens = [T_OPEN_TAG => true, T_CONSTANT_ENCAPSED_STRING => true,
               T_ENCAPSED_AND_WHITESPACE => true, T_WHITESPACE => true];
            foreach($tokens as $token)
               if($token !== '.' && is_array($token))
                  if(!isset($validTokens[$token[0]]))
                     goto invalid;
            $path = @eval("return $path;");
            if($path !== '' && $path[0] === '/')
               if(@$path[1] === '/')
                  $path = substr($path, 1);
               else
                  $path = '/' . ResourceDir . $path;
         }catch(Exception $e) {
            invalid:
            # This is supposed not to happen very often. While parameters will
            # most likely be dynamic, the path itself should be static; so let's
            # accept this performance drawback here.
            $return = '<?php $oldDir=getcwd();chdir(' .
               var_export($dirCaches[$_current_file], true) .
               ');echo ';
            if($escape)
               $return .= 'htmlspecialchars(';
            $return .= "Router::mapPathToURL(\$l->translatePath(fim\\parsePath($path))";
            if(!empty($parameters)) {
               $return .= ', [';
               foreach($parameters as $key => $param)
                  $return .= var_export($key, true) . "=>$param,";
               $return .= ']';
            }
            if($escape)
               $return .= "), ENT_QUOTES, '" . addcslashes(Smarty::$_CHARSET,
                     "\\'") . "'";
            $return .= ');chdir($oldDir);unset($oldDir);?>';
            chdir($oldDir);
            return $return;
         }
         try {
            $parsedParameters = [];
            foreach($parameters as $key => $param) {
               $tokens = token_get_all($param);
               foreach($tokens as $token)
                  if($token !== '.' && is_array($token))
                     if(!isset($validTokens[$token[0]]))
                        goto invalid2;
               $parsedParameters[$key] = @eval("return $param;");
            }
         }catch(Exception $e) {
            invalid2:
            # This is supposed to be the most likely case. We already have a
            # parsed, static path, but at least one of our parameters is
            # dynamic. We therefore do not need to do a chdir in the template,
            # as we already know the whole path.
            if($escape)
               $return = '<?php echo htmlspecialchars(Router::mapPathToURL($l->translatePath(';
            else
               $return = '<?php echo Router::mapPathToURL($l->translatePath(';
            $return .= var_export('/' . Router::normalize($path), true) . ')';
            if(!empty($parameters)) {
               $return .= ',[';
               foreach($parameters as $key => $param)
                  $return .= var_export($key, true) . '=>' . $param . ',';
               $return .= ']';
            }
            chdir($oldDir);
            return $escape ? ("$return), ENT_QUOTES, '" .
               addcslashes(Smarty::$_CHARSET, "\\'") . "');?>") : "$return);?>";
         }
         # We cannot apply routings now, as the translatePath function only
         # works for paths, not for urls. So everything is left to the runtime
         # without further caching.
         if($escape)
            $return = '<?php echo htmlspecialchars(Router::mapPathToURL($l->translatePath(';
         else
            $return = '<?php echo Router::mapPathToURL($l->translatePath(';
         $return .= var_export('/' . Router::normalize($path), true) . ')';
         if(!empty($parsedParameters))
            $return .= ',' . var_export($parsedParameters, true);
         chdir($oldDir);
         return $escape ? ("$return), ENT_QUOTES, '" .
            addcslashes(Smarty::$_CHARSET, "\\'") . "');?>") : "$return);?>";
      }

   }

   class Smarty_Internal_Compile_L extends Smarty_Internal_CompileBase {

      public function compile($args, $compiler) {
         # copy pieces of the getAttribute() behavior. As we want to allow as
         # many named or unnamed parameters (at least one), we cannot fully rely
         # on the given parent function.
         $_attr = [];
         $escape = $compiler->smarty->escape_html;
         foreach($args as $mixed) {
            # shorthand?
            if(!is_array($mixed)) {
               if(trim($mixed, '\'"') === 'nofilter')
                  $escape = false;
               else
                  $_attr[] = $mixed;
            }else{
               if(isset($mixed['nofilter']))
                  if(is_string($mixed['nofilter']) && in_array(trim($mixed['nofilter'],
                           '\'"'), ['true', 'false']))
                     $escape &= trim($mixed['nofilter'], '\'"') !== 'true';
                  elseif(is_numeric($mixed['nofilter']) && in_array($mixed['nofilter'],
                        [0, 1]))
                     $escape &= $mixed['nofilter'] != 1;
                  else
                     $compiler->trigger_template_error("illegal value of option flag \"nofilter\"",
                        $compiler->lex->taglineno);
               else // named attribute - but we actually don't care
                  $_attr[] = reset($mixed);
            }
         }
         if(empty($_attr))
            $compiler->trigger_template_error("missing language key attribute",
               $compiler->lex->taglineno);
         $str = "\$l->get({$_attr[0]}";
         array_shift($_attr);
         if(!empty($_attr))
            $str .= ', [' . implode(', ', $_attr) . ']';
         if($escape)
            return "<?php echo htmlspecialchars($str), ENT_QUOTES, '" . addcslashes(Smarty::$_CHARSET,
                  "\\'") . "');?>";
         else
            return "<?php echo $str); ?>";
      }

   }

}

namespace fim {

   /**
    * Internal function
    * @param string $path
    * @return string
    */
   function parsePath($path) {
      if($path !== '' && $path[0] === '/')
         if(@$path[1] === '/')
            return substr($path, 1);
         else
            return '/' . ResourceDir . $path;
      return $path;
   }

   /**
    * Internal function
    * @param string $in
    * @return string
    */
   function fimRegisterLanguage($in) {
      return '<?php if(!isset($l)) $l = I18N::getLanguage();?>' . $in;
   }

}