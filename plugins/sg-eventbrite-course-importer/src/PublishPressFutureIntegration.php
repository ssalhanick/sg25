<?php
/**
 * PublishPress Future Integration
 *
 * Handles integration with PublishPress Future plugin to set post expiration
 * based on Eventbrite event sales end date.
 *
 * @package SG\EventbriteCourseImporter
 */

namespace SG\EventbriteCourseImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * PublishPress Future Integration Class
 */
class PublishPressFutureIntegration {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize hooks
	 */
	private function init() {
		// Hook into post save/update to set expiration
		add_action( 'save_post_sg_course', array( $this, 'set_post_expiration' ), 20, 2 );
		add_action( 'wp_insert_post', array( $this, 'set_post_expiration' ), 20, 2 );
	}

	/**
	 * Set post expiration based on Eventbrite sales end date
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function set_post_expiration( $post_id, $post ) {
		// Only process sg_course post type
		if ( 'sg_course' !== $post->post_type ) {
			return;
		}

		// Check if PublishPress Future is active
		if ( ! function_exists( 'postexpirator_schedule_event' ) && ! class_exists( '\PublishPress\Future\Core\HooksAbstract' ) ) {
			return;
		}

		// Get the overall sales end date from meta
		$sales_end_overall = get_post_meta( $post_id, '_sg_course_sales_end_overall', true );

		// Fallback to ticket expiration if overall sales end is not available
		if ( empty( $sales_end_overall ) ) {
			$sales_end_overall = get_post_meta( $post_id, '_sg_course_ticket_expiration', true );
		}

		// Fallback to event end date if still not available
		if ( empty( $sales_end_overall ) ) {
			$sales_end_overall = get_post_meta( $post_id, '_sg_course_end_datetime', true );
		}

		if ( empty( $sales_end_overall ) ) {
			return;
		}

		// Parse the datetime string
		try {
			// Handle different datetime formats
			$expiration_dt = null;
			
			if ( is_numeric( $sales_end_overall ) ) {
				// Unix timestamp
				$expiration_dt = new \DateTime( '@' . $sales_end_overall );
			} else {
				// Try parsing as ISO format first
				$expiration_dt = new \DateTime( $sales_end_overall, new \DateTimeZone( 'UTC' ) );
			}

			// Convert to site timezone
			$expiration_dt->setTimezone( wp_timezone() );
			$expiration_timestamp = $expiration_dt->getTimestamp();

			// Check if expiration is in the future
			if ( $expiration_timestamp <= time() ) {
				return; // Don't set expiration for past dates
			}

			// Set expiration using PublishPress Future
			// Method 1: Using the action hook (if available)
			if ( has_action( 'publishpressfuture_expire_post' ) ) {
				do_action( 'publishpressfuture_expire_post', $post_id, $expiration_timestamp );
			}

			// Method 2: Direct meta update (PublishPress Future uses these meta keys)
			update_post_meta( $post_id, '_expiration-date', $expiration_timestamp );
			update_post_meta( $post_id, '_expiration-date-status', 'saved' );

			// Schedule the expiration event
			if ( function_exists( 'postexpirator_schedule_event' ) ) {
				postexpirator_schedule_event( $post_id, $expiration_timestamp );
			} elseif ( class_exists( '\PublishPress\Future\Core\HooksAbstract' ) ) {
				// Use PublishPress Future v3+ API
				$hooks = \PublishPress\Future\Core\HooksAbstract::getInstance();
				if ( method_exists( $hooks, 'actionSchedule' ) ) {
					$hooks->actionSchedule( $post_id, $expiration_timestamp );
				}
			}

			error_log( "SG Eventbrite: Set post expiration for course {$post_id} to " . $expiration_dt->format( 'Y-m-d H:i:s' ) );

		} catch ( \Exception $e ) {
			error_log( 'SG Eventbrite: Error setting post expiration: ' . $e->getMessage() );
		}
	}
}

