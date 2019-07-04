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
use function method_exists;
use function pll_languages_list;
use function preg_grep;
use function preg_quote;
use function preg_replace;
use function print_r;
use function set_transient;
use function strlen;
use function wp_using_ext_object_cache;

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
    private $redis = false;

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

        if ($this->checkForRedisCache()) {
            $this->redis = new FrcRedisObjectCacheList();
        }
    }

    /**
     * @return bool
     */
    public function checkForRedisCache() {
        if (!wp_using_ext_object_cache()) {
            return false;
        }

        global $redisObjectCache;
        if (!isset($redisObjectCache) || empty($redisObjectCache)) {
            return false;
        }

        if (!method_exists($redisObjectCache, 'validate_object_cache_dropin') || !method_exists($redisObjectCache, 'get_redis_status')) {
            return false;
        }

        if ($redisObjectCache->validate_object_cache_dropin()) {
            if ($redisObjectCache->get_redis_status()) {
                global $wp_object_cache;
                if (!method_exists($wp_object_cache, 'redis_instance')) {
                    return false;
                }
                return true;
            }
        }

        return false;
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

    /**
     * @param $message
     * @param string $function
     */
    public function logMessage($message, $function = '') {
        if ($this->log) {
            if ($function != '') {
                $function = str_pad($function, 25, " ", STR_PAD_LEFT);
                $function = $function . ': ';
            }
            error_log(print_r('frc-transient-manager ' . $function . $message, true));
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

        return 'transientApi:' . $this->db_prefix . '|' . $locale . '|';
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

    public function findTransientKeys($key, $locale) {
        if ($this->redis != false) {
            return $this->redis->keys($this->transientPrefix($locale) . '*' . $key . '*');
        } else {
            error_log(print_r('falsely using redis, might be a bug', true));
            die('falsely using redis, might be a bug');
        }
    }

    /**
     * @param bool|string $locale
     *
     * @return array
     */
    public function getTransientKeys($locale = false) {
        if ($this->redis != false) {
            return $this->redis->keys($this->transientPrefix($locale) . '*');
        } else {
            return $this->getTransient($this->optionName(), $locale) ?: [];
        }
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

        if ($this->redis != false) {
            return $content;
        }

        if (false === $content) {
            $content = [];
        }

        $key = $this->generateTransientKey($this->optionName(), $locale);

        $logFunction = 'setTransientKeys';
        $this->logMessage('========= ' . $logFunction . ' =========');
        $this->logMessage('setTransientKeys count: ' . count($content), $logFunction);
        $this->logMessage('setTransientKeys locale: ' . $locale, $logFunction);
        $this->logMessage('setTransientKeys key: ' . $key, $logFunction);
        $this->logMessage('/======== ' . $logFunction . ' =========');

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
        if ($this->redis != false) {
            return $transient_keys;
        }

        $logFunction = 'updateTransientKeys';
        $this->logMessage('========= ' . $logFunction . ' =========');
        $this->logMessage('before count: ' . count($transient_keys), $logFunction);
        $this->logMessage('locale: ' . $locale, $logFunction);
        $this->logMessage('add: ' . $new_transient_key, $logFunction);

        if (in_array($new_transient_key, $transient_keys)) {
            $this->logMessage('!!already in!!', $logFunction);
        }

        if (!in_array($new_transient_key, $transient_keys)) {
            $transient_keys[] = $new_transient_key;
            $transient_keys   = array_values($transient_keys);
            $this->setTransientKeys($transient_keys, $locale);
        }

        $this->logMessage('/======== ' . $logFunction . ' =========');

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

        if ($this->redis != false) {
            return true;
        }

        $transient_keys = $this->getTransientKeys($locale);

        $count       = count($transient_keys);
        $logFunction = 'deleteTransientKey';
        $this->logMessage('========= ' . $logFunction . ' =========');
        $this->logMessage('before count: ' . $count, $logFunction);
        $this->logMessage('locale: ' . $locale, $logFunction);
        $this->logMessage('remove: ' . $transient_key, $logFunction);

        $transient_keys = array_filter($transient_keys, function ($e) use ($transient_key) {
            return ($e != $transient_key);
        });

        $filter = count($transient_keys);

        if ($filter < $count) {
            $transient_keys = array_values($transient_keys);
            $this->setTransientKeys($transient_keys, $locale);
        } else {
            $this->logMessage('!!not found!!', $logFunction);
        }
        $this->logMessage('/======== ' . $logFunction . ' =========');

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

        $logFunction = 'setTransient';
        $this->logMessage('========= ' . $logFunction . ' =========');
        $this->logMessage('transient: ' . $transient_name, $logFunction);
        $this->logMessage('locale: ' . $locale, $logFunction);
        $this->logMessage('key: ' . $key, $logFunction);
        $this->logMessage('/======== ' . $logFunction . ' =========');

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

        $logFunction = 'deleteTransient';
        $this->logMessage('========= ' . $logFunction . ' =========');
        $this->logMessage('transient: ' . $transient_name, $logFunction);
        $this->logMessage('locale: ' . $locale, $logFunction);
        $this->logMessage('key: ' . $key, $logFunction);
        $this->logMessage('/======== ' . $logFunction . ' =========');

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
        if ($this->redis != false) {
            $transients = $this->findTransientKeys('(' . $post_id . ')', $locale);
        } else {
            $transient_keys = $this->getTransientKeys($locale);
            $input = preg_quote('(' . $post_id . ')', '~'); // don't forget to quote input string!
            $transients = preg_grep('~' . $input . '~', $transient_keys);
        }

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

        if ($this->redis != false) {
            $transients = $this->findTransientKeys($transient_name, $locale);
        } else {
            $transient_keys = $this->getTransientKeys($locale);
            $input      = preg_quote($transient_name, '~');
            $transients = preg_grep('~' . $input . '~', $transient_keys);
        }

        $logFunction = 'deleteTransientsLike';
        if (empty($transients)) {
            $this->logMessage('========= ' . $logFunction . ' =========');
            $this->logMessage('not found in transient keys list: ' . $transient_name, $logFunction);
            $this->logMessage('/======== ' . $logFunction . ' =========');

            return null;
        }

        $this->logMessage('========= ' . $logFunction . ' =========');
        $this->logMessage('delete transient like: ' . $transient_name, $logFunction);
        if (!empty($transients)) {
            $this->logMessage('found ' . count($transients) . ' keys', $logFunction);
        }
        // Remove all transients found one by one
        foreach ($transients as $key => $transient) {
            $arr                = explode('|', $transient);
            $transientKeyLocale = $arr[1];

            $this->logMessage('delete transient: ' . $transient, $logFunction);
            $this->logMessage('locale: ' . $transientKeyLocale, $logFunction);
            $this->logMessage('function locale: ' . $locale, $logFunction);
            $this->logMessage('caller locale: ' . $this->getLocale(), $logFunction);

            $this->deleteTransient($transient, $transientKeyLocale, true);
        }
        $this->logMessage('/======== ' . $logFunction . ' =========');

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
