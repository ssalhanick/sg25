<?php
/**
 * Template Manager Class.
 *
 * Handles all TEC template customizations and overrides.
 * Serves as the main entry point for the template module.
 *
 * @package SG\HumanitixApiImporter\Templates
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Templates;

use SG\HumanitixApiImporter\Admin\Logger;
use SG\HumanitixApiImporter\Templates\Hooks\TemplateHooks;
use SG\HumanitixApiImporter\Templates\Assets\TemplateAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Template Manager Class.
 *
 * Manages all TEC template customizations and overrides.
 *
 * @package SG\HumanitixApiImporter\Templates
 * @since 1.0.0
 */
class TemplateManager {

	/**
	 * The template manager instance.
	 *
	 * @var TemplateManager
	 */
	private static $instance = null;

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * The template hooks instance.
	 *
	 * @var TemplateHooks
	 */
	private $hooks;

	/**
	 * The template assets instance.
	 *
	 * @var TemplateAssets
	 */
	private $assets;

	/**
	 * Whether TEC is active.
	 *
	 * @var bool
	 */
	private $tec_active = false;

	/**
	 * Get the template manager instance.
	 *
	 * @since 1.0.0
	 * @return TemplateManager|null The template manager instance or null if not initialized.
	 */
	public static function get_instance() {
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Initializes the template manager and sets up all necessary components.
	 *
	 * @since 1.0.0
	 * @param Logger $logger The logger instance.
	 */
	public function __construct( Logger $logger ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[sg-humanitix-api-importer] TemplateManager constructor called' );
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
		// Check if TEC is active.
		$this->check_tec_availability();

		// Only initialize if TEC is active.
		if ( $this->tec_active ) {
			$this->init_components();
			$this->init_hooks();
		} else {
			$this->logger->log(
				'info',
				'TEC not active - template module not initialized',
				array( 'module' => 'templates' )
			);
		}
	}

	/**
	 * Check if TEC is available and active.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function check_tec_availability() {
		// Check if TEC plugin is active.
		$this->tec_active = class_exists( 'Tribe__Events__Main' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[sg-humanitix-api-importer] TEC active: ' . ( $this->tec_active ? 'true' : 'false' ) );
		}
	}

	/**
	 * Initialize template components.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_components() {
		// Initialize template hooks.
		$this->hooks = new TemplateHooks( $this->logger );

		// Initialize template assets.
		$this->assets = new TemplateAssets( $this->logger );

		$this->logger->log(
			'info',
			'Template components initialized',
			array( 'module' => 'templates' )
		);
	}

	/**
	 * Initialize WordPress hooks for template functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		// Hook into TEC template system.
		add_filter( 'tribe_events_template_paths', array( $this, 'add_template_paths' ) );
		add_filter( 'tribe_events_template_part_path', array( $this, 'add_template_part_paths' ), 10, 3 );

		// Hook into TEC content filters.
		add_filter( 'tribe_events_single_event_title', array( $this, 'modify_event_title' ) );
		add_filter( 'tribe_events_single_event_meta', array( $this, 'modify_event_meta' ) );

		// Hook into TEC page detection.
		add_action( 'wp', array( $this, 'check_tec_pages' ) );

		$this->logger->log(
			'info',
			'Template hooks initialized',
			array( 'module' => 'templates' )
		);
	}

	/**
	 * Add custom template paths to TEC.
	 *
	 * @since 1.0.0
	 * @param array $paths The existing template paths.
	 * @return array Modified template paths.
	 */
	public function add_template_paths( $paths ) {
		$custom_path = SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH . '/src/Templates/Overrides';
		array_unshift( $paths, $custom_path );

		$this->logger->log(
			'debug',
			'Added custom template path',
			array(
				'module' => 'templates',
				'path'   => $custom_path,
			)
		);

		return $paths;
	}

	/**
	 * Add custom template part paths to TEC.
	 *
	 * @since 1.0.0
	 * @param string $path The template part path.
	 * @param string $template The template name.
	 * @param string $file The file name.
	 * @return string Modified template part path.
	 */
	public function add_template_part_paths( $path, $template, $file ) {
		$custom_path = SG_HUMANITIX_API_IMPORTER_PLUGIN_PATH . '/src/Templates/Overrides/' . $file;

		if ( file_exists( $custom_path ) ) {
			$this->logger->log(
				'debug',
				'Using custom template part',
				array(
					'module' => 'templates',
					'file'   => $file,
					'path'   => $custom_path,
				)
			);

			return $custom_path;
		}

		return $path;
	}

	/**
	 * Modify event title.
	 *
	 * @since 1.0.0
	 * @param string $title The event title.
	 * @return string Modified event title.
	 */
	public function modify_event_title( $title ) {
		// Add custom title modifications here.
		return $title;
	}

	/**
	 * Modify event meta.
	 *
	 * @since 1.0.0
	 * @param array $meta The event meta.
	 * @return array Modified event meta.
	 */
	public function modify_event_meta( $meta ) {
		// Add custom meta modifications here.
		return $meta;
	}

	/**
	 * Check if current page is a TEC page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function check_tec_pages() {
		if ( function_exists( 'tribe_is_event_query' ) && tribe_is_event_query() ) {
			$this->logger->log(
				'debug',
				'TEC page detected',
				array(
					'module' => 'templates',
					'url'    => $_SERVER['REQUEST_URI'] ?? '',
				)
			);
		}
	}

	/**
	 * Get whether TEC is active.
	 *
	 * @since 1.0.0
	 * @return bool True if TEC is active, false otherwise.
	 */
	public function is_tec_active() {
		return $this->tec_active;
	}

	/**
	 * Get the template hooks instance.
	 *
	 * @since 1.0.0
	 * @return TemplateHooks The template hooks instance.
	 */
	public function get_hooks() {
		return $this->hooks;
	}

	/**
	 * Get the template assets instance.
	 *
	 * @since 1.0.0
	 * @return TemplateAssets The template assets instance.
	 */
	public function get_assets() {
		return $this->assets;
	}
} 