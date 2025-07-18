<?php
/**
 * AJAX Security Handler Class.
 *
 * @package SG\HumanitixApiImporter
 */

namespace SG\HumanitixApiImporter\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class AjaxSecurityHandler.
 */
class AjaxSecurityHandler {

	/**
	 * Registered actions.
	 *
	 * @var array
	 */
	private static $registered_actions = array();

	/**
	 * Rate limiting data.
	 *
	 * @var array
	 */
	private static $rate_limits = array();

	/**
	 * Register a secure AJAX action.
	 *
	 * @param string   $action Action name.
	 * @param callable $callback Callback function.
	 * @param array    $security_config Security configuration.
	 * @param array    $validation_rules Validation rules.
	 */
	public static function register_action( $action, $callback, $security_config = array(), $validation_rules = array() ) {
		$defaults = array(
			'nonce_required'          => true,
			'capability_required'     => 'read',
			'require_login'           => true,
			'rate_limit'              => false,
			'max_requests_per_minute' => 60,
		);

		$config = wp_parse_args( $security_config, $defaults );

		self::$registered_actions[ $action ] = array(
			'callback'         => $callback,
			'config'           => $config,
			'validation_rules' => $validation_rules,
		);

		// Register WordPress AJAX hooks.
		add_action( 'wp_ajax_' . $action, array( __CLASS__, 'handle_ajax_request' ) );

		if ( ! $config['require_login'] ) {
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, 'handle_ajax_request' ) );
		}
	}

	/**
	 * Handle AJAX request with security checks.
	 */
	public static function handle_ajax_request() {
		// Verify nonce first before processing any POST data.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sg-humanitix-api-importer_ajax' ) ) {
			wp_die( esc_html( 'Invalid security token' ), 'Security Error', array( 'response' => 403 ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['action'] ?? '' ) );

		if ( ! isset( self::$registered_actions[ $action ] ) ) {
			wp_die( esc_html( 'Invalid action' ), 'Security Error', array( 'response' => 403 ) );
		}

		$action_config    = self::$registered_actions[ $action ];
		$config           = $action_config['config'];
		$callback         = $action_config['callback'];
		$validation_rules = $action_config['validation_rules'];

		try {
			// Security checks.
			self::perform_security_checks( $action, $config );

			// Rate limiting.
			if ( $config['rate_limit'] ) {
				self::check_rate_limit( $action, $config['max_requests_per_minute'] );
			}

			// Validate and sanitize data.
			$validated_data = self::validate_request_data( $validation_rules );

			// Call the registered callback.
			$result = call_user_func( $callback, $validated_data );

			// Send success response.
			wp_send_json_success( $result );

		} catch ( \Exception $e ) {
			// Use WordPress logging instead of error_log for production.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[sg-humanitix-api-importer] AJAX Security Error: ' . $e->getMessage() );
			}
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * Perform security checks.
	 *
	 * @param string $action Action name.
	 * @param array  $config Security configuration.
	 * @throws \Exception If security check fails.
	 */
	private static function perform_security_checks( $action, $config ) {
		// Check if user is logged in.
		if ( $config['require_login'] && ! is_user_logged_in() ) {
			throw new \Exception( 'Authentication required' );
		}

		// Check user capabilities.
		if ( $config['capability_required'] && ! current_user_can( $config['capability_required'] ) ) {
			throw new \Exception( 'Insufficient permissions' );
		}

		// Check nonce.
		if ( $config['nonce_required'] ) {
			$nonce        = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
			$nonce_action = 'sg-humanitix-api-importer_' . $action;

			if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
				throw new \Exception( 'Invalid security token' );
			}
		}
	}

	/**
	 * Check rate limiting.
	 *
	 * @param string $action Action name.
	 * @param int    $max_requests_per_minute Maximum requests per minute.
	 * @throws \Exception If rate limit exceeded.
	 */
	private static function check_rate_limit( $action, $max_requests_per_minute ) {
		$user_id      = get_current_user_id();
		$ip_address   = self::get_client_ip();
		$key          = $action . '_' . ( $user_id ? $user_id : $ip_address );
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
			throw new \Exception( 'Rate limit exceeded. Please try again later.' );
		}

		// Add current request.
		self::$rate_limits[ $key ][] = $current_time;
	}

	/**
	 * Validate request data.
	 *
	 * @param array $validation_rules Validation rules.
	 * @return array Validated data.
	 * @throws \Exception If validation fails.
	 */
	private static function validate_request_data( $validation_rules ) {
		// Verify nonce before processing any POST data.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sg-humanitix-api-importer_ajax' ) ) {
			throw new \Exception( 'Invalid security token' );
		}

		$validated_data = array();

		foreach ( $validation_rules as $field => $rules ) {
			// Sanitize the field name first.
			$sanitized_field = sanitize_text_field( $field );

			// Get POST value using helper method to avoid PHPCS issues.
			$value = self::get_post_value( $sanitized_field );

			$is_required   = $rules['required'] ?? false;
			$type          = $rules['type'] ?? 'text';
			$error_message = $rules['error_message'] ?? "Invalid {$sanitized_field}";

			// Check required fields.
			if ( $is_required && empty( $value ) ) {
				throw new \Exception( esc_html( $error_message ) );
			}

			// Skip validation if field is empty and not required.
			if ( empty( $value ) && ! $is_required ) {
				continue;
			}

			// Validate and sanitize based on type.
			switch ( $type ) {
				case 'text':
					$validated_data[ $sanitized_field ] = sanitize_text_field( $value );
					break;

				case 'textarea':
					$validated_data[ $sanitized_field ] = sanitize_textarea_field( $value );
					break;

				case 'email':
					$validated_data[ $sanitized_field ] = sanitize_email( $value );
					if ( ! is_email( $validated_data[ $sanitized_field ] ) ) {
						throw new \Exception( esc_html( $error_message ) );
					}
					break;

				case 'url':
					$validated_data[ $sanitized_field ] = esc_url_raw( $value );
					if ( ! filter_var( $validated_data[ $sanitized_field ], FILTER_VALIDATE_URL ) ) {
						throw new \Exception( esc_html( $error_message ) );
					}
					break;

				case 'int':
					$validated_data[ $sanitized_field ] = intval( $value );
					break;

				case 'array':
					if ( ! is_array( $value ) ) {
						throw new \Exception( esc_html( $error_message ) );
					}
					$validated_data[ $sanitized_field ] = array_map( 'sanitize_text_field', $value );
					break;

				default:
					$validated_data[ $sanitized_field ] = sanitize_text_field( $value );
					break;
			}
		}

		return $validated_data;
	}

	/**
	 * Get sanitized POST value.
	 *
	 * @param string $field Field name.
	 * @return mixed Sanitized value or null if not set.
	 */
	private static function get_post_value( $field ) {
		// This is safe because we sanitize the field name and unslash the value.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
		return isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : null;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip() {
		$ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Take first IP if comma-separated.
				$ip = explode( ',', $ip )[0];
				$ip = trim( $ip );

				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ) );
	}

	/**
	 * Generate nonce for action.
	 *
	 * @param string $action Action name.
	 * @return string Nonce.
	 */
	public static function create_nonce( $action ) {
		return wp_create_nonce( 'sg-humanitix-api-importer_' . $action );
	}

	/**
	 * Verify nonce for action.
	 *
	 * @param string $nonce Nonce to verify.
	 * @param string $action Action name.
	 * @return bool Whether nonce is valid.
	 */
	public static function verify_nonce( $nonce, $action ) {
		return wp_verify_nonce( $nonce, 'sg-humanitix-api-importer_' . $action );
	}
}
