<?php
/**
 * Course Expiration Integration
 *
 * Handles automatic expiration of courses using PublishPress Future plugin.
 * Courses will be set to 'draft' status when they expire based on Eventbrite event sales end date.
 *
 * @package SG\EventbriteCourseImporter
 */

namespace SG\EventbriteCourseImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Course Expiration Integration Class
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

		// Check if PublishPress Future is available
		if ( ! class_exists( '\PublishPress\Future\Modules\Expirator\HooksAbstract' ) ) {
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

			// Use PublishPress Future to schedule the expiration with 'draft' status
			// PublishPress Future expects an options array with expireType and newStatus
			$opts = array(
				'expireType'     => 'change-status', // ExpirationActionsAbstract::CHANGE_POST_STATUS
				'newStatus'      => 'draft',
				'category'        => array(),
				'categoryTaxonomy' => '',
			);

			// Use PublishPress Future's action hook with the correct format
			// This hook expects: post_id, timestamp, opts array
			do_action( 'publishpressfuture_schedule_expiration', $post_id, $expiration_timestamp, $opts );

			error_log( "SG Eventbrite: Set post expiration for course {$post_id} to " . $expiration_dt->format( 'Y-m-d H:i:s' ) );

		} catch ( \Exception $e ) {
			error_log( 'SG Eventbrite: Error setting post expiration: ' . $e->getMessage() );
		}
	}

}

