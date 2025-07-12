<?php
/**
 * Security Validator Class.
 *
 * @package SG\HumanitixApiImporter
 */

namespace SG\HumanitixApiImporter\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class SecurityValidator.
 */
class SecurityValidator {

	/**
	 * Validate and sanitize input data.
	 *
	 * @param mixed  $data Data to validate.
	 * @param string $type Data type.
	 * @param array  $options Validation options.
	 * @return mixed Sanitized data.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	public static function validate( $data, $type = 'text', $options = array() ) {
		switch ( $type ) {
			case 'text':
				return self::validate_text( $data, $options );

			case 'textarea':
				return self::validate_textarea( $data, $options );

			case 'email':
				return self::validate_email( $data, $options );

			case 'url':
				return self::validate_url( $data, $options );

			case 'int':
				return self::validate_int( $data, $options );

			case 'float':
				return self::validate_float( $data, $options );

			case 'boolean':
				return self::validate_boolean( $data );

			case 'array':
				return self::validate_array( $data, $options );

			case 'json':
				return self::validate_json( $data, $options );

			case 'slug':
				return self::validate_slug( $data, $options );

			case 'filename':
				return self::validate_filename( $data, $options );

			default:
				throw new \InvalidArgumentException( esc_html( "Unknown validation type: {$type}" ) );
		}
	}

	/**
	 * Validate text input.
	 *
	 * @param mixed $data Input data.
	 * @param array $options Validation options.
	 * @return string Sanitized text.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private static function validate_text( $data, $options = array() ) {
		$sanitized = sanitize_text_field( $data );

		if ( isset( $options['min_length'] ) && strlen( $sanitized ) < $options['min_length'] ) {
			throw new \InvalidArgumentException( esc_html( "Text must be at least {$options['min_length']} characters long" ) );
		}

		if ( isset( $options['max_length'] ) && strlen( $sanitized ) > $options['max_length'] ) {
			throw new \InvalidArgumentException( esc_html( "Text must be no more than {$options['max_length']} characters long" ) );
		}

		if ( isset( $options['pattern'] ) && ! preg_match( $options['pattern'], $sanitized ) ) {
			throw new \InvalidArgumentException( esc_html( 'Text does not match required pattern' ) );
		}

		return $sanitized;
	}

	/**
	 * Validate textarea input.
	 *
	 * @param mixed $data Input data.
	 * @param array $options Validation options.
	 * @return string Sanitized textarea.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private static function validate_textarea( $data, $options = array() ) {
		$sanitized = sanitize_textarea_field( $data );

		if ( isset( $options['max_length'] ) && strlen( $sanitized ) > $options['max_length'] ) {
			throw new \InvalidArgumentException( esc_html( "Text must be no more than {$options['max_length']} characters long" ) );
		}

		return $sanitized;
	}

	/**
	 * Validate email input.
	 *
	 * @param mixed $data Input data.
	 * @param array $options Validation options.
	 * @return string Sanitized email.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private static function validate_email( $data, $options = array() ) {
		$sanitized = sanitize_email( $data );

		if ( ! is_email( $sanitized ) ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid email address' ) );
		}

		return $sanitized;
	}

	/**
	 * Validate URL input.
	 *
	 * @param mixed $data Input data.
	 * @param array $options Validation options.
	 * @return string Sanitized URL.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private static function validate_url( $data, $options = array() ) {
		$sanitized = esc_url_raw( $data );

		if ( ! filter_var( $sanitized, FILTER_VALIDATE_URL ) ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid URL' ) );
		}

		if ( isset( $options['allowed_protocols'] ) ) {
			$parsed = wp_parse_url( $sanitized );
			$scheme = $parsed['scheme'] ?? '';

			if ( ! in_array( $scheme, $options['allowed_protocols'], true ) ) {
				throw new \InvalidArgumentException( esc_html( 'URL protocol not allowed' ) );
			}
		}

		return $sanitized;
	}

	/**
	 * Validate integer input.
	 *
	 * @param mixed $data Input data.
	 * @param array $options Validation options.
	 * @return int Sanitized integer.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private static function validate_int( $data, $options = array() ) {
		$sanitized = intval( $data );

		if ( isset( $options['min'] ) && $sanitized < $options['min'] ) {
			throw new \InvalidArgumentException( esc_html( "Value must be at least {$options['min']}" ) );
		}

		if ( isset( $options['max'] ) && $sanitized > $options['max'] ) {
			throw new \InvalidArgumentException( esc_html( "Value must be no more than {$options['max']}" ) );
		}

		return $sanitized;
	}

	/**
	 * Validate float input.
	 *
	 * @param mixed $data Input data.
	 * @param array $options Validation options.
	 * @return float Sanitized float.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private static function validate_float( $data, $options = array() ) {
		$sanitized = floatval( $data );

		if ( isset( $options['min'] ) && $sanitized < $options['min'] ) {
			throw new \InvalidArgumentException( esc_html( "Value must be at least {$options['min']}" ) );
		}

		if ( isset( $options['max'] ) && $sanitized > $options['max'] ) {
			throw new \InvalidArgumentException( esc_html( "Value must be no more than {$options['max']}" ) );
		}

		return $sanitized;
	}

	/**
	 * Validate boolean input.
	 *
	 * @param mixed $data Input data.
	 * @return bool Sanitized boolean.
	 */
	private static function validate_boolean( $data ) {
		return rest_sanitize_boolean( $data );
	}

	/**
	 * Validate array input.
	 *
	 * @param mixed $data Input data.
	 * @param array $options Validation options.
	 * @return array Sanitized array.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private static function validate_array( $data, $options = array() ) {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( esc_html( 'Expected array' ) );
		}

		$item_type    = $options['item_type'] ?? 'text';
		$item_options = $options['item_options'] ?? array();

		$sanitized = array();
		foreach ( $data as $key => $value ) {
			$sanitized_key               = sanitize_text_field( $key );
			$sanitized_value             = self::validate( $value, $item_type, $item_options );
			$sanitized[ $sanitized_key ] = $sanitized_value;
		}

		if ( isset( $options['max_items'] ) && count( $sanitized ) > $options['max_items'] ) {
			throw new \InvalidArgumentException( esc_html( "Array must contain no more than {$options['max_items']} items" ) );
		}

		return $sanitized;
	}

	/**
	 * Validate JSON input.
	 *
	 * @param mixed $data Input data.
	 * @param array $options Validation options.
	 * @return array Parsed JSON data.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private static function validate_json( $data, $options = array() ) {
		if ( is_array( $data ) ) {
			return $data; // Already parsed.
		}

		$decoded = json_decode( $data, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid JSON: ' . json_last_error_msg() ) );
		}

		return $decoded;
	}

	/**
	 * Validate slug input.
	 *
	 * @param mixed $data Input data.
	 * @param array $options Validation options.
	 * @return string Sanitized slug.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private static function validate_slug( $data, $options = array() ) {
		$sanitized = sanitize_title( $data );

		if ( empty( $sanitized ) ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid slug' ) );
		}

		return $sanitized;
	}

	/**
	 * Validate filename input.
	 *
	 * @param mixed $data Input data.
	 * @param array $options Validation options.
	 * @return string Sanitized filename.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private static function validate_filename( $data, $options = array() ) {
		$sanitized = sanitize_file_name( $data );

		if ( empty( $sanitized ) ) {
			throw new \InvalidArgumentException( esc_html( 'Invalid filename' ) );
		}

		if ( isset( $options['allowed_extensions'] ) ) {
			$extension = pathinfo( $sanitized, PATHINFO_EXTENSION );

			if ( ! in_array( $extension, $options['allowed_extensions'], true ) ) {
				throw new \InvalidArgumentException( esc_html( 'File extension not allowed' ) );
			}
		}

		return $sanitized;
	}

	/**
	 * Check if current user has required capability.
	 *
	 * @param string $capability Required capability.
	 * @param mixed  $object_id Optional object ID for meta capabilities.
	 * @return bool Whether user has capability.
	 */
	public static function user_can( $capability, $object_id = null ) {
		if ( $object_id ) {
			return current_user_can( $capability, $object_id );
		}

		return current_user_can( $capability );
	}

	/**
	 * Check if request is from valid referer.
	 *
	 * @param string $action Action name.
	 * @return bool Whether referer is valid.
	 */
	public static function check_admin_referer( $action ) {
		return check_admin_referer( $action );
	}

	/**
	 * Check if request is from AJAX.
	 *
	 * @return bool Whether request is AJAX.
	 */
	public static function is_ajax_request() {
		return wp_doing_ajax();
	}

	/**
	 * Get sanitized POST data.
	 *
	 * @param string $key POST key.
	 * @param mixed  $default_value Default value.
	 * @return mixed Sanitized POST data.
	 */
	public static function get_post_data( $key, $default_value = null ) {
		// This is a utility method for getting POST data - nonce verification should be done by the caller.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default_value;
	}

	/**
	 * Get sanitized GET data.
	 *
	 * @param string $key GET key.
	 * @param mixed  $default_value Default value.
	 * @return mixed Sanitized GET data.
	 */
	public static function get_get_data( $key, $default_value = null ) {
		// This is a utility method for getting GET data - nonce verification should be done by the caller.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default_value;
	}
}
