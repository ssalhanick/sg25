<?php
/**
 * Plugin Class.
 *
 * @package SG\HumanitixImporter
 */

namespace SG\HumanitixImporter;

use SG\HumanitixImporter\Security\AjaxSecurityHandler;
use SG\HumanitixImporter\Security\RestApiSecurityHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Plugin.
 */
class Plugin  {

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
	 * Initialize security utilities.
	 */
	private function init_security_utilities() {
		// Example AJAX action registration with secure handler.
		AjaxSecurityHandler::register_action(
			'sg-humanitix-importer_action',
			array( $this, 'handle_secure_ajax_request' ),
			array(
				'nonce_required'          => true,
				'capability_required'     => 'edit_posts',
				'require_login'           => true,
				'rate_limit'              => true,
				'max_requests_per_minute' => 30,
			),
			array(
				'data' => array(
					'type'          => 'text',
					'required'      => true,
					'error_message' => 'Data field is required and must be text.',
				),
			)
		);

		// Example REST API endpoint registration.
		RestApiSecurityHandler::register_endpoint(
			'sg-humanitix-importer/v1',
			'/data',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'handle_rest_request' ),
			),
			array(
				'capability_required'     => 'read',
				'require_login'           => false,
				'rate_limit'              => true,
				'max_requests_per_minute' => 100,
			)
		);
	}

	/**
	 * Handle secure AJAX request (example implementation).
	 *
	 * @param array $validated_data Validated and sanitized request data.
	 * @return array Response data.
	 */
	public function handle_secure_ajax_request( array $validated_data ): array {
		$data = $validated_data['data'] ?? '';

		// Your secure processing logic here.
		return array(
			'status'        => 'success',
			'message'       => 'Data processed securely',
			'received_data' => $data,
		);
	}

	/**
	 * Handle secure REST API request (example implementation).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @param array $validated_data Validated request data.
	 * @return \WP_REST_Response Response data.
	 */
	public function handle_rest_request( $request, $validated_data ) {
		$query = $request->get_param( 'query' ) ?? '';

		// Your REST API processing logic here.
		$data = array(
			'message' => 'REST API endpoint working',
			'query'   => $query,
			'time'    => current_time( 'mysql' ),
		);

		return new \WP_REST_Response( $data, 200 );
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