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

# This file is a merge of Zend Framework's FileReflection capabilities.
# Some bugs in the original script were fixed.
# The original copyright and doccomment follow.
/**
 * Copyright (c) 2005-2014, Zend Technologies USA, Inc. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 * - Neither the name of Zend Technologies USA, Inc. nor the names of its
 *   contributors may be used to endorse or promote products derived from this
 *   software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace fim {

   if(@FrameworkPath === 'FrameworkPath')
      die('Please do not call this file directly.');

   class ReflectionFileHelper {

      /**
       * @var string
       */
      protected $file = null;

      /**
       * @var bool
       */
      protected $isScanned = false;

      /**
       * @var array
       */
      protected $tokens = [];

      /**
       * @var null
       */
      protected $docComment = null;

      /**
       * @var array
       */
      protected $infos = [];

      /**
       * @param string $file
       * @throws \InvalidArgumentException
       */
      public function __construct($file) {
         if((@include_once $file) === false)
            throw new \InvalidArgumentException("File \"$file\" not found or invalid");
         $this->tokens = token_get_all(file_get_contents($file));
      }

      /**
       * @return string
       */
      public function getFile() {
         return $this->file;
      }

      /**
       * Get doc comment
       *
       * @return string
       */
      public function getDocComment() {
         foreach($this->tokens as $token) {
            $type = $token[0];
            if(($type !== T_OPEN_TAG) && ($type !== T_WHITESPACE))
               if($type === T_DOC_COMMENT || $type === T_COMMENT) {
                  $this->docComment = $token[1];
                  return $this->docComment;
               }else # Only whitespace is allowed before file docblocks
                  return;
         }
      }

      /**
       * @return array
       */
      public function getNamespaces() {
         $this->scan();
         $namespaces = [];
         foreach($this->infos as $info)
            if($info['type'] === 'namespace')
               $namespaces[] = $info['namespace'];
         return $namespaces;
      }

      /**
       * @param null|string $namespace
       * @return array|null
       */
      public function getUses($namespace = null) {
         $this->scan();
         return $this->getUsesNoScan($namespace);
      }

      /**
       * @return array
       */
      public function getIncludes() {
         $this->scan();
      }

      /**
       * @return array
       */
      public function getClassNames() {
         $this->scan();
         $return = [];
         foreach($this->infos as $info)
            if($info['type'] === 'class')
               $return[] = $info['name'];
         return $return;
      }

      /**
       * @return \ReflectionClass[]
       */
      public function getClasses() {
         $this->scan();
         $return = [];
         foreach($this->infos as $info)
            if($info['type'] === 'class')
               $return[] = $this->getClass($info['name']);
         return $return;
      }

      /**
       * Return the class object from this scanner
       *
       * @param string|int $name
       * @return \ReflectionClass
       */
      public function getClass($name) {
         return new \ReflectionClass($name);
      }

      /**
       * @param string $className
       * @return bool|null|array
       */
      public function getClassNameInformation($className) {
         $this->scan();

         $classFound = false;
         foreach($this->infos as $info)
            if($info['type'] === 'class' && $info['name'] === $className) {
               $classFound = true;
               break;
            }

         if(!$classFound)
            return false;
         if(!isset($info))
            return null;

         return ['namespace' => $info['namespace'], 'uses' => $info['uses']];
      }

      /**
       * @return array
       */
      public function getFunctionNames() {
         $this->scan();
         $functionNames = [];
         foreach($this->infos as $info)
            if($info['type'] === 'function')
               $functionNames[] = $info['name'];
         return $functionNames;
      }

      /**
       * @return \ReflectionFunction[]
       */
      public function getFunctions() {
         $this->scan();
         $functions = [];
         # I'm sorry for closures, but we cannot include them. Even if it would
         # be possible to create a ReflectionFunction around this closure
         # (which would be hard, as we don't have a closure instance), this
         # would only get us top-level closures. So we would not have any
         # information about closures that occur within functions.
         foreach($this->infos as $info)
            if($info['type'] == 'function' && $info['name'] !== null)
               $functions[$info['name']] = new \ReflectionFunction($info['name']);
         return $functions;
      }

      /**
       * Scan
       *
       * @throws \RuntimeException
       */
      protected function scan() {
         if($this->isScanned)
            return;
         if(!$this->tokens)
            throw new \RuntimeException('No tokens were provided');

         # Variables & Setup
         $tokens = &$this->tokens;
         $infos = &$this->infos;
         $tokenIndex = null;
         $token = null;
         $tokenType = null;
         $tokenContent = null;
         $tokenLine = null;
         $namespace = null;
         $docCommentIndex = false;
         $infoIndex = 0;

         # Macros
         $MACRO_TOKEN_ADVANCE = function() use (&$tokens, &$tokenIndex, &$token, &$tokenType, &$tokenContent, &$tokenLine) {
            $tokenIndex = ($tokenIndex === null) ? 0 : $tokenIndex + 1;
            if(!isset($tokens[$tokenIndex])) {
               $token = false;
               $tokenContent = false;
               $tokenType = false;
               $tokenLine = false;
               return false;
            }
            if($tokens[$tokenIndex] === '"')
               do
                  ++$tokenIndex;
               while($tokens[$tokenIndex] !== '"');
            $token = $tokens[$tokenIndex];
            if(is_array($token))
               list($tokenType, $tokenContent, $tokenLine) = $token;
            else{
               $tokenType = null;
               $tokenContent = $token;
            }
            return $tokenIndex;
         };
         $MACRO_TOKEN_LOGICAL_START_INDEX = function() use (&$tokenIndex, &$docCommentIndex) {
            return ($docCommentIndex === false) ? $tokenIndex : $docCommentIndex;
         };
         $MACRO_DOC_COMMENT_START = function() use (&$tokenIndex, &$docCommentIndex) {
            $docCommentIndex = $tokenIndex;
            return $docCommentIndex;
         };
         $MACRO_DOC_COMMENT_VALIDATE = function() use (&$tokenType, &$docCommentIndex) {
            static $validTrailingTokens = [T_WHITESPACE => true, T_FINAL => true,
               T_ABSTRACT => true, T_INTERFACE => true, T_CLASS => true, T_FUNCTION => true];
            if($docCommentIndex !== false && !isset($validTrailingTokens[$tokenType]))
               $docCommentIndex = false;
            return $docCommentIndex;
         };
         $MACRO_INFO_ADVANCE = function() use (&$infoIndex, &$infos, &$tokenIndex, &$tokenLine) {
            $infos[$infoIndex]['tokenEnd'] = $tokenIndex;
            $infos[$infoIndex]['lineEnd'] = $tokenLine;
            ++$infoIndex;
            return $infoIndex;
         };

         # START FINITE STATE MACHINE FOR SCANNING TOKENS
         # Initialize token
         $MACRO_TOKEN_ADVANCE();

         SCANNER_TOP:

         if($token === false)
            goto SCANNER_END;
         # Validate current doc comment index
         $MACRO_DOC_COMMENT_VALIDATE();
         switch($tokenType) {
            case T_DOC_COMMENT:
               $MACRO_DOC_COMMENT_START();
               goto SCANNER_CONTINUE;
            case T_NAMESPACE:
               $infos[$infoIndex] = [
                  'type' => 'namespace',
                  'tokenStart' => $MACRO_TOKEN_LOGICAL_START_INDEX(),
                  'tokenEnd' => null,
                  'lineStart' => $token[2],
                  'lineEnd' => null,
                  'namespace' => null,
               ];
               # start processing with next token
               if($MACRO_TOKEN_ADVANCE() === false)
                  goto SCANNER_END;

               SCANNER_NAMESPACE_TOP:

               if($tokenType === null && $tokenContent === ';' || $tokenContent === '{')
                  goto SCANNER_NAMESPACE_END;
               if($tokenType === T_WHITESPACE)
                  goto SCANNER_NAMESPACE_CONTINUE;
               if($tokenType === T_NS_SEPARATOR || $tokenType === T_STRING)
                  $infos[$infoIndex]['namespace'] .= $tokenContent;

               SCANNER_NAMESPACE_CONTINUE:

               if($MACRO_TOKEN_ADVANCE() === false)
                  goto SCANNER_END;
               goto SCANNER_NAMESPACE_TOP;

               SCANNER_NAMESPACE_END:

               $namespace = $infos[$infoIndex]['namespace'];

               $MACRO_INFO_ADVANCE();
               goto SCANNER_CONTINUE;
            case T_USE:
               $infos[$infoIndex] = [
                  'type' => 'use',
                  'tokenStart' => $MACRO_TOKEN_LOGICAL_START_INDEX(),
                  'tokenEnd' => null,
                  'lineStart' => $tokens[$tokenIndex][2],
                  'lineEnd' => null,
                  'namespace' => $namespace,
                  'statements' => [0 => ['use' => null, 'as' => null]],
               ];

               $useStatementIndex = 0;
               $useAsContext = false;

               // start processing with next token
               if($MACRO_TOKEN_ADVANCE() === false)
                  goto SCANNER_END;

               SCANNER_USE_TOP:

               if($tokenType === null)
                  if($tokenContent === ';')
                     goto SCANNER_USE_END;
                  elseif($tokenContent === ',') {
                     $useAsContext = false;
                     ++$useStatementIndex;
                     $infos[$infoIndex]['statements'][$useStatementIndex] = [
                        'use' => null, 'as' => null];
                  }

               # ANALYZE
               if($tokenType !== null) {
                  if($tokenType === T_AS) {
                     $useAsContext = true;
                     goto SCANNER_USE_CONTINUE;
                  }
                  if($tokenType === T_NS_SEPARATOR || $tokenType === T_STRING)
                     if($useAsContext === false)
                        $infos[$infoIndex]['statements'][$useStatementIndex]['use'] .= $tokenContent;
                     else
                        $infos[$infoIndex]['statements'][$useStatementIndex]['as'] =
                           $tokenContent;
               }

               SCANNER_USE_CONTINUE:

               if($MACRO_TOKEN_ADVANCE() === false)
                  goto SCANNER_END;
               goto SCANNER_USE_TOP;

               SCANNER_USE_END:

               $MACRO_INFO_ADVANCE();
               goto SCANNER_CONTINUE;
            case T_INCLUDE:
            case T_INCLUDE_ONCE:
            case T_REQUIRE:
            case T_REQUIRE_ONCE:
               # Static for performance
               static $includeTypes = [
                  T_INCLUDE => 'include',
                  T_INCLUDE_ONCE => 'include_once',
                  T_REQUIRE => 'require',
                  T_REQUIRE_ONCE => 'require_once'
               ];
               $infos[$infoIndex] = [
                  'type' => 'include',
                  'tokenStart' => $MACRO_TOKEN_LOGICAL_START_INDEX(),
                  'tokenEnd' => null,
                  'lineStart' => $tokens[$tokenIndex][2],
                  'lineEnd' => null,
                  'includeType' => $includeTypes[$tokens[$tokenIndex][0]],
                  'path' => '',
               ];

               # start processing with next token
               if($MACRO_TOKEN_ADVANCE() === false)
                  goto SCANNER_END;

               SCANNER_INCLUDE_TOP:

               if($tokenType === null && $tokenContent === ';')
                  goto SCANNER_INCLUDE_END;

               $infos[$infoIndex]['path'] .= $tokenContent;

               SCANNER_INCLUDE_CONTINUE:

               if($MACRO_TOKEN_ADVANCE() === false)
                  goto SCANNER_END;
               goto SCANNER_INCLUDE_TOP;

               SCANNER_INCLUDE_END:

               $MACRO_INFO_ADVANCE();
               goto SCANNER_CONTINUE;
            case T_FUNCTION:
            case T_FINAL:
            case T_ABSTRACT:
            case T_CLASS:
            case T_INTERFACE:
            case T_TRAIT:
               $infos[$infoIndex] = [
                  'type' => ($tokenType === T_FUNCTION) ? 'function' : 'class',
                  'tokenStart' => $MACRO_TOKEN_LOGICAL_START_INDEX(),
                  'tokenEnd' => null,
                  'lineStart' => $tokens[$tokenIndex][2],
                  'lineEnd' => null,
                  'namespace' => $namespace,
                  'uses' => $this->getUsesNoScan($namespace),
                  'name' => null,
                  'shortName' => null,
               ];
               $classBraceCount = 0;
               # start processing with current token
               SCANNER_CLASS_TOP:

               # process the name
               if($infos[$infoIndex]['shortName'] == ''
                  && (($tokenType === T_CLASS || $tokenType === T_INTERFACE || $tokenType === T_TRAIT) && $infos[$infoIndex]['type'] === 'class'
                  || ($tokenType === T_FUNCTION && $infos[$infoIndex]['type'] === 'function'))
               ) {
                  if(($tokens[$tokenIndex + 1] !== '(') && ($tokens[$tokenIndex + 2] !== '(')) {
                     $infos[$infoIndex]['shortName'] = $tokens[$tokenIndex + 2][1];
                     $infos[$infoIndex]['name'] = (($namespace != null) ? "$namespace\\"
                              : '') . $infos[$infoIndex]['shortName'];
                  }#else closure, no name
               }

               if($tokenType === null) {
                  if($tokenContent === '{')
                     ++$classBraceCount;
                  if($tokenContent === '}') {
                     --$classBraceCount;
                     if($classBraceCount === 0)
                        goto SCANNER_CLASS_END;
                  }
               }

               SCANNER_CLASS_CONTINUE:

               if($MACRO_TOKEN_ADVANCE() === false)
                  goto SCANNER_END;
               goto SCANNER_CLASS_TOP;

               SCANNER_CLASS_END:

               $MACRO_INFO_ADVANCE();
               goto SCANNER_CONTINUE;
         }

         SCANNER_CONTINUE:

         if($MACRO_TOKEN_ADVANCE() === false)
            goto SCANNER_END;
         goto SCANNER_TOP;

         SCANNER_END:

         # END FINITE STATE MACHINE FOR SCANNING TOKENS
         $this->isScanned = true;
      }

      /**
       * Check for namespace
       *
       * @param string $namespace
       * @return bool
       */
      public function hasNamespace($namespace) {
         $this->scan();

         foreach($this->infos as $info)
            if($info['type'] === 'namespace' && $info['namespace'] === $namespace)
               return true;
         return false;
      }

      /**
       * @param string $namespace
       * @return null|array
       * @throws \InvalidArgumentException
       */
      protected function getUsesNoScan($namespace) {
         $namespaces = [];
         foreach($this->infos as $info)
            if($info['type'] === 'namespace')
               $namespaces[] = $info['namespace'];
         if($namespace === null)
            $namespace = array_shift($namespaces);
         elseif(!is_string($namespace))
            throw new \InvalidArgumentException('Invalid namespace provided');
         elseif(!in_array($namespace, $namespaces))
            return null;
         $uses = [];
         foreach($this->infos as $info) {
            if($info['type'] !== 'use')
               continue;
            foreach($info['statements'] as $statement)
               if($info['namespace'] === $namespace)
                  $uses[] = $statement;
         }
         return $uses;
      }

   }

   class ReflectionFileCache {

      /**
       * @var array
       */
      protected static $cache = [];

      /**
       * @var null|FileScanner
       */
      protected $fileScanner = null;

      /**
       * @param string $file
       * @throws \InvalidArgumentException
       */
      public function __construct($file) {
         if(!file_exists($file))
            throw new \InvalidArgumentException("File \"$file\" not found");
         $file = realpath($file);
         $cacheId = hash('md5', $file);
         if(isset(static::$cache[$cacheId]))
            $this->fileScanner = self::$cache[$cacheId];
         else{
            $this->fileScanner = new ReflectionFileHelper($file);
            self::$cache[$cacheId] = $this->fileScanner;
         }
      }

      /**
       * @return void
       */
      public static function clearCache() {
         self::$cache = [];
      }

      /**
       * @return AnnotationManager
       */
      public function getAnnotationManager() {
         return $this->fileScanner->getAnnotationManager();
      }

      /**
       * @return array|null|string
       */
      public function getFile() {
         return $this->fileScanner->getFile();
      }

      /**
       * @return null|string
       */
      public function getDocComment() {
         return $this->fileScanner->getDocComment();
      }

      /**
       * @return array
       */
      public function getNamespaces() {
         return $this->fileScanner->getNamespaces();
      }

      /**
       * @param null|string $namespace
       * @return array|null
       */
      public function getUses($namespace = null) {
         return $this->fileScanner->getUses($namespace);
      }

      /**
       * @return array
       */
      public function getIncludes() {
         return $this->fileScanner->getIncludes();
      }

      /**
       * @return array
       */
      public function getClassNames() {
         return $this->fileScanner->getClassNames();
      }

      /**
       * @return array
       */
      public function getClasses() {
         return $this->fileScanner->getClasses();
      }

      /**
       * @param int|string $className
       * @return ClassScanner
       */
      public function getClass($className) {
         return $this->fileScanner->getClass($className);
      }

      /**
       * @param string $className
       * @return bool|null|NameInformation
       */
      public function getClassNameInformation($className) {
         return $this->fileScanner->getClassNameInformation($className);
      }

      /**
       * @return array
       */
      public function getFunctionNames() {
         return $this->fileScanner->getFunctionNames();
      }

      /**
       * @return array
       */
      public function getFunctions() {
         return $this->fileScanner->getFunctions();
      }

   }

}

namespace {

   class ReflectionFile {

      /**
       * @var string
       */
      protected $filename = null;

      /**
       * @var string
       */
      protected $docComment = null;

      /**
       * @var string
       */
      protected $namespaces = [];

      /**
       * @var array
       */
      protected $uses = [];

      /**
       * @var array
       */
      protected $includes = [];

      /**
       * @var ReflectionClass[]
       */
      protected $classes = [];

      /**
       * @var ReflectionFunction[]
       */
      protected $functions = [];

      /**
       * @var string
       */
      protected $contents = null;

      /**
       * @param string $filename
       */
      public function __construct($filename) {
         $this->filename = $filename;
         $this->reflect();
      }

      /**
       * Return the file name of the reflected file
       *
       * @return string
       */
      public function getFileName() {
         return $this->filename;
      }

      /**
       * @return string
       */
      public function getDocComment() {
         return $this->docComment;
      }

      /**
       * @return array
       */
      public function getNamespaces() {
         return $this->namespaces;
      }

      /**
       * @return string
       */
      public function getNamespace() {
         if(empty($this->namespaces))
            return null;
         return $this->namespaces[0];
      }

      /**
       * @return array
       */
      public function getUses() {
         return $this->uses;
      }

      /**
       * @return array
       */
      public function getRequiredFiles() {
         return $this->includes;
      }

      /**
       * Return the reflection classes of the classes found inside this file
       *
       * @return ReflectionClass[]
       */
      public function getClasses() {
         return $this->classes;
      }

      /**
       * Return the names of the classes found inside this file
       *
       * @return string[]
       */
      public function getClassNames() {
         return array_keys($this->classes);
      }

      /**
       * Return the reflection functions of the functions found inside this file
       *
       * @return ReflectionFunction[]
       */
      public function getFunctions() {
         return $this->functions;
      }

      /**
       * Return the names of the functions found inside this file
       *
       * @return string[]
       */
      public function getFunctionNames() {
         return array_keys($this->functions);
      }

      /**
       * Retrieve the reflection class of a given class found in this file
       *
       * @param null|string $name
       * @return ReflectionClass|null
       * @throws \InvalidArgumentException for invalid class name or invalid reflection class
       */
      public function getClass($name = null) {
         if($name === null)
            return reset($this->classes) ? : null;
         if(isset($this->classes[$name]))
            return $this->classes[$name];
         throw new \InvalidArgumentException("Class by name $name not found.");
      }

      /**
       * Return the full contents of file
       *
       * @return string
       */
      public function getContents() {
         if($this->contents !== null)
            return $this->contents;
         return $this->contents = file_get_contents($this->filename);
      }

      /**
       * This method does the work of "reflecting" the file
       *
       * Uses fim\ReflectionFileHelper to gather file information
       *
       * @return void
       */
      protected function reflect() {
         $scanner = new \fim\ReflectionFileCache($this->filename);
         $this->docComment = $scanner->getDocComment();
         $this->includes = $scanner->getIncludes();
         $this->classes = array_combine($scanner->getClassNames(),
            $scanner->getClasses());
         $this->namespaces = $scanner->getNamespaces();
         $this->functions = $scanner->getFunctions();
         foreach($this->namespaces as $namespace)
            $this->uses[$namespace] = $scanner->getUses($namespace);
      }

   }

}