<?php
/**
 * Performance Configuration Class.
 *
 * Controls performance optimization settings for the Humanitix importer.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Admin;

/**
 * Performance Configuration Class.
 *
 * Controls performance optimization settings for the Humanitix importer.
 *
 * @package SG\HumanitixApiImporter\Admin
 * @since 1.0.0
 */
class PerformanceConfig {

	/**
	 * Get performance configuration settings.
	 *
	 * @return array Configuration settings.
	 */
	public static function get_settings() {
		return array(
			'optimized_logging'     => ! defined( 'HUMANITIX_OPTIMIZED_LOGGING' ) || HUMANITIX_OPTIMIZED_LOGGING,
			'enable_image_download' => ! defined( 'HUMANITIX_DISABLE_IMAGE_DOWNLOAD' ) || ! HUMANITIX_DISABLE_IMAGE_DOWNLOAD,
			'batch_size'            => defined( 'HUMANITIX_BATCH_SIZE' ) ? HUMANITIX_BATCH_SIZE : 25,
			'enable_caching'        => ! defined( 'HUMANITIX_DISABLE_CACHING' ) || HUMANITIX_DISABLE_CACHING,
			'log_to_file'           => ! defined( 'HUMANITIX_DISABLE_FILE_LOGGING' ) || HUMANITIX_DISABLE_FILE_LOGGING,
			'data_structure_optimization' => ! defined( 'HUMANITIX_DISABLE_DATA_OPTIMIZATION' ) || HUMANITIX_DISABLE_DATA_OPTIMIZATION,
			'max_string_length'     => defined( 'HUMANITIX_MAX_STRING_LENGTH' ) ? HUMANITIX_MAX_STRING_LENGTH : 255,
			'max_array_size'        => defined( 'HUMANITIX_MAX_ARRAY_SIZE' ) ? HUMANITIX_MAX_ARRAY_SIZE : 50,
			'max_ticket_types'      => defined( 'HUMANITIX_MAX_TICKET_TYPES' ) ? HUMANITIX_MAX_TICKET_TYPES : 10,
			'enable_memory_monitoring' => ! defined( 'HUMANITIX_DISABLE_MEMORY_MONITORING' ) || HUMANITIX_DISABLE_MEMORY_MONITORING,
		);
	}

	/**
	 * Check if optimized logging is enabled.
	 *
	 * @return bool Whether optimized logging is enabled.
	 */
	public static function is_optimized_logging_enabled() {
		return ! defined( 'HUMANITIX_OPTIMIZED_LOGGING' ) || HUMANITIX_OPTIMIZED_LOGGING;
	}

	/**
	 * Check if image download should be enabled.
	 *
	 * @return bool Whether to enable image downloads.
	 */
	public static function should_enable_image_download() {
		// Enable image downloads by default, but allow disabling for performance
		return ! defined( 'HUMANITIX_DISABLE_IMAGE_DOWNLOAD' ) || ! HUMANITIX_DISABLE_IMAGE_DOWNLOAD;
	}

	/**
	 * Get the batch size for imports.
	 *
	 * @return int Batch size.
	 */
	public static function get_batch_size() {
		return defined( 'HUMANITIX_BATCH_SIZE' ) ? HUMANITIX_BATCH_SIZE : 5;
	}

	/**
	 * Get dynamic batch size based on available memory and event count.
	 *
	 * @param int $total_events Total number of events to process.
	 * @return int Optimized batch size.
	 */
	public static function get_dynamic_batch_size($total_events = 0) {
		$base_batch_size = self::get_batch_size();
		
		// If less than 5 events, process sequentially (no batching)
		if ($total_events > 0 && $total_events < 5) {
			return $total_events;
		}
		
		// Get available memory
		$memory_limit = self::get_memory_limit();
		$current_memory = memory_get_usage(true);
		$available_memory = $memory_limit - $current_memory;
		
		// Target 128MB with conservative estimates
		$target_memory = 128 * 1024 * 1024; // 128MB in bytes
		
		// If we have limited memory, reduce batch size
		if ($available_memory < $target_memory) {
			$memory_factor = $available_memory / $target_memory;
			$adjusted_batch_size = max(1, floor($base_batch_size * $memory_factor));
			return min($adjusted_batch_size, $base_batch_size);
		}
		
		return $base_batch_size;
	}

	/**
	 * Get memory limit in bytes.
	 *
	 * @return int Memory limit in bytes.
	 */
	public static function get_memory_limit() {
		$memory_limit = ini_get('memory_limit');
		
		if ($memory_limit === '-1') {
			return PHP_INT_MAX;
		}
		
		$unit = strtolower(substr($memory_limit, -1));
		$value = (int) substr($memory_limit, 0, -1);
		
		switch ($unit) {
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
	 * Check if memory usage is within safe limits.
	 *
	 * @return bool True if memory usage is safe.
	 */
	public static function is_memory_safe() {
		$memory_limit = self::get_memory_limit();
		$current_memory = memory_get_usage(true);
		$memory_usage_percentage = ($current_memory / $memory_limit) * 100;
		
		// Keep memory usage under 80% to be safe
		return $memory_usage_percentage < 80;
	}

	/**
	 * Get memory usage information.
	 *
	 * @return array Memory usage data.
	 */
	public static function get_memory_info() {
		$memory_limit = self::get_memory_limit();
		$current_memory = memory_get_usage(true);
		$peak_memory = memory_get_peak_usage(true);
		$available_memory = $memory_limit - $current_memory;
		
		return array(
			'limit' => $memory_limit,
			'current' => $current_memory,
			'peak' => $peak_memory,
			'available' => $available_memory,
			'usage_percentage' => ($current_memory / $memory_limit) * 100,
			'limit_mb' => round($memory_limit / 1024 / 1024, 2),
			'current_mb' => round($current_memory / 1024 / 1024, 2),
			'peak_mb' => round($peak_memory / 1024 / 1024, 2),
			'available_mb' => round($available_memory / 1024 / 1024, 2),
		);
	}

	/**
	 * Force garbage collection to free memory.
	 *
	 * @return bool True if garbage collection was successful.
	 */
	public static function force_garbage_collection() {
		if (function_exists('gc_collect_cycles')) {
			$collected = gc_collect_cycles();
			return $collected > 0;
		}
		return false;
	}

	/**
	 * Check if caching is enabled.
	 *
	 * @return bool Whether caching is enabled.
	 */
	public static function is_caching_enabled() {
		return ! defined( 'HUMANITIX_DISABLE_CACHING' ) || HUMANITIX_DISABLE_CACHING;
	}

	/**
	 * Check if logging to file is enabled.
	 *
	 * @return bool Whether file logging is enabled.
	 */
	public static function is_file_logging_enabled() {
		return ! defined( 'HUMANITIX_DISABLE_FILE_LOGGING' ) || HUMANITIX_DISABLE_FILE_LOGGING;
	}

	/**
	 * Get recommended settings for high-performance imports.
	 *
	 * @return array Recommended settings.
	 */
	public static function get_high_performance_settings() {
		return array(
			'HUMANITIX_OPTIMIZED_LOGGING'     => true,
			'HUMANITIX_ENABLE_IMAGE_DOWNLOAD' => false,
			'HUMANITIX_BATCH_SIZE'            => 50,
			'HUMANITIX_DISABLE_CACHING'       => false,
			'HUMANITIX_DISABLE_FILE_LOGGING'  => false,
		);
	}

	/**
	 * Get recommended settings for debugging.
	 *
	 * @return array Recommended settings.
	 */
	public static function get_debug_settings() {
		return array(
			'HUMANITIX_OPTIMIZED_LOGGING'     => false,
			'HUMANITIX_ENABLE_IMAGE_DOWNLOAD' => true,
			'HUMANITIX_BATCH_SIZE'            => 5,
			'HUMANITIX_DISABLE_CACHING'       => false,
			'HUMANITIX_DISABLE_FILE_LOGGING'  => false,
			'HUMANITIX_MEMORY_TARGET'         => 128, // 128MB target
		);
	}

	/**
	 * Get recommended settings for 128MB memory target.
	 *
	 * @return array Recommended settings.
	 */
	public static function get_128mb_settings() {
		return array(
			'HUMANITIX_OPTIMIZED_LOGGING'     => true,
			'HUMANITIX_ENABLE_IMAGE_DOWNLOAD' => false,
			'HUMANITIX_BATCH_SIZE'            => 5,
			'HUMANITIX_DISABLE_CACHING'       => false,
			'HUMANITIX_DISABLE_FILE_LOGGING'  => false,
			'HUMANITIX_MEMORY_TARGET'         => 128, // 128MB target
		);
	}

	/**
	 * Check if data structure optimization is enabled.
	 *
	 * @return bool Whether data structure optimization is enabled.
	 */
	public static function is_data_optimization_enabled() {
		return ! defined( 'HUMANITIX_DISABLE_DATA_OPTIMIZATION' ) || HUMANITIX_DISABLE_DATA_OPTIMIZATION;
	}

	/**
	 * Get maximum string length for truncation.
	 *
	 * @return int Maximum string length.
	 */
	public static function get_max_string_length() {
		return defined( 'HUMANITIX_MAX_STRING_LENGTH' ) ? HUMANITIX_MAX_STRING_LENGTH : 255;
	}

	/**
	 * Get maximum array size for processing.
	 *
	 * @return int Maximum array size.
	 */
	public static function get_max_array_size() {
		return defined( 'HUMANITIX_MAX_ARRAY_SIZE' ) ? HUMANITIX_MAX_ARRAY_SIZE : 50;
	}

	/**
	 * Get maximum ticket types to process.
	 *
	 * @return int Maximum ticket types.
	 */
	public static function get_max_ticket_types() {
		return defined( 'HUMANITIX_MAX_TICKET_TYPES' ) ? HUMANITIX_MAX_TICKET_TYPES : 10;
	}

	/**
	 * Check if memory monitoring is enabled.
	 *
	 * @return bool Whether memory monitoring is enabled.
	 */
	public static function is_memory_monitoring_enabled() {
		return ! defined( 'HUMANITIX_DISABLE_MEMORY_MONITORING' ) || HUMANITIX_DISABLE_MEMORY_MONITORING;
	}

	/**
	 * Get data structure optimization settings.
	 *
	 * @return array Optimization settings.
	 */
	public static function get_data_optimization_settings() {
		return array(
			'enabled' => self::is_data_optimization_enabled(),
			'max_string_length' => self::get_max_string_length(),
			'max_array_size' => self::get_max_array_size(),
			'max_ticket_types' => self::get_max_ticket_types(),
			'memory_monitoring' => self::is_memory_monitoring_enabled(),
		);
	}

	/**
	 * Get memory usage statistics for data processing.
	 *
	 * @return array Memory statistics.
	 */
	public static function get_data_processing_memory_stats() {
		$memory_info = self::get_memory_info();
		
		return array(
			'current_mb' => $memory_info['current_mb'],
			'peak_mb' => $memory_info['peak_mb'],
			'available_mb' => $memory_info['available_mb'],
			'usage_percentage' => $memory_info['usage_percentage'],
			'is_safe' => self::is_memory_safe(),
			'optimization_enabled' => self::is_data_optimization_enabled(),
		);
	}

	/**
	 * Check if data processing should be optimized based on memory usage.
	 *
	 * @return bool True if optimization is needed.
	 */
	public static function should_optimize_data_processing() {
		if ( ! self::is_data_optimization_enabled() ) {
			return false;
		}

		$memory_info = self::get_memory_info();
		$memory_usage_percentage = $memory_info['usage_percentage'];

		// Optimize if memory usage is above 70%
		return $memory_usage_percentage > 70;
	}

	/**
	 * Get adaptive batch size based on data complexity and memory.
	 *
	 * @param int $total_events Total number of events.
	 * @param array $memory_stats Memory statistics.
	 * @return int Adaptive batch size.
	 */
	public static function get_adaptive_batch_size( $total_events = 0, $memory_stats = array() ) {
		$base_batch_size = self::get_dynamic_batch_size( $total_events );
		
		if ( empty( $memory_stats ) ) {
			$memory_stats = self::get_data_processing_memory_stats();
		}

		// Reduce batch size if memory usage is high
		if ( $memory_stats['usage_percentage'] > 80 ) {
			return max( 1, floor( $base_batch_size * 0.5 ) );
		} elseif ( $memory_stats['usage_percentage'] > 60 ) {
			return max( 1, floor( $base_batch_size * 0.75 ) );
		}

		return $base_batch_size;
	}

	/**
	 * Get recommended data structure settings for current memory conditions.
	 *
	 * @return array Recommended settings.
	 */
	public static function get_recommended_data_settings() {
		$memory_stats = self::get_data_processing_memory_stats();
		
		$settings = array(
			'max_string_length' => 255,
			'max_array_size' => 50,
			'max_ticket_types' => 10,
		);

		// Adjust settings based on memory usage
		if ( $memory_stats['usage_percentage'] > 80 ) {
			$settings['max_string_length'] = 100;
			$settings['max_array_size'] = 20;
			$settings['max_ticket_types'] = 5;
		} elseif ( $memory_stats['usage_percentage'] > 60 ) {
			$settings['max_string_length'] = 150;
			$settings['max_array_size'] = 30;
			$settings['max_ticket_types'] = 7;
		}

		return $settings;
	}
}
