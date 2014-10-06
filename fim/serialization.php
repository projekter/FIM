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
 * This interface can be implemented by any class. Implementing it will allow
 * the class to be represented in a PrimaryTable as column.
 */
interface fimSerializable {

   /**
    * Returns a string representation of the current object.
    */
   public function fimSerialize();

   /**
    * Restores an object of the given class by reverting the serialization
    * process. This differs to the specification of PHP's Serializable - it is
    * static!
    * @return static
    */
   public static function fimUnserialize($serialized);
}

function fimSerialize($arg) {
   if($arg instanceof fimSerializable)
      return 'f' . serialize(array(get_class($arg), $arg->fimSerialize()));
   elseif(is_array($arg))
      foreach($arg as &$val)
         $val = fimSerialize($val);
   return serialize($arg);
}

function fimUnserialize($serialized) {
   if($serialized == '')
      throw new SerializationException(I18N::getInternalLocale()->get(['serialization',
         'unserializeInvalid']));
   elseif($serialized[0] === 'f') {
      $unserialized = unserialize(substr($serialized, 1));
      if(!is_array($unserialized))
         throw new SerializationException(I18N::getInternalLocale()->get(['serialization',
            'unserializeInvalid']));
      return $unserialized[0]::fimUnserialize($unserialized[1]);
   }else{
      $unserialization = unserialize($serialized);
      if(is_array($unserialization))
         foreach($unserialization as &$uns)
            $uns = fimUnserialize($uns);
      return $unserialization;
   }
}
