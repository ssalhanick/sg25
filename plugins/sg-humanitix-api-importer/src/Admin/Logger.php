<?php
/**
 * Admin Logger Class.
 *
 * Handles the logging and debugging logic for the plugin.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

/**
 * Admin Logger Class.
 *
 * Handles the logging and debugging logic for the plugin.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class Logger {

	/**
	 * The table name.
	 *
	 * @var Logger
	 */
	private $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'humanitix_import_logs';

		// Add table name to $wpdb object for WPCS compatibility.
		$wpdb->humanitix_import_logs = $this->table_name;
	}

	/**
	 * Log a message with context
	 *
	 * @param string $level The level of the message log message.
	 * @param string $message The content of the log message.
	 * @param array  $context Optional - Additional structured data.
	 */
	public function log( $level, $message, $context = array() ) {
		global $wpdb;

		// Sanitize level.
		$level = sanitize_text_field( $level );

		// Sanitize message.
		$message = sanitize_textarea_field( $message );

		// Prepare context for storage.
		$context_json = ! empty( $context ) ? wp_json_encode( $context ) : null;

		$result = $wpdb->insert(
			$wpdb->humanitix_import_logs,
			array(
				'level'      => $level,
				'message'    => $message,
				'context'    => $context_json,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			// Use WordPress logging instead of error_log.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Humanitix Importer: Failed to insert log entry: ' . $wpdb->last_error );
			}
		}

		return $result;
	}

	/**
	 * Get logs with filtering
	 *
	 * @param string $level The urgency of the message (error, warning, urgent).
	 * @param string $date The datetime that the log was triggerd.
	 * @param int    $limit Default 100.
	 * @param int    $offset Default 0.
	 */
	public function get_logs( $level = '', $date = '', $limit = 100, $offset = 0 ) {
		global $wpdb;

		$where_conditions = array();
		$where_values     = array();

		if ( ! empty( $level ) ) {
			$where_conditions[] = 'level = %s';
			$where_values[]     = sanitize_text_field( $level );
		}

		if ( ! empty( $date ) ) {
			$where_conditions[] = 'DATE(created_at) = %s';
			$where_values[]     = sanitize_text_field( $date );
		}

		$limit  = absint( $limit );
		$offset = absint( $offset );

		// Build the SQL directly in the prepare call.
		$sql = "SELECT * FROM $wpdb->humanitix_import_logs";

		if ( ! empty( $where_conditions ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_conditions );
		}

		$sql .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';

		// Add limit and offset to values array.
		$prepare_values   = $where_values;
		$prepare_values[] = $limit;
		$prepare_values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, ...$prepare_values ) );
	}

	/**
	 * Get recent logs
	 *
	 * @param int $limit The number of logs to return.
	 * @return array Recent logs.
	 */
	public function get_recent_logs( $limit = 10 ) {
		return $this->get_logs( '', '', $limit, 0 );
	}

	/**
	 * Get recent imports
	 *
	 * @param int $limit The number of imports to return.
	 */
	public function get_recent_imports( $limit = 10 ) {
		global $wpdb;

		$limit = absint( $limit );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->humanitix_import_logs 
                 WHERE level = %s 
                 ORDER BY created_at DESC 
                 LIMIT %d",
				'import',
				$limit
			)
		);
	}

	/**
	 * Get import statistics
	 *
	 * @param int $days The number of days out to pull the statistics from.
	 */
	public function get_import_stats( $days = 30 ) {
		global $wpdb;

		$days = absint( $days );

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                    COUNT(*) as total_imports,
                    COUNT(CASE WHEN level = %s THEN 1 END) as total_errors,
                    COUNT(CASE WHEN level = %s THEN 1 END) as total_warnings,
                    MAX(created_at) as last_import
                 FROM $wpdb->humanitix_import_logs 
                 WHERE level IN (%s, %s, %s) 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				'error',
				'warning',
				'import',
				'error',
				'warning',
				$days
			)
		);

		return $stats;
	}

	/**
	 * Get daily import counts for charting
	 *
	 * @param int $days The number of days to get the daily import count for. Defaults to 30.
	 */
	public function get_daily_imports( $days = 30 ) {
		global $wpdb;

		$days = absint( $days );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count,
                    COUNT(CASE WHEN level = %s THEN 1 END) as errors
                 FROM $wpdb->humanitix_import_logs 
                 WHERE level IN (%s, %s) 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY date DESC",
				'error',
				'import',
				'error',
				$days
			)
		);
	}

	/**
	 * Clean up old logs
	 *
	 * @param int $days The number of days out to clean up old logs.
	 */
	public function cleanup_old_logs( $days = 30 ) {
		global $wpdb;

		$days = absint( $days );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->humanitix_import_logs 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return $deleted;
	}

	/**
	 * Clean up debug log file
	 *
	 * @param int $max_size_mb Maximum size in MB before cleanup.
	 * @return bool Whether cleanup was successful.
	 */
	public function cleanup_debug_log( $max_size_mb = 10 ) {
		$log_file = WP_CONTENT_DIR . '/humanitix-debug.log';

		if ( ! file_exists( $log_file ) ) {
			return true;
		}

		$file_size = filesize( $log_file );
		$max_size  = $max_size_mb * 1024 * 1024; // Convert MB to bytes.

		if ( $file_size > $max_size ) {
			// Keep only the last 1000 lines.
			$lines = file( $log_file );
			$lines = array_slice( $lines, -1000 );
			file_put_contents( $log_file, implode( '', $lines ) );
			return true;
		}

		return true;
	}

	/**
	 * Log a concise import summary
	 *
	 * @param int   $imported_count Number of events imported.
	 * @param array $errors Array of error messages.
	 * @param float $duration Import duration in seconds.
	 */
	public function log_import_summary( $imported_count, $errors = array(), $duration = 0 ) {
		$message = sprintf(
			'Import completed: %d events imported in %.2f seconds',
			$imported_count,
			$duration
		);

		if ( ! empty( $errors ) ) {
			$message .= sprintf( ' (%d errors)', count( $errors ) );
		}

		$this->log(
			'import',
			$message,
			array(
				'imported_count' => $imported_count,
				'error_count'    => count( $errors ),
				'errors'         => $errors,
				'duration'       => $duration,
			)
		);
	}

	/**
	 * Get total log count
	 */
	public function get_total_count() {
		global $wpdb;

		return $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->humanitix_import_logs" );
	}

	/**
	 * Export logs to CSV
	 *
	 * @param string $level The level of all of the logs that need to be exported.
	 * @param string $date The date range for which the logs should be exported.
	 */
	public function export_logs( $level = '', $date = '' ) {
		$logs = $this->get_logs( $level, $date, 10000 ); // Large limit for export.

		$filename = 'humanitix-import-logs-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// Use WordPress filesystem methods.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$csv_content = '';

		// CSV headers.
		$csv_content .= implode( ',', array( 'Date', 'Level', 'Message', 'Context' ) ) . "\n";

		// CSV data.
		foreach ( $logs as $log ) {
			$context     = ! empty( $log->context ) ? json_decode( $log->context, true ) : array();
			$context_str = is_array( $context ) ? wp_json_encode( $context ) : $context;

			$row = array(
				$log->created_at,
				$log->level,
				$log->message,
				$context_str,
			);

			// Escape CSV values properly.
			$escaped_row = array_map(
				function ( $value ) {
					if ( strpos( $value, ',' ) !== false || strpos( $value, '"' ) !== false || strpos( $value, "\n" ) !== false ) {
						return '"' . str_replace( '"', '""', $value ) . '"';
					}
					return $value;
				},
				$row
			);

			$csv_content .= implode( ',', $escaped_row ) . "\n";
		}

		// Escape output for security.
		echo wp_kses_post( $csv_content );
		exit;
	}

	/**
	 * Check if table exists
	 */
	public function table_exists() {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->humanitix_import_logs
			)
		);

		return $result === $wpdb->humanitix_import_logs;
	}

	/**
	 * Get API connection test logs.
	 *
	 * @param int $limit The number of connection tests to return.
	 * @return array Array of connection test logs.
	 */
	public function get_connection_test_logs( $limit = 50 ) {
		global $wpdb;

		$limit = absint( $limit );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->humanitix_import_logs 
                 WHERE context LIKE %s 
                 ORDER BY created_at DESC 
                 LIMIT %d",
				'%test_type%connection_test%',
				$limit
			)
		);
	}

	/**
	 * Get connection test statistics.
	 *
	 * @param int $days The number of days to analyze.
	 * @return object Connection test statistics.
	 */
	public function get_connection_test_stats( $days = 30 ) {
		global $wpdb;

		$days = absint( $days );

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                    COUNT(*) as total_tests,
                    COUNT(CASE WHEN level = %s THEN 1 END) as successful_tests,
                    COUNT(CASE WHEN level = %s THEN 1 END) as failed_tests,
                    COUNT(CASE WHEN level = %s THEN 1 END) as warning_tests,
                    MAX(created_at) as last_test
                 FROM $wpdb->humanitix_import_logs 
                 WHERE context LIKE %s 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				'success',
				'error',
				'warning',
				'%test_type%connection_test%',
				$days
			)
		);

		// Calculate success rate.
		if ( $stats && $stats->total_tests > 0 ) {
			$stats->success_rate = round( ( $stats->successful_tests / $stats->total_tests ) * 100, 2 );
		} else {
			$stats->success_rate = 0;
		}

		return $stats;
	}

	/**
	 * Get recent connection test results.
	 *
	 * @param int $limit The number of recent tests to return.
	 * @return array Array of recent connection test results.
	 */
	public function get_recent_connection_tests( $limit = 10 ) {
		global $wpdb;

		$limit = absint( $limit );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                    level,
                    message,
                    context,
                    created_at,
                    CASE 
                        WHEN level = %s THEN 'success'
                        WHEN level = %s THEN 'error'
                        WHEN level = %s THEN 'warning'
                        ELSE 'info'
                    END as status
                 FROM $wpdb->humanitix_import_logs 
                 WHERE context LIKE %s 
                 ORDER BY created_at DESC 
                 LIMIT %d",
				'success',
				'error',
				'warning',
				'%test_type%connection_test%',
				$limit
			)
		);
	}
}
