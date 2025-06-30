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
		$original_key = $api_key;
		$api_key      = sanitize_text_field( $api_key );

		$this->log_debug( 'HumanitixAPI: clean_api_key - Original key length: ' . strlen( $original_key ) );
		$this->log_debug( 'HumanitixAPI: clean_api_key - Original key preview: ' . substr( $original_key, 0, 20 ) . '...' );

		// Remove any whitespace.
		$api_key = trim( $api_key );

		// Remove any "Bearer " prefix if it was accidentally included.
		$api_key = preg_replace( '/^Bearer\s+/i', '', $api_key );

		// Remove any quotes if they were included.
		$api_key = trim( $api_key, '"\'`' );

		// Check for common issues.
		if ( strlen( $api_key ) > 200 ) {
			$this->log_debug( 'HumanitixAPI: clean_api_key - WARNING: API key is very long (' . strlen( $api_key ) . ' chars). This might be a JWT token or multi-line key.' );
		}

		if ( strpos( $api_key, "\n" ) !== false ) {
			$this->log_debug( 'HumanitixAPI: clean_api_key - WARNING: API key contains newlines. Removing them.' );
			$api_key = str_replace( array( "\n", "\r" ), '', $api_key );
		}

		if ( strpos( $api_key, ' ' ) !== false ) {
			$this->log_debug( 'HumanitixAPI: clean_api_key - WARNING: API key contains spaces. This might be a multi-part key.' );
		}

		// Check if it looks like a JWT token (3 parts separated by dots).
		$parts = explode( '.', $api_key );
		if ( count( $parts ) === 3 ) {
			$this->log_debug( 'HumanitixAPI: clean_api_key - INFO: API key appears to be a JWT token format.' );
		}

		$this->log_debug( 'HumanitixAPI: clean_api_key - Final key length: ' . strlen( $api_key ) );
		$this->log_debug( 'HumanitixAPI: clean_api_key - Final key preview: ' . substr( $api_key, 0, 20 ) . '...' );

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
			'preview'     => substr( $cleaned_key, 0, 20 ) . '...',
		);
	}

	/**
	 * Log debug information with fallback methods.
	 *
	 * @param string $message The message to log.
	 */
	private function log_debug( $message ) {
		// Try WordPress error_log first.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message );
		}

		// Also try to write to a custom log file.
		$log_file  = WP_CONTENT_DIR . '/humanitix-debug.log';
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] {$message}" . PHP_EOL;

		// Try to write to custom log file.
		@file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
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
				'/health' => 'Health check',
				'/ping'   => 'Ping endpoint',
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
		$this->log_debug( 'HumanitixAPI: get_events called with page: ' . $page );
		$this->log_debug( 'HumanitixAPI: API endpoint: ' . $this->api_endpoint );
		$this->log_debug( 'HumanitixAPI: Has API key: ' . ( ! empty( $this->api_key ) ? 'yes' : 'no' ) );
		$this->log_debug( 'HumanitixAPI: Has org ID: ' . ( ! empty( $this->org_id ) ? 'yes' : 'no' ) );

		$params = array(
			'page' => max( 1, absint( $page ) ),
		);

		// Try different possible endpoint structures.
		$possible_endpoints = array(
			'/events',
			'/event',
			'/organiser/events',
			'/organiser/' . $this->org_id . '/events',
		);

		foreach ( $possible_endpoints as $endpoint ) {
			$this->log_debug( 'HumanitixAPI: Trying endpoint: ' . $endpoint );
			$this->log_debug( 'HumanitixAPI: Making request to ' . $endpoint . ' with params: ' . print_r( $params, true ) );

			$response = $this->make_request( 'GET', $endpoint, $params );
			$this->log_debug( 'HumanitixAPI: Response from ' . $endpoint . ': ' . print_r( $response, true ) );

			if ( is_wp_error( $response ) ) {
				$this->log_debug( 'HumanitixAPI: ' . $endpoint . ' returned WP_Error: ' . $response->get_error_message() );
				continue; // Try next endpoint.
			}

			// Handle different response formats.
			$events = array();
			if ( isset( $response['data'] ) ) {
				$events = $response['data'];
				$this->log_debug( 'HumanitixAPI: Found events in response[data]: ' . count( $events ) . ' events' );
			} elseif ( isset( $response['events'] ) ) {
				$events = $response['events'];
				$this->log_debug( 'HumanitixAPI: Found events in response[events]: ' . count( $events ) . ' events' );
			} elseif ( is_array( $response ) ) {
				$events = $response;
				$this->log_debug( 'HumanitixAPI: Response is array with ' . count( $events ) . ' items' );
			}

			if ( ! empty( $events ) ) {
				$this->log_debug( 'HumanitixAPI: Successfully found events using endpoint: ' . $endpoint );
				return $events;
			}
		}

		$this->log_debug( 'HumanitixAPI: No events found with any endpoint, returning empty array' );
		return array();
	}

	/**
	 * Get a single event by ID.
	 *
	 * @param string $event_id The event ID.
	 * @return array|WP_Error Event data or error.
	 */
	public function get_event( $event_id ) {
		$response = $this->make_request( 'GET', '/events/' . sanitize_text_field( $event_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Handle different response formats.
		if ( isset( $response['data'] ) ) {
			return $response['data'];
		} elseif ( isset( $response['event'] ) ) {
			return $response['event'];
		}

		return $response;
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
		$url = trailingslashit( $this->api_endpoint ) . ltrim( $endpoint, '/' );
		$this->log_debug( 'HumanitixAPI: make_request - URL: ' . $url );
		$this->log_debug( 'HumanitixAPI: make_request - Method: ' . $method );
		$this->log_debug( 'HumanitixAPI: make_request - Params: ' . print_r( $params, true ) );

		$headers = array(
			'x-api-key'    => $this->api_key,  // Humanitix API expects x-api-key header.
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);

		// Add organization ID header if available.
		if ( ! empty( $this->org_id ) ) {
			$headers['X-Organiser-ID'] = $this->org_id;
		}

		$this->log_debug( 'HumanitixAPI: make_request - API Key length: ' . strlen( $this->api_key ) );
		$this->log_debug( 'HumanitixAPI: make_request - API Key preview: ' . substr( $this->api_key, 0, 10 ) . '...' );
		$this->log_debug( 'HumanitixAPI: make_request - Headers: ' . print_r( $headers, true ) );

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 30,
		);

		// Add parameters based on method.
		if ( 'GET' === $method && ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		} elseif ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && ! empty( $params ) ) {
			$args['body'] = wp_json_encode( $params );
		}

		$this->log_debug( 'HumanitixAPI: make_request - Final URL: ' . $url );
		$this->log_debug( 'HumanitixAPI: make_request - Final args: ' . print_r( $args, true ) );

		// For test requests, limit the response size.
		if ( $is_test ) {
			$args['timeout'] = 10;
		}

		$response = wp_remote_request( $url, $args );
		$this->log_debug( 'HumanitixAPI: make_request - wp_remote_request response: ' . print_r( $response, true ) );

		if ( is_wp_error( $response ) ) {
			$this->log_debug( 'HumanitixAPI: make_request - wp_remote_request returned WP_Error: ' . $response->get_error_message() );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		$this->log_debug( 'HumanitixAPI: make_request - Status code: ' . $status_code );
		$this->log_debug( 'HumanitixAPI: make_request - Response body: ' . substr( $body, 0, 500 ) );

		// For test requests, return the full response for debugging.
		if ( $is_test ) {
			return $response;
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log_debug( 'HumanitixAPI: make_request - JSON decode error: ' . json_last_error_msg() );
			return new \WP_Error( 'json_error', 'Invalid JSON response from API: ' . $body );
		}

		$this->log_debug( 'HumanitixAPI: make_request - Decoded data: ' . print_r( $data, true ) );
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

		return new \WP_Error( 'schema_not_found', 'Could not retrieve API schema from any endpoint' );
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
