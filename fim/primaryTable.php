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
    * This interface can be implemented by any object. This will allow the
    * object to be used as a column type in a primary table.
    */
   interface DataHelper {

      /**
       * This is called first when the data helper is assigned to a row object.
       * @param PrimaryTable $row The object that is associated with the helper
       * @param string $fieldName The name of the field the helper is assigned
       *    to
       * @param mixed $value The current value of the object
       * @param callable $change This callback function shall be called whenever
       *    the database representation has to be updated. It does not need any
       *    parameter.
       */
      public function bind(PrimaryTable $row, $fieldName, $value,
         callable $change);

      /**
       * This is called when the connection between the data helper and the row
       * object is about to be released.
       */
      public function unbind();

      /**
       * This is called whenever the actual database representation of the value
       * is required. This will also get called on unbound helpers.
       * @return mixed
       */
      public function getStorageValue();
   }

   /**
    * This is a base class that can be inherited from that provides a very
    * general implementation for the DataHelper interface.
    */
   abstract class DataHelperPrimitive implements DataHelper {

      /**
       * @var PrimaryTable
       */
      protected $row;

      /**
       * @var string
       */
      protected $fieldName;

      /**
       * @var mixed
       */
      protected $value;

      /**
       * @var callable
       */
      protected $changeCallback;

      /**
       * Binds the object to a table entry. Performs an unbind if necessary.
       * @param PrimaryTable $row
       * @param string $fieldName
       * @param mixed $value
       * @param callable $change
       */
      public function bind(PrimaryTable $row, $fieldName, $value,
         callable $change) {
         if(isset($this->changeCallback))
            $this->unbind();
         $this->row = $row;
         $this->fieldName = (string)$fieldName;
         $this->value = $value;
         $this->changeCallback = $change;
      }

      /**
       * Releases all mapping information between this object an its formerly
       * associated table entry.
       */
      public function unbind() {
         $this->row = $this->fieldName = $this->value = $this->changeCallback = null;
      }

      /**
       * Returns the plain value which is stored in the database. This is likely
       * to be overridden.
       * @return type
       */
      public function getStorageValue() {
         return $this->value;
      }

      /**
       * Call this function in order to store the given value in the database.
       * Requires bouding state
       * @param mixed $value
       * @throws DataHelperPrimitiveException
       */
      protected function setValue($value) {
         if(!isset($this->changeCallback))
            throw new DataHelperPrimitiveException(I18N::getInternalLanguage()->get(['primaryTable',
               'dataHelperUnbound']));
         $this->value = $value;
         $change = $this->changeCallback;
         $change();
      }

   }

   /**
    * This is the base class for all table rows which belong to PrimaryTables.
    * It should be inherited from instead of the plain Table
    * @internal Structure of required protected static $columns:
    *    Associative array: ('fieldName' => 'fieldType')<br />
    *    Any column that is a primary key has to be marked with * at the
    *    beginning of the fieldType; if it is an autoincrement value, it has to
    *    start with + instead - there might only be one autoincrement value per
    *    table!<br />
    *    Any column that might be null has to be marked with 0 at the beginning
    *    of the fieldType. Any null column cannot be a primary key.
    *    Valid types are:
    *    - string
    *    - int
    *    - double
    *    - bool
    *    - blob (in PHP as a large string)
    *    - a class name of a class that implements the DataHelper interface
    *      (null is not possible in this case)
    *    - any other value is treated a a class name of a class that implements
    *      the fimSerializable interface.
    */
   abstract class PrimaryTable extends Table implements Autoboxable {

      private static $rands = [];

      /**
       * Defines whether an object does not exist within the database
       * @var bool
       */
      protected $virtual = false;

      /**
       * Initializes the static storage. In this storage, all details as the table
       * name or other variables that would have been declared as static in the
       * actual subclass are put. Setting a value is only possible by overwriting
       * this initialization function.<br />
       * Do not forget to call the parent function!
       * @param array &$storage
       */
      protected static function initializeStaticStorage(array &$storage) {
         parent::initializeStaticStorage($storage);
         $cols = static::$columns;
         $autoIncrement = null;
         $nullable = $keys = $dataHelpers = [];
         foreach($cols as $name => &$type) {
            if($type === '' || !is_string($type))
               throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                  'definitionInvalid'], [$name, $storage['tableName']]));
            if(($type[0] === '*') || ($type[0] === '+')) {
               if($type[0] === '+')
                  if(isset($autoIncrement))
                     throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                        'definitionInvalid'], [$name, $storage['tableName']]));
                  else
                     $autoIncrement = $name;
               $keys[$name] = $type[0];
               $type = (string)substr($type, 1);
               if($type === '')
                  throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                     'definitionInvalid'], [$name, $storage['tableName']]));
            }elseif($type[0] === '0') {
               $nullable[$name] = true;
               $type = (string)substr($type, 1);
               if($type === '')
                  throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                     'definitionInvalid'], [$name, $storage['tableName']]));
            }
            if($type !== 'string' && $type !== 'int' && $type !== 'double' && $type !== 'bool' && $type !== 'blob') {
               try {
                  if($type[0] !== '\\') {
                     $namespace = explode('\\', get_called_class());
                     $namespace[count($namespace) - 1] = $type;
                     $type = implode('\\', $namespace);
                  }
                  $rc = new ReflectionClass($type);
                  if($rc->implementsInterface('DataHelper')) {
                     if(isset($nullable[$name]))
                        throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                           'definitionInvalid'], [$name, $storage['tableName']]));
                     $dataHelpers[$name] = true;
                  }elseif(!$rc->implementsInterface('fimSerializable'))
                     throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                        'definitionInvalid'], [$name, $storage['tableName']]));
               }catch(Exception $e) {
                  throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                     'definitionInvalid'], [$name, $storage['tableName']]),
                  $e->getCode(), $e);
               }
            }
         }
         $storage['cols'] = $cols;
         $storage['nullable'] = $nullable;
         $storage['keys'] = $keys;
         $storage['dataHelpers'] = $dataHelpers;
         $storage['autoIncrement'] = $autoIncrement;
      }

      private function update($name, $value, $fieldValue = null) {
         $data = static::getStaticStorage();
         if(!$this->virtual)
            Database::getActiveConnection()->update($data['tableName'],
               array($name => $value),
               array_intersect_key($this->fields, $data['keys']));
         if($fieldValue === null)
            $fieldValue = $value;
         if(($fieldValue !== $this->fields[$name]) && ($this->fields[$name] instanceof DataHelper)) {
            $this->fields[$name]->unbind();
            if(($this->fields[$name] = $fieldValue) instanceof DataHelper)
               fim\bindHelper($fieldValue, $this, $name,
                  $fieldValue->getStorageValue());
         }else
            $this->fields[$name] = ($fieldValue === null) ? $value : $fieldValue;
      }

      public final function __set($name, $value) {
         $data = static::getStaticStorage();
         if(!isset($this->fields))
            throw new TableException(I18N::getInternalLanguage()->get(['table', 'deleted'],
               [$data['tableName']]));
         if(isset($this->fields[$name]) || @array_key_exists($name,
               $this->fields)) {
            if(isset($data['keys'][$name]))
               throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                  'field', 'readOnly'], [$name, $data['tableName']]));
            $method = "set$name";
            if(method_exists($this, $method)) {
               if($this->$method($value) !== false)
                  $this->fields[$name] = $value;
            }elseif(isset($data['nullable'][$name]) && ($value === null))
               $this->update($name, $value);
            else
               switch($data['cols'][$name]) {
                  case 'string':
                     $this->update($name, (string)$value);
                     break;
                  case 'int':
                     $this->update($name, (int)$value);
                     break;
                  case 'double':
                     $this->update($name, (double)$value);
                     break;
                  case 'bool':
                     $this->update($name, (bool)$value);
                     break;
                  case 'blob':
                     $this->update($name, new Blob($value), (string)$value);
                     break;
                  default:
                     if(!((object)$value instanceof $data['cols'][$name]))
                        throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                           'field', 'invalid'], [$name, $data['tableName']]));
                     if(isset($data['dataHelpers'][$name]))
                        $this->update($name, $value->getStorageValue(), $value);
                     else
                        $this->update($name, fimSerialize($value), $value);
               }
            $method = "notify$name";
            if(method_exists($this, $method))
               $this->$method();
         }else
            throw new TableException(I18N::getInternalLanguage()->get(['table', 'field',
               'unknown'], [$data['tableName'], $name]));
      }

      public final function __call($name, $arguments) {
         if(!isset($this->fields))
            throw new TableException(I18N::getInternalLanguage()->get(['table', 'deleted'],
               [static::getStaticStorage('tableName')]));
         if(substr($name, 0, 3) === 'set') {
            $property = substr($name, 3);
            $property[0] = strtolower($property[0]);
            $this->$property = array_shift($arguments);
         }elseif(isset($this->fields[$name]) && ($this->fields[$name] instanceof DataHelper) && method_exists($this->fields[$name],
               '__invoke'))
         # Speed up call_user_func_array with this monster
            if(empty($arguments))
               $this->fields[$name]->__invoke();
            elseif(isset($arguments[2])) {
               if(isset($arguments[4])) {
                  if(isset($arguments[6])) {
                     if(isset($arguments[8]))
                        return call_user_func_array([$this->fields[$name], '__invoke'],
                           $arguments);
                     elseif(isset($arguments[7]))
                        return $this->fields[$name]->__invoke($arguments[0],
                              $arguments[1], $arguments[2], $arguments[3],
                              $arguments[4], $arguments[5], $arguments[6],
                              $arguments[7]);
                     else
                        return $this->fields[$name]->__invoke($arguments[0],
                              $arguments[1], $arguments[2], $arguments[3],
                              $arguments[4], $arguments[5], $arguments[6]);
                  }elseif(isset($arguments[5]))
                     return $this->fields[$name]->__invoke($arguments[0],
                           $arguments[1], $arguments[2], $arguments[3],
                           $arguments[4], $arguments[5]);
                  else
                     return $this->fields[$name]->__invoke($arguments[0],
                           $arguments[1], $arguments[2], $arguments[3],
                           $arguments[4]);
               }elseif(isset($arguments[3]))
                  return $this->fields[$name]->__invoke($arguments[0],
                        $arguments[1], $arguments[2], $arguments[3]);
               else
                  return $this->fields[$name]->__invoke($arguments[0],
                        $arguments[1], $arguments[2]);
            }elseif(isset($arguments[1]))
               return $this->fields[$name]->__invoke($arguments[0],
                     $arguments[1]);
            else
               return $this->fields[$name]->__invoke($arguments[0]);
         # End speed up
         else
            throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
               'callUnknown'], [$name, static::getStaticStorage('tableName')]));
      }

      private function getSingletonKey() {
         if(!isset($this->fields))
            throw new TableException(I18N::getInternalLanguage()->get(['table', 'deleted'],
               [static::getStaticStorage('tableName')]));
         return implode('|',
            array_intersect_key($this->fields, static::getStaticStorage('keys')));
      }

      /**
       * Returns a string representation of this object which is by default the
       * singleton key. Subclasses may overwrite this behavior.
       * @return string
       */
      public function __toString() {
         return $this->getSingletonKey();
      }

      public final function fimSerialize() {
         if(!isset($this->fields))
            return serialize(null);
         $data = static::getStaticStorage();
         if($this->virtual) {
            $fields = $this->fields;
            unset($fields[$data['autoIncrement']]);
         }else
            $fields = array_intersect_key($this->fields, $data['keys']);
         foreach($fields as $name => &$value)
            if(isset($data['dataHelpers'][$name]))
               $value = $value->getStorageValue();
         return fimSerialize($fields);
      }

      private function generateNoDBKey() {
         $data = static::getStaticStorage();
         if(!isset($data['autoIncrement']))
            throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
               'create', 'virtualNoAI'], [$data['tableName']]));
         do
            $this->fields[$data['autoIncrement']] = -rand();
         while(isset(self::$rands[$this->fields[$data['autoIncrement']]]));
         self::$rands[$this->fields[$data['autoIncrement']]] = $this;
      }

      public static final function fimUnserialize($serialized) {
         $fields = fimUnserialize($serialized);
         if(!isset($fields))
            return new static;# deleted entry, no fields
         if(!is_array($fields))
            throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
               'unserialize']));
         $data = static::getStaticStorage();
         if(count($fields + $data['keys']) === count($data['keys'])) {
            $return = static::translateStatement(Database::getActiveConnection()->simpleSelect($data['tableName'],
                     $fields, [], null, null, null, '', true));
            return reset($return);
         }elseif(count($fields + $data['cols']) === count($data['cols'])) {
            $return = new static;
            $return->virtual = true;
            $return->fields = $fields;
            unset($fields);
            $return->generateNoDBKey();
            foreach($data['dataHelpers'] as $key => $true) {
               $new = &$return->fields[$key];
               $val = $new;
               $new = new $data['cols'][$key];
               fim\bindHelper($new, $return, $key, $val);
            }
            return $return;
         }else
            throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
               'unserialize']));
      }

      /**
       * Tries to automatically unbox a given key into an instance of the given
       * class. The given key is expected to be the autoincrement key (if
       * available) or the only primary key. If none of them are available, null
       * is returned. This function shall be overwritten when a more specific
       * handling of unboxing is required.
       * @param string $key
       * @return static|null
       */
      public static function unbox($key) {
         $data = static::getStaticStorage();
         if(isset($data['autoIncrement']))
            return static::findOneBy([$data['autoIncrement'] => $key]);
         $keyCount = count($data['keys']);
         if($keyCount === 1)
            return static::findOneBy([key($data['keys']) => $key]);
         return null;
      }

      /**
       * Turns the "virtual" table entry into one that really is represented in the
       * database. The id will change.
       * @return static
       */
      protected function makeDBRepresentation() {
         if(!$this->virtual)
            return $this;# Already non-virtual
         $data = static::getStaticStorage();
         if(!isset($this->fields))
            throw new TableException(I18N::getInternalLanguage()->get(['table', 'deleted'],
               [$data['tableName']]));
         $args = $this->fields;
         unset(self::$rands[$this->fields[$data['autoIncrement']]],
            $args[$data['autoIncrement']]);
         foreach($args as $key => &$arg)
            if($arg !== null)
               switch($data['cols'][$key]) {
                  case 'string':
                  case 'int':
                  case 'double':
                  case 'bool':
                     break;
                  case 'blob':
                     $arg = new Blob($arg);
                     break;
                  default:
                     if($arg instanceof DataHelper)
                        $arg = $arg->getStorageValue();
                     else
                        $arg = fimSerialize($arg);
               }
         static::singleton($this->getSingletonKey(), null);
         $conn = Database::getActiveConnection();
         $conn->insert($data['tableName'], $args);
         if($data['autoIncrement'] !== null)
            $this->fields[$data['autoIncrement']] = $conn->lastInsertId($data['tableName'],
               $data['autoIncrement']);
         $this->virtual = false;
         return static::singleton($this->getSingletonKey(), $this);
      }

      /**
       * This function will delete the current entry from the database. Data
       * helpers will be unbound.
       * @return bool
       */
      protected function delete() {
         if(!isset($this->fields))
            return true;
         $data = static::getStaticStorage();
         if(!$this->virtual)
            if(!Database::getActiveConnection()->delete($data['tableName'],
                  array_intersect_key($this->fields, $data['keys'])))
               return false;
         foreach($data['dataHelpers'] as $key => $true)
            $this->fields[$key]->unbind();
         $this->singleton($this->getSingletonKey(), null);
         $this->fields = null;
         return true;
      }

      /**
       * This function creates a new table entry.
       * All the parameters have to be given in exactly the same order as declared.
       * An autoincrement key must not be given.
       * If there is one parameter more than expected and this last parameter is
       * true, a virtual entry will be created which has no database
       * representation. You may as well give false in order to specify database
       * representation; however, this is not necessary.
       * @return static An instance of the object that will be created.
       *    The singleton operation was already performed. A last set of e.g.
       *    callback functions may be performed.
       * @throws PrimaryTableException if the parameters didn't match the
       *    expectations.
       */
      protected static final function createNew() {
         $params = array_reverse(func_get_args());
         $data = static::getStaticStorage();
         $args = $data['cols'];
         $fields = [];
         if(isset($data['autoIncrement']))
            unset($args[$data['autoIncrement']]);
         foreach($args as $key => &$arg) {
            if(empty($params))
               throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                  'create', 'parameters'], [$data['tableName']]));
            $val = array_pop($params);
            if(!isset($val) && isset($data['nullable'][$key]))
               $arg = $fields[$key] = null;
            else
               switch($arg) {
                  case 'string':
                     $arg = $fields[$key] = (string)$val;
                     break;
                  case 'int':
                     $arg = $fields[$key] = (int)$val;
                     break;
                  case 'double':
                     $arg = $fields[$key] = (double)$val;
                     break;
                  case 'bool':
                     $arg = $fields[$key] = (bool)$val;
                     break;
                  case 'blob':
                     $fields[$key] = (string)$val;
                     $arg = new Blob($val);
                     break;
                  default:
                     if(!((object)$val instanceof $data['cols'][$key]))
                        throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                           'field', 'invalid'], [$key, $data['tableName']]));
                     $fields[$key] = $val;
                     if(isset($data['dataHelpers'][$key]))
                        $arg = $val->getStorageValue();
                     else
                        $arg = fimSerialize($val);
               }
         }
         $noDBRep = (bool)array_pop($params);
         if(!empty($params))
            throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
               'create', 'parameters'], [$data['tableName']]));
         if(!$noDBRep) {
            $conn = Database::getActiveConnection();
            if(!$conn->insert($data['tableName'], $args))
               throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                  'create', 'failed'], [$data['tableName']]));
            if($data['autoIncrement'] !== null)
               $fields[$data['autoIncrement']] = $conn->lastInsertId($data['tableName'],
                  $data['autoIncrement']);
         }
         $new = new static;
         $new->fields = $fields;
         if(($new->virtual = $noDBRep))
            $new->generateNoDBKey();
         static::singleton($new->getSingletonKey(), $new);
         foreach($data['dataHelpers'] as $key => $true)
            fim\bindHelper($fields[$key], $new, $key, $args[$key]);
         return $new;
      }

      /**
       * This function converts the results from a database statement into objects
       * of the current class.
       * @param PDOStatement $result
       * @return static[]
       * @throws PrimaryTableException if a database entry was invalid
       */
      protected static final function translateStatement(PDOStatement $result) {
         $return = [];
         $data = static::getStaticStorage();
         while(($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
            $key = implode('|', array_intersect_key($row, $data['keys']));
            if(($singleton = static::singleton($key)) !== null)
               $return[] = $singleton;
            else{
               $return[] = $obj = static::singleton($key, new static);
               foreach($data['cols'] as $key => $value)
                  if(!isset($row[$key]) && !array_key_exists($key, $row))
                     throw new PrimaryTableException(I18N::getInternalLanguage()->get(['primaryTable',
                        'translateFailed']));
                  elseif(isset($data['nullable'][$key]) && ($row[$key] === null))
                     $obj->fields[$key] = null;
                  else
                     switch($value) {
                        case 'string': case 'blob':
                           $obj->fields[$key] = (string)$row[$key];
                           break;
                        case 'int':
                           $obj->fields[$key] = (int)$row[$key];
                           break;
                        case 'double':
                           $obj->fields[$key] = (double)$row[$key];
                           break;
                        case 'bool':
                           $obj->fields[$key] = (bool)$row[$key];
                           break;
                        default:
                           if(!isset($data['dataHelpers'][$key]))
                              $obj->fields[$key] = unserialize($row[$key]);
                     }
               foreach($data['dataHelpers'] as $key => $true) {
                  $new = &$obj->fields[$key];
                  $new = new $data['cols'][$key];
                  fim\bindHelper($new, $obj, $key, $row[$key]);
               }
            }
         }
         return $return;
      }

      /**
       * Returns an array of objects of this class that fulfil given expectations
       * @param array $where (default empty)
       * @param string|null $orderBy A valid SQL order string or null
       * @return static[]
       */
      protected static final function findBy(array $where = [], $orderBy = null) {
         return static::translateStatement(Database::getActiveConnection()->simpleSelect(static::getStaticStorage('tableName'),
                  $where, [], $orderBy, null, null, '', true));
      }

      /**
       * Returns an array of objects of this class that fulfil given expectations
       * @param string $where String that will be assigned to the WHERE-clause.
       *    If you do not define this string, there will not be any WHERE
       *    restriction.
       * @param array $bind Numeric or associative array (depending on the
       *    WHERE-clause) that specifies all bindings that will be applied to the
       *    query. Mind the datatypes!
       * @param array $columns An array that defines which columns
       *    will be returned. If you do not define this array, all columns will be
       *    retrieved.
       * @param string $order_by (default null) Specify this string to sort the
       *    result in the desired way. Ensure that all column names are escaped
       *    properly. <code>'"lastname" ASC, "firstname" ASC'</code>
       * @param string $group_by_having (default null) Specify this string to
       *    append a <i>GROUP BY</i> section to the sql. Enure that all column
       *    names are escaped properly.
       *    <code>'"name" HAVING COUNT("name") > 5'</code>
       * @param string $limit (default null) Specify this string to append a
       *    <i>LIMIT</i> section to the sql. <code>'5,7'</code>
       * @param string $join (default '') Specify this string to append a
       *    <i>JOIN</i> section to the sql. Mind the fact that you need to enter
       *    the whole sql string, including the JOIN itself.
       *    <code>'JOIN "a" ON "a"."id" = "b"."a"</code>
       * @return static[]
       */
      protected static final function getBy($where = null, array $bind = null,
         array $columns = [], $order_by = null, $group_by_having = null,
         $limit = null, $join = '') {
         return static::translateStatement(Database::getActiveConnection()->select(static::getStaticStorage('tableName'),
                  $where, $bind, $columns, $order_by, $group_by_having, $limit,
                  $join, true));
      }

      /**
       * Returns the first object of this class that fulfils given expectations or
       * null if there is none.
       * @param array $where
       * @param string|null $orderBy A valid SQL order string or null
       * @return static|null
       */
      protected static final function findOneBy(array $where, $orderBy = null) {
         $return = static::translateStatement(Database::getActiveConnection()->simpleSelect(static::getStaticStorage('tableName'),
                  $where, [], $orderBy, null, '0, 1', '', true));
         return reset($return) ? : null;
      }

      /**
       * Returns the first object of this class that fulfils given expectations or
       * null if there is none
       * @param string $where String that will be assigned to the WHERE-clause.
       *    If you do not define this string, there will not be any WHERE
       *    restriction.
       * @param array $bind Numeric or associative array (depending on the
       *    WHERE-clause) that specifies all bindings that will be applied to the
       *    query. Mind the datatypes!
       * @param array $columns An array that defines which columns
       *    will be returned. If you do not define this array, all columns will be
       *    retrieved.
       * @param string $order_by (default null) Specify this string to sort the
       *    result in the desired way. Ensure that all column names are escaped
       *    properly. <code>'"lastname" ASC, "firstname" ASC'</code>
       * @param string $group_by_having (default null) Specify this string to
       *    append a <i>GROUP BY</i> section to the sql. Enure that all column
       *    names are escaped properly.
       *    <code>'"name" HAVING COUNT("name") > 5'</code>
       * @param string $limit (default null) Specify this string to append a
       *    <i>LIMIT</i> section to the sql. <code>'5,7'</code>
       * @param string $join (default '') Specify this string to append a
       *    <i>JOIN</i> section to the sql. Mind the fact that you need to enter
       *    the whole sql string, including the JOIN itself.
       *    <code>'JOIN "a" ON "a"."id" = "b"."a"</code>
       * @return static|null
       */
      protected static final function getOneBy($where = null,
         array $bind = null, array $columns = [], $order_by = null,
         $group_by_having = null, $limit = null, $join = '') {
         $return = static::getBy($where, $bind, $columns, $order_by,
               $group_by_having, $limit, $join);
         return reset($return) ? : null;
      }

   }

}

namespace fim {

   /**
    * Helper function that binds a data helper to the table object. Due to a
    * bug in PHP, this function cannot be declared within PrimaryTable, although
    * it would belong to it
    * @param \DataHelper $helper
    * @param \PrimaryTable $to
    * @param string $key
    * @param mixed $value
    */
   function bindHelper(\DataHelper $helper, \PrimaryTable $to, $key, $value) {
      $helper->bind($to, $key, $value,
         \Closure::bind(function() use ($key, $helper) {
            $data = static::getStaticStorage();
            if(!$this->virtual)
               \Database::getActiveConnection()->update($data['tableName'],
                  array($key => $helper->getStorageValue()),
                  array_intersect_key($this->fields, $data['keys']));
         }, $to, $to));
   }

}