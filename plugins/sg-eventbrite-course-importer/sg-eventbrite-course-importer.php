<?php
/**
 * Main plugin File
 *
 * @package SG\EventbriteCourseImporter
 *
 * Plugin Name:       Eventbrite Course Importer
 * Plugin URI:        https://example.com
 * Description:       This plugin imports eventbrite events as courses, using the Eventbrite API key.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0+
 * Author:            Web Development Team
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sg-eventbrite-course-importer
 * Domain Path:       /languages

 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_FILE', __FILE__ );
define( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_VERSION', '1.0.0' );

// Composer autoloader.
if ( file_exists( SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/vendor/autoload.php' ) ) {
	require_once SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/vendor/autoload.php';
}



// Initialize the plugin.
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'SG\EventbriteCourseImporter\\Plugin' ) ) {
			return;
		}

		$plugin = new SG\EventbriteCourseImporter\Plugin();
		
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
			class_exists( 'SG\EventbriteCourseImporter\\Assets' );
			
		}
	},
	1
); 