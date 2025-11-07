<?php
/**
 * Eventbrite Template Manager Class.
 *
 * Handles course template customizations and overrides.
 * Serves as the main entry point for the template module.
 *
 * @package SG\EventbriteCourseImporter\Templates
 * @since 1.0.0
 */

namespace SG\EventbriteCourseImporter\Templates;

use SG\EventbriteCourseImporter\Admin\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Eventbrite Template Manager Class.
 *
 * Manages course template customizations and overrides.
 *
 * @package SG\EventbriteCourseImporter\Templates
 * @since 1.0.0
 */
class EventbriteTemplateManager {

	/**
	 * The template manager instance.
	 *
	 * @var EventbriteTemplateManager
	 */
	private static $instance = null;

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * The template assets instance.
	 *
	 * @var EventbriteTemplateAssets
	 */
	private $assets;

	/**
	 * Initializes the template manager and sets up all necessary components.
	 *
	 * @since 1.0.0
	 * @param Logger $logger The logger instance.
	 */
	public function __construct( Logger $logger ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[sg-eventbrite-course-importer] EventbriteTemplateManager constructor called' );
		}

		self::$instance = $this;
		$this->logger   = $logger;
		$this->init();
	}

	/**
	 * Initialize the template manager.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init() {
		// Initialize template assets
		$this->assets = new EventbriteTemplateAssets( $this->logger );

		// Hook into WordPress template system
		add_filter( 'template_include', array( $this, 'override_single_course_template' ) );
	}

	/**
	 * Get the template manager instance.
	 *
	 * @since 1.0.0
	 * @return EventbriteTemplateManager|null The template manager instance or null if not initialized.
	 */
	public static function get_instance() {
		return self::$instance;
	}

	/**
	 * Override single course template.
	 *
	 * @since 1.0.0
	 * @param string $template The original template path.
	 * @return string The modified template path.
	 */
	public function override_single_course_template( $template ) {
		if ( is_singular( 'sg_course' ) ) {
			$custom_template = \SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/src/Templates/Overrides/single-sg_course.php';
			if ( file_exists( $custom_template ) ) {
				$this->logger->log(
					'debug',
					'Overriding single course template',
					array(
						'module' => 'templates',
						'template' => $custom_template,
					)
				);
				return $custom_template;
			}
		}
		
		return $template;
	}

	/**
	 * Get the logger instance.
	 *
	 * @since 1.0.0
	 * @return Logger The logger instance.
	 */
	public function get_logger() {
		return $this->logger;
	}

	/**
	 * Get the template assets instance.
	 *
	 * @since 1.0.0
	 * @return EventbriteTemplateAssets The template assets instance.
	 */
	public function get_assets() {
		return $this->assets;
	}
}
