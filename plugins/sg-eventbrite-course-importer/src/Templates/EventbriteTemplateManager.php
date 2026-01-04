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

		// Hook into WordPress template system - check for page template conflicts first
		add_filter( 'template_include', array( $this, 'check_page_template_conflicts' ), 5 );
		add_filter( 'template_include', array( $this, 'override_single_course_template' ) );
		add_filter( 'template_include', array( $this, 'override_taxonomy_template' ) );
		add_filter( 'template_include', array( $this, 'override_page_template' ), 20 );
		
		// Debug: Log that filters are being registered
		error_log( 'SG Eventbrite: Template filters registered' );
		
		// Register plugin page templates in the dropdown
		add_filter( 'theme_page_templates', array( $this, 'register_page_templates' ), 10, 4 );
		
		// Modify taxonomy queries to only show published courses
		add_action( 'pre_get_posts', array( $this, 'filter_taxonomy_published_courses' ) );
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
	 * Check for page template conflicts with custom post type archives.
	 * If a page with our template exists at the same URL as a CPT archive, load the page instead.
	 *
	 * @since 1.0.0
	 * @param string $template The original template path.
	 * @return string The modified template path.
	 */
	public function check_page_template_conflicts( $template ) {
		// Only process on frontend
		if ( is_admin() ) {
			return $template;
		}

		global $wp_query;
		
		// Check if this is a course archive
		if ( ! isset( $wp_query->query_vars['post_type'] ) || 'sg_course' !== $wp_query->query_vars['post_type'] ) {
			return $template;
		}
		
		// Check if this is an archive (not a single post)
		if ( isset( $wp_query->is_singular ) && $wp_query->is_singular ) {
			return $template;
		}
		
		// Get the current request URI
		$request_uri = trim( $_SERVER['REQUEST_URI'], '/' );
		$request_parts = explode( '/', $request_uri );
		$last_part = end( $request_parts );
		
		// Remove query string if present
		$last_part = strtok( $last_part, '?' );
		
		error_log( 'SG Eventbrite: check_page_template_conflicts - Request URI: ' . $request_uri . ', Last part: ' . $last_part );
		
		// Check if a page exists with this slug and our template
		$page = get_page_by_path( $last_part );
		
		if ( $page && 'page' === get_post_type( $page->ID ) ) {
			$page_template = get_page_template_slug( $page->ID );
			$plugin_templates = $this->get_plugin_page_templates();
			
			error_log( 'SG Eventbrite: Found page ID: ' . $page->ID . ', Template: ' . ( $page_template ? $page_template : 'default' ) );
			
			if ( ! empty( $page_template ) && isset( $plugin_templates[ $page_template ] ) ) {
				$template_path = \SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/src/Templates/Overrides/' . $page_template;
				if ( file_exists( $template_path ) ) {
					error_log( 'SG Eventbrite: Overriding archive with page template: ' . $template_path );
					
					// Set up the query to show the page instead
					$wp_query->is_archive = false;
					$wp_query->is_page = true;
					$wp_query->is_singular = true;
					$wp_query->queried_object = $page;
					$wp_query->queried_object_id = $page->ID;
					$wp_query->post_count = 1;
					$wp_query->posts = array( $page );
					
					return $template_path;
				}
			}
		}
		
		return $template;
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
	 * Override taxonomy template for course categories.
	 *
	 * @since 1.0.0
	 * @param string $template The original template path.
	 * @return string The modified template path.
	 */
	public function override_taxonomy_template( $template ) {
		if ( is_tax( 'sg_course_category' ) ) {
			$custom_template = \SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/src/Templates/Overrides/taxonomy-sg_course_category.php';
			if ( file_exists( $custom_template ) ) {
				$this->logger->log(
					'debug',
					'Overriding taxonomy template',
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
	 * Override page template when plugin template is selected.
	 *
	 * @since 1.0.0
	 * @param string $template The original template path.
	 * @return string The modified template path.
	 */
	public function override_page_template( $template ) {
		// Debug: Always log when this function is called
		error_log( 'SG Eventbrite: override_page_template() called. is_admin: ' . ( is_admin() ? 'yes' : 'no' ) . ', is_page: ' . ( is_page() ? 'yes' : 'no' ) );
		error_log( 'SG Eventbrite: Current template path: ' . $template );
		
		// Only process on frontend
		if ( is_admin() ) {
			error_log( 'SG Eventbrite: Early return - is_admin: yes' );
			return $template;
		}

		global $post, $wp_query;
		
		// Debug: Log query info
		if ( isset( $wp_query ) ) {
			error_log( 'SG Eventbrite: Query vars - is_page: ' . ( $wp_query->is_page ? 'yes' : 'no' ) . ', is_singular: ' . ( $wp_query->is_singular ? 'yes' : 'no' ) . ', post_type: ' . ( isset( $wp_query->query_vars['post_type'] ) ? $wp_query->query_vars['post_type'] : 'none' ) );
		}
		
		if ( ! $post ) {
			error_log( 'SG Eventbrite: Early return - no post object' );
			return $template;
		}
		
		error_log( 'SG Eventbrite: Post ID: ' . $post->ID . ', Post type: ' . get_post_type( $post->ID ) . ', Post title: ' . get_the_title( $post->ID ) );
		
		// Check if this is a page by post type instead of is_page() which might not be set yet
		if ( 'page' !== get_post_type( $post->ID ) ) {
			error_log( 'SG Eventbrite: Early return - not a page. Post type: ' . get_post_type( $post->ID ) );
			
			// Debug: Try to find pages with our templates
			$pages_with_templates = get_posts( array(
				'post_type' => 'page',
				'posts_per_page' => -1,
				'meta_query' => array(
					array(
						'key' => '_wp_page_template',
						'value' => array( 'page-course-categories.php', 'page-now-enrolling.php' ),
						'compare' => 'IN',
					),
				),
			) );
			error_log( 'SG Eventbrite: Found ' . count( $pages_with_templates ) . ' pages with plugin templates' );
			foreach ( $pages_with_templates as $page ) {
				error_log( 'SG Eventbrite: Page - ID: ' . $page->ID . ', Title: ' . $page->post_title . ', Slug: ' . $page->post_name . ', Template: ' . get_page_template_slug( $page->ID ) );
			}
			
			return $template;
		}

		$page_template = get_page_template_slug( $post->ID );
		
		// Debug logging
		error_log( 'SG Eventbrite: override_page_template called for page ID: ' . $post->ID );
		error_log( 'SG Eventbrite: Page template slug: ' . ( $page_template ? $page_template : 'none' ) );
		
		// Check if a plugin template is selected
		$plugin_templates = $this->get_plugin_page_templates();
		error_log( 'SG Eventbrite: Available plugin templates: ' . print_r( array_keys( $plugin_templates ), true ) );
		
		if ( ! empty( $page_template ) && isset( $plugin_templates[ $page_template ] ) ) {
			$template_path = \SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/src/Templates/Overrides/' . $page_template;
			error_log( 'SG Eventbrite: Template path: ' . $template_path );
			error_log( 'SG Eventbrite: Template exists: ' . ( file_exists( $template_path ) ? 'yes' : 'no' ) );
			
			if ( file_exists( $template_path ) ) {
				$this->logger->log(
					'debug',
					'Overriding page template',
					array(
						'module' => 'templates',
						'template' => $template_path,
						'page_id' => $post->ID,
					)
				);
				error_log( 'SG Eventbrite: Returning template: ' . $template_path );
				return $template_path;
			}
		} else {
			error_log( 'SG Eventbrite: Template not matched or empty. Page template: ' . ( $page_template ? $page_template : 'empty' ) );
		}
		
		return $template;
	}

	/**
	 * Register plugin page templates in the WordPress template dropdown.
	 *
	 * @since 1.0.0
	 * @param array $templates Array of page templates. Keys are filenames, values are translated names.
	 * @param WP_Theme $theme The theme object.
	 * @param WP_Post $post The post being edited.
	 * @param string $post_type The post type.
	 * @return array Modified array of page templates.
	 */
	public function register_page_templates( $templates, $theme, $post, $post_type ) {
		// Only add templates for pages
		if ( 'page' !== $post_type ) {
			return $templates;
		}

		$plugin_templates = $this->get_plugin_page_templates();
		
		// Merge plugin templates with theme templates
		return array_merge( $templates, $plugin_templates );
	}

	/**
	 * Get plugin page templates.
	 *
	 * @since 1.0.0
	 * @return array Array of template files with their display names.
	 */
	private function get_plugin_page_templates() {
		$templates = array();
		$template_dir = \SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/src/Templates/Overrides';
		
		// Define our page templates
		$template_files = array(
			'page-course-categories.php' => __( 'Course Categories Landing Page', 'sg-eventbrite-course-importer' ),
			'page-now-enrolling.php'     => __( 'Now Enrolling Courses', 'sg-eventbrite-course-importer' ),
		);
		
		foreach ( $template_files as $filename => $name ) {
			$file_path = $template_dir . '/' . $filename;
			if ( file_exists( $file_path ) ) {
				// Use the filename as the key so WordPress can identify it
				$templates[ $filename ] = $name;
			}
		}
		
		return $templates;
	}

	/**
	 * Filter taxonomy queries to only show published courses.
	 *
	 * @since 1.0.0
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	public function filter_taxonomy_published_courses( $query ) {
		if ( ! is_admin() && $query->is_main_query() && is_tax( 'sg_course_category' ) ) {
			$query->set( 'post_status', 'publish' );
		}
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
