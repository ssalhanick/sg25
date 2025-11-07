<?php
/**
 * Plugin Class.
 *
 * @package SG\EventbriteCourseImporter
 */

namespace SG\EventbriteCourseImporter;

use SG\EventbriteCourseImporter\Security\AjaxSecurityHandler;
use SG\EventbriteCourseImporter\Security\RestApiSecurityHandler;
use SG\EventbriteCourseImporter\CustomPostType;
use SG\EventbriteCourseImporter\EventbriteAPI;
use SG\EventbriteCourseImporter\Admin\ImportInterface;
use SG\EventbriteCourseImporter\Admin\SettingsManager;
use SG\EventbriteCourseImporter\Admin\ACFFieldManager;
use SG\EventbriteCourseImporter\Admin\Logger;
use SG\EventbriteCourseImporter\Templates\EventbriteTemplateManager;
use SG\EventbriteCourseImporter\PublishPressFutureIntegration;

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
		error_log( '[sg-eventbrite-course-importer] Plugin activated' );
	}

	/**
	 * Deactivate Function
	 */
	public function deactivate() {
		error_log( '[sg-eventbrite-course-importer] Plugin deactivated' );
	}

	/**
	 * Initialize plugin
	 */
	private function init() {
		// Constants are already defined in the main plugin file
		// Just define the build-specific constants if not already defined
		if ( ! defined( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_BUILD_PATH' ) ) {
			define( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_BUILD_PATH', SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/assets/build' );
		}
		if ( ! defined( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_BUILD_URL' ) ) {
			define( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_BUILD_URL', SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL . '/assets/build' );
		}

		new Assets();
		new Patterns();
		new BlockManager();
		new CustomPostType();
		new ImportInterface();
		new SettingsManager();
		new ACFFieldManager();
		
		// Initialize PublishPress Future integration
		new PublishPressFutureIntegration();
		
		// Initialize EventbriteTemplateManager for course templates
		$logger = new Logger();
		new EventbriteTemplateManager( $logger );

		// Schedule cron job for early bird expiry check
		$this->schedule_early_bird_cron();

		// Initialize security utilities.
		$this->init_security_utilities();
	}

		/**
	 * Initialize security utilities.
	 */
	private function init_security_utilities() {
		// Example AJAX action registration with secure handler.
		AjaxSecurityHandler::register_action(
			'sg-eventbrite-course-importer_action',
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
			'sg-eventbrite-course-importer/v1',
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
	 * Schedule cron job for early bird expiry check.
	 */
	private function schedule_early_bird_cron() {
		if ( ! wp_next_scheduled( 'sg_eventbrite_check_early_bird_expiry' ) ) {
			wp_schedule_event( time(), 'daily', 'sg_eventbrite_check_early_bird_expiry' );
		}
		add_action( 'sg_eventbrite_check_early_bird_expiry', array( $this, 'check_early_bird_expiry' ) );
	}

	/**
	 * Check for expired early bird tickets and update prices.
	 */
	public function check_early_bird_expiry() {
		$args = array(
			'post_type'      => 'sg_course',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_sg_course_early_bird_expires',
					'compare' => 'EXISTS',
				),
			),
		);

		$courses = get_posts( $args );

		foreach ( $courses as $course ) {
			$expires = get_post_meta( $course->ID, '_sg_course_early_bird_expires', true );
			if ( empty( $expires ) ) {
				continue;
			}

			$expires_time = strtotime( $expires );
			$now = time();

			if ( $now >= $expires_time ) {
				// Early bird has expired, update to regular price
				$regular_price = get_post_meta( $course->ID, '_sg_course_regular_price', true );
				if ( ! empty( $regular_price ) ) {
					$currency = get_post_meta( $course->ID, '_sg_course_price', true );
					// Extract currency from display price if available
					if ( preg_match( '/^([A-Z]{3})\s/', $currency, $matches ) ) {
						$currency_code = $matches[1];
						update_post_meta( $course->ID, '_sg_course_price', $currency_code . ' ' . number_format( $regular_price, 2 ) );
					} else {
						update_post_meta( $course->ID, '_sg_course_price', number_format( $regular_price, 2 ) );
					}
					// Clear early bird meta
					delete_post_meta( $course->ID, '_sg_course_early_bird_expires' );
					delete_post_meta( $course->ID, '_sg_course_early_bird_price' );
				}
			}
		}
	}

	/**
	 * Init Hooks
	 */
	public function init_hooks(): void {
		add_action(
			'admin_footer',
			function () {
				// This is safe because we're outputting a static string, but for best practices.
				echo '<script>console.log("sg-eventbrite-course-importer:", window.sgEventbriteCourseImporter);</script>';
			}
		);
	}
} 