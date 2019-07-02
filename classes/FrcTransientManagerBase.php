<?php

namespace Frc;

use function array_filter;
use function array_push;
use function array_values;
use function count;
use function defined;
use function delete_transient;
use function error_log;
use function explode;
use function function_exists;
use function get_locale;
use function get_transient;
use function getenv;
use function in_array;
use function is_preview;
use function is_user_logged_in;
use function pll_languages_list;
use function preg_grep;
use function preg_quote;
use function preg_replace;
use function print_r;
use function set_transient;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Transient Extensions with Polylang Support
 *
 * @link http://codex.wordpress.org/Transients_API
 *
 *
 */
class FrcTransientManagerBase {

    protected $enable;
    protected $cache_messages;
    protected $db_prefix;
    protected $locale;
    protected $prefix;
    protected $suffix = '';
    protected $logged_in_suffix = '';
    protected $acf_fields;
    protected $post_data;
    protected $log = false;

    /**
     * TransientObject constructor.
     *
     * @param bool $enable
     * @param bool $cache_messages
     */
    protected function __construct($enable = true, $cache_messages = true, $logged_in_suffix = '') {

        $this->db_prefix        = $this->dbPrefix();
        $this->locale           = $this->getLocale();
        $this->prefix           = $this->transientPrefix($this->locale);
        $this->logged_in_suffix = $logged_in_suffix;
        $this->enable           = $enable;
        $this->acf_fields       = [];
        $this->post_data        = [];
        $this->cache_messages   = $cache_messages;
    }

    /**
     * @return bool
     */
    public function isEnable():bool {
        return $this->enable;
    }

    /**
     * @param bool $enable
     */
    public function setEnable(bool $enable):void {
        $this->enable = $enable;
    }

    /**
     * @return bool
     */
    public function isCacheMessages():bool {
        return $this->cache_messages;
    }

    public function enableLog() {
        $this->log = true;
    }

    public function logMessage($message) {
        if ($this->log) {
            error_log(print_r($message, true));
        }
    }

    /**
     * @param bool $cache_messages
     */
    public function setCacheMessages(bool $cache_messages):void {
        $this->cache_messages = $cache_messages;
    }

    /**
     * String added to the beginning of get_transient() and set_transient()
     * based on table prefix set in `wp-config.php`
     *
     * @return String
     */
    protected function dbPrefix() {
        global $wpdb;

        return $wpdb->prefix;
    }

    /**
     * @param bool|string $locale
     *
     * @return string
     */
    protected function transientPrefix($locale = false) {
        if (!$locale) {
            $locale = $this->locale;
        }

        return $this->db_prefix . '|' . $locale . '|';
    }

    /**
     * @return string
     */
    protected function transientSuffix() {
        $suffix = '|' . $this->suffix;
        if (is_user_logged_in()) {
            $suffix = '|' . $this->logged_in_suffix;
        }

        return $suffix;
    }

    /**
     * @return string
     */
    protected function getLocale() {
        return get_locale();
    }

    /**
     * @return array
     */
    protected function getAllLocales() {
        $locales = [$this->locale];
        if (function_exists('pll_languages_list')) {
            $args    = [
                'fields' => 'locale',
            ];
            $locales = pll_languages_list($args);
        }
        array_push($locales, 'options');
        array_push($locales, 'globals');

        return $locales;
    }

    /**
     * @return int
     */
    protected function getExpiration() {
        return getenv('TRANSIENT_EXPIRATION') ?: YEAR_IN_SECONDS;
    }

    /**
     * @return string
     */
    protected function optionName() {
        return 'transient_keys';
    }

    /**
     * @param bool|string $transient_name
     * @param bool|string $locale
     *
     * @return string
     */
    protected function generateTransientKey($transient_name = false, $locale = false) {
        if (false == $transient_name) {
            die('frc_transient name cannot be empty');
        }

        return $this->transientPrefix($locale) . $transient_name . $this->transientSuffix();
    }

    /**
     * @param bool|string $locale
     *
     * @return array
     */
    public function getTransientKeys($locale = false) {
        return $this->getTransient($this->optionName(), $locale) ?: [];
    }

    /**
     * @return array
     */
    public function getAllTransientKeys() {
        $return = [];
        foreach ($this->getAllLocales() as $locale) {
            $return[$locale] = $this->getTransientKeys($locale);
        }

        return $return;
    }

    /**
     * @param mixed $content
     * @param bool|string $expiration
     * @param bool|int $locale
     *
     * @return bool|mixed
     */
    protected function setTransientKeys($content = false, $locale = false) {

        if (false === $content) {
            $content = [];
        }

        $key = $this->generateTransientKey($this->optionName(), $locale);

        $this->logMessage('========= setTransientKeys =========');
        $this->logMessage('count: ' . count($content));
        $this->logMessage('locale: ' . $locale);
        $this->logMessage('key: ' . $key);
        $this->logMessage('================================');


        set_transient($key, $content, 0);

        return $content;
    }

    /**
     * @param string $new_transient_key
     * @param bool|string $locale
     *
     * @return array
     */
    protected function updateTransientKeys($new_transient_key, $locale = false) {
        $transient_keys = $this->getTransientKeys($locale);

        $this->logMessage('========= updateTransientKeys =========');
        $this->logMessage('before count: ' . count($transient_keys));
        $this->logMessage('locale: ' . $locale);
        $this->logMessage('add: ' . $new_transient_key);
        if (in_array($new_transient_key, $transient_keys)) {
            $this->logMessage('!!already in!!');
        }
        $this->logMessage('================================');


        if (!in_array($new_transient_key, $transient_keys)) {
            $transient_keys[] = $new_transient_key;
            $transient_keys   = array_values($transient_keys);
            $this->setTransientKeys($transient_keys, $locale);
        }

        return $transient_keys;
    }

    /**
     * @param string $transient_key
     * @param bool|string $locale
     *
     * @return bool
     */
    protected function deleteTransientKey($transient_key, $locale = false) {
        // Get the current list of transients.
        $transient_keys = $this->getTransientKeys($locale);

        $count = count($transient_keys);

        $this->logMessage('========= deleteTransientKey =========');
        $this->logMessage('before count: ' . $count);
        $this->logMessage('locale: ' . $locale);
        $this->logMessage('remove: ' . $transient_key);


        $transient_keys = array_filter($transient_keys, function ($e) use ($transient_key) {
            return ($e != $transient_key);
        });

        $filter = count($transient_keys);

        if ($filter < $count) {
            $transient_keys = array_values($transient_keys);
            $this->setTransientKeys($transient_keys, $locale);
        } else {
            $this->logMessage('!!not found!!');
        }

        $this->logMessage('================================');

        return true;
    }

    /**
     * @param bool|string $transient_name
     * @param bool|string $locale
     *
     * @return mixed
     */
    public function getTransient($transient_name = false, $locale = false) {
        if (is_preview() || !$this->enable) {
            return null;
        }

        $data = get_transient($this->generateTransientKey($transient_name, $locale));

        if ($this->isCacheMessages() && (WP_DEBUG && !empty($transient_name) && $data != false)) {
            $cache_message = '<!-- Served from cache -->';
            $cache_message = preg_replace('/ -->/', ': ' . $transient_name . ' from locale ' . $locale . ' -->', $cache_message);
            echo $cache_message;
        }

        if (empty($data)) {
            return null;
        }

        return $data;
    }

    /**
     * @param bool $transient_name
     * @param bool $content
     * @param bool $expiration
     * @param bool $locale
     *
     * @return bool|mixed
     */
    protected function setTransient($transient_name = false, $content = false, $locale = false, $expiration = false) {
        if (empty($transient_name)) {
            return null;
        }

        if (empty($content)) {
            $this->deleteTransient($transient_name, $locale);

            return null;
        }

        if (is_preview()) {
            return $content;
        }
        if (false === $expiration) {
            $expiration = $this->getExpiration();
        }
        $key = $this->generateTransientKey($transient_name, $locale);
        $this->updateTransientKeys($key, $locale);
        if ($this->isCacheMessages() && (WP_DEBUG && !empty($transient_name))) {
            $cache_message = '<!-- Served from cache -->';
            $cache_message = preg_replace('/ -->/', ': ' . $transient_name . ' with locale ' . $locale . ' -->', $cache_message);
            echo $cache_message;
        }

        $this->logMessage('========= setTransient =========');
        $this->logMessage('transient: ' . $transient_name);
        $this->logMessage('locale: ' . $locale);
        $this->logMessage('key: ' . $key);
        $this->logMessage('================================');

        set_transient($key, $content, $expiration);

        return $content;
    }

    /**
     * @param bool|string $transient_name
     * @param bool|string $locale
     *
     * @param bool $keyGenerated
     *
     * @return bool
     */
    public function deleteTransient($transient_name = false, $locale = false, $keyGenerated = false) {
        if (empty($transient_name)) {
            return null;
        }

        if (!$keyGenerated) {
            $key = $this->generateTransientKey($transient_name, $locale);
        } else {
            $key = $transient_name;
        }
        $this->deleteTransientKey($key, $locale);

        $this->logMessage('========= deleteTransient =========');
        $this->logMessage('transient: ' . $transient_name);
        $this->logMessage('locale: ' . $locale);
        $this->logMessage('key: ' . $key);
        $this->logMessage('================================');

        return delete_transient($key);
    }

    /**
     * @param bool|string $locale
     *
     * @return bool
     */
    public function deleteTransients($locale = false) {
        $transient_keys = $this->getTransientKeys($locale);
        if (empty($transient_keys)) {
            $this->setTransientKeys([], $locale);

            return true;
        }
        foreach ($transient_keys as $t) {
            $this->deleteTransient($t, $locale, true);
        }
        $this->setTransientKeys([], $locale);

        return true;
    }

    /**
     * @return true
     */
    public function deleteAllTransients() {
        foreach ($this->getAllLocales() as $locale) {
            $this->deleteTransients($locale);
        }

        return true;
    }

    /**
     * @param int|bool $post_id
     * @param bool $locale
     *
     * @return bool
     *
     */
    public function deletePostTransients($post_id = false, $locale = false) {
        if (empty($post_id)) {
            return null;
        }
        $transient_keys = $this->getTransientKeys($locale);
        $input          = preg_quote('(' . $post_id . ')', '~'); // don't forget to quote input string!
        $transients     = preg_grep('~' . $input . '~', $transient_keys);
        if (empty($transients)) {
            return null;
        }
        foreach ($transients as $key => $transient) {
            $arr                = explode('|', $transient);
            $transientKeyLocale = $arr[1];
            $this->deleteTransient($transient, $transientKeyLocale, true);
        }

        return true;
    }

    /**
     * @param bool|string $transient_name
     * @param bool|string $locale
     *
     * @return bool
     */
    public function deleteTransientsLike($transient_name = false, $locale = false) {
        if (empty($transient_name)) {
            return null;
        }

        $transient_keys = $this->getTransientKeys($locale);
        //magic, don't touch
        $input      = preg_quote($transient_name, '~');
        $transients = preg_grep('~' . $input . '~', $transient_keys);

        if (empty($transients)) {
            $this->logMessage('========= deleteTransientsLike =========');
            $this->logMessage('not found in transient keys list: ' . $transient_name);
            $this->logMessage('========================================');
            return null;
        }

        $this->logMessage('========= deleteTransientsLike =========');
        $this->logMessage('delete transient like: ' . $transient_name);
        if (!empty($transients)) {
            $this->logMessage('found ' . count($transients) . ' keys');
        }
        // Remove all transients found one by one
        foreach ($transients as $key => $transient) {
            $arr                = explode('|', $transient);
            $transientKeyLocale = $arr[1];
            $this->logMessage('delete transient: ' . $transient);
            $this->logMessage('locale: ' . $transientKeyLocale);
            $this->logMessage('function locale: ' . $locale);
            $this->logMessage('caller locale: ' . $this->getLocale());
            $this->deleteTransient($transient, $transientKeyLocale, true);
        }
        $this->logMessage('========================================');

        return true;
    }

    /**
     * @param bool|string $transient_name
     *
     * @return bool
     */
    public function deleteAllTransientsLike($transient_name = false) {
        if (empty($transient_name)) {
            return null;
        }
        foreach ($this->getAllLocales() as $locale) {
            $this->deleteTransientsLike($transient_name, $locale);
        }

        return true;
    }
} // End Transient Extensions class
