<?php
/**
 * REST API Security Handler Class.
 *
 * @package SG\HumanitixApiImporter
 */

namespace SG\HumanitixApiImporter\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class RestApiSecurityHandler.
 *
 * Provides secure REST API endpoint registration and handling.
 */
class RestApiSecurityHandler {

	/**
	 * Registered endpoints.
	 *
	 * @var array
	 */
	private static $registered_endpoints = array();

	/**
	 * Rate limiting data.
	 *
	 * @var array
	 */
	private static $rate_limits = array();

	/**
	 * Register a secure REST API endpoint.
	 *
	 * @param string $namespace_api API namespace.
	 * @param string $route API route.
	 * @param array  $args Endpoint arguments.
	 * @param array  $security_config Security configuration.
	 */
	public static function register_endpoint( $namespace_api, $route, $args = array(), $security_config = array() ) {
		$defaults = array(
			'capability_required'     => 'read',
			'require_login'           => true,
			'rate_limit'              => false,
			'max_requests_per_minute' => 60,
			'validate_nonce'          => false, // REST API uses different auth.
			'cors_enabled'            => false,
		);

		$config = wp_parse_args( $security_config, $defaults );

		// Store endpoint configuration.
		$endpoint_key                                = $namespace_api . '/' . $route;
		self::$registered_endpoints[ $endpoint_key ] = array(
			'args'   => $args,
			'config' => $config,
		);

		// Wrap the callback with security checks.
		if ( isset( $args['callback'] ) ) {
			$original_callback = $args['callback'];
			$args['callback']  = function ( $request ) use ( $original_callback, $config, $endpoint_key ) {
				return self::handle_secure_request( $request, $original_callback, $config, $endpoint_key );
			};
		}

		// Set permission callback if not provided.
		if ( ! isset( $args['permission_callback'] ) ) {
			$args['permission_callback'] = function ( $request ) use ( $config ) {
				return self::check_permissions( $request, $config );
			};
		}

		// Register the endpoint.
		add_action(
			'rest_api_init',
			function () use ( $namespace_api, $route, $args ) {
				register_rest_route( $namespace_api, $route, $args );
			}
		);

		// Add CORS headers if enabled.
		if ( $config['cors_enabled'] ) {
			add_action( 'rest_api_init', array( __CLASS__, 'add_cors_headers' ) );
		}
	}

	/**
	 * Handle secure REST API request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @param callable         $callback Original callback.
	 * @param array            $config Security configuration.
	 * @param string           $endpoint_key Endpoint identifier.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	private static function handle_secure_request( $request, $callback, $config, $endpoint_key ) {
		try {
			// Rate limiting.
			if ( $config['rate_limit'] ) {
				self::check_rate_limit( $endpoint_key, $config['max_requests_per_minute'] );
			}

			// Validate and sanitize request data.
			$validated_data = self::validate_request_data( $request );

			// Call the original callback with validated data.
			$result = call_user_func( $callback, $request, $validated_data );

			// Ensure we return a proper REST response.
			if ( ! is_wp_error( $result ) && ! ( $result instanceof \WP_REST_Response ) ) {
				$result = new \WP_REST_Response( $result, 200 );
			}

			return $result;

		} catch ( \Exception $e ) {
			error_log( '[sg-humanitix-api-importer] REST API Security Error: ' . $e->getMessage() );
			return new \WP_Error(
				'security_error',
				$e->getMessage(),
				array( 'status' => $e->getCode() ? $e->getCode() : 403 )
			);
		}
	}

	/**
	 * Check permissions for REST API request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @param array            $config Security configuration.
	 * @return bool Whether request is permitted.
	 */
	private static function check_permissions( $request, $config ) {
		// Check login requirement.
		if ( $config['require_login'] && ! is_user_logged_in() ) {
			return false;
		}

		// Check user capabilities.
		if ( $config['capability_required'] && ! current_user_can( $config['capability_required'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check rate limiting for REST API endpoints.
	 *
	 * @param string $endpoint_key Endpoint identifier.
	 * @param int    $max_requests_per_minute Maximum requests per minute.
	 * @throws \Exception If rate limit exceeded.
	 */
	private static function check_rate_limit( $endpoint_key, $max_requests_per_minute ) {
		$user_id      = get_current_user_id();
		$ip_address   = self::get_client_ip();
		$key          = $endpoint_key . '_' . ( $user_id ? $user_id : $ip_address );
		$current_time = time();

		// Clean old entries.
		if ( isset( self::$rate_limits[ $key ] ) ) {
			self::$rate_limits[ $key ] = array_filter(
				self::$rate_limits[ $key ],
				function ( $timestamp ) use ( $current_time ) {
					return $current_time - $timestamp < 60; // Keep last minute.
				}
			);
		}

		// Check current rate.
		$current_requests = count( self::$rate_limits[ $key ] ?? array() );

		if ( $current_requests >= $max_requests_per_minute ) {
			throw new \Exception( 'Rate limit exceeded. Please try again later.', 429 );
		}

		// Add current request.
		self::$rate_limits[ $key ][] = $current_time;
	}

	/**
	 * Validate and sanitize REST API request data.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return array Validated data.
	 */
	private static function validate_request_data( $request ) {
		$validated_data = array();

		// Get all parameters from request.
		$params = $request->get_params();

		foreach ( $params as $key => $value ) {
			// Basic sanitization - can be extended based on field types.
			if ( is_string( $value ) ) {
				$validated_data[ $key ] = sanitize_text_field( $value );
			} elseif ( is_array( $value ) ) {
				$validated_data[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$validated_data[ $key ] = $value;
			}
		}

		return $validated_data;
	}

	/**
	 * Add CORS headers for REST API.
	 */
	public static function add_cors_headers() {
		add_filter(
			'rest_pre_serve_request',
			function ( $served, $result, $request, $server ) {
				$server->send_header( 'Access-Control-Allow-Origin', '*' );
				$server->send_header( 'Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS' );
				$server->send_header( 'Access-Control-Allow-Headers', 'Content-Type, Authorization, X-WP-Nonce' );
				return $served;
			},
			10,
			4
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip() {
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated IPs.
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1'; // Fallback.
	}

	/**
	 * Create nonce for REST API requests.
	 *
	 * @return string Generated nonce.
	 */
	public static function create_nonce() {
		return wp_create_nonce( 'wp_rest' );
	}

	/**
	 * Get REST API URL.
	 *
	 * @param string $namespace_api API namespace.
	 * @param string $route API route.
	 * @return string REST API URL.
	 */
	public static function get_rest_url( $namespace_api = '', $route = '' ) {
		if ( $namespace_api && $route ) {
			return rest_url( $namespace_api . '/' . $route );
		}
		return rest_url();
	}

	/**
	 * Enqueue REST API data for frontend.
	 *
	 * @param string $handle Script handle to localize.
	 * @param string $object_name JavaScript object name.
	 * @param array  $additional_data Additional data to include.
	 */
	public static function localize_rest_data( $handle, $object_name = 'sgHumanitixApiImporterRestApi', $additional_data = array() ) {
		$rest_data = wp_parse_args(
			$additional_data,
			array(
				'restUrl'   => rest_url(),
				'nonce'     => self::create_nonce(),
				'namespace' => 'sg-humanitix-api-importer/v1',
			)
		);

		wp_localize_script( $handle, $object_name, $rest_data );
	}

	/**
	 * Get registered endpoints (for debugging).
	 *
	 * @return array Registered endpoints.
	 */
	public static function get_registered_endpoints() {
		return self::$registered_endpoints;
	}
}
