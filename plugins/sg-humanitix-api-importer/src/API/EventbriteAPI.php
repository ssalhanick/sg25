<?php
/**
 * Eventbrite API Class.
 *
 * Handles communication with the Eventbrite API for fetching events and related data.
 *
 * @package SG\HumanitixApiImporter\API
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Eventbrite API Class.
 *
 * Handles communication with the Eventbrite API for fetching events and related data.
 *
 * @package SG\HumanitixApiImporter\API
 * @since 1.0.0
 */
class EventbriteAPI extends AbstractEventAPI {

	/**
	 * Constructor.
	 *
	 * @param string $api_key The Eventbrite API key.
	 * @param string $api_endpoint Optional custom API endpoint.
	 * @param string $org_id The Eventbrite organization ID.
	 */
	public function __construct( $api_key, $api_endpoint = '', $org_id = '' ) {
		$this->provider_name = 'eventbrite';
		parent::__construct( $api_key, $api_endpoint, $org_id );
	}

	/**
	 * Fetch events from Eventbrite API.
	 *
	 * @param array $params Query parameters for fetching events.
	 * @return array|WP_Error Array of events or WP_Error on failure.
	 */
	public function fetch_events( $params = array() ) {
		$default_params = array(
			'expand' => 'venue,organizer,category,subcategory',
			'status' => 'live',
			'order_by' => 'start_date',
		);

		$params = wp_parse_args( $params, $default_params );

		// Build query string
		$query_string = http_build_query( $params );
		$endpoint = $this->api_endpoint . '/events/search/?' . $query_string;

		$response = $this->make_request( $endpoint, array(
			'method' => 'GET',
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract events from Eventbrite response format
		$events = array();
		if ( isset( $response['events'] ) ) {
			$events = $response['events'];
		}

		return array(
			'success'      => true,
			'events'       => $events,
			'count'        => count( $events ),
			'pagination'   => isset( $response['pagination'] ) ? $response['pagination'] : array(),
			'raw_response' => $response,
		);
	}

	/**
	 * Fetch a single event by ID from Eventbrite.
	 *
	 * @param string $event_id The Eventbrite event ID.
	 * @return array|WP_Error Event data or WP_Error on failure.
	 */
	public function fetch_event( $event_id ) {
		$endpoint = $this->api_endpoint . '/events/' . urlencode( $event_id ) . '/?expand=venue,organizer,category,subcategory';

		$response = $this->make_request( $endpoint, array(
			'method' => 'GET',
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'success'      => true,
			'event'        => $response,
			'raw_response' => $response,
		);
	}

	/**
	 * Test the Eventbrite API connection.
	 *
	 * @return array|WP_Error Connection test result or WP_Error on failure.
	 */
	public function test_connection() {
		// Try to fetch user information to test the connection
		$endpoint = $this->api_endpoint . '/users/me/';

		$response = $this->make_request( $endpoint, array(
			'method' => 'GET',
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'success' => true,
			'message' => 'Successfully connected to Eventbrite API',
			'user'    => $response,
		);
	}

	/**
	 * Validate Eventbrite API key format.
	 *
	 * @param string $api_key The API key to validate.
	 * @return array Validation result with status and suggestions.
	 */
	public function validate_api_key_format( $api_key ) {
		$cleaned_key = $this->clean_api_key( $api_key );
		$issues      = array();
		$suggestions = array();

		// Check if key is empty
		if ( empty( $cleaned_key ) ) {
			$issues[] = 'API key is empty';
			$suggestions[] = 'Please enter your Eventbrite API key';
		}

		// Check if key looks like a valid Eventbrite private token
		if ( ! empty( $cleaned_key ) && strlen( $cleaned_key ) < 20 ) {
			$issues[] = 'API key appears to be too short';
			$suggestions[] = 'Eventbrite private tokens are typically longer than 20 characters';
		}

		// Check for common formatting issues
		if ( strpos( $cleaned_key, ' ' ) !== false ) {
			$issues[] = 'API key contains spaces';
			$suggestions[] = 'Remove any spaces from your API key';
		}

		if ( strpos( $cleaned_key, "\n" ) !== false || strpos( $cleaned_key, "\r" ) !== false ) {
			$issues[] = 'API key contains line breaks';
			$suggestions[] = 'Remove any line breaks from your API key';
		}

		$status = empty( $issues ) ? 'valid' : 'invalid';

		return array(
			'status'      => $status,
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'cleaned_key' => $cleaned_key,
		);
	}

	/**
	 * Get the default API endpoint for Eventbrite.
	 *
	 * @return string
	 */
	protected function get_default_endpoint() {
		return 'https://www.eventbriteapi.com/v3';
	}

	/**
	 * Get authentication headers for Eventbrite API requests.
	 *
	 * @return array
	 */
	protected function get_auth_headers() {
		return array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Fetch venues from Eventbrite API.
	 *
	 * @param array $params Query parameters for fetching venues.
	 * @return array|WP_Error Array of venues or WP_Error on failure.
	 */
	public function fetch_venues( $params = array() ) {
		$default_params = array(
			'expand' => 'address',
		);

		$params = wp_parse_args( $params, $default_params );

		// Build query string
		$query_string = http_build_query( $params );
		$endpoint = $this->api_endpoint . '/venues/?' . $query_string;

		$response = $this->make_request( $endpoint, array(
			'method' => 'GET',
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract venues from Eventbrite response format
		$venues = array();
		if ( isset( $response['venues'] ) ) {
			$venues = $response['venues'];
		}

		return array(
			'success'      => true,
			'venues'       => $venues,
			'count'        => count( $venues ),
			'pagination'   => isset( $response['pagination'] ) ? $response['pagination'] : array(),
			'raw_response' => $response,
		);
	}

	/**
	 * Fetch organizers from Eventbrite API.
	 *
	 * @param array $params Query parameters for fetching organizers.
	 * @return array|WP_Error Array of organizers or WP_Error on failure.
	 */
	public function fetch_organizers( $params = array() ) {
		$default_params = array(
			'expand' => 'description',
		);

		$params = wp_parse_args( $params, $default_params );

		// Build query string
		$query_string = http_build_query( $params );
		$endpoint = $this->api_endpoint . '/organizers/?' . $query_string;

		$response = $this->make_request( $endpoint, array(
			'method' => 'GET',
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract organizers from Eventbrite response format
		$organizers = array();
		if ( isset( $response['organizers'] ) ) {
			$organizers = $response['organizers'];
		}

		return array(
			'success'      => true,
			'organizers'   => $organizers,
			'count'        => count( $organizers ),
			'pagination'   => isset( $response['pagination'] ) ? $response['pagination'] : array(),
			'raw_response' => $response,
		);
	}
} 