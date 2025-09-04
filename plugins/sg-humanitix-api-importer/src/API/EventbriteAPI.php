<?php
namespace SG\HumanitixApiImporter\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eventbrite API client implementing OAuth 2.0 authentication
 */
class EventbriteAPI extends AbstractEventAPI {

	/**
	 * OAuth 2.0 endpoints
	 */
	const OAUTH_AUTHORIZE_URL = 'https://www.eventbrite.com/oauth/authorize';
	const OAUTH_TOKEN_URL = 'https://www.eventbrite.com/oauth/token';
	const API_BASE_URL = 'https://www.eventbriteapi.com/v3';

	/**
	 * OAuth credentials
	 */
	protected $client_id;
	protected $client_secret;
	protected $redirect_uri;
	protected $access_token;
	protected $refresh_token;
	protected $token_expires_at;

	/**
	 * Constructor
	 */
	public function __construct( $client_id, $client_secret, $redirect_uri = null ) {
		// Call parent constructor with client_id as api_key
		parent::__construct( $client_id, '', '' );
		
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->redirect_uri = $redirect_uri ?: $this->get_default_redirect_uri();
		$this->api_endpoint = $this->get_default_endpoint();
		$this->provider_name = 'eventbrite';
		
		// Load stored tokens
		$this->load_tokens();
	}

	/**
	 * Get default redirect URI
	 */
	private function get_default_redirect_uri() {
		return admin_url( 'admin.php?page=eventbrite-oauth-callback' );
	}

	/**
	 * Load stored OAuth tokens
	 */
	private function load_tokens() {
		$tokens = get_option( 'sg_hai_eventbrite_tokens', array() );
		
		if ( ! empty( $tokens ) ) {
			$this->access_token = $tokens['access_token'] ?? null;
			$this->refresh_token = $tokens['refresh_token'] ?? null;
			$this->token_expires_at = $tokens['expires_at'] ?? null;
		}
	}

	/**
	 * Save OAuth tokens
	 */
	private function save_tokens( $access_token, $refresh_token = null, $expires_in = 3600 ) {
		$tokens = array(
			'access_token' => $access_token,
			'refresh_token' => $refresh_token ?: $this->refresh_token,
			'expires_at' => time() + $expires_in,
		);
		
		update_option( 'sg_hai_eventbrite_tokens', $tokens );
		
		$this->access_token = $access_token;
		$this->refresh_token = $tokens['refresh_token'];
		$this->token_expires_at = $tokens['expires_at'];
	}

	/**
	 * Check if we have a valid access token
	 */
	public function is_authenticated() {
		return ! empty( $this->access_token ) && 
		       ( ! $this->token_expires_at || $this->token_expires_at > time() + 60 );
	}

	/**
	 * Get authorization URL for OAuth flow
	 */
	public function get_authorization_url( $state = null ) {
		$params = array(
			'response_type' => 'code',
			'client_id' => $this->client_id,
			'redirect_uri' => $this->redirect_uri,
		);

		if ( $state ) {
			$params['state'] = $state;
		}

		return self::OAUTH_AUTHORIZE_URL . '?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for access token
	 */
	public function exchange_code_for_token( $code, $state = null ) {
		$response = wp_remote_post( self::OAUTH_TOKEN_URL, array(
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body' => array(
				'grant_type' => 'authorization_code',
				'client_id' => $this->client_id,
				'client_secret' => $this->client_secret,
				'code' => $code,
				'redirect_uri' => $this->redirect_uri,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || isset( $data['error'] ) ) {
			return new \WP_Error( 'oauth_error', $data['error_description'] ?? 'OAuth token exchange failed' );
		}

		// Save tokens
		$this->save_tokens(
			$data['access_token'],
			$data['refresh_token'] ?? null,
			$data['expires_in'] ?? 3600
		);

		return $data;
	}

	/**
	 * Refresh access token using refresh token
	 */
	public function refresh_access_token() {
		if ( empty( $this->refresh_token ) ) {
			return new \WP_Error( 'no_refresh_token', 'No refresh token available' );
		}

		$response = wp_remote_post( self::OAUTH_TOKEN_URL, array(
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body' => array(
				'grant_type' => 'refresh_token',
				'client_id' => $this->client_id,
				'client_secret' => $this->client_secret,
				'refresh_token' => $this->refresh_token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || isset( $data['error'] ) ) {
			return new \WP_Error( 'oauth_error', $data['error_description'] ?? 'Token refresh failed' );
		}

		// Save new tokens
		$this->save_tokens(
			$data['access_token'],
			$data['refresh_token'] ?? $this->refresh_token,
			$data['expires_in'] ?? 3600
		);

		return $data;
	}

	/**
	 * Ensure we have a valid access token
	 */
	private function ensure_valid_token() {
		if ( ! $this->is_authenticated() ) {
			if ( $this->refresh_token ) {
				$result = $this->refresh_access_token();
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			} else {
				return new \WP_Error( 'not_authenticated', 'Not authenticated with Eventbrite API' );
			}
		}

		return true;
	}

	/**
	 * Make authenticated API request
	 */
	protected function make_request( $endpoint, $args = array() ) {
		// Ensure we have a valid token
		$token_check = $this->ensure_valid_token();
		if ( is_wp_error( $token_check ) ) {
			return $token_check;
		}

		// Prepare request arguments
		$request_args = array_merge( array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type' => 'application/json',
			),
		), $args );

		// Make the request
		$url = $this->api_endpoint . '/' . ltrim( $endpoint, '/' );
		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $status_code >= 400 ) {
			$error_data = json_decode( $body, true );
			$error_message = $error_data['error_description'] ?? 'API request failed';
			
			// Debug: Log the full error response
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Eventbrite API] Error response: ' . $body );
				error_log( '[Eventbrite API] Status code: ' . $status_code );
				error_log( '[Eventbrite API] Error message: ' . $error_message );
			}
			
			return new \WP_Error( 'api_error', $error_message, array( 'status' => $status_code ) );
		}

		return json_decode( $body, true );
	}

	/**
	 * Fetch events from Eventbrite
	 */
	public function fetch_events( $args = array() ) {
		// Debug: Check authentication status
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Eventbrite API] fetch_events called - authenticated: ' . ( $this->is_authenticated() ? 'YES' : 'NO' ) );
			error_log( '[Eventbrite API] access_token present: ' . ( ! empty( $this->access_token ) ? 'YES' : 'NO' ) );
		}

		$defaults = array(
			'status' => 'live',
			'order_by' => 'start_asc',
			'expand' => 'venue,organizer',
		);

		$params = array_merge( $defaults, $args );
		$query_string = http_build_query( $params );

		// Try different endpoints - users/me/events doesn't exist, try users/me/owned_events
		$endpoint = 'users/me/owned_events?' . $query_string;
		
		// Debug: Log the endpoint being called
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Eventbrite API] Calling endpoint: ' . $endpoint );
		}

		$result = $this->make_request( $endpoint );
		
		// If owned_events fails, try getting organizations first
		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Eventbrite API] owned_events failed, trying organizations approach' );
			}
			
			// Get user's organizations
			$orgs = $this->make_request( 'users/me/organizations' );
			if ( is_wp_error( $orgs ) ) {
				return $orgs;
			}
			
			// Get events from the first organization (if any)
			if ( ! empty( $orgs['organizations'] ) ) {
				$org_id = $orgs['organizations'][0]['id'];
				$org_endpoint = 'organizations/' . $org_id . '/events?' . $query_string;
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Eventbrite API] Trying organization events: ' . $org_endpoint );
				}
				
				return $this->make_request( $org_endpoint );
			}
		}
		
		return $result;
	}

	/**
	 * Fetch a single event by ID
	 */
	public function fetch_event( $event_id ) {
		return $this->make_request( 'events/' . $event_id . '/?expand=venue,organizer' );
	}

	/**
	 * Test API connection
	 */
	public function test_connection() {
		// Debug: Log the test connection attempt
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Eventbrite API] Testing connection to users/me/' );
		}

		$result = $this->make_request( 'users/me/' );
		
		if ( is_wp_error( $result ) ) {
			// Debug: Log the connection test failure
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Eventbrite API] Connection test failed: ' . $result->get_error_message() );
			}
			return $result;
		}

		// Debug: Log successful connection
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Eventbrite API] Connection test successful - user: ' . ( $result['id'] ?? 'unknown' ) );
		}

		return array(
			'success' => true,
			'user' => $result,
		);
	}

	/**
	 * Get user information
	 */
	public function get_user_info() {
		return $this->make_request( 'users/me/' );
	}

	/**
	 * Clear stored tokens (logout)
	 */
	public function clear_tokens() {
		delete_option( 'sg_hai_eventbrite_tokens' );
		$this->access_token = null;
		$this->refresh_token = null;
		$this->token_expires_at = null;
	}

	/**
	 * Get stored tokens info
	 */
	public function get_token_info() {
		return array(
			'has_access_token' => ! empty( $this->access_token ),
			'has_refresh_token' => ! empty( $this->refresh_token ),
			'expires_at' => $this->token_expires_at,
			'is_valid' => $this->is_authenticated(),
		);
	}

	/**
	 * Validate API key format (OAuth Client ID for Eventbrite)
	 */
	public function validate_api_key_format( $api_key ) {
		// Eventbrite Client IDs are typically alphanumeric strings
		return ! empty( $api_key ) && is_string( $api_key ) && strlen( $api_key ) > 10;
	}

	/**
	 * Get default API endpoint
	 */
	protected function get_default_endpoint() {
		return self::API_BASE_URL;
	}

	/**
	 * Get authentication headers for API requests
	 */
	protected function get_auth_headers() {
		if ( ! $this->is_authenticated() ) {
			return array();
		}

		return array(
			'Authorization' => 'Bearer ' . $this->access_token,
			'Content-Type' => 'application/json',
		);
	}
}