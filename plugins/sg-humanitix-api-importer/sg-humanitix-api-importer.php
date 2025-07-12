<?php
/**
 * Main plugin File
 *
 * @package SG\HumanitixApiImporter
 *
 * Plugin Name:       Humanitix API Importer
 * Plugin URI:        https://example.com
 * Description:       This plugin imports Humanitix events through their API and converts them into The Events Calendar events.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Scott Salhanick
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sg-humanitix-api-importer
 * Domain Path:       /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_FILE', __FILE__ );
define( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
// Note: Other constants are defined in Plugin.php to avoid conflicts

// Composer autoloader.
if ( file_exists( SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH . '/vendor/autoload.php' ) ) {
	require_once SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH . '/vendor/autoload.php';
}



// Initialize the plugin.
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'SG\\HumanitixApiImporter\\Plugin' ) ) {
			return;
		}

		$plugin = new SG\HumanitixApiImporter\Plugin();

		// Register activation/deactivation hooks.
		register_activation_hook( __FILE__, array( $plugin, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $plugin, 'deactivate' ) );
	}
);

// Initialize hooks early for admin functionality.
add_action(
	'init',
	function () {
		if ( ! is_admin() ) {
			// Preload critical classes for frontend.
			class_exists( 'SG\\HumanitixApiImporter\\Assets' );

		}
	},
	1
);
