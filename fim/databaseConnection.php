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
 * Use this class in order to bind blob data to a statement
 */
final class Blob {

   public $data;

   public function __construct($data) {
      $this->data = (string)$data;
   }

}

final class DatabaseConnection {

   // <editor-fold desc="Handling a connection" defaultstate="collapsed">
   private static $defaults = ['recursive' => true, 'driver' => null,
      'host' => null, 'port' => null, 'database' => null, 'user' => null,
      'password' => null, 'dsn' => null, 'persistent' => false];
   private $data = [];
   private $computedValues = [];
   private $connectionFile;
   private $initStmt;
   private $parent;
   private $driver;

   /**
    * @var PDO
    */
   private $connection;

   public final function __construct(array $data, $connectionFile, $parentFile) {
      $this->connectionFile = $connectionFile;
      if($parentFile !== null) {
         static $loadConnectionFile = null;
         if($loadConnectionFile === null)
            $loadConnectionFile = Closure::bind(function($fileName) {
                  return self::loadConnectionFile($fileName);
               }, null, 'Database');
         $this->parent = $loadConnectionFile($parentFile);
         if(isset($this->parent)) # Maybe the file contained "delete"...
            $parentValues = $this->parent->recursiveValues();
         else
            $parentValues = self::$defaults;
      }else
         $parentValues = self::$defaults;
      $this->data = array_merge($parentValues, $data);
      $this->compute();
   }

   private final function recursiveValues() {
      return $this->data['recursive'] ? $this->data :
         (isset($this->parent) ? $this->parent->recursiveValues() : self::$defaults);
   }

   private final function compute() {
      $data = $this->data;
      $computed = &$this->computedValues;
      $computed['options'] = $data['persistent'] ? [PDO::ATTR_PERSISTENT => true]
            : [];
      if(!empty($data['dsn'])) {
         $computed['dsn'] = $data['dsn'];
         if(preg_match('#^[^:]+#', $data['dsn'], $matches) !== 1)
            throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
               'data', 'invalid'], [$this->connectionFile]));
         switch($this->driver = strtolower(trim($matches[0]))) {
            case 'mysql':
               $computed['options'][PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET LOCAL SQL_MODE = ANSI_QUOTES';
               break;
            case 'firebird':
               $this->initStmt = 'SET SQL DIALECT 3';
               break;
            case 'sqlsrv':
            case 'sybase':
            case 'mssql':
            case 'dblib':
               $this->initStmt = 'SET QUOTED_IDENTIFIED ON';
               break;
         }
      }else{
         switch($data['driver']) {
            case 'mysql':
               if(empty($data['host']))
                  $data['host'] = '127.0.0.1';
               if(empty($data['port']))
                  $data['port'] = 3306;
               elseif($data['host'] === 'localhost')
                  $data['host'] = '127.0.0.1';# fix a likely bug
               $dsn = "mysql:charset=utf8;host={$data['host']};port={$data['port']};dbname={$data['database']}";
               $computed['options'][PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET LOCAL SQL_MODE = ANSI_QUOTES';
               break;
            case 'firebird':
               if(empty($data['database']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['Firebird', 'database', $this->connectionFile]));
               $dsn = 'firebird:charset=UTF-8;dbname=';
               if(!empty($data['host'])) {
                  $dsn .= $data['host'];
                  if(!empty($data['port']))
                     $dsn .= "/{$data['port']}";
                  $dsn .= ':';
               }elseif(!empty($data['port']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'wrong', 'firebird'], [$this->connectionFile]));
               $dsn .= $data['database'];
               $this->initStmt = 'SET SQL DIALECT 3';
               break;
            case 'pgsql':
               if(empty($data['database']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['PostgreSQL', 'database', $this->connectionFile]));
               $dsn = "pgsql:host={$data['host']};" .
                  (empty($data['port']) ? '' : "port={$data['port']};") .
                  "dbname={$data['database']}";
               break;
            case 'sqlite':
               if(empty($data['database']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['SQLite', 'database', $this->connectionFile]));
               $dsn = "sqlite:{$data['database']}";
               break;
            case 'odbc':
               throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                  'data', 'wrong', 'odbc'], [$this->connectionFile]));
            case 'oracle':
               if(empty($data['database']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['Oracle', 'database', $this->connectionFile]));
               $dsn = 'oci:charset=utf8;dbname=';
               if(!empty($data['host'])) {
                  $dsn .= "//{$data['host']}";
                  if(!empty($data['port']))
                     $dsn .= ":{$data['port']}";
                  $dsn .= '/';
               }elseif(!empty($data['port']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'wrong', 'oracle'], [$this->connectionFile]));
               $dsn .= $data['database'];
               break;
            case 'sqlsrv':
               if(empty($data['host']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['MSSQL/Azure', 'host', $this->connectionFile]));
               if(!empty($data['port']))
                  $data['port'] = ",{$data['port']}";
               if(empty($data['database']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['MSSQL/Azure', 'database', $this->connectionFile]));
               $dsn = "sqlsrv:APP=FIM;Database={$data['database']};Server={$data['host']}{$data['port']}";
               $this->initStmt = 'SET QUOTED_IDENTIFIED ON';
               break;
            case 'sybase':
            case 'mssql':
               if(!empty($data['port']))
                  $data['port'] = ",{$data['port']}";
            case 'dblib':
               if(empty($data['host']))
                  $data['host'] = '127.0.0.1';
               if(!empty($data['port']) && $data['driver'] === 'dblib')
                  $data['port'] = ":{$data['port']}";
               if(empty($data['database']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['MSSQL/Azure', 'database', $this->connectionFile]));
               $dsn = "{$data['driver']}:appname=FIM;charset=UTF-8;dbname={$data['database']};host={$data['host']}{$data['port']}";
               $this->initStmt = 'SET QUOTED_IDENTIFIER ON';
               break;
            case '4d':
               if(empty($data['host']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'], ['4D', 'host', $this->connectionFile]));
               $dsn = "4D:host={$data['host']};charset=UTF-8";
               if(!empty($data['port']))
                  $dsn .= ";port={$data['port']}";
               break;
            case 'ibm':
               if(empty($data['host']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'], ['IBM', 'host', $this->connectionFile]));
               if(empty($data['port']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'], ['IBM', 'port', $this->connectionFile]));
               if(empty($data['database']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['IBM', 'database', $this->connectionFile]));
               $dsn = "ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE={$data['database']};HOSTNAME={$data['host']};PORT={$data['port']};PROTOCOL=TCPIP";
               break;
            case 'cubrid':
               if(empty($data['host']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['CUBRID', 'host', $this->connectionFile]));
               if(empty($data['port']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['CUBRID', 'port', $this->connectionFile]));
               if(empty($data['database']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['CUBRID', 'database', $this->connectionFile]));
               $dsn = "cubrid:host={$data['host']};port={$data['port']};dbname={$data['database']}";
               break;
            case 'informix':
               if(empty($data['host']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['Informix', 'host', $this->connectionFile]));
               if(empty($data['port']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['Informix', 'port', $this->connectionFile]));
               if(empty($data['database']))
                  throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                     'data', 'missing'],
                     ['Informix', 'database', $this->connectionFile]));
               $dsn = "informix:host={$data['host']};service={$data['port']};database={$data['database']};DELIMIDENT=y";
               break;
            default:
               throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                  'data', 'wrong', 'driver'],
                  [$data['driver'], $this->connectionFile]));
         }
         $computed['dsn'] = $dsn;
         $this->driver = $data['driver'];
      }
   }

   public final function disconnect() {
      $this->connection = null;
   }

   /**
    * @return PDO
    */
   public final function getConnection() {
      if(isset($this->connection))
         return $this->connection;
      try {
         $this->connection = new PDO($this->computedValues['dsn'],
            $this->data['user'], $this->data['password'],
            $this->computedValues['options']);
         $this->connection->setAttribute(PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION);
         if(isset($this->initStmt))
            $this->connection->exec($this->initStmt);
         return $this->connection;
      }catch(PDOException $e) {
         throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
            'connectionFailed'], [$this->connectionFile, $e->getMessage()]),
         $e->getCode(), $e);
      }
   }

   /**
    * @return boolean
    */
   public final function isRecursive() {
      return $this->data['recursive'];
   }

   // </editor-fold>
   // <editor-fold desc="Database functions" defaultstate="collapsed">
   private $transactions = 0;

   /**
    * Enters a (nested) transaction
    */
   public final function enterTransaction() {
      if(++$this->transactions === 1)
         $this->connection->beginTransaction();
      else
         $this->connection->exec('SAVEPOINT sp' . ($this->transactions - 1));
   }

   /**
    * Leaves a (nested) transaction
    * @param bool $commit Determines whether to apply the changed (true) or not
    */
   public final function leaveTransaction($commit = true) {
      if($this->transactions === 0)
         return;
      elseif(--$this->transactions === 0) {
         if($commit)
            $this->connection->commit();
         else
            $this->connection->rollBack();
      }elseif($commit)
         $this->connection->exec("RELEASE SAVEPOINT sp{$this->transactions}");
      else
         $this->connection->exec("ROLLBACK TO SAVEPOINT sp{$this->transactions}");
   }

   /**
    * Binds parameters to a PDO statement and executes it
    * @param PDOStatement $stmt
    * @param array $values Either a numeric (0-indexed) array or an associative
    *    array, depending on whether you use numered or named parameters.
    * @return boolean
    * @throws DatabaseException
    */
   public final function execute(PDOStatement $stmt, array $values = []) {
      if(!empty($values)) {
         reset($values);
         $associative = key($values) !== 0;
         foreach($values as $key => $value) {
            if($value === null)
               $stmt->bindValue($associative ? $key : $key + 1, null,
                  PDO::PARAM_NULL);
            elseif(is_string($value))
               $stmt->bindValue($associative ? $key : $key + 1, $value,
                  PDO::PARAM_STR);
            elseif(is_int($value))
               $stmt->bindValue($associative ? $key : $key + 1, $value,
                  PDO::PARAM_INT);
            elseif(is_bool($value))
               $stmt->bindValue($associative ? $key : $key + 1, $value,
                  PDO::PARAM_BOOL);
            elseif(is_float($value))
               $stmt->bindValue($associative ? $key : $key + 1, (string)$value,
                  PDO::PARAM_STR);
            elseif(is_object($value))
               if($value instanceof Blob)
                  $stmt->bindValue($associative ? $key : $key + 1, $value->data,
                     PDO::PARAM_LOB);
               else
                  try {
                     $stmt->bindValue($associative ? $key : $key + 1,
                        (string)$value, PDO::PARAM_STR);
                  }catch(Exception $e) {
                     throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                        'bindError', 'object']), 0, $e);
                  }
            else
               throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                  'bindError', 'unknown']));
         }
      }
      return $stmt->execute();
   }

   /**
    * Executes a select statement to the database.
    * @param string $table
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
    * @param bool $force_statement (default false) Set this to TRUE to always
    *    get a PDOStatement in return.
    * @return null|array|PDOStatement
    * - PDOStatement if there are either multiple rows or you set
    *   $force_statement to true
    * - null if no row was found matching the requirements
    * - an associative array [column_name => column_value] if only one row was
    *   found
    * @throws DatabaseException
    */
   public final function select($table, $where = null, array $bind = null,
      array $columns = [], $order_by = null, $group_by_having = null,
      $limit = null, $join = '', $force_statement = false) {
      if(empty($columns))
         $sql = "SELECT * FROM \"$table\" $join";
      else
         $sql = 'SELECT "' . implode('", "', $columns) . "\" FROM \"$table\" $join";
      if($where !== null)
         $sql .= " WHERE $where";
      if($group_by_having !== null)
         $sql .= " GROUP BY $group_by_having";
      if($order_by !== null)
         $sql .= " ORDER BY $order_by";
      if($limit !== null)
         $sql .= " LIMIT $limit";
      $statement = $this->connection->prepare($sql);
      if(!$this->execute($statement, $bind))
         throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
            'sql', 'selectError']));
      if(($force_statement) || (($num = $statement->rowCount()) > 1))
         return $statement;
      elseif($num === 0)
         return null;
      else
         return $statement->fetch(PDO::FETCH_ASSOC);
   }

   /**
    * Executes a select statement with simple conditions to the database.
    * @param string $table
    * @param array $where Associative array field => value
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
    * @param bool $force_statement (default false) Set this to TRUE to always
    *    get a PDOStatement in return.
    * @return null|array|PDOStatement
    * - PDOStatement if there are either multiple rows or you set
    *   $force_statement to true
    * - null if no row was found matching the requirements
    * - an associative array [column_name => column_value] if only one row was
    *   found
    * @throws DatabaseException
    */
   public final function simpleSelect($table, array $where = [],
      array $columns = [], $order_by = null, $group_by_having = null,
      $limit = null, $join = '', $force_statement = false) {
      $wheres = $bind = [];
      foreach($where as $name => $value) {
         $wheres[] = $name = '"' . addcslashes($name, '"\\') . '" = ?';
         $bind[] = $value;
      }
      $wheres = empty($wheres) ? null : implode(' AND ', $wheres);
      return $this->select($table, $wheres, $bind, $columns, $order_by,
            $group_by_having, $limit, $join, $force_statement);
   }

   /**
    * Executes an insert statement to the database. The last inserted id might
    * be avaiblable via the lastInsertId() function of the PDO connection.
    * @param string $into The table name
    * @param array $columnValues An associative array that connects column names
    *    with their values
    * @return bool True on success
    * @see PDO::lastInsertId()
    */
   public final function insert($into, array $columnValues) {
      if(empty($columnValues))
         return false;
      $sql = "INSERT INTO \"$into\"(\"" . implode('", "',
            array_keys($columnValues)) . '") VALUES(' . str_repeat('?, ',
            count($columnValues) - 1) . '?)';
      return $this->execute($this->connection->prepare($sql),
            array_values($columnValues));
   }

   /**
    * Executes an update statement to the database.
    * @param string $table The table name
    * @param array $newValues An associative array that connects column names
    *    with their values
    * @param array $whereColumns Either this is a same structured associative
    *    array with conditions for the update or this is a simple enumerating
    *    array for which the keys will be assumed in the same order as in
    *    $newValues (if you do not specify enough conditions, the last columns
    *    will be ignored)
    * @return bool True on success
    * @throws DatabaseException
    */
   public final function update($table, array $newValues, array $whereColumns) {
      if(empty($newValues))
         return null;
      $sql = "UPDATE \"$table\" SET \"" . implode('" = ?, "',
            array_keys($newValues)) . '" = ?';
      $params = array_values($newValues);
      if(!empty($whereColumns)) {
         reset($whereColumns);
         if(is_int(key($whereColumns))) {
            $keys = array_keys($newValues);
            $diff = count($keys) - count($whereColumns);
            if($diff > 0)
               array_splice($keys, 0, count($keys));
            elseif($diff < 0)
               throw new DatabaseException(I18N::getInternalLocale()->get(['databaseConnection',
                  'sql', 'updateError']));
         }else
            $keys = array_keys($whereColumns);
         $sql .= ' WHERE "' . implode('" = ? AND "', $keys) . '" = ?';
         $params = array_merge($params, array_values($whereColumns));
      }
      return $this->execute($this->connection->prepare($sql), $params);
   }

   /**
    * Executes a delete statement to the database.
    * @param string $table The table name
    * @param array $whereColumns An associative array that connects column names
    *    with their values
    * @return bool True on success
    */
   public final function delete($table, array $whereColumns) {
      $sql = "DELETE FROM \"$table\"";
      if(!empty($whereColumns))
         $sql .= ' WHERE "' . implode('" = ? AND "', array_keys($whereColumns)) . '" = ?';
      return $this->execute($this->connection->prepare($sql),
            array_values($whereColumns));
   }

   /**
    * Gets the last insert id. For pgsql, giving the parameters $table and
    * $keyField is required.
    * @param string $table
    * @param string $keyField
    * @return string
    */
   public final function lastInsertId($table = '', $keyField = 'id') {
      if($this->driver === 'pgsql') {
         if(preg_match('#^(?:"([^"]++)"\.)?+"?([^"]++)#', $table, $matches) === 1 || preg_match('#^(?:([^\.]++)\.)?+"?([^"]++)#',
               $table, $matches) === 1)
            $table = $matches[2];
         return $this->connection->lastInsertId("{$table}_{$keyField}_seq");
      }
      return $this->connection->lastInsertId();
   }

   // </editor-fold>
}
