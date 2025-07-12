<?php
/**
 * Debug Helper Class.
 *
 * Provides optimized debug logging methods to work with the existing Logger class.
 * Reduces verbose error_log statements and provides structured, concise debug information.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

/**
 * Debug Helper Class.
 *
 * Provides optimized debug logging methods to work with the existing Logger class.
 * Reduces verbose error_log statements and provides structured, concise debug information.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class DebugHelper {

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Whether debug mode is enabled.
	 *
	 * @var bool
	 */
	private $debug_enabled;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger The logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Log a concise debug message with context.
	 *
	 * @param string $component The component name (e.g., 'API', 'Importer', 'DataMapper').
	 * @param string $action The action being performed.
	 * @param array  $context Optional context data.
	 * @param string $level The log level (debug, info, warning, error).
	 */
	public function log( $component, $action, $context = array(), $level = 'debug' ) {
		if ( ! $this->debug_enabled ) {
			return;
		}

		$message = sprintf( '[%s] %s', strtoupper( $component ), $action );
		
		$this->logger->log( $level, $message, $context );
	}

	/**
	 * Log API request/response data concisely.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $request_data Request data (sanitized).
	 * @param array  $response_data Response data (sanitized).
	 * @param int    $status_code HTTP status code.
	 */
	public function log_api_request( $endpoint, $request_data = array(), $response_data = array(), $status_code = null ) {
		$context = array(
			'endpoint'     => $endpoint,
			'status_code'  => $status_code,
			'request_size' => is_array( $request_data ) ? count( $request_data ) : 0,
			'response_size' => is_array( $response_data ) ? count( $response_data ) : 0,
		);

		// Only include actual data if it's small and relevant
		if ( ! empty( $request_data ) && count( $request_data ) <= 5 ) {
			$context['request_data'] = $this->sanitize_debug_data( $request_data );
		}

		if ( ! empty( $response_data ) && count( $response_data ) <= 10 ) {
			$context['response_data'] = $this->sanitize_debug_data( $response_data );
		}

		$this->log( 'API', "Request to {$endpoint}", $context );
	}

	/**
	 * Log event processing data concisely.
	 *
	 * @param string $event_name The event name.
	 * @param string $humanitix_id The Humanitix event ID.
	 * @param array  $event_data Event data summary.
	 * @param string $action The action being performed (create, update, skip).
	 */
	public function log_event_processing( $event_name, $humanitix_id, $event_data = array(), $action = 'process' ) {
		$context = array(
			'humanitix_id' => $humanitix_id,
			'action'       => $action,
			'has_pricing'  => isset( $event_data['pricing'] ) || isset( $event_data['ticketTypes'] ),
		);

		$this->log( 'Importer', "{$action} event: {$event_name}", $context );
	}

	/**
	 * Log event creation/update status inline.
	 *
	 * @param string $event_name The event name.
	 * @param int    $post_id The WordPress post ID.
	 * @param string $action The action (created, updated, failed).
	 * @param array  $context Additional context.
	 */
	public function log_event_status( $event_name, $post_id, $action, $context = array() ) {
		$context['post_id'] = $post_id;
		$context['action'] = $action;
		
		$this->log( 'Importer', "Event {$action}: {$event_name} (ID: {$post_id})", $context );
	}

	/**
	 * Log venue processing data concisely.
	 *
	 * @param string $venue_name The venue name.
	 * @param int    $venue_id The venue ID (if created/found).
	 * @param string $action The action (create, find, link).
	 * @param array  $venue_data Venue data summary.
	 */
	public function log_venue_processing( $venue_name, $venue_id = null, $action = 'process', $venue_data = array() ) {
		$context = array(
			'venue_name' => $venue_name,
			'venue_id'   => $venue_id,
			'action'     => $action,
			'has_address' => isset( $venue_data['address'] ),
			'has_coords' => isset( $venue_data['latLng'] ) || isset( $venue_data['lat_lng'] ),
		);

		$this->log( 'Venue', "{$action} venue: {$venue_name}", $context );
	}

	/**
	 * Log data mapping information concisely.
	 *
	 * @param string $source_field The source field name.
	 * @param string $target_field The target field name.
	 * @param mixed  $value The mapped value (sanitized).
	 * @param string $component The component doing the mapping.
	 */
	public function log_data_mapping( $source_field, $target_field, $value = null, $component = 'DataMapper' ) {
		$context = array(
			'source_field' => $source_field,
			'target_field' => $target_field,
			'value_type'   => gettype( $value ),
			'value_preview' => $this->get_value_preview( $value ),
		);

		$this->log( $component, "Map {$source_field} → {$target_field}", $context );
	}

	/**
	 * Log error with context concisely.
	 *
	 * @param string $component The component where the error occurred.
	 * @param string $error_message The error message.
	 * @param array  $context Additional error context.
	 */
	public function log_error( $component, $error_message, $context = array() ) {
		$context['error_type'] = 'error';
		$context['timestamp']  = current_time( 'mysql' );

		$this->log( $component, $error_message, $context, 'error' );
	}

	/**
	 * Log critical error that should appear in admin interface.
	 * These are errors that could break the event or site functionality.
	 *
	 * @param string $component The component where the error occurred.
	 * @param string $error_message The error message.
	 * @param array  $context Additional error context.
	 */
	public function log_critical_error( $component, $error_message, $context = array() ) {
		$context['error_type'] = 'critical';
		$context['timestamp']  = current_time( 'mysql' );
		$context['admin_visible'] = true;

		// Log to both debug and admin interface
		$this->log( $component, "CRITICAL: {$error_message}", $context, 'error' );
		
		// Also log to admin interface for immediate visibility
		$this->logger->log( 'error', "CRITICAL ERROR: {$error_message}", $context );
	}

	/**
	 * Log warning with context concisely.
	 *
	 * @param string $component The component where the warning occurred.
	 * @param string $warning_message The warning message.
	 * @param array  $context Additional warning context.
	 */
	public function log_warning( $component, $warning_message, $context = array() ) {
		$context['warning_type'] = 'warning';
		$context['timestamp']    = current_time( 'mysql' );

		$this->log( $component, $warning_message, $context, 'warning' );
	}

	/**
	 * Log import summary concisely.
	 *
	 * @param int   $total_events Total events processed.
	 * @param int   $imported_count Number of events imported.
	 * @param int   $skipped_count Number of events skipped.
	 * @param array $errors Array of errors.
	 * @param float $duration Import duration.
	 */
	public function log_import_summary( $total_events, $imported_count, $skipped_count = 0, $errors = array(), $duration = 0 ) {
		$context = array(
			'total_events'    => $total_events,
			'imported_count'  => $imported_count,
			'skipped_count'   => $skipped_count,
			'error_count'     => count( $errors ),
			'duration'        => $duration,
			'success_rate'    => $total_events > 0 ? round( ( $imported_count / $total_events ) * 100, 2 ) : 0,
		);

		if ( ! empty( $errors ) ) {
			$context['errors'] = array_slice( $errors, 0, 5 ); // Limit to first 5 errors
		}

		$this->log( 'Import', "Summary: {$imported_count}/{$total_events} imported", $context, 'info' );
	}

	/**
	 * Log performance metrics concisely.
	 *
	 * @param string $operation The operation name.
	 * @param float  $duration Duration in seconds.
	 * @param array  $metrics Additional metrics.
	 */
	public function log_performance( $operation, $duration, $metrics = array() ) {
		$context = array(
			'operation' => $operation,
			'duration'  => $duration,
			'metrics'   => $metrics,
		);

		$this->log( 'Performance', "{$operation} completed in {$duration}s", $context );
	}

	/**
	 * Sanitize debug data to prevent sensitive information leakage.
	 *
	 * @param mixed $data The data to sanitize.
	 * @return mixed The sanitized data.
	 */
	private function sanitize_debug_data( $data ) {
		if ( is_array( $data ) ) {
			$sanitized = array();
			foreach ( $data as $key => $value ) {
				// Redact sensitive keys
				if ( in_array( strtolower( $key ), array( 'api_key', 'password', 'token', 'secret' ), true ) ) {
					$sanitized[ $key ] = '[REDACTED]';
				} else {
					$sanitized[ $key ] = $this->sanitize_debug_data( $value );
				}
			}
			return $sanitized;
		}

		if ( is_string( $data ) && strlen( $data ) > 200 ) {
			return substr( $data, 0, 200 ) . '...';
		}

		return $data;
	}

	/**
	 * Get a preview of a value for logging.
	 *
	 * @param mixed $value The value to preview.
	 * @return string The preview string.
	 */
	private function get_value_preview( $value ) {
		if ( is_null( $value ) ) {
			return 'null';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_string( $value ) ) {
			return strlen( $value ) > 50 ? substr( $value, 0, 50 ) . '...' : $value;
		}

		if ( is_array( $value ) ) {
			return 'array(' . count( $value ) . ' items)';
		}

		if ( is_object( $value ) ) {
			return get_class( $value ) . ' object';
		}

		return (string) $value;
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool Whether debug mode is enabled.
	 */
	public function is_debug_enabled() {
		return $this->debug_enabled;
	}

	/**
	 * Batch log messages to reduce database writes.
	 * Only logs to file system when debug is enabled.
	 *
	 * @param array $messages Array of messages to batch log.
	 */
	public function batch_log( $messages ) {
		if ( ! $this->debug_enabled ) {
			return;
		}

		$log_file = WP_CONTENT_DIR . '/humanitix-debug.log';
		$timestamp = current_time( 'mysql' );
		
		$log_entries = array();
		foreach ( $messages as $message ) {
			$log_entries[] = "[{$timestamp}] " . $message;
		}
		
		file_put_contents( $log_file, implode( "\n", $log_entries ) . "\n", FILE_APPEND | LOCK_EX );
	}

	/**
	 * Log only critical errors to database, everything else to file.
	 *
	 * @param string $component The component name.
	 * @param string $action The action being performed.
	 * @param array  $context Optional context data.
	 * @param string $level The log level.
	 */
	public function log_optimized( $component, $action, $context = array(), $level = 'debug' ) {
		// Only log critical errors to database
		if ( $level === 'error' || $level === 'critical' ) {
			$this->log( $component, $action, $context, $level );
		} else {
			// Log to file for performance
			$message = sprintf( '[%s] %s', strtoupper( $component ), $action );
			$log_file = WP_CONTENT_DIR . '/humanitix-debug.log';
			$timestamp = current_time( 'mysql' );
			$context_str = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
			file_put_contents( $log_file, "[{$timestamp}] {$message}{$context_str}\n", FILE_APPEND | LOCK_EX );
		}
	}

	/**
	 * Check if optimized logging should be used.
	 *
	 * @return bool Whether to use optimized logging.
	 */
	public function should_use_optimized_logging() {
		// Use optimized logging by default, unless explicitly disabled
		return PerformanceConfig::is_optimized_logging_enabled();
	}

	/**
	 * Smart logging that chooses between database and file based on context.
	 *
	 * @param string $component The component name.
	 * @param string $action The action being performed.
	 * @param array  $context Optional context data.
	 * @param string $level The log level.
	 */
	public function smart_log( $component, $action, $context = array(), $level = 'debug' ) {
		if ( $this->should_use_optimized_logging() ) {
			$this->log_optimized( $component, $action, $context, $level );
		} else {
			$this->log( $component, $action, $context, $level );
		}
	}
} 