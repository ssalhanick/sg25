<?php
/**
 * Abstract Events Importer Class.
 *
 * Defines the contract for all event importer implementations.
 *
 * @package SG\HumanitixApiImporter\Importer
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Importer;

use SG\HumanitixApiImporter\API\AbstractEventAPI;
use SG\HumanitixApiImporter\Admin\Logger;
use SG\HumanitixApiImporter\Rules\RuleEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Abstract Events Importer Class.
 *
 * Defines the contract for all event importer implementations.
 * Both Humanitix and Eventbrite importers will extend this class.
 *
 * @package SG\HumanitixApiImporter\Importer
 * @since 1.0.0
 */
abstract class AbstractEventsImporter {

	/**
	 * The API instance.
	 *
	 * @var AbstractEventAPI
	 */
	protected $api;

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Array of successfully imported event IDs.
	 *
	 * @var array
	 */
	protected $imported_events = array();

	/**
	 * Array of error messages from failed imports.
	 *
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Timestamp when the import process started.
	 *
	 * @var float
	 */
	protected $start_time;

	/**
	 * The data mapper instance.
	 *
	 * @var DataMapper
	 */
	protected $data_mapper;

	/**
	 * The rule engine instance for filtering events.
	 *
	 * @var RuleEngine
	 */
	protected $rule_engine;

	/**
	 * Constructor.
	 *
	 * @param AbstractEventAPI $api The API instance.
	 * @param Logger           $logger Optional logger instance.
	 * @param DataMapper       $data_mapper Optional data mapper instance.
	 * @param RuleEngine       $rule_engine Optional rule engine instance.
	 */
	public function __construct( AbstractEventAPI $api, Logger $logger = null, DataMapper $data_mapper = null, RuleEngine $rule_engine = null ) {
		$this->api = $api;
		$this->logger = $logger ?: new \SG\HumanitixApiImporter\Admin\Logger();
		$this->data_mapper = $data_mapper ?: $this->create_default_data_mapper();
		$this->rule_engine = $rule_engine;
	}

	/**
	 * Create a default data mapper for this importer.
	 *
	 * @return DataMapper
	 */
	abstract protected function create_default_data_mapper();

	/**
	 * Import events from the API.
	 *
	 * @param array $params Import parameters.
	 * @return array Import results.
	 */
	abstract public function import_events( $params = array() );

	/**
	 * Import a single event.
	 *
	 * @param array $event_data Event data from the API.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	abstract protected function import_single_event( $event_data );

	/**
	 * Process venue data.
	 *
	 * @param array $venue_data Venue data from the API.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	abstract protected function process_venue( $venue_data );

	/**
	 * Process organizer data.
	 *
	 * @param array $organizer_data Organizer data from the API.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	abstract protected function process_organizer( $organizer_data );

	/**
	 * Map API event data to WordPress format.
	 *
	 * @param array $api_event Event data from the API.
	 * @return array Mapped event data.
	 */
	abstract protected function map_event_data( $api_event );

	/**
	 * Get the API provider name.
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return $this->api->get_provider_name();
	}

	/**
	 * Check if an event should be imported based on rules.
	 *
	 * @param array $event_data The event data to check.
	 * @return bool Whether the event should be imported.
	 */
	protected function should_import_event( $event_data ) {
		// If no rule engine is configured, import all events
		if ( ! $this->rule_engine ) {
			return true;
		}

		// Evaluate rules against event data
		return $this->rule_engine->evaluate_event( $event_data );
	}

	/**
	 * Get imported events.
	 *
	 * @return array
	 */
	public function get_imported_events() {
		return $this->imported_events;
	}

	/**
	 * Get errors.
	 *
	 * @return array
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Get import statistics.
	 *
	 * @return array
	 */
	public function get_import_stats() {
		$end_time = microtime( true );
		$duration = $end_time - $this->start_time;

		return array(
			'provider'        => $this->get_provider_name(),
			'total_imported'  => count( $this->imported_events ),
			'total_errors'    => count( $this->errors ),
			'duration'        => round( $duration, 2 ),
			'start_time'      => $this->start_time,
			'end_time'        => $end_time,
		);
	}

	/**
	 * Start the import process.
	 *
	 * @return void
	 */
	protected function start_import() {
		$this->start_time = microtime( true );
		$this->imported_events = array();
		$this->errors = array();

		$this->logger->log(
			sprintf( 'Starting %s event import', $this->get_provider_name() ),
			'info'
		);
	}

	/**
	 * End the import process.
	 *
	 * @return void
	 */
	protected function end_import() {
		$stats = $this->get_import_stats();

		$this->logger->log(
			sprintf(
				'Completed %s event import. Imported: %d, Errors: %d, Duration: %ss',
				$this->get_provider_name(),
				$stats['total_imported'],
				$stats['total_errors'],
				$stats['duration']
			),
			'info'
		);
	}

	/**
	 * Add an imported event.
	 *
	 * @param int $post_id The WordPress post ID.
	 * @return void
	 */
	protected function add_imported_event( $post_id ) {
		$this->imported_events[] = $post_id;
	}

	/**
	 * Add an error.
	 *
	 * @param string $message Error message.
	 * @param array  $context Error context.
	 * @return void
	 */
	protected function add_error( $message, $context = array() ) {
		$error = array(
			'message' => $message,
			'context' => $context,
			'timestamp' => current_time( 'mysql' ),
		);

		$this->errors[] = $error;
		$this->logger->log( $message, 'error', $context );
	}

	/**
	 * Check if an event already exists.
	 *
	 * @param string $external_id External event ID.
	 * @return int|false Post ID if exists, false otherwise.
	 */
	protected function event_exists( $external_id ) {
		$meta_key = $this->get_external_id_meta_key();
		
		$posts = get_posts( array(
			'post_type'      => 'tribe_events',
			'meta_key'       => $meta_key,
			'meta_value'     => $external_id,
			'posts_per_page' => 1,
			'post_status'    => 'any',
		) );

		return ! empty( $posts ) ? $posts[0]->ID : false;
	}

	/**
	 * Get the meta key for storing external event IDs.
	 *
	 * @return string
	 */
	abstract protected function get_external_id_meta_key();

	/**
	 * Update an existing event.
	 *
	 * @param int   $post_id The WordPress post ID.
	 * @param array $event_data Updated event data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	protected function update_existing_event( $post_id, $event_data ) {
		$mapped_data = $this->map_event_data( $event_data );

		// Update post data
		$post_data = array(
			'ID'           => $post_id,
			'post_title'   => $mapped_data['post_title'],
			'post_content' => $mapped_data['post_content'],
			'post_status'  => 'publish',
		);

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update meta fields
		$this->update_event_meta( $post_id, $mapped_data );

		return true;
	}

	/**
	 * Update event meta fields.
	 *
	 * @param int   $post_id The WordPress post ID.
	 * @param array $mapped_data Mapped event data.
	 * @return void
	 */
	protected function update_event_meta( $post_id, $mapped_data ) {
		// Update standard event meta
		if ( isset( $mapped_data['_EventStartDate'] ) ) {
			update_post_meta( $post_id, '_EventStartDate', $mapped_data['_EventStartDate'] );
		}

		if ( isset( $mapped_data['_EventEndDate'] ) ) {
			update_post_meta( $post_id, '_EventEndDate', $mapped_data['_EventEndDate'] );
		}

		if ( isset( $mapped_data['_EventTimezone'] ) ) {
			update_post_meta( $post_id, '_EventTimezone', $mapped_data['_EventTimezone'] );
		}

		if ( isset( $mapped_data['_EventURL'] ) ) {
			update_post_meta( $post_id, '_EventURL', $mapped_data['_EventURL'] );
		}

		// Update custom meta fields
		foreach ( $mapped_data as $key => $value ) {
			if ( strpos( $key, '_' ) === 0 ) {
				continue; // Skip standard meta fields
			}

			if ( ! empty( $value ) ) {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Create a new event.
	 *
	 * @param array $event_data Event data from the API.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	protected function create_new_event( $event_data ) {
		$mapped_data = $this->map_event_data( $event_data );

		// Create post data
		$post_data = array(
			'post_title'   => $mapped_data['post_title'],
			'post_content' => $mapped_data['post_content'],
			'post_status'  => 'publish',
			'post_type'    => 'tribe_events',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set event meta
		$this->set_event_meta( $post_id, $mapped_data );

		// Set external ID meta
		$external_id_meta_key = $this->get_external_id_meta_key();
		update_post_meta( $post_id, $external_id_meta_key, $event_data['id'] );

		return $post_id;
	}

	/**
	 * Set event meta fields.
	 *
	 * @param int   $post_id The WordPress post ID.
	 * @param array $mapped_data Mapped event data.
	 * @return void
	 */
	protected function set_event_meta( $post_id, $mapped_data ) {
		// Set standard event meta
		if ( isset( $mapped_data['_EventStartDate'] ) ) {
			add_post_meta( $post_id, '_EventStartDate', $mapped_data['_EventStartDate'], true );
		}

		if ( isset( $mapped_data['_EventEndDate'] ) ) {
			add_post_meta( $post_id, '_EventEndDate', $mapped_data['_EventEndDate'], true );
		}

		if ( isset( $mapped_data['_EventTimezone'] ) ) {
			add_post_meta( $post_id, '_EventTimezone', $mapped_data['_EventTimezone'], true );
		}

		if ( isset( $mapped_data['_EventURL'] ) ) {
			add_post_meta( $post_id, '_EventURL', $mapped_data['_EventURL'], true );
		}

		// Set custom meta fields
		foreach ( $mapped_data as $key => $value ) {
			if ( strpos( $key, '_' ) === 0 ) {
				continue; // Skip standard meta fields
			}

			if ( ! empty( $value ) ) {
				add_post_meta( $post_id, $key, $value, true );
			}
		}
	}
} 