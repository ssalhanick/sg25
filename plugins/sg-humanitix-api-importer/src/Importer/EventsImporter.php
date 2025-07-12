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
	 * @param int|null $import_limit Optional limit on number of events to import (for debugging).
	 * @return array Import result.
	 */
	public function import_events( $page = 1, $import_limit = null ) {
		// Initialize debug helper
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $this->logger );
		
		$debug_helper->log( 'Importer', "Starting import_events with page: {$page}" . ( $import_limit ? ", limit: {$import_limit}" : '' ) );
		
		// Debug: Check what Humanitix IDs are already stored
		$this->debug_check_stored_humanitix_ids();
		
		try {
			// Get events from Humanitix API.
			$debug_helper->log( 'API', 'Calling get_events()' );
			$events = $this->api->get_events( $page );

			if ( is_wp_error( $events ) ) {
				$debug_helper->log_error( 'API', 'API returned WP_Error: ' . $events->get_error_message() );
				return array(
					'success'  => false,
					'message'  => 'Failed to fetch events: ' . $events->get_error_message(),
					'imported' => 0,
					'errors'   => array( 'Failed to fetch events: ' . $events->get_error_message() ),
				);
			}

			if ( empty( $events ) ) {
				$debug_helper->log( 'API', 'No events returned from API' );
				return array(
					'success'  => true,
					'message'  => 'No events found to import.',
					'imported' => 0,
					'errors'   => array(),
				);
			}

			// Apply import limit if specified (for debugging)
			if ( $import_limit && is_numeric( $import_limit ) ) {
				$original_count = count( $events );
				$events = array_slice( $events, 0, intval( $import_limit ) );
				$debug_helper->log( 'Importer', "Limited import from {$original_count} to " . count( $events ) . ' events' );
			}

			$debug_helper->log( 'Importer', 'Processing ' . count( $events ) . ' events' );

			$imported_count = 0;
			$errors         = array();

			foreach ( $events as $event ) {
				$result = $this->import_single_event( $event );
				if ( $result['success'] ) {
					++$imported_count;
				} else {
					$errors[] = $result['message'];
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

			// Log concise import summary
			$this->logger->log_import_summary( $imported_count, $errors );

			return array(
				'success'  => $imported_count > 0,
				'message'  => $message,
				'imported' => $imported_count,
				'errors'   => $errors,
			);

		} catch ( \Exception $e ) {
			$debug_helper->log_error( 'Importer', 'Exception caught: ' . $e->getMessage() );
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
			$humanitix_id = $event_data['_id'] ?? 'unknown';
			$event_name = $event_data['name'] ?? 'Unknown';
			
			// Initialize debug helper
			$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $this->logger );
			
			$debug_helper->log_event_processing( $event_name, $humanitix_id, $event_data, 'process' );

			// Use DataMapper to convert Humanitix format to TEC format.
			$mapper       = new DataMapper();
			$mapped_event = $mapper->map_event( $event_data );

			if ( empty( $mapped_event ) ) {
				$debug_helper->log_error( 'DataMapper', 'DataMapper returned empty mapped event' );
				return array(
					'success' => false,
					'message' => 'Failed to map event data for event: ' . ( $event_data['name'] ?? 'Unknown' ),
				);
			}

			// Process venue data from the mapped event
			$venue_id = $this->process_venue_from_mapped_event( $mapped_event, $event_data );

			// Note: Humanitix doesn't provide organizer data in their API
			// They only provide organiserId as a reference, not organizer details
			$organizer_id = null;

			// Check if event already exists by Humanitix ID.
			$humanitix_id = $event_data['_id'] ?? '';
			$debug_helper->log( 'Importer', "Checking for existing event with humanitix_id: {$humanitix_id}" );
			
			$existing_event = $this->find_existing_event( $humanitix_id );

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
				$debug_helper->log_critical_error( 'Importer', "Failed to {$action} event: " . $post_id->get_error_message(), array(
					'event_name' => $event_name,
					'humanitix_id' => $humanitix_id,
					'action' => $action,
				) );
				return array(
					'success' => false,
					'message' => 'Failed to ' . $action . ' event: ' . $post_id->get_error_message(),
				);
			}

			// Log event status inline
			$debug_helper->log_event_status( $event_name, $post_id, $action, array(
				'humanitix_id' => $humanitix_id,
				'venue_id' => $venue_id,
			) );

			// Update meta fields.
			$this->update_event_meta( $post_id, $mapped_event['meta_input'] );

			// Store Humanitix ID for future reference
			update_post_meta( $post_id, '_humanitix_event_id', $event_data['_id'] ?? '' );

			// Link venue to event if venue was created/found
			if ( $venue_id ) {
				update_post_meta( $post_id, '_EventVenueID', $venue_id );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "Humanitix EventsImporter: Linked venue ID {$venue_id} to event {$post_id}" );
				}
			}

			// Link venue to event if venue was created/found
			if ( $venue_id ) {
				update_post_meta( $post_id, '_EventVenueID', $venue_id );
			}



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
			$debug_helper->log_critical_error( 'Importer', 'Failed to import event: ' . $e->getMessage(), array(
				'event_name' => $event_name ?? 'Unknown',
				'humanitix_id' => $humanitix_id ?? 'unknown',
				'error' => $e->getMessage(),
			) );

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

			// Extract featured image ID before creating event
			$featured_image_id = $tec_event_data['_thumbnail_id'] ?? null;
			unset( $tec_event_data['_thumbnail_id'] );

			// Create the event using TEC functions.
			$event_id = tribe_create_event( $tec_event_data );

			if ( $event_id ) {
				// Store external ID for future reference.
				update_post_meta( $event_id, '_humanitix_event_id', $event_data['id'] );
				update_post_meta( $event_id, '_humanitix_last_import', current_time( 'mysql' ) );

				// Set featured image if available
				if ( $featured_image_id ) {
					$thumbnail_set = set_post_thumbnail( $event_id, $featured_image_id );
					
					if ( $thumbnail_set ) {
						$this->logger->log(
							'info',
							"Successfully set featured image for event: {$event_title}",
							array(
								'wordpress_id'   => $event_id,
								'attachment_id'  => $featured_image_id,
							)
						);
					} else {
						$this->logger->log(
							'warning',
							"Failed to set featured image for event: {$event_title}",
							array(
								'wordpress_id'   => $event_id,
								'attachment_id'  => $featured_image_id,
							)
						);
					}
				}

				$this->imported_events[] = $event_id;

				$this->logger->log(
					'info',
					"Successfully created event: {$event_title}",
					array(
						'wordpress_id' => $event_id,
						'humanitix_id' => $event_data['id'],
						'venue_id'     => $tec_event_data['Venue'][0] ?? null,
						'organizer_id' => $tec_event_data['Organizer'][0] ?? null,
						'has_image'    => ! empty( $featured_image_id ),
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
			
			// Extract featured image ID before updating event
			$featured_image_id = $tec_event_data['_thumbnail_id'] ?? null;
			unset( $tec_event_data['_thumbnail_id'] );
			
			$tec_event_data['ID'] = $existing_event_id;

			$updated = tribe_update_event( $existing_event_id, $tec_event_data );

			if ( $updated ) {
				update_post_meta( $existing_event_id, '_humanitix_last_import', current_time( 'mysql' ) );
				
				// Set featured image if available
				if ( $featured_image_id ) {
					$thumbnail_set = set_post_thumbnail( $existing_event_id, $featured_image_id );
					
					if ( $thumbnail_set ) {
						$this->logger->log(
							'info',
							"Successfully set featured image for updated event: {$event_title}",
							array(
								'wordpress_id'   => $existing_event_id,
								'attachment_id'  => $featured_image_id,
							)
						);
					} else {
						$this->logger->log(
							'warning',
							"Failed to set featured image for updated event: {$event_title}",
							array(
								'wordpress_id'   => $existing_event_id,
								'attachment_id'  => $featured_image_id,
							)
						);
					}
				}
				
				$this->imported_events[] = $existing_event_id;

				$this->logger->log(
					'info',
					"Successfully updated event: {$event_title}",
					array(
						'wordpress_id' => $existing_event_id,
						'humanitix_id' => $event_data['id'],
						'has_image'    => ! empty( $featured_image_id ),
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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Starting map_event_fields for event: " . ( $humanitix_event['name'] ?? 'Unknown' ) );
			error_log( "Humanitix EventsImporter: Venue data in event: " . wp_json_encode( $humanitix_event['venue'] ?? 'not set' ) );
		}

		// Process venue - check multiple possible field names
		$venue_data = $humanitix_event['venue'] ?? $humanitix_event['eventLocation'] ?? $humanitix_event['location'] ?? array();
		
		// Map Humanitix eventLocation structure to expected venue fields
		if ( ! empty( $venue_data ) && isset( $venue_data['venueName'] ) ) {
			$venue_data = $this->map_humanitix_venue_data( $venue_data );
		}
		
		$venue_id = $this->process_venue( $venue_data );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Venue processing result - ID: " . ( $venue_id ? $venue_id : 'null' ) );
		}

		// Process organizer.
		$organizer_id = $this->process_organizer( $humanitix_event['organizer'] ?? array() );

		// Process event image.
		$image_url = $humanitix_event['featureImage']['url'] ?? $humanitix_event['bannerImage']['url'] ?? '';
		$featured_image_id = $this->process_event_image( $image_url );
		
		// Log image processing attempt
		if ( ! empty( $image_url ) ) {
			$this->logger->log(
				'info',
				'Attempting to process event image',
				array(
					'image_url' => $image_url,
					'feature_image_id' => $featured_image_id,
				)
			);
		} else {
			$this->logger->log(
				'info',
				'No image URL found in event data',
				array(
					'has_feature_image' => isset( $humanitix_event['featureImage']['url'] ),
					'has_banner_image' => isset( $humanitix_event['bannerImage']['url'] ),
					'image_fields' => array_keys( $humanitix_event ),
				)
			);
		}

		// Check if this is a series event.
		$is_series = $this->is_series_event( $humanitix_event );
		$series_info = $is_series ? $this->extract_series_info( $humanitix_event ) : null;

		$tec_event_data = array(
			'post_title'          => $humanitix_event['title'] ?? '',
			'post_content'        => $humanitix_event['description'] ?? '',
			'post_excerpt'        => $humanitix_event['short_description'] ?? '',
			'EventStartDate'      => $this->format_date( $humanitix_event['startDate'] ),
			'EventEndDate'        => $this->format_date( $humanitix_event['endDate'] ),
			'EventStartHour'      => $this->extract_hour( $humanitix_event['startDate'] ),
			'EventStartMinute'    => $this->extract_minute( $humanitix_event['startDate'] ),
			'EventEndHour'        => $this->extract_hour( $humanitix_event['endDate'] ),
			'EventEndMinute'      => $this->extract_minute( $humanitix_event['endDate'] ),
			'EventShowMapLink'    => true,
			'EventShowMap'        => true,
			'EventURL'            => $humanitix_event['url'] ?? '',
			'EventCost'           => $this->format_cost( $humanitix_event['pricing'] ?? array() ),
			'EventCurrencySymbol' => '$',
			'post_status'         => 'publish',
		);

		// Log date processing for debugging.
		$this->logger->log(
			'info',
			'Date processing details',
			array(
				'original_start_date' => $humanitix_event['startDate'] ?? 'not set',
				'original_end_date' => $humanitix_event['endDate'] ?? 'not set',
				'timezone' => $humanitix_event['timezone'] ?? 'not set',
				'formatted_start_date' => $tec_event_data['EventStartDate'],
				'formatted_end_date' => $tec_event_data['EventEndDate'],
				'start_hour' => $tec_event_data['EventStartHour'],
				'start_minute' => $tec_event_data['EventStartMinute'],
				'end_hour' => $tec_event_data['EventEndHour'],
				'end_minute' => $tec_event_data['EventEndMinute'],
				'is_series' => $is_series,
			)
		);

		// Add series information if this is a series event.
		if ( $is_series && $series_info ) {
			$tec_event_data['humanitix_series_id'] = $series_info['series_id'];
			$tec_event_data['humanitix_series_instance'] = $series_info['instance_number'];
			$tec_event_data['humanitix_series_total'] = $series_info['total_instances'];
			$tec_event_data['humanitix_recurrence_rule'] = $series_info['recurrence_rule'];
			
			// Log series event information.
			$this->logger->log(
				'info',
				'Processing series event instance',
				array(
					'series_id' => $series_info['series_id'],
					'instance_number' => $series_info['instance_number'],
					'total_instances' => $series_info['total_instances'],
					'recurrence_rule' => $series_info['recurrence_rule'],
				)
			);
		}

		// Add venue if available.
		if ( $venue_id ) {
			$tec_event_data['Venue'] = array( $venue_id );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: Added venue ID {$venue_id} to event data" );
				error_log( "Humanitix EventsImporter: Full TEC event data: " . wp_json_encode( $tec_event_data ) );
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: No venue ID available to add to event" );
			}
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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Starting venue processing with data: " . wp_json_encode( $venue_data ) );
		}

		if ( empty( $venue_data ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: Empty venue data provided" );
			}
			return null;
		}

		$venue_name = $venue_data['name'] ?? 'Unknown Venue';
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Processing venue: {$venue_name}" );
		}

		// Check if venue already exists.
		$existing_venue = $this->find_existing_venue( $venue_name );

		if ( $existing_venue ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: Found existing venue ID: {$existing_venue}" );
			}
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

		// Prepare venue data for TEC
		$venue_args = array(
			'Venue'   => $venue_name,
			'Address' => $venue_data['address'] ?? '',
			'City'    => $venue_data['city'] ?? '',
			'State'   => $venue_data['state'] ?? '',
			'Zip'     => $venue_data['postal_code'] ?? '',
			'Country' => $venue_data['country'] ?? '',
			'Phone'   => $venue_data['phone'] ?? '',
			'Website' => $venue_data['website'] ?? '',
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Creating venue with args: " . wp_json_encode( $venue_args ) );
		}

		// Create new venue.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: About to call tribe_create_venue with args: " . wp_json_encode( $venue_args ) );
		}
		
		$venue_id = tribe_create_venue( $venue_args );

		// Fallback: If tribe_create_venue fails, try manual venue creation
		if ( ! $venue_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: tribe_create_venue failed, trying manual venue creation" );
			}
			
			$venue_id = $this->create_venue_manually( $venue_args );
		}

		if ( $venue_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: Successfully created venue with ID: {$venue_id}" );
				
				// Verify the venue was created properly
				$venue_post = get_post( $venue_id );
				if ( $venue_post ) {
					error_log( "Humanitix EventsImporter: Venue post created - Title: {$venue_post->post_title}, Type: {$venue_post->post_type}" );
					
					// Check venue meta fields
					$venue_address = get_post_meta( $venue_id, '_VenueAddress', true );
					$venue_city = get_post_meta( $venue_id, '_VenueCity', true );
					$venue_state = get_post_meta( $venue_id, '_VenueState', true );
					$venue_country = get_post_meta( $venue_id, '_VenueCountry', true );
					
					error_log( "Humanitix EventsImporter: Venue meta fields - Address: {$venue_address}, City: {$venue_city}, State: {$venue_state}, Country: {$venue_country}" );
				} else {
					error_log( "Humanitix EventsImporter: ERROR - Venue post not found after creation!" );
				}
			}
			
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
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: Failed to create venue - tribe_create_venue returned false/null" );
			}
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
	 * Map Humanitix eventLocation data to expected venue fields.
	 *
	 * @param array $humanitix_venue_data The venue data from Humanitix eventLocation.
	 * @return array Mapped venue data for TEC.
	 */
	private function map_humanitix_venue_data( $humanitix_venue_data ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Mapping venue data from: " . wp_json_encode( $humanitix_venue_data ) );
		}

		$mapped_venue = array(
			'name' => $humanitix_venue_data['venueName'] ?? 'Unknown Venue',
			'address' => $humanitix_venue_data['address'] ?? '',
			'city' => $humanitix_venue_data['city'] ?? '',
			'state' => $humanitix_venue_data['region'] ?? '',
			'country' => $humanitix_venue_data['country'] ?? '',
		);

		// Extract postal code from address if not provided separately
		if ( empty( $mapped_venue['postal_code'] ) && ! empty( $humanitix_venue_data['address'] ) ) {
			// Try to extract postal code from address
			if ( preg_match( '/\b(\d{5}(?:-\d{4})?)\b/', $humanitix_venue_data['address'], $matches ) ) {
				$mapped_venue['postal_code'] = $matches[1];
			}
		}

		// Extract coordinates if available
		if ( isset( $humanitix_venue_data['latLng'] ) && is_array( $humanitix_venue_data['latLng'] ) ) {
			$mapped_venue['lat_lng'] = $humanitix_venue_data['latLng'];
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Mapped venue data to: " . wp_json_encode( $mapped_venue ) );
		}

		return $mapped_venue;
	}

	/**
	 * Create venue manually if tribe_create_venue fails.
	 *
	 * @param array $venue_args The venue arguments.
	 * @return int|false The venue ID or false on failure.
	 */
	private function create_venue_manually( $venue_args ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Creating venue manually with args: " . wp_json_encode( $venue_args ) );
		}

		// Create the venue post
		$venue_post_data = array(
			'post_title'   => $venue_args['Venue'],
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'tribe_venue',
		);

		$venue_id = wp_insert_post( $venue_post_data );

		if ( $venue_id && ! is_wp_error( $venue_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: Manually created venue with ID: {$venue_id}" );
			}

			// Set venue meta fields
			update_post_meta( $venue_id, '_VenueAddress', $venue_args['Address'] ?? '' );
			update_post_meta( $venue_id, '_VenueCity', $venue_args['City'] ?? '' );
			update_post_meta( $venue_id, '_VenueState', $venue_args['State'] ?? '' );
			update_post_meta( $venue_id, '_VenueCountry', $venue_args['Country'] ?? '' );
			update_post_meta( $venue_id, '_VenueZip', $venue_args['Zip'] ?? '' );
			update_post_meta( $venue_id, '_VenuePhone', $venue_args['Phone'] ?? '' );
			update_post_meta( $venue_id, '_VenueURL', $venue_args['Website'] ?? '' );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: Set venue meta fields for venue ID: {$venue_id}" );
			}

			return $venue_id;
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: Failed to create venue manually" );
			}
			return false;
		}
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
	 * Find existing venue by name with caching.
	 *
	 * Searches for an existing venue with the given name using optimized queries and caching.
	 *
	 * @since 1.0.0
	 * @param string $venue_name The name of the venue to search for.
	 * @return int|false The venue ID or false if not found.
	 */
	private function find_existing_venue( $venue_name ) {
		if ( empty( $venue_name ) ) {
			return false;
		}

		// Use static cache for this request
		static $venue_cache = array();
		$cache_key = sanitize_title( $venue_name );
		
		if ( isset( $venue_cache[ $cache_key ] ) ) {
			return $venue_cache[ $cache_key ];
		}

		// Use direct SQL query for better performance
		global $wpdb;
		
		// Search by post title first (most common case)
		$venue_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
				 WHERE post_type = 'tribe_venue' 
				 AND post_status = 'publish' 
				 AND post_title = %s
				 LIMIT 1",
				$venue_name
			)
		);
		
		// If not found by title, search by meta fields
		if ( ! $venue_id ) {
			$venue_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					 WHERE p.post_type = 'tribe_venue' 
					 AND p.post_status = 'publish' 
					 AND pm.meta_key IN ('_VenueVenue', '_VenueAddress')
					 AND pm.meta_value = %s
					 LIMIT 1",
					$venue_name
				)
			);
		}
		
		$venue_id = $venue_id ? intval( $venue_id ) : false;
		
		// Cache the result
		$venue_cache[ $cache_key ] = $venue_id;
		
		return $venue_id;
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
		if ( empty( $image_url ) ) {
			$this->logger->log(
				'warning',
				'Empty image URL provided for download',
				array(
					'image_url' => $image_url,
				)
			);
			return null;
		}

		// Download image and create attachment.
		$upload = media_sideload_image( $image_url, 0, '', 'id' );

		if ( is_wp_error( $upload ) ) {
			$this->logger->log(
				'error',
				'Failed to download and attach image',
				array(
					'image_url' => $image_url,
					'error'     => $upload->get_error_message(),
				)
			);
			return null;
		}

		$this->logger->log(
			'info',
			'Successfully downloaded and attached image',
			array(
				'image_url'     => $image_url,
				'attachment_id' => $upload,
			)
		);

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
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: find_existing_event called with empty humanitix_id" );
			}
			return false;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Searching for existing event with humanitix_id: {$humanitix_id}" );
		}

		$args = array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'     => '_humanitix_event_id',
					'value'   => $humanitix_id,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);

		$query = new \WP_Query( $args );
		$posts = $query->posts;

		$existing_event_id = ! empty( $posts ) ? $posts[0] : false;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( $existing_event_id ) {
				error_log( "Humanitix EventsImporter: Found existing event ID: {$existing_event_id} for humanitix_id: {$humanitix_id}" );
			} else {
				error_log( "Humanitix EventsImporter: No existing event found for humanitix_id: {$humanitix_id}" );
			}
		}

		return $existing_event_id;
	}

	/**
	 * Format date string to Y-m-d format.
	 *
	 * @since 1.0.0
	 * @param string $date_string The date string to format (ISO 8601 format from Humanitix).
	 * @return string The formatted date.
	 */
	private function format_date( $date_string ) {
		if ( empty( $date_string ) ) {
			return '';
		}

		// Handle ISO 8601 format from Humanitix API (e.g., "2021-02-01T23:26:13.485Z").
		$timestamp = strtotime( $date_string );
		if ( false === $timestamp ) {
			$this->logger->log(
				'warning',
				'Invalid date string provided',
				array(
					'date_string' => $date_string,
				)
			);
			return '';
		}

		// Use date() to preserve the original timezone information from the ISO string.
		return date( 'Y-m-d', $timestamp );
	}

	/**
	 * Extract hour from date string.
	 *
	 * @since 1.0.0
	 * @param string $date_string The date string to extract hour from (ISO 8601 format).
	 * @return string The hour in 24-hour format.
	 */
	private function extract_hour( $date_string ) {
		if ( empty( $date_string ) ) {
			return '00';
		}

		$timestamp = strtotime( $date_string );
		if ( false === $timestamp ) {
			return '00';
		}

		// Use date() to preserve the original timezone information from the ISO string.
		return date( 'H', $timestamp );
	}

	/**
	 * Extract minute from date string.
	 *
	 * @since 1.0.0
	 * @param string $date_string The date string to extract minute from (ISO 8601 format).
	 * @return string The minute.
	 */
	private function extract_minute( $date_string ) {
		if ( empty( $date_string ) ) {
			return '00';
		}

		$timestamp = strtotime( $date_string );
		if ( false === $timestamp ) {
			return '00';
		}

		// Use date() to preserve the original timezone information from the ISO string.
		return date( 'i', $timestamp );
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
			// Handle featured image separately
			if ( $meta_key === '_thumbnail_id' ) {
				if ( ! empty( $meta_value ) ) {
					$thumbnail_set = set_post_thumbnail( $post_id, $meta_value );
					
					if ( $thumbnail_set ) {
						$this->logger->log(
							'info',
							'Successfully set featured image',
							array(
								'post_id'       => $post_id,
								'attachment_id' => $meta_value,
							)
						);
					} else {
						$this->logger->log(
							'warning',
							'Failed to set featured image',
							array(
								'post_id'       => $post_id,
								'attachment_id' => $meta_value,
							)
						);
					}
				}
			} else {
				// Handle all other meta fields normally
				update_post_meta( $post_id, $meta_key, $meta_value );
			}
		}
	}

	/**
	 * Process venue data from the mapped event.
	 *
	 * This method extracts venue data from the mapped event's meta fields
	 * and creates or finds the venue, then returns the venue ID.
	 *
	 * @since 1.0.0
	 * @param array $mapped_event The mapped event data from DataMapper.
	 * @param array $event_data The original Humanitix event data.
	 * @return int|null The venue ID or null if processing failed.
	 */
	private function process_venue_from_mapped_event( $mapped_event, $event_data ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Starting process_venue_from_mapped_event for event: " . ( $event_data['name'] ?? 'Unknown' ) );
		}

		// Try to get venue data from the original event data first
		$venue_data = $event_data['venue'] ?? $event_data['eventLocation'] ?? $event_data['location'] ?? array();
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Extracted venue data from original event: " . wp_json_encode( $venue_data ) );
		}

		// If no venue data found, return null
		if ( empty( $venue_data ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix EventsImporter: No venue data found in original event" );
			}
			return null;
		}

		// Map Humanitix eventLocation structure to expected venue fields
		if ( ! empty( $venue_data ) && isset( $venue_data['venueName'] ) ) {
			$venue_data = $this->map_humanitix_venue_data( $venue_data );
		}

		// Process venue
		$venue_id = $this->process_venue( $venue_data );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Venue processing result from mapped event - ID: " . ( $venue_id ? $venue_id : 'null' ) );
		}

		return $venue_id;
	}

	/**
	 * Debug helper: Check what Humanitix IDs are stored in the database.
	 *
	 * @return array Array of stored Humanitix IDs.
	 */
	private function debug_check_stored_humanitix_ids() {
		global $wpdb;
		
		$results = $wpdb->get_results(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} 
			 WHERE meta_key = '_humanitix_event_id' 
			 ORDER BY post_id DESC"
		);
		
		$stored_ids = array();
		foreach ( $results as $result ) {
			$stored_ids[ $result->post_id ] = $result->meta_value;
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix EventsImporter: Currently stored Humanitix IDs: " . wp_json_encode( $stored_ids ) );
		}
		
		return $stored_ids;
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

	/**
	 * Check if an event is part of a series.
	 *
	 * @since 1.0.0
	 * @param array $humanitix_event The Humanitix event data.
	 * @return bool True if this is a series event, false otherwise.
	 */
	private function is_series_event( $humanitix_event ) {
		// Check for series indicators in Humanitix data.
		$series_indicators = array(
			'series_id',
			'series',
			'recurring',
			'recurrence',
			'parent_event_id',
			'instance_number',
			'total_instances',
		);

		foreach ( $series_indicators as $indicator ) {
			if ( isset( $humanitix_event[ $indicator ] ) && ! empty( $humanitix_event[ $indicator ] ) ) {
				return true;
			}
		}

		// Check for series information in nested objects.
		if ( isset( $humanitix_event['series'] ) && is_array( $humanitix_event['series'] ) ) {
			return true;
		}

		if ( isset( $humanitix_event['recurrence'] ) && is_array( $humanitix_event['recurrence'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Extract series information from Humanitix event data.
	 *
	 * @since 1.0.0
	 * @param array $humanitix_event The Humanitix event data.
	 * @return array|null Series information or null if not a series event.
	 */
	private function extract_series_info( $humanitix_event ) {
		$series_info = array(
			'series_id'        => '',
			'instance_number'  => 1,
			'total_instances'  => 1,
			'recurrence_rule'  => '',
			'parent_event_id'  => '',
		);

		// Extract series ID.
		if ( isset( $humanitix_event['series_id'] ) ) {
			$series_info['series_id'] = sanitize_text_field( $humanitix_event['series_id'] );
		} elseif ( isset( $humanitix_event['series']['id'] ) ) {
			$series_info['series_id'] = sanitize_text_field( $humanitix_event['series']['id'] );
		} elseif ( isset( $humanitix_event['parent_event_id'] ) ) {
			$series_info['parent_event_id'] = sanitize_text_field( $humanitix_event['parent_event_id'] );
		}

		// Extract instance information.
		if ( isset( $humanitix_event['instance_number'] ) ) {
			$series_info['instance_number'] = (int) $humanitix_event['instance_number'];
		}

		if ( isset( $humanitix_event['total_instances'] ) ) {
			$series_info['total_instances'] = (int) $humanitix_event['total_instances'];
		}

		// Extract recurrence rule.
		if ( isset( $humanitix_event['recurrence'] ) && is_array( $humanitix_event['recurrence'] ) ) {
			$recurrence = $humanitix_event['recurrence'];
			
			// Build recurrence rule string.
			$rule_parts = array();
			
			if ( isset( $recurrence['frequency'] ) ) {
				$rule_parts[] = 'FREQ=' . strtoupper( $recurrence['frequency'] );
			}
			
			if ( isset( $recurrence['interval'] ) ) {
				$rule_parts[] = 'INTERVAL=' . $recurrence['interval'];
			}
			
			if ( isset( $recurrence['count'] ) ) {
				$rule_parts[] = 'COUNT=' . $recurrence['count'];
			}
			
			if ( isset( $recurrence['until'] ) ) {
				$rule_parts[] = 'UNTIL=' . $recurrence['until'];
			}
			
			if ( isset( $recurrence['byday'] ) ) {
				$rule_parts[] = 'BYDAY=' . $recurrence['byday'];
			}
			
			$series_info['recurrence_rule'] = implode( ';', $rule_parts );
		}

		// If we have any series information, return it.
		if ( ! empty( $series_info['series_id'] ) || ! empty( $series_info['parent_event_id'] ) ) {
			return $series_info;
		}

		return null;
	}
}
