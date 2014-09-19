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
 * This is the base class for all tables that wish to make use of FIM's
 * integrated database capabilities. Instances of this class represent a single
 * row within a table. It should not be inherited from; make use of the more
 * specific PrimaryTable class.
 */
abstract class Table implements fimSerializable {

   /**
    * @var array
    */
   protected $fields;
   private static $staticStorage = [];

   protected function __construct() {
      if(!isset(static::$columns))
         throw new TableException(I18N::getInternalLanguage()->get(['table', 'definitionInvalid'],
            [get_class($this)]));
   }

   public final function __get($name) {
      if(!isset($this->fields))
         throw new TableException(I18N::getInternalLanguage()->get(['table', 'deleted'],
            [static::getStaticStorage('tableName')]));
      if(isset($this->fields[$name]) || @array_key_exists($name, $this->fields))
         return $this->fields[$name];
      else
         throw new TableException(I18N::getInternalLanguage()->get(['table', 'field', 'unknown'],
            [static::getStaticStorage('tableName'), $name]));
   }

   public function __set($name, $value) {
      if(!isset($this->fields))
         throw new TableException(I18N::getInternalLanguage()->get(['table', 'deleted'],
            [static::getStaticStorage('tableName')]));
      if(isset($this->fields[$name]) || @array_key_exists($name, $this->fields)) {
         $method = "set$name";
         if(method_exists($this, $method)) {
            if($this->$method($value) !== false)
               $this->fields[$name] = $value;
         }else
            throw new TableException(I18N::getInternalLanguage()->get(['table', 'field', 'readOnly'],
               [$name, static::getStaticStorage('tableName')]));
      }else
         throw new TableException(I18N::getInternalLanguage()->get(['table', 'field', 'unknown'],
            [static::getStaticStorage('tableName'), $name]));
   }

   public final function __isset($name) {
      if(!isset($this->fields))
         throw new TableException(I18N::getInternalLanguage()->get(['table', 'deleted'],
            [static::getStaticStorage('tableName')]));
      return isset($this->fields[$name]);
   }

   public final function __sleep() {
      throw new TableException(I18N::getInternalLanguage()->get(['table', 'serialize']));
   }

   public final function __wakeup() {
      throw new TableException(I18N::getInternalLanguage()->get(['table', 'unserialize']));
   }

   /**
    * Provides singleton handling features.
    * @param string $key The key of the singleton
    * @param mixed $action Set this to 0 (default) to get the value if existing.
    *    Set this to null to unset the value and use an object value to set it.
    * @return static
    */
   protected static final function singleton($key, $action = 0) {
      static $singletons = [];
      if($action === 0)
         return isset($singletons[$key]) ? $singletons[$key] : null;
      elseif($action === null)
         unset($singletons[$key]);
      else
         return $singletons[$key] = $action;
   }

   /**
    * Retrieves the number of rows in the table
    * @return int
    */
   public static final function count() {
      return (int)Database::getActiveConnection()->getConnection()
            ->query('SELECT COUNT(*) FROM "' . static::getStaticStorage('tableName') . '"')
            ->fetchColumn();
   }

   /**
    * Initializes the static storage. In this storage, all details as the table
    * name or other variables that would have been declared as static in the
    * actual subclass are put. Setting a value is only possible by overwriting
    * this initialization function
    * @param array &$storage
    */
   protected static function initializeStaticStorage(array &$storage) {
      $tableNamespace = explode('\\', get_called_class());
      # We could do a check of databases here. Maybe this would improve
      # coding - but as this value is cached, it might get invalid
      # afterwards. So instead of relying on a check that is not always
      # triggered, let's not check at all.
      $storage['tableName'] = array_pop($tableNamespace);
   }

   /**
    * Gets a static value that is different for each subclass without having to
    * define this field in the subclass itself.
    * @param string|null $singleValue
    * @return array
    */
   protected static final function getStaticStorage($singleValue = null) {
      static $storage = null;
      if($storage === null) {
         # Access by reference will suppress the error if the key does not exist
         $storage = &self::$staticStorage[get_called_class()];
         if($storage === null) {
            $storage = [];
            static::initializeStaticStorage($storage);
         }
      }
      if($singleValue !== null)
         return $storage[$singleValue];
      else
         return $storage;
   }

}
