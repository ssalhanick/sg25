<?php
/**
 * Humanitix API Class.
 *
 * Handles communication with the Humanitix API for fetching events and related data.
 *
 * @package SG\HumanitixApiImporter
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Humanitix API Class.
 *
 * Handles communication with the Humanitix API for fetching events and related data.
 *
 * @package SG\HumanitixApiImporter
 * @since 1.0.0
 */
class HumanitixAPI {

	/**
	 * The API key for authentication.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * The API endpoint base URL.
	 *
	 * @var string
	 */
	private $api_endpoint;

	/**
	 * The organization ID for scoping requests.
	 *
	 * @var string
	 */
	private $org_id;

	/**
	 * Constructor.
	 *
	 * @param string $api_key The Humanitix API key.
	 * @param string $api_endpoint Optional custom API endpoint.
	 * @param string $org_id The Humanitix organization ID.
	 */
	public function __construct( $api_key, $api_endpoint = '', $org_id = '' ) {
		// Clean and validate API key.
		$this->api_key = $this->clean_api_key( $api_key );
		$this->org_id  = sanitize_text_field( $org_id );

		// Default to Humanitix API endpoint if not provided.
		$this->api_endpoint = ! empty( $api_endpoint )
			? esc_url_raw( $api_endpoint )
			: 'https://api.humanitix.com/v1';
	}

	/**
	 * Clean and validate API key format.
	 *
	 * @param string $api_key The raw API key.
	 * @return string The cleaned API key.
	 */
	private function clean_api_key( $api_key ) {
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
	 * Validate API key format and provide guidance.
	 *
	 * @param string $api_key The API key to validate.
	 * @return array Validation result with status and suggestions.
	 */
	public function validate_api_key_format( $api_key ) {
		$cleaned_key = $this->clean_api_key( $api_key );
		$issues      = array();
		$suggestions = array();

		// Check length.
		if ( strlen( $cleaned_key ) < 10 ) {
			$issues[]      = 'API key is too short (less than 10 characters)';
			$suggestions[] = 'Make sure you copied the complete API key from your Humanitix console';
		}

		if ( strlen( $cleaned_key ) > 500 ) {
			$issues[]      = 'API key is unusually long (' . strlen( $cleaned_key ) . ' characters)';
			$suggestions[] = 'You may have copied extra content. Try copying just the API key portion';
		}

		// Check for common patterns.
		if ( strpos( $cleaned_key, ' ' ) !== false ) {
			$issues[]      = 'API key contains spaces';
			$suggestions[] = 'Remove any spaces from the API key';
		}

		if ( strpos( $cleaned_key, "\n" ) !== false ) {
			$issues[]      = 'API key contains line breaks';
			$suggestions[] = 'Remove any line breaks from the API key';
		}

		// Check if it looks like a JWT.
		$parts = explode( '.', $cleaned_key );
		if ( count( $parts ) === 3 ) {
			$suggestions[] = 'This appears to be a JWT token. Make sure you\'re using the correct API key format for Humanitix';
		}

		// Check for common prefixes that shouldn't be there.
		if ( preg_match( '/^(Bearer|Token|API-Key)\s+/i', $cleaned_key ) ) {
			$issues[]      = 'API key contains authentication prefix';
			$suggestions[] = 'Remove any "Bearer ", "Token ", or "API-Key " prefixes from the key';
		}

		// Check for x-api-key prefix (which is correct for Humanitix).
		if ( preg_match( '/^x-api-key\s*:\s*/i', $cleaned_key ) ) {
			$issues[]      = 'API key contains x-api-key header prefix';
			$suggestions[] = 'Remove the "x-api-key: " prefix from the key - it will be added automatically';
		}

		return array(
			'valid'       => empty( $issues ),
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'length'      => strlen( $cleaned_key ),
			'preview'     => '[REDACTED]', // Don't expose API key preview.
		);
	}

	/**
	 * Log debug information with fallback methods.
	 *
	 * @param string $message The message to log.
	 */
	private function log_debug( $message ) {
		// Only log if WP_DEBUG is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message );
		}
	}

	/**
	 * Test API connection.
	 *
	 * @return array Test result with status and message.
	 */
	public function test_connection() {
		// Initialize logger.
		$logger = new \SG\HumanitixApiImporter\Admin\Logger();

		try {
			// Check if this is the mock server.
			$is_mock_server = strpos( $this->api_endpoint, 'stoplight.io/mocks' ) !== false;

			// Log the connection test attempt with organization ID info.
			$logger->log(
				'info',
				'Starting API connection test',
				array(
					'endpoint'       => $this->api_endpoint,
					'is_mock_server' => $is_mock_server,
					'has_org_id'     => ! empty( $this->org_id ),
					'org_id'         => ! empty( $this->org_id ) ? substr( $this->org_id, 0, 8 ) . '...' : 'not_set',
					'test_type'      => 'connection_test',
				)
			);

			// Validate required credentials.
			if ( empty( $this->api_key ) ) {
				$error_message = 'API key is required for connection test.';
				$logger->log( 'error', $error_message, array( 'test_type' => 'connection_test' ) );
				return array(
					'success' => false,
					'message' => $error_message,
					'debug'   => array( 'missing_api_key' => true ),
				);
			}

			// Validate API key format.
			$validation = $this->validate_api_key_format( $this->api_key );
			if ( ! $validation['valid'] ) {
				$error_message = 'API key format is invalid: ' . implode( ', ', $validation['issues'] );
				$suggestions   = ! empty( $validation['suggestions'] ) ? ' Suggestions: ' . implode( '; ', $validation['suggestions'] ) : '';
				$full_message  = $error_message . $suggestions;

				$logger->log(
					'error',
					$full_message,
					array(
						'test_type'  => 'connection_test',
						'validation' => $validation,
					)
				);

				return array(
					'success' => false,
					'message' => $full_message,
					'debug'   => array(
						'invalid_api_key_format' => true,
						'validation'             => $validation,
					),
				);
			}

			if ( empty( $this->org_id ) ) {
				$error_message = 'Organization ID is required for connection test.';
				$logger->log( 'error', $error_message, array( 'test_type' => 'connection_test' ) );
				return array(
					'success' => false,
					'message' => $error_message,
					'debug'   => array( 'missing_org_id' => true ),
				);
			}

					// Try different endpoints to test the connection.
		$test_endpoints = array(
			'/'       => 'Root endpoint',
			'/events' => 'Events endpoint',
		);

			foreach ( $test_endpoints as $endpoint => $description ) {
				$response = $this->make_request( 'GET', $endpoint, array(), true );

				if ( is_wp_error( $response ) ) {
					// Log failed endpoint attempt.
					$logger->log(
						'warning',
						"API endpoint test failed: {$description}",
						array(
							'endpoint'    => $this->api_endpoint . $endpoint,
							'description' => $description,
							'error'       => $response->get_error_message(),
							'test_type'   => 'connection_test',
						)
					);
					continue; // Try next endpoint.
				}

				$status_code = wp_remote_retrieve_response_code( $response );
				$body        = wp_remote_retrieve_body( $response );

				// For mock server, 422 is actually a valid response indicating the server is working.
				if ( $is_mock_server && 422 === $status_code ) {
					$success_message = 'Mock server connection successful! Server is responding (422 indicates endpoint not found, but server is reachable).';

					// Log successful mock server connection.
					$logger->log(
						'success',
						$success_message,
						array(
							'endpoint'         => $this->api_endpoint . $endpoint,
							'status_code'      => $status_code,
							'working_endpoint' => $endpoint,
							'is_mock_server'   => true,
							'response_preview' => substr( $body, 0, 200 ),
							'test_type'        => 'connection_test',
						)
					);

					return array(
						'success' => true,
						'message' => $success_message,
						'debug'   => array(
							'endpoint'         => $this->api_endpoint . $endpoint,
							'status_code'      => $status_code,
							'working_endpoint' => $endpoint,
							'is_mock_server'   => true,
							'response_preview' => substr( $body, 0, 200 ),
						),
					);
				}

				// If we get a 200, 201, or even a 404, the server is responding.
				if ( in_array( $status_code, array( 200, 201, 404 ) ) ) {
					$success_message = "API connection successful! Server responded with status {$status_code} on {$description}.";

					// Log successful connection.
					$logger->log(
						'success',
						$success_message,
						array(
							'endpoint'         => $this->api_endpoint . $endpoint,
							'status_code'      => $status_code,
							'working_endpoint' => $endpoint,
							'response_preview' => substr( $body, 0, 200 ),
							'test_type'        => 'connection_test',
						)
					);

					return array(
						'success' => true,
						'message' => $success_message,
						'debug'   => array(
							'endpoint'         => $this->api_endpoint . $endpoint,
							'status_code'      => $status_code,
							'working_endpoint' => $endpoint,
							'response_preview' => substr( $body, 0, 200 ),
						),
					);
				}
			}

			// If none of the test endpoints worked, try a simple GET request.
			$response = wp_remote_get( $this->api_endpoint, array( 'timeout' => 10 ) );

			if ( is_wp_error( $response ) ) {
				$error_message = 'API connection failed: ' . $response->get_error_message();

				// Log connection failure.
				$logger->log(
					'error',
					$error_message,
					array(
						'endpoint'  => $this->api_endpoint,
						'error'     => $response->get_error_message(),
						'test_type' => 'connection_test',
					)
				);

				return array(
					'success' => false,
					'message' => $error_message,
					'debug'   => array(
						'endpoint' => $this->api_endpoint,
						'error'    => $response->get_error_message(),
					),
				);
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = wp_remote_retrieve_body( $response );

			// For mock server, 422 is actually a valid response.
			if ( $is_mock_server && 422 === $status_code ) {
				$success_message = 'Mock server connection successful! Server is responding (422 indicates endpoint not found, but server is reachable).';

				// Log successful mock server connection.
				$logger->log(
					'success',
					$success_message,
					array(
						'endpoint'         => $this->api_endpoint,
						'status_code'      => $status_code,
						'is_mock_server'   => true,
						'response_preview' => substr( $body, 0, 200 ),
						'test_type'        => 'connection_test',
					)
				);

				return array(
					'success' => true,
					'message' => $success_message,
					'debug'   => array(
						'endpoint'         => $this->api_endpoint,
						'status_code'      => $status_code,
						'is_mock_server'   => true,
						'response_preview' => substr( $body, 0, 200 ),
					),
				);
			}

			if ( in_array( $status_code, array( 200, 201, 404 ) ) ) {
				$success_message = "API connection successful! Server responded with status {$status_code}.";

				// Log successful connection.
				$logger->log(
					'success',
					$success_message,
					array(
						'endpoint'         => $this->api_endpoint,
						'status_code'      => $status_code,
						'response_preview' => substr( $body, 0, 200 ),
						'test_type'        => 'connection_test',
					)
				);

				return array(
					'success' => true,
					'message' => $success_message,
					'debug'   => array(
						'endpoint'         => $this->api_endpoint,
						'status_code'      => $status_code,
						'response_preview' => substr( $body, 0, 200 ),
					),
				);
			} elseif ( 401 === $status_code ) {
				$error_message = 'Authentication failed. Please check your API key.';

				// Log authentication failure.
				$logger->log(
					'error',
					$error_message,
					array(
						'endpoint'    => $this->api_endpoint,
						'status_code' => $status_code,
						'response'    => $body,
						'test_type'   => 'connection_test',
					)
				);

				return array(
					'success' => false,
					'message' => $error_message,
					'debug'   => array(
						'endpoint'    => $this->api_endpoint,
						'status_code' => $status_code,
						'response'    => $body,
					),
				);
			} elseif ( 422 === $status_code ) {
				$error_message = 'API endpoint not found or invalid. Please check your API endpoint URL.';

				// Log endpoint not found error.
				$logger->log(
					'error',
					$error_message,
					array(
						'endpoint'    => $this->api_endpoint,
						'status_code' => $status_code,
						'response'    => $body,
						'suggestion'  => 'Try using the root endpoint or check the API documentation for correct paths.',
						'test_type'   => 'connection_test',
					)
				);

				return array(
					'success' => false,
					'message' => $error_message,
					'debug'   => array(
						'endpoint'    => $this->api_endpoint,
						'status_code' => $status_code,
						'response'    => $body,
						'suggestion'  => 'Try using the root endpoint or check the API documentation for correct paths.',
					),
				);
			} else {
				$error_message = 'API connection failed. Status code: ' . $status_code;

				// Log connection failure with unexpected status code.
				$logger->log(
					'error',
					$error_message,
					array(
						'endpoint'    => $this->api_endpoint,
						'status_code' => $status_code,
						'response'    => $body,
						'test_type'   => 'connection_test',
					)
				);

				return array(
					'success' => false,
					'message' => $error_message,
					'debug'   => array(
						'endpoint'    => $this->api_endpoint,
						'status_code' => $status_code,
						'response'    => $body,
					),
				);
			}
		} catch ( \Exception $e ) {
			$error_message = 'API connection error: ' . $e->getMessage();

			// Log exception.
			$logger->log(
				'error',
				$error_message,
				array(
					'endpoint'  => $this->api_endpoint,
					'error'     => $e->getMessage(),
					'trace'     => $e->getTraceAsString(),
					'test_type' => 'connection_test',
				)
			);

			return array(
				'success' => false,
				'message' => $error_message,
				'debug'   => array(
					'endpoint' => $this->api_endpoint,
					'error'    => $e->getMessage(),
				),
			);
		}
	}

	/**
	 * Get events from Humanitix API.
	 *
	 * @param int $page Page number to fetch (>= 1).
	 * @return array|WP_Error Events data or error.
	 */
	public function get_events( $page = 1 ) {
		// Initialize debug helper.
		$logger       = new \SG\HumanitixApiImporter\Admin\Logger();
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $logger );

		$debug_helper->log(
			'API',
			"get_events called with page: {$page}",
			array(
				'endpoint'    => $this->api_endpoint,
				'has_api_key' => ! empty( $this->api_key ),
				'has_org_id'  => ! empty( $this->org_id ),
				'api_url'     => trailingslashit( $this->api_endpoint ) . 'events',
			)
		);

		$params = array(
			'page' => max( 1, absint( $page ) ),
			'limit' => 50, // Add limit parameter for better pagination
		);

		// Use the correct Humanitix API endpoint for events.
		$possible_endpoints = array(
			'/events', // Primary endpoint according to Humanitix API docs.
		);

		foreach ( $possible_endpoints as $endpoint ) {
			$debug_helper->log( 'API', "Trying endpoint: {$endpoint}" );

			$response = $this->make_request( 'GET', $endpoint, $params );

			if ( is_wp_error( $response ) ) {
				$debug_helper->log_critical_error(
					'API',
					"Endpoint {$endpoint} returned WP_Error: " . $response->get_error_message(),
					array(
						'endpoint'      => $endpoint,
						'error_message' => $response->get_error_message(),
					)
				);
				continue; // Try next endpoint.
			}

			// Handle different response formats.
			$events = array();
			if ( isset( $response['data'] ) ) {
				$events = $response['data'];
				$debug_helper->log( 'API', 'Found events in response[data]: ' . count( $events ) . ' events' );
			} elseif ( isset( $response['events'] ) ) {
				$events = $response['events'];
				$debug_helper->log( 'API', 'Found events in response[events]: ' . count( $events ) . ' events' );
			} elseif ( is_array( $response ) ) {
				$events = $response;
				$debug_helper->log( 'API', 'Response is array with ' . count( $events ) . ' items' );
			}

			if ( ! empty( $events ) ) {
				$debug_helper->log( 'API', "Successfully found events using endpoint: {$endpoint}" );
				return $events;
			}
		}

		$debug_helper->log( 'API', 'No events found with any endpoint, returning empty array' );
		return array();
	}

	/**
	 * Get a single event by ID with improved error handling and validation.
	 *
	 * @param string $event_id The event ID.
	 * @param int    $retry_attempts Number of retry attempts (default: 2).
	 * @return array|WP_Error Event data or error.
	 */
	public function get_event( $event_id, $retry_attempts = 2 ) {
		// Initialize debug helper.
		$logger       = new \SG\HumanitixApiImporter\Admin\Logger();
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $logger );

		// Validate event ID format
		$event_id = sanitize_text_field( $event_id );
		if ( empty( $event_id ) ) {
			$debug_helper->log_critical_error( 'API', 'Empty event ID provided' );
			return new \WP_Error( 'invalid_event_id', 'Event ID cannot be empty.' );
		}

		// Check if event ID looks valid (basic format check)
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $event_id ) ) {
			$debug_helper->log_critical_error( 'API', 'Invalid event ID format', array( 'event_id' => $event_id ) );
			return new \WP_Error( 'invalid_event_id', 'Invalid event ID format. Event ID should contain only letters, numbers, hyphens, and underscores.' );
		}

		$debug_helper->log( 'API', "Starting get_event with validated event_id: {$event_id}" );

		// Try to get from cache first
		$cache_key = 'humanitix_event_' . md5( $event_id );
		$cached_event = wp_cache_get( $cache_key, 'humanitix_events' );
		
		if ( $cached_event !== false ) {
			$debug_helper->log( 'API', "Event found in cache for event_id: {$event_id}" );
			return $cached_event;
		}

		$attempt = 0;
		$last_error = null;

		while ( $attempt <= $retry_attempts ) {
			$attempt++;
			
			$debug_helper->log( 'API', "Attempt {$attempt} to fetch event_id: {$event_id}" );

			$response = $this->make_request( 'GET', '/events/' . $event_id );

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				
				// Check if it's a 404 (event not found)
				if ( strpos( $response->get_error_message(), '404' ) !== false ) {
					$debug_helper->log_critical_error( 'API', "Event not found: {$event_id}" );
					return new \WP_Error( 'event_not_found', "Event with ID '{$event_id}' was not found. Please verify the event ID is correct." );
				}

				// Check if it's a network/timeout error
				if ( strpos( $response->get_error_message(), 'timeout' ) !== false || 
					 strpos( $response->get_error_message(), 'connection' ) !== false ) {
					
					if ( $attempt <= $retry_attempts ) {
						$debug_helper->log( 'API', "Network error on attempt {$attempt}, retrying in 2 seconds..." );
						sleep( 2 );
						continue;
					}
				}

				// For other errors, return immediately
				$debug_helper->log_critical_error( 'API', "API request failed on attempt {$attempt}", array(
					'event_id' => $event_id,
					'error' => $response->get_error_message()
				) );
				return $response;
			}

			// Handle different response formats
			$event_data = null;
			if ( isset( $response['data'] ) ) {
				$event_data = $response['data'];
			} elseif ( isset( $response['event'] ) ) {
				$event_data = $response['event'];
			} elseif ( is_array( $response ) && ! empty( $response ) ) {
				$event_data = $response;
			}

			if ( empty( $event_data ) ) {
				$debug_helper->log_critical_error( 'API', "Empty or invalid response for event_id: {$event_id}", array(
					'response_keys' => array_keys( $response ),
					'response_type' => gettype( $response )
				) );
				return new \WP_Error( 'invalid_response', 'Invalid or empty response from API for event ID: ' . $event_id );
			}

			// Validate that we have the expected event data structure
			if ( ! isset( $event_data['_id'] ) && ! isset( $event_data['id'] ) ) {
				$debug_helper->log_critical_error( 'API', "Response missing event ID for event_id: {$event_id}", array(
					'response_keys' => array_keys( $event_data )
				) );
				return new \WP_Error( 'invalid_event_data', 'Response does not contain valid event data structure.' );
			}

			// Cache the successful response for 5 minutes
			wp_cache_set( $cache_key, $event_data, 'humanitix_events', 300 );

			$debug_helper->log( 'API', "Successfully fetched event data for event_id: {$event_id}", array(
				'event_name' => $event_data['name'] ?? $event_data['title'] ?? 'Unknown',
				'event_id' => $event_data['_id'] ?? $event_data['id'] ?? 'unknown'
			) );

			return $event_data;
		}

		// If we get here, all retry attempts failed
		$debug_helper->log_critical_error( 'API', "All retry attempts failed for event_id: {$event_id}", array(
			'retry_attempts' => $retry_attempts,
			'last_error' => $last_error ? $last_error->get_error_message() : 'Unknown error'
		) );

		return $last_error ?: new \WP_Error( 'max_retries_exceeded', 'Failed to fetch event after multiple attempts. Please try again later.' );
	}

	/**
	 * Validate event ID format and provide helpful feedback.
	 *
	 * @param string $event_id The event ID to validate.
	 * @return array Validation result with success status and message.
	 */
	public function validate_event_id( $event_id ) {
		$event_id = trim( $event_id );
		
		if ( empty( $event_id ) ) {
			return array(
				'success' => false,
				'message' => 'Event ID cannot be empty.',
				'suggestion' => 'Please enter a valid Humanitix event ID.'
			);
		}

		// Check for common mistakes
		if ( strpos( $event_id, ' ' ) !== false ) {
			return array(
				'success' => false,
				'message' => 'Event ID contains spaces.',
				'suggestion' => 'Please remove any spaces from the event ID.'
			);
		}

		if ( strpos( $event_id, 'https://' ) !== false || strpos( $event_id, 'http://' ) !== false ) {
			return array(
				'success' => false,
				'message' => 'Event ID appears to be a URL.',
				'suggestion' => 'Please enter only the event ID, not the full URL. The event ID is usually found in the URL after /events/.'
			);
		}

		// Check if it looks like a valid Humanitix event ID format
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $event_id ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid event ID format.',
				'suggestion' => 'Event ID should contain only letters, numbers, hyphens, and underscores. Please check the event ID from your Humanitix console.'
			);
		}

		// Check length (Humanitix IDs are typically 24 characters)
		if ( strlen( $event_id ) < 10 ) {
			return array(
				'success' => false,
				'message' => 'Event ID seems too short.',
				'suggestion' => 'Humanitix event IDs are typically longer. Please verify you have the correct event ID from your Humanitix console.'
			);
		}

		return array(
			'success' => true,
			'message' => 'Event ID format looks valid.',
			'suggestion' => 'You can proceed with the import.'
		);
	}

	/**
	 * Get helpful information about finding event IDs.
	 *
	 * @return array Information about finding event IDs.
	 */
	public function get_event_id_help() {
		return array(
			'title' => 'How to Find Your Event ID',
			'steps' => array(
				'1. Log into your Humanitix console at https://console.humanitix.com',
				'2. Navigate to the event you want to import',
				'3. Look at the URL in your browser - it will look like: https://console.humanitix.com/console/events/{event_id}/overview',
				'4. Copy the {event_id} part (the string of letters and numbers)',
				'5. Paste it into the Event ID field above'
			),
			'example' => 'Example: If your URL is https://console.humanitix.com/console/events/507f1f77bcf86cd799439011/overview, then your Event ID is: 507f1f77bcf86cd799439011',
			'note' => 'Note: Event IDs are case-sensitive and should not include any extra characters or spaces.'
		);
	}

	/**
	 * Make HTTP request to Humanitix API.
	 *
	 * @param string $method HTTP method (GET, POST, etc.).
	 * @param string $endpoint API endpoint.
	 * @param array  $params Query parameters or body data.
	 * @param bool   $is_test Whether this is a test request.
	 * @return array|WP_Error Response data or error.
	 */
	private function make_request( $method, $endpoint, $params = array(), $is_test = false ) {
		// Initialize debug helper.
		$logger       = new \SG\HumanitixApiImporter\Admin\Logger();
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $logger );

		$url = trailingslashit( $this->api_endpoint ) . ltrim( $endpoint, '/' );

		$debug_helper->log(
			'API',
			"Making request to {$endpoint}",
			array(
				'url'          => $url,
				'method'       => $method,
				'params_count' => count( $params ),
				'is_test'      => $is_test,
			)
		);

		$headers = array(
			'x-api-key'    => $this->api_key,  // Humanitix API expects x-api-key header.
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);

		// Add organization ID header if available.
		if ( ! empty( $this->org_id ) ) {
			$headers['X-Organiser-ID'] = $this->org_id;
		}

		$args = array(
			'method'      => $method,
			'headers'     => $headers,
			'timeout'     => 60, // Increased timeout for better reliability.
			'httpversion' => '1.1',
			'user-agent'  => 'Humanitix-API-Importer/1.0',
		);

		// Add parameters based on method.
		if ( 'GET' === $method && ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		} elseif ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && ! empty( $params ) ) {
			$args['body'] = wp_json_encode( $params );
		}

		// For test requests, limit the response size.
		if ( $is_test ) {
			$args['timeout'] = 15;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$debug_helper->log_critical_error(
				'API',
				'wp_remote_request returned WP_Error: ' . $response->get_error_message(),
				array(
					'url'           => $url,
					'method'        => $method,
					'error_message' => $response->get_error_message(),
				)
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		$debug_helper->log(
			'API',
			'Response received',
			array(
				'status_code'  => $status_code,
				'body_length'  => strlen( $body ),
				'body_preview' => substr( $body, 0, 200 ),
			)
		);

		// Handle specific HTTP error codes.
		if ( $status_code >= 500 ) {
			$debug_helper->log_critical_error(
				'API',
				'Server error received',
				array(
					'status_code' => $status_code,
					'url'         => $url,
					'method'      => $method,
				)
			);
			return new \WP_Error( 'server_error', "Server error: HTTP {$status_code}" );
		}

		if ( $status_code >= 400 ) {
			// Parse error response for better debugging
			$error_data = json_decode( $body, true );
			$error_message = "Client error: HTTP {$status_code}";
			
			if ( $error_data && isset( $error_data['message'] ) ) {
				$error_message .= " - " . $error_data['message'];
			}
			
			$debug_helper->log_critical_error(
				'API',
				'Client error received',
				array(
					'status_code' => $status_code,
					'url'         => $url,
					'method'      => $method,
					'body'        => $body,
					'error_data'  => $error_data,
				)
			);
			return new \WP_Error( 'client_error', $error_message );
		}

		// For test requests, return the full response for debugging.
		if ( $is_test ) {
			return $response;
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$debug_helper->log_critical_error(
				'API',
				'JSON decode error: ' . json_last_error_msg(),
				array(
					'body_preview' => substr( $body, 0, 200 ),
					'json_error'   => json_last_error_msg(),
				)
			);
			return new \WP_Error( 'json_error', 'Invalid JSON response from API: ' . $body );
		}

		$debug_helper->log(
			'API',
			'Data decoded successfully',
			array(
				'data_type'   => gettype( $data ),
				'is_array'    => is_array( $data ),
				'array_count' => is_array( $data ) ? count( $data ) : 0,
			)
		);

		return $data;
	}

	/**
	 * Get API endpoint.
	 *
	 * @return string The API endpoint.
	 */
	public function get_api_endpoint() {
		return $this->api_endpoint;
	}

	/**
	 * Get organization ID.
	 *
	 * @return string The organization ID.
	 */
	public function get_org_id() {
		return $this->org_id;
	}

	/**
	 * Check if API key is set.
	 *
	 * @return bool Whether API key is set.
	 */
	public function has_api_key() {
		return ! empty( $this->api_key );
	}

	/**
	 * Check if organization ID is set.
	 *
	 * @return bool Whether organization ID is set.
	 */
	public function has_org_id() {
		return ! empty( $this->org_id );
	}

	/**
	 * Get API schema information.
	 *
	 * @return array|WP_Error Schema information or error.
	 */
	public function get_schema_info() {
		// Try to get schema from different endpoints.
		$schema_endpoints = array(
			'/schema'       => 'Schema endpoint',
			'/docs'         => 'Documentation endpoint',
			'/openapi.json' => 'OpenAPI schema',
			'/swagger.json' => 'Swagger schema',
			'/api-docs'     => 'API documentation',
			'/'             => 'Root endpoint',
		);

		foreach ( $schema_endpoints as $endpoint => $description ) {
			$response = $this->make_request( 'GET', $endpoint, array(), true );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = wp_remote_retrieve_body( $response );

			if ( in_array( $status_code, array( 200, 201 ) ) ) {
				$data = json_decode( $body, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					return array(
						'success'      => true,
						'endpoint'     => $endpoint,
						'description'  => $description,
						'schema'       => $data,
						'raw_response' => $body,
					);
				}
			}
		}

		// Humanitix API doesn't provide schema endpoints, so return a helpful error.
		return new \WP_Error(
			'schema_not_found',
			'The Humanitix API does not provide OpenAPI/Swagger schema endpoints. This is normal and expected. The plugin will analyze the actual event data structure instead.'
		);
	}

	/**
	 * Get sample event data for schema analysis.
	 *
	 * @param int $page Page number to fetch (>= 1).
	 * @return array|WP_Error Sample event data or error.
	 */
	public function get_sample_events( $page = 1 ) {
		$params = array(
			'page' => max( 1, absint( $page ) ),
		);

		$response = $this->make_request( 'GET', '/events', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Handle different response formats.
		$events = array();
		if ( isset( $response['data'] ) ) {
			$events = $response['data'];
		} elseif ( isset( $response['events'] ) ) {
			$events = $response['events'];
		} elseif ( is_array( $response ) ) {
			$events = $response;
		}

		return array(
			'success'      => true,
			'events'       => $events,
			'count'        => count( $events ),
			'raw_response' => $response,
		);
	}

	/**
	 * Analyze event data structure.
	 *
	 * @param array $event_data Event data to analyze.
	 * @return array Analysis results.
	 */
	public function analyze_event_structure( $event_data ) {
		$analysis = array(
			'fields'          => array(),
			'required_fields' => array(),
			'optional_fields' => array(),
			'data_types'      => array(),
			'nested_objects'  => array(),
			'arrays'          => array(),
		);

		if ( ! is_array( $event_data ) ) {
			return $analysis;
		}

		foreach ( $event_data as $field => $value ) {
			$field_info = array(
				'field'       => $field,
				'type'        => gettype( $value ),
				'value'       => $value,
				'is_required' => ! is_null( $value ),
			);

			$analysis['fields'][ $field ] = $field_info;

			if ( '' === is_null( $value ) || $value ) {
				$analysis['optional_fields'][ $field ] = $field_info;
			} else {
				$analysis['required_fields'][ $field ] = $field_info;
			}

			$analysis['data_types'][ $field ] = gettype( $value );

			if ( is_array( $value ) && ! empty( $value ) ) {
				if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
					// Associative array - nested object..
					$analysis['nested_objects'][ $field ] = $value;
				} else {
					// Indexed array.
					$analysis['arrays'][ $field ] = $value;
				}
			}
		}

		return $analysis;
	}
}
