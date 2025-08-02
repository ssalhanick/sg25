<?php
/**
 * Template Assets Class.
 *
 * Handles all CSS and JS assets for TEC template customizations.
 *
 * @package SG\HumanitixApiImporter\Templates\Assets
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Templates\Assets;

use SG\HumanitixApiImporter\Admin\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Template Assets Class.
 *
 * Manages all CSS and JS assets for TEC template customizations.
 *
 * @package SG\HumanitixApiImporter\Templates\Assets
 * @since 1.0.0
 */
class TemplateAssets {

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
	 * Whether TEC is active.
	 *
	 * @var bool
	 */
	private $tec_active = false;

	/**
	 * Constructor.
	 *
	 * Initializes the template assets and sets up asset loading.
	 *
	 * @since 1.0.0
	 * @param Logger $logger The logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->check_tec_availability();
		$this->init_assets();
	}

	/**
	 * Check if TEC is available.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function check_tec_availability() {
		// Check if TEC plugin is active.
		$this->tec_active = class_exists( 'Tribe__Events__Main' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[sg-humanitix-api-importer] TemplateAssets: TEC active: ' . ( $this->tec_active ? 'true' : 'false' ) );
		}
	}

	/**
	 * Initialize asset loading.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_assets() {
		// Only initialize if TEC is active.
		if ( ! $this->tec_active ) {
			$this->logger->log(
				'info',
				'Template assets not initialized - TEC not active',
				array( 'module' => 'templates', 'component' => 'assets' )
			);
			return;
		}

		// Hook into WordPress asset loading.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_template_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Hook into TEC-specific asset loading.
		add_action( 'tribe_events_before_html', array( $this, 'check_tec_page' ) );

		$this->logger->log(
			'info',
			'Template assets initialized',
			array( 'module' => 'templates', 'component' => 'assets' )
		);
	}

	/**
	 * Enqueue template assets for frontend.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_template_assets() {
		// Only enqueue if TEC is active.
		if ( ! $this->tec_active ) {
			return;
		}

		// Only enqueue on TEC pages.
		if ( ! $this->is_tec_page() ) {
			return;
		}

		// Enqueue CSS.
		$this->enqueue_template_css();

		// Enqueue JS.
		$this->enqueue_template_js();

		$this->assets_enqueued = true;

		$this->logger->log(
			'debug',
			'Template assets enqueued for frontend',
			array( 'module' => 'templates', 'component' => 'assets' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_admin_assets() {
		// Only enqueue if TEC is active.
		if ( ! $this->tec_active ) {
			return;
		}

		// Only enqueue on TEC admin pages.
		if ( ! $this->is_tec_admin_page() ) {
			return;
		}

		// Enqueue admin CSS.
		$this->enqueue_admin_css();

		// Enqueue admin JS.
		$this->enqueue_admin_js();

		$this->logger->log(
			'debug',
			'Template assets enqueued for admin',
			array( 'module' => 'templates', 'component' => 'assets' )
		);
	}

	/**
	 * Check if current page is a TEC page.
	 *
	 * @since 1.0.0
	 * @return bool True if TEC page, false otherwise.
	 */
	private function is_tec_page() {
		// Check if TEC functions are available.
		if ( ! function_exists( 'tribe_is_event_query' ) ) {
			return false;
		}

		// Check various TEC page conditions with function existence checks.
		return (
			( function_exists( 'tribe_is_event_query' ) && tribe_is_event_query() ) ||
			( function_exists( 'tribe_is_event' ) && tribe_is_event() ) ||
			( function_exists( 'tribe_is_event_category' ) && tribe_is_event_category() ) ||
			( function_exists( 'tribe_is_event_venue' ) && tribe_is_event_venue() ) ||
			( function_exists( 'tribe_is_event_organizer' ) && tribe_is_event_organizer() ) ||
			( function_exists( 'tribe_is_month' ) && tribe_is_month() ) ||
			( function_exists( 'tribe_is_list_view' ) && tribe_is_list_view() ) ||
			( function_exists( 'tribe_is_day' ) && tribe_is_day() ) ||
			( function_exists( 'tribe_is_week' ) && tribe_is_week() ) ||
			( function_exists( 'tribe_is_map_view' ) && tribe_is_map_view() ) ||
			( function_exists( 'tribe_is_photo_view' ) && tribe_is_photo_view() )
		);
	}

	/**
	 * Check if current page is a TEC admin page.
	 *
	 * @since 1.0.0
	 * @return bool True if TEC admin page, false otherwise.
	 */
	private function is_tec_admin_page() {
		global $pagenow, $post_type;

		// Check if we're on a TEC admin page.
		return (
			( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) &&
			'tribe_events' === $post_type
		) || (
			'edit.php' === $pagenow &&
			'tribe_events' === $post_type
		);
	}

	/**
	 * Enqueue template CSS.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function enqueue_template_css() {
		$css_file = 'tec_templates.css';
		$css_path = SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH . '/assets/build/css/' . $css_file;
		$css_url  = SG_HUMANITIX_API_IMPORTER_PLUGIN_URL . '/assets/build/css/' . $css_file;

		// Only enqueue if file exists.
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'sg-humanitix-template-styles',
				$css_url,
				array(),
				SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION,
				'all'
			);

			$this->logger->log(
				'debug',
				'Template CSS enqueued',
				array(
					'module' => 'templates',
					'component' => 'assets',
					'file' => $css_file,
				)
			);
		} else {
			$this->logger->log(
				'warning',
				'Template CSS file not found',
				array(
					'module' => 'templates',
					'component' => 'assets',
					'path' => $css_path,
				)
			);
		}
	}

	/**
	 * Enqueue template JS.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function enqueue_template_js() {
		$js_file = 'templates.js';
		$js_path = SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH . '/src/Templates/Assets/js/' . $js_file;
		$js_url  = SG_HUMANITIX_API_IMPORTER_PLUGIN_URL . '/src/Templates/Assets/js/' . $js_file;

		// Only enqueue if file exists.
		if ( file_exists( $js_path ) ) {
			wp_enqueue_script(
				'sg-humanitix-template-scripts',
				$js_url,
				array( 'jquery' ),
				SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION,
				true
			);

			// Localize script with data.
			wp_localize_script(
				'sg-humanitix-template-scripts',
				'sgHumanitixTemplate',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'sg-humanitix-template-nonce' ),
				)
			);

			$this->logger->log(
				'debug',
				'Template JS enqueued',
				array(
					'module' => 'templates',
					'component' => 'assets',
					'file' => $js_file,
				)
			);
		} else {
			$this->logger->log(
				'warning',
				'Template JS file not found',
				array(
					'module' => 'templates',
					'component' => 'assets',
					'path' => $js_path,
				)
			);
		}
	}

	/**
	 * Enqueue admin CSS.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function enqueue_admin_css() {
		$css_file = 'templates-admin.css';
		$css_path = SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH . '/src/Templates/Assets/css/' . $css_file;
		$css_url  = SG_HUMANITIX_API_IMPORTER_PLUGIN_URL . '/src/Templates/Assets/css/' . $css_file;

		// Only enqueue if file exists.
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'sg-humanitix-template-admin-styles',
				$css_url,
				array(),
				SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION,
				'all'
			);

			$this->logger->log(
				'debug',
				'Template admin CSS enqueued',
				array(
					'module' => 'templates',
					'component' => 'assets',
					'file' => $css_file,
				)
			);
		}
	}

	/**
	 * Enqueue admin JS.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function enqueue_admin_js() {
		$js_file = 'templates-admin.js';
		$js_path = SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH . '/src/Templates/Assets/js/' . $js_file;
		$js_url  = SG_HUMANITIX_API_IMPORTER_PLUGIN_URL . '/src/Templates/Assets/js/' . $js_file;

		// Only enqueue if file exists.
		if ( file_exists( $js_path ) ) {
			wp_enqueue_script(
				'sg-humanitix-template-admin-scripts',
				$js_url,
				array( 'jquery' ),
				SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION,
				true
			);

			// Localize script with data.
			wp_localize_script(
				'sg-humanitix-template-admin-scripts',
				'sgHumanitixTemplateAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'sg-humanitix-template-admin-nonce' ),
				)
			);

			$this->logger->log(
				'debug',
				'Template admin JS enqueued',
				array(
					'module' => 'templates',
					'component' => 'assets',
					'file' => $js_file,
				)
			);
		}
	}

	/**
	 * Check if we're on a TEC page and log it.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function check_tec_page() {
		if ( $this->is_tec_page() && ! $this->assets_enqueued ) {
			$this->logger->log(
				'debug',
				'TEC page detected - assets should be loaded',
				array(
					'module' => 'templates',
					'component' => 'assets',
					'url' => $_SERVER['REQUEST_URI'] ?? '',
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

	/**
	 * Check if TEC is active.
	 *
	 * @since 1.0.0
	 * @return bool True if TEC is active, false otherwise.
	 */
	public function is_tec_active() {
		return $this->tec_active;
	}
} 