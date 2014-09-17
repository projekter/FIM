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
      private static $activeLanguage;

      /**
       * @var I18N
       */
      private static $internalLanguage;

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
            # So let's issue a warning
            if(isset(self::$internalLanguage))
               Log::reportError(self::$internalLanguage->get('i18n.setupWrong',
                     [$name]));
            else
               Log::reportError("FIM was set up with incorrect language files. The loading of the language \"$name\" did not succeed. Make sure your main fallback language is named \"root\".");
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
         if(isset(self::$internalLanguage))
            throw new I18NException(self::$internalLanguage->get('i18n.init.double'));
         $rbi = new ResourceBundle($locale, FrameworkPath . 'language', true);
         if($rbi === null)
            throw new I18NException("The internal language $locale did not exist. FIM could not be initialized.");
         self::$internalLanguage = new I18N($locale, $rbi);
         $rbc = new ResourceBundle($locale, CodeDir . 'language', true);
         self::$activeLanguage = ($rbc !== null) ? new I18N($locale, $rbc) : new \fim\noLanguage();
      }

      /**
       * Sets the language that is used by the main application. By default, this
       * will be the same language as the internal language. In contrast to the
       * internal language, it can be changed at any time.
       * @param string $locale
       * @return boolean
       */
      public static final function setLanguage($locale) {
         $rbc = new ResourceBundle($locale, CodeDir . 'language/language', true);
         if($rbc !== false) {
            self::$activeLanguage = new I18N($locale, $rbc);
            if(isset($GLOBALS['l']))
               $GLOBALS['l'] = self::$activeLanguage;
            return true;
         }else
            return false;
      }

      /**
       * Formats a language key.
       * @param string $key The language key identifier
       * @param array $args Parameters that will be passed to
       *    MessageFormatter::formatMessage if the returned value was a string
       * @return int|string|array|ResourceBundle
       * @see MessageFormatter
       */
      public final function get($key, array $args = []) {
         $val = $this->bundle->get($key);
         if($val === null) {
            static $fallback;
            if(!isset($fallback))
               $fallback = Config::get('languageCodedFallback');
            if($fallback)
               $val = $key;
            else{
               if($this === self::$internalLanguage)
                  if($key !== 'i18n.get.notFound.internal')
                     Log::reportError(self::$internalLanguage->get('i18n.get.notFound.internal',
                           [$key]), true);
                  else
                     Log::reportError("The internal language key \"$key\" was not found.",
                        true);
               else
                  Log::reportError(self::$internalLanguage->get('i18n.get.notFound',
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
       * Returns the locale of this language
       * @return string
       */
      public final function getLocale() {
         return $this->locale;
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
            throw new I18NException(self::$internalLanguage->get('i18n.format.invalidDateFormat'));
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
            throw new I18NException(self::$internalLanguage->get('i18n.format.invalidTimeFormat'));
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
            throw new I18NException(self::$internalLanguage->get('i18n.format.invalidDateFormat'));
         if($timeFormat !== IntlDateFormatter::FULL && $timeFormat !== IntlDateFormatter::LONG && $timeFormat !== IntlDateFormatter::MEDIUM && $timeFormat !== IntlDateFormatter::SHORT)
            throw new I18NException(self::$internalLanguage->get('i18n.format.invalidTimeFormat'));
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
         $fullPath = Router::normalize($path, false, false);
         static $localeParts;
         if(!isset($localeParts))
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
         static $defaultLocale;
         if(!isset($defaultLocale))
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
            Log::reportError(self::$internalLanguage->get('i18n.translatePath.notFound',
                  ['/' . Router::normalize($path)]), true);
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
            throw new I18NException(self::$internalLanguage->get('i18n.format.invalidDateFormat'));
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
            throw new I18NException(self::$internalLanguage->get('i18n.format.invalidTimeFormat'));
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
            throw new I18NException(self::$internalLanguage->get('i18n.format.invalidDateFormat'));
         if($timeFormat !== IntlDateFormatter::FULL && $timeFormat !== IntlDateFormatter::LONG && $timeFormat !== IntlDateFormatter::SHORT)
            throw new I18NException(self::$internalLanguage->get('i18n.format.invalidTimeFormat'));
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
       * @param array $acceptedLanguages This array contains all valid languages.
       *    They have to be represented as language string
       * @param string $fallback This language string will be returned when there
       *    is no match
       * @return string A language string
       */
      public static final function detectBrowserLanguage(array $acceptedLanguages,
         $fallback = 'en') {
         $lang = Locale::acceptFromHttp(@$_SERVER['HTTP_ACCEPT_LANGUAGE']);
         if(isset($lang))
            return Locale::lookup($acceptedLanguages, $lang, true, $fallback);
         else
            return $fallback;
      }

      /**
       * Returns the current I18N
       * @return I18N
       */
      public static final function getLanguage() {
         return self::$activeLanguage;
      }

      /**
       * Returns the current I18N object that is used by FIM only
       * @return I18N
       */
      public static final function getInternalLanguage() {
         return self::$internalLanguage;
      }

   }

}

namespace fim {

   /**
    * This helper class is set as default for uninitialized languages.
    */
   class noLanguage {

      private static $logged = false;

      public function __call($name, $arguments) {
         if(!self::$logged) {
            self::$logged = true;
            \Log::reportError(\I18N::getInternalLanguage()->get('i18n.get.noLanguage'),
               true);
         }
         if($name === 'get')
            return reset($arguments);
         else
            return false;
      }

   }

}