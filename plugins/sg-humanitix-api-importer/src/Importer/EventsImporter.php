<?php
/**
 * Events Importer Class.
 *
 * Handles the import of events from Humanitix API to The Events Calendar plugin.
 * Manages event creation, updates, venue/organizer processing, and image handling.
 *
 * @package SG\HumanitixApiImporter\Importer
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Importer;

use SG\HumanitixApiImporter\HumanitixAPI;
use SG\HumanitixApiImporter\Admin\Logger;
use SG\HumanitixApiImporter\Importer\DataMapper;

/**
 * Events Importer Class.
 *
 * Handles the import of events from Humanitix API to The Events Calendar plugin.
 * Manages event creation, updates, venue/organizer processing, and image handling.
 *
 * @package SG\HumanitixApiImporter\Importer
 * @since 1.0.0
 */
class EventsImporter {

	/**
	 * The Humanitix API instance.
	 *
	 * @var HumanitixAPI
	 */
	private $api;

	/**
	 * The logger instance.
	 *
	 * @var Logger
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
	 * @param HumanitixAPI $api The Humanitix API instance.
	 * @param Logger       $logger Optional logger instance.
	 */
	public function __construct( HumanitixAPI $api, Logger $logger = null ) {
		$this->api    = $api;
		$this->logger = $logger ? $logger : new \SG\HumanitixApiImporter\Admin\Logger();
	}

	/**
	 * Import events from Humanitix API.
	 *
	 * @param int $page Page number to import (>= 1).
	 * @return array Import result.
	 */
	public function import_events( $page = 1 ) {
		error_log( 'Humanitix EventsImporter: Starting import_events with page: ' . $page );

		try {
			// Get events from Humanitix API.
			error_log( 'Humanitix EventsImporter: Calling api->get_events()' );
			$events = $this->api->get_events( $page );
			error_log( 'Humanitix EventsImporter: API response: ' . print_r( $events, true ) );

			if ( is_wp_error( $events ) ) {
				error_log( 'Humanitix EventsImporter: API returned WP_Error: ' . $events->get_error_message() );
				return array(
					'success'  => false,
					'message'  => 'Failed to fetch events: ' . $events->get_error_message(),
					'imported' => 0,
					'errors'   => array( 'Failed to fetch events: ' . $events->get_error_message() ),
				);
			}

			if ( empty( $events ) ) {
				error_log( 'Humanitix EventsImporter: No events returned from API' );
				return array(
					'success'  => true,
					'message'  => 'No events found to import.',
					'imported' => 0,
					'errors'   => array(),
				);
			}

			error_log( 'Humanitix EventsImporter: Processing ' . count( $events ) . ' events' );
			$imported_count = 0;
			$errors         = array();

			foreach ( $events as $index => $event ) {
				error_log( 'Humanitix EventsImporter: Processing event ' . ( $index + 1 ) . ': ' . ( $event['name'] ?? 'Unknown' ) );
				$result = $this->import_single_event( $event );
				if ( $result['success'] ) {
					++$imported_count;
					error_log( 'Humanitix EventsImporter: Event imported successfully' );
				} else {
					$errors[] = $result['message'];
					error_log( 'Humanitix EventsImporter: Event import failed: ' . $result['message'] );
				}
			}

			$message = sprintf(
				'Successfully imported %d events from page %d.',
				$imported_count,
				$page
			);

			if ( ! empty( $errors ) ) {
				$message .= ' Errors: ' . implode( ', ', $errors );
			}

			error_log( 'Humanitix EventsImporter: Import completed. Imported: ' . $imported_count . ', Errors: ' . count( $errors ) );

			return array(
				'success'  => $imported_count > 0,
				'message'  => $message,
				'imported' => $imported_count,
				'errors'   => $errors,
			);

		} catch ( \Exception $e ) {
			error_log( 'Humanitix EventsImporter: Exception caught: ' . $e->getMessage() );
			return array(
				'success'  => false,
				'message'  => 'Import failed: ' . $e->getMessage(),
				'imported' => 0,
				'errors'   => array( 'Import failed: ' . $e->getMessage() ),
			);
		}
	}

	/**
	 * Import a single event.
	 *
	 * @param array $event_data Humanitix event data.
	 * @return array Import result.
	 */
	public function import_single_event( $event_data ) {
		try {
			// Use DataMapper to convert Humanitix format to TEC format.
			$mapper       = new DataMapper();
			$mapped_event = $mapper->map_event( $event_data );

			if ( empty( $mapped_event ) ) {
				return array(
					'success' => false,
					'message' => 'Failed to map event data for event: ' . ( $event_data['name'] ?? 'Unknown' ),
				);
			}

			// Check if event already exists by Humanitix ID.
			$existing_event = $this->find_existing_event( $event_data['_id'] ?? '' );

			if ( $existing_event ) {
				// Update existing event.
				$post_id = wp_update_post( array_merge( $mapped_event, array( 'ID' => $existing_event ) ) );
				$action  = 'updated';
			} else {
				// Create new event.
				$post_id = wp_insert_post( $mapped_event );
				$action  = 'created';
			}

			if ( is_wp_error( $post_id ) ) {
				return array(
					'success' => false,
					'message' => 'Failed to ' . $action . ' event: ' . $post_id->get_error_message(),
				);
			}

			// Update meta fields.
			$this->update_event_meta( $post_id, $mapped_event['meta_input'] );

			// Log the import.
			$this->logger->log(
				'import',
				'Event ' . $action,
				array(
					'post_id'      => $post_id,
					'humanitix_id' => $event_data['_id'] ?? '',
					'action'       => $action,
					'event_title'  => $mapped_event['post_title'] ?? '',
				)
			);

			return array(
				'success' => true,
				'message' => 'Event ' . $action . ' successfully',
				'post_id' => $post_id,
				'action'  => $action,
			);

		} catch ( \Exception $e ) {
			$this->logger->log(
				'error',
				'Failed to import event',
				array(
					'error'      => $e->getMessage(),
					'event_data' => $event_data,
				)
			);

			return array(
				'success' => false,
				'message' => 'Import failed: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Create new event in The Events Calendar.
	 *
	 * Maps Humanitix event data to TEC format and creates the event.
	 *
	 * @since 1.0.0
	 * @param array $event_data The event data from Humanitix API.
	 * @return void
	 * @throws \Exception When event creation fails.
	 */
	private function create_event( $event_data ) {
		$event_title = $event_data['title'] ?? 'Unknown Event';

		try {
			// Map Humanitix fields to TEC fields.
			$tec_event_data = $this->map_event_fields( $event_data );

			// Create the event using TEC functions.
			$event_id = tribe_create_event( $tec_event_data );

			if ( $event_id ) {
				// Store external ID for future reference.
				update_post_meta( $event_id, '_humanitix_event_id', $event_data['id'] );
				update_post_meta( $event_id, '_humanitix_last_import', current_time( 'mysql' ) );

				$this->imported_events[] = $event_id;

				$this->logger->log(
					'info',
					"Successfully created event: {$event_title}",
					array(
						'wordpress_id' => $event_id,
						'humanitix_id' => $event_data['id'],
						'venue_id'     => $tec_event_data['Venue'][0] ?? null,
						'organizer_id' => $tec_event_data['Organizer'][0] ?? null,
						'has_image'    => ! empty( $tec_event_data['_thumbnail_id'] ),
					)
				);
			} else {
				throw new \Exception( 'Failed to create event in WordPress' );
			}
		} catch ( \Exception $e ) {
			$this->logger->log(
				'error',
				"Failed to create event: {$event_title}",
				array(
					'humanitix_id' => $event_data['id'],
					'error'        => $e->getMessage(),
				)
			);
			throw $e;
		}
	}

	/**
	 * Update existing event.
	 *
	 * Updates an existing event with new data from Humanitix API.
	 *
	 * @since 1.0.0
	 * @param int   $existing_event_id The WordPress post ID of the existing event.
	 * @param array $event_data The updated event data from Humanitix API.
	 * @return void
	 * @throws \Exception When event update fails.
	 */
	private function update_event( $existing_event_id, $event_data ) {
		$event_title = $event_data['title'] ?? 'Unknown Event';

		try {
			$tec_event_data       = $this->map_event_fields( $event_data );
			$tec_event_data['ID'] = $existing_event_id;

			$updated = tribe_update_event( $existing_event_id, $tec_event_data );

			if ( $updated ) {
				update_post_meta( $existing_event_id, '_humanitix_last_import', current_time( 'mysql' ) );
				$this->imported_events[] = $existing_event_id;

				$this->logger->log(
					'info',
					"Successfully updated event: {$event_title}",
					array(
						'wordpress_id' => $existing_event_id,
						'humanitix_id' => $event_data['id'],
					)
				);
			} else {
				throw new \Exception( 'Failed to update event in WordPress' );
			}
		} catch ( \Exception $e ) {
			$this->logger->log(
				'error',
				"Failed to update event: {$event_title}",
				array(
					'wordpress_id' => $existing_event_id,
					'humanitix_id' => $event_data['id'],
					'error'        => $e->getMessage(),
				)
			);
			throw $e;
		}
	}

	/**
	 * Map Humanitix event fields to The Events Calendar fields.
	 *
	 * Converts Humanitix API data format to TEC-compatible format.
	 *
	 * @since 1.0.0
	 * @param array $humanitix_event The event data from Humanitix API.
	 * @return array The mapped event data for TEC.
	 */
	private function map_event_fields( $humanitix_event ) {
		// Process venue.
		$venue_id = $this->process_venue( $humanitix_event['venue'] ?? array() );

		// Process organizer.
		$organizer_id = $this->process_organizer( $humanitix_event['organizer'] ?? array() );

		// Process event image.
		$featured_image_id = $this->process_event_image( $humanitix_event['image'] ?? '' );

		$tec_event_data = array(
			'post_title'          => $humanitix_event['title'] ?? '',
			'post_content'        => $humanitix_event['description'] ?? '',
			'post_excerpt'        => $humanitix_event['short_description'] ?? '',
			'EventStartDate'      => $this->format_date( $humanitix_event['start_date'] ),
			'EventEndDate'        => $this->format_date( $humanitix_event['end_date'] ),
			'EventStartHour'      => $this->extract_hour( $humanitix_event['start_date'] ),
			'EventStartMinute'    => $this->extract_minute( $humanitix_event['start_date'] ),
			'EventEndHour'        => $this->extract_hour( $humanitix_event['end_date'] ),
			'EventEndMinute'      => $this->extract_minute( $humanitix_event['end_date'] ),
			'EventShowMapLink'    => true,
			'EventShowMap'        => true,
			'EventURL'            => $humanitix_event['url'] ?? '',
			'EventCost'           => $this->format_cost( $humanitix_event['pricing'] ?? array() ),
			'EventCurrencySymbol' => '$',
			'post_status'         => 'publish',
		);

		// Add venue if available.
		if ( $venue_id ) {
			$tec_event_data['Venue'] = array( $venue_id );
		}

		// Add organizer if available.
		if ( $organizer_id ) {
			$tec_event_data['Organizer'] = array( $organizer_id );
		}

		// Add featured image if available.
		if ( $featured_image_id ) {
			$tec_event_data['_thumbnail_id'] = $featured_image_id;
		}

		return $tec_event_data;
	}

	/**
	 * Process venue data.
	 *
	 * Creates or finds existing venue and returns the venue ID.
	 *
	 * @since 1.0.0
	 * @param array $venue_data The venue data from Humanitix API.
	 * @return int|null The venue ID or null if creation failed.
	 */
	private function process_venue( $venue_data ) {
		if ( empty( $venue_data ) ) {
			return null;
		}

		$venue_name = $venue_data['name'] ?? 'Unknown Venue';

		// Check if venue already exists.
		$existing_venue = $this->find_existing_venue( $venue_name );

		if ( $existing_venue ) {
			$this->logger->log(
				'info',
				"Using existing venue: {$venue_name}",
				array(
					'venue_id'           => $existing_venue,
					'humanitix_venue_id' => $venue_data['id'] ?? null,
				)
			);
			return $existing_venue;
		}

		// Create new venue.
		$venue_id = tribe_create_venue(
			array(
				'Venue'   => $venue_name,
				'Address' => $venue_data['address'] ?? '',
				'City'    => $venue_data['city'] ?? '',
				'State'   => $venue_data['state'] ?? '',
				'Zip'     => $venue_data['postal_code'] ?? '',
				'Country' => $venue_data['country'] ?? '',
				'Phone'   => $venue_data['phone'] ?? '',
				'Website' => $venue_data['website'] ?? '',
			)
		);

		if ( $venue_id ) {
			// Store external venue ID for future reference.
			update_post_meta( $venue_id, '_humanitix_venue_id', $venue_data['id'] ?? '' );

			$this->logger->log(
				'info',
				"Created new venue: {$venue_name}",
				array(
					'venue_id'           => $venue_id,
					'humanitix_venue_id' => $venue_data['id'] ?? null,
					'address'            => $venue_data['address'] ?? null,
					'city'               => $venue_data['city'] ?? null,
				)
			);
		} else {
			$this->logger->log(
				'error',
				"Failed to create venue: {$venue_name}",
				array(
					'humanitix_venue_id' => $venue_data['id'] ?? null,
				)
			);
		}

		return $venue_id;
	}

	/**
	 * Process organizer data.
	 *
	 * Creates or finds existing organizer and returns the organizer ID.
	 *
	 * @since 1.0.0
	 * @param array $organizer_data The organizer data from Humanitix API.
	 * @return int|null The organizer ID or null if creation failed.
	 */
	private function process_organizer( $organizer_data ) {
		if ( empty( $organizer_data ) ) {
			return null;
		}

		$organizer_name = $organizer_data['name'] ?? 'Unknown Organizer';

		// Check if organizer already exists.
		$existing_organizer = $this->find_existing_organizer( $organizer_name );

		if ( $existing_organizer ) {
			$this->logger->log(
				'info',
				"Using existing organizer: {$organizer_name}",
				array(
					'organizer_id'           => $existing_organizer,
					'humanitix_organizer_id' => $organizer_data['id'] ?? null,
				)
			);
			return $existing_organizer;
		}

		// Create new organizer.
		$organizer_id = tribe_create_organizer(
			array(
				'Organizer' => $organizer_name,
				'Email'     => $organizer_data['email'] ?? '',
				'Website'   => $organizer_data['website'] ?? '',
				'Phone'     => $organizer_data['phone'] ?? '',
			)
		);

		if ( $organizer_id ) {
			// Store external organizer ID for future reference.
			update_post_meta( $organizer_id, '_humanitix_organizer_id', $organizer_data['id'] ?? '' );

			$this->logger->log(
				'info',
				"Created new organizer: {$organizer_name}",
				array(
					'organizer_id'           => $organizer_id,
					'humanitix_organizer_id' => $organizer_data['id'] ?? null,
					'email'                  => $organizer_data['email'] ?? null,
				)
			);
		} else {
			$this->logger->log(
				'error',
				"Failed to create organizer: {$organizer_name}",
				array(
					'humanitix_organizer_id' => $organizer_data['id'] ?? null,
				)
			);
		}

		return $organizer_id;
	}

	/**
	 * Process event image.
	 *
	 * Downloads and attaches event image as featured image.
	 *
	 * @since 1.0.0
	 * @param string $image_url The URL of the event image.
	 * @return int|null The attachment ID or null if processing failed.
	 */
	private function process_event_image( $image_url ) {
		if ( empty( $image_url ) ) {
			return null;
		}

		$this->logger->log(
			'info',
			'Processing event image',
			array(
				'image_url' => $image_url,
			)
		);

		// Download and attach image.
		$attachment_id = $this->download_and_attach_image( $image_url );

		if ( $attachment_id ) {
			$this->logger->log(
				'info',
				'Successfully processed event image',
				array(
					'attachment_id' => $attachment_id,
					'image_url'     => $image_url,
				)
			);
		} else {
			$this->logger->log(
				'warning',
				'Failed to process event image',
				array(
					'image_url' => $image_url,
				)
			);
		}

		return $attachment_id;
	}

	/**
	 * Find existing venue by name.
	 *
	 * Searches for an existing venue with the given name.
	 *
	 * @since 1.0.0
	 * @param string $venue_name The name of the venue to search for.
	 * @return int|false The venue ID or false if not found.
	 */
	private function find_existing_venue( $venue_name ) {
		if ( empty( $venue_name ) ) {
			return false;
		}

		$args = array(
			'post_type'      => 'tribe_venue',
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_VenueVenue',
					'value'   => $venue_name,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
		);

		$query = new \WP_Query( $args );
		return $query->have_posts() ? $query->posts[0]->ID : false;
	}

	/**
	 * Find existing organizer by name.
	 *
	 * Searches for an existing organizer with the given name.
	 *
	 * @since 1.0.0
	 * @param string $organizer_name The name of the organizer to search for.
	 * @return int|false The organizer ID or false if not found.
	 */
	private function find_existing_organizer( $organizer_name ) {
		if ( empty( $organizer_name ) ) {
			return false;
		}

		$args = array(
			'post_type'      => 'tribe_organizer',
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_OrganizerOrganizer',
					'value'   => $organizer_name,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
		);

		$query = new \WP_Query( $args );
		return $query->have_posts() ? $query->posts[0]->ID : false;
	}

	/**
	 * Download and attach image.
	 *
	 * Downloads an image from URL and creates a WordPress attachment.
	 *
	 * @since 1.0.0
	 * @param string $image_url The URL of the image to download.
	 * @return int|null The attachment ID or null if download failed.
	 */
	private function download_and_attach_image( $image_url ) {
		// Download image and create attachment.
		$upload = media_sideload_image( $image_url, 0, '', 'id' );

		if ( is_wp_error( $upload ) ) {
			return null;
		}

		return $upload;
	}

	/**
	 * Find existing event by Humanitix ID.
	 *
	 * @param string $humanitix_id Humanitix event ID.
	 * @return int|false Post ID if found, false otherwise.
	 */
	private function find_existing_event( $humanitix_id ) {
		if ( empty( $humanitix_id ) ) {
			return false;
		}

		$args = array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'     => 'humanitix_event_id',
					'value'   => $humanitix_id,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);

		$query = new \WP_Query( $args );
		$posts = $query->posts;

		return ! empty( $posts ) ? $posts[0] : false;
	}

	/**
	 * Format date string to Y-m-d format.
	 *
	 * @since 1.0.0
	 * @param string $date_string The date string to format.
	 * @return string The formatted date.
	 */
	private function format_date( $date_string ) {
		// Convert Humanitix date format to Y-m-d.
		return gmdate( 'Y-m-d', strtotime( $date_string ) );
	}

	/**
	 * Extract hour from date string.
	 *
	 * @since 1.0.0
	 * @param string $date_string The date string to extract hour from.
	 * @return string The hour in 24-hour format.
	 */
	private function extract_hour( $date_string ) {
		return gmdate( 'H', strtotime( $date_string ) );
	}

	/**
	 * Extract minute from date string.
	 *
	 * @since 1.0.0
	 * @param string $date_string The date string to extract minute from.
	 * @return string The minute.
	 */
	private function extract_minute( $date_string ) {
		return gmdate( 'i', strtotime( $date_string ) );
	}

	/**
	 * Format pricing information for TEC.
	 *
	 * Converts Humanitix pricing array to TEC-compatible cost string.
	 *
	 * @since 1.0.0
	 * @param array $pricing The pricing array from Humanitix API.
	 * @return string The formatted cost string.
	 */
	private function format_cost( $pricing ) {
		// Format pricing information for TEC.
		if ( empty( $pricing ) ) {
			return 'Free';
		}

		$costs = array();
		foreach ( $pricing as $ticket ) {
			$costs[] = $ticket['name'] . ': $' . $ticket['price'];
		}

		return implode( ', ', $costs );
	}

	/**
	 * Update event meta fields.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $meta_data Meta data to update.
	 */
	private function update_event_meta( $post_id, $meta_data ) {
		if ( empty( $meta_data ) || ! is_array( $meta_data ) ) {
			return;
		}

		foreach ( $meta_data as $meta_key => $meta_value ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}
	}

	/**
	 * Get import statistics.
	 *
	 * Returns statistics about the current import process.
	 *
	 * @since 1.0.0
	 * @return array Import statistics including counts and error details.
	 */
	public function get_import_stats() {
		return array(
			'imported'       => $this->imported_events,
			'errors'         => $this->errors,
			'total_imported' => count( $this->imported_events ),
			'total_errors'   => count( $this->errors ),
		);
	}
}
