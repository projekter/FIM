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
    * This file provides all necessary utilities for full internationalization.
    */
   class I18N {

      /**
       * @var I18N
       */
      private static $activeLanguage = null;

      /**
       * @var I18N
       */
      private static $internalLanguage = null;

      /**
       * @var ResourceBundle
       */
      private $bundle;
      private $locale;
      private $numberFormatters = [];
      private $dateFormatters = [];

      /**
       * @var IntlTimeZone
       */
      private $timeZone;

      /**
       * @var IntlCalendar
       */
      private $calendar;

      private function __construct($name, ResourceBundle $bundle = null) {
         if($bundle === null) {
            $this->bundle = new \fim\noLanguage();
            # So let's issue a warning if the failing was the internal language.
            # The application may not make use of our language handling.
            if(self::$internalLanguage === null)
               Log::reportInternalError("FIM could not load its internal language data.");
         }else
            $this->bundle = $bundle;
         $name = Locale::canonicalize($name);
         if(Locale::getRegion($name) == null) {
            # set a default region. Might not make sense for some countries, but
            # is required for other functions.
            # This list only covers official codes; Intl package supports lots of
            # others. The selection is completely subjective. If you do only give
            # a two-char language code, you cannot rely on correct time zone
            # detection. Simply supply full qualified language codes to get full
            # support.
            static $defaultRegions = [ // <editor-fold desc="Default regions" defaultstate="collapsed">
               'af' => 'af_ZA', 'am' => 'am_ET', 'ar' => 'ar_AE', 'as' => 'as_IN',
               'ba' => 'ba_RU',
               'be' => 'be_BY', 'bg' => 'bg_BG', 'bn' => 'bn_IN', 'bo' => 'bo_CN',
               'br' => 'br_FR',
               'bs' => 'bs_Latn', 'ca' => 'ca_ES', 'co' => 'co_FR', 'cs' => 'cs_CZ',
               'cy' => 'cy_GB', 'da' => 'da_DK', 'de' => 'de_DE', 'dv' => 'dv_MV',
               'el' => 'el_GR',
               'en' => 'en_US', 'es' => 'es_ES', 'et' => 'et_EE', 'eu' => 'eu_ES',
               'fa' => 'fa_IR',
               'ff' => 'ff_Latn', 'fi' => 'fi_FI', 'fo' => 'fo_FO', 'fr' => 'fr_FR',
               'fy' => 'fy_NL', 'ga' => 'ga_IE', 'gd' => 'gd_GB', 'gl' => 'gl_ES',
               'gn' => 'gn_PY',
               'gu' => 'gu_IN', 'ha' => 'ha_Latn', 'he' => 'he_IL', 'hi' => 'hi_IN',
               'hr' => 'hr_HR', 'hu' => 'hu_HU', 'hy' => 'hy_AM', 'id' => 'id_ID',
               'ig' => 'ig_NG',
               'ii' => 'ii_CN', 'is' => 'is_IS', 'it' => 'it_IT', 'iu' => 'iu_Latn',
               'ja' => 'ja_JP', 'ka' => 'ka_GE', 'kk' => 'kk_KZ', 'kl' => 'kl_GL',
               'km' => 'km_KH',
               'kn' => 'kn_IN', 'ko' => 'ko_KR', 'kr' => 'kr_NG', 'ks' => 'ks_Arab',
               'ku' => 'ku_Arab', 'ky' => 'ky_KG', 'la' => 'la_Latn', 'lb' => 'lb_LU',
               'lo' => 'lo_LA', 'lt' => 'lt_LT', 'lv' => 'lv_LV', 'mi' => 'mi_NZ',
               'mk' => 'mk_MK',
               'ml' => 'ml_IN', 'mn' => 'mn_MN', 'mr' => 'mr_IN', 'ms' => 'ms_BN',
               'mt' => 'mt_MT',
               'my' => 'my_MM', 'nb' => 'nb_NO', 'ne' => 'ne_NP', 'nl' => 'nl_NL',
               'nn' => 'nn_NO',
               'oc' => 'oc_FR', 'om' => 'om_ET', 'or' => 'or_IN', 'pa' => 'pa_IN',
               'pl' => 'pl_PL',
               'ps' => 'ps_AF', 'pt' => 'pt_BR', 'rm' => 'rm_CH', 'ro' => 'ro_RO',
               'ru' => 'ru_RU',
               'rw' => 'rw_RW', 'sa' => 'sa_IN', 'sd' => 'sd_Arab', 'se' => 'se_SE',
               'si' => 'si_LK', 'sk' => 'sk_SK', 'sl' => 'sl_SI', 'so' => 'so_SO',
               'sq' => 'sq_AL',
               'sr' => 'sr_Cyrl', 'st' => 'st_ZA', 'sv' => 'sv_SE', 'sw' => 'sw_KE',
               'ta' => 'ta_IN', 'te' => 'te_IN', 'tg' => 'tg_Cyrl', 'th' => 'th_TH',
               'ti' => 'ti_ER', 'tk' => 'tk_TM', 'tn' => 'tn_BW', 'tr' => 'tr_TR',
               'ts' => 'ts_ZA',
               'tt' => 'tt_RU', 'ug' => 'ug_CN', 'uk' => 'uk_UA', 'ur' => 'ur_IN',
               'uz' => 'uz_Latn',
               've' => 've_ZA', 'vi' => 'vi_VN', 'wo' => 'wo_SN', 'xh' => 'xh_ZA',
               'yi' => 'yi_Hebr',
               'yo' => 'yo_NG', 'zh' => 'zh_CN', 'zu' => 'zu_ZA' // </editor-fold>
            ];
            $code = Locale::getPrimaryLanguage($name);
            if(isset($code) && isset($defaultRegions[$code]))
               $name = $defaultRegions[$code];
         }
         $this->locale = $name;
      }

      /**
       * Internal function. Do not use.
       * @param string $locale
       * @throws I18NException
       */
      public static final function initialize($locale) {
         if(self::$internalLanguage !== null)
            throw new FIMInternalException(self::$internalLanguage->get(['i18n',
               'doubleInitialization']));
         $rbi = new ResourceBundle($locale, FrameworkPath . 'language/', true);
         if($rbi === null)
            throw new FIMInternalException("The internal language $locale did not exist. FIM could not be initialized.");
         self::$internalLanguage = new I18N($locale, $rbi);
         $rbc = new ResourceBundle($locale, CodeDir . 'language/', true);
         self::$activeLanguage = new I18N($locale, $rbc);
      }

      /**
       * @const Returns the complete locale strings that are supported
       */
      const SUPPORTED_LOCALES = 0;

      /**
       * @const Returns only the supported languages
       */
      const SUPPORTED_LANGUAGES = 1;

      /**
       * @const Returns only the supported regions
       */
      const SUPPORTED_REGIONS = 2;

      /**
       * @const Returns only the supported scripts
       */
      const SUPPORTED_SCRIPTS = 3;

      /**
       * Returns an array of all locales that are found in the language
       * directory
       * @param int $checkFor One of I18N::SUPPORTED_LOCALES to
       *    I18N::SUPPORTED_SCRIPTS
       * @return string[]
       */
      public static final function getSupportedLocales($checkFor = self::SUPPORTED_LOCALES) {
         # There is a function called "ResourceBundle::getLocales" which would
         # perfectly suit our wishes if it did as it is documented. But this
         # function requires to create a locale called "res_index" and add a
         # table called "InstalledLocales" inside (which has to be compiled to
         # a resource bundle) which contains all the locales that are available.
         # This just introduces lots of extra work which should be avoided.
         # Therefore we will scan the directory for ourselves. As ICU caches the
         # data until our webserver dies, we will do as well (if possible).
         static $supportedLocales = null;
         if($supportedLocales !== null)
            return $supportedLocales[$checkFor];
         static $cacheMethod = null;
         if($cacheMethod === null)
            if(function_exists('apc_store'))
               $cacheMethod = ['apc_fetch', 'apc_store'];
            elseif(function_exists('xcache_set'))
               $cacheMethod = ['xcache_get', 'xcache_set'];
            elseif(function_exists('zend_shm_cache_store'))
               $cacheMethod = ['zend_shm_cache_fetch', 'zend_shm_cache_store'];
            elseif(function_exists('wincache_ucache_set'))
               $cacheMethod = ['wincache_ucache_get', 'wincache_ucache_set'];
            elseif(class_exists('YAC', false))
               $cacheMethod = [['YAC', 'get'], ['YAC', 'set']];
            else
               $cacheMethod = false;
         if($cacheMethod !== false && ($supportedLocales = $cacheMethod[0]('FIM' . crc32(CodeDir))) !== false)
            return $supportedLocales[$checkFor];
         $di = new fimDirectoryIterator(CodeDir . 'language');
         $_locales = $_languages = $_regions = $_scripts = [];
         foreach(new RegexIterator($di,
         OS === 'Windows' ? '/([A-Z_-]++)\.res$/i' : '/([A-Za-z_-]++)\.res$/',
         RegexIterator::GET_MATCH) as $item) {
            $curLocale = Locale::canonicalize($item[1]);
            if($curLocale === 'root')
               continue;
            $_locales[$curLocale] = true;
            # Those functions are supposed to return NULL, but might return ''
            # instead.
            if(($tmp = Locale::getPrimaryLanguage($curLocale)) != null)
               $_languages[$tmp] = true;
            if(($tmp = Locale::getRegion($curLocale)) != null)
               $_regions[$tmp] = true;
            if(($tmp = Locale::getScript($curLocale)) != null)
               $_scripts[$tmp] = true;
         }
         $supportedLocales = [self::SUPPORTED_LOCALES => array_keys($_locales),
            self::SUPPORTED_LANGUAGES => array_keys($_languages),
            self::SUPPORTED_REGIONS => array_keys($_regions),
            self::SUPPORTED_SCRIPTS => array_keys($_scripts)];
         if($cacheMethod !== false)
            $cacheMethod[1]('FIM' . crc32(CodeDir), $supportedLocales);
         return $supportedLocales[$checkFor];
      }

      /**
       * Returns the current I18N
       * @return I18N
       */
      public static final function getLocale() {
         return self::$activeLanguage;
      }

      /**
       * Returns the current I18N object that is used by FIM only
       * @return I18N
       */
      public static final function getInternalLanguage() {
         return self::$internalLanguage;
      }

      /**
       * Sets the locale that is used by the main application. By default, this
       * will be the same locale as the internal one. In contrast to the
       * internal locale, it can be changed at any time.
       * @param string|I18N $locale
       * @return I18N|false
       */
      public static final function setLocale($locale) {
         if($locale instanceof self) {
            self::$activeLanguage = $locale;
            if(isset($GLOBALS['l']))
               $GLOBALS['l'] = $locale;
            return $locale;
         }
         $rbc = new ResourceBundle($locale, CodeDir . 'language/', true);
         if($rbc !== false) {
            self::$activeLanguage = new I18N($locale, $rbc);
            if(isset($GLOBALS['l']))
               $GLOBALS['l'] = self::$activeLanguage;
            return self::$activeLanguage;
         }else
            return false;
      }

      /**
       * Formats a language key.
       * @param string|array $key The language key identifier
       * @param array $args Parameters that will be passed to
       *    MessageFormatter::formatMessage if the returned value was a string
       * @return int|string|array|ResourceBundle
       * @see MessageFormatter
       */
      public final function get($key, array $args = []) {
         if(is_array($key)) {
            $val = $this->bundle;
            foreach($key as $keyFragment) {
               if(!($val instanceof ResourceBundle)) {
                  $val = null;
                  break;
               }
               $val = $val->get($keyFragment);
            }
            if($val === null)
               $key = implode('/', $key);
         }else
            $val = $this->bundle->get($key);
         if($val === null) {
            static $fallback = null;
            if($fallback === null)
               $fallback = Config::get('languageCodedFallback');
            if($fallback)
               $val = $key;
            else{
               if($this === self::$internalLanguage)
                  if($key !== ['i18n', 'get', 'internalNotFound'])
                     Log::reportInternalError(self::$internalLanguage->get(['i18n',
                           'get', 'internalNotFound'], [$key]), true);
                  else
                     Log::reportInternalError("The internal language key \"$key\" was not found.",
                        true);
               else
                  Log::reportError(self::$internalLanguage->get(['i18n', 'get', 'notFound'],
                        [$key]), true);
               return empty($args) ? $key : "$key: " . implode('|', $args);
            }
         }
         if(empty($args) || !is_string($val))
            return $val;
         else
            return MessageFormatter::formatMessage($this->locale, $val, $args);
      }

      /**
       * Returns the complete locale of this object
       * @return string
       */
      public final function getLocaleName() {
         return $this->locale;
      }

      /**
       * Returns the language part of this locale
       * @return string
       */
      public final function getLocaleLanguage() {
         return Locale::getPrimaryLanguage($this->locale);
      }

      /**
       * Returns the region part of this locale
       * @return string
       */
      public final function getLocaleRegion() {
         return Locale::getRegion($this->locale);
      }

      /**
       * Returns the script part of this locale
       * @return string
       */
      public final function getLocaleScript() {
         return Locale::getScript($this->locale);
      }

      /**
       * Returns the name of the language of this locale
       * @return string
       */
      public final function getDisplayLanguage() {
         return Locale::getDisplayLanguage($this->locale,
               self::$activeLanguage->locale);
      }

      /**
       * Returns the name of this locale
       * @return string
       */
      public final function getDisplayName() {
         return Locale::getDisplayName($this->locale,
               self::$activeLanguage->locale);
      }

      /**
       * Returns the name of the region of this locale
       * @return string
       */
      public final function getDisplayRegion() {
         return Locale::getDisplayRegion($this->locale,
               self::$activeLanguage->locale);
      }

      /**
       * Returns the name of the script of this locale
       * @return string
       */
      public final function getDisplayScript() {
         return Locale::getDisplayScript($this->locale,
               self::$activeLanguage->locale);
      }

      /**
       * Returns the name of the variant of this locale
       * @return string
       */
      public final function getDisplayVariant() {
         return Locale::getDisplayVariant($this->locale,
               self::$activeLanguage->locale);
      }

      /**
       * Formats a given file size in bytes to a human readable format
       * @param int|double $size The number that will be formatted
       * @param int $digits Number of significant digits
       * @param boolean $binary Set this to false if you do not wish to align at
       *    1024 (KiB, MiB, ...) but at 1000 (KB, MB)
       * @param boolean $thinsp If this is set, the separator between number and
       *    unit will be set as a thin space (html: &thinsp;) by using the unicode
       *    character 0x2009. Note that the unicode character is used directly, not
       *    any html entity! UTF-8 output encoding is highly recommended.
       * @return string
       */
      public final function formatSize($size, $digits = 0, $binary = true,
         $thinsp = false) {
         static $binaryPrefixes = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'EiB', 'ZiB',
            'YiB'];
         static $decimalPrefixes = ['B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB'];
         $factor = $binary ? 1024 : 1000;
         for($prefix = 0; $size >= $factor; ++$prefix)
            $size /= $factor;
         return $this->formatNumber(round($size, $digits)) . ($thinsp ? ' ' : ' ') .
            ($binary ? $binaryPrefixes[$prefix] : $decimalPrefixes[$prefix]);
      }

      /**
       * Formats a number with respect to the current locale
       * @param number $number
       * @param int $format
       * @param int $type
       * @return string
       */
      public final function formatNumber($number,
         $format = NumberFormatter::DECIMAL,
         $type = NumberFormatter::TYPE_DEFAULT) {
         if(!isset($this->numberFormatters[$format]))
            $this->numberFormatters[$format] = new NumberFormatter($this->locale,
               $format);
         return $this->numberFormatters[$format]->format($number, $type);
      }

      /**
       * Formats a currency with respect to the current locale
       * @param number $currency
       * @param string $type 3-letter ISO 4217 currency code (e.g. USD, EUR, RUR)
       * @return string
       */
      public final function formatCurrency($currency, $type = 'USD') {
         if(!isset($this->numberFormatters[NumberFormatter::CURRENCY]))
            $this->numberFormatters[NumberFormatter::CURRENCY] = new NumberFormatter($this->locale,
               NumberFormatter::CURRENCY);
         return $this->numberFormatters[NumberFormatter::CURRENCY]->formatCurrency($currency,
               $type);
      }

      /**
       * Formats a date with respect to the current locale, timezone and calendar.
       * @param int|DateTime|IntlCalendar|array $timestamp
       * @param int $format Possible values:<table>
       * <tr><th>IntlDateFormatter::FULL</th><td>Tuesday, April 15, 2014</td></tr>
       * <tr><th>IntlDateFormatter::LONG</th><td>April 15, 2014</td></tr>
       * <tr><th>IntlDateFormatter::MEDIUM</th><td>Apr 15, 2014</td></tr>
       * <tr><th>IntlDateFormatter::SHORT</th><td>4/15/14</td></tr></table>
       * @return string
       */
      public final function formatDate($timestamp,
         $format = IntlDateFormatter::MEDIUM) {
         if($format !== IntlDateFormatter::FULL && $format !== IntlDateFormatter::LONG && $format !== IntlDateFormatter::MEDIUM && $format !== IntlDateFormatter::SHORT)
            throw new I18NException(self::$internalLanguage->get(['i18n', 'format',
               'invalidDateFormat']));
         $hash = "{$format}_" . IntlDateFormatter::NONE;
         if(!isset($this->dateFormatters[$hash]))
            $this->dateFormatters[$hash] = new IntlDateFormatter($this->locale,
               $format, IntlDateFormatter::NONE, $this->timeZone,
               $this->calendar);
         return $this->dateFormatters[$hash]->format($timestamp);
      }

      /**
       * Formats a time with respect to the current locale, timezone and calendar.
       * @param int|DateTime|IntlCalendar|array $timestamp
       * @param int $format Possible values:<table>
       * <tr><th>IntlDateFormatter::FULL</th><td>5:54:40 PM Central European Summer Time</td></tr>
       * <tr><th>IntlDateFormatter::LONG</th><td>5:54:40 PM GMT+2</td></tr>
       * <tr><th>IntlDateFormatter::MEDIUM</th><td>5:54:40 PM</td></tr>
       * <tr><th>IntlDateFormatter::SHORT</th><td>5:54 PM</td></tr></table>
       * @return string
       */
      public final function formatTime($timestamp,
         $format = IntlDateFormatter::MEDIUM) {
         if($format !== IntlDateFormatter::FULL && $format !== IntlDateFormatter::LONG && $format !== IntlDateFormatter::MEDIUM && $format !== IntlDateFormatter::SHORT)
            throw new I18NException(self::$internalLanguage->get(['i18n', 'format',
               'invalidTimeFormat']));
         $hash = IntlDateFormatter::NONE . "_$format";
         if(!isset($this->dateFormatters[$hash]))
            $this->dateFormatters[$hash] = new IntlDateFormatter($this->locale,
               IntlDateFormatter::NONE, $format, $this->timeZone,
               $this->calendar);
         return $this->dateFormatters[$hash]->format($timestamp);
      }

      /**
       * Formats date and time with respect to the current locale, timezone and
       * calendar.
       * @param int|DateTime|IntlCalendar|array $timestamp
       * @param int $dateFormat Possible values:<table>
       * <tr><th>IntlDateFormatter::FULL</th><td>Tuesday, April 15, 2014 at <i>&lt;time&gt;</i></td></tr>
       * <tr><th>IntlDateFormatter::LONG</th><td>April 15, 2014 at <i>&lt;time&gt;</i></td></tr>
       * <tr><th>IntlDateFormatter::MEDIUM</th><td>Apr 15, 2014, <i>&lt;time&gt;</i></td></tr>
       * <tr><th>IntlDateFormatter::SHORT</th><td>4/15/14, <i>&lt;time&gt;</i></td></tr></table>
       * @param int $timeFormat Possible values:<table>
       * <tr><th>IntlDateFormatter::FULL</th><td><i>&lt;date&gt;</i> 5:54:40 PM Central European Summer Time</td></tr>
       * <tr><th>IntlDateFormatter::LONG</th><td><i>&lt;date&gt;</i> 5:54:40 PM GMT+2</td></tr>
       * <tr><th>IntlDateFormatter::MEDIUM</th><td><i>&lt;date&gt;</i> 5:54:40 PM</td></tr>
       * <tr><th>IntlDateFormatter::SHORT</th><td><i>&lt;date&gt;</i> 5:54 PM</td></tr></table>
       * @return string
       */
      public final function formatDateTime($timestamp,
         $dateFormat = IntlDateFormatter::SHORT,
         $timeFormat = IntlDateFormatter::MEDIUM) {
         if($dateFormat !== IntlDateFormatter::FULL && $dateFormat !== IntlDateFormatter::LONG && $dateFormat !== IntlDateFormatter::MEDIUM && $dateFormat !== IntlDateFormatter::SHORT)
            throw new I18NException(self::$internalLanguage->get(['i18n', 'format',
               'invalidDateFormat']));
         if($timeFormat !== IntlDateFormatter::FULL && $timeFormat !== IntlDateFormatter::LONG && $timeFormat !== IntlDateFormatter::MEDIUM && $timeFormat !== IntlDateFormatter::SHORT)
            throw new I18NException(self::$internalLanguage->get(['i18n', 'format',
               'invalidTimeFormat']));
         $hash = "{$dateFormat}_$timeFormat";
         if(!isset($this->dateFormatters[$hash]))
            $this->dateFormatters[$hash] = new IntlDateFormatter($this->locale,
               $dateFormat, $timeFormat, $this->timeZone, $this->calendar);
         return $this->dateFormatters[$hash]->format($timestamp);
      }

      /**
       * Translates a file path by replacing the placeholder &lt;L&gt; with the
       * best-fit locale
       * @param string $path
       * @return string|false
       */
      public final function translatePath($path) {
         $fullPath = Router::convertFIMToFilesystem($path);
         static $localeParts = null;
         if($localeParts === null)
            $localeParts = Locale::parseLocale($this->locale);
         $usedLocale = $localeParts;
         while(!empty($usedLocale)) {
            $composedLocale = Locale::composeLocale($usedLocale);
            if(fileUtils::fileExists(str_replace('<L>', $composedLocale,
                     $fullPath)))
               return str_replace('<L>', $composedLocale, $path);
            else
               array_pop($usedLocale);
         }
         static $defaultLocale = null;
         if($defaultLocale === null)
            $defaultLocale = Locale::parseLocale(Locale::getDefault());
         if($defaultLocale !== $localeParts) {
            $usedLocale = $defaultLocale;
            while(!empty($usedLocale)) {
               $composedLocale = Locale::composeLocale($usedLocale);
               if(fileUtils::fileExists(str_replace('<L>', $composedLocale,
                        $fullPath)))
                  return str_replace('<L>', $composedLocale, $path);
               else
                  array_pop($usedLocale);
            }
         }
         if(fileUtils::fileExists(str_replace('<L>', 'root', $fullPath)))
            return str_replace('<L>', 'root', $path);
         else{
            Log::reportError(self::$internalLanguage->get(['i18n', 'translatePathNotFound'],
                  [Router::normalizeFIM($path)]), true);
            return false;
         }
      }

      /**
       * Parses a string number into an integer/double data type
       * @param string $number
       * @param int $format
       * @param int $type
       * @return int|double|false
       */
      public final function parseNumber($number,
         $format = NumberFormatter::DECIMAL,
         $type = NumberFormatter::TYPE_DOUBLE) {
         if(!isset($this->numberFormatters[$format]))
            $this->numberFormatters[$format] = new NumberFormatter($this->locale,
               $format);
         return $this->numberFormatters[$format]->parse($number, $type);
      }

      /**
       * Parses a string currency into an integer/double data type
       * @param string $currency
       * @param string &$type
       * @return double|false
       */
      public final function parseCurrency($currency, &$type) {
         if(!isset($this->numberFormatters[NumberFormatter::CURRENCY]))
            $this->numberFormatters[NumberFormatter::CURRENCY] = new NumberFormatter($this->locale,
               NumberFormatter::CURRENCY);
         return $this->numberFormatters[NumberFormatter::CURRENCY]->parseCurrency(str_replace("\x20",
                  "\xC2\xA0", $currency), $type);
      }

      /**
       * Parses a date string into a unix timestamp with respect to the current
       * locale, timezone and calendar.
       * @param string $date
       * @param int $format Possible values:<table>
       * <tr><th>IntlDateFormatter::FULL</th><td>Tuesday, April 12, 1952 AD</td></tr>
       * <tr><th>IntlDateFormatter::LONG</th><td>January 12, 1952</td></tr>
       * <tr><th>IntlDateFormatter::MEDIUM</th><td>Jan 12, 1952</td></tr>
       * <tr><th>IntlDateFormatter::SHORT</th><td>12/13/52</td></tr></table>
       * @return int|bool
       */
      public final function parseDate($date, $format = IntlDateFormatter::SHORT) {
         if($format !== IntlDateFormatter::FULL && $format !== IntlDateFormatter::LONG && $format !== IntlDateFormatter::MEDIUM && $format !== IntlDateFormatter::SHORT)
            throw new I18NException(self::$internalLanguage->get(['i18n', 'format',
               'invalidDateFormat']));
         $hash = "{$format}_" . IntlDateFormatter::NONE;
         if(!isset($this->dateFormatters[$hash]))
            $this->dateFormatters[$hash] = new IntlDateFormatter($this->locale,
               $format, IntlDateFormatter::NONE, $this->timeZone,
               $this->calendar);
         return $this->dateFormatters[$hash]->parse($date);
      }

      /**
       * Parses a time string into a unix timestamp with respect to the current
       * locale, timezone and calendar.
       * @param string $time
       * @param int $format Possible values:<table>
       * <tr><th>IntlDateFormatter::FULL</th><td>3:30:42pm PST</td></tr>
       * <tr><th>IntlDateFormatter::LONG</th><td>3:30:32pm</td></tr>
       * <tr><th>IntlDateFormatter::SHORT</th><td>3:30pm</td></tr></table>
       * @return int|bool
       */
      public final function parseTime($time, $format = IntlDateFormatter::SHORT) {
         if($format !== IntlDateFormatter::FULL && $format !== IntlDateFormatter::LONG && $format !== IntlDateFormatter::SHORT)
            throw new I18NException(self::$internalLanguage->get(['i18n', 'format',
               'invalidTimeFormat']));
         $hash = IntlDateFormatter::NONE . "_$format";
         if(!isset($this->dateFormatters[$hash]))
            $this->dateFormatters[$hash] = new IntlDateFormatter($this->locale,
               IntlDateFormatter::NONE, $format, $this->timeZone,
               $this->calendar);
         return $this->dateFormatters[$hash]->parse($time);
      }

      /**
       * Parses a date time string into a unix timestamp with respect to the
       * current locale, timezone and calendar.
       * @param string $dateTime
       * @param int $dateFormat Possible values:<table>
       * <tr><th>IntlDateFormatter::FULL</th><td>Tuesday, April 12, 1952 AD</td></tr>
       * <tr><th>IntlDateFormatter::LONG</th><td>January 12, 1952</td></tr>
       * <tr><th>IntlDateFormatter::MEDIUM</th><td>Jan 12, 1952</td></tr>
       * <tr><th>IntlDateFormatter::SHORT</th><td>12/13/52</td></tr></table>
       * @param int $timeFormat Possible values:<table>
       * <tr><th>IntlDateFormatter::FULL</th><td>3:30:42pm PST</td></tr>
       * <tr><th>IntlDateFormatter::LONG</th><td>3:30:32pm</td></tr>
       * <tr><th>IntlDateFormatter::SHORT</th><td>3:30pm</td></tr></table>
       * @return int
       */
      public final function parseDateTime($dateTime,
         $dateFormat = IntlDateFormatter::SHORT,
         $timeFormat = IntlDateFormatter::SHORT) {
         if($dateFormat !== IntlDateFormatter::FULL && $dateFormat !== IntlDateFormatter::LONG && $dateFormat !== IntlDateFormatter::MEDIUM && $dateFormat !== IntlDateFormatter::SHORT)
            throw new I18NException(self::$internalLanguage->get(['i18n', 'format',
               'invalidDateFormat']));
         if($timeFormat !== IntlDateFormatter::FULL && $timeFormat !== IntlDateFormatter::LONG && $timeFormat !== IntlDateFormatter::SHORT)
            throw new I18NException(self::$internalLanguage->get(['i18n', 'format',
               'invalidTimeFormat']));
         $hash = "{$dateFormat}_$timeFormat";
         if(!isset($this->dateFormatters[$hash]))
            $this->dateFormatters[$hash] = new IntlDateFormatter($this->locale,
               $dateFormat, $timeFormat, $this->timeZone, $this->calendar);
         return $this->dateFormatters[$hash]->parse($dateTime);
      }

      /**
       * Returns an IntlTimeZone object that allows to perform various operations.
       * If the object was not set manually before, it is determined automatically.
       * <br />Warning: As there is no proper way to obtain a timezone from the
       * locale, automatic guessing will most likely fail poorly (just think of
       * how many timezones would be valid in en_US).
       * @return IntlTimeZone
       * @requires PHP 5.5
       */
      public final function getTimeZone() {
         if(!isset($this->timeZone)) {
            $timezones = IntlTimeZone::createEnumeration(Locale::getRegion($this->locale));
            if(($timezone = $timezones->current()) === null)
               $timezone = 'GMT';
            $this->timeZone = IntlTimeZone::createTimeZone($timezone);
         }
         return $this->timeZone;
      }

      /**
       * Changes the current timezone.
       * @param IntlTimeZone $timeZone
       * @requires PHP 5.5
       */
      public final function setTimeZone(IntlTimeZone $timeZone) {
         $this->timeZone = $timeZone;
         if(isset($this->calendar))
            $this->calendar->setTimeZone($timeZone);
         foreach($this->dateFormatters as $dF)
            $dF->setTimeZone($timeZone);
      }

      /**
       * Returns an IntlCalendar object that allows to perform variaous operations.
       * If the object was not set manually before, it is determined automatically
       * by using the current language and time zone.
       * @return IntlCalendar
       * @requires PHP 5.5
       */
      public final function getCalendar() {
         if(!isset($this->calendar))
            $this->calendar = IntlCalendar::createInstance($this->getTimeZone(),
                  $this->locale);
         return $this->calendar;
      }

      /**
       * Changes the current calendar. Neither time zone nor language will be
       * adjusted.
       * @param IntlCalendar $calendar
       * @requires PHP 5.5
       */
      public final function setCalendar(IntlCalendar $calendar) {
         $this->calendar = $calendar;
         foreach($this->dateFormatters as $dF)
            $dF->setCalendar($calendar);
      }

      /**
       * Calculates the prefered language according to the user's browser. The
       * language has to be included in $acceptedLanguages
       * @param array $acceptedLocales This array contains all valid locales.
       *    If null, all supported locales are used.
       * @param string $fallback This language string will be returned when there
       *    is no match
       * @return string A language string
       */
      public static final function detectBrowserLocale(array $acceptedLocales = null,
         $fallback = 'en') {
         if($acceptedLocales === null)
            $acceptedLocales = self::getSupportedLocales();
         $lang = Locale::acceptFromHttp(@$_SERVER['HTTP_ACCEPT_LANGUAGE']);
         if($lang !== null)
            return Locale::lookup($acceptedLocales, $lang, true, $fallback);
         else
            return $fallback;
      }

   }

}

namespace fim {

   /**
    * This helper class is set as default for uninitialized languages. It is
    * placeholder for a ResourceBundle
    */
   class noLanguage {

      private static $logged = false;

      public function get($key) {
         if(!self::$logged) {
            self::$logged = true;
            \Log::reportError(\I18N::getInternalLanguage()->get(['i18n', 'get', 'noLanguage'],
                  [$key]), true);
         }
         return $key;
      }

   }

}