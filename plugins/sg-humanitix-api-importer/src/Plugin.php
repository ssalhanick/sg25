<?php
/**
 * Plugin Class.
 *
 * Main plugin class that initializes and manages the Humanitix API Importer plugin.
 * Handles plugin activation, deactivation, initialization, and core functionality.
 *
 * @package SG\HumanitixApiImporter
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter;

use SG\HumanitixApiImporter\Security\AjaxSecurityHandler;
use SG\HumanitixApiImporter\Security\RestApiSecurityHandler;
use SG\HumanitixApiImporter\Admin\Logger;
use SG\HumanitixApiImporter\Admin\AdminInterface;
use SG\HumanitixApiImporter\Admin\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main Plugin Class.
 *
 * Main plugin class that initializes and manages the Humanitix API Importer plugin.
 * Handles plugin activation, deactivation, initialization, and core functionality.
 *
 * @package SG\HumanitixApiImporter
 * @since 1.0.0
 */
class Plugin {

	/**
	 * The Humanitix API instance.
	 *
	 * @var HumanitixAPI
	 */
	private $api;

	/**
	 * The events importer instance.
	 *
	 * @var Importer\EventsImporter
	 */
	private $importer;

	/**
	 * The admin interface instance.
	 *
	 * @var Admin\AdminInterface
	 */
	private $admin;

	/**
	 * The settings manager instance.
	 *
	 * @var Admin\SettingsManager
	 */
	private $settings;

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * Initializes the plugin and sets up all necessary components.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Plugin activation hook.
	 *
	 * Handles plugin activation tasks including database setup and logging.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activate() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[sg-humanitix-api-importer] Plugin activated' );
		}
		$this->create_logs_table();
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * Handles plugin deactivation tasks and cleanup.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deactivate() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[sg-humanitix-api-importer] Plugin deactivated' );
		}
	}

	/**
	 * Initialize plugin components and settings.
	 *
	 * Sets up plugin constants, initializes components, and checks database version.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init() {
		// Define constants only if they haven't been defined already.
		if ( ! defined( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH' ) ) {
			define( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __DIR__ ) ) );
		}
		if ( ! defined( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_URL' ) ) {
			define( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_URL', untrailingslashit( plugin_dir_url( __DIR__ ) ) );
		}
		if ( ! defined( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_BUILD_PATH' ) ) {
			define( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_BUILD_PATH', SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH . '/assets/build' );
		}
		if ( ! defined( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_BUILD_URL' ) ) {
			define( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_BUILD_URL', SG_HUMANITIX_API_IMPORTER_PLUGIN_URL . '/assets/build' );
		}
		if ( ! defined( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION' ) ) {
			define( 'SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION', '1.0.0' );
		}

		new Assets();

		// Initialize Humanitix API components.
		$this->init_api_components();

		// Initialize security utilities.
		$this->init_security_utilities();

		// Check database version and update if needed.
		$this->check_database_version();
	}

	/**
	 * Initialize API components and dependencies.
	 *
	 * Sets up the API client, importer, logger, and admin components.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_api_components() {
		// Initialize logger.
		$this->logger = new Logger();

		// Initialize settings manager.
		$this->settings = new SettingsManager();

		// Get API settings from options.
		$options      = get_option( 'humanitix_importer_options', array() );
		$api_key      = $options['api_key'] ?? '';
		$org_id       = $options['org_id'] ?? '';
		$api_endpoint = $options['api_endpoint'] ?? '';

		// Initialize admin interface with settings.
		$this->admin = new AdminInterface( null, $this->settings );

		if ( ! empty( $api_key ) && ! empty( $org_id ) ) {
			// Initialize API client with organization ID.
			$this->api = new HumanitixAPI( $api_key, $api_endpoint, $org_id );

			// Initialize importer with logger.
			$this->importer = new Importer\EventsImporter( $this->api, $this->logger );

			// Update admin interface with importer.
			$this->admin->set_importer( $this->importer );
		}
	}

	/**
	 * Initialize security utilities and handlers.
	 *
	 * Sets up AJAX and REST API security handlers with proper validation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_security_utilities() {
		// Example AJAX action registration with secure handler.
		AjaxSecurityHandler::register_action(
			'sg-humanitix-api-importer_action',
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
			'sg-humanitix-api-importer/v1',
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
	 * Handle secure AJAX request.
	 *
	 * Processes validated AJAX requests and returns appropriate responses.
	 *
	 * @since 1.0.0
	 * @param array $validated_data Validated and sanitized request data.
	 * @return array Response data with status and message.
	 */
	public function handle_secure_ajax_request( array $validated_data ): array {
		$action = $validated_data['action'] ?? '';

		switch ( $action ) {
			case 'import_events':
				if ( isset( $this->importer ) ) {
					$result = $this->importer->import_events();
					return array(
						'status'         => $result['success'] ? 'success' : 'error',
						'message'        => $result['success'] ? 'Events imported successfully' : $result['error'],
						'imported_count' => $result['imported'],
						'errors'         => $result['errors'],
					);
				}
				return array(
					'status'  => 'error',
					'message' => 'API not configured',
				);

			default:
				return array(
					'status'  => 'error',
					'message' => 'Invalid action',
				);
		}
	}

	/**
	 * Handle secure REST API request.
	 *
	 * Processes validated REST API requests and returns appropriate responses.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @param array            $validated_data Validated request data.
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
	 * Initialize WordPress hooks.
	 *
	 * Sets up various WordPress hooks and filters for the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_hooks(): void {
		add_action(
			'admin_footer',
			function () {
				// This is safe because we're outputting a static string, but for best practices.
				echo '<script>console.log("sg-humanitix-api-importer:", window.sgHumanitixApiImporter);</script>';
			}
		);
	}

	/**
	 * Create logs table in database.
	 *
	 * Creates the custom logs table for storing import and error logs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function create_logs_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'humanitix_import_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY level (level),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Add version option to track schema updates.
		add_option( 'humanitix_importer_db_version', '1.0.0' );
	}

	/**
	 * Check database version and update if needed.
	 *
	 * Compares current database version with plugin version and runs updates if necessary.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function check_database_version() {
		$current_version = get_option( 'humanitix_importer_db_version', '0.0.0' );
		$plugin_version  = '1.0.0'; // Your plugin version.

		if ( version_compare( $current_version, $plugin_version, '<' ) ) {
			$this->update_database( $current_version, $plugin_version );
			update_option( 'humanitix_importer_db_version', $plugin_version );
		}
	}

	/**
	 * Update database schema.
	 *
	 * Handles database schema updates between plugin versions.
	 *
	 * @since 1.0.0
	 * @param string $from_version The version updating from.
	 * @param string $to_version The version updating to.
	 * @return void
	 */
	private function update_database( $from_version, $to_version ) {
		global $wpdb;

		// Handle database schema updates here.
		if ( version_compare( $from_version, '1.0.0', '<' ) ) {
			// Create table if it doesn't exist.
			$this->create_logs_table();
		}

		// Future version updates would go here.
		// if (version_compare($from_version, '1.1.0', '<')) {
		// Add new columns, etc.
		// }
	}
}
