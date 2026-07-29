<?php
/**
 * Plugin Name: Upload to GitHub
 * Plugin URI: https://github.com/inject3r/upload-to-github
 * Description: Upload WordPress media files directly to a GitHub repository instead of the default uploads folder.
 * Version: 1.0.1
 * Author: Abolfazl Hosseini
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: upload-to-github
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package Upload_To_GitHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UG_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'UG_VERSION', '1.0.1' );

// Load required files.
require_once UG_PLUGIN_PATH . 'includes/class-ug-github-api.php';
require_once UG_PLUGIN_PATH . 'includes/class-ug-upload-handler.php';
require_once UG_PLUGIN_PATH . 'includes/class-ug-settings.php';
require_once UG_PLUGIN_PATH . 'includes/class-ug-main.php';

if ( ! function_exists( 'ug_init' ) ) {
	/**
	 * Bootstrap the plugin once all other plugins have loaded.
	 *
	 * @return void
	 */
	function ug_init() {
		$plugin = new UG_Main();
		$plugin->init();
	}
}
add_action( 'plugins_loaded', 'ug_init' );

if ( ! function_exists( 'ug_activate' ) ) {
	/**
	 * Seed the default plugin settings on activation.
	 *
	 * @return void
	 */
	function ug_activate() {
		if ( ! get_option( 'ug_settings' ) ) {
			update_option(
				'ug_settings',
				array(
					'github_username' => '',
					'github_repo'     => '',
					'github_token'    => '',
					'repo_visibility' => 'public',
					'upload_path'     => '',
				)
			);
		}
	}
}
register_activation_hook( __FILE__, 'ug_activate' );

if ( ! function_exists( 'ug_load_textdomain_custom' ) ) {
	/**
	 * Load the plugin translation file for the current locale.
	 *
	 * WordPress 4.6+ loads translations for WordPress.org-hosted plugins
	 * automatically; this fallback keeps manually installed .mo files working.
	 *
	 * @return void
	 */
	function ug_load_textdomain_custom() {
		$domain = 'upload-to-github';
		$locale = apply_filters( 'plugin_locale', determine_locale(), $domain );

		$paths = array(
			WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo',
			WP_LANG_DIR . '/' . $domain . '-' . $locale . '.mo',
			UG_PLUGIN_PATH . 'languages/' . $domain . '-' . $locale . '.mo',
		);

		foreach ( $paths as $mo_file ) {
			if ( file_exists( $mo_file ) ) {
				load_textdomain( $domain, $mo_file );
				break;
			}
		}
	}
}
add_action( 'init', 'ug_load_textdomain_custom' );

if ( ! function_exists( 'ug_load_textdomain_mofile' ) ) {
	/**
	 * Redirect the plugin .mo file lookup to a manually installed translation.
	 *
	 * @param string $mofile Current .mo file path.
	 * @param string $domain Text domain being loaded.
	 * @return string
	 */
	function ug_load_textdomain_mofile( $mofile, $domain ) {
		if ( 'upload-to-github' === $domain ) {
			$locale = determine_locale();

			$custom_mofile = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';
			if ( file_exists( $custom_mofile ) ) {
				return $custom_mofile;
			}

			$plugin_mofile = UG_PLUGIN_PATH . 'languages/' . $domain . '-' . $locale . '.mo';
			if ( file_exists( $plugin_mofile ) ) {
				return $plugin_mofile;
			}
		}
		return $mofile;
	}
}
add_filter( 'load_textdomain_mofile', 'ug_load_textdomain_mofile', 10, 2 );
