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

   if(CLI) {

      /**
       * This class provides all functionalities for dealing with objects and values
       * that have to persist in a longer context than the current request.
       */
      abstract class Session {

         /**
          * Holds a memcached object (or memcached wrapper, if you use the old
          * memcache library) which is connected according to the
          * memcachedConnection configuration.
          * Null, if there is no given memcachedConnection.
          * If there was no memcached extension found, a php-only fallback is used.
          * This should not be considered speedy...
          * @var Memcached
          */
         public static $memcached;

         /**
          * Holds a redis object (or predis, if you use the PHP library) which
          * is connected as specified in the redisConnection configuration.
          * Null, if there is no redisConnection given.
          * @var RedisArray|Predis
          */
         public static $redis;

         private final function __construct() {

         }

         public static final function __callStatic($name, $arguments) {
            throw new SessionException(I18N::getInternalLanguage()->get(['session', 'cli']));
         }

      }

   }else{

      /**
       * This class provides all functionalities for dealing with objects and values
       * that have to persist in a longer context than the current request.
       */
      abstract class Session {

         private static $extendedFlashs = [];
         private static $constants = [];

         /**
          * Contains all unserialized session data
          * @var mixed[]
          */
         private static $sessionStatic = [], $sessionFlash = [];

         /**
          * Holds a memcached object (or memcached wrapper, if you use the old
          * memcache library) which is connected as specified in the
          * memcachedConnection configuration.
          * Null, if there is no memcachedConnection given.
          * If there was no memcached extension found, a php-only fallback is
          * used. This should not be considered speedy...
          * @var Memcached
          */
         public static $memcached;

         /**
          * Holds a redis object (or predis, if you use the PHP library) which
          * is connected as specified in the redisConnection configuration.
          * Null, if there is no redisConnection given.
          * @var RedisArray|Predis
          */
         public static $redis;

         private final function __construct() {

         }

         /**
          * Gets a session variable
          * @param string $name The variable name
          * @param mixed $default A default value that will be returned if the
          *    requested variable is not accessible
          * @return mixed
          */
         public static function get($name, $default = null) {
            $sname = "fim$name";
            if(isset($_SESSION[$sname])) {
               self::$sessionStatic[$name] = fimUnserialize($_SESSION[$sname]);
               unset($_SESSION[$sname]);
            }
            if(isset(self::$sessionStatic[$name]) || array_key_exists($name,
                  self::$sessionStatic))
               return self::$sessionStatic[$name];
            else
               return $default;
         }

         /**
          * Checks whether a specific value is stored within a session
          * @param string $name The variable name
          * @return bool
          */
         public static function has($name) {
            # array_key_exists is not necessary in $_SESSION, as null can never
            # be hold in the $_SESSION array; only serialized values are stored
            # here
            return isset($_SESSION["fim$name"]) || isset(self::$sessionStatic[$name]) || array_key_exists($name,
                  self::$sessionStatic);
         }

         /**
          * Sets a session variable
          * @param string $name The variable name
          * @param mixed $value The variable value
          * @param bool $overwrite (default true) Set this to false if you do not want
          *    to overwrite an existing variable of the same name
          */
         public static function set($name, $value, $overwrite = true) {
            $sname = "fim$name";
            if(!$overwrite && (isset($_SESSION[$sname]) || isset(self::$sessionStatic[$name]) || array_key_exists($name,
                  self::$sessionStatic)))
               return;
            unset($_SESSION[$sname]);
            self::$sessionStatic[$name] = $value;
         }

         /**
          * Removes a session variable
          * @param string $name The variable name
          */
         public static function delete($name) {
            unset($_SESSION["fim$name"], self::$sessionStatic[$name]);
         }

         /**
          * Removes all the session variables
          * @param bool $onlyStatic (default true) If false, flash variables will be
          *    deleted too. If true, only static variables will be removed.
          */
         public static function clear($onlyStatic = true) {
            foreach($_SESSION as $key => $value)
               if((($str = substr($key, 0, 3)) === 'fim') || (!$onlyStatic && $str === 'Fim'))
                  unset($_SESSION[$key]);
            self::$sessionStatic = [];
            if(!$onlyStatic) {
               self::$sessionFlash = [];
               self::$extendedFlashs = [];
            }
         }

         /**
          * Gets a temporary session variable
          * @param string $name The variable name
          * @param mixed $default A default value that will be returned if the
          *    requested variable is not accessible
          * @param boolean $extend Automatically call extendFlash() for this variable
          * @return mixed
          */
         public static function getFlash($name, $default = null, $extend = false) {
            $sname = "Fim$name";
            if(isset($_SESSION[$sname])) {
               self::$sessionFlash[$name] = fimUnserialize($_SESSION[$sname]);
               unset($_SESSION[$sname]);
            }
            if(isset(self::$sessionFlash[$name]) || array_key_exists($name,
                  self::$sessionFlash))
               return self::$sessionFlash[$name];
            else
               return $default;
            if($extend)
               self::$extendedFlashs[$name] = true;
         }

         /**
          * Checks whether a specific temporary value is stored within a session
          * @param string $name The variable name
          * @return bool
          */
         public static function hasFlash($name) {
            return isset($_SESSION["Fim$name"]) || isset(self::$sessionFlash[$name]) || array_key_exists($name,
                  self::$sessionFlash);
         }

         /**
          * Sets a temporary session variable that will only be accessible within the
          * very next request
          * @param string $name The variable name
          * @param mixed $value The variable value
          * @param bool $overwrite (default true) Set this to false if you do not want
          *    to overwrite an existing variable of the same name
          */
         public static function setFlash($name, $value, $overwrite = true) {
            $sname = "Fim$name";
            if(!$overwrite && (isset($_SESSION[$sname]) || isset(self::$sessionFlash[$name]) || array_key_exists($name,
                  self::$sessionFlash)))
               return;
            unset($_SESSION[$sname]);
            self::$sessionFlash[$name] = $value;
            self::$extendedFlashs[$name] = true;
         }

         /**
          * Removes a temporary session variable
          * @param string $name The variable name
          */
         public static function deleteFlash($name) {
            unset($_SESSION["Fim$name"], self::$sessionFlash[$name],
               self::$extendedFlashs[$name]);
         }

         /**
          * Extends the lifetime of a temporary session variable so it will stay one
          * more request alilfe
          * @param string $name The variable name
          */
         public static function extendFlash($name) {
            self::$extendedFlashs[$name] = true;
         }

         /**
          * Extends the lifetime of all temporary session variables
          */
         public static function extendFlashs() {
            foreach($_SESSION as $key => $value)
               if(substr($key, 0, 3) === 'Fim')
                  self::$extendedFlashs[substr($key, 3)] = true;
            self::$extendedFlashs += array_fill_keys(array_keys(self::$sessionFlash),
               true);
         }

         /**
          * Clears all flash variables that are not extended.
          */
         private static function cleanupFlashs() {
            foreach($_SESSION as $key => $value)
               if((substr($key, 0, 3) === 'Fim') && !isset(self::$extendedFlashs[substr($key,
                        3)]))
                  unset($_SESSION[$key]);
            self::$sessionFlash = array_intersect_key(self::$sessionFlash,
               self::$extendedFlashs);
         }

         /**
          * Gets a FIM constant
          * @param string $name The constant's name
          * @param mixed $default A default value that will be returned if the
          *    requested constant is not accessible
          * @return mixed
          */
         public static function getConst($name, $default = null) {
            if(isset(self::$constants[$name]) || array_key_exists($name,
                  self::$constants))
               return self::$constants[$name];
            else
               return $default;
         }

         /**
          * Checks whether a specific constant is known to FIM
          * @param string $name The variable name
          * @return bool
          */
         public static function hasConst($name) {
            return isset(self::$constants[$name]) || array_key_exists($name,
                  self::$constants);
         }

         /**
          * Sets a FIM constant
          * @param string $name The constant's name
          * @param mixed $value The constant's value
          * @param bool $overwrite (default true) Set this to false if you do not want
          *    to overwrite an existing constant of the same name
          */
         public static function setConst($name, $value, $overwrite = true) {
            if($overwrite || !isset(self::$constants[$name]) || !array_key_exists($name,
                  self::$constants))
               self::$constants[$name] = $value;
         }

         /**
          * Removes a FIM constant
          * @param string $name The constant's name
          */
         public static function deleteConst($name) {
            unset(self::$constants[$name]);
         }

         /**
          * @return string Returns the current session id
          */
         public static function getId() {
            return session_id();
         }

         /**
          * Renews the session id with respect to the session transition. Renewal
          * is not possible when the current session is a transition session which
          * was already replaced by another one.
          * @return string|false The new session id
          */
         public static function renewId() {
            if(isset($_SESSION['FIMINVALIDATE']) && $_SESSION['FIMINVALIDATE'] < 0)
               return false;
            $transition = Config::get('sessionTransition');
            if($transition === 0)
               session_regenerate_id(true);
            else{
               $_SESSION['FIMINVALIDATE'] = -(time() + $transition);
               session_write_close();
               session_start();
               session_regenerate_id();
            }
            $lifetime = Config::get('sessionLifetime');
            if($lifetime === 0)
               unset($_SESSION['FIMINVALIDATE']);
            else
               $_SESSION['FIMINVALIDATE'] = time() + $lifetime;
            return session_id();
         }

         /**
          * Replaces the current session id with a new one.
          * The session's lifetime will be set appropriately.
          * It will not be possible to access the session under its old id.
          * @param string $newId
          * @return bool True on success, false when the current session is a
          *    transition session which was already replaced by another one.
          */
         public static function replaceId($newId) {
            if(isset($_SESSION['FIMINVALIDATE']) && $_SESSION['FIMINVALIDATE'] < 0)
               return false;
            $oldID = session_id();
            $oldData = $_SESSION;
            session_unset();
            $file = session_save_path() . "/sess_$oldID";
            session_destroy();
            @unlink($file);
            session_id($newId);
            session_start();
            $_SESSION = $oldData;
            $lifetime = Config::get('sessionLifetime');
            if($lifetime !== 0)
               $_SESSION['FIMINVALIDATE'] = time() + $lifetime;
            else
               unset($_SESSION['FIMINVALIDATE']);
            return true;
         }

         /**
          * Sets the lifetime of the session cookie used by the framework. A lifetime
          * of zero means that the cookie expires when the browser window closes
          * (default). All other values are in seconds, calculated from now. This
          * does not have anything to do with the session configuration.
          * @param int $lifetime
          * @return bool True on success, false when the current session is a
          *    transition session which was already replaced by another one.
          */
         public static function setLifetime($lifetime = 0) {
            if(isset($_SESSION['FIMINVALIDATE']) && $_SESSION['FIMINVALIDATE'] < 0)
               return false;
            session_set_cookie_params($lifetime, BaseDir, '');
            setcookie(session_name(), session_id(),
               ($lifetime === 0) ? 0 : time() + $lifetime, BaseDir, null,
               Config::get('requireSecure'));
            return true;
         }

      }

   }
}

namespace fim {

   class MemcachedSession implements \SessionHandlerInterface {

      public function close() {
         return true;
      }

      public function destroy($session_id) {
         return \Session::$memcached->delete("fimSession$session_id");
      }

      public function gc($maxlifetime) {
         $maxlifetime = time() - $maxlifetime;
         \Session::$memcached->getDelayed(preg_grep('/^fimSession.*/',
               \Session::$memcached->getAllKeys()), false,
            function(\Memcached $mc, array $data) use($maxlifetime) {
            if(unpack('NTime', $data['data'])['Time'] > $maxlifetime)
               $mc->delete($data['key']);
         });
         return true;
      }

      public function open($save_path, $name) {
         return true;
      }

      public function read($session_id) {
         if(($result = \Session::$memcached->get("fimSession$session_id")) === false)
            return '';
         else
            return substr($result, 4);
      }

      public function write($session_id, $session_data) {
         return \Session::$memcached->set("fimSession$session_id",
               pack('N', time()) . $session_data);
      }

   }

   class RedisSession implements \SessionHandlerInterface {

      public function close() {
         return true;
      }

      public function destroy($session_id) {
         return \Session::$redis->hdel('fimSession', $session_id);
      }

      public function gc($maxlifetime) {
         $maxlifetime = time() - $maxlifetime;
         foreach(\Session::$redis->hgetall('fimSession') as $key => $value)
            if(unpack('NTime', $value)['Time'] > $maxlifetime)
               \Session::$redis->hdel('fimSession', $key);
         return true;
      }

      public function open($save_path, $name) {
         return true;
      }

      public function read($session_id) {
         if(($result = \Session::$redis->hget('fimSession', $session_id)) === false)
            return '';
         else
            return substr($result, 4);
      }

      public function write($session_id, $session_data) {
         return \Session::$redis->hset('fimSession', $session_id,
               pack('N', time()) . $session_data);
      }

   }

   function sessionInitialize() {
      $memcachedConnection = \Config::get('memcachedConnection');
      if(!empty($memcachedConnection)) {
         if(class_exists('\\Memcached', false)) {
            # The new MemcacheD extension is loaded
            \Session::$memcached = $memcached = new \Memcached('fim');
            # We will only add new servers if the list is empty. This might not
            # be the case because we use Memcached persistancy.
            if(count($memcached->getServerList()) === 0) {
               $memcached->setOptions([\Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
                  \Memcached::OPT_COMPRESSION => true]);
               if(defined('\\Memcached::OPT_COMPRESSION_TYPE'))
                  $memcached->setOption(\Memcached::OPT_COMPRESSION_TYPE,
                     \Memcached::COMPRESSION_FASTLZ);
               if(\Memcached::HAVE_IGBINARY)
                  $memcached->setOption(\Memcached::OPT_SERIALIZER,
                     \Memcached::SERIALIZER_IGBINARY);
               $memcached->addServers($memcachedConnection);
            }
         }elseif(class_exists('\\Memcache', false)) {
            # The old Memcache extension is loaded, so use our wrapper
            require FrameworkPath . 'memcachedWrapper.php';
            \Session::$memcached = $memcache = new \Memcached('fim');
            foreach($memcachedConnection as $conn)
               $memcache->addserver($conn[0], $conn[1], true,
                  isset($conn[2]) ? $conn[2] : null);
         }else{
            # No Memcache extension is loaded, so use our replacement
            require FrameworkPath . 'memcachedStandalone.php';
            \Session::$memcached = new \Memcached('fim');
            \Session::$memcached->addServers($memcachedConnection);
         }
      }
      $redisConnection = \Config::get('redisConnection');
      if(!empty($redisConnection)) {
         if(class_exists('\\Redis', false))
         # The Redis extension is loaded
            \Session::$redis = new \RedisArray($redisConnection,
               \Config::get('redisOptions'));
         else{
            \Predis\Autoloader::register(true);
            # No Redis extension is loaded; however, FIM will look for Predis,
            # the official Redis PHP standalone library. As this library is not
            # bundled with FIM, it has to be made available as a plugin.
            \Session::$redis = new \Predis\Client($redisConnection,
               \Config::get('redisOptions'));
         }
      }
      if(CLI) # We don't need any session handling in client mode
         return;
      switch(\Config::get('sessionStorage')) {
         case \Config::SESSION_STORAGE_MEMCACHED:
            session_set_save_handler(new MemcachedSession());
            break;
         case \Config::SESSION_STORAGE_REDIS:
            session_set_save_handler(new RedisSession());
            break;
      }
      session_cache_limiter('nocache');
      session_cache_expire(1440);
      session_set_cookie_params(0, BaseDir,
         \Config::get('subdomainDepth') !== 0 ? '.' . Server : '',
         \Config::get('requireSecure'));
      session_name('fim');
      session_start();
      if(!isset($_SESSION['FIMIP']))
         $_SESSION['FIMIP'] = $_SERVER['REMOTE_ADDR'];
      elseif($_SESSION['FIMIP'] !== $_SERVER['REMOTE_ADDR']) {
         Session::clear(false);
         $_SESSION['FIMIP'] = $_SERVER['REMOTE_ADDR'];
      }
      \Config::registerShutdownFunction(\Closure::bind(function() {
            if(Executor::hasExecutedModules())
               \Session::cleanupFlashs();
            # Serialize existing objects, cleanup invalid ones; let the session
            # handler do everything that is not serializable
            foreach(self::$sessionStatic as $key => $value)
               $_SESSION["fim$key"] = fimSerialize($value);
            foreach(self::$sessionFlash as $key => $value)
               $_SESSION["Fim$key"] = fimSerialize($value);
         }, null, '\\Session'));
      if(isset($_SESSION['FIMINVALIDATE'])) {
         $time = time();
         $deadline = $_SESSION['FIMINVALIDATE'];
         if($deadline < 0 && $time > -$deadline) {
            session_unset();
            session_destroy();
            session_start();
            $lifetime = \Config::get('sessionLifetime');
            if($lifetime !== 0)
               $_SESSION['FIMINVALIDATE'] = time() + $lifetime;
         }elseif($deadline > 0 && $time > $deadline)
            \Session::renewId();
      }else{
         $lifetime = \Config::get('sessionLifetime');
         if($lifetime !== 0)
            $_SESSION['FIMINVALIDATE'] = time() + $lifetime;
      }
   }

}