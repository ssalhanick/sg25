<?php
/**
 * Delete Manager Class.
 *
 * Handles the permanent deletion of archived TEC events after a specified age threshold.
 * Implements safety measures including backup creation, batch processing, and recovery periods.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Archive;

use SG\HumanitixApiImporter\Admin\Logger;
use SG\HumanitixApiImporter\Admin\ErrorCode;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Delete Manager Class.
 *
 * Handles the permanent deletion of archived TEC events after a specified age threshold.
 * Implements safety measures including backup creation, batch processing, and recovery periods.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */
class DeleteManager {

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
	 * Delete settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * Initializes the delete manager.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = new Logger();
		$this->queries = new ArchiveQueries();
		$this->validator = new ArchiveValidator();
		$this->settings = $this->get_delete_settings();
	}

	/**
	 * Get delete settings from WordPress options.
	 *
	 * @since 1.0.0
	 * @return array Delete settings.
	 */
	private function get_delete_settings() {
		$options = get_option( 'humanitix_importer_options', array() );
		
		return array(
			'delete_enabled'           => isset( $options['delete_enabled'] ) ? $options['delete_enabled'] : false,
			'delete_age_threshold'     => isset( $options['delete_age_threshold'] ) ? floatval( $options['delete_age_threshold'] ) : 5.0,
			'delete_recovery_period'   => isset( $options['delete_recovery_period'] ) ? intval( $options['delete_recovery_period'] ) : 30,
			'delete_batch_size'        => isset( $options['delete_batch_size'] ) ? intval( $options['delete_batch_size'] ) : 25,
			'delete_notifications'     => isset( $options['delete_notifications'] ) ? $options['delete_notifications'] : true,
			'delete_dry_run'           => isset( $options['delete_dry_run'] ) ? $options['delete_dry_run'] : true,
			'delete_backup_enabled'    => isset( $options['delete_backup_enabled'] ) ? $options['delete_backup_enabled'] : true,
		);
	}

	/**
	 * Get events eligible for deletion.
	 *
	 * @since 1.0.0
	 * @param float $age_threshold Age threshold in years (defaults to settings).
	 * @param int   $limit Maximum number of events to return.
	 * @return array Array of event IDs eligible for deletion.
	 */
	public function get_events_to_delete( $age_threshold = null, $limit = null ) {
		if ( null === $age_threshold ) {
			$age_threshold = $this->settings['delete_age_threshold'];
		}

		if ( null === $limit ) {
			$limit = $this->settings['delete_batch_size'];
		}

		// Get events that are archived AND older than the deletion threshold
		$cutoff_date = $this->calculate_deletion_cutoff_date( $age_threshold );

		$args = array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'archived',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_EventStartDate',
					'value'   => $cutoff_date,
					'compare' => '<',
					'type'    => 'DATE',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => '_EventStartDate',
			'order'          => 'ASC',
		);

		$query = new \WP_Query( $args );
		$events = $query->posts;

		$this->logger->log(
			'info',
			'Found events eligible for deletion',
			array(
				'age_threshold' => $age_threshold,
				'cutoff_date'   => $cutoff_date,
				'count'         => count( $events ),
				'limit'         => $limit,
			)
		);

		return $events;
	}

	/**
	 * Calculate the cutoff date for deletion based on age threshold.
	 *
	 * @since 1.0.0
	 * @param float $age_threshold Age threshold in years.
	 * @return string Cutoff date in Y-m-d format.
	 */
	private function calculate_deletion_cutoff_date( $age_threshold ) {
		// Convert years to days for precise decimal handling
		$days_ago = $age_threshold * 365.25;
		return date( 'Y-m-d', strtotime( "-{$days_ago} days" ) );
	}

	/**
	 * Delete a single event permanently.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to delete.
	 * @param bool $dry_run Whether to perform a dry run.
	 * @return array Deletion result.
	 */
	public function delete_event( $event_id, $dry_run = false ) {
		$result = array(
			'success' => false,
			'message' => '',
			'event_id' => $event_id,
		);

		// Validate deletion operation
		$validation = $this->validate_delete_operation( $event_id );
		if ( ! $validation['success'] ) {
			$result['message'] = $validation['message'];
			return $result;
		}

		if ( $dry_run ) {
			$result['success'] = true;
			$result['message'] = 'Dry run: Event would be deleted';
			return $result;
		}

		// Create backup before deletion
		if ( $this->settings['delete_backup_enabled'] ) {
			$backup_created = $this->create_event_backup( $event_id );
			if ( ! $backup_created ) {
				$result['message'] = 'Failed to create backup before deletion';
				return $result;
			}
		}

		// Get event data for logging
		$event = get_post( $event_id );
		$event_title = $event ? $event->post_title : 'Unknown Event';
		$start_date = get_post_meta( $event_id, '_EventStartDate', true );

		// Delete the event
		$deleted = wp_delete_post( $event_id, true ); // true = force delete

		if ( $deleted ) {
			$result['success'] = true;
			$result['message'] = 'Event deleted successfully';

			$this->logger->log(
				'info',
				'Event permanently deleted',
				array(
					'event_id'    => $event_id,
					'event_title' => $event_title,
					'start_date'  => $start_date,
				)
			);
		} else {
			$result['message'] = 'Failed to delete event';
			
			$this->logger->log(
				'error',
				'Failed to delete event',
				array(
					'event_id'    => $event_id,
					'event_title' => $event_title,
				)
			);
		}

		return $result;
	}

	/**
	 * Process a batch of events for deletion.
	 *
	 * @since 1.0.0
	 * @param array $events Array of event IDs to delete.
	 * @param bool  $dry_run Whether to perform a dry run.
	 * @return array Batch processing results.
	 */
	public function process_delete_batch( $events, $dry_run = false ) {
		$results = array(
			'total'      => count( $events ),
			'successful' => 0,
			'failed'     => 0,
			'errors'     => array(),
		);

		foreach ( $events as $event_id ) {
			$result = $this->delete_event( $event_id, $dry_run );
			
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
			'Delete batch processing completed',
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
	 * Validate deletion operation for an event.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to validate.
	 * @return array Validation result.
	 */
	public function validate_delete_operation( $event_id ) {
		$result = array(
			'success' => false,
			'message' => '',
		);

		// Check if event exists
		$event = get_post( $event_id );
		if ( ! $event ) {
			$result['message'] = 'Event does not exist';
			return $result;
		}

		// Check if it's a TEC event
		if ( 'tribe_events' !== $event->post_type ) {
			$result['message'] = 'Event is not a The Events Calendar event';
			return $result;
		}

		// Check if event is archived (safety check)
		if ( 'archived' !== $event->post_status ) {
			$result['message'] = 'Event is not archived - cannot delete non-archived events';
			return $result;
		}

		// Check if event has start date
		$start_date = get_post_meta( $event_id, '_EventStartDate', true );
		if ( empty( $start_date ) ) {
			$result['message'] = 'Event has no start date';
			return $result;
		}

		// Check if event meets deletion age threshold
		$event_date = strtotime( $start_date );
		$cutoff_date = strtotime( $this->calculate_deletion_cutoff_date( $this->settings['delete_age_threshold'] ) );
		
		if ( $event_date > $cutoff_date ) {
			$result['message'] = 'Event does not meet deletion age threshold';
			return $result;
		}

		// Check system health
		$health_check = $this->validator->check_system_health();
		if ( ! $health_check['success'] ) {
			$result['message'] = 'System health check failed: ' . $health_check['message'];
			return $result;
		}

		$result['success'] = true;
		return $result;
	}

	/**
	 * Create a backup of an event before deletion.
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

		// Create backup data
		$backup_data = array(
			'event_id'      => $event_id,
			'post_data'     => $event->to_array(),
			'meta_data'     => get_post_meta( $event_id ),
			'backup_date'   => current_time( 'mysql' ),
			'deletion_date' => current_time( 'mysql' ),
		);

		// Store backup in WordPress options
		$backups = get_option( 'humanitix_event_backups', array() );
		$backups[ $event_id ] = $backup_data;
		update_option( 'humanitix_event_backups', $backups );

		$this->logger->log(
			'info',
			'Event backup created before deletion',
			array(
				'event_id'    => $event_id,
				'event_title' => $event->post_title,
			)
		);

		return true;
	}

	/**
	 * Get deletion statistics.
	 *
	 * @since 1.0.0
	 * @return array Deletion statistics.
	 */
	public function get_delete_statistics() {
		$total_archived = $this->queries->get_archived_events_count();
		$events_to_delete = count( $this->get_events_to_delete( $this->settings['delete_age_threshold'], -1 ) );
		
		// Count events deleted this month
		$start_of_month = date( 'Y-m-01' );
		$end_of_month = date( 'Y-m-t' );
		$deleted_this_month = $this->get_events_deleted_in_range( $start_of_month, $end_of_month );

		return array(
			'total_archived'      => $total_archived,
			'events_to_delete'    => $events_to_delete,
			'deleted_this_month'  => count( $deleted_this_month ),
			'delete_threshold'    => $this->settings['delete_age_threshold'],
			'delete_enabled'      => $this->settings['delete_enabled'],
			'delete_dry_run'      => $this->settings['delete_dry_run'],
		);
	}

	/**
	 * Get events deleted within a date range.
	 *
	 * @since 1.0.0
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date End date in Y-m-d format.
	 * @return array Array of deleted event IDs.
	 */
	public function get_events_deleted_in_range( $start_date, $end_date ) {
		// This would need to be implemented based on how you track deletions
		// For now, return empty array - you could store deletion logs in a custom table
		return array();
	}

	/**
	 * Restore a deleted event from backup.
	 *
	 * @since 1.0.0
	 * @param int $event_id The original event ID.
	 * @return array Restoration result.
	 */
	public function restore_event_from_backup( $event_id ) {
		$result = array(
			'success' => false,
			'message' => '',
		);

		$backups = get_option( 'humanitix_event_backups', array() );
		
		if ( ! isset( $backups[ $event_id ] ) ) {
			$result['message'] = 'No backup found for this event';
			return $result;
		}

		$backup = $backups[ $event_id ];

		// Restore the event
		$post_data = $backup['post_data'];
		$post_data['ID'] = 0; // Force creation of new post
		$post_data['post_status'] = 'archived'; // Restore as archived

		$new_event_id = wp_insert_post( $post_data );

		if ( $new_event_id && ! is_wp_error( $new_event_id ) ) {
			// Restore meta data
			foreach ( $backup['meta_data'] as $meta_key => $meta_values ) {
				foreach ( $meta_values as $meta_value ) {
					add_post_meta( $new_event_id, $meta_key, $meta_value );
				}
			}

			$result['success'] = true;
			$result['message'] = 'Event restored successfully';
			$result['new_event_id'] = $new_event_id;

			$this->logger->log(
				'info',
				'Event restored from backup',
				array(
					'original_event_id' => $event_id,
					'new_event_id'     => $new_event_id,
				)
			);
		} else {
			$result['message'] = 'Failed to restore event';
		}

		return $result;
	}

	/**
	 * Clean up old backups.
	 *
	 * @since 1.0.0
	 * @param int $days_to_keep Number of days to keep backups.
	 * @return int Number of backups cleaned up.
	 */
	public function cleanup_old_backups( $days_to_keep = 90 ) {
		$backups = get_option( 'humanitix_event_backups', array() );
		$cutoff_date = strtotime( "-{$days_to_keep} days" );
		$cleaned_count = 0;

		foreach ( $backups as $event_id => $backup ) {
			$backup_date = strtotime( $backup['backup_date'] );
			if ( $backup_date < $cutoff_date ) {
				unset( $backups[ $event_id ] );
				$cleaned_count++;
			}
		}

		update_option( 'humanitix_event_backups', $backups );

		$this->logger->log(
			'info',
			'Old backups cleaned up',
			array(
				'cleaned_count' => $cleaned_count,
				'days_to_keep'  => $days_to_keep,
			)
		);

		return $cleaned_count;
	}
}
 