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
    * This interface can be implemented so that any parameter string can be
    * automatically converted to an object or any required data structure
    */
   interface Autoboxable {

      /**
       * This function is called when there is user-entered data which shall be
       * transformed into usable data
       * @param string $data
       * @return static|mixed
       */
      public static function unbox($data);
   }

}

namespace fim {

   abstract class Autoboxing {

      public static $currentlyCalling = '';

      private final function __construct() {

      }

      /**
       * Calls a method using autoboxing
       * @param string|object $class
       * @param string $name
       * @param array $arguments You may pass optional arguments as an
       *    associative array to the function. The keys need to match the
       *    parameter names.
       * @param string $parentClass Reserved for internal use
       * @return mixed
       */
      public static function callMethod($class, $name, array $arguments = [],
         $parentClass = null) {
         $reflection = new \ReflectionClass(isset($parentClass) ? $parentClass : $class);
         if(!isset($parentClass))
            self::$currentlyCalling = $reflection->name;
         $cacheClass = '\\' . self::setupFileCache(new \ReflectionFile($reflection->getFileName())) . "\\{$reflection->name}";
         return $cacheClass::$name($arguments, $class);
      }

      /**
       * Calls a function using autoboxing
       * @param callable $function Any callable, but no internal PHP functions
       * @param array $arguments You may pass optional arguments as an
       *    associative array to the function. The keys need to match the
       *    parameter names.
       * @return mixed
       * @throws \AutoboxingException
       */
      public static function callFunction(callable $function,
         array $arguments = []) {
         if(is_array($function))
            if(is_string($function[0]))
               return self::callMethod($function[0], $function[1]);
            else
               return self::callMethod(get_class($function[0]), $function[1]);
         elseif(is_object($function) && !($function instanceof \Closure))
            return self::callMethod(get_class($function), '__invoke');
         $reflection = new \ReflectionFunction($function);
         if(!$reflection->isUserDefined())
            throw new \AutoboxingException(\I18N::getInternalLanguage()->get(['autoboxing',
               'internalFunction'], [(string)$function]));
         self::$currentlyCalling = $reflection->name;
         self::setupFileCache(new \ReflectionFile($reflection->getFileName()));
         $func = self::setupFunctionCache($reflection);
         if($reflection->isClosure())
            return $func($arguments, $function);
         else
            return $func($arguments);
      }

      /**
       * @param \ReflectionFile $file
       * @return string Namespace in which the cache classes are found
       */
      private static function setupFileCache(\ReflectionFile $file) {
         $absClassFile = $file->getFileName();
         $classFile = \Router::convertFilesystemToFIM($absClassFile);
         $hash = md5($classFile);
         $cacheFile = CodeDir . "cache/autoboxing/$hash";
         $closureFile = "{$cacheFile}_functions.php";
         $cacheFile .= '.php';
         $cacheNamespace = "cache\\autoboxing\\_$hash";
         if(in_array($cacheFile, get_included_files(), true))
            return $cacheNamespace;
         $currentTimestamp = filemtime($absClassFile);
         $cacheTimestamp = @filemtime($cacheFile);
         if($currentTimestamp !== $cacheTimestamp) {
            if(@file_put_contents($cacheFile,
                  self::generateAutoboxingCache($file, $cacheNamespace,
                     $classFile)) === false)
               throw new FIMInternalException(I18N::getInternalLanguage()->get(['autoboxing',
                  'cache', 'writeError', 'content'], [$classFile]));
            if(!touch($cacheFile, $currentTimestamp))
               throw new FIMInternalException(I18N::getInternalLanguage()->get(['autoboxing',
                  'cache', 'writeError', 'timestamp'], [$classFile]));
            @unlink($closureFile);
         }
         require_once $cacheFile;
         return $cacheNamespace;
      }

      private static function setupFunctionCache(\ReflectionFunction $function) {
         $classFile = \Router::convertFilesystemToFIM($function->getFileName());
         $hash = md5($classFile);
         $cacheFile = CodeDir . "cache/autoboxing/{$hash}_functions.php";
         $namespace = "cache\\autoboxing\\_{$hash}_functions";
         $cacheClass = "\\$namespace\\Autoboxing";
         if($function->isClosure()) {
            $startLine = $function->getStartLine();
            $endLine = $function->getEndLine();
            if($startLine !== $endLine)
               $functionName = 'Closure' . md5("$startLine-$endLine");
            else # Multiple closures may occur in one line. We need to distinguish between them, so try to get as much information as possible
               $functionName = 'Closure' . md5("$startLine:{$function->getNumberOfRequiredParameters()}:{$function->getNumberOfParameters()}:{$function->getDocComment()}");
         }else
            $functionName = 'Function' . md5($function->name);
         if(($include = (@include_once $cacheFile)) !== false) {
            if(method_exists($cacheClass, $functionName) || isset($cacheClass::$newMethods[$functionName]))
               return [$cacheClass, $functionName];
            $lock = new \Semaphore($cacheFile);
            $lock->lock();
            if(($cacheContent = @file_get_contents($cacheFile)) === false)
               throw new FIMInternalException(I18N::getInternalLanguage()->get(['autoboxing',
                  'cache', 'readError'], [$classFile]));
         }else{
            $lock = new \Semaphore($cacheFile);
            $lock->lock();
            $now = date('Y-m-d H:i:s');
            $cacheContent = <<<Cache
<?php

/* This is an autogenerated cache file. Do not change this file, it might be
 * overwritten at any time without notification.
 * The original file is $classFile.
 * Date of generation: $now.
 */

namespace $namespace;

abstract class Autoboxing {

   // < new content >

   public static \$newMethods = [];

   public static function __callStatic(\$name, \$arguments) {
      if(isset(self::\$newMethods[\$name])) {
         \$func = self::\$newMethods[\$name];
         if(isset(\$arguments[1]))
            return \$func(\$arguments[0], \$arguments[1]);
         elseif(isset(\$arguments[0]))
            return \$func(\$arguments[0]);
         else
            return \$func();
      }else
         # Sadly, we cannot trigger a fatal error, which would be the exact equivalent to this situation
         throw new \BadMethodCallException("Call to undefined method \$name");
   }

}
Cache;
         }
         $code = 'if(!empty($arguments))
         $arguments = \array_change_key_case($arguments, CASE_LOWER);
      ' . self::generateAutoboxingMethod($function, $classFile);
         $cacheContent = str_replace('#   // < new content >#',
            "   // < new content >

   public static function $functionName(array \$arguments" . ($function->isClosure()
                  ? ', $obj' : '') . ") {
      $code
   }", $cacheContent);
         if(@file_put_contents($cacheFile, $cacheContent) === false)
            throw new FIMInternalException(I18N::getInternalLanguage()->get(['autoboxing',
               'cache', 'writeError', 'content'], [$classFile]));
         $lock->unlock();
         if($include === false)
            include_once $cacheFile;
         else
            $cacheClass::$newMethods[$functionName] = create_function('$arguments' . ($function->isClosure()
                     ? ', $obj' : ''), $code);
         return [$cacheClass, $functionName];
      }

      private static function generateAutoboxingCache(\ReflectionFile $file,
         $namespace, $fileName) {
         if(!is_dir(CodeDir . 'cache/autoboxing') && !mkdir(CodeDir . 'cache/autoboxing',
               0700, true))
            throw new FIMInternalException(I18N::getInternalLanguage()->get(['autoboxing',
               'cache', 'writeError', 'directory']));
         $now = date('Y-m-d H:i:s');
         $cacheContent = <<<Cache
<?php

/* This is an autogenerated cache file. Do not change this file, it might be
 * overwritten at any time without notification.
 * The original file is $fileName.
 * Date of generation: $now.
 */

Cache;
         foreach($file->getClasses() as $class) {
            $ns = $class->getNamespaceName();
            if($ns !== '')
               $ns = "\\$ns";
            $cacheContent .= "

namespace $namespace$ns {
abstract class {$class->getShortName()} {

   public static function __callStatic(\$name, \$arguments) {";
            $parentClass = $class->getParentClass();
            if($parentClass === false || dirname($parentClass->getFileName()) === __DIR__)
               $cacheContent .= "
      # Sadly, we cannot trigger a fatal error, which would be the exact equivalent to this situation
      throw new \BadMethodCallException('Call to undefined method ' . \\fim\\Autoboxing::\$currentlyCalling . \"::\$name\");
   }";
            else
               $cacheContent .= "
      return \\fim\\Autoboxing::callMethod(\$arguments[1], \$name, \$arguments[0], '\\{$parentClass->name}');
   }";
            # We will add every method that was declared as public in the given
            # class. Methods in reserved namespace (beginning with __) will not
            # be added, as well as inherited (but not overwritten) methods.
            foreach($class->getMethods() as $method) {
               $name = $method->name;
               if(!$method->isPublic() || (isset($name[1]) && $name[0] === '_' && $name[1] === '_') || $method->getDeclaringClass()->name !== $class->name)
                  continue;
               $code = self::generateAutoboxingMethod($method, $fileName);
               $cacheContent .= "

   public static function $name(array \$arguments" . ($method->isStatic() ? '' : ', $obj') . ") {
      if(!empty(\$arguments))
         \$arguments = \\array_change_key_case(\$arguments, CASE_LOWER);
      $code
   }";
            }
            $cacheContent .= "

}
}";
         }
         return $cacheContent;
      }

      private static function generateAutoboxingMethod(\ReflectionFunctionAbstract $m,
         $fileName) {
         # We will do rather expensive parsing of the docblock. This will grant
         # maximum compatibility with the current work-in-progress standard and
         # allow programmers not to bother whether FIM will be able to parse
         # their docblock. However, we can only do that because of we're in
         # generating the cache (which will hopefully only occur once).
         $annotations = explode("\n",
            str_replace(["\r\n", "\r"], ["\n", "\n"],
               substr($m->getDocComment(), 3, -2)));
         foreach($annotations as $key => &$annotationLine)
            if($annotationLine === '' || (($annotationLine = trim($annotationLine)) === ''))
               unset($annotations[$key]);
            elseif($annotationLine[0] === '*')
               $annotationLine = ltrim($annotationLine, "*\t \0\x0B");
         $annotations = implode("\n", $annotations);
         preg_match_all('/^@param\s++(\S++)\s++\$(\S++)(?:\s++((?:(?!^@).)*))?+/sim',
            $annotations, $matches);
         if(!empty($matches)) {
            $types = array_combine($matches[2], $matches[1]);
            $values = array_combine($matches[2], array_map('trim', $matches[3]));
            unset($matches);
         }
         $parameters = [];
         $checks = '';
         foreach($m->getParameters() as $parameter) {
            $name = $parameter->name;
            if(isset($values[$name]) && $values[$name] !== '') {
               $lower = strtolower($name);
               $values[$name] = strtolower($values[$name]);
               if(substr($values[$name], 0, 6) === 'param|') {
                  $values[$name] = substr($values[$name], 6);
                  $param = "(isset(\$arguments['$lower']) || array_key_exists('$lower', \$arguments)) ? \$arguments['$lower'] : ";
               }else
                  $param = '';
               switch(strtolower($values[$name])) {
                  case 'param':
                     if($parameter->isOptional())
                        $value = $param . var_export($parameter->getDefaultValue(),
                              true);
                     else{
                        $value = "\$arguments['$lower']";
                        $checks .= "if(!isset(\$arguments['$lower']) && !array_key_exists('$lower', \$arguments))
         throw new \\BadMethodCallException('Missing argument \"$lower\" for ' . \\fim\\Autoboxing::\$currentlyCalling . '" .
                           ($m instanceof \ReflectionMethod ? "::{$m->getShortName()}"
                                 : '') . "()');
      ";
                     }
                     break;
                  case 'post':
                     $value = "$param\\Request::post('$name')";
                     break;
                  case 'session':
                     $value = "$param\\Session::get('$name')";
                     break;
                  case 'file':
                  case 'filestream':
                     $value = "$param\\Request::getFileResource('$name')";
                     break;
                  case 'filestring':
                     $value = "$param\\Request::getFileContent('$name')";
                     break;
                  case 'get':
                     $value = "$param\\Request::get('$name')";
                     break;
                  default:
                     if(preg_match('/(get|param|post|session|file|fileStream|fileString)\\s*+:\\s*+(.*+)/i',
                           $values[$name], $matches) === 1) {
                        $key = addcslashes($matches[2], "\\'");
                        $lower = strtolower($key);
                        if($param !== '')
                           $param = "(isset(\$arguments['$lower']) || (array_key_exists('$lower', \$arguments)) ? \$arguments['$lower'] : ";
                        switch(strtolower($matches[1])) {
                           case 'get':
                              $value = "$param\\Request::get('$key')";
                              break;
                           case 'param':
                              if($parameter->isOptional())
                                 $value = $param . var_export($parameter->getDefaultValue(),
                                       true);
                              else{
                                 $value = "\$arguments['$lower']";
                                 $checks .= "if(!isset(\$arguments['$lower']) && !array_key_exists('$lower', \$arguments))
         throw new \\BadMethodCallException('Missing argument \"$lower\" for ' . \\fim\\Autoboxing::\$currentlyCalling . '" .
                                    ($m instanceof \ReflectionMethod ? "::{$m->getShortName()}"
                                          : '') . "()');
      ";
                              }
                              break;
                           case 'post':
                              $value = "$param\\Request::post('$key')";
                              break;
                           case 'session':
                              $value = "$param\\Session::get('$key')";
                              break;
                           case 'file':
                           case 'filestream':
                              $value = "$param\\Request::getFileResource('$key')";
                              break;
                           case 'filestring':
                              $value = "$param\\Request::getFileContent('$key')";
                              break;
                        }
                     }else
                        $value = $values[$name];
               }
            }else
               $value = "(isset(\$arguments['$name']) || array_key_exists('$name', \$arguments)) ? \$arguments['$name'] : \\Request::get('$name')";
            $parseTypeHint = true;
            if(isset($types[$name])) {
               $type = strtolower($types[$name]);
               $typeArray = explode('|', $type);
               if(($allowNull = array_search('null', $typeArray, true)) !== false) {
                  unset($typeArray[$allowNull]);
                  $allowNull = true;
                  $type = implode('|', $typeArray);
               }
               unset($typeArray);
               static $casts = ['int' => 'int', 'integer' => 'int', 'bool' => 'bool',
                  'boolean' => 'bool', 'float' => 'double', 'double' => 'double',
                  'real' => 'double', 'string' => 'string', 'binary' => 'binary',
                  'object' => 'object', 'unset' => 'unset', 'array' => 'array'];
               if($type !== 'mixed')
                  if(isset($casts[$type])) {
                     if($allowNull)
                        $val = "(\$tmp !== null) ? ({$casts[$type]})\$tmp : null";
                     else
                        $val = "({$casts[$type]})($value)";
                     $parseTypeHint = false;
                  }elseif(substr($type, -2) === '[]') {
                     $type = substr($type, 0, -2);
                     if(preg_match('/^\(\s*+(null\s*+\|\s*+)?+([^|$)]++)\s*+(\|\s*+null\s*+)?+\)$/i',
                           $type, $matches) === 1) {
                        $type = $matches[2];
                        $allowInnerNull = !empty($matches[1]) || !empty($matches[3]);
                     }else
                        $allowInnerNull = false;
                     if(isset($casts[$type])) {
                        $checks .= "\$$name = (array)($value);
      foreach(\$$name as " . ($allowInnerNull ? '' : '$key => ') . "&\$val)
         " . (!$allowInnerNull ? "if(\$val === null)
            unset(\${$name}[\$key]);
         else
            " : '') . "\$val = ({$casts[$type]})\$val;
      unset(\$val);
      ";
                        if($allowNull)
                           $val = "\$$name ?: null";
                        else
                           $val = "\$$name";
                        $parseTypeHint = false;
                     }else
                        try {
                           $castClass = new \ReflectionClass($type);
                           if($castClass->implementsInterface('\\Autoboxable')) {
                              $checks .= "\$$name = (array)($value);
      foreach(\$$name as " . ($allowInnerNull ? '' : '$key => ') . "&\$val)
         " . ($allowInnerNull ?
                                    "\$val = \\{$castClass->name}::unbox(\$val);"
                                       :
                                    "if((\$val = \\{$castClass->name}::unbox(\$val)) === null)
            unset(\${$name}[\$key]);") . "
      unset(\$val);
      ";
                           }
                           if($allowNull)
                              $val = "\$$name ?: null";
                           else
                              $val = "\$$name";
                           $parseTypeHint = false;
                        }catch(\ReflectionException $e) {
                           \Log::reportInternalError(\I18N::getInternalLanguage()->get(['autoboxing',
                                 'reflectionException'],
                                 [$types[$name], $fileName, $m->name]));
                        }
                  }else
                     try {
                        $castClass = new \ReflectionClass($type);
                        if($castClass->implementsInterface('\\Autoboxable')) {
                           $val = "\\{$castClass->name}::unbox($value)";
                           $parseTypeHint = false;
                        }
                     }catch(\ReflectionException $e) {
                        \Log::reportInternalError(\I18N::getInternalLanguage()->get(['autoboxing',
                              'reflectionException'],
                              [$types[$name], $fileName, $m->name]));
                     }
            }
            $typeHint = $parameter->getClass();
            if($parseTypeHint) {
               if(($typeHint !== null) && ($typeHint->implementsInterface('\\Autoboxable')))
                  $val = "\\{$typeHint->name}::unbox($value)";
               elseif($parameter->isArray())
                  $val = "(array)($value)";
               elseif($parameter->isCallable() || !$parameter->canBePassedByValue())
                  return "throw new \\AutoboxingException(\\I18N::getInternalLanguage()->get(['autoboxing', 'invalidTypehint'], ['{$m->name}', '$fileName', '$name']));";
               else
                  $val = $value;
            }
            if($typeHint !== null) {
               if($parameter->allowsNull())
                  $checks .= "if(((\$$name = $val) !== null) && !(\$$name instanceof \\{$typeHint->name}))";
               else
                  $checks .= "if(!((\$$name = $val) instanceof \\{$typeHint->name}))";
               $checks .= "
         throw new \\FIMErrorException(\\I18N::getInternalLanguage()->get(['autoboxing', 'failed'], ['$fileName', '{$m->name}', '$name']), [404]);
      ";
               $parameters[] = "\$$name";
            }else
               $parameters[] = $val;
         }
         if($m instanceof \ReflectionMethod)
            $callSequence = $m->isStatic() ? "\\{$m->class}::{$m->name}" : "\$obj->{$m->name}";
         elseif($m->isClosure())
            $callSequence = '$obj';
         else
            $callSequence = "\\{$m->name}";
         return "{$checks}return $callSequence(" . implode(', ', $parameters) . ');';
      }

   }

}