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
 * This class will wrap the old Memcache extension so that it conforms with the
 * newer Memcached extension.
 */
class Memcached {

   // <editor-fold desc="Memcached constants" defaultstate="collapsed">
   /**
    * <p>Enables or disables payload compression. When enabled,
    * item values longer than a certain threshold (currently 100 bytes) will be
    * compressed during storage and decompressed during retrieval
    * transparently.</p>
    * <p>Type: boolean, default: <b>TRUE</b>.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_COMPRESSION = -1001;
   const OPT_COMPRESSION_TYPE = -1004;

   /**
    * <p>This can be used to create a "domain" for your item keys. The value
    * specified here will be prefixed to each of the keys. It cannot be
    * longer than 128 characters and will reduce the
    * maximum available key size. The prefix is applied only to the item keys,
    * not to the server keys.</p>
    * <p>Type: string, default: "".</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_PREFIX_KEY = -1002;

   /**
    * <p>
    * Specifies the serializer to use for serializing non-scalar values.
    * The valid serializers are <b>Memcached::SERIALIZER_PHP</b>
    * or <b>Memcached::SERIALIZER_IGBINARY</b>. The latter is
    * supported only when memcached is configured with
    * --enable-memcached-igbinary option and the
    * igbinary extension is loaded.
    * </p>
    * <p>Type: integer, default: <b>Memcached::SERIALIZER_PHP</b>.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_SERIALIZER = -1003;

   /**
    * <p>Indicates whether igbinary serializer support is available.</p>
    * <p>Type: boolean.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HAVE_IGBINARY = 0;

   /**
    * <p>Indicates whether JSON serializer support is available.</p>
    * <p>Type: boolean.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HAVE_JSON = 0;
   const HAVE_SESSION = 1;
   const HAVE_SASL = 0;

   /**
    * <p>Specifies the hashing algorithm used for the item keys. The valid
    * values are supplied via <b>Memcached::HASH_*</b> constants.
    * Each hash algorithm has its advantages and its disadvantages. Go with the
    * default if you don't know or don't care.</p>
    * <p>Type: integer, default: <b>Memcached::HASH_DEFAULT</b></p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_HASH = 2;

   /**
    * <p>The default (Jenkins one-at-a-time) item key hashing algorithm.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HASH_DEFAULT = 0;

   /**
    * <p>MD5 item key hashing algorithm.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HASH_MD5 = 1;

   /**
    * <p>CRC item key hashing algorithm.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HASH_CRC = 2;

   /**
    * <p>FNV1_64 item key hashing algorithm.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HASH_FNV1_64 = 3;

   /**
    * <p>FNV1_64A item key hashing algorithm.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HASH_FNV1A_64 = 4;

   /**
    * <p>FNV1_32 item key hashing algorithm.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HASH_FNV1_32 = 5;

   /**
    * <p>FNV1_32A item key hashing algorithm.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HASH_FNV1A_32 = 6;

   /**
    * <p>Hsieh item key hashing algorithm.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HASH_HSIEH = 7;

   /**
    * <p>Murmur item key hashing algorithm.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const HASH_MURMUR = 8;

   /**
    * <p>Specifies the method of distributing item keys to the servers.
    * Currently supported methods are modulo and consistent hashing. Consistent
    * hashing delivers better distribution and allows servers to be added to
    * the cluster with minimal cache losses.</p>
    * <p>Type: integer, default: <b>Memcached::DISTRIBUTION_MODULA.</b></p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_DISTRIBUTION = 9;

   /**
    * <p>Modulo-based key distribution algorithm.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const DISTRIBUTION_MODULA = 0;

   /**
    * <p>Consistent hashing key distribution algorithm (based on libketama).</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const DISTRIBUTION_CONSISTENT = 1;
   const DISTRIBUTION_VIRTUAL_BUCKET = 6;

   /**
    * <p>Enables or disables compatibility with libketama-like behavior. When
    * enabled, the item key hashing algorithm is set to MD5 and distribution is
    * set to be weighted consistent hashing distribution. This is useful
    * because other libketama-based clients (Python, Ruby, etc.) with the same
    * server configuration will be able to access the keys transparently.
    * </p>
    * <p>
    * It is highly recommended to enable this option if you want to use
    * consistent hashing, and it may be enabled by default in future
    * releases.
    * </p>
    * <p>Type: boolean, default: <b>FALSE</b>.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_LIBKETAMA_COMPATIBLE = 16;
   const OPT_LIBKETAMA_HASH = 17;
   const OPT_TCP_KEEPALIVE = 32;

   /**
    * <p>Enables or disables buffered I/O. Enabling buffered I/O causes
    * storage commands to "buffer" instead of being sent. Any action that
    * retrieves data causes this buffer to be sent to the remote connection.
    * Quitting the connection or closing down the connection will also cause
    * the buffered data to be pushed to the remote connection.</p>
    * <p>Type: boolean, default: <b>FALSE</b>.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_BUFFER_WRITES = 10;

   /**
    * <p>Enable the use of the binary protocol. Please note that you cannot
    * toggle this option on an open connection.</p>
    * <p>Type: boolean, default: <b>FALSE</b>.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_BINARY_PROTOCOL = 18;

   /**
    * <p>Enables or disables asynchronous I/O. This is the fastest transport
    * available for storage functions.</p>
    * <p>Type: boolean, default: <b>FALSE</b>.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_NO_BLOCK = 0;

   /**
    * <p>Enables or disables the no-delay feature for connecting sockets (may
    * be faster in some environments).</p>
    * <p>Type: boolean, default: <b>FALSE</b>.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_TCP_NODELAY = 1;

   /**
    * <p>The maximum socket send buffer in bytes.</p>
    * <p>Type: integer, default: varies by platform/kernel
    * configuration.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_SOCKET_SEND_SIZE = 4;

   /**
    * <p>The maximum socket receive buffer in bytes.</p>
    * <p>Type: integer, default: varies by platform/kernel
    * configuration.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_SOCKET_RECV_SIZE = 5;

   /**
    * <p>In non-blocking mode this set the value of the timeout during socket
    * connection, in milliseconds.</p>
    * <p>Type: integer, default: 1000.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_CONNECT_TIMEOUT = 14;

   /**
    * <p>The amount of time, in seconds, to wait until retrying a failed
    * connection attempt.</p>
    * <p>Type: integer, default: 0.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_RETRY_TIMEOUT = 15;

   /**
    * <p>Socket sending timeout, in microseconds. In cases where you cannot
    * use non-blocking I/O this will allow you to still have timeouts on the
    * sending of data.</p>
    * <p>Type: integer, default: 0.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_SEND_TIMEOUT = 19;

   /**
    * <p>Socket reading timeout, in microseconds. In cases where you cannot
    * use non-blocking I/O this will allow you to still have timeouts on the
    * reading of data.</p>
    * <p>Type: integer, default: 0.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_RECV_TIMEOUT = 20;

   /**
    * <p>Timeout for connection polling, in milliseconds.</p>
    * <p>Type: integer, default: 1000.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_POLL_TIMEOUT = 8;

   /**
    * <p>Enables or disables caching of DNS lookups.</p>
    * <p>Type: boolean, default: <b>FALSE</b>.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_CACHE_LOOKUPS = 6;

   /**
    * <p>Specifies the failure limit for server connection attempts. The
    * server will be removed after this many continuous connection
    * failures.</p>
    * <p>Type: integer, default: 0.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const OPT_SERVER_FAILURE_LIMIT = 21;
   const OPT_AUTO_EJECT_HOSTS = 28;
   const OPT_HASH_WITH_PREFIX_KEY = 25;
   const OPT_NOREPLY = 26;
   const OPT_SORT_HOSTS = 12;
   const OPT_VERIFY_KEY = 13;
   const OPT_USE_UDP = 27;
   const OPT_NUMBER_OF_REPLICAS = 29;
   const OPT_RANDOMIZE_REPLICA_READ = 30;
   const OPT_REMOVE_FAILED_SERVERS = 35;

   /**
    * <p>The operation was successful.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_SUCCESS = 0;

   /**
    * <p>The operation failed in some fashion.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_FAILURE = 1;

   /**
    * <p>DNS lookup failed.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_HOST_LOOKUP_FAILURE = 2;

   /**
    * <p>Failed to read network data.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_UNKNOWN_READ_FAILURE = 7;

   /**
    * <p>Bad command in memcached protocol.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_PROTOCOL_ERROR = 8;

   /**
    * <p>Error on the client side.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_CLIENT_ERROR = 9;

   /**
    * <p>Error on the server side.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_SERVER_ERROR = 10;

   /**
    * <p>Failed to write network data.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_WRITE_FAILURE = 5;

   /**
    * <p>Failed to do compare-and-swap: item you are trying to store has been
    * modified since you last fetched it.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_DATA_EXISTS = 12;

   /**
    * <p>Item was not stored: but not because of an error. This normally
    * means that either the condition for an "add" or a "replace" command
    * wasn't met, or that the item is in a delete queue.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_NOTSTORED = 14;

   /**
    * <p>Item with this key was not found (with "get" operation or "cas"
    * operations).</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_NOTFOUND = 16;

   /**
    * <p>Partial network data read error.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_PARTIAL_READ = 18;

   /**
    * <p>Some errors occurred during multi-get.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_SOME_ERRORS = 19;

   /**
    * <p>Server list is empty.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_NO_SERVERS = 20;

   /**
    * <p>End of result set.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_END = 21;

   /**
    * <p>System error.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_ERRNO = 26;

   /**
    * <p>The operation was buffered.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_BUFFERED = 32;

   /**
    * <p>The operation timed out.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_TIMEOUT = 31;

   /**
    * <p>Bad key.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_BAD_KEY_PROVIDED = 33;
   const RES_STORED = 15;
   const RES_DELETED = 22;
   const RES_STAT = 24;
   const RES_ITEM = 25;
   const RES_NOT_SUPPORTED = 28;
   const RES_FETCH_NOTFINISHED = 30;
   const RES_SERVER_MARKED_DEAD = 35;
   const RES_UNKNOWN_STAT_KEY = 36;
   const RES_INVALID_HOST_PROTOCOL = 34;
   const RES_MEMORY_ALLOCATION_FAILURE = 17;

   /**
    * <p>Failed to create network socket.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_CONNECTION_SOCKET_CREATE_FAILURE = 11;

   /**
    * <p>Payload failure: could not compress/decompress or serialize/unserialize the value.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const RES_PAYLOAD_FAILURE = -1001;

   /**
    * <p>The default PHP serializer.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const SERIALIZER_PHP = 1;

   /**
    * <p>The igbinary serializer.
    * Instead of textual representation it stores PHP data structures in a
    * compact binary form, resulting in space and time gains.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const SERIALIZER_IGBINARY = 2;

   /**
    * <p>The JSON serializer. Requires PHP 5.2.10+.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const SERIALIZER_JSON = 3;
   const SERIALIZER_JSON_ARRAY = 4;
   const COMPRESSION_FASTLZ = 2;
   const COMPRESSION_ZLIB = 1;

   /**
    * <p>A flag for <b>Memcached::getMulti</b> and
    * <b>Memcached::getMultiByKey</b> to ensure that the keys are
    * returned in the same order as they were requested in. Non-existing keys
    * get a default value of NULL.</p>
    * @link http://php.net/manual/en/memcached.constants.php
    */
   const GET_PRESERVE_ORDER = 1;
   const GET_ERROR_RETURN_VALUE = false;

   // </editor-fold>

   /**
    * @var Memcache
    */
   private $memcache;
   private $persistent;
   private $status;
   private $servers;
   private static $casSupport = null;

   /**
    * @param string $persistent_id
    */
   public function __construct($persistent_id = null) {
      # persistent_id could be respected with APC enabled, as APC user cache
      # stores objects without serialization. Everything else would be a waste
      $this->memcache = new Memcache();
      $this->persistent = isset($persistent_id);
      if(self::$casSupport === null)
         self::$casSupport = method_exists($this->memcache, 'cas');
   }

   /**
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function add($key, $value, $expiration = 0) {
      if($this->memcache->add($key, $value, MEMCACHE_COMPRESSED, $expiration)) {
         $this->status = self::RES_SUCCESS;
         return true;
      }else{
         $this->status = self::RES_FAILURE;
         return false;
      }
   }

   /**
    * Alias for add().
    * @param string $server_key
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function addByKey($server_key, $key, $value, $expiration = 0) {
      return $this->add($key, $value, $expiration);
   }

   /**
    * @param string $host
    * @param int $port
    * @param int $weight
    * @return boolean
    */
   public function addServer($host, $port, $weight = null) {
      if(!method_exists($this->memcache, 'addServer')) {
         if(empty($this->servers)) {
            if(($this->persistent && $this->memcache->pconnect($host, $port)) || (!$this->persistent && $this->memcache->connect($host,
                  $port)))
               $this->servers[] = ['host' => $host, 'port' => $port];
         }
         $this->status = self::RES_SUCCESS;
         return true; # addServer returns true, even if the server was not
         # available, as the connection normally is not established
      }
      if($this->memcache->addServer($host, $port, true, $weight)) {
         $this->status = self::RES_SUCCESS;
         $this->servers[] = ['host' => $host, 'port' => $port];
         return true;
      }else{
         $this->status = self::RES_FAILURE;
         return false;
      }
   }

   /**
    * @param array $servers
    */
   public function addServers(array $servers) {
      if(empty($servers)) {
         $this->status = self::RES_SUCCESS;
         return true;
      }
      if(!method_exists($this->memcache, 'addServer')) {
         if(empty($this->servers)) {
            usort($servers,
               function(array $a, array $b) {
               return (isset($a['weight']) ? $a['weight'] : 1) - (isset($b['weight'])
                        ? $b['weight'] : 1);
            });
            if($this->persistent) {
               foreach($servers as $server)
                  if($this->memcache->pconnect($server['host'], $server['port'])) {
                     $this->servers[] = ['host' => $server['host'], 'port' => $server['port']];
                     break;
                  }
            }else
               foreach($servers as $server)
                  if($this->memcache->connect($server['host'], $server['port'])) {
                     $this->servers[] = ['host' => $server['host'], 'port' => $server['port']];
                     break;
                  }
         }
         $this->status = self::RES_SUCCESS;
         return true; # again return true, regardless of the success
      }
      $success = false;
      foreach($servers as $server)
         if(($success |= $this->memcache->addServer($server[0], $server[1],
            true, isset($server[2]) ? $server[2] : null)))
            $this->servers[] = ['host' => $server[0], 'port' => $server[1]];
      $this->status = $success ? self::RES_SUCCESS : self::RES_FAILURE;
      return $success;
   }

   /**
    * This operation will fail, as compression is turned on by default.
    * @param string $key
    * @param string $value
    * @return boolean
    */
   public function append($key, $value) {
      $this->status = self::RES_SUCCESS;
      # you don't believe success status, but view the source...
      trigger_error('cannot append/prepend with compression turned on',
         E_USER_WARNING);
      return false;
   }

   /**
    * Alias for append().
    * @param string $server_key
    * @param string $key
    * @param mixed $value
    * @return boolean
    */
   public function appendByKey($server_key, $key, $value) {
      return $this->append($key, $value);
   }

   private $casData = [];

   /**
    * @param double $cas_token
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function cas($cas_token, $key, $value, $expiration = 0) {
      if(self::$casSupport) {
         if($this->memcache->cas($key, $value, MEMCACHE_COMPRESSED, $expiration,
               $cas_token))
            $this->status = self::RES_SUCCESS;
         else
            $this->status = self::RES_DATA_EXISTS;
         return true;
      }
      # We have no intrinsic CAS support. We may emulate it, but this is not
      # threadsafe! As FIM provides a semaphore class, we would have the
      # possibility to wrap this piece of code in a semaphore lock. But remember
      # this class is not a fully-featured replacement for the current
      # Memcached library.
      $this->status = self::RES_SUCCESS;
      $cas_token = (string)$cas_token;
      if(!isset($this->casData[$cas_token]) && !array_key_exists($cas_token,
            $this->casData))
         return false;# yep, fail with success...
      $newValue = @$this->memcache->get($key);
      if($newValue !== $this->casData[$cas_token]) {
         $this->status = self::RES_DATA_EXISTS;
         return true;
      }
      $this->casData[$cas_token] = $value;
      if(!$this->memcache->set($key, $value, MEMCACHE_COMPRESSED, $expiration)) {
         $this->status = self::RES_FAILURE;
         return false;
      }
      return true;
   }

   /**
    * Alias for cas().
    * @param double $cas_token
    * @param string $server_key
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function casByKey($cas_token, $server_key, $key, $value,
      $expiration = 0) {
      return $this->cas($cas_token, $key, $value, $expiration);
   }

   /**
    * @param string $key
    * @param int $offset
    * @param int $initial_value
    * @param int $expiry
    * @return boolean
    */
   public function decrement($key, $offset = 1, $initial_value = 0, $expiry = 0) {
      if(($result = $this->memcache->decrement($key, $offset)) === false) {
         if(!$this->memcache->set($key, $initial_value, MEMCACHE_COMPRESSED,
               $expiry)) {
            $this->status = self::RES_FAILURE;
            return false;
         }else{
            $this->status = self::RES_SUCCESS;
            return $initial_value;
         }
      }
      $this->status = self::RES_SUCCESS;
      return $result;
   }

   /**
    * Alias for decrement()
    * @param string $key
    * @param int $offset
    * @param int $initial_value
    * @param int $expiry
    * @return boolean
    */
   public function decrementByKey($server_key, $key, $offset = 1,
      $initial_value = 0, $expiry = 0) {
      return $this->decrement($key, $offset, $initial_value, $expiry);
   }

   /**
    * @param string $key
    * @param int $time
    * @return boolean
    */
   public function delete($key, $time = 0) {
      if($time !== 0) {
         # Actually, Memcached does not support delayed deletion any more. PHP's
         # Memcached extension thus only pretends to support this.
         $this->status = self::RES_PROTOCOL_ERROR;
         return false;
      }
      if($this->memcache->delete($key)) {
         $this->status = self::RES_SUCCESS;
         return true;
      }else{
         $this->status = self::RES_NOTFOUND;
         return false;
      }
   }

   /**
    * Alias for delete()
    * @param string $server_key
    * @param string $key
    * @param int $time
    * @return boolean
    */
   public function deleteByKey($server_key, $key, $time = 0) {
      return $this->delete($key, $time);
   }

   /**
    * @param array $keys
    * @param int $time
    * @return boolean
    */
   public function deleteMulti(array $keys, $time = 0) {
      $success = false;
      if($time !== 0) {
         # Actually, Memcached does not support delayed deletion any more. PHP's
         # Memcached extension thus only pretends to support this.
         $this->status = self::RES_PROTOCOL_ERROR;
         return false;
      }
      foreach($keys as $key)
         $success |= $this->memcache->delete($key);
      if($success)
         $this->status = self::RES_SUCCESS;
      else
         $this->status = self::RES_NOTFOUND;
      return $success;
   }

   /**
    * Alias for deleteMulti()
    * @param string $server_key
    * @param array $keys
    * @param int $time
    * @return boolean
    */
   public function deleteMultiByKey($server_key, array $keys, $time = 0) {
      return $this->deleteMulti($keys, $time);
   }

   private $delayedData = [], $delayedIndex = 0;

   /**
    * @return boolean|array
    */
   public function fetch() {
      if(!isset($this->delayedData[$this->delayedIndex])) {
         $this->status = self::RES_END;
         return false;
      }
      $this->status = self::RES_SUCCESS;
      $result = $this->delayedData[$this->delayedIndex];
      unset($this->delayedData[$this->delayedIndex++]);
      return $result;
   }

   /**
    * @return array
    */
   public function fetchAll() {
      $result = $this->delayedData;
      $this->delayedData = [];
      $this->delayedIndex = 0;
      $this->status = self::RES_SUCCESS;
      return $result;
   }

   private $flushData = null;

   /**
    * Flushing is perfomed instantaneously ($delay = 0) or at the end of the
    * script (> 0)
    * @param int $delay
    * @return boolean
    */
   public function flush($delay = 0) {
      if($delay === 0) {
         if($this->memcache->flush()) {
            $this->status = self::RES_SUCCESS;
            return true;
         }else{
            $this->status = self::RES_FAILURE;
            return false;
         }
      }else{
         if(!isset($this->flushData))
            $this->flushData = Config::registerShutdownFunction([$this->memcache,
                  'flush']);
         $this->status = self::RES_SUCCESS;
         return true;
      }
   }

   /**
    * @param string $key
    * @param callable $cache_cb
    * @param double &$cas_token
    * @return string|boolean
    */
   public function get($key, callable $cache_cb = null, &$cas_token = null) {
      if(self::$casSupport) {
         $flags = [];
         $cas = [];
         $result = @$this->memcache->get([$key], $flags, $cas);
      }else
         $result = @$this->memcache->get([$key]);
      if(!isset($result[$key])) {
         if(isset($cache_cb)) {
            $value = '';
            if($cache_cb($this, $key, $value)) {
               $this->memcache->set($key, $value, MEMCACHE_COMPRESSED);
               $this->status = self::RES_SUCCESS;
               if(!self::$casSupport) {
                  $cas_token = $this->casData[$key] = count($this->casData);
                  $this->casData[$cas_token] = $value;
               }else
                  @$this->memcache->get($key, $flags, $cas_token);
               $cas_token = (double)$cas_token;
               return $value;
            }
         }
         $this->status = self::RES_NOTFOUND;
         return false;
      }else{
         if(!self::$casSupport) {
            if(isset($this->casData[$key]))
               $cas_token = $this->casData[$key];
            else
               $cas_token = $this->casData[$key] = count($this->casData);
            $this->casData[$cas_token] = $result[$key];
            $cas_token = (double)$cas_token;
         }else
            $cas_token = (double)$cas[$key];
         $this->status = self::RES_SUCCESS;
         return $result[$key];
      }
   }

   /**
    * @return array
    */
   public function getAllKeys() {
      $result = [];
      foreach($this->memcache->getStats('slabs') as $slabID => $slabMeta) {
         if(($slabID = (int)$slabID) === 0)
            continue;
         # Well, this is said to be removed from server side support
         # according to the manual... however, memcached does it the same way
         if(($cdump = @$this->memcache->getStats('cachedump', $slabID, 0)) !== false)
            $result += array_keys($cdump);
      }
      $this->status = self::RES_SUCCESS;
      return array_unique($result, SORT_REGULAR);
   }

   /**
    * Alias for get()
    * @param string $server_key
    * @param string $key
    * @param callable $cache_cb
    * @param double &$cas_token
    * @return string|boolean
    */
   public function getByKey($server_key, $key, callable $cache_cb = null,
      &$cas_token = null) {
      return $this->get($key, $cache_cb, $cas_token);
   }

   /**
    * @param array $keys
    * @param boolean $with_cas
    * @param callable $value_cb
    * @return boolean
    */
   public function getDelayed(array $keys, $with_cas = false,
      callable $value_cb = null) {
      $cas = [];
      if(($data = ($with_cas ? $this->getMulti($keys, $cas) : $this->getMulti($keys))) === false) {
         $this->status = self::RES_FAILURE;
         return false;
      }
      if(isset($value_cb))
         if($with_cas)
            foreach($data as $key => $element)
               $value_cb($this,
                  ['key' => $key, 'data' => $element, 'cas' => $cas[$key]]);
         else
            foreach($data as $key => $element)
               $value_cb($this, ['key' => $key, 'data' => $element]);
      else{
         if($with_cas)
            foreach($data as $key => $element)
               $this->delayedData[] = ['key' => $key, 'data' => $element, 'cas' => $cas[$key]];
         else
            foreach($data as $key => $element)
               $this->delayedData[] = ['key' => $key, 'data' => $element];
      }
      $this->status = self::RES_SUCCESS;
      return true;
   }

   /**
    * Alias for getDelayed()
    * @param string $server_key
    * @param array $keys
    * @param boolean $with_cas
    * @param callable $value_cb
    * @return boolean
    */
   public function getDelayedByKey($server_key, array $keys, $with_cas = false,
      callable $value_cb = null) {
      return $this->getDelayed($keys, $with_cas, $value_cb);
   }

   /**
    * @param array $keys
    * @param array $cas_tokens
    * @param int $flags
    * @return boolean
    */
   public function getMulti(array $keys, array &$cas_tokens = null, $flags = 0) {
      if(isset($cas_tokens)) {
         $cas = [];
         if(self::$casSupport) {
            $result = @$this->memcache->get($keys, $flags, $cas);
            $cas_tokens = $cas;
            foreach($cas_tokens as &$token)
               $token = (double)$token;
         }else{
            $result = @$this->memcache->get($keys);
            foreach($result as $key => $value) {
               if(isset($this->casData[$key]))
                  $cas_token = $this->casData[$key];
               else
                  $cas_token = $this->casData[$key] = count($this->casData);
               $this->casData[$cas_token] = (double)$value;
               $cas_tokens[$key] = (double)$cas_token;
            }
         }
      }else
         $result = @$this->memcache->get($keys);
      if($result === false) {
         $this->status = self::RES_FAILURE;
         return false;
      }else{
         $this->status = self::RES_SUCCESS;
         return $result;
      }
   }

   /**
    * Alias for getMulti()
    * @param string $server_key
    * @param array $keys
    * @param array $cas_tokens
    * @param int $flags
    * @return boolean
    */
   public function getMultiByKey($server_key, array $keys,
      array &$cas_tokens = null, $flags = 0) {
      return $this->getMulti($keys, $cas_tokens, $flags);
   }

   /**
    * @param int $option
    * @return mixed
    */
   public function getOption($option) {
      $this->status = self::RES_SUCCESS;
      switch($option) {
         case self::OPT_COMPRESSION:
            return true;
         case self::OPT_SERIALIZER:
            return self::SERIALIZER_PHP;
         case self::OPT_PREFIX_KEY:
            return '';
         case self::OPT_HASH:
            if(ini_get('memcache.hash_function') === 'fnv')
               return self::HASH_FNV1A_32;
            else
               return self::HASH_CRC;
         case self::OPT_DISTRIBUTION:
            return self::DISTRIBUTION_MODULA;
         case self::OPT_BUFFER_WRITES:
            return false;
         case self::OPT_BINARY_PROTOCOL:
            return ini_get('memcache.protocol') !== 'ascii';
         case self::OPT_NO_BLOCK:
         case self::OPT_TCP_NODELAY:
            return false;
         case self::OPT_SOCKET_SEND_SIZE:
         case self::OPT_SOCKET_RECV_SIZE:
            return (int)Config::iniGet('memcache.chunk_size');
         case self::OPT_CONNECT_TIMEOUT:
            return 1000;
         case self::OPT_RETRY_TIMEOUT:
         case self::OPT_SEND_TIMEOUT:
         case self::OPT_RECV_TIMEOUT:
            return 0;
         case self::OPT_POLL_TIMEOUT:
            return 1000;
         case self::OPT_CACHE_LOOKUPS:
            return false;
         case self::OPT_SERVER_FAILURE_LIMIT:
            return 0;
         case self::OPT_AUTO_EJECT_HOSTS:
            return false;
         case self::OPT_COMPRESSION_TYPE:
            return self::COMPRESSION_ZLIB;
         case self::OPT_LIBKETAMA_COMPATIBLE:
            return false;
         default: # There are some further keys, but they are not documented and
            # not available in all php_memcached versions
            $this->status = self::RES_BAD_KEY_PROVIDED;
            return false;
      }
   }

   /**
    * @return int
    */
   public function getResultCode() {
      return $this->status;
   }

   /**
    * @return string
    */
   public function getResultMessage() {
      static $status = [self::RES_SUCCESS => 'SUCCESS',
         self::RES_FAILURE => 'FAILURE',
         self::RES_HOST_LOOKUP_FAILURE => 'getaddrinfo() or getnameinfo() HOSTNAME LOOKUP FAILURE',
         self::RES_UNKNOWN_READ_FAILURE => 'UNKNOWN READ FAILURE',
         self::RES_PROTOCOL_ERROR => 'PROTOCOL ERROR',
         self::RES_CLIENT_ERROR => 'CLIENT ERROR',
         self::RES_SERVER_ERROR => 'SERVER ERROR',
         self::RES_WRITE_FAILURE => 'WRITE FAILURE',
         self::RES_DATA_EXISTS => 'CONNECTION DATA EXISTS',
         self::RES_NOTSTORED => 'NOT STORED',
         self::RES_STORED => 'STORED',
         self::RES_NOTFOUND => 'NOT FOUND',
         self::RES_MEMORY_ALLOCATION_FAILURE => 'MEMORY ALLOCATION FAILURE',
         self::RES_PARTIAL_READ => 'PARTIAL READ',
         self::RES_SOME_ERRORS => 'SOME ERRORS WERE REPORTED',
         self::RES_NO_SERVERS => 'NO SERVERS DEFINED',
         self::RES_END => 'SERVER END',
         self::RES_DELETED => 'SERVER DELETE',
         self::RES_STAT => 'STAT VALUE',
         self::RES_ITEM => 'ITEM VALUE',
         self::RES_ERRNO => 'SYSTEM ERROR',
         self::RES_NOT_SUPPORTED => 'ACTION NOT SUPPORTED',
         self::RES_FETCH_NOTFINISHED => 'FETCH WAS NOT COMPLETED',
         self::RES_BUFFERED => 'ACTION QUEUED',
         self::RES_TIMEOUT => 'A TIMEOUT OCCURED',
         self::RES_BAD_KEY_PROVIDED => 'A BAD KEY WAS PROVIDED/CHARACTERS OUT OF RANGE',
         self::RES_INVALID_HOST_PROTOCOL => 'THE HOST TRANSPORT PROTOCOL DOES NOT MATCH THAT OF THE CLIENT',
         self::RES_SERVER_MARKED_DEAD => 'SERVER IS MARKED DEAD',
         self::RES_UNKNOWN_STAT_KEY => 'ENCOUNTERED AN UNKNOWN STAT KEY',
         self::RES_PAYLOAD_FAILURE => 'PAYLOAD FAILURE'];
      if(isset($status[$this->status]))
         return $status[$this->status];
      else
         return 'INVALID memcached_return_t';
   }

   /**
    * @param string $server_key
    * @return array
    */
   public function getServerByKey($server_key) {
      if(method_exists($this->memcache, 'findserver')) {# Only in newer memcaches
         $server = explode(':', $this->memcache->findserver($server_key));
         return ['host' => $server[0], 'port' => $server[1], 'weight' => 0];
      }else{ # just use one server and hope the best
         return reset($this->servers) + ['weight' => 0];
      }
      # Newer memcached versions do only return 0 instead of the correct weight
   }

   /**
    * @return array
    */
   public function getServerList() {
      # The weight is no longer included in newer memcached versions
      return $this->servers;
   }

   /**
    * Do not use this function when you have multiple servers, an old memcache
    * extension (< 2.0.0) and need to rely on correct information
    * @return array
    */
   public function getStats() {
      if(method_exists($this->memcache, 'getExtendedStats'))
         return $this->memcache->getExtendedStats();
      else{
         # We are unable to serve with the correct information, old memache
         # versions simply do not support this. So generate as least a result
         # that is structural identical to what we expect, although it is
         # neither complete nor necessarily correct for multiple servers...
         $server = reset($this->servers);
         return ["{$server['host']}:{$server['port']}" => $this->memcache->getStats()];
      }
   }

   /**
    * Do not use this function when you have multiple servers. It will produce
    * wrong results and will only include the version of the server memcache is
    * connected to with the key of the first-added server
    * @return array
    */
   public function getVersion() {
      # Not even new versions of memcache support getting the version by server
      $server = reset($this->servers);
      return ["{$server['host']}:{$server['port']}" => $this->memcache->getVersion()];
   }

   /**
    * @param string $key
    * @param int $offset
    * @param int $initial_value
    * @param int $expiry
    * @return boolean
    */
   public function increment($key, $offset = 1, $initial_value = 0, $expiry = 0) {
      if(($result = $this->memcache->increment($key, $offset)) === false) {
         if(!$this->memcache->set($key, $initial_value, MEMCACHE_COMPRESSED,
               $expiry)) {
            $this->status = self::RES_FAILURE;
            return false;
         }else{
            $this->status = self::RES_SUCCESS;
            return $initial_value;
         }
      }
      $this->status = self::RES_SUCCESS;
      return $result;
   }

   /**
    * Alias for increment()
    * @param string $server_key
    * @param string $key
    * @param int $offset
    * @param int $initial_value
    * @param int $expiry
    * @return boolean
    */
   public function incrementByKey($server_key, $key, $offset = 1,
      $initial_value = 0, $expiry = 0) {
      return $this->increment($key, $offset, $initial_value, $expiry);
   }

   /**
    * @return boolean
    */
   public function isPersistent() {
      return true;
   }

   /**
    * @return boolean
    */
   public function isPristine() {
      return empty($this->servers); # Mmh... not good but better than nothing
   }

   /**
    * This operation will fail, as compression is turned on by default.
    * @param string $key
    * @param string $value
    * @return boolean
    */
   public function prepend($key, $value) {
      $this->status = self::RES_SUCCESS;
      # you don't believe success status, but view the source...
      trigger_error('cannot append/prepend with compression turned on',
         E_USER_WARNING);
      return false;
   }

   /**
    * Alias for prepend()
    * @param string $server_key
    * @param string $key
    * @param string $value
    * @return boolean
    */
   public function prependByKey($server_key, $key, $value) {
      return $this->prepend($key, $value);
   }

   /**
    * @return boolean
    */
   public function quit() {
      return $this->memcache->close();
   }

   /**
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function replace($key, $value, $expiration = 0) {
      if($this->memcache->replace($key, $value, MEMCACHE_COMPRESSED, $expiration)) {
         $this->status = self::RES_SUCCESS;
         return true;
      }else{
         $this->status = self::RES_NOTSTORED;
         return false;
      }
   }

   /**
    * Alias for replace()
    * @param string $server_key
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function replaceByKey($server_key, $key, $value, $expiration = 0) {
      return $this->replace($key, $value, $expiration);
   }

   /**
    * @return boolean
    */
   public function resetServerList() {
      if(empty($this->servers))
         return true;
      else{
         if(!$this->memcache->close())
            return false;
         if(isset($this->servers[2]))
            $this->memcache = new Memcache();
         $this->servers = [];
         return true;
      }
   }

   /**
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function set($key, $value, $expiration = 0) {
      if($this->memcache->set($key, $value, MEMCACHE_COMPRESSED, $expiration)) {
         $this->status = self::RES_SUCCESS;
         return true;
      }else{
         $this->status = self::RES_FAILURE;
         return false;
      }
   }

   /**
    * Alias for set()
    * @param string $server_key
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function setByKey($server_key, $key, $value, $expiration = 0) {
      return $this->set($key, $value, $expiration);
   }

   /**
    * @param array $items
    * @param int $expiration
    * @return boolean
    */
   public function setMulti(array $items, $expiration = 0) {
      foreach($items as $key => $value)
         if(!$this->memcache->set($key, $value, MEMCACHE_COMPRESSED, $expiration)) {
            $this->status = self::RES_FAILURE;
            return false;
         }
      $this->status = self::RES_SUCCESS;
      return true;
   }

   /**
    * Alias for setMulti()
    * @param string $server_key
    * @param array $items
    * @param int $expiration
    * @return boolean
    */
   public function setMultiByKey($server_key, array $items, $expiration = 0) {
      return $this->setMulti($items, $expiration);
   }

   /**
    * @param string $option
    * @param string $value
    * @return boolean
    */
   public function setOption($option, $value) {
      switch($option) {
         case self::OPT_HASH:
            if($value === 'fnv')
               ini_set('memcache.hash_function', 'fnv');
            else
               ini_set('memcache.hash_function', 'crc32');
            break;
         case self::OPT_BINARY_PROTOCOL:
            if($value)
               ini_set('memcache.protocol', 'binary');
            else
               ini_get('memcache.protocol', 'ascii');
            break;
         case self::OPT_SOCKET_SEND_SIZE:
         case self::OPT_SOCKET_RECV_SIZE:
            ini_set('memcache.chunk_size', (int)$value);
            break;
         default: # Memcache does not allow much configuration...
            $this->status = self::RES_BAD_KEY_PROVIDED;
            return false;
      }
      $this->status = self::RES_SUCCESS;
      return true;
   }

   /**
    * @param array $options
    * @return boolean
    */
   public function setOptions(array $options) {
      $failed = false;
      foreach($options as $option => $value)
         $failed |=!$this->setOption($option, $value);
      if($failed)
         $this->status = self::RES_BAD_KEY_PROVIDED;
      # success will be set by setOption
      return $failed;
   }

   /**
    * SASL is not supported by memcache and there's no way to emulate it. You
    * have to use memcached if you have to rely on SASL.
    * @param string $username
    * @param string $password
    * @throws BadMethodCallException
    */
   public function setSaslAuthData($username, $password) {
      throw new BadMethodCallException(I18N::getInternalLocale()->get(['memcachedWrapper',
         'saslUnsupported']));
   }

   /**
    * This function is not thread-safe!
    * @param string $key
    * @param int $expiration
    * @return boolean
    */
   public function touch($key, $expiration) {
      # This is potentially dangerous as this is definitely not atomic. But the
      # only way to overcome this would be to wrap all set routines in a
      # semaphore, which would drastically impact speed. Once again, consider
      # using the Memcached library with a current version of the server (as
      # touch is not supported in older versions)
      if(!$this->memcache->replace($key, @$this->memcache->get($key),
            MEMCACHE_COMPRESSED, $expiration)) {
         $this->status = self::RES_FAILED;
         return false;
      }else{
         $this->status = self::RES_SUCCESS;
         return true;
      }
   }

   /**
    * Alias for touch()
    * @param string $server_key
    * @param string $key
    * @param int $expiration
    * @return boolean
    */
   public function touchByKey($server_key, $key, $expiration) {
      return $this->touch($key, $expiration);
   }

}
