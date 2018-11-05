<?php

namespace Frc;

use function get_post;
use Frc;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
/**
 * Created by PhpStorm.
 * User: janneaalto
 * Date: 16/01/2018
 * Time: 13.38
 */

class FrcTransientManagerFunctions extends FrcTransientManagerBase {

    protected static $instance = null;

    public static function getInstance(bool $enable = true, bool $cache_messages = true, string $logged_in_suffix = '') {
        if (is_null(self::$instance)) {
            self::$instance = new self($enable, $cache_messages, $logged_in_suffix);
        }

        return self::$instance;
    }

    /**
     * FrcTransientManagerFunctions constructor.
     *
     * @param bool $enable
     * @param bool $cache_messages
     * @param string $logged_in_suffix
     */
    public function __construct(bool $enable = true, bool $cache_messages = true, string $logged_in_suffix = '') {
        parent::__construct($enable, $cache_messages, $logged_in_suffix);
    }

    /**
     * Compress HTML before saving to frc_transient
     *
     * @param string $content
     *
     * @return string
     */
    public function compressTransient($content) {
        $content = str_replace("\t", '', $content);
        $content = str_replace("\n", '', $content);

        return $content;
    }

    /**
     * @param $response
     *
     * @return bool
     */
    public function checkResponse($response) {

        if (! is_array($response)) {
            return false;
        }

        if (is_wp_error($response)) {
            return false;
        }

        if (! isset($response['response'])) {
            return false;
        }

        if (! isset($response['response']['code'])) {
            return false;
        }

        if (4 === $response['response']['code'][0] || 5 === $response['response']['code'][0]) {
            return false;
        }

        return $response['response']['code'];
    }

    /**
     * @param string $transient_name
     * @param callable $function
     * @param array $args
     * @param bool|string $locale
     * @param bool|int $expiration
     *
     * @return bool
     */
    public function theBufferFuncTransient($transient_name, $function, $args = [], $locale = false, $expiration = false) {

        if (! isset($transient_name) || ! is_callable($function)) {
            return null;
        }

        echo $this->callBufferFuncTransient($transient_name, $function, $args, $locale, $expiration);

        return true;
    }

    /**
     * @param string $transient_name
     * @param callable $function
     * @param array $args
     * @param bool|string $locale
     * @param bool|int $expiration
     *
     * @return bool|string
     */
    public function callBufferFuncTransient($transient_name, $function, $args = [], $locale = false, $expiration = false) {

        if (! isset($transient_name) || ! is_callable($function)) {
            return null;
        }

        $output = $this->getTransient($transient_name, $locale);

        if (! empty($output)) {
            return $output;
        } else {
            if ($locale != false && $locale != $this->locale) {
                switch_to_locale($locale);
            }

            ob_start();
            call_user_func_array($function, $args);
            $output = ob_get_clean();

            if ($locale != false && $locale != $this->locale) {
                restore_previous_locale();
            }

            $output = $this->compressTransient($output);

            return $this->setTransient($transient_name, $output, $locale, $expiration);
        }
    }

    /**
     * @param string $transient_name
     * @param callable $function
     * @param array $args
     * @param bool|string $locale
     * @param bool|int $expiration
     *
     * @return bool
     */
    public function theFuncTransient($transient_name, $function, $args = [], $locale = false, $expiration = false) {

        if (! isset($transient_name) || ! is_callable($function)) {
            return null;
        }

        echo $this->callFuncTransient($transient_name, $function, $args, $locale, $expiration);

        return true;
    }

    /**
     * @param string $transient_name
     * @param callable $function
     * @param array $args
     * @param bool|string $locale
     * @param bool|int $expiration
     *
     * @return bool|string|array
     */
    public function callFuncTransient($transient_name, $function, $args = [], $locale = false, $expiration = false) {

        if (! $transient_name || ! is_callable($function)) {
            return null;
        }

        $output = $this->getTransient($transient_name, $locale);

        if (! empty($output)) {
            return $output;
        } else {
            if ($locale != false && $locale != $this->locale) {
                switch_to_locale($locale);
            }

            $output = call_user_func_array($function, $args);

            if ($locale != false && $locale != $this->locale) {
                restore_previous_locale();
            }

            return $this->setTransient($transient_name, $output, $locale, $expiration);
        }
    }

    /**
     * @param string $url
     * @param array $query
     * @param array $args
     * @param bool|int $expiration
     *
     * @return bool
     */
    public function theFile($url, $query = [], $args = [], $expiration = false) {

        if (! $url) {
            return null;
        }

        echo $this->getFile($url, $query, $args, $expiration);

        return true;
    }

    /**
     * @param string $url
     * @param array $query
     * @param array $args
     * @param bool|int $expiration
     *
     * @return bool|mixed
     */
    public function getFile($url, $query = [], $args = [], $expiration = false) {

        if (! $url) {
            return null;
        }

        $locale         = 'global';
        $url            = add_query_arg($query, $url);
        $transient_name = md5($url);
        $output         = $this->getTransient($transient_name, $locale);

        if (! empty($output)) {
            return $output;
        } else {
            $output = wp_safe_remote_get($url, $args);

            if (! $this->checkResponse($output)) {
                return null;
            }

            return $this->setTransient($transient_name, $output, $locale, $expiration);
        }
    }

    private function normalizeAcfPostId($post_id) {
        if ($post_id == 'option') {
            return 'options';
        }
        return $post_id;
    }

    /**
     * @param $post_id
     * @param bool $locale
     * @param bool $expiration
     * @param bool $fields
     *
     * @return null
     */
    public function setAcfFields($post_id, $locale = false, $expiration = false, $fields = false) {

        if (! $post_id) {
            return null;
        }

        $post_id = $this->normalizeAcfPostId($post_id);

        if (function_exists('pll_get_post_language')) {
            $post_locale = pll_get_post_language($post_id, 'locale');
        } else {
            $post_locale = get_option('WPLANG');
        }

        if ($locale != false) {
            $post_locale = $locale;
        }

        $transient_name = '(' . $post_id . ')-acf';

        if ($fields) {
            $post_fields = $fields;
        } else {
            if ($post_locale != false && $post_locale != $this->locale) {
                switch_to_locale($post_locale);
            }

            $post_fields = get_fields($post_id);

            if ($post_locale != false && $post_locale != $this->locale) {
                restore_previous_locale();
            }
        }

        if (! is_int($post_id) && intval($post_id) == 0) {
            $post_locale = 'options';
        } else {
            $post_id = intval($post_id);
        }

        $this->setTransient($transient_name, $post_fields, $post_locale, $expiration);
        $acf_fields                             = $this->getAcfFields($post_id, $post_locale);
        $acf_fields[ $post_locale ][ $post_id ] = $post_fields;
        $this->acf_fields                       = $acf_fields;

        return $acf_fields;
    }

    /**
     * @param $post_id
     * @param bool $locale
     *
     * @return null
     */
    protected function getAcfFields($post_id, $locale = false) {

        if (! $post_id) {
            return null;
        }

        if (! $locale) {
            $locale = $this->getLocale();
        }

        if (! is_int($post_id) && intval($post_id) == 0) {
            $locale = 'options';
        } else {
            $post_id = intval($post_id);
        }

        $transient_name                    = '(' . $post_id . ')-acf';
        $post_fields                       = $this->getTransient($transient_name, $locale);
        $acf_fields[ $locale ][ $post_id ] = $post_fields;
        $this->acf_fields                  = $acf_fields;

        if (! empty($acf_fields) && isset($acf_fields[ $locale ][ $post_id ]) && ! empty($acf_fields[ $locale ][ $post_id ])) {
            return $acf_fields;
        }

        return null;
    }

    /**
     * @param string $field
     * @param int $post_id
     * @param bool|string $locale
     * @param bool|int $expiration
     *
     * @return bool
     */
    public function theAcf($field, $post_id, $locale = false, $expiration = false) {

        if (! $post_id || ! $field) {
            return null;
        }

        $post_id = $this->normalizeAcfPostId($post_id);

        echo $this->getAcf($field, $post_id, $locale, $expiration);

        return true;
    }

    /**
     * @param $field
     * @param $post_id
     * @param bool $locale
     * @param bool $expiration
     *
     * @return mixed|null
     */
    public function getAcf($field, $post_id, $locale = false, $expiration = false) {

        if (! $post_id || ! $field) {
            return null;
        }

        $post_id = $this->normalizeAcfPostId($post_id);

        $acf_field = $this->getAcfField($field, $post_id, $locale, $this->acf_fields);

        if (! $acf_field) {
            $acf_field = $this->getAcfField($field, $post_id, $locale, $this->getAcfFields($post_id, $locale));
        }

        if ($acf_field) {
            return $acf_field;
        } else {
            //FU bbpress
//            remove_action( 'pre_get_posts', 'bbp_pre_get_posts_normalize_forum_visibility', 4 );
            $acf_field = get_field($field, $post_id);
//            add_action( 'pre_get_posts', 'bbp_pre_get_posts_normalize_forum_visibility', 4 );

            if (isset($acf_field) && ! empty($acf_field)) {
                return $this->setAcfField($acf_field, $field, $post_id, $this->acf_fields, $locale, $expiration);
            }

            return null;
        }
    }

    /**
     * @param $field
     * @param $post_id
     * @param $locale
     * @param $acf_fields
     *
     * @return null
     */
    protected function getAcfField($field, $post_id, $locale, $acf_fields) {

        if (! $post_id || ! $field) {
            return null;
        }

        if (! $locale) {
            $locale = $this->getLocale();
        }

        if (! is_int($post_id) && intval($post_id) == 0) {
            $locale = 'options';
        } else {
            $post_id = intval($post_id);
        }

        if (! empty($acf_fields) && isset($acf_fields[ $locale ][ $post_id ]) && ! empty($acf_fields[ $locale ][ $post_id ]) && isset($acf_fields[ $locale ][ $post_id ][ $field ]) && ! empty($acf_fields[ $locale ][ $post_id ][ $field ])) {
            return $acf_fields[ $locale ][ $post_id ][ $field ];
        }

        return null;
    }

    /**
     * @param $acf_field
     * @param $field
     * @param $post_id
     * @param $acf_fields
     * @param bool $locale
     * @param bool $expiration
     *
     * @return null
     */
    protected function setAcfField($acf_field, $field, $post_id, $acf_fields, $locale = false, $expiration = false) {

        if (! $post_id || ! $field || ! $acf_field || empty($acf_field)) {
            return null;
        }

        if (! $locale) {
            $locale = $this->getLocale();
        }

        if (! is_int($post_id) && intval($post_id) == 0) {
            $locale = 'options';
        } else {
            $post_id = intval($post_id);
        }

        $acf_fields[ $locale ][ $post_id ][ $field ] = $acf_field;
        $this->acf_fields                            = $acf_fields;
        $transient_name                              = '(' . $post_id . ')-acf';
        $this->setTransient($transient_name, $acf_fields[ $locale ][ $post_id ], $locale, $expiration);

        return $acf_fields[ $locale ][ $post_id ][ $field ];
    }



    /**
     * @param string $transient_name
     * @param mixed $data
     * @param bool|string $locale
     * @param bool|int $expiration
     *
     * @return bool|mixed
     */
    public function setData($transient_name, $data, $locale = false, $expiration = false) {

        if (! isset($transient_name) || ! isset($data)) {
            return null;
        }

        return $this->setTransient($transient_name, $data, $locale, $expiration);
    }

    /**
     * @param string $transient_name
     * @param bool|string $locale
     *
     * @return bool|mixed
     */
    public function getData($transient_name, $locale = false) {

        if (! isset($transient_name)) {
            return null;
        }

        $output = $this->getTransient($transient_name, $locale);

        if (! empty($output)) {
            return $output;
        } else {
            return null;
        }
    }
}
