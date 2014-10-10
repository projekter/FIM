<?php

namespace data;

/**
 * @property-read int $id
 * @property string $title
 * @property bool $completed
 * @property int $created
 */
class task_item extends \PrimaryTable {

   protected static $columns = [
      'id' => '+int',
      'title' => 'string',
      'completed' => 'bool',
      'created' => 'int',
   ];

   /**
    * @param string $title
    * @param bool $completed
    * @return self
    */
   public static function create($title, $completed) {
      return parent::createNew((string)$title, (bool)$completed, time());
   }

   /**
    * @return bool
    */
   public function delete() {
      return parent::delete();
   }

   /**
    * @return self[]
    */
   public static function fetchAll() {
      return parent::findBy([], '"completed" ASC, "title" ASC');
   }

   /**
    * @param int $id
    * @return self|null
    */
   public static function fetchById($id) {
      return parent::findOneBy(['id' => (int)$id]);
   }

}
