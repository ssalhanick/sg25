<?php
/**
 * Error Code System for Humanitix API Importer
 *
 * Provides standardized error codes for quick issue identification
 * when HUMANITIX_DEBUG is false.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

/**
 * Error Code System for Humanitix API Importer
 *
 * Provides standardized error codes for quick issue identification
 * when HUMANITIX_DEBUG is false.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class ErrorCode {

	// API Connection Errors (1000-1999).
	const API_KEY_INVALID           = 1001;
	const API_CONNECTION_TIMEOUT    = 1002;
	const API_RATE_LIMIT_EXCEEDED   = 1003;
	const API_SERVER_ERROR          = 1004;
	const API_CLIENT_ERROR          = 1005;
	const API_NETWORK_ERROR         = 1006;
	const API_SSL_ERROR             = 1007;
	const API_RESPONSE_INVALID      = 1008;
	const API_AUTHENTICATION_FAILED = 1009;

	// Data Validation Errors (2000-2999).
	const DATA_MISSING_EVENT_NAME     = 2001;
	const DATA_MISSING_VENUE          = 2002;
	const DATA_MISSING_DATE_TIME      = 2003;
	const DATA_MISSING_FEATURED_IMAGE = 2004;
	const DATA_INVALID_DATE_FORMAT    = 2005;
	const DATA_INVALID_TIMEZONE       = 2006;
	const DATA_MISSING_REQUIRED_FIELD = 2007;
	const DATA_INVALID_JSON           = 2008;
	const DATA_EMPTY_RESPONSE         = 2009;

	// WordPress Integration Errors (3000-3999).
	const WP_POST_CREATION_FAILED       = 3001;
	const WP_POST_UPDATE_FAILED         = 3002;
	const WP_TERM_CREATION_FAILED       = 3003;
	const WP_META_UPDATE_FAILED         = 3004;
	const WP_ATTACHMENT_CREATION_FAILED = 3005;
	const WP_PERMISSION_DENIED          = 3006;
	const WP_DATABASE_ERROR             = 3007;
	const WP_MEMORY_LIMIT_EXCEEDED      = 3008;
	const WP_TIMEOUT_ERROR              = 3009;

	// Memory Management Errors (4000-4999).
	const MEMORY_LIMIT_REACHED             = 4001;
	const MEMORY_BATCH_SIZE_TOO_LARGE      = 4002;
	const MEMORY_GARBAGE_COLLECTION_FAILED = 4003;
	const MEMORY_MONITORING_FAILED         = 4004;
	const MEMORY_OPTIMIZATION_FAILED       = 4005;

	// Import Process Errors (5000-5999).
	const IMPORT_BATCH_FAILED               = 5001;
	const IMPORT_VENUE_MAPPING_FAILED       = 5002;
	const IMPORT_ORGANIZER_MAPPING_FAILED   = 5003;
	const IMPORT_DATE_PROCESSING_FAILED     = 5004;
	const IMPORT_IMAGE_PROCESSING_FAILED    = 5005;
	const IMPORT_DUPLICATE_DETECTION_FAILED = 5006;
	const IMPORT_VALIDATION_FAILED          = 5007;
	const IMPORT_MAPPING_FAILED             = 5008;

	// Configuration Errors (6000-6999).
	const CONFIG_API_KEY_MISSING   = 6001;
	const CONFIG_INVALID_SETTINGS  = 6002;
	const CONFIG_PERMISSION_DENIED = 6003;
	const CONFIG_DATABASE_ERROR    = 6004;
	const CONFIG_FILE_NOT_FOUND    = 6005;
	const CONFIG_INVALID_FORMAT    = 6006;

	/**
	 * Get error message for a given error code
	 *
	 * @param int $code Error code.
	 * @return string Human-readable error message
	 */
	public static function get_message( $code ) {
		$messages = array(
			// API Connection Errors.
			self::API_KEY_INVALID                   => 'API key is invalid or expired',
			self::API_CONNECTION_TIMEOUT            => 'API connection timed out',
			self::API_RATE_LIMIT_EXCEEDED           => 'API rate limit exceeded',
			self::API_SERVER_ERROR                  => 'API server error occurred',
			self::API_CLIENT_ERROR                  => 'API client error occurred',
			self::API_NETWORK_ERROR                 => 'Network connectivity issue',
			self::API_SSL_ERROR                     => 'SSL/TLS certificate error',
			self::API_RESPONSE_INVALID              => 'Invalid API response format',
			self::API_AUTHENTICATION_FAILED         => 'API authentication failed',

			// Data Validation Errors.
			self::DATA_MISSING_EVENT_NAME           => 'Event name is missing',
			self::DATA_MISSING_VENUE                => 'Venue information is missing',
			self::DATA_MISSING_DATE_TIME            => 'Event date/time is missing',
			self::DATA_MISSING_FEATURED_IMAGE       => 'Featured image is missing',
			self::DATA_INVALID_DATE_FORMAT          => 'Invalid date format received',
			self::DATA_INVALID_TIMEZONE             => 'Invalid timezone information',
			self::DATA_MISSING_REQUIRED_FIELD       => 'Required field is missing',
			self::DATA_INVALID_JSON                 => 'Invalid JSON response',
			self::DATA_EMPTY_RESPONSE               => 'Empty response from API',

			// WordPress Integration Errors.
			self::WP_POST_CREATION_FAILED           => 'Failed to create WordPress post',
			self::WP_POST_UPDATE_FAILED             => 'Failed to update WordPress post',
			self::WP_TERM_CREATION_FAILED           => 'Failed to create WordPress term',
			self::WP_META_UPDATE_FAILED             => 'Failed to update post meta',
			self::WP_ATTACHMENT_CREATION_FAILED     => 'Failed to create attachment',
			self::WP_PERMISSION_DENIED              => 'WordPress permission denied',
			self::WP_DATABASE_ERROR                 => 'WordPress database error',
			self::WP_MEMORY_LIMIT_EXCEEDED          => 'WordPress memory limit exceeded',
			self::WP_TIMEOUT_ERROR                  => 'WordPress timeout error',

			// Memory Management Errors.
			self::MEMORY_LIMIT_REACHED              => 'Memory limit reached during import',
			self::MEMORY_BATCH_SIZE_TOO_LARGE       => 'Batch size too large for available memory',
			self::MEMORY_GARBAGE_COLLECTION_FAILED  => 'Garbage collection failed',
			self::MEMORY_MONITORING_FAILED          => 'Memory monitoring failed',
			self::MEMORY_OPTIMIZATION_FAILED        => 'Memory optimization failed',

			// Import Process Errors.
			self::IMPORT_BATCH_FAILED               => 'Batch processing failed',
			self::IMPORT_VENUE_MAPPING_FAILED       => 'Venue mapping failed',
			self::IMPORT_ORGANIZER_MAPPING_FAILED   => 'Organizer mapping failed',
			self::IMPORT_DATE_PROCESSING_FAILED     => 'Date processing failed',
			self::IMPORT_IMAGE_PROCESSING_FAILED    => 'Image processing failed',
			self::IMPORT_DUPLICATE_DETECTION_FAILED => 'Duplicate detection failed',
			self::IMPORT_VALIDATION_FAILED          => 'Import validation failed',
			self::IMPORT_MAPPING_FAILED             => 'Data mapping failed',

			// Configuration Errors.
			self::CONFIG_API_KEY_MISSING            => 'API key not configured',
			self::CONFIG_INVALID_SETTINGS           => 'Invalid plugin settings',
			self::CONFIG_PERMISSION_DENIED          => 'Configuration permission denied',
			self::CONFIG_DATABASE_ERROR             => 'Configuration database error',
			self::CONFIG_FILE_NOT_FOUND             => 'Configuration file not found',
			self::CONFIG_INVALID_FORMAT             => 'Invalid configuration format',
		);

		return isset( $messages[ $code ] ) ? $messages[ $code ] : 'Unknown error occurred';
	}

	/**
	 * Get error category for a given error code
	 *
	 * @param int $code Error code.
	 * @return string Error category
	 */
	public static function get_category( $code ) {
		if ( $code >= 1000 && $code < 2000 ) {
			return 'API';
		} elseif ( $code >= 2000 && $code < 3000 ) {
			return 'Data';
		} elseif ( $code >= 3000 && $code < 4000 ) {
			return 'WordPress';
		} elseif ( $code >= 4000 && $code < 5000 ) {
			return 'Memory';
		} elseif ( $code >= 5000 && $code < 6000 ) {
			return 'Import';
		} elseif ( $code >= 6000 && $code < 7000 ) {
			return 'Configuration';
		}

		return 'Unknown';
	}

	/**
	 * Check if error code is critical (should stop import)
	 *
	 * @param int $code Error code
	 * @return bool True if critical error
	 */
	public static function is_critical( $code ) {
		$critical_codes = array(
			self::API_KEY_INVALID,
			self::API_AUTHENTICATION_FAILED,
			self::CONFIG_API_KEY_MISSING,
			self::WP_PERMISSION_DENIED,
			self::WP_DATABASE_ERROR,
			self::MEMORY_LIMIT_REACHED,
		);

		return in_array( $code, $critical_codes );
	}

	/**
	 * Get error code from exception
	 *
	 * @param \Exception $exception.
	 * @return int Error code
	 */
	public static function from_exception( $exception ) {
		$message = strtolower( $exception->getMessage() );

		// API Errors.
		if ( strpos( $message, 'api key' ) !== false || strpos( $message, 'authentication' ) !== false ) {
			return self::API_KEY_INVALID;
		}
		if ( strpos( $message, 'timeout' ) !== false ) {
			return self::API_CONNECTION_TIMEOUT;
		}
		if ( strpos( $message, 'rate limit' ) !== false ) {
			return self::API_RATE_LIMIT_EXCEEDED;
		}

		// Data Errors.
		if ( strpos( $message, 'missing' ) !== false && strpos( $message, 'name' ) !== false ) {
			return self::DATA_MISSING_EVENT_NAME;
		}
		if ( strpos( $message, 'missing' ) !== false && strpos( $message, 'venue' ) !== false ) {
			return self::DATA_MISSING_VENUE;
		}
		if ( strpos( $message, 'missing' ) !== false && ( strpos( $message, 'date' ) !== false || strpos( $message, 'time' ) !== false ) ) {
			return self::DATA_MISSING_DATE_TIME;
		}

		// WordPress Errors.
		if ( strpos( $message, 'permission' ) !== false ) {
			return self::WP_PERMISSION_DENIED;
		}
		if ( strpos( $message, 'database' ) !== false ) {
			return self::WP_DATABASE_ERROR;
		}
		if ( strpos( $message, 'memory' ) !== false ) {
			return self::WP_MEMORY_LIMIT_EXCEEDED;
		}

		// Default to generic error.
		return self::IMPORT_MAPPING_FAILED;
	}

	/**
	 * Format error for logging
	 *
	 * @param int    $code Error code.
	 * @param string $context Additional context.
	 * @return string Formatted error message
	 */
	public static function format_error( $code, $context = '' ) {
		$category = self::get_category( $code );
		$message  = self::get_message( $code );
		$critical = self::is_critical( $code ) ? ' [CRITICAL]' : '';

		$formatted = "[ERROR-{$code}] {$category}: {$message}{$critical}";

		if ( ! empty( $context ) ) {
			$formatted .= " - {$context}";
		}

		return $formatted;
	}
}
