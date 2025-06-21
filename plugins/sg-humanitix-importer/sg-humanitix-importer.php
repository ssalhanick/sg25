<?php
/**
 * Main plugin File
 *
 * @package SG\HumanitixImporter
 *
 * Plugin Name:       Humanitix Importer
 * Plugin URI:        https://example.com
 * Description:       API management tool to import Humanitix Events into The Events Calendar.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Scott Salhanick
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sg-humanitix-importer
 * Domain Path:       /languages

 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'SG_HUMANITIX_IMPORTER_PLUGIN_FILE', __FILE__ );
define( 'SG_HUMANITIX_IMPORTER_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'SG_HUMANITIX_IMPORTER_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'SG_HUMANITIX_IMPORTER_PLUGIN_VERSION', '1.0.0' );

// Composer autoloader.
if ( file_exists( SG_HUMANITIX_IMPORTER_PLUGIN_PATH . '/vendor/autoload.php' ) ) {
	require_once SG_HUMANITIX_IMPORTER_PLUGIN_PATH . '/vendor/autoload.php';
}



// Initialize the plugin.
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'SG\HumanitixImporter\\Plugin' ) ) {
			return;
		}

		$plugin = new SG\HumanitixImporter\Plugin();
		
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
			class_exists( 'SG\HumanitixImporter\\Assets' );
			
		}
	},
	1
); 