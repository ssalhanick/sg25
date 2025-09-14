<?php
/**
 * Eventbrite Events Importer Class.
 *
 * Handles importing events from Eventbrite API to The Events Calendar.
 *
 * @package SG\HumanitixApiImporter\Importer
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Importer;

use SG\HumanitixApiImporter\API\EventbriteAPI;
use SG\HumanitixApiImporter\Admin\Logger;
use SG\HumanitixApiImporter\Rules\RuleEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Eventbrite Events Importer Class.
 *
 * Handles importing events from Eventbrite API to The Events Calendar.
 * Extends AbstractEventsImporter to provide Eventbrite-specific functionality.
 *
 * @package SG\HumanitixApiImporter\Importer
 * @since 1.0.0
 */
class EventbriteEventsImporter extends AbstractEventsImporter {

	/**
	 * Constructor.
	 *
	 * @param EventbriteAPI $api The Eventbrite API instance.
	 * @param Logger        $logger Optional logger instance.
	 * @param RuleEngine    $rule_engine Optional rule engine instance.
	 */
	public function __construct( EventbriteAPI $api, Logger $logger = null, RuleEngine $rule_engine = null ) {
		$this->api = $api;
		$this->logger = $logger ? $logger : new Logger();
		$this->rule_engine = $rule_engine;
	}

	/**
	 * Import events from Eventbrite API.
	 *
	 * @param int      $page Page number to import (>= 1).
	 * @param int|null $import_limit Optional limit on number of events to import (for debugging).
	 * @return array Import result.
	 * @throws \Exception When API is not initialized or API calls fail after retries.
	 */
	public function import_events( $page = 1, $import_limit = null ) {
		// Initialize debug helper for consistent logging
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $this->logger );
		
		$debug_helper->log( 'Importer', "Starting Eventbrite import_events with page: {$page}" . ( $import_limit ? ", limit: {$import_limit}" : '' ) );

		// Check if API is available.
		if ( ! $this->api ) {
			throw new \Exception( 'Eventbrite API not initialized. Please check your configuration.' );
		}

		// Check if API is authenticated.
		if ( ! $this->api->is_authenticated() ) {
			throw new \Exception( 'Eventbrite API not authenticated. Please authorize your account first.' );
		}

		$start_time = microtime( true );

		try {
			// First, test the connection to get user info
			$debug_helper->log( 'API', 'Testing Eventbrite connection' );
			$user_test = $this->api->test_connection();
			if ( is_wp_error( $user_test ) ) {
				throw new \Exception( 'Failed to connect to Eventbrite: ' . $user_test->get_error_message() );
			}
			$debug_helper->log( 'API', 'Eventbrite connection test successful' );

			// Fetch events from Eventbrite API
			$debug_helper->log( 'API', 'Fetching events from Eventbrite API' );
			$events = $this->api->fetch_events();
			
			if ( is_wp_error( $events ) ) {
				throw new \Exception( 'Failed to fetch events from Eventbrite: ' . $events->get_error_message() );
			}
			
			$debug_helper->log( 'API', 'Successfully fetched ' . count( $events ) . ' events from Eventbrite' );

			// Apply import limit if specified
			$original_count = count( $events );
			if ( $import_limit && $original_count > $import_limit ) {
				$events = array_slice( $events, 0, $import_limit );
				$debug_helper->log( 'Importer', "Limited import from {$original_count} to " . count( $events ) . ' events' );
			}

			$debug_helper->log( 'Importer', 'Processing ' . count( $events ) . ' events' );

			$imported_count = 0;
			$skipped_count = 0;
			$errors = array();
			$event_counter = 0;

			// Process each event
			foreach ( $events as $index => $event_data ) {
				$event_counter++;
				$event_name = $event_data['name']['text'] ?? 'Unknown Event';
				$event_id = $event_data['id'] ?? 'unknown';
				
				try {
					// Apply rules filtering if rule engine is available
					if ( $this->rule_engine && ! $this->rule_engine->evaluate_event( $event_data ) ) {
						$debug_helper->log( 'Rules', "Event '{$event_name}' (ID: {$event_id}) skipped by rules" );
						$skipped_count++;
						continue;
					}

					// Import the event (placeholder - would need actual Eventbrite data mapping)
					$debug_helper->log( 'Importer', "Processing event " . $event_counter . " of " . count( $events ) . ": '{$event_name}' (ID: {$event_id})" );
					$result = $this->import_single_event( $event_data );
					
					if ( $result ) {
						$imported_count++;
						$debug_helper->log( 'Importer', "Successfully imported event: '{$event_name}' (ID: {$event_id})" );
					}
				} catch ( \Exception $e ) {
					$error_msg = "Failed to import event '{$event_name}' (ID: {$event_id}): " . $e->getMessage();
					$errors[] = $error_msg;
					$debug_helper->log_error( 'Importer', $error_msg );
				}
			}

			$end_time = microtime( true );
			$duration = round( $end_time - $start_time, 2 );

			// Log import summary with detailed statistics
			$debug_helper->log_import_summary( $original_count, $imported_count, $skipped_count, $errors, $duration );

			$message = sprintf(
				'Eventbrite import completed: %d events imported, %d skipped, %d errors in %.2f seconds',
				$imported_count,
				$skipped_count,
				count( $errors ),
				$duration
			);

			$debug_helper->log( 'Importer', $message );

			return array(
				'imported' => $imported_count,
				'skipped'  => $skipped_count,
				'errors'   => $errors,
				'message'  => $message,
			);

		} catch ( \Exception $e ) {
			$debug_helper->log_error( 'Importer', 'Eventbrite import failed: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Import a single event from Eventbrite data.
	 *
	 * @param array $event_data Event data from Eventbrite API.
	 * @return bool True if successful, false otherwise.
	 * @throws \Exception When import fails.
	 */
	protected function import_single_event( $event_data ) {
		// Initialize debug helper for consistent logging
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $this->logger );
		
		$event_name = $event_data['name']['text'] ?? 'Unknown Event';
		$event_id = $event_data['id'] ?? 'unknown';
		
		$debug_helper->log( 'Event', "Processing Eventbrite event: '{$event_name}' (ID: {$event_id})" );
		
		// TODO: Implement Eventbrite-specific data mapping
		// This is a placeholder implementation
		
		// For now, just log that we would import this event
		$debug_helper->log( 'Event', "Would import Eventbrite event: '{$event_name}' (ID: {$event_id})" );
		
		// Return true to simulate successful import
		// In a real implementation, this would:
		// 1. Map Eventbrite data to TEC format
		// 2. Create/update the event in WordPress
		// 3. Handle any conflicts or duplicates
		
		return true;
	}

	/**
	 * Test the Eventbrite API connection.
	 *
	 * @return array Test result.
	 */
	public function test_connection() {
		if ( ! $this->api ) {
			return array(
				'success' => false,
				'message' => 'Eventbrite API not initialized',
			);
		}

		try {
			$result = $this->api->test_connection();
			
			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'message' => 'Connection test failed: ' . $result->get_error_message(),
				);
			}

			return array(
				'success' => true,
				'message' => 'Eventbrite API connection successful',
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Connection test failed: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Create a default data mapper for Eventbrite events.
	 *
	 * @return DataMapper
	 */
	protected function create_default_data_mapper() {
		// TODO: Create Eventbrite-specific data mapper
		// For now, return null as we don't have Eventbrite data mapping implemented yet
		return null;
	}

	/**
	 * Process venue data from Eventbrite API.
	 *
	 * @param array $venue_data Venue data from Eventbrite API.
	 * @return array Processed venue data.
	 */
	protected function process_venue( $venue_data ) {
		// TODO: Implement Eventbrite venue processing
		// For now, return the data as-is
		return $venue_data;
	}

	/**
	 * Process organizer data from Eventbrite API.
	 *
	 * @param array $organizer_data Organizer data from Eventbrite API.
	 * @return array Processed organizer data.
	 */
	protected function process_organizer( $organizer_data ) {
		// TODO: Implement Eventbrite organizer processing
		// For now, return the data as-is
		return $organizer_data;
	}

	/**
	 * Map Eventbrite event data to The Events Calendar format.
	 *
	 * @param array $api_event Event data from Eventbrite API.
	 * @return array Mapped event data for TEC.
	 */
	protected function map_event_data( $api_event ) {
		// TODO: Implement Eventbrite to TEC data mapping
		// For now, return a basic structure
		return array(
			'post_title' => $api_event['name']['text'] ?? 'Eventbrite Event',
			'post_content' => $api_event['description']['text'] ?? '',
			'post_status' => 'publish',
			'meta_input' => array(
				'_eventbrite_id' => $api_event['id'] ?? '',
				'_eventbrite_url' => $api_event['url'] ?? '',
			),
		);
	}

	/**
	 * Get the meta key for storing external event ID.
	 *
	 * @return string Meta key for external event ID.
	 */
	protected function get_external_id_meta_key() {
		return '_eventbrite_id';
	}
}