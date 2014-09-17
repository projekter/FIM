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
 * This base class can be used whenever synchronization is needed in a cross
 * platform environment. Performance may heavily differ on different platforms,
 * if there is no php accelerator installed.<br />
 * If you need to respect a multi-server environment, Memcached needs to be
 * set up and properly configured.
 */
final class Semaphore {

   private static $multiServerMethod = null, $singleServerMethod = null;
   private $data;
   private $multiServer;
   private $locked = false;

   const METHOD_SEMAPHORE = 1;
   const METHOD_FILE = 2;
   const METHOD_MEMCACHED = 3;
   const METHOD_REDIS = 4;
   const METHOD_APC = 5;
   const METHOD_XCACHE = 6;
   const METHOD_WINCACHE = 7;

   # Zend cache is not equipped with atomic data cache operations

   /**
    * Creates a new semaphore.
    * @param string $name The name identifies a semaphore system-wide
    * @param boolean $multiServer Set this to true if FIM runs in a multi-server
    *    environment. The semaphore is shared across all servers. This requires
    *    Memcached or Redis to be set up properly.
    */
   public function __construct($name, $multiServer = false) {
      $this->data = "fimSemaphore$name";
      $this->multiServer = $multiServer;
      if($multiServer) {
         if(self::$multiServerMethod === null)
            if(isset(Session::$redis))
               self::$multiServerMethod = self::METHOD_REDIS;
            else
               self::$multiServerMethod = self::METHOD_MEMCACHED;
      }else{
         if(self::$singleServerMethod === null)
            if(function_exists('sem_acquire')) {
               self::$singleServerMethod = self::METHOD_SEMAPHORE;
            }elseif(function_exists('wincache_lock'))
               self::$singleServerMethod = self::METHOD_WINCACHE;
            elseif(function_exists('apc_add'))
               self::$singleServerMethod = self::METHOD_APC;
            elseif(function_exists('xcache_inc'))
               self::$singleServerMethod = self::METHOD_XCACHE;
            elseif(isset(Session::$redis))
               self::$singleServerMethod = self::METHOD_REDIS;
            else{
               $servers = Session::$memcached->getServerList();
               if(empty($servers))
                  self::$singleServerMethod = self::METHOD_FILE;
               else
                  self::$singleServerMethod = self::METHOD_MEMCACHED;
            }
         if(self::$singleServerMethod === self::METHOD_SEMAPHORE)
            $this->data = sem_get(crc32($this->data));
         elseif(self::$singleServerMethod === self::METHOD_FILE)
            $this->data = fopen(sys_get_temp_dir() . '/' . hash('md5',
                  FrameworkPath . $this->data) . '.lock', 'a');
      }
   }

   public function __destruct() {
      $this->unlock();
      if(!$this->multiServer && self::$singleServerMethod === self::METHOD_FILE)
         fclose($this->data);
   }

   /**
    * Acquires a new lock. This function will block until the lock is acquired.
    * @return boolean False on failure
    */
   public function lock() {
      if($this->multiServer) {
         if(self::$multiServerMethod === self::METHOD_REDIS)
            while(!Session::$redis->setnx($this->data, true))
               usleep(3000);
         else#if(self::$multiServerMethod === self::METHOD_MEMCACHED)
            while(!Session::$memcached->add($this->data, true))
               usleep(3000);
      }else
         switch(self::$singleServerMethod) {
            case self::METHOD_SEMAPHORE:
               if(!sem_acquire($this->data))
                  return false;
            case self::METHOD_WINCACHE:
               if(!wincache_lock($this->data))
                  return false;
            case self::METHOD_APC:
               while(!apc_add($this->data, true))
                  usleep(3000);
               break;
            case self::METHOD_XCACHE:
               while(xcache_inc($this->data) > 1) {
                  xcache_dec($this->data);
                  usleep(3000);
               }
               break;
            case self::METHOD_REDIS:
               while(!Session::$redis->setnx($this->data, true))
                  usleep(3000);
               break;
            case self::METHOD_MEMCACHED:
               while(!Session::$memcached->add($this->data, true))
                  usleep(3000);
               break;
            default:
               if(!flock($this->data, LOCK_EX))
                  return false;
         }
      $this->locked = true;
      return true;
   }

   /**
    * Releases a lock
    * @return boolean False on failure and when there was no existing lock
    */
   public function unlock() {
      if(!$this->locked)
         return false;
      if($this->multiServer) {
         if(self::$multiServerMethod === self::METHOD_REDIS) {
            if(!Session::$memcached->del($this->data))
               return false;
         }else#if(self::$multiServerMethod === self::METHOD_MEMCACHED)
         if(!Session::$memcached->delete($this->data))
            return false;
      }else
         switch(self::$singleServerMethod) {
            case self::METHOD_SEMAPHORE:
               if(!sem_release($this->data))
                  return false;
               break;
            case self::METHOD_WINCACHE:
               if(!wincache_unlock($this->data))
                  return false;
            case self::METHOD_APC:
               if(!apc_delete($this->data))
                  return false;
               break;
            case self::METHOD_XCACHE:
               if(xcache_dec($this->data) != 0)
                  return false;
               break;
            case self::METHOD_REDIS:
               if(!Session::$redis->del($this->data))
                  return false;
               break;
            case self::METHOD_MEMCACHED:
               if(!Session::$memcached->delete($this->data))
                  return false;
               break;
            default:
               if(!flock($this->data, LOCK_UN))
                  return false;
         }
      $this->locked = false;
      return true;
   }

}
