<?php
/**
 * Admin Logger Class.
 *
 * Handles the logging and debugging logic for the plugin.
 *
 * @package SG\EventbriteCourseImporter\Admin
 * @since 1.0.0
 */

namespace SG\EventbriteCourseImporter\Admin;

/**
 * Admin Logger Class.
 *
 * Handles the logging and debugging logic for the plugin.
 *
 * @package SG\EventbriteCourseImporter\Admin
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
		$this->table_name = $wpdb->prefix . 'sg_eventbrite_import_logs';

		// Add table name to $wpdb object for WPCS compatibility.
		$wpdb->sg_eventbrite_import_logs = $this->table_name;
	}

	/**
	 * Log a message with context
	 *
	 * @param string $message The content of the log message.
	 * @param string $level The level of the message log message.
	 * @param array  $context Optional - Additional structured data.
	 */
	public function log( $message, $level = 'info', $context = array() ) {
		// Check if we should skip this log based on level
		if ( ! $this->should_log_level( $level ) ) {
			return false;
		}

		// Check if we should skip this log based on content
		if ( $this->should_skip_log( $message, $context ) ) {
			return false;
		}

		// Truncate context if it's too large
		$context = $this->truncate_context( $context );
		global $wpdb;

		// Sanitize level.
		$level = sanitize_text_field( $level );

		// Sanitize message.
		$message = sanitize_textarea_field( $message );

		// Prepare context for storage.
		$context_json = ! empty( $context ) ? wp_json_encode( $context ) : null;

		$result = $wpdb->insert(
			$wpdb->sg_eventbrite_import_logs,
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
				error_log( 'Eventbrite Course Importer: Failed to insert log entry: ' . $wpdb->last_error );
			}
		}

		return $result;
	}

	/**
	 * Log an error with error code
	 *
	 * @param int    $error_code The error code from ErrorCode class.
	 * @param string $context Optional - Additional context information.
	 * @param array  $data Optional - Additional structured data.
	 */
	public function log_error_code( $error_code, $context = '', $data = array() ) {
		// Check if SG_EVENTBRITE_DEBUG is enabled.
		if ( defined( 'SG_EVENTBRITE_DEBUG' ) && SG_EVENTBRITE_DEBUG ) {
			// Full debugging - log everything.
			$message  = ErrorCode::format_error( $error_code, $context );
			$log_data = array_merge(
				$data,
				array(
					'error_code'  => $error_code,
					'category'    => ErrorCode::get_category( $error_code ),
					'is_critical' => ErrorCode::is_critical( $error_code ),
					'context'     => $context,
				)
			);
		} else {
			// Minimal logging - just error code and basic message.
			$message  = ErrorCode::format_error( $error_code, $context );
			$log_data = array(
				'error_code'  => $error_code,
				'category'    => ErrorCode::get_category( $error_code ),
				'is_critical' => ErrorCode::is_critical( $error_code ),
			);
		}

		return $this->log( 'error', $message, $log_data );
	}

	/**
	 * Log import summary with error codes
	 *
	 * @param int   $imported_count Number of events imported.
	 * @param int   $updated_count Number of events updated.
	 * @param int   $existing_count Number of events that already existed.
	 * @param array $errors Array of error codes encountered.
	 * @param float $duration Import duration in seconds.
	 */
	public function log_import_summary_with_codes( $imported_count, $updated_count, $existing_count, $errors = array(), $duration = 0 ) {
		$summary = sprintf(
			'Import completed: %d new events, %d updated events, %d existing events in %.2f seconds',
			$imported_count,
			$updated_count,
			$existing_count,
			$duration
		);

		$context = array(
			'imported_count' => $imported_count,
			'updated_count'  => $updated_count,
			'existing_count' => $existing_count,
			'duration'       => $duration,
			'error_count'    => count( $errors ),
		);

		// If SG_EVENTBRITE_DEBUG is enabled, include detailed error information.
		if ( defined( 'SG_EVENTBRITE_DEBUG' ) && SG_EVENTBRITE_DEBUG && ! empty( $errors ) ) {
			$context['errors'] = $errors;
		}

		return $this->log( 'import', $summary, $context );
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

		// Memory optimization: Check available memory
		$memory_limit = ini_get( 'memory_limit' );
		$memory_usage = memory_get_usage( true );
		$memory_limit_bytes = $this->return_bytes( $memory_limit );
		$available_memory = $memory_limit_bytes - $memory_usage;
		
		// If we have less than 10MB available, reduce the limit
		if ( $available_memory < 10 * 1024 * 1024 ) {
			$limit = min( $limit, 10 ); // Reduce to max 10 records
		}

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
		$sql = "SELECT * FROM $wpdb->sg_eventbrite_import_logs";

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
	 * Check if we should log based on current level settings.
	 *
	 * @param string $level The log level to check.
	 * @return bool Whether to log this level.
	 */
	private function should_log_level( $level ) {
		// Get current settings
		$settings = get_option( 'sg_eventbrite_importer_options', array() );
		$log_level = isset( $settings['log_level'] ) ? $settings['log_level'] : 'info';
		
		// Define level hierarchy
		$levels = array(
			'debug' => 0,
			'info' => 1,
			'warning' => 2,
			'error' => 3,
			'critical' => 4,
		);
		
		$current_level = isset( $levels[ $log_level ] ) ? $levels[ $log_level ] : 1;
		$message_level = isset( $levels[ $level ] ) ? $levels[ $level ] : 1;
		
		return $message_level >= $current_level;
	}

	/**
	 * Check if we should skip logging this message based on content.
	 *
	 * @param string $message The log message.
	 * @param array  $context The log context.
	 * @return bool Whether to skip this log.
	 */
	private function should_skip_log( $message, $context ) {
		// Check if noise filtering is enabled
		$settings = get_option( 'sg_eventbrite_importer_options', array() );
		$filter_noise = isset( $settings['filter_log_noise'] ) ? $settings['filter_log_noise'] : true;
		
		if ( ! $filter_noise ) {
			return false;
		}
		// Skip template assets and hooks initiations
		$skip_patterns = array(
			// Template assets
			'template assets',
			'template-assets',
			'assets/css/',
			'assets/js/',
			'assets/src/',
			'TemplateAssets',
			'TemplateManager',
			'template manager',
			'template initialization',
			'template loaded',
			'template hooks',
			'TemplateHooks',
			'template hooks registered',
			'template hooks initialized',
			'template override',
			'single-event.php',
			'templates.css',
			'templates.js',
			
			// Hooks initiations
			'hooks initialized',
			'hooks registered',
			'add_action',
			'add_filter',
			'register_hook',
			'init_hooks',
			'WordPress hooks',
			'admin_menu',
			'admin_init',
			'wp_ajax_',
			'wp_rest_',
			'init',
			'plugins_loaded',
			'after_setup_theme',
			
			// General noise
			'initialized successfully',
			'loaded successfully',
			'registered successfully',
			'created successfully',
			'setup complete',
			'initialization complete',
			'constructor called',
			'class instantiated',
		);

		$message_lower = strtolower( $message );
		$context_json = wp_json_encode( $context );
		$context_lower = strtolower( $context_json );

		foreach ( $skip_patterns as $pattern ) {
			if ( strpos( $message_lower, $pattern ) !== false ) {
				return true;
			}
			if ( strpos( $context_lower, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Truncate context data to prevent oversized logs.
	 *
	 * @param array $context The context data to truncate.
	 * @return array Truncated context data.
	 */
	private function truncate_context( $context ) {
		if ( empty( $context ) ) {
			return $context;
		}

		$max_context_size = 500; // Maximum context size in characters
		$context_json = wp_json_encode( $context );
		
		if ( strlen( $context_json ) > $max_context_size ) {
			// Truncate large context
			$truncated = array();
			$current_size = 0;
			
			foreach ( $context as $key => $value ) {
				$item_json = wp_json_encode( array( $key => $value ) );
				
				if ( $current_size + strlen( $item_json ) > $max_context_size ) {
					$truncated['_truncated'] = 'Context truncated due to size limit';
					break;
				}
				
				$truncated[ $key ] = $value;
				$current_size += strlen( $item_json );
			}
			
			return $truncated;
		}
		
		return $context;
	}

	/**
	 * Convert memory limit string to bytes
	 *
	 * @param string $val Memory limit string (e.g., '128M', '1G').
	 * @return int Memory limit in bytes.
	 */
	private function return_bytes( $val ) {
		$val = trim( $val );
		$last = strtolower( $val[ strlen( $val ) - 1 ] );
		$val = (int) $val;
		switch ( $last ) {
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}
		return $val;
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
				"SELECT * FROM $wpdb->sg_eventbrite_import_logs 
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
                 FROM $wpdb->sg_eventbrite_import_logs 
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
                 FROM $wpdb->sg_eventbrite_import_logs 
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
				"DELETE FROM $wpdb->sg_eventbrite_import_logs 
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
		$log_file = WP_CONTENT_DIR . '/sg-eventbrite-debug.log';

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

		return $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->sg_eventbrite_import_logs" );
	}

	/**
	 * Export logs to CSV
	 *
	 * @param string $level The level of all of the logs that need to be exported.
	 * @param string $date The date range for which the logs should be exported.
	 */
	public function export_logs( $level = '', $date = '' ) {
		$logs = $this->get_logs( $level, $date, 10000 ); // Large limit for export.

		$filename = 'sg-eventbrite-import-logs-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';

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
				$wpdb->sg_eventbrite_import_logs
			)
		);

		return $result === $wpdb->sg_eventbrite_import_logs;
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
				"SELECT * FROM $wpdb->sg_eventbrite_import_logs 
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
                 FROM $wpdb->sg_eventbrite_import_logs 
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
                 FROM $wpdb->sg_eventbrite_import_logs 
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
