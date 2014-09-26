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

namespace fim {

   require FrameworkPath . 'smarty/Smarty.class.php';
   \Module::$smarty = new \Smarty();
   \Module::$smarty->setCompileDir(CodeDir . 'cache/templates');
   \Module::$smarty->setTemplateDir([]);
   \Module::$smarty->registerFilter('post', '\\fim\\initTemplate');

   /**
    * Internal function
    * @param string $in
    * @return string
    */
   function initTemplate($in) {
      return '<?php if(!isset($l)) $l = I18N::getLanguage();?>' . $in;
   }

   # We cannot simply define the functions smarty_compiler_url and
   # smarty_compiler_l which would be the "standard" way. But these functions
   # won't allow us to make use of the shorthand syntax; they would require the
   # template coder to give parameter names although this is really unnecessary.
   # Both functions are almost identical, so we use a trait to share them.

   trait urlCompiler {

      /**
       * Checks whether given PHP code consists only of static strings and
       * parses them.
       * @param string $str
       * @return false|string False if the input was dynamic
       */
      private function parseStaticString($str) {
         try {
            $tokens = token_get_all("<?php $str");
            static $validTokens = [T_OPEN_TAG => true, T_CONSTANT_ENCAPSED_STRING => true,
               T_ENCAPSED_AND_WHITESPACE => true, T_WHITESPACE => true];
            foreach($tokens as $token)
               if($token !== '.' && is_array($token))
                  if(!isset($validTokens[$token[0]]))
                     return false;
            return (string)@eval("return $str;");
         }catch(\Exception $e) {
            return false;
         }
      }

      private $parameters, $path, $escape;

      private function gatherAttributes($args, $compiler) {
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
               else{ # named attribute
                  $attr = each($mixed);
                  $parameters[$attr['key']] = $attr['value'];
               }
            }
         }
         if(!isset($path))
            $compiler->trigger_template_error("missing url attribute",
               $compiler->lex->taglineno);
         $this->parameters = $parameters;
         $this->path = $path;
         $this->escape = $escape;
      }

      private $cwd, $resourceType, $resourceName;

      private function checkDirectory($compiler) {
         $templateSource = $compiler->template->source;
         if($templateSource->type === 'fim')
            $cacheKey = $templateSource->filepath;
         else
            $cacheKey = "{$templateSource->type}:{$templateSource->filepath}";
         static $dirCaches = [];
         if(!isset($dirCaches[$cacheKey]))
         # We need to get the file name of the template that is actually
         # executed. Most likely, this would mean FIM resource style, but of
         # course any different resource type is also valid. But we can only
         # perform chdir for the resource types 'fim', 'file' and 'extends'.
         # The values of those however should have already been normalized.
            switch($templateSource->type) {
               case 'fim':
                  $dirCaches[$cacheKey] = dirname(\Router::convertFIMToFilesystem($templateSource->filepath,
                        false));
                  break;
               case 'file':
                  $dirCaches[$cacheKey] = dirname($templateSource->filepath);
                  break;
               case 'extends':
                  $templateSource = end($templateSource->components);
                  switch($templateSource->type) {
                     case 'fim':
                        $dirCaches[$cacheKey] = dirname(\Router::convertFIMToFilesystem($templateSource->filepath,
                              false));
                        break 2;
                     case 'file':
                        $dirCaches[$cacheKey] = dirname($templateSource->filepath);
                        break 2;
                  }
               default:
                  # This will not prevent us from fetching to corrent url; but
                  # it has to be given absolute; no relative ones are allowed.
                  $dirCaches[$cacheKey] = false;
            }
         $this->cwd = $dirCaches[$cacheKey];
         $this->resourceType = $templateSource->type;
         $this->resourceName = $cacheKey;
      }

      private function returnFullyDynamic($translate) {
         # This is supposed not to happen very often. While parameters will
         # most likely be dynamic, the path itself should be static; so let's
         # accept this performance drawback here.
         if($this->cwd === false) {
            $return = "<?php \$path={$this->path}; if(@\$path[0] !== '/') throw new FIMException(I18N::getInternalLanguage()->get(['module', 'template', 'urlRelative'], ['" .
               addcslashes($this->resourceName, "\\'") . "', \$path, '" .
               addcslashes($this->resourceType, "\\'") . "']));";
            $path = '$path';
         }else
            $return = '<?php $chdir=new fim\\chdirHelper(\'' . addcslashes($this->cwd,
                  "\\'") . "'); echo ";
         if($this->escape)
            $return .= 'htmlspecialchars(';
         $return .= $translate ?
            "Router::mapPathToURL(\$l->translatePath($path)" :
            "Router::mapPathToURL($path";
         if(!empty($this->parameters)) {
            $return .= ', [';
            foreach($this->parameters as $key => $param)
               $return .= var_export($key, true) . " => $param, ";
            $return .= ']';
         }
         if($this->escape)
            $return .= "), ENT_QUOTES, '" . addcslashes(\Smarty::$_CHARSET,
                  "\\'") . "'";
         $return .= ($this->cwd === false ? '); unset($path);?>' : '); unset($chdir);?>');
         return $return;
      }

      private function returnDynamicParameter($staticPath, $translate) {
         # This is supposed to be the most likely case. We already have a
         # parsed, static path, but at least one of our parameters is dynamic.
         # We therefore do not need to do a chdir in the template, as we already
         # know the whole path.
         if($this->escape)
            $return = $translate ? '<?php echo htmlspecialchars(Router::mapPathToURL($l->translatePath(\''
                  : '<?php echo htmlspecialchars(Router::mapPathToURL(\'';
         else
            $return = $translate ? '<?php echo Router::mapPathToURL($l->translatePath(\''
                  : '<?php echo Router::mapPathToURL(\'';
         $return .= addcslashes(\Router::normalizeFIM($staticPath), "\\'") . "'";
         if($translate)
            $return .= ')';
         if(!empty($this->parameters)) {
            $return .= ', [';
            foreach($this->parameters as $key => $param)
               $return .= var_export($key, true) . " => $param, ";
            $return .= ']';
         }
         return $this->escape ? ("$return), ENT_QUOTES, '" .
            addcslashes(\Smarty::$_CHARSET, "\\'") . "');?>") : "$return);?>";
      }

      private function returnStaticWithRouting($staticPath, $staticParameters,
         $translate) {
         # We cannot assure that our path will never change, so we cannot fully
         # cache the url.
         if($this->escape)
            $return = $translate ? '<?php echo htmlspecialchars(Router::mapPathToURL($l->translatePath(\''
                  : '<?php echo htmlspecialchars(Router::mapPathToURL(\'';
         else
            $return = $translate ? '<?php echo Router::mapPathToURL($l->translatePath(\''
                  : '<?php echo Router::mapPathToURL(\'';
         $return .= addcslashes(\Router::normalizeFIM($staticPath), "\\'") . "'";
         if($translate)
            $return .= ')';
         if(!empty($staticParameters))
            $return .= ', ' . var_export($staticParameters, true);
         return $this->escape ? ("$return), ENT_QUOTES, '" .
            addcslashes(\Smarty::$_CHARSET, "\\'") . "');?>") : "$return);?>";
      }

      private function returnFullyStatic($urls, $staticPath) {
         # $urls now is either a string (output directly) or an array - or the
         # url is completely inaccessible.
         if($urls === false) {
            \Log::reportError(\I18N::getInternalLanguage()->get(['module', 'template',
                  'url'], [$staticPath, $this->resourceName]));
            return '';
         }elseif(is_string($urls))
            if($this->escape)
               return htmlspecialchars($urls, ENT_QUOTES, \Smarty::$_CHARSET);
            else
               return $urls;
         elseif($this->escape)
            return htmlspecialchars($urls[0], ENT_QUOTES, \Smarty::$_CHARSET) .
               '<?php echo ServerEncoded;?>' .
               htmlspecialchars($urls[1], ENT_QUOTES, \Smarty::$_CHARSET);
         else
            return "{$urls[0]}<?php echo Server;?>{$urls[1]}";
      }

      protected function _compile($args, $compiler, $translate) {
         $this->gatherAttributes($args, $compiler);
         $this->checkDirectory($compiler);
         if($this->cwd !== false)
            $chdir = new chdirHelper($this->cwd);
         # Now our cwd is assured to be the template's directory.
         # We can only output the path and all parameters if they are not
         # composed but static strings. This is a very unlikely thing, but may
         # occur in static navigations. It's worth the effort to check. For
         # this, we have to parse the code and check for dynamic strings.
         # Everything that is not a string literal, a concatenation of constant
         # strings or a whitespace character is supposed to be dynamic.
         if(($staticPath = $this->parseStaticString($this->path)) === false)
            return $this->returnFullyDynamic($translate);
         if($this->cwd === false && @$staticPath[0] !== '/')
            throw new \FIMException(\I18N::getInternalLanguage()->get(['module',
               'template', 'urlRelative'],
               [$this->resourceName, $staticPath, $this->resourceType]));
         $staticParameters = [];
         foreach($this->parameters as $key => $param)
            if(($staticParameters[$key] = $this->parseStaticString($param)) === false)
               return $this->returnDynamicParameter($staticPath, $translate);
         if($translate)
         # We cannot apply routings now, as the translatePath function only
         # works for paths, not for urls. So everything is left to the
         # runtime without further caching.
            return $this->returnStaticWithRouting($staticPath,
                  $staticParameters, $translate);
         # Although this might not happen very often, we can also have urls that
         # are completely static and the url function is only used for
         # consistency and possibly differing subdomains. Now we know the path
         # and all parameters, so we might be able to do better caching.
         $urls = \Router::mapPathToURL($staticPath, $staticParameters,
               $noRouting, true);
         if($noRouting)
         # Caching gets even better. We now know that our routing will never
         # change, so we can really use the routing's result.
            return $this->returnFullyStatic($urls, $staticPath);
         else
            return $this->returnStaticWithRouting($staticPath,
                  $staticParameters, $translate);
      }

   }

}

namespace {

   class Smarty_Internal_Compile_URL extends Smarty_Internal_CompileBase {

      use fim\urlCompiler;

      public function compile($args, $compiler) {
         return $this->_compile($args, $compiler, false);
      }

   }

   class Smarty_Internal_Compile_LURL extends Smarty_Internal_CompileBase {

      use fim\urlCompiler;

      public function compile($args, $compiler) {
         return $this->_compile($args, $compiler, true);
      }

   }

   class Smarty_Internal_Compile_L extends Smarty_Internal_CompileBase {

      public function compile($args, $compiler) {
         # copy pieces of the getAttribute() behavior. As we want to allow as
         # many named or unnamed parameters (at least one), we cannot fully rely
         # on the given parent function.
         $_attr = [];
         $method = null;
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
               else{ # named attribute - but we actually don't care
                  $_attr[] = reset($mixed);
                  if($method === null && !method_exists('I18N',
                        $method = key($mixed)))
                     $method = false;
               }
            }
         }
         if(empty($_attr))
            $compiler->trigger_template_error("missing language key attribute",
               $compiler->lex->taglineno);
         if($method === null) {
            # Lazy get allows to enter all the attributes without need for array
            # syntax
            $str = "\$l->get({$_attr[0]}";
            array_shift($_attr);
            if(!empty($_attr))
               $str .= ', [' . implode(', ', $_attr) . ']';
         }else
         # But verbose syntax requires exact input of attributes
            $str = "\$l->$method(" . implode(', ', $_attr);
         if($escape)
            return "<?php echo htmlspecialchars($str), ENT_QUOTES, '" . addcslashes(Smarty::$_CHARSET,
                  "\\'") . "');?>";
         else
            return "<?php echo $str); ?>";
      }

   }

   # We want Smarty to be aware of FIM path identifiers, i.e. the maximum level
   # of absoluteness shall be CodeDir.

   class Smarty_Resource_FIM extends Smarty_Internal_Resource_File {

      /**
       * test is file exists and save timestamp
       *
       * @param  Smarty_Template_Source $source source object
       * @param  string                 $file   file name
       * @return bool                   true if file exists
       */
      protected function fileExists(Smarty_Template_Source $source, $file) {
         if(($file = Router::convertFIMToFilesystem($file, false)) === false)
            return $source->exists = $source->timestamp = false;
         $source->timestamp = is_file($file) ? @filemtime($file) : false;
         return $source->exists = !!$source->timestamp;
      }

      /**
       * build template filepath by traversing the template_dir array
       *
       * @param  Smarty_Template_Source   $source    source object
       * @param  Smarty_Internal_Template $_template template object
       * @return string                   fully qualified filepath
       * @throws SmartyException          if default template handler is registered but not callable
       */
      protected function buildFilepath(Smarty_Template_Source $source,
         Smarty_Internal_Template $_template = null) {
         $file = $source->name;
         # Go relative to a given template?
         if($file[0] !== '/' && $_template && $_template->parent instanceof Smarty_Internal_Template) {
            $parentSource = $_template->parent->source;
            switch($parentSource->type) {
               case 'fim':
                  $file = Router::normalizeFIM(dirname($parentSource->filepath) . "/$file");
                  break;
               case 'file':
                  $file = Router::normalizeFIM(Router::convertFilesystemToFIM(dirname($parentSource->filepath)) . "/$file");
                  break;
               case 'extends':
                  $lastComponent = end($parentSource->components);
                  switch($lastComponent->type) {
                     case 'fim':
                        $file = Router::normalizeFIM(dirname($lastComponent->filepath) . "/$file");
                        break;
                     case 'file':
                        $file = Router::normalizeFIM(Router::convertFilesystemToFIM(dirname($lastComponent->filepath)) . "/$file");
                        break;
                     default:
                        throw new SmartyException("Template '{$file}' cannot be relative to template of resource type 'extends' with last source type '{$lastComponent->type}'");
                  }
               default:
                  throw new SmartyException("Template '{$file}' cannot be relative to template of resource type '{$_template->parent->source->type}'");
            }
            return $this->fileExists($source, $file) ? $file : false;
         }
         $file = Router::normalizeFIM($file);
         return $this->fileExists($source, $file) ? $file : false;
         # If template_dir etc. shall be supported, use the file: protocol
      }

      /**
       * populate Source Object with timestamp and exists from Resource
       *
       * @param Smarty_Template_Source $source source object
       */
      public function populateTimestamp(Smarty_Template_Source $source) {
         $source->timestamp = @filemtime(Router::convertFIMToFilesystem($source->filepath,
                  false));
         $source->exists = !!$source->timestamp;
      }

      /**
       * Load template's source from file into current template object
       *
       * @param  Smarty_Template_Source $source source object
       * @return string                 template source
       * @throws SmartyException        if source cannot be loaded
       */
      public function getContent(Smarty_Template_Source $source) {
         if($source->timestamp)
            return file_get_contents(Router::convertFIMToFilesystem($source->filepath,
                  false));
         if($source instanceof Smarty_Config_Source)
            throw new SmartyException("Unable to read config {$source->type} '{$source->name}'");
         throw new SmartyException("Unable to read template {$source->type} '{$source->name}'");
      }

   }

   Module::$smarty->registerResource('fim', new Smarty_Resource_FIM());
   Module::$smarty->default_resource_type = 'fim';
}