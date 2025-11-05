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

		error_log( 'SG Eventbrite: Starting Eventbrite import process' );

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

		error_log( 'SG Eventbrite: Found ' . count( $events ) . ' events to process' );

		// Debug logging
		error_log( 'SG Eventbrite: Using structured content API for event descriptions' );

		foreach ( $events as $event ) {
			$this->import_single_event( $event, $options );
		}

		error_log( 'SG Eventbrite: Eventbrite import process completed' );
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
			error_log( 'SG Eventbrite: ' . $error );
			return new \WP_Error( 'missing_event_id', $error );
		}

		error_log( "SG Eventbrite: Processing event: {$event_id}" );

		// Check if event already exists
		$existing_post = $this->find_existing_course( $event_id );
		if ( $existing_post && ! $options['update_existing'] ) {
			error_log( "SG Eventbrite: Event {$event_id} already exists, skipping" );
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
			error_log( "SG Eventbrite: Updated existing course for event {$event_id}" );
		} else {
			$post_id = wp_insert_post( $post_data );
			error_log( "SG Eventbrite: Created new course for event {$event_id}" );
		}

		if ( is_wp_error( $post_id ) ) {
			$error = sprintf( 'Failed to save course for event %s: %s', $event_id, $post_id->get_error_message() );
			$this->errors[] = $error;
			error_log( 'SG Eventbrite: ' . $error );
			return $post_id;
		}

		// Handle featured image
		if ( $options['import_images'] && ! empty( $course_data['image_url'] ) ) {
			$this->set_featured_image( $post_id, $course_data['image_url'], $event_id );
		}

		// Set taxonomies
		$intake_data = $options['intake_data'][ $event_id ] ?? array();
		$this->set_course_taxonomies( $post_id, $event, $intake_data );

		// Update ACF fields if ACF is active
		if ( function_exists( 'update_field' ) ) {
			if ( ! empty( $course_data['meta']['_sg_course_instructor'] ) ) {
				update_field( 'instructor', $course_data['meta']['_sg_course_instructor'], $post_id );
			}
			if ( ! empty( $course_data['meta']['_sg_course_day_of_week'] ) ) {
				$days = explode( ', ', $course_data['meta']['_sg_course_day_of_week'] );
				$days = array_map( 'strtolower', array_map( 'trim', $days ) );
				update_field( 'day_of_week', $days, $post_id );
			}
			if ( ! empty( $course_data['meta']['_sg_course_class_length'] ) ) {
				update_field( 'class_length', $course_data['meta']['_sg_course_class_length'], $post_id );
			}
			if ( ! empty( $course_data['meta']['_sg_course_course_length'] ) ) {
				update_field( 'course_length', $course_data['meta']['_sg_course_course_length'], $post_id );
			}
			if ( ! empty( $course_data['meta']['_sg_course_ticket_class_id'] ) ) {
				update_field( 'ticket_class_id', $course_data['meta']['_sg_course_ticket_class_id'], $post_id );
			}
			if ( ! empty( $course_data['meta']['_sg_course_ticket_class_name'] ) ) {
				update_field( 'ticket_class_name', $course_data['meta']['_sg_course_ticket_class_name'], $post_id );
			}
			if ( ! empty( $course_data['meta']['_sg_course_ticket_price'] ) ) {
				update_field( 'ticket_price', floatval( $course_data['meta']['_sg_course_ticket_price'] ), $post_id );
			}
			if ( ! empty( $course_data['meta']['_sg_course_ticket_price_total'] ) ) {
				update_field( 'ticket_price_total', floatval( $course_data['meta']['_sg_course_ticket_price_total'] ), $post_id );
			}
			if ( ! empty( $course_data['meta']['_sg_course_ticket_expiration'] ) ) {
				update_field( 'ticket_expiration', $course_data['meta']['_sg_course_ticket_expiration'], $post_id );
			}
			if ( ! empty( $course_data['meta']['_sg_course_ticket_sales_start'] ) ) {
				update_field( 'ticket_sales_start', $course_data['meta']['_sg_course_ticket_sales_start'], $post_id );
			}
		}

		$this->imported_events[] = $post_id;
		error_log( "SG Eventbrite: Successfully imported event {$event_id} as course {$post_id}" );

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
		// Process structured content to HTML
		$description = $this->process_structured_content( $event );
		
		// Debug: Log structured content processing
		error_log( "SG Eventbrite: Event {$event_id} - Structured content available: " . ( isset( $event['structured_content'] ) ? 'YES' : 'NO' ) );
		error_log( "SG Eventbrite: Event {$event_id} - Final description length: " . strlen( $description ) . " characters" );
		// Extract start/end time data from Eventbrite API structure
		// Eventbrite returns: { "timezone": "America/Los_Angeles", "utc": "2018-05-12T02:00:00Z", "local": "2018-05-11T19:00:00" }
		$start_date = $event['start']['utc'] ?? '';
		$end_date = $event['end']['utc'] ?? '';
		$event_timezone = $event['start']['timezone'] ?? null; // Event's original timezone
		$start_local = $event['start']['local'] ?? ''; // Event's local time in its timezone
		$end_local = $event['end']['local'] ?? '';
		
		// Debug: Log the raw datetime strings from API
		error_log( "SG Eventbrite: Event {$event_id} - Start time data:" );
		error_log( "  - UTC: " . $start_date );
		error_log( "  - Local: " . $start_local );
		error_log( "  - Event Timezone: " . ( $event_timezone ?? 'not set' ) );
		error_log( "  - End UTC: " . $end_date );
		error_log( "  - End Local: " . $end_local );
		
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

		// Get intake form data if available, otherwise extract from description
		$intake_data = $options['intake_data'][ $event_id ] ?? array();
		$instructor = '';
		$day_of_week = '';
		$class_length = '';
		$course_length = '';

		if ( ! empty( $intake_data ) ) {
			// Use intake form data
			$instructor = sanitize_text_field( $intake_data['instructor'] ?? '' );
			$day_of_week_array = isset( $intake_data['day_of_week'] ) ? (array) $intake_data['day_of_week'] : array();
			$day_of_week = ! empty( $day_of_week_array ) ? implode( ', ', array_map( 'ucfirst', $day_of_week_array ) ) : '';
			$class_length = sanitize_text_field( $intake_data['class_length'] ?? '' );
			$course_length = sanitize_text_field( $intake_data['course_length'] ?? '' );
		} else {
			// Extract keywords from description if enabled
			$extracted_data = array();
			if ( $options['extract_keywords'] ) {
				$extracted_data = $this->extract_keywords_from_description( $description );
			}
			
			$instructor = $extracted_data['instructor'] ?? '';
			$course_length = $extracted_data['course_length'] ?? '';
		}

		// Format dates - use event's local time (what attendees see)
		// Eventbrite API structure: { "timezone": "America/Chicago", "utc": "2018-05-12T02:00:00Z", "local": "2018-05-11T19:00:00" }
		// We store the event's LOCAL time (already converted by Eventbrite) - this is what attendees see
		// No additional conversion needed - we want to preserve the event's local time as displayed
		try {
			if ( ! empty( $start_local ) && ! empty( $event_timezone ) ) {
				// Use event's local time directly (e.g., "2025-11-05T17:00:00" in America/Chicago)
				// Parse it with the event's timezone to create a proper DateTime object
				$event_tz = new \DateTimeZone( $event_timezone );
				$start_datetime = new \DateTime( $start_local, $event_tz );
				
				// Debug: Log what we're storing
				error_log( "SG Eventbrite: Event {$event_id} - Storing event local time:" );
				error_log( "  - Event timezone: " . $event_timezone );
				error_log( "  - Event local time (from API): " . $start_local );
				error_log( "  - Parsed datetime: " . $start_datetime->format( 'Y-m-d H:i:s T' ) );
				error_log( "  - Storing date: " . $start_datetime->format( 'Y-m-d' ) );
				error_log( "  - Storing time: " . $start_datetime->format( 'H:i' ) );
			} elseif ( ! empty( $start_date ) ) {
				// Fallback: if local time not available, use UTC and convert to site timezone
				error_log( "SG Eventbrite: Event {$event_id} - Using UTC as fallback (local time not available)" );
				$utc_timezone = new \DateTimeZone( 'UTC' );
				$site_timezone = wp_timezone();
				$start_datetime = new \DateTime( $start_date, $utc_timezone );
				$start_datetime->setTimezone( $site_timezone );
			} else {
				$start_datetime = null;
			}
			
			if ( ! empty( $end_local ) && ! empty( $event_timezone ) ) {
				// Use event's local time directly
				$event_tz = new \DateTimeZone( $event_timezone );
				$end_datetime = new \DateTime( $end_local, $event_tz );
				
				// Debug: Log what we're storing for end time
				error_log( "SG Eventbrite: Event {$event_id} - Storing event end time:" );
				error_log( "  - Event timezone: " . $event_timezone );
				error_log( "  - Event local time (from API): " . $end_local );
				error_log( "  - Parsed datetime: " . $end_datetime->format( 'Y-m-d H:i:s T' ) );
				error_log( "  - Storing date: " . $end_datetime->format( 'Y-m-d' ) );
				error_log( "  - Storing time: " . $end_datetime->format( 'H:i' ) );
			} elseif ( ! empty( $end_date ) ) {
				// Fallback: if local time not available, use UTC and convert to site timezone
				$utc_timezone = new \DateTimeZone( 'UTC' );
				$site_timezone = wp_timezone();
				$end_datetime = new \DateTime( $end_date, $utc_timezone );
				$end_datetime->setTimezone( $site_timezone );
			} else {
				$end_datetime = null;
			}
		} catch ( \Exception $e ) {
			error_log( "SG Eventbrite: Error parsing datetime - " . $e->getMessage() );
			error_log( "SG Eventbrite: start_date UTC: " . $start_date . ", start_local: " . $start_local );
			error_log( "SG Eventbrite: end_date UTC: " . $end_date . ", end_local: " . $end_local );
			error_log( "SG Eventbrite: event_timezone: " . ( $event_timezone ?? 'not set' ) );
			
			// Fallback: try parsing UTC if local time failed
			if ( ! empty( $start_date ) ) {
				try {
					$utc_timezone = new \DateTimeZone( 'UTC' );
					$site_timezone = wp_timezone();
					$start_datetime = new \DateTime( $start_date, $utc_timezone );
					$start_datetime->setTimezone( $site_timezone );
				} catch ( \Exception $e2 ) {
					error_log( "SG Eventbrite: Fallback parsing also failed: " . $e2->getMessage() );
					$start_datetime = null;
				}
			} else {
				$start_datetime = null;
			}
			
			if ( ! empty( $end_date ) ) {
				try {
					$utc_timezone = new \DateTimeZone( 'UTC' );
					$site_timezone = wp_timezone();
					$end_datetime = new \DateTime( $end_date, $utc_timezone );
					$end_datetime->setTimezone( $site_timezone );
				} catch ( \Exception $e2 ) {
					error_log( "SG Eventbrite: End date fallback parsing also failed: " . $e2->getMessage() );
					$end_datetime = null;
				}
			} else {
				$end_datetime = null;
			}
		}

		// Calculate day of week if not provided from intake form
		if ( empty( $day_of_week ) ) {
			$day_of_week = $start_datetime->format( 'l' );
		}

		// Calculate class length from start and end times if not provided from intake form
		if ( empty( $class_length ) && $start_datetime && $end_datetime ) {
			$class_length = $this->calculate_class_length( $start_datetime, $end_datetime );
		}

		// Get pricing information
		$intake_data = $options['intake_data'][ $event_id ] ?? array();
		$include_fees = $options['include_fees'] ?? false;
		$selected_ticket_class_id = $intake_data['ticket_class_id'] ?? '';
		$custom_ticket_data = array(
			'name'       => $intake_data['ticket_class_name'] ?? '',
			'price'      => $intake_data['ticket_price'] ?? '',
			'expiration' => $intake_data['ticket_expiration'] ?? '',
		);
		$pricing = $this->extract_pricing( $ticket_classes, $include_fees, $selected_ticket_class_id, $custom_ticket_data );
		
		// Detect early bird tickets
		$early_bird_data = $this->detect_early_bird_ticket( $ticket_classes, $include_fees );

		// Build location string
		$location = $this->build_location_string( $venue );

		// Create excerpt from description
		$excerpt = wp_trim_words( strip_tags( $description ), 30, '...' );

		$course_data = array(
			'title'       => $name,
			'description' => wp_kses_post( $description ), // Full event description from separate API call
			'excerpt'     => $excerpt,
			'image_url'   => $logo['url'] ?? '',
			'meta'        => array(
				'_sg_course_start_date'        => $start_datetime ? $start_datetime->format( 'Y-m-d' ) : '',
				'_sg_course_start_time'        => $start_datetime ? $start_datetime->format( 'H:i' ) : '',
				'_sg_course_start_datetime'    => $start_datetime ? $start_datetime->format( 'Y-m-d H:i:s' ) : '', // Full datetime for proper timezone handling
				'_sg_course_end_date'          => $end_datetime ? $end_datetime->format( 'Y-m-d' ) : '',
				'_sg_course_end_time'          => $end_datetime ? $end_datetime->format( 'H:i' ) : '',
				'_sg_course_end_datetime'      => $end_datetime ? $end_datetime->format( 'Y-m-d H:i:s' ) : '', // Full datetime for proper timezone handling
				'_sg_course_price'             => $pricing['display_price'],
				'_sg_course_ticket_class_id'   => $pricing['ticket_class_id'],
				'_sg_course_ticket_class_name' => $pricing['ticket_class_name'],
				'_sg_course_ticket_price'      => $pricing['base_price'],
				'_sg_course_ticket_price_total' => $pricing['total_price'],
				'_sg_course_ticket_expiration' => $pricing['ticket_expiration'],
				'_sg_course_ticket_sales_start' => $pricing['ticket_sales_start'],
				'_sg_course_location'          => $location,
				'_sg_course_eventbrite_id'     => $event_id,
				'_sg_course_eventbrite_url'    => $event['url'] ?? '',
				'_sg_course_last_imported'     => current_time( 'mysql' ),
				// From intake form or extracted keywords
				'_sg_course_instructor'        => $instructor,
				'_sg_course_class_length'      => $class_length,
				'_sg_course_course_length'     => $course_length,
				'_sg_course_day_of_week'       => $day_of_week,
				// Additional Eventbrite data
				'_sg_course_eventbrite_venue'  => $venue['name'] ?? '',
				'_sg_course_eventbrite_organizer' => $event['organizer']['name'] ?? '',
				'_sg_course_eventbrite_capacity' => $event['capacity'] ?? '',
				'_sg_course_eventbrite_status' => $event['status'] ?? '',
			),
		);
		
		// Add early bird data to meta if found
		if ( ! empty( $early_bird_data['found'] ) ) {
			$course_data['meta']['_sg_course_early_bird_price'] = $early_bird_data['price'];
			$course_data['meta']['_sg_course_early_bird_expires'] = $early_bird_data['expires'];
			$course_data['meta']['_sg_course_regular_price'] = $pricing['total_price'];
		}

		return $course_data;
	}

	/**
	 * Process Eventbrite structured content to HTML.
	 *
	 * @param array $event Event data from Eventbrite API.
	 * @return string HTML content.
	 */
	private function process_structured_content( $event ) {
		$event_id = $event['id'] ?? '';
		error_log( 'SG Eventbrite: Processing structured content for event: ' . $event_id );
		
		// Fetch structured content using the dedicated API endpoint
		$structured_content_response = $this->api->get_event_structured_content( $event_id );
		
		if ( is_wp_error( $structured_content_response ) ) {
			error_log( 'SG Eventbrite: Failed to fetch structured content: ' . $structured_content_response->get_error_message() );
			// Fallback to basic description
			return $this->get_fallback_description( $event );
		}
		
		if ( ! isset( $structured_content_response['modules'] ) || empty( $structured_content_response['modules'] ) ) {
			error_log( 'SG Eventbrite: No structured content modules found, falling back to basic description' );
			return $this->get_fallback_description( $event );
		}

		$html_content = '';
		$modules = $structured_content_response['modules'];

		// Debug: Log structured content structure
		error_log( 'SG Eventbrite: Structured content modules count: ' . count( $modules ) );

		// Process each module in the structured content
		foreach ( $modules as $module ) {
			if ( ! isset( $module['type'] ) ) {
				error_log( 'SG Eventbrite: Module missing type field: ' . print_r( $module, true ) );
				continue;
			}

			error_log( 'SG Eventbrite: Processing module type: ' . $module['type'] );
			switch ( $module['type'] ) {
				case 'text':
					$text_html = $this->process_text_module( $module );
					error_log( 'SG Eventbrite: Text module HTML length: ' . strlen( $text_html ) );
					$html_content .= $text_html;
					break;
				case 'image':
					$html_content .= $this->process_image_module( $module );
					break;
				case 'video':
					$html_content .= $this->process_video_module( $module );
					break;
				case 'list':
					$html_content .= $this->process_list_module( $module );
					break;
				default:
					// Handle unknown module types
					error_log( "SG Eventbrite: Unknown structured content module type: " . $module['type'] );
					break;
			}
		}

		// Debug: Log final HTML output
		error_log( 'SG Eventbrite: Final structured content HTML length: ' . strlen( $html_content ) . ' characters' );
		error_log( 'SG Eventbrite: Final structured content HTML: ' . substr( $html_content, 0, 500 ) . '...' );

		return $html_content;
	}
	
	/**
	 * Get fallback description when structured content is not available.
	 *
	 * @param array $event Event data.
	 * @return string HTML content.
	 */
	private function get_fallback_description( $event ) {
		if ( isset( $event['description']['html'] ) ) {
			error_log( 'SG Eventbrite: Using basic HTML description' );
			return $event['description']['html'];
		} elseif ( isset( $event['description']['text'] ) ) {
			error_log( 'SG Eventbrite: Using basic text description' );
			return wp_kses_post( $event['description']['text'] );
		}
		error_log( 'SG Eventbrite: No description content found' );
		return '';
	}

	/**
	 * Process text module from structured content.
	 *
	 * @param array $module Text module data.
	 * @return string HTML content.
	 */
	private function process_text_module( $module ) {
		error_log( 'SG Eventbrite: Processing text module: ' . print_r( $module, true ) );
		
		if ( ! isset( $module['data'] ) || ! isset( $module['data']['body'] ) ) {
			error_log( 'SG Eventbrite: Text module missing data or body' );
			return '';
		}

		$body = $module['data']['body'];
		
		// Check if the text is directly in the body
		if ( isset( $body['text'] ) ) {
			error_log( 'SG Eventbrite: Found direct text content, length: ' . strlen( $body['text'] ) );
			return wp_kses_post( $body['text'] );
		}
		
		// Fallback: check if body is an array of blocks
		if ( is_array( $body ) ) {
			error_log( 'SG Eventbrite: Text module body is array with ' . count( $body ) . ' items' );
			$html = '';
			
			// Process each block in the text module
			foreach ( $body as $block ) {
				if ( ! isset( $block['type'] ) ) {
					continue;
				}

				switch ( $block['type'] ) {
					case 'paragraph':
						$html .= '<p>' . wp_kses_post( $block['text'] ?? '' ) . '</p>';
						break;
					case 'heading':
						$level = $block['level'] ?? 2;
						$html .= '<h' . $level . '>' . wp_kses_post( $block['text'] ?? '' ) . '</h' . $level . '>';
						break;
					case 'list':
						$html .= $this->process_list_block( $block );
						break;
					case 'quote':
						$html .= '<blockquote>' . wp_kses_post( $block['text'] ?? '' ) . '</blockquote>';
						break;
					default:
						// Fallback to paragraph for unknown block types
						$html .= '<p>' . wp_kses_post( $block['text'] ?? '' ) . '</p>';
						break;
				}
			}
			
			return $html;
		}

		error_log( 'SG Eventbrite: Text module body structure not recognized' );
		return '';
	}

	/**
	 * Process image module from structured content.
	 *
	 * @param array $module Image module data.
	 * @return string HTML content.
	 */
	private function process_image_module( $module ) {
		if ( ! isset( $module['data']['url'] ) ) {
			return '';
		}

		$url = esc_url( $module['data']['url'] );
		$alt = esc_attr( $module['data']['alt_text'] ?? '' );
		$caption = isset( $module['data']['caption'] ) ? wp_kses_post( $module['data']['caption'] ) : '';

		$html = '<figure>';
		$html .= '<img src="' . $url . '" alt="' . $alt . '" />';
		if ( $caption ) {
			$html .= '<figcaption>' . $caption . '</figcaption>';
		}
		$html .= '</figure>';

		return $html;
	}

	/**
	 * Process video module from structured content.
	 *
	 * @param array $module Video module data.
	 * @return string HTML content.
	 */
	private function process_video_module( $module ) {
		if ( ! isset( $module['data']['url'] ) ) {
			return '';
		}

		$url = esc_url( $module['data']['url'] );
		$caption = isset( $module['data']['caption'] ) ? wp_kses_post( $module['data']['caption'] ) : '';

		$html = '<figure>';
		$html .= '<video controls><source src="' . $url . '" type="video/mp4">Your browser does not support the video tag.</video>';
		if ( $caption ) {
			$html .= '<figcaption>' . $caption . '</figcaption>';
		}
		$html .= '</figure>';

		return $html;
	}

	/**
	 * Process list module from structured content.
	 *
	 * @param array $module List module data.
	 * @return string HTML content.
	 */
	private function process_list_module( $module ) {
		if ( ! isset( $module['data']['items'] ) ) {
			return '';
		}

		$list_type = $module['data']['style'] === 'ordered' ? 'ol' : 'ul';
		$html = '<' . $list_type . '>';

		foreach ( $module['data']['items'] as $item ) {
			$html .= '<li>' . wp_kses_post( $item ) . '</li>';
		}

		$html .= '</' . $list_type . '>';

		return $html;
	}

	/**
	 * Process list block within text module.
	 *
	 * @param array $block List block data.
	 * @return string HTML content.
	 */
	private function process_list_block( $block ) {
		if ( ! isset( $block['items'] ) ) {
			return '';
		}

		$list_type = $block['style'] === 'ordered' ? 'ol' : 'ul';
		$html = '<' . $list_type . '>';

		foreach ( $block['items'] as $item ) {
			$html .= '<li>' . wp_kses_post( $item ) . '</li>';
		}

		$html .= '</' . $list_type . '>';

		return $html;
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

		return $extracted;
	}

	/**
	 * Calculate class length from start and end datetime.
	 * Returns a human-readable format like "2 hours" or "90 minutes".
	 *
	 * @param \DateTime $start Start datetime.
	 * @param \DateTime $end End datetime.
	 * @return string Formatted class length (e.g., "2 hours", "90 minutes").
	 */
	private function calculate_class_length( $start, $end ) {
		if ( ! $start || ! $end ) {
			return '';
		}

		$diff = $start->diff( $end );
		$total_minutes = ( $diff->h * 60 ) + $diff->i;
		
		// If less than 60 minutes, return in minutes
		if ( $total_minutes < 60 ) {
			return $total_minutes . ' ' . ( $total_minutes === 1 ? 'minute' : 'minutes' );
		}
		
		// If exact hours (no minutes), return in hours
		if ( $diff->i === 0 ) {
			return $diff->h . ' ' . ( $diff->h === 1 ? 'hour' : 'hours' );
		}
		
		// If hours and minutes, return both
		$hours = $diff->h;
		$minutes = $diff->i;
		
		$result = '';
		if ( $hours > 0 ) {
			$result .= $hours . ' ' . ( $hours === 1 ? 'hour' : 'hours' );
		}
		if ( $minutes > 0 ) {
			if ( $result ) {
				$result .= ' ';
			}
			$result .= $minutes . ' ' . ( $minutes === 1 ? 'minute' : 'minutes' );
		}
		
		return $result;
	}

	/**
	 * Extract pricing information from ticket classes.
	 *
	 * @param array  $ticket_classes Ticket classes data.
	 * @param bool   $include_fees Whether to include fees in price.
	 * @param string $selected_ticket_class_id Selected ticket class ID from intake form.
	 * @param array  $custom_ticket_data Custom ticket data from intake form.
	 * @return array Pricing information.
	 */
	private function extract_pricing( $ticket_classes, $include_fees = false, $selected_ticket_class_id = '', $custom_ticket_data = array() ) {
		$pricing = array(
			'display_price'     => 'Free',
			'base_price'         => 0,
			'total_price'        => 0,
			'currency'           => 'USD',
			'free'               => true,
			'ticket_class_id'    => '',
			'ticket_class_name'  => '',
			'ticket_expiration' => '',
			'ticket_sales_start' => '',
		);

		if ( empty( $ticket_classes ) ) {
			return $pricing;
		}

		// Find selected ticket class or use first one
		$ticket = null;
		if ( ! empty( $selected_ticket_class_id ) ) {
			foreach ( $ticket_classes as $tc ) {
				if ( isset( $tc['id'] ) && $tc['id'] === $selected_ticket_class_id ) {
					$ticket = $tc;
					break;
				}
			}
		}
		
		if ( ! $ticket ) {
			$ticket = $ticket_classes[0]; // Fallback to first ticket class
		}

		$cost = $ticket['cost'] ?? array();
		$currency = $cost['currency'] ?? 'USD';
		
		// Use custom ticket data if provided
		if ( ! empty( $custom_ticket_data['price'] ) ) {
			$base_price = floatval( $custom_ticket_data['price'] );
			$ticket_name = ! empty( $custom_ticket_data['name'] ) ? sanitize_text_field( $custom_ticket_data['name'] ) : ( $ticket['name'] ?? '' );
		} else {
			$base_price = ! empty( $cost['value'] ) ? ( $cost['value'] / 100 ) : 0; // Convert from cents
			$ticket_name = $ticket['name'] ?? '';
		}

		if ( $base_price > 0 ) {
			$total_price = $base_price;
			
			// Handle fees based on include_fees setting and ticket class fee structure
			$include_fee = $ticket['include_fee'] ?? false;
			$split_fee = $ticket['split_fee'] ?? false;
			
			if ( $include_fees && $split_fee ) {
				// Fees are split - add actual_cost + actual_fee
				$actual_cost = isset( $ticket['actual_cost'] ) ? ( $ticket['actual_cost']['value'] / 100 ) : $base_price;
				$actual_fee = isset( $ticket['actual_fee'] ) ? ( $ticket['actual_fee']['value'] / 100 ) : 0;
				$total_price = $actual_cost + $actual_fee;
			} elseif ( $include_fee && ! $split_fee ) {
				// Fees already included in cost.value
				$total_price = $base_price;
			} else {
				// Don't include fees - use base price only
				$total_price = $base_price;
			}

			$pricing['display_price'] = $currency . ' ' . number_format( $total_price, 2 );
			$pricing['base_price'] = $base_price;
			$pricing['total_price'] = $total_price;
			$pricing['currency'] = $currency;
			$pricing['free'] = false;
			
			// Store ticket class data
			$pricing['ticket_class_id'] = $ticket['id'] ?? '';
			$pricing['ticket_class_name'] = $ticket_name;
			
			// Use custom expiration or from ticket class
			if ( ! empty( $custom_ticket_data['expiration'] ) ) {
				// Convert datetime-local format to UTC ISO format
				$expiration = sanitize_text_field( $custom_ticket_data['expiration'] );
				if ( ! empty( $expiration ) ) {
					// datetime-local format: YYYY-MM-DDTHH:MM
					// Convert to UTC ISO format for storage
					try {
						$expiration_dt = new \DateTime( $expiration, wp_timezone() );
						$expiration_dt->setTimezone( new \DateTimeZone( 'UTC' ) );
						$pricing['ticket_expiration'] = $expiration_dt->format( 'Y-m-d\TH:i:s\Z' );
					} catch ( \Exception $e ) {
						// Fallback: use as-is if conversion fails
						error_log( 'SG Eventbrite: Error converting expiration datetime: ' . $e->getMessage() );
						$pricing['ticket_expiration'] = $expiration;
					}
				}
			} elseif ( isset( $ticket['sales_end'] ) ) {
				// Handle different sales_end structures
				if ( isset( $ticket['sales_end']['utc'] ) ) {
					$pricing['ticket_expiration'] = $ticket['sales_end']['utc'];
				} elseif ( is_string( $ticket['sales_end'] ) ) {
					$pricing['ticket_expiration'] = $ticket['sales_end'];
				}
			}
			
			if ( isset( $ticket['sales_start'] ) ) {
				// Handle different sales_start structures
				if ( isset( $ticket['sales_start']['utc'] ) ) {
					$pricing['ticket_sales_start'] = $ticket['sales_start']['utc'];
				} elseif ( is_string( $ticket['sales_start'] ) ) {
					$pricing['ticket_sales_start'] = $ticket['sales_start'];
				}
			}
		}

		return $pricing;
	}

	/**
	 * Detect early bird ticket class from ticket classes.
	 *
	 * @param array $ticket_classes Ticket classes data.
	 * @param bool  $include_fees Whether to include fees in price calculation.
	 * @return array Early bird data with 'found', 'price', and 'expires' keys.
	 */
	private function detect_early_bird_ticket( $ticket_classes, $include_fees = false ) {
		$result = array(
			'found'   => false,
			'price'   => 0,
			'expires' => '',
		);

		if ( empty( $ticket_classes ) || count( $ticket_classes ) < 2 ) {
			return $result; // Need at least 2 ticket classes to compare
		}

		$now = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$lowest_price = null;
		$lowest_ticket = null;

		// Find the lowest priced ticket class that hasn't expired
		foreach ( $ticket_classes as $ticket ) {
			$cost = $ticket['cost'] ?? array();
			if ( empty( $cost['value'] ) || $cost['value'] <= 0 ) {
				continue; // Skip free tickets
			}

			// Check if ticket sales have ended
			if ( isset( $ticket['sales_end'] ) && isset( $ticket['sales_end']['utc'] ) ) {
				$sales_end = new \DateTime( $ticket['sales_end']['utc'], new \DateTimeZone( 'UTC' ) );
				if ( $sales_end < $now ) {
					continue; // This ticket class has expired
				}
			}

			// Calculate price (with or without fees)
			$base_price = $cost['value'] / 100;
			$price = $base_price;

			if ( $include_fees ) {
				$split_fee = $ticket['split_fee'] ?? false;
				if ( $split_fee ) {
					$actual_cost = isset( $ticket['actual_cost'] ) ? ( $ticket['actual_cost']['value'] / 100 ) : $base_price;
					$actual_fee = isset( $ticket['actual_fee'] ) ? ( $ticket['actual_fee']['value'] / 100 ) : 0;
					$price = $actual_cost + $actual_fee;
				}
			}

			// Track lowest price ticket
			if ( $lowest_price === null || $price < $lowest_price ) {
				$lowest_price = $price;
				$lowest_ticket = $ticket;
			}
		}

		// Check if there's a higher priced ticket (meaning we found an early bird)
		if ( $lowest_ticket ) {
			foreach ( $ticket_classes as $ticket ) {
				if ( $ticket['id'] === $lowest_ticket['id'] ) {
					continue; // Skip the lowest price ticket
				}

				$cost = $ticket['cost'] ?? array();
				if ( empty( $cost['value'] ) || $cost['value'] <= 0 ) {
					continue;
				}

				$base_price = $cost['value'] / 100;
				$price = $base_price;

				if ( $include_fees ) {
					$split_fee = $ticket['split_fee'] ?? false;
					if ( $split_fee ) {
						$actual_cost = isset( $ticket['actual_cost'] ) ? ( $ticket['actual_cost']['value'] / 100 ) : $base_price;
						$actual_fee = isset( $ticket['actual_fee'] ) ? ( $ticket['actual_fee']['value'] / 100 ) : 0;
						$price = $actual_cost + $actual_fee;
					}
				}

				// If we find a higher priced ticket, we have an early bird
				if ( $price > $lowest_price ) {
					$result['found'] = true;
					$result['price'] = $lowest_price;
					if ( isset( $lowest_ticket['sales_end'] ) && isset( $lowest_ticket['sales_end']['utc'] ) ) {
						$result['expires'] = $lowest_ticket['sales_end']['utc'];
					}
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * Get dynamic price for a course (checks early bird expiry).
	 *
	 * @param int $post_id Course post ID.
	 * @return string Display price.
	 */
	public static function get_dynamic_price( $post_id ) {
		$early_bird_expires = get_post_meta( $post_id, '_sg_course_early_bird_expires', true );
		
		if ( ! empty( $early_bird_expires ) ) {
			$expires_time = strtotime( $early_bird_expires );
			$now = time();
			
			if ( $now < $expires_time ) {
				// Early bird is still active
				$early_bird_price = get_post_meta( $post_id, '_sg_course_early_bird_price', true );
				$currency = get_post_meta( $post_id, '_sg_course_price', true );
				// Extract currency from display price if available
				$currency_code = 'USD';
				if ( preg_match( '/^([A-Z]{3})\s/', $currency, $matches ) ) {
					$currency_code = $matches[1];
				}
				return $currency_code . ' ' . number_format( floatval( $early_bird_price ), 2 );
			} else {
				// Early bird has expired, return regular price
				$regular_price = get_post_meta( $post_id, '_sg_course_regular_price', true );
				if ( ! empty( $regular_price ) ) {
					$currency = get_post_meta( $post_id, '_sg_course_price', true );
					$currency_code = 'USD';
					if ( preg_match( '/^([A-Z]{3})\s/', $currency, $matches ) ) {
						$currency_code = $matches[1];
					}
					return $currency_code . ' ' . number_format( floatval( $regular_price ), 2 );
				}
			}
		}
		
		// No early bird, return stored price
		return get_post_meta( $post_id, '_sg_course_price', true );
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
			error_log( "SG Eventbrite: Set featured image for course {$post_id}" );
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
	private function set_course_taxonomies( $post_id, $event, $intake_data = array() ) {
		// Set categories from intake form
		if ( ! empty( $intake_data['categories'] ) && is_array( $intake_data['categories'] ) ) {
			$category_ids = array_map( 'intval', $intake_data['categories'] );
			wp_set_post_terms( $post_id, $category_ids, 'sg_course_category' );
		} else {
			// Fallback: Set categories based on Eventbrite categories
			$categories = $event['category_id'] ?? '';
			if ( $categories ) {
				// You can map Eventbrite categories to your custom categories here
				// For now, we'll create a generic category
				$term = wp_insert_term( 'Imported Course', 'sg_course_category' );
				if ( ! is_wp_error( $term ) ) {
					wp_set_post_terms( $post_id, array( $term['term_id'] ), 'sg_course_category' );
				}
			}
		}

		// Set tags from intake form
		if ( ! empty( $intake_data['tags'] ) ) {
			$tags_string = sanitize_text_field( $intake_data['tags'] );
			$tags = array_map( 'trim', explode( ',', $tags_string ) );
			$tags = array_filter( $tags ); // Remove empty tags
			
			if ( ! empty( $tags ) ) {
				wp_set_post_terms( $post_id, $tags, 'sg_course_tag' );
			}
		} else {
			// Fallback: Set tags based on Eventbrite subcategories or keywords
			$subcategories = $event['subcategory_id'] ?? '';
			if ( $subcategories ) {
				$term = wp_insert_term( 'Eventbrite Import', 'sg_course_tag' );
				if ( ! is_wp_error( $term ) ) {
					wp_set_post_terms( $post_id, array( $term['term_id'] ), 'sg_course_tag' );
				}
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