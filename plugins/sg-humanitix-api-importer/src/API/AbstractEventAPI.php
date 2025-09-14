<?php
/**
 * Abstract Event API Class.
 *
 * Defines the contract for all event API implementations.
 *
 * @package SG\HumanitixApiImporter\API
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Abstract Event API Class.
 *
 * Defines the contract for all event API implementations.
 * Both Humanitix and Eventbrite APIs will extend this class.
 *
 * @package SG\HumanitixApiImporter\API
 * @since 1.0.0
 */
abstract class AbstractEventAPI {

	/**
	 * The API key for authentication.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * The API endpoint base URL.
	 *
	 * @var string
	 */
	protected $api_endpoint;

	/**
	 * The organization ID for scoping requests.
	 *
	 * @var string
	 */
	protected $org_id;

	/**
	 * The API provider name (e.g., 'humanitix', 'eventbrite').
	 *
	 * @var string
	 */
	protected $provider_name;

	/**
	 * Constructor.
	 *
	 * @param string $api_key The API key.
	 * @param string $api_endpoint Optional custom API endpoint.
	 * @param string $org_id The organization ID.
	 */
	public function __construct( $api_key, $api_endpoint = '', $org_id = '' ) {
		$this->api_key = $this->clean_api_key( $api_key );
		$this->org_id  = sanitize_text_field( $org_id );
		$this->api_endpoint = $this->sanitize_endpoint( $api_endpoint );
	}

	/**
	 * Get the API provider name.
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return $this->provider_name;
	}

	/**
	 * Get the API endpoint.
	 *
	 * @return string
	 */
	public function get_api_endpoint() {
		return $this->api_endpoint;
	}

	/**
	 * Get the organization ID.
	 *
	 * @return string
	 */
	public function get_org_id() {
		return $this->org_id;
	}

	/**
	 * Fetch events from the API.
	 *
	 * @param array $params Query parameters for fetching events.
	 * @return array|WP_Error Array of events or WP_Error on failure.
	 */
	abstract public function fetch_events( $params = array() );

	/**
	 * Fetch a single event by ID.
	 *
	 * @param string $event_id The event ID.
	 * @return array|WP_Error Event data or WP_Error on failure.
	 */
	abstract public function fetch_event( $event_id );

	/**
	 * Test the API connection.
	 *
	 * @return array|WP_Error Connection test result or WP_Error on failure.
	 */
	abstract public function test_connection();

	/**
	 * Validate API key format.
	 *
	 * @param string $api_key The API key to validate.
	 * @return array Validation result with status and suggestions.
	 */
	abstract public function validate_api_key_format( $api_key );

	/**
	 * Clean and validate API key format.
	 *
	 * @param string $api_key The raw API key.
	 * @return string The cleaned API key.
	 */
	protected function clean_api_key( $api_key ) {
		$api_key = sanitize_text_field( $api_key );

		// Remove any whitespace.
		$api_key = trim( $api_key );

		// Remove any "Bearer " prefix if it was accidentally included.
		$api_key = preg_replace( '/^Bearer\s+/i', '', $api_key );

		// Remove any quotes if they were included.
		$api_key = trim( $api_key, '"\'`' );

		// Remove any newlines.
		$api_key = str_replace( array( "\n", "\r" ), '', $api_key );

		return $api_key;
	}

	/**
	 * Sanitize API endpoint URL.
	 *
	 * @param string $endpoint The endpoint URL.
	 * @return string The sanitized endpoint URL.
	 */
	protected function sanitize_endpoint( $endpoint ) {
		return ! empty( $endpoint ) ? esc_url_raw( $endpoint ) : $this->get_default_endpoint();
	}

	/**
	 * Get the default API endpoint for this provider.
	 *
	 * @return string
	 */
	abstract protected function get_default_endpoint();

	/**
	 * Make an HTTP request to the API.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $args     Request arguments.
	 * @return array|WP_Error Response data or WP_Error on failure.
	 */
	protected function make_request( $endpoint, $args = array() ) {
		$defaults = array(
			'timeout' => 30,
			'headers' => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Add authentication header
		$args['headers'] = array_merge( $args['headers'], $this->get_auth_headers() );

		$response = wp_remote_request( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $response_code >= 400 ) {
			return new \WP_Error(
				'api_error',
				sprintf( 'API request failed with status %d: %s', $response_code, $body )
			);
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'json_error', 'Failed to parse API response' );
		}

		return $data;
	}

	/**
	 * Get authentication headers for API requests.
	 *
	 * @return array
	 */
	abstract protected function get_auth_headers();

	/**
	 * Get rate limiting information from response headers.
	 *
	 * @param array $response The HTTP response.
	 * @return array Rate limiting information.
	 */
	protected function get_rate_limit_info( $response ) {
		$headers = wp_remote_retrieve_headers( $response );
		
		return array(
			'limit'     => $headers->get( 'X-RateLimit-Limit' ),
			'remaining' => $headers->get( 'X-RateLimit-Remaining' ),
			'reset'     => $headers->get( 'X-RateLimit-Reset' ),
		);
	}
} 