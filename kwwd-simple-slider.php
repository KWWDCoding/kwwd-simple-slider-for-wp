<?php
/**
 * Plugin Name: Simple Slider by KWWD
 * Plugin URI:  https://www.kwwdoc.uk/blog/Simple-Slider
 * Description: Custom image carousel slider with URL Shortify integration.
 * Version:     1.1.9
 * Author:      KWWD
 * License:     GPL3
 * Licence URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Update URI: https://raw.githubusercontent.com/KWWDCoding/kwwd-simple-slider-for-wp/main/assets/';
 */

defined('ABSPATH') || exit;
define('KWWDSlider_SLIDER_VERSION', '1.1.9');
/**************************************************************
 * UPDATE CHECKER (GITHUB Method)
 *************************************************************/
// Use the RAW content URL from GitHub
$githubAssets = 'https://raw.githubusercontent.com/KWWDCoding/kwwd-simple-slider-for-wp/main/assets/';

require_once plugin_dir_path(__FILE__) . 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/KWWDCoding/kwwd-simple-slider-for-wp/',
    __FILE__,
    'kwwd-simple-slider-for-wp'
);
// Since you're using GitHub's "Releases" feature to host the ZIPs:
$myUpdateChecker->getVcsApi()->enableReleaseAssets();

/** PLUGIN ICONS ***/
$myUpdateChecker->addResultFilter(function($info) use ($githubAssets) {
    if ($info) {
        $info->icons = array(
            '1x'      => $githubAssets . 'icon-128x128.png',
            '2x'      => $githubAssets . 'icon-256x256.png', // Optional
            'default' => $githubAssets . 'icon-128x128.png',
        );
    }
    return $info;
});
/***************** END PLUGIN UPDATE **************************/

define('KWWDSlider_SLIDER_DIR',     plugin_dir_path(__FILE__));
define('KWWDSlider_SLIDER_URL',     plugin_dir_url(__FILE__));

// URL Shortify settings
define('KWWDSlider_SHORTIFY_GROUP', 5);
define('KWWDSlider_IMG_MAX_WIDTH',  150);
define('KWWDSlider_IMG_MAX_HEIGHT', 225);

// ── Includes ──────────────────────────────────────────────────────────────────
require_once KWWDSlider_SLIDER_DIR . 'includes/global-settings.php'; // Must come before db.php (KWWDSlider_get_active_slider_settings depends on it)
require_once KWWDSlider_SLIDER_DIR . 'includes/db.php';
require_once KWWDSlider_SLIDER_DIR . 'includes/shortcode.php';
require_once KWWDSlider_SLIDER_DIR . 'includes/admin.php';

// ── Activation ────────────────────────────────────────────────────────────────
register_activation_hook(__FILE__, 'KWWDSlider_slider_activate');

function KWWDSlider_slider_activate() {
    KWWDSlider_slider_create_tables();
}

// ── Scripts & Styles ──────────────────────────────────────────────────────────
add_action('wp_enqueue_scripts', 'KWWDSlider_slider_frontend_assets');

function KWWDSlider_slider_frontend_assets() {
    wp_enqueue_style(
        'swiper',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        [],
        '11'
    );
    wp_enqueue_script(
        'swiper',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        [],
        '11',
        true
    );
    wp_enqueue_style(
        'kwwd-slider',
        KWWDSlider_SLIDER_URL . 'assets/slider.css',
        ['swiper'],
        KWWDSlider_SLIDER_VERSION
    );
    wp_enqueue_script(
        'kwwd-slider',
        KWWDSlider_SLIDER_URL . 'assets/slider.js',
        ['swiper'],
        KWWDSlider_SLIDER_VERSION,
        true
    );
}

add_action('admin_enqueue_scripts', 'KWWDSlider_slider_admin_assets');

function KWWDSlider_slider_admin_assets($hook) {
    if (strpos($hook, 'kwwd-slider') === false) return;
    wp_enqueue_media();
    wp_enqueue_style(
        'kwwd-slider-admin',
        KWWDSlider_SLIDER_URL . 'assets/admin.css',
        [],
        KWWDSlider_SLIDER_VERSION
    );
    wp_enqueue_script(
        'kwwd-slider-admin',
        KWWDSlider_SLIDER_URL . 'assets/admin.js',
        ['jquery', 'jquery-ui-sortable'],
        KWWDSlider_SLIDER_VERSION,
        true
    );
    wp_localize_script('kwwd-slider-admin', 'wkrnSlider', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('KWWDSlider_slider_nonce'),
    ]);
}
?>