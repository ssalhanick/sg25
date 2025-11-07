<?php
/**
 * Eventbrite Template Assets Class.
 *
 * Handles all CSS and JS assets for course template customizations.
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
 * Eventbrite Template Assets Class.
 *
 * Manages all CSS and JS assets for course template customizations.
 *
 * @package SG\EventbriteCourseImporter\Templates
 * @since 1.0.0
 */
class EventbriteTemplateAssets {

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Whether assets have been enqueued.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Logger $logger The logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->init();
	}

	/**
	 * Initialize the template assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init() {
		// Hook into WordPress
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		// Only enqueue on course pages.
		if ( ! $this->is_course_page() ) {
			return;
		}

		// Enqueue course CSS.
		$this->enqueue_course_css();

		$this->assets_enqueued = true;

		$this->logger->log(
			'debug',
			'Course template assets enqueued for frontend',
			array( 'module' => 'templates', 'component' => 'assets' )
		);
	}

	/**
	 * Check if current page is a course page.
	 *
	 * @since 1.0.0
	 * @return bool True if course page, false otherwise.
	 */
	private function is_course_page() {
		return is_singular( 'sg_course' );
	}

	/**
	 * Enqueue course template CSS.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function enqueue_course_css() {
		$css_file = 'course-templates.css';
		$css_path = \SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/src/Templates/Assets/css/' . $css_file;
		$css_url  = \SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL . '/src/Templates/Assets/css/' . $css_file;

		// Only enqueue if file exists.
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'sg-eventbrite-course-template-styles',
				$css_url,
				array(),
				\SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_VERSION,
				'all'
			);

			$this->logger->log(
				'debug',
				'Course template CSS enqueued',
				array(
					'module' => 'templates',
					'component' => 'assets',
					'file' => $css_file,
					'url' => $css_url,
				)
			);
		} else {
			$this->logger->log(
				'warning',
				'Course template CSS file not found',
				array(
					'module' => 'templates',
					'component' => 'assets',
					'file' => $css_file,
					'path' => $css_path,
				)
			);
		}
	}

	/**
	 * Get whether assets have been enqueued.
	 *
	 * @since 1.0.0
	 * @return bool True if assets enqueued, false otherwise.
	 */
	public function are_assets_enqueued() {
		return $this->assets_enqueued;
	}
}
