<?php
/*
 * Plugin Name: Transient Manager
 * Version: 0.0.1
 * Plugin URI: https://github.com/frc/frc-transients-api
 * Description: This is a helper plugin for transients
 * Author: Janne Aalto
 * Author URI: https://frantic.com/
 * Text Domain: frc-transient-manager
 */
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
/**
 * Initialize Transient Extensions
 */

require_once dirname(__FILE__) . '/classes/FrcTransientManagerBase.php';
require_once dirname(__FILE__) . '/classes/FrcTransientManagerFunctions.php';

if (file_exists('bedrock-on-heroku/web/app/plugins/polylang/polylang.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/plugins/polylang/polylang.php';
}



function frcTransient() {
    $enable = getenv('FRC_TRANSIENT_DISABLE') == 'true' ? false : true;
    return Frc\FrcTransientManagerFunctions::getInstance($enable, false);
}

// Global for backwards compatibility.
$GLOBALS['frcTransient'] = frcTransient();

/**
 * @param $post_id
 */
function frc_theme_flush_acf($post_id) {

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    frcTransient()->setAcfFields($post_id);
}
add_action('acf/save_post', 'frc_theme_flush_acf', 99, 1);

