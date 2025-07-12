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
		return defined( 'HUMANITIX_BATCH_SIZE' ) ? HUMANITIX_BATCH_SIZE : 25;
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
		);
	}
}
