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
 * This class will simulate the behavior of the new Memcached extension by using
 * plain PHP socket functions.
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
    * @var resource
    */
   private $sock, $asciiSock;
   private $persistent, $pristine = false;
   private $status;
   private $servers = [], $currentServer;

   private function sendPacket($opcode, $extras, $key, $value, $cas) {
      if(!isset($this->sock) && !$this->establishConnection())
         return false;
      # Header:
      # Byte/       0       |       1       |       2       |       3       |
      #     |0 1 2 3 4 5 6 7|0 1 2 3 4 5 6 7|0 1 2 3 4 5 6 7|0 1 2 3 4 5 6 7|
      #     +---------------+---------------+---------------+---------------+
      #    0| Magic         | Opcode        | Key length                    |
      #     +---------------+---------------+---------------+---------------+
      #    4| Extras length | Data type     | Reserved                      |
      #     +---------------+---------------+---------------+---------------+
      #    8| Total body length                                             |
      #     +---------------+---------------+---------------+---------------+
      #   12| Opaque                                                        |
      #     +---------------+---------------+---------------+---------------+
      #   16| CAS                                                           |
      #     |                                                               |
      #     +---------------+---------------+---------------+---------------+
      # Magic number:      0x80 (Request), 0x81 (Response)
      # Opcode:            $opcode
      # Key Length:        Length in bytes of $key
      # Extras length:     Length in bytes of $extras
      # Data type:         not used, 0x00
      # Total body length: Length in bytes of $extras + $key + $value
      # Opaque:            Will be copied back in the response
      # CAS:               $cas
      $keyLen = strlen($key);
      $extrasLen = strlen($extras);
      if(@fwrite($this->sock,
            pack('CCnCxxxNxxxxNN', 0x80, $opcode, $keyLen, $extrasLen,
               $keyLen + $extrasLen + strlen($value),
               ($cas & 0xFFFFFFFF00000000) >> 32, $cas & 0xFFFFFFFF)) === false) {
         $this->status = self::RES_WRITE_FAILURE;
         return false;
      }
      return @fwrite($this->sock, $extras . $key . $value) !== false;
   }

   private function receivePacket() {
      if(($header = @fread($this->sock, 24)) === false) {
         $this->status = self::RES_UNKNOWN_READ_FAILURE;
         return false;
      }
      $header = unpack('CMagic/COpcode/nKeyLen/CExtraLen/CDatatype/nStatus/NBodyLen/NOpaque/NCASHigh/NCASLow',
         $header);
      if($header['Magic'] !== 0x81) {
         $this->status = self::RES_PROTOCOL_ERROR;
         Log::reportInternalError(I18N::getInternalLanguage()->get(['memcachedStandalone',
               'invalidProtocol']), true);
         return false;
      }
      $extra = ($header['ExtraLen'] > 0) ? @fread($this->sock,
            $header['ExtraLen']) : '';
      $key = ($header['KeyLen'] > 0) ? @fread($this->sock, $header['KeyLen']) : '';
      $valLen = $header['BodyLen'] - $header['ExtraLen'] - $header['KeyLen'];
      $value = ($valLen > 0) ? @fread($this->sock, $valLen) : '';
      $cas = $header['CASLow'] | ($header['CASHigh'] << 32);
      return ['opcode' => $header['Opcode'], 'status' => $header['Status'],
         'extra' => $extra, 'key' => $key, 'value' => $value, 'cas' => $cas];
   }

   private function receive($opcode) {
      if(($return = $this->receivePacket()) === false)
         return false;
      if($return['opcode'] !== $opcode) {
         $this->status = self::RES_PROTOCOL_ERROR;
         Log::reportInternalError(I18N::getInternalLanguage()->get(['memcachedStandalone',
               'invalidProtocol']), true);
         return false;
      }
      switch($return['status']) {
         case 0: # Ok
            $this->status = self::RES_SUCCESS;
            return $return;
         case 0x01: # Key not found
            $this->status = self::RES_NOTFOUND;
            return false;
         case 0x02: # Key exists
            $this->status = self::RES_NOTSTORED;
            return false;
         case 0x03: # Value too large
            $this->status = self::RES_MEMORY_ALLOCATION_FAILURE;
            return false;
         case 0x04: # Invalid argument
            $this->status = self::RES_NOT_SUPPORTED;
            return false;
         case 0x05: # Item not stored
            $this->status = self::RES_NOTSTORED;
            return false;
         case 0x06: # Incr/Decr on non-numeric value
            $this->status = self::RES_FAILURE;
            return false;
         case 0x81: # Unknown command
            $this->status = self::RES_PROTOCOL_ERROR;
            return false;
         case 0x82: # Out of memory
            $this->status = self::RES_MEMORY_ALLOCATION_FAILURE;
            return false;
         default:
            $this->status = self::RES_PROTOCOL_ERROR;
            Log::reportInternalError(I18N::getInternalLanguage()->get(['memcachedStandalone',
                  'invalidProtocol']), true);
            return false;
      }
   }

   private function validateKey($key) {
      # We will be quite restrictive here. Memcached will only check for empty
      # strings and spaces; however, control characters are not allowed either
      if(preg_match('/^$|[\\x00-\\x20]/', $key) === 1) {
         $this->status = self::RES_BAD_KEY_PROVIDED;
         return false;
      }
      return true;
   }

   private function establishConnection($ascii = false) {
      usort($this->servers,
         function(array $a, array $b) {
         return (isset($a['weight']) ? $a['weight'] : 1) - #
            (isset($b['weight']) ? $b['weight'] : 1);
      });
      if($ascii) {
         foreach($this->servers as $server)
            if(($this->asciiSock = @fsockopen($server['host'], $server['port'])) !== false)
               return true;
         return false;
      }
      $func = $this->persistent ? 'pfsockopen' : 'fsockopen';
      foreach($this->servers as $server)
         if(($this->sock = @$func($server['host'], $server['port'])) !== false) {
            $this->currentServer = $server;
            $this->pristine = @ftell($this->sock) === 0; # Not superb, but ok
            return true;
         }
      $this->status = self::RES_NO_SERVERS;
      $this->currentServer = null;
      return false;
   }

   /**
    * @param string $persistent_id
    */
   public function __construct($persistent_id = null) {
      $this->persistent = isset($persistent_id);
   }

   public function __destruct() {
      if(isset($this->sock))
         @fclose($this->sock);
      if(isset($this->asciiSock))
         @fclose($this->asciiSock);
   }

   /**
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function add($key, $value, $expiration = 0) {
      if(!$this->validateKey($key))
         return false;
      if(!$this->sendPacket(0x02, pack('xxxxN', (int)$expiration), $key, $value,
            null))
         return false;
      return $this->receive(0x02) !== false;
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
      $this->servers[] = ['host' => (string)$host, 'port' => (int)$port, 'weight' => (int)$weight];
      $this->status = self::RES_SUCCESS;
      return true;
   }

   /**
    * @param array $servers
    */
   public function addServers(array $servers) {
      $i = 1;
      foreach($servers as $server) {
         if(($entrySize = count($server)) > 1) {
            reset($server);
            if(($host = each($server)) === false) {
               trigger_error("could not get server host for entry #$i",
                  E_USER_WARNING);
               continue;
            }
            if(($port = each($server)) === false) {
               trigger_error("could not get server port for entry #$i",
                  E_USER_WARNING);
               continue;
            }
            if($entrySize > 2) {
               if(($weight = each($server)) === false) {
                  trigger_error("could not get server weight for entry #$i",
                     E_USER_WARNING);
                  continue;
               }
            }else
               $weight = ['value' => 0];
            $this->servers[] = ['host' => (string)$host['value'],
               'port' => (int)$port['value'], 'weight' => (int)$weight['value']];
         }
         ++$i;
      }
      $this->status = self::RES_SUCCESS;
      return true;
   }

   /**
    * @param string $key
    * @param string $value
    * @return boolean
    */
   public function append($key, $value) {
      if(!$this->validateKey($key))
         return false;
      if(!$this->sendPacket(0x0E, null, $key, $value, null))
         return false;
      return $this->receive(0x0E) !== false;
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

   /**
    * @param double $cas_token
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function cas($cas_token, $key, $value, $expiration = 0) {
      if(!$this->validateKey($key))
         return false;
      if(!$this->sendPacket(0x01, pack('xxxxN', (int)$expiration), $key, $value,
            (double)$cas_token))
         return false;
      return $this->receive(0x01) !== false;
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
      if(!$this->validateKey($key))
         return false;
      $offset = (int)$offset;
      if($offset < 0) {
         trigger_error('offset has to be > 0', E_USER_WARNING);
         return false;
      }
      if(func_num_args() < 3) {
         # We do not have an initial value and no expiry date. So we set the
         # value of expiration to 0xFFFFFFFF; this will make the server fail
         # if the key did not exist before.
         if(!$this->sendPacket(0x06,
               pack('NNxxxxxxxxN', ($offset & 0xFFFFFFFF00000000) >> 32,
                  $offset & 0xFFFFFFFF, 0xFFFFFFFF), $key, null, null))
            return false;
      }elseif(!$this->sendPacket(0x06,
            pack('NNNNN', ($offset & 0xFFFFFFFF00000000) >> 32,
               $offset & 0xFFFFFFFF,
               ($initial_value & 0xFFFFFFFF00000000) >> 32,
               $initial_value & 0xFFFFFFFF, $expiry), $key, null, null))
         return false;
      if(($response = $this->receive(0x06)) === false)
         return false;
      $result = unpack('NHigh/NLow', $response['value']);
      return $result['High'] << 32 | $result['Low'];
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
      if(!$this->validateKey($key))
         return false;
      if($time !== 0) {
         # Actually, Memcached does not support delayed deletion any more.
         # Support for this has been dropped of both protocols, text and binary.
         $this->status = self::RES_PROTOCOL_ERROR;
         return false;
      }
      if(!$this->sendPacket(0x04, null, $key, null, null))
         return false;
      return $this->receive(0x04) !== false;
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
      if($time !== 0) {
         # Actually, Memcached does not support delayed deletion any more.
         # Support for this has been dropped of both protocols, text and binary.
         $this->status = self::RES_PROTOCOL_ERROR;
         return false;
      }
      $success = false;
      $invalidKey = false;
      foreach($keys as $key) {
         if($this->validateKey($key)) {
            if($this->sendPacket(0x04, null, $key, null, null))
               $success |= $this->receive(0x04) !== false;
         }else
            $invalidKey = true;
      }
      if($invalidKey)
         $this->status = self::RES_SOME_ERRORS;
      elseif($success)
         $this->status = self::RES_SUCCESS;
      else
         $this->status = self::RES_NOTFOUND;
      return (bool)$success;
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

   /**
    * @param int $delay
    * @return boolean
    */
   public function flush($delay = 0) {
      if($delay === 0) {
         if(!$this->sendPacket(0x08, null, null, null, null))
            return false;
      }elseif(!$this->sendPacket(0x08, pack('N', $delay), null, null, null))
         return false;
      return $this->receive(0x08) !== false;
   }

   /**
    * @param string $key
    * @param callable $cache_cb
    * @param double &$cas_token
    * @return string|boolean
    */
   public function get($key, callable $cache_cb = null, &$cas_token = null) {
      if(!$this->validateKey($key))
         return false;
      if(!$this->sendPacket(0x00, null, $key, null, null))
         return false;
      $response = $this->receive(0x00);
      if(isset($cache_cb) && $this->status === self::RES_NOTFOUND) {
         $cas_token = 0.0;
         # We will now perform the read-through callback. All predefined
         # values are set as in the native Memcached extension.
         $value = null;
         # While it is undocumented and never mentioned, Memcached actually
         # passes four parameters: expiration time is the fourth
         $expiration = 0;
         if($cache_cb($this, $key, $value) && $this->set($key, $value,
               $expiration)) {
            $this->status = self::RES_SUCCESS;
            return $value;
         }else{
            $this->status = self::RES_NOTFOUND;
            return false;
         }
      }
      if($response === false)
         return false;
      $cas_token = $response['cas'];
      return $response['value'];
   }

   /**
    * @return array
    */
   public function getAllKeys() {
      # Binary protocol does not allow to retrieve all keys. As libmemcached
      # allows to switch to ASCII protocol, we should support this nevertheless.
      # As there is no way to switch from binary to ASCII when the connection
      # is already open, we have to open another connection (which cannot make
      # use of permanence, as this would not be a "new connection") and then
      # only send commands in the ASCII protocol.
      # Note however that even this way of retrieval is marked as non-standard
      # in the protocol spec and can change...
      if(!isset($this->asciiSock) && !$this->establishConnection(true))
         return false;
      # libmemcached simply iterates over all 200 possible slabs. But as
      # Memcached does not indicate when no further slabs exist, performance is
      # very poor when there are not very much slabs. So instead, we will gather
      # all slabs before and then only query those which exist. There are
      # basically two ways: 'stats items' and 'stats slabs'. However, the items
      # version returns less lines with superfluous information.
      if(@fwrite($this->asciiSock, "stats items\r\n") === false) {
         $this->status = self::RES_WRITE_FAILURE;
         return false;
      }
      $slabs = [];
      while(($slab = @fgets($this->asciiSock)) !== false) {
         if($slab === "END\r\n")
            break;
         $slab = explode(':', $slab);
         if(!isset($slab[1]) || $slab[0] !== 'STAT items')
            continue;
         $slabs[(int)$slab[1]] = true;
      }
      $keys = [];
      foreach($slabs as $slab => $unused) {
         if(@fwrite($this->asciiSock, "stats cachedump $slab 0\r\n") === false) {
            $this->status = self::RES_WRITE_FAILURE;
            return false;
         }
         while(($line = @fgets($this->asciiSock)) !== false) {
            if($line === "END\r\n")
               continue 2;
            # ITEM key_<id> [<bytes> b; <timestamp> s]
            $parts = explode(' ', $line, 3);
            if(!isset($parts[1]) || $parts[0] !== 'ITEM')
               continue;
            $keys[] = $parts[1];
         }
      }
      return $keys;
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
      # As the protocol specification says, we will send quiet get commands
      # followed by a noop. Note that this function will not be left until all
      # keys were retrieved. However, this is exactly what Memcached does as
      # well. Note once more that we do really only call getMulti.
      $cas = [];
      if(($data = ($with_cas ? $this->getMulti($keys, $cas) : $this->getMulti($keys))) === false)
         return false;
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
      $keys = array_filter($keys, [$this, 'validateKey']);
      if(empty($keys)) {
         $this->status = self::RES_BAD_KEY_PROVIDED;
         return false;
      }
      # Actually, this is more advanced than Memcached's implementation. We do
      # yield a get command for every key, but instead we work with quiet
      # commands.
      foreach($keys as $idx => $key)
         if(!$this->sendPacket(0x0D, null, $key, null, null))
            unset($keys[$idx]);
      if(!$this->sendPacket(0x0A, null, null, null, null) && !empty($keys)) {
         # We have sent at least one quiet message, but could not trigger the
         # responses. If any of the next requests will not fail, the responses
         # will be completely unexpected. Let's instead close the connection.
         $this->quit();
         $this->status = self::RES_WRITE_FAILURE;
         return false;
      }
      $result = [];
      # The items will always be in the correct order, so we don't need to check
      # for $flags & self::GET_PRESERVE_ORDER
      foreach($keys as $key)
         if(($response = $this->receive(0x0D)) !== false) {
            $result[$key] = $response['value'];
            $cas_tokens[$key] = $response['cas'];
         }
      $this->receive(0x0A);
      $this->status = self::RES_SUCCESS;
      return $result;
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
            return false;
         case self::OPT_SERIALIZER:
            return self::SERIALIZER_PHP;
         case self::OPT_PREFIX_KEY:
            return '';
         case self::OPT_HASH:
            return self::HASH_DEFAULT;
         case self::OPT_DISTRIBUTION:
            return self::DISTRIBUTION_MODULA;
         case self::OPT_BUFFER_WRITES:
            return false;
         case self::OPT_BINARY_PROTOCOL:
            return true;
         case self::OPT_NO_BLOCK:
         case self::OPT_TCP_NODELAY:
            return false;
         case self::OPT_SOCKET_SEND_SIZE:
         case self::OPT_SOCKET_RECV_SIZE:
            return 1024; # This is just a dummy size - we always send everything
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
      # Note that this script only connects to a single Memcached server: the
      # one with the greatest weight. So any server key will lead to the one
      # and only server.
      if(!$this->validateKey($server_key))
         return false;
      $this->status = self::RES_SUCCESS;
      if(!isset($this->currentServer) && !$this->establishConnection())
         return false;# Even here, we return success
      if(isset($this->currentServer)) {
         # Newer memcached versions do only return 0 instead of the correct weight
         $return = $this->currentServer;
         $return['weight'] = 0;
         return $return;
      }
      return false;
   }

   /**
    * @return array
    */
   public function getServerList() {
      # Weight is not returned any more
      $servers = $this->servers;
      foreach($servers as &$server)
         unset($server['weight']);
      return $servers;
   }

   /**
    * @return array
    */
   public function getStats() {
      # We retrieve the stats before, so now we know there is a server.
      if(!$this->sendPacket(0x10, null, null, null, null))
         return false;
      $stats = [];
      while(true) {
         if(($packet = $this->receive(0x10)) === false) {
            # We don't know why it failed; maybe the server continues to
            # complete our request...
            while(!@feof($this->sock))
               @fread($this->sock, 24);
            return false;
         }
         if($packet['key'] === '' && $packet['value'] === '')
            break;
         $stats[$packet['key']] = $packet['value'];
      }
      $server = $this->currentServer;
      return ["{$server['host']}:{$server['port']}" => $stats];
   }

   /**
    * @return array
    */
   public function getVersion() {
      if(!$this->sendPacket(0x0B, null, null, null, null))
         return false;
      if(($result = $this->receive(0x0B)) === false)
         return false;
      $server = $this->currentServer;
      return ["{$server['host']}:{$server['port']}" => $result['value']];
   }

   /**
    * @param string $key
    * @param int $offset
    * @param int $initial_value
    * @param int $expiry
    * @return boolean
    */
   public function increment($key, $offset = 1, $initial_value = 0, $expiry = 0) {
      if(!$this->validateKey($key))
         return false;
      $offset = (int)$offset;
      if($offset < 0) {
         trigger_error('offset has to be > 0', E_USER_WARNING);
         return false;
      }
      if(func_num_args() < 3) {
         # We do not have an initial value and no expiry date. So we set the
         # value of expiration to 0xFFFFFFFF; this will make the server fail
         # if the key did not exist before.
         if(!$this->sendPacket(0x05,
               pack('NNxxxxxxxxN', ($offset & 0xFFFFFFFF00000000) >> 32,
                  $offset & 0xFFFFFFFF, 0xFFFFFFFF), $key, null, null))
            return false;
      }elseif(!$this->sendPacket(0x05,
            pack('NNNNN', ($offset & 0xFFFFFFFF00000000) >> 32,
               $offset & 0xFFFFFFFF,
               ($initial_value & 0xFFFFFFFF00000000) >> 32,
               $initial_value & 0xFFFFFFFF, $expiry), $key, null, null))
         return false;
      if(($response = $this->receive(0x05)) === false)
         return false;
      $result = unpack('NHigh/NLow', $response['value']);
      return $result['High'] << 32 | $result['Low'];
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
      return $this->persistent;
   }

   /**
    * @return boolean
    */
   public function isPristine() {
      if(!isset($this->sock) && !$this->establishConnection())
         return false;
      return $this->pristine;
   }

   /**
    * @param string $key
    * @param string $value
    * @return boolean
    */
   public function prepend($key, $value) {
      if(!$this->validateKey($key))
         return false;
      if(!$this->sendPacket(0x0F, null, $key, $value, null))
         return false;
      return $this->receive(0x0F) !== false;
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
      if(isset($this->asciiSock)) {
         @fclose($this->asciiSock);
         $this->asciiSock = null;
      }
      if(!isset($this->sock))
         return true;
      if(!@fclose($this->sock))
         return false;
      $this->sock = $this->currentServer = null;
      return true;
   }

   /**
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return boolean
    */
   public function replace($key, $value, $expiration = 0) {
      if(!$this->validateKey($key))
         return false;
      if(!$this->sendPacket(0x03, pack('xxxxN', (int)$expiration), $key, $value,
            null))
         return false;
      return $this->receive(0x03) !== false;
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
         if(!$this->quit())
            return false;
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
      if(!$this->validateKey($key))
         return false;
      if(!$this->sendPacket(0x01, pack('xxxxN', (int)$expiration), $key, $value,
            null))
         return false;
      return $this->receive(0x01) !== false;
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
      foreach($items as $key => $value) {
         # Memcached really iterates over the array (and there is not key
         # checking)
         if(!$this->validateKey($key))
            return false;
         if(!$this->sendPacket(0x01, pack('xxxxN', (int)$expiration), $key,
               $value, null))
            return false;
         if($this->receive(0x01) === false)
            return false;
      }
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
      # We don't support any options. But let's assume the operation would be
      # successful in Memcached.
      $this->status = self::RES_SUCCESS;
      return true;
   }

   /**
    * @param array $options
    * @return boolean
    */
   public function setOptions(array $options) {
      # No options...
      $this->status = self::RES_SUCCESS;
      return true;
   }

   /**
    * SASL is not supported by this replacement. You have to use memcached if
    * you have to rely on SASL.
    * @param string $username
    * @param string $password
    * @throws BadMethodCallException
    */
   public function setSaslAuthData($username, $password) {
      # SASL is only supported in Memcached with binary protocol; you should
      # really consider to use the proper Memcached extension if you need SASL
      throw new BadMethodCallException(I18N::getInternalLanguage()->get(['memcachedStandalone',
         'saslUnsupported']));
   }

   /**
    * @param string $key
    * @param int $expiration
    * @return boolean
    */
   public function touch($key, $expiration) {
      if(!$this->validateKey($key))
         return false;
      # Note that this command is still undocumented and may only be available
      # in newer server versions.
      if(!$this->sendPacket(0x1C, pack('N', $expiration), $key, null, null))
         return false;
      return $this->receive(0x1C) !== false;
      # We won't try again in ASCII mode, although TOUCH is documented for this
      # protocol. This is because binary touch was added in morning of
      # 2011-09-27 to Memcached server (and is yet still undocumented), but
      # ASCII touch was added in the evening of the same day. So any server
      # which supports ASCII has to support binary as well.
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
