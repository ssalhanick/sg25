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
			'enable_image_download' => defined( 'HUMANITIX_ENABLE_IMAGE_DOWNLOAD' ) && HUMANITIX_ENABLE_IMAGE_DOWNLOAD,
			'batch_size'            => defined( 'HUMANITIX_BATCH_SIZE' ) ? HUMANITIX_BATCH_SIZE : 25,
			'enable_caching'        => ! defined( 'HUMANITIX_DISABLE_CACHING' ) || HUMANITIX_DISABLE_CACHING,
			'log_to_file'           => ! defined( 'HUMANITIX_DISABLE_FILE_LOGGING' ) || HUMANITIX_DISABLE_FILE_LOGGING,
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
		return defined( 'HUMANITIX_ENABLE_IMAGE_DOWNLOAD' ) && HUMANITIX_ENABLE_IMAGE_DOWNLOAD;
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
}
