<?php
/**
 * Eventbrite Importer Class.
 *
 * Handles the import of events from Eventbrite API to SG Course custom post type.
 * Manages event creation, updates, and data mapping.
 *
 * @package SG\EventbriteCourseImporter
 * @since 1.0.0
 */

namespace SG\EventbriteCourseImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Eventbrite Importer Class.
 *
 * Handles the import of events from Eventbrite API to SG Course custom post type.
 *
 * @package SG\EventbriteCourseImporter
 * @since 1.0.0
 */
class EventbriteImporter {

	/**
	 * The Eventbrite API instance.
	 *
	 * @var EventbriteAPI
	 */
	private $api;

	/**
	 * The logger instance.
	 *
	 * @var \SG\EventbriteCourseImporter\Admin\Logger
	 */
	private $logger;

	/**
	 * Array of successfully imported event IDs.
	 *
	 * @var array
	 */
	private $imported_events = array();

	/**
	 * Array of error messages from failed imports.
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Timestamp when the import process started.
	 *
	 * @var float
	 */
	private $start_time;

	/**
	 * Constructor.
	 *
	 * @param EventbriteAPI $api The Eventbrite API instance.
	 * @param \SG\EventbriteCourseImporter\Admin\Logger $logger Optional logger instance.
	 */
	public function __construct( EventbriteAPI $api, $logger = null ) {
		$this->api = $api;
		$this->logger = $logger ? $logger : new \SG\EventbriteCourseImporter\Admin\Logger();
	}

	/**
	 * Import events from Eventbrite.
	 *
	 * @param array $event_ids Array of Eventbrite event IDs to import.
	 * @param array $options Import options.
	 * @return array Import results.
	 */
	public function import_events( $event_ids = array(), $options = array() ) {
		$this->start_time = microtime( true );
		$this->imported_events = array();
		$this->errors = array();

		$default_options = array(
			'update_existing' => true,
			'import_images'   => true,
			'extract_keywords' => true,
			'status'          => 'publish',
		);

		$options = wp_parse_args( $options, $default_options );

		$this->logger->log( 'Starting Eventbrite import process', 'info' );

		// If no specific event IDs provided, get all events from organization
		if ( empty( $event_ids ) ) {
			$events_response = $this->api->get_organization_events();
			if ( is_wp_error( $events_response ) ) {
				$this->errors[] = $events_response->get_error_message();
				return $this->get_import_results();
			}
			$events = $events_response['events'] ?? array();
		} else {
			$events = array();
			foreach ( $event_ids as $event_id ) {
				$event_response = $this->api->get_event( $event_id );
				if ( is_wp_error( $event_response ) ) {
					$this->errors[] = sprintf( 'Failed to fetch event %s: %s', $event_id, $event_response->get_error_message() );
					continue;
				}
				$events[] = $event_response;
			}
		}

		$this->logger->log( sprintf( 'Found %d events to process', count( $events ) ), 'info' );

		foreach ( $events as $event ) {
			$this->import_single_event( $event, $options );
		}

		$this->logger->log( 'Eventbrite import process completed', 'info' );
		return $this->get_import_results();
	}

	/**
	 * Import a single event.
	 *
	 * @param array $event Event data from Eventbrite API.
	 * @param array $options Import options.
	 * @return int|WP_Error Post ID or error.
	 */
	public function import_single_event( $event, $options = array() ) {
		$event_id = $event['id'] ?? '';
		if ( empty( $event_id ) ) {
			$error = 'Event ID is missing from event data';
			$this->errors[] = $error;
			$this->logger->log( $error, 'error' );
			return new \WP_Error( 'missing_event_id', $error );
		}

		$this->logger->log( "Processing event: {$event_id}", 'debug' );

		// Check if event already exists
		$existing_post = $this->find_existing_course( $event_id );
		if ( $existing_post && ! $options['update_existing'] ) {
			$this->logger->log( "Event {$event_id} already exists, skipping", 'info' );
			return $existing_post->ID;
		}

		// Map Eventbrite data to course format
		$course_data = $this->map_event_to_course( $event, $options );

		if ( is_wp_error( $course_data ) ) {
			$this->errors[] = $course_data->get_error_message();
			return $course_data;
		}

		// Create or update the course post
		$post_data = array(
			'post_title'   => $course_data['title'],
			'post_content' => $course_data['description'],
			'post_excerpt' => $course_data['excerpt'],
			'post_status'  => $options['status'],
			'post_type'    => CustomPostType::POST_TYPE,
			'meta_input'   => $course_data['meta'],
		);

		if ( $existing_post ) {
			$post_data['ID'] = $existing_post->ID;
			$post_id = wp_update_post( $post_data );
			$this->logger->log( "Updated existing course for event {$event_id}", 'info' );
		} else {
			$post_id = wp_insert_post( $post_data );
			$this->logger->log( "Created new course for event {$event_id}", 'info' );
		}

		if ( is_wp_error( $post_id ) ) {
			$error = sprintf( 'Failed to save course for event %s: %s', $event_id, $post_id->get_error_message() );
			$this->errors[] = $error;
			$this->logger->log( $error, 'error' );
			return $post_id;
		}

		// Handle featured image
		if ( $options['import_images'] && ! empty( $course_data['image_url'] ) ) {
			$this->set_featured_image( $post_id, $course_data['image_url'], $event_id );
		}

		// Set taxonomies
		$this->set_course_taxonomies( $post_id, $event );

		$this->imported_events[] = $post_id;
		$this->logger->log( "Successfully imported event {$event_id} as course {$post_id}", 'info' );

		return $post_id;
	}

	/**
	 * Map Eventbrite event data to course format.
	 *
	 * @param array $event Event data from Eventbrite API.
	 * @param array $options Import options.
	 * @return array|WP_Error Mapped course data or error.
	 */
	private function map_event_to_course( $event, $options = array() ) {
		$event_id = $event['id'] ?? '';
		$name = $event['name']['text'] ?? '';
		$description = $event['description']['text'] ?? '';
		$start_date = $event['start']['utc'] ?? '';
		$end_date = $event['end']['utc'] ?? '';
		$venue = $event['venue'] ?? array();
		$logo = $event['logo'] ?? array();
		$ticket_classes = $event['ticket_classes'] ?? array();

		// Validate required fields
		if ( empty( $name ) ) {
			return new \WP_Error( 'missing_title', 'Event title is required' );
		}

		if ( empty( $start_date ) ) {
			return new \WP_Error( 'missing_start_date', 'Event start date is required' );
		}

		// Extract keywords from description if enabled
		$extracted_data = array();
		if ( $options['extract_keywords'] ) {
			$extracted_data = $this->extract_keywords_from_description( $description );
		}

		// Format dates
		$start_datetime = new \DateTime( $start_date );
		$end_datetime = new \DateTime( $end_date );

		// Calculate day of week
		$day_of_week = $start_datetime->format( 'l' );

		// Calculate class length in hours
		$class_length = $this->calculate_class_length( $start_datetime, $end_datetime );

		// Get pricing information
		$pricing = $this->extract_pricing( $ticket_classes );

		// Build location string
		$location = $this->build_location_string( $venue );

		// Create excerpt from description
		$excerpt = wp_trim_words( strip_tags( $description ), 30, '...' );

		$course_data = array(
			'title'       => $name,
			'description' => $description,
			'excerpt'     => $excerpt,
			'image_url'   => $logo['url'] ?? '',
			'meta'        => array(
				'_sg_course_start_date'        => $start_datetime->format( 'Y-m-d' ),
				'_sg_course_start_time'        => $start_datetime->format( 'H:i' ),
				'_sg_course_price'             => $pricing['display_price'],
				'_sg_course_location'          => $location,
				'_sg_course_eventbrite_id'     => $event_id,
				'_sg_course_eventbrite_url'    => $event['url'] ?? '',
				'_sg_course_last_imported'     => current_time( 'mysql' ),
				// Extracted keywords
				'_sg_course_instructor'        => $extracted_data['instructor'] ?? '',
				'_sg_course_class_length'      => $class_length,
				'_sg_course_course_length'     => $extracted_data['course_length'] ?? '',
				'_sg_course_drop_in_class'     => $extracted_data['drop_in_class'] ? '1' : '0',
				'_sg_course_day_of_week'       => $day_of_week,
				// Additional Eventbrite data
				'_sg_course_eventbrite_venue'  => $venue['name'] ?? '',
				'_sg_course_eventbrite_organizer' => $event['organizer']['name'] ?? '',
				'_sg_course_eventbrite_capacity' => $event['capacity'] ?? '',
				'_sg_course_eventbrite_status' => $event['status'] ?? '',
			),
		);

		return $course_data;
	}

	/**
	 * Extract keywords from event description.
	 *
	 * @param string $description Event description.
	 * @return array Extracted data.
	 */
	private function extract_keywords_from_description( $description ) {
		$extracted = array(
			'instructor'    => '',
			'course_length' => '',
			'drop_in_class' => false,
		);

		// Convert HTML to text for easier parsing
		$text = wp_strip_all_tags( $description );

		// Look for instructor patterns
		$instructor_patterns = array(
			'/(?:instructor|teacher|taught by|led by|presented by)[\s:]+([^.\n]+)/i',
			'/([A-Z][a-z]+ [A-Z][a-z]+) will teach/i',
			'/([A-Z][a-z]+ [A-Z][a-z]+) teaches/i',
		);

		foreach ( $instructor_patterns as $pattern ) {
			if ( preg_match( $pattern, $text, $matches ) ) {
				$extracted['instructor'] = trim( $matches[1] );
				break;
			}
		}

		// Look for course length patterns
		$length_patterns = array(
			'/(\d+)\s*(?:week|weeks|session|sessions|day|days|month|months)/i',
			'/(?:course|class|program|workshop)[\s\w]*(?:lasts?|runs?|is|takes?)[\s\w]*(?:for\s+)?(\d+)\s*(?:week|weeks|session|sessions|day|days|month|months)/i',
		);

		foreach ( $length_patterns as $pattern ) {
			if ( preg_match( $pattern, $text, $matches ) ) {
				$extracted['course_length'] = trim( $matches[0] );
				break;
			}
		}

		// Check for drop-in class indicators
		$drop_in_patterns = array(
			'/drop[\s-]?in/i',
			'/walk[\s-]?in/i',
			'/no registration required/i',
			'/open to all/i',
		);

		foreach ( $drop_in_patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				$extracted['drop_in_class'] = true;
				break;
			}
		}

		return $extracted;
	}

	/**
	 * Calculate class length in hours.
	 *
	 * @param DateTime $start Start datetime.
	 * @param DateTime $end End datetime.
	 * @return float Class length in hours.
	 */
	private function calculate_class_length( $start, $end ) {
		$diff = $start->diff( $end );
		return $diff->h + ( $diff->i / 60 );
	}

	/**
	 * Extract pricing information from ticket classes.
	 *
	 * @param array $ticket_classes Ticket classes data.
	 * @return array Pricing information.
	 */
	private function extract_pricing( $ticket_classes ) {
		$pricing = array(
			'display_price' => 'Free',
			'currency'      => 'USD',
			'free'          => true,
		);

		if ( empty( $ticket_classes ) ) {
			return $pricing;
		}

		$ticket = $ticket_classes[0]; // Use first ticket class
		$cost = $ticket['cost'] ?? array();

		if ( ! empty( $cost['value'] ) && $cost['value'] > 0 ) {
			$currency = $cost['currency'] ?? 'USD';
			$value = $cost['value'] / 100; // Convert from cents
			$pricing['display_price'] = $currency . ' ' . number_format( $value, 2 );
			$pricing['currency'] = $currency;
			$pricing['free'] = false;
		}

		return $pricing;
	}

	/**
	 * Build location string from venue data.
	 *
	 * @param array $venue Venue data.
	 * @return string Location string.
	 */
	private function build_location_string( $venue ) {
		if ( empty( $venue ) ) {
			return '';
		}

		$location_parts = array();

		if ( ! empty( $venue['name'] ) ) {
			$location_parts[] = $venue['name'];
		}

		$address = $venue['address'] ?? array();
		if ( ! empty( $address['address_1'] ) ) {
			$location_parts[] = $address['address_1'];
		}

		if ( ! empty( $address['city'] ) ) {
			$city_state = $address['city'];
			if ( ! empty( $address['region'] ) ) {
				$city_state .= ', ' . $address['region'];
			}
			$location_parts[] = $city_state;
		}

		return implode( ', ', $location_parts );
	}

	/**
	 * Set featured image for course.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $image_url Image URL.
	 * @param string $event_id Event ID for logging.
	 */
	private function set_featured_image( $post_id, $image_url, $event_id ) {
		if ( empty( $image_url ) ) {
			return;
		}

		$image_id = $this->download_image( $image_url, $event_id );
		if ( $image_id && ! is_wp_error( $image_id ) ) {
			set_post_thumbnail( $post_id, $image_id );
			$this->logger->log( "Set featured image for course {$post_id}", 'debug' );
		}
	}

	/**
	 * Download image from URL.
	 *
	 * @param string $image_url Image URL.
	 * @param string $event_id Event ID for filename.
	 * @return int|WP_Error Attachment ID or error.
	 */
	private function download_image( $image_url, $event_id ) {
		$upload_dir = wp_upload_dir();
		$filename = 'eventbrite-event-' . $event_id . '.jpg';
		$file_path = $upload_dir['path'] . '/' . $filename;

		$response = wp_remote_get( $image_url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$image_data = wp_remote_retrieve_body( $response );
		if ( empty( $image_data ) ) {
			return new \WP_Error( 'empty_image', 'Image data is empty' );
		}

		$file_saved = file_put_contents( $file_path, $image_data );
		if ( false === $file_saved ) {
			return new \WP_Error( 'file_save_error', 'Failed to save image file' );
		}

		$wp_filetype = wp_check_filetype( $filename, null );
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		return $attachment_id;
	}

	/**
	 * Set course taxonomies.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $event Event data.
	 */
	private function set_course_taxonomies( $post_id, $event ) {
		// Set categories based on Eventbrite categories
		$categories = $event['category_id'] ?? '';
		if ( $categories ) {
			// You can map Eventbrite categories to your custom categories here
			// For now, we'll create a generic category
			$term = wp_insert_term( 'Imported Course', 'sg_course_category' );
			if ( ! is_wp_error( $term ) ) {
				wp_set_post_terms( $post_id, array( $term['term_id'] ), 'sg_course_category' );
			}
		}

		// Set tags based on Eventbrite subcategories or keywords
		$subcategories = $event['subcategory_id'] ?? '';
		if ( $subcategories ) {
			$term = wp_insert_term( 'Eventbrite Import', 'sg_course_tag' );
			if ( ! is_wp_error( $term ) ) {
				wp_set_post_terms( $post_id, array( $term['term_id'] ), 'sg_course_tag' );
			}
		}
	}

	/**
	 * Find existing course by Eventbrite ID.
	 *
	 * @param string $eventbrite_id Eventbrite event ID.
	 * @return WP_Post|null Existing post or null.
	 */
	private function find_existing_course( $eventbrite_id ) {
		$posts = get_posts( array(
			'post_type'      => CustomPostType::POST_TYPE,
			'post_status'    => 'any',
			'meta_key'       => '_sg_course_eventbrite_id',
			'meta_value'     => $eventbrite_id,
			'posts_per_page' => 1,
		) );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Get import results.
	 *
	 * @return array Import results.
	 */
	private function get_import_results() {
		$end_time = microtime( true );
		$duration = round( $end_time - $this->start_time, 2 );

		return array(
			'success'           => empty( $this->errors ),
			'imported_count'    => count( $this->imported_events ),
			'imported_events'   => $this->imported_events,
			'errors'            => $this->errors,
			'duration'          => $duration,
			'memory_usage'      => memory_get_peak_usage( true ),
		);
	}
}