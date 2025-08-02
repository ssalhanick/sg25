<?php
/**
 * Archive Validator Class.
 *
 * Handles safety checks and validation for archive operations.
 * Ensures data integrity and prevents accidental data loss.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Archive;

use SG\HumanitixApiImporter\Admin\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Archive Validator Class.
 *
 * Handles safety checks and validation for archive operations.
 * Ensures data integrity and prevents accidental data loss.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */
class ArchiveValidator {

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * Validate archive operation for an event.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to validate.
	 * @return array Validation result with success status and message.
	 */
	public function validate_archive_operation( $event_id ) {
		$result = array(
			'success' => false,
			'message' => '',
			'errors'  => array(),
		);

		// Check if event exists
		$event = get_post( $event_id );
		if ( ! $event ) {
			$result['errors'][] = 'Event does not exist';
			$result['message']  = 'Invalid event ID';
			return $result;
		}

		// Check if it's a TEC event
		if ( 'tribe_events' !== $event->post_type ) {
			$result['errors'][] = 'Not a TEC event';
			$result['message']  = 'Event is not a The Events Calendar event';
			return $result;
		}

		// Check if already archived
		if ( 'archived' === $event->post_status ) {
			$result['errors'][] = 'Event is already archived';
			$result['message']  = 'Event is already archived';
			return $result;
		}

		// Check if event has start date
		$start_date = get_post_meta( $event_id, '_EventStartDate', true );
		if ( empty( $start_date ) ) {
			$result['errors'][] = 'Event has no start date';
			$result['message']  = 'Event has no start date';
			return $result;
		}

		// Check if event is in the future
		$event_date = strtotime( $start_date );
		$now        = current_time( 'timestamp' );
		if ( $event_date > $now ) {
			$result['errors'][] = 'Event is in the future';
			$result['message']  = 'Cannot archive future events';
			return $result;
		}

		// Check database connection
		global $wpdb;
		if ( ! $wpdb->check_connection() ) {
			$result['errors'][] = 'Database connection failed';
			$result['message']  = 'Database connection failed';
			return $result;
		}

		// Check memory usage
		$memory_limit = ini_get( 'memory_limit' );
		$memory_usage = memory_get_usage( true );
		$memory_limit_bytes = $this->convert_memory_limit_to_bytes( $memory_limit );
		
		if ( $memory_usage > ( $memory_limit_bytes * 0.8 ) ) {
			$result['errors'][] = 'Memory usage too high';
			$result['message']  = 'Memory usage too high for safe operation';
			return $result;
		}

		// All checks passed
		$result['success'] = true;
		$result['message'] = 'Event validation passed';

		$this->logger->log(
			'info',
			'Archive validation passed',
			array(
				'event_id' => $event_id,
				'event_title' => $event->post_title,
			)
		);

		return $result;
	}

	/**
	 * Validate unarchive operation for an event.
	 *
	 * @since 1.0.0
	 * @param int $event_id The event ID to validate.
	 * @return array Validation result with success status and message.
	 */
	public function validate_unarchive_operation( $event_id ) {
		$result = array(
			'success' => false,
			'message' => '',
			'errors'  => array(),
		);

		// Check if event exists
		$event = get_post( $event_id );
		if ( ! $event ) {
			$result['errors'][] = 'Event does not exist';
			$result['message']  = 'Invalid event ID';
			return $result;
		}

		// Check if it's a TEC event
		if ( 'tribe_events' !== $event->post_type ) {
			$result['errors'][] = 'Not a TEC event';
			$result['message']  = 'Event is not a The Events Calendar event';
			return $result;
		}

		// Check if event is archived
		if ( 'archived' !== $event->post_status ) {
			$result['errors'][] = 'Event is not archived';
			$result['message']  = 'Event is not archived';
			return $result;
		}

		// Check database connection
		global $wpdb;
		if ( ! $wpdb->check_connection() ) {
			$result['errors'][] = 'Database connection failed';
			$result['message']  = 'Database connection failed';
			return $result;
		}

		// All checks passed
		$result['success'] = true;
		$result['message'] = 'Unarchive validation passed';

		$this->logger->log(
			'info',
			'Unarchive validation passed',
			array(
				'event_id' => $event_id,
				'event_title' => $event->post_title,
			)
		);

		return $result;
	}

	/**
	 * Validate batch archive operation.
	 *
	 * @since 1.0.0
	 * @param array $event_ids Array of event IDs to validate.
	 * @return array Validation result with success status and message.
	 */
	public function validate_batch_archive_operation( $event_ids ) {
		$result = array(
			'success' => false,
			'message' => '',
			'errors'  => array(),
			'valid_events' => array(),
			'invalid_events' => array(),
		);

		if ( empty( $event_ids ) ) {
			$result['errors'][] = 'No events provided';
			$result['message']  = 'No events provided for archiving';
			return $result;
		}

		// Check batch size
		$batch_size = count( $event_ids );
		$max_batch_size = 100; // Configurable limit
		
		if ( $batch_size > $max_batch_size ) {
			$result['errors'][] = "Batch size too large ({$batch_size} > {$max_batch_size})";
			$result['message']  = 'Batch size too large for safe operation';
			return $result;
		}

		// Validate each event
		foreach ( $event_ids as $event_id ) {
			$validation = $this->validate_archive_operation( $event_id );
			
			if ( $validation['success'] ) {
				$result['valid_events'][] = $event_id;
			} else {
				$result['invalid_events'][] = array(
					'event_id' => $event_id,
					'error'    => $validation['message'],
				);
			}
		}

		// Check if we have any valid events
		if ( empty( $result['valid_events'] ) ) {
			$result['errors'][] = 'No valid events to archive';
			$result['message']  = 'No valid events to archive';
			return $result;
		}

		// All checks passed
		$result['success'] = true;
		$result['message'] = sprintf(
			'Batch validation passed: %d valid, %d invalid',
			count( $result['valid_events'] ),
			count( $result['invalid_events'] )
		);

		$this->logger->log(
			'info',
			'Batch archive validation completed',
			array(
				'total_events' => count( $event_ids ),
				'valid_events' => count( $result['valid_events'] ),
				'invalid_events' => count( $result['invalid_events'] ),
			)
		);

		return $result;
	}

	/**
	 * Check system health for archive operations.
	 *
	 * @since 1.0.0
	 * @return array Health check result.
	 */
	public function check_system_health() {
		$health = array(
			'success' => true,
			'checks'  => array(),
		);

		// Check database connection
		global $wpdb;
		$db_check = $wpdb->check_connection();
		$health['checks']['database'] = array(
			'status' => $db_check ? 'ok' : 'error',
			'message' => $db_check ? 'Database connection OK' : 'Database connection failed',
		);

		// Check memory usage
		$memory_usage = memory_get_usage( true );
		$memory_limit = ini_get( 'memory_limit' );
		$memory_limit_bytes = $this->convert_memory_limit_to_bytes( $memory_limit );
		$memory_percentage = ( $memory_usage / $memory_limit_bytes ) * 100;
		
		$health['checks']['memory'] = array(
			'status' => $memory_percentage < 80 ? 'ok' : 'warning',
			'message' => sprintf(
				'Memory usage: %s / %s (%.1f%%)',
				$this->format_bytes( $memory_usage ),
				$memory_limit,
				$memory_percentage
			),
		);

		// Check disk space
		$disk_free = disk_free_space( ABSPATH );
		$disk_total = disk_total_space( ABSPATH );
		$disk_percentage = ( ( $disk_total - $disk_free ) / $disk_total ) * 100;
		
		$health['checks']['disk'] = array(
			'status' => $disk_percentage < 90 ? 'ok' : 'warning',
			'message' => sprintf(
				'Disk usage: %.1f%% free',
				100 - $disk_percentage
			),
		);

		// Check if TEC is active
		$tec_active = class_exists( 'Tribe__Events__Main' );
		$health['checks']['tec'] = array(
			'status' => $tec_active ? 'ok' : 'error',
			'message' => $tec_active ? 'The Events Calendar is active' : 'The Events Calendar is not active',
		);

		// Check for any critical errors
		$critical_errors = array_filter( $health['checks'], function( $check ) {
			return $check['status'] === 'error';
		} );

		if ( ! empty( $critical_errors ) ) {
			$health['success'] = false;
		}

		return $health;
	}

	/**
	 * Convert memory limit string to bytes.
	 *
	 * @since 1.0.0
	 * @param string $memory_limit Memory limit string (e.g., '128M').
	 * @return int Memory limit in bytes.
	 */
	private function convert_memory_limit_to_bytes( $memory_limit ) {
		$unit = strtolower( substr( $memory_limit, -1 ) );
		$value = (int) substr( $memory_limit, 0, -1 );

		switch ( $unit ) {
			case 'k':
				return $value * 1024;
			case 'm':
				return $value * 1024 * 1024;
			case 'g':
				return $value * 1024 * 1024 * 1024;
			default:
				return $value;
		}
	}

	/**
	 * Format bytes to human readable format.
	 *
	 * @since 1.0.0
	 * @param int $bytes Bytes to format.
	 * @return string Formatted bytes.
	 */
	private function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$bytes = max( $bytes, 0 );
		$pow = floor( log( $bytes ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );

		$bytes /= pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
} 