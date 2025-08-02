<?php
/**
 * Archive Manager Class.
 *
 * Handles the archiving of TEC events that are older than a specified threshold.
 * Manages archive operations, custom post status, and archive validation.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Archive;

use SG\HumanitixApiImporter\Admin\Logger;
use SG\HumanitixApiImporter\Admin\ErrorCode;
use SG\HumanitixApiImporter\Archive\ArchiveQueries;
use SG\HumanitixApiImporter\Archive\ArchiveValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Archive Manager Class.
 *
 * Handles the archiving of TEC events that are older than a specified threshold.
 * Manages archive operations, custom post status, and archive validation.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */
class ArchiveManager {

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * The archive queries instance.
	 *
	 * @var ArchiveQueries
	 */
	private $queries;

	/**
	 * The archive validator instance.
	 *
	 * @var ArchiveValidator
	 */
	private $validator;

	/**
	 * Archive settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * Initializes the archive manager and registers custom post status.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = new Logger();
		$this->queries = new ArchiveQueries();
		$this->validator = new ArchiveValidator();
		$this->settings = $this->get_archive_settings();
		
		// Register custom post status
		add_action( 'init', array( $this, 'register_archive_post_status' ) );
		
		// Add archive status to admin filters
		add_action( 'admin_footer-post.php', array( $this, 'add_archive_status_to_dropdown' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'add_archive_status_to_dropdown' ) );
	}

	/**
	 * Register the 'archived' post status.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_archive_post_status() {
		register_post_status(
			'archived',
			array(
				'label'                     => _x( 'Archived', 'post status', 'sg-humanitix-api-importer' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(
					'Archived <span class="count">(%s)</span>',
					'Archived <span class="count">(%s)</span>',
					'sg-humanitix-api-importer'
				),
			)
		);
	}

	/**
	 * Add archive status to post status dropdown.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_archive_status_to_dropdown() {
		global $post;
		
		if ( $post && 'tribe_events' === $post->post_type ) {
			?>
			<script>
			jQuery(document).ready(function($) {
				$('#post_status').append('<option value="archived"><?php esc_html_e( 'Archived', 'sg-humanitix-api-importer' ); ?></option>');
			});
			</script>
			<?php
		}
	}

	/**
	 * Get archive settings.
	 *
	 * @since 1.0.0
	 * @return array Archive settings.
	 */
	private function get_archive_settings() {
		$defaults = array(
			'archive_enabled'        => false,
			'archive_age_threshold'  => 2, // years
			'archive_frequency'      => 'monthly',
			'archive_post_status'    => 'archived',
			'archive_batch_size'     => 50,
			'archive_notifications'  => true,
			'archive_dry_run'        => false,
		);

		$options = get_option( 'humanitix_importer_options', array() );
		
		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Archive a single event.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to archive.
	 * @return array Archive result with success status and message.
	 */
	public function archive_event( $event_id ) {
		$validation = $this->validator->validate_archive_operation( $event_id );
		if ( ! $validation['success'] ) {
			return array(
				'success' => false,
				'message' => $validation['message'],
			);
		}

		$event = get_post( $event_id );
		if ( ! $event || 'tribe_events' !== $event->post_type ) {
			return array(
				'success' => false,
				'message' => 'Invalid event ID',
			);
		}

		// Check if already archived
		if ( 'archived' === $event->post_status ) {
			return array(
				'success' => false,
				'message' => 'Event is already archived',
			);
		}

		// Create backup before archiving
		$backup_created = $this->create_event_backup( $event_id );
		if ( ! $backup_created ) {
			return array(
				'success' => false,
				'message' => 'Failed to create backup',
			);
		}

		// Archive the event
		$update_result = wp_update_post( array(
			'ID'          => $event_id,
			'post_status' => $this->settings['archive_post_status'],
		), true );

		if ( is_wp_error( $update_result ) ) {
			$this->logger->log(
				'error',
				'Failed to archive event',
				array(
					'event_id' => $event_id,
					'error'    => $update_result->get_error_message(),
				)
			);

			return array(
				'success' => false,
				'message' => 'Failed to update event status: ' . $update_result->get_error_message(),
			);
		}

		// Add archive metadata
		update_post_meta( $event_id, '_event_archived_date', current_time( 'mysql' ) );
		update_post_meta( $event_id, '_event_archived_by', 'system' );

		$this->logger->log(
			'info',
			'Event archived successfully',
			array(
				'event_id'    => $event_id,
				'event_title' => $event->post_title,
				'archive_date' => current_time( 'mysql' ),
			)
		);

		return array(
			'success' => true,
			'message' => 'Event archived successfully',
			'event_id' => $event_id,
		);
	}

	/**
	 * Unarchive a single event.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to unarchive.
	 * @return array Unarchive result with success status and message.
	 */
	public function unarchive_event( $event_id ) {
		$event = get_post( $event_id );
		if ( ! $event || 'tribe_events' !== $event->post_type ) {
			return array(
				'success' => false,
				'message' => 'Invalid event ID',
			);
		}

		if ( 'archived' !== $event->post_status ) {
			return array(
				'success' => false,
				'message' => 'Event is not archived',
			);
		}

		// Restore the event
		$update_result = wp_update_post( array(
			'ID'          => $event_id,
			'post_status' => 'publish',
		), true );

		if ( is_wp_error( $update_result ) ) {
			$this->logger->log(
				'error',
				'Failed to unarchive event',
				array(
					'event_id' => $event_id,
					'error'    => $update_result->get_error_message(),
				)
			);

			return array(
				'success' => false,
				'message' => 'Failed to restore event: ' . $update_result->get_error_message(),
			);
		}

		// Remove archive metadata
		delete_post_meta( $event_id, '_event_archived_date' );
		delete_post_meta( $event_id, '_event_archived_by' );

		$this->logger->log(
			'info',
			'Event unarchived successfully',
			array(
				'event_id'    => $event_id,
				'event_title' => $event->post_title,
				'restore_date' => current_time( 'mysql' ),
			)
		);

		return array(
			'success' => true,
			'message' => 'Event restored successfully',
			'event_id' => $event_id,
		);
	}

	/**
	 * Get events that should be archived based on age threshold.
	 *
	 * @since 1.0.0
	 * @param int $age_threshold Age threshold in years.
	 * @param int $limit Maximum number of events to return.
	 * @return array Array of event IDs to archive.
	 */
	public function get_events_to_archive( $age_threshold = null, $limit = null ) {
		if ( null === $age_threshold ) {
			$age_threshold = $this->settings['archive_age_threshold'];
		}

		if ( null === $limit ) {
			$limit = $this->settings['archive_batch_size'];
		}

		return $this->queries->get_events_older_than( $age_threshold, $limit );
	}

	/**
	 * Process a batch of events for archiving.
	 *
	 * @since 1.0.0
	 * @param array $events Array of event IDs to archive.
	 * @param bool  $dry_run Whether to perform a dry run.
	 * @return array Batch processing results.
	 */
	public function process_archive_batch( $events, $dry_run = false ) {
		$results = array(
			'total'      => count( $events ),
			'successful' => 0,
			'failed'     => 0,
			'errors'     => array(),
		);

		foreach ( $events as $event_id ) {
			if ( $dry_run ) {
				$results['successful']++;
				continue;
			}

			$result = $this->archive_event( $event_id );
			
			if ( $result['success'] ) {
				$results['successful']++;
			} else {
				$results['failed']++;
				$results['errors'][] = array(
					'event_id' => $event_id,
					'error'    => $result['message'],
				);
			}
		}

		$this->logger->log(
			'info',
			'Archive batch processing completed',
			array(
				'total'      => $results['total'],
				'successful' => $results['successful'],
				'failed'     => $results['failed'],
				'dry_run'    => $dry_run,
			)
		);

		return $results;
	}

	/**
	 * Validate archive operation for an event.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to validate.
	 * @return bool Whether the archive operation is valid.
	 */
	public function validate_archive_operation( $event_id ) {
		// Check if event exists
		$event = get_post( $event_id );
		if ( ! $event ) {
			return false;
		}

		// Check if it's a TEC event
		if ( 'tribe_events' !== $event->post_type ) {
			return false;
		}

		// Check if already archived
		if ( 'archived' === $event->post_status ) {
			return false;
		}

		// Check if event has start date
		$start_date = get_post_meta( $event_id, '_EventStartDate', true );
		if ( empty( $start_date ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Create a backup of an event before archiving.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to backup.
	 * @return bool Whether backup was successful.
	 */
	private function create_event_backup( $event_id ) {
		$event = get_post( $event_id );
		if ( ! $event ) {
			return false;
		}

		// Get all event meta
		$meta = get_post_meta( $event_id );
		
		// Create backup data
		$backup_data = array(
			'post_data' => array(
				'ID'           => $event->ID,
				'post_title'   => $event->post_title,
				'post_content' => $event->post_content,
				'post_status'  => $event->post_status,
				'post_date'    => $event->post_date,
				'post_modified' => $event->post_modified,
			),
			'meta_data' => $meta,
			'backup_date' => current_time( 'mysql' ),
		);

		// Store backup in post meta
		$backup_stored = update_post_meta( $event_id, '_event_archive_backup', $backup_data );
		
		if ( $backup_stored ) {
			$this->logger->log(
				'info',
				'Event backup created',
				array(
					'event_id' => $event_id,
					'backup_date' => current_time( 'mysql' ),
				)
			);
		}

		return $backup_stored;
	}

	/**
	 * Get archive statistics.
	 *
	 * @since 1.0.0
	 * @return array Archive statistics.
	 */
	public function get_archive_statistics() {
		return $this->queries->get_archive_statistics();
	}
} 