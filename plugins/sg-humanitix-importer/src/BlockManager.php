<?php
/**
 * Block Manager Class.
 *
 * @package SG\HumanitixImporter
 */

namespace SG\HumanitixImporter;

use Utd\SharedCore\BlockManager as SharedBlockManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class BlockManager.
 *
 * Extends the shared core BlockManager and handles block registration and management for the plugin.
 */
class BlockManager extends SharedBlockManager {

	/**
	 * List of custom blocks to register
	 *
	 * @var array
	 */
	private $custom_blocks = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->init_custom_blocks();
	}

	/**
	 * Initialize custom block management.
	 */
	private function init_custom_blocks() {
		// Hook into WordPress block registration.
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'acf/init', array( $this, 'register_acf_blocks' ) );
		add_action( 'init', array( $this, 'register_block_categories' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		
		// Register our blocks with shared core for whitelisting.
		add_filter( 'utd_shared_core_allowed_blocks', array( $this, 'register_blocks_with_core' ) );
	}

	/**
	 * Register custom blocks.
	 */
	public function register_blocks() {
		// Register native Gutenberg blocks.
		if ( function_exists( 'register_block_type' ) ) {
			foreach ( $this->custom_blocks as $block_name => $block_config ) {
				register_block_type( $block_name, $block_config );
			}
		}

		// Log block registration for debugging.
		if ( WP_DEBUG ) {
			error_log( '[sg-humanitix-importer] Registered ' . count( $this->custom_blocks ) . ' custom blocks' );
		}
	}

	/**
	 * Register ACF blocks.
	 */
	public function register_acf_blocks() {
		// Check if ACF function exists.
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return;
		}

		$blocks_path = SG_HUMANITIX_IMPORTER_PLUGIN_PATH . '/blocks/acf';
		
		// Get all block directories.
		$block_dirs = $this->get_block_directories( $blocks_path );

		foreach ( $block_dirs as $block_dir ) {
			$register_file = $blocks_path . '/' . $block_dir . '/register.php';
			
			if ( file_exists( $register_file ) ) {
				require_once $register_file;
			}
		}

		// Log ACF block registration for debugging.
		if ( WP_DEBUG ) {
			error_log( '[sg-humanitix-importer] Registered ' . count( $block_dirs ) . ' ACF blocks from ' . $blocks_path );
		}
	}

	/**
	 * Register custom block categories.
	 */
	public function register_block_categories( $categories ) {
		return array_merge(
			array(
				array(
					'slug'  => 'sg-humanitix-importer-custom',
					'title' => __( 'Humanitix Importer Custom Blocks', 'sg-humanitix-importer' ),
				),
				array(
					'slug'  => 'sg-humanitix-importer-patterns',
					'title' => __( 'Humanitix Importer Patterns', 'sg-humanitix-importer' ),
				),
			),
			$categories
		);
	}

	/**
	 * Get block directories from a path.
	 *
	 * @param string $path The path to scan.
	 * @return array Array of directory names.
	 */
	private function get_block_directories( $path ) {
		if ( ! is_dir( $path ) ) {
			return array();
		}

		$directories = array();
		$items = scandir( $path );

		foreach ( $items as $item ) {
			if ( $item !== '.' && $item !== '..' && is_dir( $path . '/' . $item ) ) {
				$directories[] = $item;
			}
		}

		return $directories;
	}

	/**
	 * Enqueue block assets.
	 */
	public function enqueue_block_assets() {
		// This will be called on both frontend and editor.
		// Enqueue assets that are needed on both.
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_block_editor_assets() {
		// Enqueue block editor styles and scripts.
		$asset_file = SG_HUMANITIX_IMPORTER_PLUGIN_BUILD_PATH . '/editor.asset.php';
		
		if ( file_exists( $asset_file ) ) {
			$asset_data = include $asset_file;
			
			wp_enqueue_script(
				'sg-humanitix-importer-blocks',
				SG_HUMANITIX_IMPORTER_PLUGIN_BUILD_URL . '/js/blocks.js',
				$asset_data['dependencies'] ?? array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor' ),
				$asset_data['version'] ?? SG_HUMANITIX_IMPORTER_PLUGIN_VERSION,
				false
			);
			
			wp_enqueue_style(
				'sg-humanitix-importer-blocks-editor',
				SG_HUMANITIX_IMPORTER_PLUGIN_BUILD_URL . '/css/editor.css',
				array(),
				$asset_data['version'] ?? SG_HUMANITIX_IMPORTER_PLUGIN_VERSION
			);
		}
	}

	/**
	 * Get template path for a block.
	 *
	 * @param string $block_name The block name.
	 * @param string $template_name The template file name.
	 * @return string The full template path.
	 */
	public function get_block_template_path( $block_name, $template_name = 'content.php' ) {
		return SG_HUMANITIX_IMPORTER_PLUGIN_PATH . '/blocks/acf/' . $block_name . '/' . $template_name;
	}

	/**
	 * Enqueue block-specific assets.
	 *
	 * @param string $block_name The block name.
	 * @param array $dependencies Array of script dependencies.
	 */
	public function enqueue_block_style( $block_name, $dependencies = array() ) {
		$style_path = SG_HUMANITIX_IMPORTER_PLUGIN_BUILD_URL . '/css/blocks/' . $block_name . '.css';
		$script_path = SG_HUMANITIX_IMPORTER_PLUGIN_BUILD_URL . '/js/blocks/' . $block_name . '.js';

		// Enqueue style if it exists.
		if ( file_exists( SG_HUMANITIX_IMPORTER_PLUGIN_PATH . '/assets/build/css/blocks/' . $block_name . '.css' ) ) {
			wp_enqueue_style(
				'sg-humanitix-importer-block-' . $block_name,
				$style_path,
				array(),
				SG_HUMANITIX_IMPORTER_PLUGIN_VERSION
			);
		}

		// Enqueue script if it exists.
		if ( file_exists( SG_HUMANITIX_IMPORTER_PLUGIN_PATH . '/assets/build/js/blocks/' . $block_name . '.js' ) ) {
			wp_enqueue_script(
				'sg-humanitix-importer-block-' . $block_name,
				$script_path,
				array_merge( array( 'jquery' ), $dependencies ),
				SG_HUMANITIX_IMPORTER_PLUGIN_VERSION,
				true
			);
		}
	}

	/**
	 * Register a custom block.
	 *
	 * @param string $block_name Block name (e.g., 'my-plugin/my-block').
	 * @param array  $block_config Block configuration.
	 */
	public function register_block( $block_name, $block_config = array() ) {
		$defaults = array(
			'editor_script'   => 'sg-humanitix-importer-blocks',
			'editor_style'    => 'sg-humanitix-importer-blocks-editor',
			'style'           => 'sg-humanitix-importer-blocks-frontend',
			'render_callback' => null,
			'attributes'      => array(),
			'category'        => 'sg-humanitix-importer',
			'supports'        => array(),
		);

		$this->custom_blocks[ $block_name ] = wp_parse_args( $block_config, $defaults );
	}

	/**
	 * Block category registration helper.
	 *
	 * @param string $slug Category slug.
	 * @param string $title Category title.
	 * @param string $icon Category icon.
	 */
	public function register_block_category( $slug, $title, $icon = 'layout' ) {
		add_filter( 'block_categories_all', function( $categories ) use ( $slug, $title, $icon ) {
			return array_merge(
				$categories,
				array(
					array(
						'slug'  => $slug,
						'title' => $title,
						'icon'  => $icon,
					),
				)
			);
		} );
	}

	/**
	 * Create block render callback helper.
	 *
	 * @param string $template_name Template name (without .php extension).
	 * @param string $template_dir Template directory relative to plugin root.
	 * @return callable Render callback function.
	 */
	public function create_render_callback( $template_name, $template_dir = 'blocks/acf' ) {
		return function( $attributes, $content = '', $block = null ) use ( $template_name, $template_dir ) {
			$template_path = SG_HUMANITIX_IMPORTER_PLUGIN_PATH . '/' . $template_dir . '/' . $template_name . '/content.php';
			
			if ( ! file_exists( $template_path ) ) {
				return '<p>Template not found: ' . esc_html( $template_name ) . '</p>';
			}

			ob_start();
			include $template_path;
			return ob_get_clean();
		};
	}

	/**
	 * Create ACF block registration helper.
	 *
	 * @param string $block_name Block name.
	 * @param array $block_args Block arguments.
	 * @return bool Whether the block was registered successfully.
	 */
	public function register_acf_block( $block_name, $block_args = array() ) {
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return false;
		}

		$defaults = array(
			'name'              => $block_name,
			'title'             => ucwords( str_replace( array( '-', '_' ), ' ', $block_name ) ),
			'description'       => __( 'A custom block created with ACF.', 'sg-humanitix-importer' ),
			'render_template'   => $this->get_block_template_path( $block_name ),
			'category'          => 'sg-humanitix-importer-custom',
			'icon'              => 'admin-customizer',
			'keywords'          => array( 'custom', $block_name ),
			'supports'          => array(
				'align' => array( 'left', 'right', 'center', 'wide', 'full' ),
				'jsx'   => true,
			),
		);

		$block_config = wp_parse_args( $block_args, $defaults );
		
		acf_register_block_type( $block_config );
		
		return true;
	}

	/**
	 * Register blocks with shared core for whitelisting.
	 *
	 * @param array $allowed_blocks Array of allowed blocks.
	 * @return array Modified array of allowed blocks.
	 */
	public function register_blocks_with_core( $allowed_blocks ) {
		// Get plugin-specific blocks to register with core.
		$plugin_blocks = $this->get_plugin_blocks_for_core();
		
		return array_merge( $allowed_blocks, $plugin_blocks );
	}

	/**
	 * Get blocks this plugin wants to register with shared core.
	 *
	 * @return array Array of block names.
	 */
	protected function get_plugin_blocks_for_core() {
		$blocks = array();
		
		// Add Gutenberg blocks registered by this plugin.
		$blocks = array_merge( $blocks, array_keys( $this->custom_blocks ) );
		
		// Add ACF blocks (discovered from filesystem).
		$acf_blocks_path = SG_HUMANITIX_IMPORTER_PLUGIN_PATH . '/blocks/acf';
		$acf_block_dirs = $this->get_block_directories( $acf_blocks_path );
		
		foreach ( $acf_block_dirs as $block_dir ) {
			$blocks[] = 'acf/' . $block_dir;
		}
		
		return $blocks;
	}

	/**
	 * Get registered custom blocks.
	 *
	 * @return array Array of registered custom blocks.
	 */
	public function get_custom_blocks() {
		return $this->custom_blocks;
	}
} 