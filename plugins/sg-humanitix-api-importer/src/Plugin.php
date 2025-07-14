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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[sg-humanitix-api-importer] Plugin constructor called' );
		}
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

		// Check if auto import should be scheduled on activation.
		$options = get_option( 'humanitix_importer_options', array() );
		if ( ! empty( $options['auto_import'] ) ) {
			$frequency   = $options['import_frequency'] ?? 'daily';
			$import_time = $options['import_time'] ?? '00:00';
			$this->schedule_auto_import( $frequency, $import_time );
		}
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

		// Clear any scheduled auto import cron jobs.
		wp_clear_scheduled_hook( 'humanitix_auto_import' );
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

		// Initialize WordPress hooks.
		$this->init_hooks();
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

		// Initialize auto import functionality.
		$this->init_auto_import();
	}

	/**
	 * Initialize auto import functionality.
	 *
	 * Sets up cron jobs and hooks for automatic event imports.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_auto_import() {
		// Register the auto import hook.
		add_action( 'humanitix_auto_import', array( $this, 'run_auto_import' ) );

		// Handle settings changes to schedule/unschedule cron jobs.
		add_action( 'update_option_humanitix_importer_options', array( $this, 'handle_settings_update' ), 10, 3 );

		// Check if auto import should be scheduled on plugin load.
		$this->check_auto_import_schedule();
	}

	/**
	 * Check and schedule auto import if enabled.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function check_auto_import_schedule() {
		$options = get_option( 'humanitix_importer_options', array() );

		if ( ! empty( $options['auto_import'] ) ) {
			$frequency   = $options['import_frequency'] ?? 'daily';
			$import_time = $options['import_time'] ?? '00:00';

			// Only schedule if not already scheduled.
			if ( ! wp_next_scheduled( 'humanitix_auto_import' ) ) {
				$this->schedule_auto_import( $frequency, $import_time );
			}
		}
	}

	/**
	 * Handle settings updates to manage cron scheduling.
	 *
	 * @since 1.0.0
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $new_value The new option value.
	 * @param string $option The option name.
	 * @return void
	 */
	public function handle_settings_update( $old_value, $new_value, $option ) {
		$old_auto_import = $old_value['auto_import'] ?? false;
		$new_auto_import = $new_value['auto_import'] ?? false;
		$old_frequency   = $old_value['import_frequency'] ?? 'daily';
		$new_frequency   = $new_value['import_frequency'] ?? 'daily';
		$old_import_time = $old_value['import_time'] ?? '00:00';
		$new_import_time = $new_value['import_time'] ?? '00:00';

		// Check if auto import was enabled/disabled or settings changed.
		if ( $old_auto_import !== $new_auto_import || $old_frequency !== $new_frequency || $old_import_time !== $new_import_time ) {
			if ( $new_auto_import ) {
				$this->schedule_auto_import( $new_frequency, $new_import_time );
			} else {
				$this->unschedule_auto_import();
			}
		}
	}

	/**
	 * Schedule the auto import cron job.
	 *
	 * @since 1.0.0
	 * @param string $frequency The import frequency (hourly, daily, weekly).
	 * @param string $import_time The time to run the import (HH:MM format).
	 * @return void
	 */
	private function schedule_auto_import( $frequency, $import_time ) {
		// Clear any existing schedule first.
		$this->unschedule_auto_import();

		// Calculate the next run time based on the specified time.
		$next_run = $this->calculate_next_run_time( $frequency, $import_time );

		// Schedule the event.
		wp_schedule_event( $next_run, $frequency, 'humanitix_auto_import' );

		// Log the scheduling.
		if ( isset( $this->logger ) ) {
			$this->logger->log(
				'info',
				'Auto import scheduled',
				array(
					'frequency'   => $frequency,
					'import_time' => $import_time,
					'next_run'    => date( 'Y-m-d H:i:s', $next_run ),
				)
			);
		}
	}

	/**
	 * Unschedule the auto import cron job.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function unschedule_auto_import() {
		wp_clear_scheduled_hook( 'humanitix_auto_import' );

		// Log the unscheduling.
		if ( isset( $this->logger ) ) {
			$this->logger->log( 'info', 'Auto import unscheduled' );
		}
	}

	/**
	 * Calculate the next run time for the cron job.
	 *
	 * @since 1.0.0
	 * @param string $frequency The import frequency.
	 * @param string $import_time The time to run (HH:MM format).
	 * @return int Unix timestamp for the next run.
	 */
	private function calculate_next_run_time( $frequency, $import_time ) {
		$time_parts = explode( ':', $import_time );
		$hour       = intval( $time_parts[0] );
		$minute     = intval( $time_parts[1] );

		// Get WordPress timezone.
		$timezone = wp_timezone();

		// Get current time in local timezone.
		$now = new \DateTime( 'now', $timezone );

		// Create a DateTime object for today at the specified time in WordPress timezone.
		$next_run = new \DateTime( 'today ' . $import_time, $timezone );

		// If the time has already passed today, schedule for tomorrow.
		if ( $next_run <= $now ) {
			$next_run = new \DateTime( 'tomorrow ' . $import_time, $timezone );
		}

		// For weekly frequency, adjust to the next occurrence.
		if ( 'weekly' === $frequency ) {
			$next_run = new \DateTime( 'next ' . $next_run->format( 'l' ) . ' ' . $import_time, $timezone );
		}

		// For hourly frequency, calculate the next hour.
		if ( 'hourly' === $frequency ) {
			$next_run = new \DateTime( 'now', $timezone );
			$next_run->setTime( $hour, $minute, 0 );

			// If the time has passed this hour, go to next hour.
			if ( $next_run <= $now ) {
				$next_run->modify( '+1 hour' );
			}
		}

		// Convert to UTC for WordPress cron (WordPress cron uses UTC).
		$next_run->setTimezone( new \DateTimeZone( 'UTC' ) );

		return $next_run->getTimestamp();
	}

	/**
	 * Run the auto import process.
	 *
	 * This method is called by the WordPress cron system.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run_auto_import() {
		$start_time = microtime( true );

		// Log the start of scheduled auto import.
		if ( isset( $this->logger ) ) {
			$this->logger->log(
				'info',
				'Scheduled auto import started',
				array(
					'category'       => 'Scheduled',
					'timestamp'      => current_time( 'mysql' ),
					'next_scheduled' => wp_next_scheduled( 'humanitix_auto_import' ),
				)
			);
		}

		if ( ! isset( $this->importer ) ) {
			if ( isset( $this->logger ) ) {
				$this->logger->log(
					'error',
					'Scheduled auto import failed: Importer not available',
					array( 'category' => 'Scheduled' )
				);
			}
			return;
		}

		try {
			// Run the import.
			$result   = $this->importer->import_events();
			$end_time = microtime( true );
			$duration = round( $end_time - $start_time, 2 );

			// Prepare detailed logging information.
			$imported_count  = $result['imported'] ?? 0;
			$updated_count   = $result['updated'] ?? 0;
			$existing_count  = $result['existing'] ?? 0;
			$error_count     = count( $result['errors'] ?? array() );
			$total_processed = $imported_count + $updated_count + $existing_count;

			// Determine the appropriate log level and message.
			if ( $result['success'] ) {
				if ( 0 === $total_processed ) {
					// No events found or processed.
					$log_level   = 'info';
					$log_message = 'Scheduled auto import completed successfully - No events found to import';
					$log_context = array(
						'category'         => 'Scheduled',
						'duration'         => $duration,
						'events_processed' => 0,
						'status'           => 'no_events',
					);
				} else {
					// Events were processed successfully.
					$log_level   = 'info';
					$log_message = sprintf(
						'Scheduled auto import completed successfully - Processed %d events (%d new, %d updated, %d existing)',
						$total_processed,
						$imported_count,
						$updated_count,
						$existing_count
					);
					$log_context = array(
						'category'         => 'Scheduled',
						'duration'         => $duration,
						'events_processed' => $total_processed,
						'events_imported'  => $imported_count,
						'events_updated'   => $updated_count,
						'events_existing'  => $existing_count,
						'errors'           => $result['errors'] ?? array(),
						'status'           => 'success',
					);
				}
			} else {
				// Import failed.
				$log_level   = 'error';
				$log_message = sprintf(
					'Scheduled auto import failed - %s',
					$result['message'] ?? 'Unknown error'
				);
				$log_context = array(
					'category' => 'Scheduled',
					'duration' => $duration,
					'errors'   => $result['errors'] ?? array(),
					'status'   => 'failed',
				);
			}

			// Log the detailed results.
			if ( isset( $this->logger ) ) {
				$this->logger->log( $log_level, $log_message, $log_context );
			}
		} catch ( \Exception $e ) {
			$end_time = microtime( true );
			$duration = round( $end_time - $start_time, 2 );

			// Log any exceptions with detailed information.
			if ( isset( $this->logger ) ) {
				$this->logger->log(
					'error',
					sprintf( 'Scheduled auto import exception: %s', $e->getMessage() ),
					array(
						'category'  => 'Scheduled',
						'duration'  => $duration,
						'exception' => $e->getMessage(),
						'file'      => $e->getFile(),
						'line'      => $e->getLine(),
						'trace'     => $e->getTraceAsString(),
						'status'    => 'exception',
					)
				);
			}
		}
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
		// if (version_compare($from_version, '1.1.0', '<')) {.
		// Add new columns, etc.
		// }.
	}
}
