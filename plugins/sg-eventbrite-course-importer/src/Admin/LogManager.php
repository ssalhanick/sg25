<?php
/**
 * Log Manager Class.
 *
 * Handles log cleanup, rotation, and management for the plugin.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Log Manager Class.
 *
 * Handles log cleanup, rotation, and management for the plugin.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class LogManager {

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * Clean up old logs based on age and size.
	 *
	 * @param int $days_to_keep Number of days to keep logs (default: 30).
	 * @param int $max_logs Maximum number of logs to keep (default: 10000).
	 * @return array Cleanup results.
	 */
	public function cleanup_old_logs( $days_to_keep = 30, $max_logs = 10000 ) {
		global $wpdb;

		$results = array(
			'deleted_by_age' => 0,
			'deleted_by_count' => 0,
			'total_deleted' => 0,
			'errors' => array(),
		);

		try {
			// Delete logs older than specified days
			$deleted_by_age = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}humanitix_import_logs 
					WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
					$days_to_keep
				)
			);

			if ( false !== $deleted_by_age ) {
				$results['deleted_by_age'] = $deleted_by_age;
			} else {
				$results['errors'][] = 'Failed to delete logs by age: ' . $wpdb->last_error;
			}

			// Get total count after age cleanup
			$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}humanitix_import_logs" );

			// If still too many logs, delete oldest ones
			if ( $total_count > $max_logs ) {
				$logs_to_delete = $total_count - $max_logs;
				
				$deleted_by_count = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}humanitix_import_logs 
						WHERE id IN (
							SELECT id FROM (
								SELECT id FROM {$wpdb->prefix}humanitix_import_logs 
								ORDER BY created_at ASC 
								LIMIT %d
							) as temp
						)",
						$logs_to_delete
					)
				);

				if ( false !== $deleted_by_count ) {
					$results['deleted_by_count'] = $deleted_by_count;
				} else {
					$results['errors'][] = 'Failed to delete logs by count: ' . $wpdb->last_error;
				}
			}

			$results['total_deleted'] = $results['deleted_by_age'] + $results['deleted_by_count'];

			// Log the cleanup operation
			$this->logger->log(
				'info',
				'Log cleanup completed',
				array(
					'deleted_by_age' => $results['deleted_by_age'],
					'deleted_by_count' => $results['deleted_by_count'],
					'total_deleted' => $results['total_deleted'],
					'errors' => $results['errors'],
				)
			);

		} catch ( \Exception $e ) {
			$results['errors'][] = 'Exception during cleanup: ' . $e->getMessage();
		}

		return $results;
	}

	/**
	 * Compress large context entries to save space.
	 *
	 * @param int $min_size Minimum size in bytes to compress (default: 1000).
	 * @return array Compression results.
	 */
	public function compress_large_contexts( $min_size = 1000 ) {
		global $wpdb;

		$results = array(
			'compressed' => 0,
			'space_saved' => 0,
			'errors' => array(),
		);

		try {
			// Get logs with large context
			$large_contexts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, context FROM {$wpdb->prefix}humanitix_import_logs 
					WHERE LENGTH(context) > %d AND context IS NOT NULL",
					$min_size
				)
			);

			foreach ( $large_contexts as $log ) {
				$original_size = strlen( $log->context );
				$compressed = gzcompress( $log->context, 6 ); // Level 6 compression
				
				if ( $compressed !== false ) {
					$compressed_size = strlen( $compressed );
					$space_saved = $original_size - $compressed_size;
					
					// Only update if compression actually saves space
					if ( $space_saved > 0 ) {
						$updated = $wpdb->update(
							$wpdb->prefix . 'humanitix_import_logs',
							array( 'context' => $compressed ),
							array( 'id' => $log->id ),
							array( '%s' ),
							array( '%d' )
						);

						if ( $updated !== false ) {
							$results['compressed']++;
							$results['space_saved'] += $space_saved;
						} else {
							$results['errors'][] = "Failed to update log ID {$log->id}: " . $wpdb->last_error;
						}
					}
				}
			}

			// Log the compression operation
			$this->logger->log(
				'info',
				'Context compression completed',
				array(
					'compressed' => $results['compressed'],
					'space_saved' => $results['space_saved'],
					'errors' => $results['errors'],
				)
			);

		} catch ( \Exception $e ) {
			$results['errors'][] = 'Exception during compression: ' . $e->getMessage();
		}

		return $results;
	}

	/**
	 * Get log statistics for monitoring.
	 *
	 * @return array Log statistics.
	 */
	public function get_log_statistics() {
		global $wpdb;

		$stats = array();

		try {
			// Basic counts
			$stats['total_logs'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}humanitix_import_logs" );
			$stats['total_size'] = $wpdb->get_var( "SELECT SUM(LENGTH(message) + LENGTH(context)) FROM {$wpdb->prefix}humanitix_import_logs" );
			
			// Level distribution
			$level_distribution = $wpdb->get_results( "
				SELECT level, COUNT(*) as count 
				FROM {$wpdb->prefix}humanitix_import_logs 
				GROUP BY level 
				ORDER BY count DESC
			" );
			$stats['level_distribution'] = $level_distribution;

			// Size analysis
			$stats['large_contexts'] = $wpdb->get_var( "
				SELECT COUNT(*) FROM {$wpdb->prefix}humanitix_import_logs 
				WHERE LENGTH(context) > 1000
			" );

			$stats['avg_message_length'] = $wpdb->get_var( "
				SELECT AVG(LENGTH(message)) FROM {$wpdb->prefix}humanitix_import_logs
			" );

			$stats['avg_context_length'] = $wpdb->get_var( "
				SELECT AVG(LENGTH(context)) FROM {$wpdb->prefix}humanitix_import_logs
			" );

			// Recent activity
			$stats['logs_today'] = $wpdb->get_var( "
				SELECT COUNT(*) FROM {$wpdb->prefix}humanitix_import_logs 
				WHERE DATE(created_at) = CURDATE()
			" );

			$stats['logs_this_week'] = $wpdb->get_var( "
				SELECT COUNT(*) FROM {$wpdb->prefix}humanitix_import_logs 
				WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
			" );

		} catch ( \Exception $e ) {
			$stats['error'] = $e->getMessage();
		}

		return $stats;
	}

	/**
	 * Export logs to file for backup.
	 *
	 * @param string $format Export format (json, csv, txt).
	 * @param int $limit Maximum number of logs to export.
	 * @return array Export results.
	 */
	public function export_logs( $format = 'json', $limit = 1000 ) {
		global $wpdb;

		$results = array(
			'success' => false,
			'file_path' => '',
			'exported_count' => 0,
			'error' => '',
		);

		try {
			// Get logs for export
			$logs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}humanitix_import_logs 
					ORDER BY created_at DESC 
					LIMIT %d",
					$limit
				)
			);

			if ( empty( $logs ) ) {
				$results['error'] = 'No logs found to export';
				return $results;
			}

			// Create export directory
			$upload_dir = wp_upload_dir();
			$export_dir = $upload_dir['basedir'] . '/humanitix-logs';
			
			if ( ! file_exists( $export_dir ) ) {
				wp_mkdir_p( $export_dir );
			}

			// Generate filename
			$timestamp = current_time( 'Y-m-d_H-i-s' );
			$filename = "humanitix-logs-{$timestamp}.{$format}";
			$file_path = $export_dir . '/' . $filename;

			// Export based on format
			switch ( $format ) {
				case 'json':
					$content = wp_json_encode( $logs, JSON_PRETTY_PRINT );
					break;
					
				case 'csv':
					$content = $this->convert_to_csv( $logs );
					break;
					
				case 'txt':
					$content = $this->convert_to_text( $logs );
					break;
					
				default:
					$results['error'] = 'Unsupported export format: ' . $format;
					return $results;
			}

			// Write file
			$written = file_put_contents( $file_path, $content );
			
			if ( $written !== false ) {
				$results['success'] = true;
				$results['file_path'] = $file_path;
				$results['exported_count'] = count( $logs );
				$results['file_size'] = $written;
			} else {
				$results['error'] = 'Failed to write export file';
			}

		} catch ( \Exception $e ) {
			$results['error'] = 'Export failed: ' . $e->getMessage();
		}

		return $results;
	}

	/**
	 * Convert logs to CSV format.
	 *
	 * @param array $logs Logs to convert.
	 * @return string CSV content.
	 */
	private function convert_to_csv( $logs ) {
		$output = fopen( 'php://temp', 'r+' );
		
		// Write headers
		fputcsv( $output, array( 'ID', 'Level', 'Message', 'Context', 'Created At' ) );
		
		// Write data
		foreach ( $logs as $log ) {
			fputcsv( $output, array(
				$log->id,
				$log->level,
				$log->message,
				$log->context,
				$log->created_at,
			) );
		}
		
		rewind( $output );
		$content = stream_get_contents( $output );
		fclose( $output );
		
		return $content;
	}

	/**
	 * Convert logs to text format.
	 *
	 * @param array $logs Logs to convert.
	 * @return string Text content.
	 */
	private function convert_to_text( $logs ) {
		$content = "Humanitix Import Logs Export\n";
		$content .= "Generated: " . current_time( 'Y-m-d H:i:s' ) . "\n";
		$content .= "Total Logs: " . count( $logs ) . "\n\n";
		
		foreach ( $logs as $log ) {
			$content .= "[{$log->created_at}] {$log->level}: {$log->message}\n";
			if ( ! empty( $log->context ) ) {
				$content .= "Context: " . $log->context . "\n";
			}
			$content .= "\n";
		}
		
		return $content;
	}

	/**
	 * Schedule automatic log cleanup.
	 */
	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'humanitix_log_cleanup' ) ) {
			wp_schedule_event( time(), 'weekly', 'humanitix_log_cleanup' );
		}
	}

	/**
	 * Clear scheduled cleanup.
	 */
	public function clear_cleanup_schedule() {
		wp_clear_scheduled_hook( 'humanitix_log_cleanup' );
	}
} 