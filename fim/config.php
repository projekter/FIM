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
 * This class contains the internal configuration of the framework. Changes to
 * some values are possible.
 */
abstract class Config {

   private static $config = null;
   private static $configDefaults = [
      'autoEscape' => true,
      'cliServer' => 'CLI',
      'defaultAccessGranted' => true,
      'defaultEncoding' => 'utf-8',
      'directoryListing' => 'development',
      'languageCodedFallback' => false,
      'languageInternal' => 'en',
      'mailErrorsTo' => false,
      'mailFrom' => 'Framework <noreply@framework.log>',
      'memcachedConnection' => [],
      'plugins' => [],
      'production' => false,
      'redisConnection' => null,
      'redisOptions' => null,
      'requireSecure' => false,
      'sessionLifetime' => 300,
      'sessionStorage' => 'default',
      'sessionTransition' => 5,
      'subdomainBase' => '',
      'subdomainBaseError' => '',
      'subdomainDefault' => '',
      'subdomainDepth' => 0,
      'x-sendfile' => 'auto',
   ];
   private static $shutdownFunctions = [];

   const DIRECTORY_LISTING_NONE = 'none';
   const DIRECTORY_LISTING_SIMPLE = 'simple';
   const DIRECTORY_LISTING_DETAIL = 'detail';
   const SESSION_STORAGE_DEFAULT = 'default';
   const SESSION_STORAGE_MEMCACHED = 'memcached';
   const SESSION_STORAGE_REDIS = 'redis';
   const XSENDFILE_NONE = 'none';
   const XSENDFILE_APACHE = 'apache';
   const XSENDFILE_CHEROKEE = 'cherokee';
   const XSENDFILE_LIGHTTPD = 'lighttpd';
   const XSENDFILE_NGINX = 'nginx';

   public static final function initialize(array $config) {
      if(self::$config !== null)
         throw new FIMInternalException(I18N::getInternalLanguage()->get(['config', 'doubleInitialization']));
      register_shutdown_function(function() {
         foreach(self::$shutdownFunctions as $f)
            $f();
         Log::finalize(); # This has to be the really last function
      });
      $config = array_merge(self::$configDefaults, $config);

      # Try to make I18N work
      if(!is_string($config['languageInternal']))
         throw new ConfigurationException('Error in configuration: languageInternal must be of type string');
      \I18N::initialize($config['languageInternal']);

      # Now all messages can be localized. Validate configuration entries
      self::validateKeys($config);
      self::validateTypes($config);
      $subdomainPassed = self::validateSubdomain($config);

      if($config['directoryListing'] === 'development')
         $config['directoryListing'] = $config['production'] ? self::DIRECTORY_LISTING_NONE
               : self::DIRECTORY_LISTING_DETAIL;

      if(CLI)
         $config['x-sendfile'] = 'none';
      elseif($config['x-sendfile'] === 'auto')
         if(function_exists('apache_get_modules') && in_array('mod_xsendfile',
               apache_get_modules()))
            $config['x-sendfile'] = self::XSENDFILE_APACHE;
         else{
            $software = getenv('SERVER_SOFTWARE');
            if(stristr($software, 'lighttpd') !== false)
               $config['x-sendfile'] = self::XSENDFILE_LIGHTTPD;
            elseif(stristr($software, 'nginx') !== false)
               $config['x-sendfile'] = self::XSENDFILE_NGINX;
            elseif(stristr($software, 'cherokee') !== false)
               $config['x-sendfile'] = self::XSENDFILE_CHEROKEE;
            else
               $config['x-sendfile'] = self::XSENDFILE_NONE;
         }

      Module::$smarty->escape_html = $config['autoEscape'];
      Smarty::$_CHARSET = $config['defaultEncoding'];
      # Just echo the default encoding; this is likely to be overwritten
      # anywhere, but if something goes wrong, we do at least have a correctly-
      # encoded output.
      if(!CLI)
         @header("Content-type: text/html; charset={$config['defaultEncoding']}");

      self::$config = $config;
      if(!$subdomainPassed)
         if($config['subdomainBaseError'] !== '') {
            chdir(dirname($config['subdomainBaseError']));
            require $config['subdomainBaseError'];
            exit;
         }else
            throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'subdomainError', 'baseMismatchesURL']));
   }

   // <editor-fold desc="Configuration validators" defaultstate="collapsed">
   private static function validateTypes(array &$config) {
      # Fast checks first
      if($config['directoryListing'] !== self::DIRECTORY_LISTING_NONE && $config['directoryListing'] !== self::DIRECTORY_LISTING_SIMPLE && $config['directoryListing'] !== self::DIRECTORY_LISTING_DETAIL && $config['directoryListing'] !== 'development')
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidValue'],
            ['directoryListing', "'none', 'simple', 'detail', 'development'"]));
      if($config['x-sendfile'] !== self::XSENDFILE_NONE && $config['x-sendfile'] !== 'auto' && $config['x-sendfile'] !== self::XSENDFILE_APACHE && $config['x-sendfile'] !== self::XSENDFILE_CHEROKEE && $config['x-sendfile'] !== self::XSENDFILE_LIGHTTPD && $config['x-sendfile'] !== self::XSENDFILE_NGINX)
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidValue'],
            ['x-sendfile',
            "'none', 'auto', 'apache', 'nginx', 'cherokee', 'lighttpd'"]));
      if($config['sessionStorage'] !== self::SESSION_STORAGE_DEFAULT && $config['sessionStorage'] !== self::SESSION_STORAGE_MEMCACHED && $config['sessionStorage'] !== self::SESSION_STORAGE_REDIS)
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidValue'],
            ['sessionStorage', "'default', 'memcached', 'redis'"]));
      # slower is_*-checks afterwards
      if(!is_bool($config['autoEscape']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['autoEscape', 'boolean']));
      if(!is_string($config['cliServer']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['cliServer', 'string']));
      if(!is_bool($config['defaultAccessGranted']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['defaultAccessGranted', 'boolean']));
      if(!is_string($config['defaultEncoding']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['defaultEncoding', 'string']));
      if(!is_bool($config['languageCodedFallback']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['languageCodedFallback', 'boolean']));
      if($config['mailErrorsTo'] !== false && !is_string($config['mailErrorsTo']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['mailErrorsTo', 'string|false']));
      if(!is_string($config['mailFrom']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['mailFrom', 'string']));
      if(!is_array($config['memcachedConnection']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['memcachedConnection', '[[string, int, int]*]']));
      else
         foreach($config['memcachedConnection'] as &$conn)
            if(!is_array($conn))
               $conn = [$conn, 11211];
            elseif(!isset($conn[0]) || !isset($conn[1]))
               throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
                  ['memcachedConnection', '[((string, int, [int])|string)*]']));
      if(!is_array($config['plugins']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['plugins', 'array']));
      else
         foreach($config['plugins'] as $key => $plug) {
            if(($lowerKey = strtolower($key)) !== $key)
               if(isset($config['plugins'][$lowerKey]))
                  throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'doublePlugins'],
                     [$key]));
               else{
                  $config['plugins'][$lowerKey] = $plug;
                  unset($config['plugins'][$key]);
               }
         }
      if(!is_bool($config['production']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['production', 'boolean']));
      if(!is_bool($config['requireSecure']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['requireSecure', 'boolean']));
      if(!is_int($config['sessionLifetime']) || ($config['sessionLifetime'] < 10 && $config['sessionLifetime'] !== 0))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['sessionLifetime', 'integer >= 10 or 0']));
      if(!is_int($config['sessionTransition']) || $config['sessionTransition'] < 0 || ($config['sessionLifetime'] !== 0 && $config['sessionTransition'] > $config['sessionLifetime']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['sessionTransition', 'integer from zero to sessionLifetime']));
      if(!is_string($config['subdomainBase']) && !is_array($config['subdomainBase']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['subdomainBase', 'string|string[]']));
      if(!is_string($config['subdomainBaseError']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['subdomainBaseError', 'string']));
      if(!is_string($config['subdomainDefault']))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['subdomainDefault', 'string']));
      if(!is_int($config['subdomainDepth']) || $config['subdomainDepth'] < 0)
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'invalidType'],
            ['subdomainDepth', 'integer >= 0']));
   }

   private static function validateKeys(array $config) {
      foreach($config as $key => $value)
         if(!isset(self::$configDefaults[$key]) && !array_key_exists($key,
               self::$configDefaults))
            throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'notFound'],
               [$key]));
   }

   private static function validateSubdomain(array &$config) {
      $depth = $config['subdomainDepth']; # Cache, as array access is relatively slow
      $bases = empty($config['subdomainBase']) ? [] : (array)$config['subdomainBase'];
      $errorFile = &$config['subdomainBaseError'];
      if($depth > 0 && (empty($bases) || empty($bases[0])))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'subdomain', 'depthRequiresBase']));
      if(!empty($errorFile)) {
         if(empty($bases))
            throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'subdomain', 'baseErrorRequiresBase']));
         $errorFile = CodeDir . 'content/' . Router::normalize($errorFile);
         if(!is_file($errorFile))
            throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'subdomain', 'baseErrorNotFound']));
      }

      if(CLI || ($depth === 0 && empty($bases))) {
         define('CurrentSubdomain', '');
         define('Server',
            CLI ? (($config['cliServer'] !== 'CLI') ?
                  $config['cliServer'] : (empty($bases) ? 'CLI' : reset($bases)))
                  :
               explode(':', @$_SERVER['HTTP_HOST'])[0] .
               Request::getPort(true));
         # First trimming the port number, then reappending it is done for
         # security purposes. As HTTP_HOST comes from the client (and we do not
         # know, which server software is used), it might contain a port or not
         # and is not very reliable.
      }else{
         $currentSubdomain = explode(':', @$_SERVER['HTTP_HOST'])[0];
         if(substr($currentSubdomain, 0, 4) === 'www.')
            $currentSubdomain = substr($currentSubdomain, 4);
         if($depth === 0) # We need to handle this case as a special one.
         # First, it improves speed and second, we cannot simply add the
         # remaining "extra subdomains" to a dotted folder name, as there is
         # no folder.
            foreach($bases as $b) {
               if($currentSubdomain === $b) {
                  $baseLength = strlen($b);
                  $base = $b;
                  break;
               }
            }#
         else{
            if(BaseDir !== '/')
               throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'validation', 'subdomain', 'documentRoot']));
            foreach($bases as $b) {
               $baseLength = strlen($b);
               if(substr($currentSubdomain, -$baseLength) === $b) {
                  $base = $b;
                  break;
               }
            }
         }
         if(!isset($base)) {
            define('Server', $currentSubdomain . Request::getPort(true));
            define('CurrentSubdomain', '');
            return false;
         }
         /**
          * Contains the host name that is currently used. This value is
          * influenced by the configuration entry subdomainBase if available
          * and contains a port number, if a port is used that differs from the
          * standard port.
          * @const Server
          */
         define('Server', $base . Request::getPort(true));
         $currentSubdomain = explode('.',
            substr($currentSubdomain, 0, -$baseLength - 1));
         if(empty($currentSubdomain[0])) {
            $count = 0;
            $currentSubdomain = [];
         }else
            $count = count($currentSubdomain);
         if($count > $depth) {
            # The outermost folder may contain as many dots as the developer
            # wants. FIM will notice the difference between a dot in this
            # folder's name and a subdomain delimiter.
            $additional = array_splice($currentSubdomain, 0, $count - $depth + 1);
            $currentSubdomain = array_merge((array)implode('.', $additional),
               $currentSubdomain);
         }elseif($count < $depth) {
            $default = explode('.', $config['subdomainDefault']);
            $defaultCount = count($default);
            if($defaultCount > $count) {
               $additional = array_splice($default, 0,
                  $defaultCount - $depth + 1);
               $default = array_merge((array)implode('.', $additional), $default);
            }
            $currentSubdomain = array_merge($currentSubdomain,
               array_splice($default, -$depth + $count));
         }
         $currentSubdomain = implode('/', array_reverse($currentSubdomain));
         /**
          * Contains the current folder that is indicated with subdomains,
          * relative to content directory. Does not contain a beginning or
          * trailing slash.
          * @const CurrentSubdomain
          */
         define('CurrentSubdomain',
            $currentSubdomain === '' ? $config['subdomainDefault'] : $currentSubdomain);
      }
      return true;
   }

   // </editor-fold>

   /**
    * Returns a configuration value by its name
    * @param string $key
    * @return mixed
    */
   public static final function get($key) {
      return @self::$config[$key];
   }

   /**
    * Sets a configuration value by its name. Not all configuration entries can
    * be changed. Another check for the validity of $value will not be
    * performed! You have to care for valid values by yourself!
    * @param string $key
    * @param mixed $value
    */
   public static final function set($key, $value) {
      if($key === 'languageInternal' || $key === 'languageCodedFallback' || $key === 'subdomainBase' || $key === 'subdomainDefault' || $key === 'subdomainDepth' || $key === 'plugins')
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'set', 'readonly'],
            [$key]));
      elseif(!isset(self::$configDefaults[$key]))
         throw new ConfigurationException(I18N::getInternalLanguage()->get(['config', 'set', 'unknown'],
            [$key]));
      self::$config[$key] = $value;
   }

   /**
    * Checks whether a configuration value equals another value
    * @param string $key
    * @param mixed $value
    * @param mixed ... You may give alternatives
    * @return boolean
    */
   public static final function equals($key, $value) {
      if(func_num_args() === 2)
         return self::$config[$key] === $value;
      else{
         $args = func_get_args();
         array_shift($args);
         foreach($args as $arg)
            if(self::$config[$key] === $arg)
               return true;
         return false;
      }
   }

   /**
    * Registers a function that will be performed at the very end of everything.
    * @param callable $callable
    * @return string An id that you may use to unregister the function again
    */
   public static final function registerShutdownFunction($callable) {
      while(isset(self::$shutdownFunctions[$id = uniqid()]));
      self::$shutdownFunctions[$id] = $callable;
      return $id;
   }

   /**
    * Unregisters a shutdown function that was previously registered with
    * \Config::registerShutdownFunction
    * @param string $id The id given back by registerShutdownFunction
    */
   public static final function unregisterShutdownFunction($id) {
      unset(self::$shutdownFunctions[$id]);
   }

   /**
    * Returns the value of an ini entry that was stored in the php.ini.
    * If the value is an integer, it is taken care of suffixes.
    * @param string $key
    * @return int|string
    */
   public static final function ini_get($key) {
      $val = ini_get($key);
      if($val === '')
         return '';
      # We will determine if the value is an integer by splitting. Sadly, this
      # process requires three function calls.
      $suffix = substr($val, -1);
      $value = substr($val, 0, -1);
      if(ctype_digit($value)) {
         if((string)(int)$suffix === $suffix)
            return (int)$val;
         elseif($suffix === 'm' || $suffix === 'M')
            return $value * 1048576;
         elseif($suffix === 'k' || $suffix === 'K')
            return $value * 1024;
         elseif($suffix === 'g' || $suffix === 'G')
            return $value * 1073741824;
      }
      return $val;
   }

}
