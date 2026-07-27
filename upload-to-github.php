<?php
/**
 * Plugin Name: Upload to GitHub
 * Plugin URI: https://github.com/inject3r/upload-to-github
 * Description: Upload WordPress media files directly to a GitHub repository instead of the default uploads folder.
 * Version: 1.0.0
 * Author: Abolfazl Hosseini
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: upload-to-github
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('UG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UG_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('UG_VERSION', '1.0.0');

// Load required files
require_once UG_PLUGIN_PATH . 'includes/class-github-api.php';
require_once UG_PLUGIN_PATH . 'includes/class-upload-handler.php';
require_once UG_PLUGIN_PATH . 'includes/class-settings.php';
require_once UG_PLUGIN_PATH . 'includes/class-main.php';

function ug_init() {
    $plugin = new UG_Main();
    $plugin->init();
}
add_action('plugins_loaded', 'ug_init');

register_activation_hook(__FILE__, 'ug_activate');
function ug_activate() {
    if (!get_option('ug_settings')) {
        update_option('ug_settings', array(
            'github_username' => '',
            'github_repo' => '',
            'github_token' => '',
            'repo_visibility' => 'public',
            'upload_path' => ''
        ));
    }
}

// Load translation files
add_action('init', 'ug_load_textdomain_custom');
function ug_load_textdomain_custom() {
    $domain = 'upload-to-github';
    $locale = apply_filters('plugin_locale', determine_locale(), $domain);
    
    $paths = array(
        WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo',
        WP_LANG_DIR . '/' . $domain . '-' . $locale . '.mo',
        UG_PLUGIN_PATH . 'languages/' . $domain . '-' . $locale . '.mo',
    );
    
    foreach ($paths as $mo_file) {
        if (file_exists($mo_file)) {
            load_textdomain($domain, $mo_file);
            break;
        }
    }
}

add_filter('load_textdomain_mofile', 'ug_load_textdomain_mofile', 10, 2);
function ug_load_textdomain_mofile($mofile, $domain) {
    if ($domain === 'upload-to-github') {
        $locale = determine_locale();
        
        $custom_mofile = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';
        if (file_exists($custom_mofile)) {
            return $custom_mofile;
        }
        
        $plugin_mofile = UG_PLUGIN_PATH . 'languages/' . $domain . '-' . $locale . '.mo';
        if (file_exists($plugin_mofile)) {
            return $plugin_mofile;
        }
    }
    return $mofile;
}