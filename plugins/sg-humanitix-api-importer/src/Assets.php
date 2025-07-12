<?php
/**
 * Assets Class.
 *
 * @package SG\HumanitixApiImporter
 */

namespace SG\HumanitixApiImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Assets.
 */
class Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize.
	 */
	private function init() {
		// Core asset hooks.
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
	}

	/**
	 * Enqueue editor assets.
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = SG_HUMANITIX_API_IMPORTER_PLUGIN_BUILD_PATH . '/editor.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset_data = include $asset_file;

			wp_enqueue_script(
				'sg-humanitix-api-importer-editor-script',
				SG_HUMANITIX_API_IMPORTER_PLUGIN_BUILD_URL . '/editor.js',
				$asset_data['dependencies'] ?? array(),
				$asset_data['version'] ?? SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION,
				false
			);

			wp_enqueue_style(
				'sg-humanitix-api-importer-editor-style',
				SG_HUMANITIX_API_IMPORTER_PLUGIN_BUILD_URL . '/editor.css',
				array(),
				$asset_data['version'] ?? SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION
			);
		}
	}

	/**
	 * Enqueue frontend scripts.
	 */
	public function enqueue_frontend_scripts(): void {
		$asset_file = SG_HUMANITIX_API_IMPORTER_PLUGIN_BUILD_PATH . '/frontend.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset_data = include $asset_file;

			wp_enqueue_script(
				'sg-humanitix-api-importer-frontend-script',
				SG_HUMANITIX_API_IMPORTER_PLUGIN_BUILD_URL . '/frontend.js',
				$asset_data['dependencies'] ?? array(),
				$asset_data['version'] ?? SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION,
				true
			);

			wp_enqueue_style(
				'sg-humanitix-api-importer-frontend-style',
				SG_HUMANITIX_API_IMPORTER_PLUGIN_BUILD_URL . '/frontend.css',
				array(),
				$asset_data['version'] ?? SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION
			);
		}

		// Localize script with plugin data.
		$this->localize_scripts();
	}

	/**
	 * Localize scripts with plugin data.
	 */
	private function localize_scripts() {
		wp_localize_script(
			'sg-humanitix-api-importer-frontend-script',
			'sgHumanitixApiImporter',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'sg-humanitix-api-importer_nonce' ),
				'plugin_url' => SG_HUMANITIX_API_IMPORTER_PLUGIN_URL,
				'version'    => SG_HUMANITIX_API_IMPORTER_PLUGIN_VERSION,
			)
		);
	}
}
