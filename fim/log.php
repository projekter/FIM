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
 * This class provides all functionalities that allowed detailed logging within
 * the framework.
 */
abstract class Log {

   private static $systemErrors = null, $customErrors = null, $inMail = false;

   private final function __construct() {

   }

   private static function createLogHandles() {
      $dir = CodeDir . 'logs';
      if(!(is_dir($dir) || mkdir($dir, 0700)))
         throw new LoggerException(I18N::getInternalLanguage()->get('log.init.failed'));
      self::$systemErrors = fopen("$dir/error.log", 'a+');
      self::$customErrors = fopen("$dir/customError.log", 'a+');
   }

   /**
    * This function must not be called. It closes all open file handles
    */
   public static final function finalize() {
      $e = error_get_last();
      if(($e !== null) && in_array($e['type'],
            [E_ERROR, E_PARSE,
            E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING,
            E_STRICT])) {
         if(Config::get('production'))
            @ob_end_clean();
         self::errorHandler($e['type'], $e['message'], $e['file'], $e['line'],
            null, true);
      }
      if(self::$systemErrors !== null) {
         fclose(self::$systemErrors);
         fclose(self::$customErrors);
         self::$systemErrors = self::$customErrors = null;
      }
   }

   /**
    * Adds an error message to the error log of the framework
    * @param string $message
    * @param bool $noMail Do not send a mail notification for this error
    */
   public static final function reportError($message, $noMail = false) {
      if(self::$systemErrors === null)
         self::createLogHandles();
      if(self::$inMail) {
         @fwrite(self::$systemErrors,
               I18N::getInternalLanguage()->get('log.mail.failed', [$message]));
         return;
      }
      @fwrite(self::$systemErrors, "$message\r\n");
      # If an error occurs while sending a mail, we will get endless recursion.
      # So we introduce a global flag to prevent this.
      self::$inMail = true;
      try {
         if(!$noMail && (($mailError = Config::get('mailErrorsTo')) != false)) {
            # No strict compare. Configuration might not be set up
            $language = I18N::getInternalLanguage();
            if($language === null)
               Response::mail($mailError, 'Error on ' . Request::getFullURL(),
                  "Hint: This is an auto-generated message.\nAn internal error occured within FIM. Please contact the support if necessary. The following message was returned:\n====\n" .
                  $message . "\n====\nYou will also find this error logged in the \"logs\" directory in the file \"error.log\".\nThe content of the _SERVER variable was as follows:\n====" .
                  print_r($_SERVER, true) . "\n====The stack was as follows:\n====\n" . print_r(debug_backtrace(),
                     true));
            else
               Response::mail($mailError,
                  $language->get('log.mail.subject', [Request::getFullURL()]),
                  $language->get('log.mail.internal',
                     [$message, print_r($_SERVER, true), print_r(debug_backtrace(),
                        true)]));
         }
      }catch(Exception $e) {
         @fwrite(self::$systemErrors,
               I18N::getInternalLanguage()->get('log.mail.failed', [(string)$e]));
      }# finally (but we need to support PHP 5.4
      self::$inMail = false;
   }

   /**
    * Adds an error message to the custom error log
    * @param string $message
    * @param bool $noMail Do not send a mail notification for this error
    */
   public static final function reportCustomError($message, $noMail = false) {
      if(self::$systemErrors === null)
         self::createLogHandles();
      @fwrite(self::$customErrors, "$message\r\n");
      if(!$noMail && (($mailError = Config::get('mailErrorsTo')) !== false)) {
         $language = I18N::getInternalLanguage();
         Response::mail($mailError,
            $language->get('log.mail.subject', [Request::getFullURL()]),
            $language->get('log.mail.custom',
               [$message, print_r($_SERVER, true), print_r(debug_backtrace(),
                  true)]));
      }
   }

   /**
    * This is the framework error handler. This function should not be called -
    * it is called by PHP automatically.
    * @param int $errno
    * @param string $errstr
    * @param string $errfile
    * @param int $errline
    * @param array $errcontext
    * @return boolean Default error handling will take process if in production
    */
   public static final function errorHandler($errno, $errstr, $errfile = '',
      $errline = 0, array $errcontext = null, $shutdown = false) {
      if($errno === E_RECOVERABLE_ERROR) # Any recoverable error might be
      # catched. It is a bit odd that recoverable errors only trigger the
      # error handler, but do not enter the catch block.
         throw new ErrorException($errstr, $errno, E_ERROR, $errfile, $errline);
      if(($errno & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) === 0 && (error_reporting() & $errno) === 0)
         return false;# expected error that would not stop the script, prepended with @
      if(($errno === E_WARNING) && (substr($errstr, 0, 7) === 'require'))
         return true;# failed "require" first fires warning, then error - we do not need the warning
      $errid = date('Y-m-d H:i') . '-' . uniqid();
      static $errorCodes = [
         E_ERROR => 'E_ERROR',
         E_WARNING => 'E_WARNING',
         E_PARSE => 'E_PARSE',
         E_NOTICE => 'E_NOTICE',
         E_CORE_ERROR => 'E_CORE_ERROR',
         E_CORE_WARNING => 'E_CORE_WARNING',
         E_COMPILE_ERROR => 'E_COMPILE_ERROR',
         E_COMPILE_WARNING => 'E_COMPILE_WARNING',
         E_USER_ERROR => 'E_USER_ERROR',
         E_USER_WARNING => 'E_USER_WARNING',
         E_USER_NOTICE => 'E_USER_NOTICE',
         E_STRICT => 'E_STRICT',
         E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
         E_DEPRECATED => 'E_DEPRECATED',
         E_USER_DEPRECATED => 'E_USER_DEPRECATED'];
      $errtype = isset($errorCodes[$errno]) ? $errorCodes[$errno] : '(unknown error type)';
      self::reportError("[$errid] $errtype: $errstr\r\n   -> $errfile : $errline\r\n");
      if(Config::get('production')) {
         switch($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
               echo I18N::getInternalLanguage()->get('log.message', [$errid]);
               if(!$shutdown)
                  exit;
         }
         return true;
      }else
         return false;
   }

   /**
    * This is the framework exception handler. This function should not be
    * called - it is called by PHP automatically.
    * @param Exception $E
    */
   public static final function exceptionHandler(Exception $E) {
      $errid = date('Y-m-d H:i') . '-' . uniqid();
      if(($l = I18N::getInternalLanguage()) === null) {
         # Language is not set up. This is only possible in a very early state
         # of FIM's initialization.
         self::reportError("[$errid] An unhandled exception of type " .
            get_class($E) . " has occured. Details:\n$E");
         echo "An error has occured and the execution was halted. Please inform an administrator of error $errid.";
         return;
      }
      if($E instanceof FIMException)
         self::reportError("[$errid] " . $l->get('log.exception',
               [get_class($E), (string)$E]));
      else
         self::reportCustomError("[$errid] " . $l->get('log.exception',
               [get_class($E), (string)$E]));
      if(Config::get('production'))
         echo $l->get('log.message', [$errid]);
      else{
         echo (CLI ? '! ' : "
<br />
<font size='1'><table class='xdebug-error xe-uncaught-exception' dir='ltr' border='1' cellspacing='0' cellpadding='1'>
<tr><th align='left' bgcolor='#f57900' colspan=\"5\"><span style='background-color: #cc0000; color: #fce94f; font-size: x-large;'>( ! )</span> "),
         $l->get('log.exception', [get_class($E), $E->getMessage()]), CLI ? "\r\n"
               : '</th></tr>';
         $xvd = function_exists('xdebug_var_dump');
         do {
            $exceptionName = get_class($E);
            if(CLI)
               echo $exceptionName, ': ', $E->getMessage(), ' in ', $E->getFile(), ' on line ', $E->getLine(), "\r\n",
               "Call Stack\r\nFile:Line, Function, Args\r\n";
            else
               echo "\n<tr><th align='left' bgcolor='#f57900' colspan=\"5\"><span style='background-color: #cc0000; color: #fce94f; font-size: x-large;'>( ! )</span> ", $exceptionName, ': ', $E->getMessage(), ' in ', $E->getFile(), ' on line <i>', $E->getLine(), "</i></th></tr>
<tr><th align='left' bgcolor='#e9b96e' colspan='5'>Call Stack</th></tr>
<tr><th align='left' bgcolor='#eeeeec'>File</th><th align='left' bgcolor='#eeeeec'>Line</th><th align='left' bgcolor='#eeeeec'>Function</th><th align='left' bgcolor='#eeeeec'>Args</th>";
            foreach($E->getTrace() as $t) {
               if(CLI)
                  echo $t['file'], ':', $t['line'], ', ', @$t['class'], @$t['type'], $t['function'], ', ';
               else
                  echo "<tr><td bgcolor='#eeeeec' align='left'>", $t['file'], "</td><td bgcolor='#eeeeec' align='center'>", $t['line'], "</td><td bgcolor='#eeeeec' align='left'>", @$t['class'], @$t['type'], $t['function'], "</td><td title='", $t['file'], "' bgcolor='#eeeeec'>";
               try {
                  # Try to get the parameter's names
                  $args = (new ReflectionMethod(@$t['class'], $t['function']))->getParameters();
                  $argsSize = count($args);
                  $givenSize = count($t['args']);
                  if($argsSize < $givenSize) {
                     foreach($args as &$arg)
                        $arg = $arg->name;
                     $args += array_fill($argsSize, $givenSize - $argsSize, '?');
                  }elseif($givenSize < $argsSize) {
                     for($i = $givenSize; $i < $argsSize; ++$i)
                        $t['args'][$i] = $args[$i]->isOptional() ? $args[$i]->getDefaultValue()
                              : '-- WARNING: THIS PARAMETER IS REQUIRED, BUT MISSING IN THE CALL! --';
                     foreach($args as &$arg)
                        $arg = $arg->name;
                  }
                  $t['args'] = array_combine($args, $t['args']);
               }catch(Exception $E) {
                  # But if this is not possible, just leave them number-indexed
               }
               if(CLI) {
                  if($xvd)
                     echo strip_tags(var_dump($t['args'])), "\r\n";
                  else
                     echo var_dump($t['args']), "\r\n";
               }elseif($xvd)
                  xdebug_var_dump($t['args']);
               else{
                  echo '<pre>';
                  var_dump($t['args']);
                  echo '</pre>';
               }
               if(!CLI)
                  echo '</td></tr>';
            }
         }while(($E = $E->getPrevious()) !== null);
         if(!CLI)
            echo '
</table></font>';
      }
   }

}

set_error_handler(['Log', 'errorHandler']);
set_exception_handler(['Log', 'exceptionHandler']);
