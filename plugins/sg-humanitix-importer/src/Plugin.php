<?php
/**
 * Plugin Class.
 *
 * @package SG\HumanitixImporter
 */

namespace SG\HumanitixImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Plugin.
 */
class Plugin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Activate Function
	 */
	public function activate() {
		error_log( '[sg-humanitix-importer] Plugin activated' );
	}

	/**
	 * Deactivate Function
	 */
	public function deactivate() {
		error_log( '[sg-humanitix-importer] Plugin deactivated' );
	}

	/**
	 * Initialize plugin
	 */
	private function init() {
		define( 'SG_HUMANITIX_IMPORTER_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __DIR__ ) ) );
		define( 'SG_HUMANITIX_IMPORTER_PLUGIN_URL', untrailingslashit( plugin_dir_url( __DIR__ ) ) );
		define( 'SG_HUMANITIX_IMPORTER_PLUGIN_BUILD_PATH', SG_HUMANITIX_IMPORTER_PLUGIN_PATH . '/assets/build' );
		define( 'SG_HUMANITIX_IMPORTER_PLUGIN_BUILD_URL', SG_HUMANITIX_IMPORTER_PLUGIN_URL . '/assets/build' );
		define( 'SG_HUMANITIX_IMPORTER_PLUGIN_VERSION', '1.0.0' );

		new Assets();
		new Patterns();
		new BlockManager();
		// Initialize security utilities.
		$this->init_security_utilities();
	}

	/**
	 * Init Hooks
	 */
	public function init_hooks(): void {
		add_action(
			'admin_footer',
			function () {
				// This is safe because we're outputting a static string, but for best practices.
				echo '<script>console.log("sg-humanitix-importer:", window.sgHumanitixImporter);</script>';
			}
		);
	}
} 