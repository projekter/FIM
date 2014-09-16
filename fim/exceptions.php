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

class FIMException extends Exception {

}

class ConfigurationException extends FIMException {

}

class I18NException extends FIMException {

}

class RulesException extends FIMException {

}

class LoggerException extends FIMException {

}

class ResponseException extends FIMException {

}

class ModuleException extends FIMException {

}

class FIMErrorException extends ModuleException {

   /**
    * @var array
    */
   public $details;

   /**
    * Contains the exception that was created most recently
    * @var FIMErrorException|null
    */
   public static $last;

   /**
    * Determines whether the exception was raised directly by the programmer
    * via an error() call or by a FIM subroutine
    * @var boolean
    */
   public static $lastInternal = false;

   public function __construct($moduleFile, array $details, $internal = false) {
      parent::__construct($moduleFile, array_shift($details));
      $this->details = $details;
      self::$last = $this;
      self::$lastInternal = $internal;
   }

}

class ForwardException extends ModuleException {

}

class DatabaseException extends FIMException {

}

class TableException extends DatabaseException {

}

class PrimaryTableException extends TableException {

}

class SerializationException extends FIMException {

}

class DataHelperPrimitiveException extends PrimaryTableException {

}

class AutoboxingException extends FIMException {

}

class SessionException extends FIMException {

}

class FileUtilsException extends FIMException {

}
