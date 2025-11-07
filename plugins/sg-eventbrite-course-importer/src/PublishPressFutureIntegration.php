<?php
/**
 * Course Expiration Integration
 *
 * Handles automatic change of post status to "archived" when courses expire
 * based on Eventbrite event sales end date. Uses WordPress cron for scheduling.
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
		// Register custom "archived" post status
		add_action( 'init', array( $this, 'register_archived_post_status' ), 10 );
		
		// Add archived status to admin UI
		add_action( 'admin_footer-post.php', array( $this, 'add_archived_status_to_dropdown' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'add_archived_status_to_dropdown' ) );
		
		// Hook into post save/update to set expiration
		add_action( 'save_post_sg_course', array( $this, 'set_post_expiration' ), 20, 2 );
		add_action( 'wp_insert_post', array( $this, 'set_post_expiration' ), 20, 2 );
		
		// Hook into our custom cron event to change post status to archived
		add_action( 'sg_eventbrite_archive_course', array( $this, 'archive_course' ), 10, 1 );
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

			// We want to change the post status to "archived" when it expires
			// Store the expiration timestamp in meta for reference
			update_post_meta( $post_id, '_sg_course_expiration_timestamp', $expiration_timestamp );
			
			// Schedule a custom WordPress cron event to change post status to archived
			$hook_name = 'sg_eventbrite_archive_course';
			$args = array( $post_id );
			
			// Clear any existing scheduled event for this post
			wp_clear_scheduled_hook( $hook_name, $args );
			
			// Schedule the new event
			$scheduled = wp_schedule_single_event( $expiration_timestamp, $hook_name, $args );
			
			if ( false === $scheduled ) {
				error_log( "SG Eventbrite: Failed to schedule course archiving for course {$post_id}" );
			} else {
				error_log( "SG Eventbrite: Scheduled course archiving for course {$post_id} at " . $expiration_dt->format( 'Y-m-d H:i:s' ) );
			}

			error_log( "SG Eventbrite: Set post expiration for course {$post_id} to " . $expiration_dt->format( 'Y-m-d H:i:s' ) );

		} catch ( \Exception $e ) {
			error_log( 'SG Eventbrite: Error setting post expiration: ' . $e->getMessage() );
		}
	}

	/**
	 * Register custom "archived" post status.
	 */
	public function register_archived_post_status() {
		register_post_status(
			'archived',
			array(
				'label'                     => _x( 'Archived', 'post status', 'sg-eventbrite-course-importer' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'sg-eventbrite-course-importer' ),
			)
		);
	}

	/**
	 * Add "archived" status to the post status dropdown in admin.
	 */
	public function add_archived_status_to_dropdown() {
		global $post;
		
		if ( ! $post || 'sg_course' !== $post->post_type ) {
			return;
		}
		
		$selected = '';
		$label = '';
		
		if ( 'archived' === $post->post_status ) {
			$selected = ' selected="selected"';
			$label = 'Archived';
		}
		
		?>
		<script>
		jQuery(document).ready(function($) {
			$('select#post_status').append('<option value="archived"<?php echo $selected; ?>><?php echo esc_js( __( 'Archived', 'sg-eventbrite-course-importer' ) ); ?></option>');
		});
		</script>
		<?php
	}

	/**
	 * Change post status to "archived" when course expires.
	 * This is called by WordPress cron when a course expiration time is reached.
	 *
	 * @param int $post_id Post ID that is expiring.
	 */
	public function archive_course( $post_id ) {
		// Only process sg_course post type
		$post = get_post( $post_id );
		if ( ! $post || 'sg_course' !== $post->post_type ) {
			return;
		}

		// Don't archive if already archived
		if ( 'archived' === $post->post_status ) {
			return;
		}

		// Change post status to archived
		$updated = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'archived',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			error_log( "SG Eventbrite: Failed to archive course {$post_id}: " . $updated->get_error_message() );
		} else {
			error_log( "SG Eventbrite: Changed course {$post_id} status to 'archived'" );
		}
	}
}

