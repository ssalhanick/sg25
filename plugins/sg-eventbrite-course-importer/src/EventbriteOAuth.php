<?php
/**
 * Eventbrite OAuth2 Authentication Class.
 *
 * Handles OAuth2 authentication flow with Eventbrite API.
 *
 * @package SG\EventbriteCourseImporter
 * @since 1.0.0
 */

namespace SG\EventbriteCourseImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Eventbrite OAuth2 Authentication Class.
 *
 * Manages OAuth2 authentication flow with Eventbrite.
 *
 * @package SG\EventbriteCourseImporter
 * @since 1.0.0
 */
class EventbriteOAuth {

	/**
	 * Eventbrite OAuth2 base URL.
	 *
	 * @var string
	 */
	const OAUTH_BASE_URL = 'https://www.eventbrite.com/oauth';

	/**
	 * Eventbrite API base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://www.eventbriteapi.com/v3';

	/**
	 * Client ID for OAuth2.
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Client Secret for OAuth2.
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * Redirect URI for OAuth2 flow.
	 *
	 * @var string
	 */
	private $redirect_uri;

	/**
	 * Access token.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Refresh token.
	 *
	 * @var string
	 */
	private $refresh_token;

	/**
	 * Token expiration time.
	 *
	 * @var int
	 */
	private $token_expires_at;

	/**
	 * Logger instance.
	 *
	 * @var \SG\EventbriteCourseImporter\Admin\Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param string $client_id OAuth2 client ID.
	 * @param string $client_secret OAuth2 client secret.
	 */
	public function __construct( $client_id = null, $client_secret = null ) {
		$this->client_id = $client_id ? $client_id : get_option( 'sg_eventbrite_client_id', '' );
		$this->client_secret = $client_secret ? $client_secret : get_option( 'sg_eventbrite_client_secret', '' );
		$this->redirect_uri = admin_url( 'edit.php?post_type=sg_course&page=sg-eventbrite-settings&oauth_callback=1' );
		$this->logger = new \SG\EventbriteCourseImporter\Admin\Logger();

		// Load stored tokens
		$this->load_tokens();
	}

	/**
	 * Get OAuth2 authorization URL.
	 *
	 * @return string Authorization URL.
	 */
	public function get_authorization_url() {
		$params = array(
			'response_type' => 'code',
			'client_id'     => $this->client_id,
			'redirect_uri'  => $this->redirect_uri,
		);

		return self::OAUTH_BASE_URL . '/authorize?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for access token.
	 *
	 * @param string $code Authorization code from callback.
	 * @return array|WP_Error Token data or error.
	 */
	public function exchange_code_for_token( $code ) {
		$url = self::OAUTH_BASE_URL . '/token';
		
		$data = array(
			'grant_type'    => 'authorization_code',
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'code'          => $code,
			'redirect_uri'  => $this->redirect_uri,
		);

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body' => http_build_query( $data ),
		) );

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'OAuth2 token exchange failed: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_message = sprintf( 'OAuth2 token exchange failed with status %d', $response_code );
			$this->logger->log( $error_message . ': ' . $response_body, 'error' );
			return new \WP_Error( 'oauth_error', $error_message, array( 'status' => $response_code, 'body' => $response_body ) );
		}

		$token_data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = 'Failed to parse OAuth2 token response';
			$this->logger->log( $error_message . ': ' . json_last_error_msg(), 'error' );
			return new \WP_Error( 'json_error', $error_message );
		}

		// Store tokens
		$this->access_token = $token_data['access_token'];
		$this->refresh_token = $token_data['refresh_token'] ?? '';
		$this->token_expires_at = time() + ( $token_data['expires_in'] ?? 3600 );

		$this->save_tokens();

		$this->logger->log( 'OAuth2 tokens obtained successfully', 'info' );

		return $token_data;
	}

	/**
	 * Refresh access token using refresh token.
	 *
	 * @return array|WP_Error New token data or error.
	 */
	public function refresh_access_token() {
		if ( empty( $this->refresh_token ) ) {
			$settings_url = admin_url( 'edit.php?post_type=sg_course&page=sg-eventbrite-settings' );
			$error_message = sprintf( 
				'No refresh token available. Please <a href="%s" target="_blank">re-authorize with Eventbrite</a> in the plugin settings.',
				esc_url( $settings_url )
			);
			return new \WP_Error( 'no_refresh_token', $error_message );
		}

		$url = self::OAUTH_BASE_URL . '/token';
		
		$data = array(
			'grant_type'    => 'refresh_token',
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'refresh_token' => $this->refresh_token,
		);

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body' => http_build_query( $data ),
		) );

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'OAuth2 token refresh failed: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_message = sprintf( 'OAuth2 token refresh failed with status %d', $response_code );
			$this->logger->log( $error_message . ': ' . $response_body, 'error' );
			return new \WP_Error( 'oauth_error', $error_message, array( 'status' => $response_code, 'body' => $response_body ) );
		}

		$token_data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = 'Failed to parse OAuth2 refresh response';
			$this->logger->log( $error_message . ': ' . json_last_error_msg(), 'error' );
			return new \WP_Error( 'json_error', $error_message );
		}

		// Update tokens
		$this->access_token = $token_data['access_token'];
		$this->refresh_token = $token_data['refresh_token'] ?? $this->refresh_token;
		$this->token_expires_at = time() + ( $token_data['expires_in'] ?? 3600 );

		$this->save_tokens();

		$this->logger->log( 'OAuth2 tokens refreshed successfully', 'info' );

		return $token_data;
	}

	/**
	 * Get valid access token (refresh if needed).
	 *
	 * @return string|WP_Error Access token or error.
	 */
	public function get_valid_access_token() {
		// Check if token is expired or will expire soon (within 5 minutes)
		if ( empty( $this->access_token ) || ( $this->token_expires_at && time() >= ( $this->token_expires_at - 300 ) ) ) {
			$refresh_result = $this->refresh_access_token();
			if ( is_wp_error( $refresh_result ) ) {
				return $refresh_result;
			}
		}

		return $this->access_token;
	}

	/**
	 * Make authenticated request to Eventbrite API.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error Response data or error.
	 */
	public function make_authenticated_request( $endpoint, $args = array() ) {
		$access_token = $this->get_valid_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		// Handle both relative endpoints and full URLs
		if ( strpos( $endpoint, 'http' ) === 0 ) {
			// Full URL provided
			$url = $endpoint;
		} else {
			// Relative endpoint, prepend API base URL
			$url = self::API_BASE_URL . '/' . ltrim( $endpoint, '/' );
		}
		
		$defaults = array(
			'timeout'     => 30,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
		);

		$args = wp_parse_args( $args, $defaults );


		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$response_headers = wp_remote_retrieve_headers( $response );


		if ( 200 !== $response_code ) {
			$error_message = sprintf( 'Authenticated API request failed with status %d', $response_code );
			return new \WP_Error( 'api_error', $error_message, array( 'status' => $response_code, 'body' => $response_body ) );
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = 'Failed to parse authenticated API response';
			return new \WP_Error( 'json_error', $error_message );
		}
		return $data;
	}

	/**
	 * Check if user is authenticated.
	 *
	 * @return bool True if authenticated.
	 */
	public function is_authenticated() {
		return ! empty( $this->access_token );
	}

	/**
	 * Get user information.
	 *
	 * @return array|WP_Error User data or error.
	 */
	public function get_user_info() {
		return $this->make_authenticated_request( 'users/me/' );
	}

	/**
	 * Get user's organizations.
	 *
	 * @return array|WP_Error Organizations data or error.
	 */
	public function get_user_organizations() {
		return $this->make_authenticated_request( 'users/me/organizations/' );
	}

	/**
	 * Load tokens from database.
	 */
	private function load_tokens() {
		$this->access_token = get_option( 'sg_eventbrite_access_token', '' );
		$this->refresh_token = get_option( 'sg_eventbrite_refresh_token', '' );
		$this->token_expires_at = get_option( 'sg_eventbrite_token_expires_at', 0 );
	}

	/**
	 * Save tokens to database.
	 */
	private function save_tokens() {
		update_option( 'sg_eventbrite_access_token', $this->access_token );
		update_option( 'sg_eventbrite_refresh_token', $this->refresh_token );
		update_option( 'sg_eventbrite_token_expires_at', $this->token_expires_at );
	}

	/**
	 * Clear stored tokens.
	 */
	public function clear_tokens() {
		delete_option( 'sg_eventbrite_access_token' );
		delete_option( 'sg_eventbrite_refresh_token' );
		delete_option( 'sg_eventbrite_token_expires_at' );
		
		$this->access_token = '';
		$this->refresh_token = '';
		$this->token_expires_at = 0;
		
		$this->logger->log( 'OAuth2 tokens cleared', 'info' );
	}

	/**
	 * Set OAuth2 credentials.
	 *
	 * @param string $client_id OAuth2 client ID.
	 * @param string $client_secret OAuth2 client secret.
	 */
	public function set_credentials( $client_id, $client_secret ) {
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
	}
}