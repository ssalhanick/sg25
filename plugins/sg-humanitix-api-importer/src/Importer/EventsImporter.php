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
use SG\HumanitixApiImporter\Logger;

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
		$this->logger = $logger ? $logger : new Logger();
	}

	/**
	 * Main import method.
	 *
	 * Fetches events from Humanitix API and imports them into The Events Calendar.
	 *
	 * @since 1.0.0
	 * @param array $options Import options and filters.
	 * @return array Import results with success status, count, and errors.
	 */
	public function import_events( $options = array() ) {
		$this->start_time      = microtime( true );
		$this->imported_events = array();
		$this->errors          = array();

		$this->logger->log(
			'info',
			'Starting event import process',
			array(
				'options'   => $options,
				'timestamp' => current_time( 'mysql' ),
			)
		);

		try {
			// Fetch events from Humanitix API.
			$this->logger->log( 'info', 'Fetching events from Humanitix API' );
			$events = $this->api->get_events( $options );

			$this->logger->log(
				'info',
				'Retrieved events from API',
				array(
					'event_count' => count( $events ),
				)
			);

			foreach ( $events as $event_data ) {
				$this->import_single_event( $event_data );
			}

			$duration = round( microtime( true ) - $this->start_time, 2 );

			$this->logger->log(
				'import',
				'Import process completed',
				array(
					'events_imported' => count( $this->imported_events ),
					'errors'          => count( $this->errors ),
					'duration'        => $duration,
					'success_rate'    => count( $events ) > 0 ? round( ( count( $this->imported_events ) / count( $events ) ) * 100, 2 ) : 0,
				)
			);

			return array(
				'success'  => true,
				'imported' => count( $this->imported_events ),
				'errors'   => $this->errors,
			);

		} catch ( \Exception $e ) {
			$duration = round( microtime( true ) - $this->start_time, 2 );

			$this->logger->log(
				'error',
				'Import process failed',
				array(
					'error'           => $e->getMessage(),
					'duration'        => $duration,
					'events_imported' => count( $this->imported_events ),
					'errors'          => count( $this->errors ),
				)
			);

			return array(
				'success'  => false,
				'error'    => $e->getMessage(),
				'imported' => count( $this->imported_events ),
				'errors'   => $this->errors,
			);
		}
	}

	/**
	 * Import a single event.
	 *
	 * Processes individual event data and either creates or updates the event.
	 *
	 * @since 1.0.0
	 * @param array $event_data The event data from Humanitix API.
	 * @return void
	 */
	private function import_single_event( $event_data ) {
		$event_title = $event_data['title'] ?? 'Unknown Event';
		$event_id    = $event_data['id'] ?? 'unknown';

		$this->logger->log(
			'info',
			"Processing event: {$event_title}",
			array(
				'humanitix_id' => $event_id,
				'event_title'  => $event_title,
			)
		);

		try {
			// Check if event already exists (by external ID).
			$existing_event = $this->find_existing_event( $event_id );

			if ( $existing_event ) {
				$this->logger->log(
					'info',
					"Updating existing event: {$event_title}",
					array(
						'wordpress_id' => $existing_event,
						'humanitix_id' => $event_id,
					)
				);
				$this->update_event( $existing_event, $event_data );
			} else {
				$this->logger->log(
					'info',
					"Creating new event: {$event_title}",
					array(
						'humanitix_id' => $event_id,
					)
				);
				$this->create_event( $event_data );
			}
		} catch ( \Exception $e ) {
			$error_message  = "Failed to import event {$event_title}: " . $e->getMessage();
			$this->errors[] = $error_message;

			$this->logger->log(
				'error',
				$error_message,
				array(
					'humanitix_id' => $event_id,
					'event_title'  => $event_title,
					'exception'    => $e->getMessage(),
				)
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
	 * Searches for an existing event using the Humanitix event ID.
	 *
	 * @since 1.0.0
	 * @param string $humanitix_id The Humanitix event ID.
	 * @return int|false The event ID or false if not found.
	 */
	private function find_existing_event( $humanitix_id ) {
		$args = array(
			'post_type'      => 'tribe_events',
			'meta_query'     => array(
				array(
					'key'     => '_humanitix_event_id',
					'value'   => $humanitix_id,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
		);

		$query = new \WP_Query( $args );
		return $query->have_posts() ? $query->posts[0]->ID : false;
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
