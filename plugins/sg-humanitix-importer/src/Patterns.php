<?php
/**
 * Patterns Class.
 *
 * @package SG\HumanitixImporter
 */

namespace SG\HumanitixImporter;

use Utd\SharedCore\Patterns as SharedPatterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Patterns.
 *
 * Extends the shared core Patterns and handles block pattern registration and management for the plugin.
 */
class Patterns extends SharedPatterns {

	/**
	 * List of custom patterns to register
	 *
	 * @var array
	 */
	private $custom_patterns = array();

	/**
	 * List of core patterns to unregister
	 *
	 * @var array
	 */
	private $blocked_patterns = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->init_custom_patterns();
	}

	/**
	 * Initialize custom patterns management.
	 */
	private function init_custom_patterns() {
		// Register custom patterns.
		add_action( 'init', array( $this, 'register_pattern_categories' ) );
		add_action( 'init', array( $this, 'register_custom_patterns' ) );

		// Unregister unwanted patterns.
		add_action( 'init', array( $this, 'unregister_blocked_patterns' ) );

		// Filter available patterns (optional).
		add_filter( 'block_editor_settings_all', array( $this, 'filter_block_patterns' ) );
	}

	/**
	 * Register pattern categories.
	 */
	public function register_pattern_categories() {
		register_block_pattern_category(
			'sg-humanitix-importer-patterns',
			array(
				'label' => __( 'Humanitix Importer Patterns', 'sg-humanitix-importer' ),
			)
		);
	}

	/**
	 * Register custom block patterns.
	 */
	public function register_custom_patterns() {
		// Define default patterns.
		$this->define_default_patterns();

		// Load patterns from files.
		$this->load_pattern_files();

		// Register each custom pattern.
		foreach ( $this->custom_patterns as $pattern_name => $pattern_data ) {
			$this->register_pattern( $pattern_name, $pattern_data );
		}
	}

	/**
	 * Load pattern files from the patterns directory.
	 */
	private function load_pattern_files() {
		$patterns_path = SG_HUMANITIX_IMPORTER_PLUGIN_PATH . '/patterns';
		
		if ( ! is_dir( $patterns_path ) ) {
			return;
		}

		$pattern_files = glob( $patterns_path . '/*.php' );

		foreach ( $pattern_files as $pattern_file ) {
			if ( is_readable( $pattern_file ) ) {
				require_once $pattern_file;
			}
		}
	}

	/**
	 * Define default patterns for the plugin.
	 */
	private function define_default_patterns() {
		// Example: Hero section pattern.
		$this->custom_patterns['sg-humanitix-importer/hero-section'] = array(
			'title'       => __( 'Humanitix Importer Hero Section', 'sg-humanitix-importer' ),
			'description' => __( 'A hero section with heading, text, and call-to-action button.', 'sg-humanitix-importer' ),
			'categories'  => array( 'header', 'hero', 'sg-humanitix-importer-patterns' ),
			'keywords'    => array( 'hero', 'header', 'banner', 'cta' ),
			'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}}},"backgroundColor":"primary","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-primary-background-color has-text-color has-background" style="padding-top:4rem;padding-bottom:4rem">
	<!-- wp:heading {"textAlign":"center","level":1,"fontSize":"x-large"} -->
	<h1 class="wp-block-heading has-text-align-center has-x-large-font-size">Welcome to Humanitix Importer</h1>
	<!-- /wp:heading -->
	
	<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
	<p class="has-text-align-center has-medium-font-size">This is a hero section created with the Humanitix Importer plugin.</p>
	<!-- /wp:paragraph -->
	
	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-buttons">
		<!-- wp:button {"backgroundColor":"secondary","textColor":"white"} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-secondary-background-color has-text-color has-background wp-element-button">Get Started</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->',
		);

		// Example: Two column content pattern.
		$this->custom_patterns['sg-humanitix-importer/two-column-content'] = array(
			'title'       => __( 'Humanitix Importer Two Column Content', 'sg-humanitix-importer' ),
			'description' => __( 'Two column layout with image and text content.', 'sg-humanitix-importer' ),
			'categories'  => array( 'columns', 'text', 'sg-humanitix-importer-patterns' ),
			'keywords'    => array( 'columns', 'image', 'text', 'content' ),
			'content'     => '<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide">
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:image {"sizeSlug":"large"} -->
		<figure class="wp-block-image size-large"><img alt=""/></figure>
		<!-- /wp:image -->
	</div>
	<!-- /wp:column -->
	
	<!-- wp:column {"verticalAlignment":"center"} -->
	<div class="wp-block-column is-vertically-aligned-center">
		<!-- wp:heading {"level":2} -->
		<h2 class="wp-block-heading">Content Heading</h2>
		<!-- /wp:heading -->
		
		<!-- wp:paragraph -->
		<p>Add your content description here. This two-column layout is perfect for showcasing features or content alongside images.</p>
		<!-- /wp:paragraph -->
		
		<!-- wp:button -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Learn More</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->',
		);

		// Example: Call to action pattern.
		$this->custom_patterns['sg-humanitix-importer/call-to-action'] = array(
			'title'       => __( 'Humanitix Importer Call to Action', 'sg-humanitix-importer' ),
			'description' => __( 'A call-to-action section with centered content.', 'sg-humanitix-importer' ),
			'categories'  => array( 'call-to-action', 'buttons', 'sg-humanitix-importer-patterns' ),
			'keywords'    => array( 'cta', 'call to action', 'button', 'centered' ),
			'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"3rem","bottom":"3rem"}}},"backgroundColor":"light-gray","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-light-gray-background-color has-background" style="padding-top:3rem;padding-bottom:3rem">
	<!-- wp:heading {"textAlign":"center","level":2} -->
	<h2 class="wp-block-heading has-text-align-center">Ready to Get Started?</h2>
	<!-- /wp:heading -->
	
	<!-- wp:paragraph {"align":"center"} -->
	<p class="has-text-align-center">Take action today and discover what Humanitix Importer can do for you.</p>
	<!-- /wp:paragraph -->
	
	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-buttons">
		<!-- wp:button {"backgroundColor":"primary","textColor":"white"} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-primary-background-color has-text-color has-background wp-element-button">Get Started Now</a></div>
		<!-- /wp:button -->
		
		<!-- wp:button {"style":{"border":{"width":"1px"}},"borderColor":"primary","textColor":"primary","className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-primary-color has-text-color has-border-color has-primary-border-color wp-element-button" style="border-width:1px">Learn More</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->',
		);
	}

	/**
	 * Register a single block pattern.
	 *
	 * @param string $pattern_name Pattern name/slug.
	 * @param array $pattern_data Pattern configuration.
	 */
	private function register_pattern( $pattern_name, $pattern_data ) {
		// Ensure required fields are present.
		if ( empty( $pattern_data['title'] ) || empty( $pattern_data['content'] ) ) {
			return;
		}

		// Set default values.
		$pattern_data = wp_parse_args( $pattern_data, array(
			'description' => '',
			'categories'  => array( 'sg-humanitix-importer-patterns' ),
			'keywords'    => array(),
			'viewport'    => 800,
		));

		// Register the pattern.
		register_block_pattern( $pattern_name, $pattern_data );
	}

	/**
	 * Unregister blocked patterns.
	 */
	public function unregister_blocked_patterns() {
		foreach ( $this->blocked_patterns as $pattern_name ) {
			unregister_block_pattern( $pattern_name );
		}
	}

	/**
	 * Filter available block patterns in the editor.
	 *
	 * @param array $settings Block editor settings.
	 * @return array Modified settings.
	 */
	public function filter_block_patterns( $settings ) {
		// Example: Hide core patterns if desired.
		// $settings['__experimentalBlockPatterns'] = array_filter(
		//     $settings['__experimentalBlockPatterns'],
		//     function( $pattern ) {
		//         return strpos( $pattern['name'], 'core/' ) === false;
		//     }
		// );

		return $settings;
	}

	/**
	 * Add a custom pattern programmatically.
	 *
	 * @param string $pattern_name Pattern name/slug.
	 * @param array $pattern_data Pattern configuration.
	 */
	public function add_pattern( $pattern_name, $pattern_data ) {
		$this->custom_patterns[ $pattern_name ] = $pattern_data;
	}

	/**
	 * Block a pattern from being available.
	 *
	 * @param string $pattern_name Pattern name to block.
	 */
	public function block_pattern( $pattern_name ) {
		$this->blocked_patterns[] = $pattern_name;
	}

	/**
	 * Get all registered custom patterns.
	 *
	 * @return array Custom patterns.
	 */
	public function get_custom_patterns() {
		return $this->custom_patterns;
	}

	/**
	 * Check if a pattern is registered.
	 *
	 * @param string $pattern_name Pattern name to check.
	 * @return bool Whether pattern is registered.
	 */
	public function is_pattern_registered( $pattern_name ) {
		$registry = WP_Block_Patterns_Registry::get_instance();
		return $registry->is_registered( $pattern_name );
	}

	/**
	 * Create a pattern from a block template.
	 *
	 * @param string $pattern_name Pattern name.
	 * @param string $title Pattern title.
	 * @param string $description Pattern description.
	 * @param array $categories Pattern categories.
	 * @param string $template_path Path to the pattern template file.
	 * @return bool Whether pattern was created successfully.
	 */
	public function create_pattern_from_template( $pattern_name, $title, $description, $categories, $template_path ) {
		if ( ! file_exists( $template_path ) ) {
			return false;
		}

		ob_start();
		include $template_path;
		$content = ob_get_clean();

		if ( empty( $content ) ) {
			return false;
		}

		$this->add_pattern( $pattern_name, array(
			'title'       => $title,
			'description' => $description,
			'categories'  => $categories,
			'content'     => $content,
		));

		return true;
	}
} 