<?php
/*
 * Plugin Name: Transient Manager
 * Version: 0.0.1
 * Plugin URI: https://github.com/JanneAalto/transient-manager
 * Description: This is a helper plugin for transients
 * Author: Janne Aalto
 * Author URI: https://frantic.com/
 * Text Domain: frc-transient-manager
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
/**
 * Initialize Transient Extensions
 */
require_once dirname(__FILE__) . '/classes/FrcTransientManagerBase.php';
require_once dirname(__FILE__) . '/classes/FrcTransientManagerFunctions.php';

function frcTransient() {
    $enable    = getenv('FRC_TRANSIENT_DISABLE') == 'true' ? false : true;
    $className = apply_filters('frcTransientClass', 'Frc\FrcTransientManagerFunctions');

    return $className::getInstance($enable, false);
}

/**
 * @param $post_id
 */
function frcFlushAcf($post_id) {

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    frcTransient()->setAcfFields($post_id);
}

add_action('acf/save_post', 'frcFlushAcf', 99, 1);

function frcPostDeleted($post_id) {

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (function_exists('pll_get_post_language')) {
        $post_locale = pll_get_post_language($post_id, 'locale');
    } else {
        $post_locale = get_option('WPLANG');
    }
    frcTransient()->deletePostTransients($post_id, $post_locale);
}

add_action('before_delete_post', 'frcPostDeleted', 10, 1);
add_action('trash_post', 'frcPostDeleted', 10, 1);

function frcPostUntrashed($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    frcTransient()->setAcfFields($post_id);
}

add_action('untrashed_post', 'frcPostUntrashed', 10, 1);
