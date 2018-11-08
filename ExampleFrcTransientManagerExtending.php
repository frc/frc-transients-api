<?php
/**
 * Created by PhpStorm.
 * User: janneaalto
 * Date: 08/11/2018
 * Time: 10.42
 */

namespace Frc;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class FrcTransientManagerExtensions extends FrcTransientManagerFunctions {

    protected static $instance = null;

    public static function getInstance(
        bool $enable = true,
        bool $cache_messages = true,
        string $logged_in_suffix = ''
    ) {
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

    public function setPostData($post_id, $post = false, $locale = false, $expiration = false) {
        if (function_exists('pll_get_post_language')) {
            $post_locale = pll_get_post_language($post_id, 'locale');
        } else {
            $post_locale = get_option('WPLANG');
        }

        if ($locale != false) {
            $post_locale = $locale;
        }

        $post_id        = intval($post_id);
        $transient_name = '(' . $post_id . ')-postData';

        if (!($post instanceof WP_Post)) {
            $post = get_post($post_id);
        }
        if ($post) {
            $post->{'permalink'} = get_permalink($post);
            $this->setTransient($transient_name, $post, $post_locale, $expiration);
        }
    }

    public function getPostData($post_id, $locale = false, $expiration = false) {
        if (!$post_id) {
            return null;
        }

        if (!$locale) {
            $locale = $this->getLocale();
        }

        $post_id        = intval($post_id);
        $transient_name = '(' . $post_id . ')-postData';
        $post_data      = $this->getTransient($transient_name, $locale);
        if ($post_data) {
            return $post_data;
        } else {
            $post_data = get_post($post_id);
            if (!empty($post_data)) {
                $post_data->{'permalink'} = get_permalink($post_data);
                return $this->setTransient($transient_name, $post_data, $locale, $expiration);
            }
        }

        return null;
    }
}

add_filter('frcTransientClass', function ($class) {
    $class = 'Frc\FrcTransientManagerExtensions';
    return $class;
});
