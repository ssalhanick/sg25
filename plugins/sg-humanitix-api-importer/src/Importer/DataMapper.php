<?php
/**
 * Data Mapper Class.
 *
 * Handles mapping Humanitix API data to The Events Calendar format.
 *
 * @package SG\HumanitixApiImporter
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Data Mapper Class.
 *
 * Maps Humanitix API event data to The Events Calendar format.
 *
 * @package SG\HumanitixApiImporter
 * @since 1.0.0
 */
class DataMapper {

	/**
	 * Default field mappings from Humanitix to The Events Calendar.
	 *
	 * @var array
	 */
	private $field_mappings = array(
		// Basic event fields from Humanitix API schema.
		'_id'             => 'humanitix_id',
		'name'            => 'post_title',
		'description'     => 'post_content',
		'startDate'       => '_EventStartDate',
		'endDate'         => '_EventEndDate',
		'timezone'        => '_EventTimezone',
		'url'             => '_EventURL',
		'slug'            => 'humanitix_slug',
		'category'        => '_EventCategory',
		'currency'        => '_EventCurrency',
		'totalCapacity'   => '_EventCapacity',
		'public'          => 'humanitix_public',
		'published'       => 'humanitix_published',
		'suspendSales'    => 'humanitix_suspend_sales',
		'markedAsSoldOut' => 'humanitix_sold_out',
		'location'        => 'humanitix_location',
		'keywords'        => 'humanitix_keywords',
		'createdAt'       => 'humanitix_created_at',
		'updatedAt'       => 'humanitix_updated_at',
	);

	/**
	 * Custom field mappings for specific Humanitix fields.
	 *
	 * @var array
	 */
	private $custom_mappings = array(
		'humanitix_id'            => '_humanitix_event_id',
		'humanitix_url'           => 'humanitix_event_url',
		'humanitix_organizer'     => 'humanitix_organizer_name',
		'humanitix_venue'         => 'humanitix_venue_name',
		'humanitix_ticket_types'  => 'humanitix_ticket_types',
		'humanitix_categories'    => 'humanitix_categories',
		'humanitix_tags'          => 'humanitix_tags',
		'humanitix_images'        => 'humanitix_images',
		'humanitix_location'      => 'humanitix_location_data',
		'humanitix_dates'         => 'humanitix_dates',
		'humanitix_pricing'       => 'humanitix_pricing',
		'humanitix_accessibility' => 'humanitix_accessibility',
	);

	/**
	 * Constructor.
	 *
	 * @param array $custom_mappings Optional custom field mappings.
	 */
	public function __construct( $custom_mappings = array() ) {
		if ( ! empty( $custom_mappings ) ) {
			$this->field_mappings = array_merge( $this->field_mappings, $custom_mappings );
		}
	}

	/**
	 * Map Humanitix event data to The Events Calendar format.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @return array Mapped event data for The Events Calendar.
	 */
	public function map_event( $humanitix_event ) {
		// Initialize debug helper for detailed logging
		$logger = new \SG\HumanitixApiImporter\Admin\Logger();
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $logger );
		
		// Start memory monitoring
		$start_memory = memory_get_usage( true );
		
		// Get performance configuration
		$performance_config = new \SG\HumanitixApiImporter\Admin\PerformanceConfig();
		$optimization_settings = $performance_config::get_data_optimization_settings();
		
		// Log raw Humanitix data when HUMANITIX_DEBUG is enabled
		$debug_helper->log_raw_api_data( 'event_mapping', $humanitix_event, 'request' );
		
		// Start performance timing
		$start_time = microtime( true );

		if ( ! is_array( $humanitix_event ) ) {
			$debug_helper->log_detailed_error( 'DataMapper', 'Invalid event data - not an array', null, array( 'data_type' => gettype( $humanitix_event ) ) );
			return array();
		}

		// Validate required fields when HUMANITIX_DEBUG is enabled
		if ( $debug_helper->is_humanitix_debug_enabled() ) {
			$this->validate_required_fields( $humanitix_event, $debug_helper );
		}

		$mapped_event = array(
			'post_type'   => 'tribe_events',
			'post_status' => 'publish',
			'meta_input'  => array(),
		);

		// Set publication date from Humanitix createdAt field if available.
		if ( isset( $humanitix_event['createdAt'] ) && ! empty( $humanitix_event['createdAt'] ) ) {
			$created_timestamp = strtotime( $humanitix_event['createdAt'] );
			if ( false !== $created_timestamp ) {
				$mapped_event['post_date']     = date( 'Y-m-d H:i:s', $created_timestamp );
				$mapped_event['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $created_timestamp );
			}
		}

		// Map basic fields with memory optimization
		$mapped_event = $this->map_basic_fields_optimized( $humanitix_event, $mapped_event, $optimization_settings );

		// Map custom Humanitix fields with memory optimization
		$mapped_event = $this->map_custom_fields_optimized( $humanitix_event, $mapped_event, $optimization_settings );

		// Handle nested objects and arrays with memory optimization
		$mapped_event = $this->map_nested_data_optimized( $humanitix_event, $mapped_event, $optimization_settings );

		// Set timezone information after we have the event dates.
		$mapped_event = $this->set_timezone_info( $humanitix_event, $mapped_event );

		// Set default values for required TEC fields.
		$mapped_event = $this->set_default_values( $mapped_event );

		// Calculate memory usage
		$end_memory = memory_get_usage( true );
		$memory_used = $end_memory - $start_memory;

		// Log performance timing and final result when HUMANITIX_DEBUG is enabled
		$debug_helper->log_performance_timing( 'event_mapping', $start_time, array(
			'event_name' => $humanitix_event['name'] ?? 'Unknown',
			'event_id' => $humanitix_event['_id'] ?? 'unknown',
			'mapped_fields_count' => count( $mapped_event['meta_input'] ),
			'memory_used_kb' => round( $memory_used / 1024, 2 ),
			'optimization_enabled' => $optimization_settings['enabled'],
			'max_string_length' => $optimization_settings['max_string_length'],
		) );

		// Log final mapped data when HUMANITIX_DEBUG is enabled
		$debug_helper->log_raw_api_data( 'event_mapping', $mapped_event, 'response' );

		return $mapped_event;
	}

	/**
	 * Validate required fields when HUMANITIX_DEBUG is enabled.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param \SG\HumanitixApiImporter\Admin\DebugHelper $debug_helper Debug helper instance.
	 */
	private function validate_required_fields( $humanitix_event, $debug_helper ) {
		$required_fields = array(
			'name' => 'Event name',
			'_id' => 'Event ID',
		);

		$recommended_fields = array(
			'startDate' => 'Start date',
			'endDate' => 'End date',
			'description' => 'Event description',
		);

		$optional_fields = array(
			'venue' => 'Venue information',
			'image' => 'Featured image',
			'category' => 'Event category',
		);

		// Check required fields
		foreach ( $required_fields as $field => $description ) {
			if ( ! isset( $humanitix_event[ $field ] ) || empty( $humanitix_event[ $field ] ) ) {
				$debug_helper->log_missing_field( 
					$humanitix_event['name'] ?? 'Unknown', 
					$field, 
					'required', 
					$humanitix_event 
				);
			}
		}

		// Check recommended fields
		foreach ( $recommended_fields as $field => $description ) {
			if ( ! isset( $humanitix_event[ $field ] ) || empty( $humanitix_event[ $field ] ) ) {
				$debug_helper->log_missing_field( 
					$humanitix_event['name'] ?? 'Unknown', 
					$field, 
					'recommended', 
					$humanitix_event 
				);
			}
		}

		// Check optional fields
		foreach ( $optional_fields as $field => $description ) {
			if ( ! isset( $humanitix_event[ $field ] ) || empty( $humanitix_event[ $field ] ) ) {
				$debug_helper->log_missing_field( 
					$humanitix_event['name'] ?? 'Unknown', 
					$field, 
					'optional', 
					$humanitix_event 
				);
			}
		}

		// Log validation summary
		$validation_results = array();
		foreach ( array_merge( $required_fields, $recommended_fields, $optional_fields ) as $field => $description ) {
			$validation_results[] = array(
				'field' => $field,
				'description' => $description,
				'valid' => isset( $humanitix_event[ $field ] ) && ! empty( $humanitix_event[ $field ] ),
				'value' => $humanitix_event[ $field ] ?? null,
			);
		}

		$debug_helper->log_data_validation( $humanitix_event['name'] ?? 'Unknown', $validation_results );
	}

	/**
	 * Map basic fields with memory optimization.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @param array $optimization_settings Optimization settings.
	 * @return array Updated mapped event data.
	 */
	private function map_basic_fields_optimized( $humanitix_event, $mapped_event, $optimization_settings ) {
		// Use a more memory-efficient approach by processing only essential fields
		$essential_fields = array(
			'name' => 'post_title',
			'description' => 'post_content',
			'startDate' => '_EventStartDate',
			'endDate' => '_EventEndDate',
			'timezone' => '_EventTimezone',
			'url' => '_EventURL',
		);

		$max_string_length = $optimization_settings['max_string_length'];

		foreach ( $essential_fields as $humanitix_field => $tec_field ) {
			if ( isset( $humanitix_event[ $humanitix_field ] ) ) {
				$value = $humanitix_event[ $humanitix_field ];

				// Handle special field mappings with memory optimization
				switch ( $tec_field ) {
					case 'post_title':
						$mapped_event['post_title'] = $this->truncate_string( sanitize_text_field( $value ), min( 200, $max_string_length ) );
						break;
					case 'post_content':
						$mapped_event['post_content'] = $this->truncate_string( wp_kses_post( $value ), min( 5000, $max_string_length * 20 ) );
						break;
					case '_EventStartDate':
						// Store the raw UTC date string for later timezone conversion.
						$mapped_event['meta_input']['_humanitix_start_date_utc'] = $value;
						break;
					case '_EventEndDate':
						// Store the raw UTC date string for later timezone conversion.
						$mapped_event['meta_input']['_humanitix_end_date_utc'] = $value;
						break;
					case '_EventTimezone':
						$mapped_event['meta_input'][ $tec_field ] = $this->convert_timezone_for_tec( $value );
						break;
					default:
						$mapped_event['meta_input'][ $tec_field ] = $this->truncate_string( sanitize_text_field( $value ), $max_string_length );
						break;
				}
			}
		}

		return $mapped_event;
	}

	/**
	 * Map custom fields with memory optimization.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @param array $optimization_settings Optimization settings.
	 * @return array Updated mapped event data.
	 */
	private function map_custom_fields_optimized( $humanitix_event, $mapped_event, $optimization_settings ) {
		// Map the Humanitix ID from _id field to the meta field
		if ( isset( $humanitix_event['_id'] ) ) {
			$mapped_event['meta_input']['_humanitix_event_id'] = $this->sanitize_custom_field_optimized( $humanitix_event['_id'], $optimization_settings );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "DataMapper: Mapped _id '{$humanitix_event['_id']}' to _humanitix_event_id meta field" );
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "DataMapper: No _id field found in humanitix_event data" );
			}
		}

		// Map URL if available
		if ( isset( $humanitix_event['url'] ) ) {
			$mapped_event['meta_input']['humanitix_event_url'] = $this->sanitize_custom_field_optimized( $humanitix_event['url'], $optimization_settings );
		}

		return $mapped_event;
	}

	/**
	 * Map nested data with memory optimization.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @param array $optimization_settings Optimization settings.
	 * @return array Updated mapped event data.
	 */
	private function map_nested_data_optimized( $humanitix_event, $mapped_event, $optimization_settings ) {
		// Initialize debug helper
		$logger       = new \SG\HumanitixApiImporter\Admin\Logger();
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $logger );

		// Handle venue data with memory optimization
		$mapped_event = $this->process_venue_data_optimized( $humanitix_event, $mapped_event, $optimization_settings );

		// Handle ticket types with memory optimization
		$mapped_event = $this->process_ticket_types_optimized( $humanitix_event, $mapped_event, $optimization_settings );

		// Handle pricing with memory optimization
		$mapped_event = $this->process_pricing_optimized( $humanitix_event, $mapped_event, $optimization_settings );

		// Handle images with memory optimization
		$mapped_event = $this->process_images_optimized( $humanitix_event, $mapped_event, $optimization_settings );

		// Validate required fields
		$this->validate_mapped_event( $mapped_event, $humanitix_event, $debug_helper );

		// Process additional data with memory limits
		$mapped_event = $this->process_additional_data_optimized( $humanitix_event, $mapped_event, $optimization_settings );

		return $mapped_event;
	}

	/**
	 * Process venue data with memory optimization.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @param array $optimization_settings Optimization settings.
	 * @return array Updated mapped event data.
	 */
	private function process_venue_data_optimized( $humanitix_event, $mapped_event, $optimization_settings ) {
		// Check multiple possible field names for venue data
		$venue = null;
		if ( isset( $humanitix_event['venue'] ) && is_array( $humanitix_event['venue'] ) ) {
			$venue = $humanitix_event['venue'];
		} elseif ( isset( $humanitix_event['eventLocation'] ) && is_array( $humanitix_event['eventLocation'] ) ) {
			$venue = $humanitix_event['eventLocation'];
		} elseif ( isset( $humanitix_event['location'] ) && is_array( $humanitix_event['location'] ) ) {
			$venue = $humanitix_event['location'];
		}

		if ( $venue ) {
			// Store only essential venue data to reduce memory usage
			$essential_venue_data = array();
			$max_string_length = $optimization_settings['max_string_length'];
			
			if ( isset( $venue['instructions'] ) ) {
				$essential_venue_data['instructions'] = $this->truncate_string( $venue['instructions'], min( 500, $max_string_length * 2 ) );
			}
			if ( isset( $venue['online_url'] ) ) {
				$essential_venue_data['online_url'] = esc_url_raw( $venue['online_url'] );
			}
			if ( isset( $venue['latLng'] ) && is_array( $venue['latLng'] ) ) {
				$essential_venue_data['latLng'] = $venue['latLng'];
			} elseif ( isset( $venue['lat_lng'] ) && is_array( $venue['lat_lng'] ) ) {
				$essential_venue_data['lat_lng'] = $venue['lat_lng'];
			}

			// Store essential venue data only
			if ( ! empty( $essential_venue_data ) ) {
				$mapped_event['meta_input']['humanitix_venue_data'] = wp_json_encode( $essential_venue_data );
			}

			// Store individual fields for easier access
			if ( isset( $venue['instructions'] ) ) {
				$mapped_event['meta_input']['humanitix_location_instructions'] = $this->truncate_string( sanitize_textarea_field( $venue['instructions'] ), min( 500, $max_string_length * 2 ) );
			}
			if ( isset( $venue['online_url'] ) ) {
				$mapped_event['meta_input']['humanitix_online_url'] = esc_url_raw( $venue['online_url'] );
			}
			if ( isset( $venue['latLng'] ) && is_array( $venue['latLng'] ) ) {
				$mapped_event['meta_input']['humanitix_lat_lng'] = wp_json_encode( $venue['latLng'] );
			} elseif ( isset( $venue['lat_lng'] ) && is_array( $venue['lat_lng'] ) ) {
				$mapped_event['meta_input']['humanitix_lat_lng'] = wp_json_encode( $venue['lat_lng'] );
			}
		}

		return $mapped_event;
	}

	/**
	 * Process ticket types with memory optimization.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @param array $optimization_settings Optimization settings.
	 * @return array Updated mapped event data.
	 */
	private function process_ticket_types_optimized( $humanitix_event, $mapped_event, $optimization_settings ) {
		if ( ! isset( $humanitix_event['ticketTypes'] ) || ! is_array( $humanitix_event['ticketTypes'] ) ) {
			return $mapped_event;
		}

		// Limit ticket types processing to prevent memory issues
		$max_ticket_types = $optimization_settings['max_ticket_types'];
		$ticket_types = array_slice( $humanitix_event['ticketTypes'], 0, $max_ticket_types );

		// Store essential ticket data only
		$essential_ticket_data = array();
		$total_capacity = 0;
		$available_tickets = 0;
		$min_price = null;
		$max_price = null;
		$max_string_length = $optimization_settings['max_string_length'];

		foreach ( $ticket_types as $ticket ) {
			if ( ! isset( $ticket['disabled'] ) || ! $ticket['disabled'] ) {
				$quantity = isset( $ticket['quantity'] ) ? intval( $ticket['quantity'] ) : 0;
				$total_capacity += $quantity;
				$available_tickets += $quantity;

				// Track pricing
				if ( isset( $ticket['price'] ) ) {
					$price = floatval( $ticket['price'] );
					if ( null === $min_price || $price < $min_price ) {
						$min_price = $price;
					}
					if ( null === $max_price || $price > $max_price ) {
						$max_price = $price;
					}
				}

				// Store only essential ticket data
				$essential_ticket_data[] = array(
					'name' => $this->truncate_string( $ticket['name'] ?? '', min( 100, $max_string_length ) ),
					'price' => $ticket['price'] ?? 0,
					'quantity' => $quantity,
				);
			}
		}

		// Store essential ticket data
		if ( ! empty( $essential_ticket_data ) ) {
			$mapped_event['meta_input']['humanitix_ticket_types'] = wp_json_encode( $essential_ticket_data );
		}

		$mapped_event['meta_input']['_EventCapacity'] = $total_capacity;
		$mapped_event['meta_input']['humanitix_available_tickets'] = $available_tickets;

		// Set pricing information
		if ( null !== $min_price && ! isset( $mapped_event['meta_input']['_EventCost'] ) ) {
			$mapped_event['meta_input']['_EventCost'] = $min_price;
			if ( $max_price !== $min_price ) {
				$mapped_event['meta_input']['_EventCost'] .= ' - ' . $max_price;
			}
		}

		return $mapped_event;
	}

	/**
	 * Process pricing with memory optimization.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @param array $optimization_settings Optimization settings.
	 * @return array Updated mapped event data.
	 */
	private function process_pricing_optimized( $humanitix_event, $mapped_event, $optimization_settings ) {
		if ( ! isset( $humanitix_event['pricing'] ) || ! is_array( $humanitix_event['pricing'] ) ) {
			return $mapped_event;
		}

		// Store only essential pricing data
		$essential_pricing_data = array();
		
		if ( isset( $humanitix_event['pricing']['maximumPrice'] ) ) {
			$essential_pricing_data['maximumPrice'] = floatval( $humanitix_event['pricing']['maximumPrice'] );
			$mapped_event['meta_input']['_EventCost'] = $essential_pricing_data['maximumPrice'];
		}

		if ( ! empty( $essential_pricing_data ) ) {
			$mapped_event['meta_input']['humanitix_pricing'] = wp_json_encode( $essential_pricing_data );
		}

		return $mapped_event;
	}

	/**
	 * Process images with memory optimization.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @param array $optimization_settings Optimization settings.
	 * @return array Updated mapped event data.
	 */
	private function process_images_optimized( $humanitix_event, $mapped_event, $optimization_settings ) {
		$images = array();
		
		// Only process essential image types
		$image_fields = array( 'bannerImage', 'featureImage' );
		
		foreach ( $image_fields as $field ) {
			if ( isset( $humanitix_event[ $field ]['url'] ) ) {
				$images[ $field ] = $humanitix_event[ $field ]['url'];
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "DataMapper: Found images: " . wp_json_encode( $images ) );
		}

		if ( ! empty( $images ) ) {
			$mapped_event['meta_input']['humanitix_images'] = wp_json_encode( $images );

			// Set featured image if available - prioritize featureImage over bannerImage
			if ( isset( $images['featureImage'] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "DataMapper: Processing featureImage: " . $images['featureImage'] );
				}
				$thumbnail_id = $this->process_event_image( $images['featureImage'] );
				if ( $thumbnail_id ) {
					$mapped_event['meta_input']['_thumbnail_id'] = $thumbnail_id;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "DataMapper: Set featured image ID: " . $thumbnail_id );
					}
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "DataMapper: Failed to process featureImage" );
					}
				}
			} elseif ( isset( $images['bannerImage'] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "DataMapper: Processing bannerImage: " . $images['bannerImage'] );
				}
				$thumbnail_id = $this->process_event_image( $images['bannerImage'] );
				if ( $thumbnail_id ) {
					$mapped_event['meta_input']['_thumbnail_id'] = $thumbnail_id;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "DataMapper: Set featured image ID: " . $thumbnail_id );
					}
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "DataMapper: Failed to process bannerImage" );
					}
				}
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "DataMapper: No images found in event data" );
			}
		}

		return $mapped_event;
	}

	/**
	 * Process additional data with memory optimization.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @param array $optimization_settings Optimization settings.
	 * @return array Updated mapped event data.
	 */
	private function process_additional_data_optimized( $humanitix_event, $mapped_event, $optimization_settings ) {
		// Process only essential additional data to reduce memory usage
		$additional_fields = array(
			'dates' => 'humanitix_dates',
			'accessibility' => 'humanitix_accessibility',
		);

		$max_array_size = $optimization_settings['max_array_size'];

		foreach ( $additional_fields as $field => $meta_key ) {
			if ( isset( $humanitix_event[ $field ] ) && is_array( $humanitix_event[ $field ] ) ) {
				// Limit array size to prevent memory issues
				$limited_data = array_slice( $humanitix_event[ $field ], 0, $max_array_size );
				$mapped_event['meta_input'][ $meta_key ] = wp_json_encode( $limited_data );
			}
		}

		return $mapped_event;
	}

	/**
	 * Validate mapped event data.
	 *
	 * @param array $mapped_event Mapped event data.
	 * @param array $humanitix_event Original Humanitix event data.
	 * @param \SG\HumanitixApiImporter\Admin\DebugHelper $debug_helper Debug helper instance.
	 */
	private function validate_mapped_event( $mapped_event, $humanitix_event, $debug_helper ) {
		// Validate required fields
		if ( empty( $mapped_event['post_title'] ) ) {
			$debug_helper->log_critical_error(
				'DataMapper',
				'Event mapping failed: Missing required post_title',
				array(
					'humanitix_id'     => $humanitix_event['_id'] ?? 'unknown',
					'available_fields' => array_keys( $humanitix_event ),
				)
			);
		}

		if ( empty( $mapped_event['meta_input']['_EventStartDate'] ) ) {
			$debug_helper->log_critical_error(
				'DataMapper',
				'Event mapping failed: Missing required start date',
				array(
					'humanitix_id'   => $humanitix_event['_id'] ?? 'unknown',
					'start_date_raw' => $humanitix_event['startDate'] ?? 'not set',
				)
			);
		}
	}

	/**
	 * Truncate string to specified length to prevent memory issues.
	 *
	 * @param string $string The string to truncate.
	 * @param int $max_length Maximum length.
	 * @return string Truncated string.
	 */
	private function truncate_string( $string, $max_length ) {
		if ( strlen( $string ) <= $max_length ) {
			return $string;
		}
		return substr( $string, 0, $max_length ) . '...';
	}

	/**
	 * Sanitize custom field values with memory optimization.
	 *
	 * @param mixed $value Field value.
	 * @param array $optimization_settings Optimization settings.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_custom_field_optimized( $value, $optimization_settings ) {
		if ( is_array( $value ) ) {
			// Limit array size to prevent memory issues
			$max_array_size = $optimization_settings['max_array_size'];
			$limited_value = array_slice( $value, 0, $max_array_size );
			return wp_json_encode( $limited_value );
		} elseif ( is_string( $value ) ) {
			$max_string_length = $optimization_settings['max_string_length'];
			return $this->truncate_string( sanitize_text_field( $value ), $max_string_length );
		} else {
			return $value;
		}
	}

	/**
	 * Set timezone information for the event.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @return array Updated mapped event data.
	 */
	private function set_timezone_info( $humanitix_event, $mapped_event ) {
		// Initialize debug helper.
		$logger       = new \SG\HumanitixApiImporter\Admin\Logger();
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $logger );

		// Get the timezone from Humanitix data.
		$timezone_string = '';
		if ( isset( $humanitix_event['timezone'] ) ) {
			$timezone_string = $this->convert_timezone_for_tec( $humanitix_event['timezone'] );
			$debug_helper->log(
				'DataMapper',
				'Set timezone from Humanitix data',
				array(
					'timezone' => $timezone_string,
				)
			);
		}

		// If no timezone from Humanitix, try to determine from event venue.
		$venue_data = $humanitix_event['venue'] ?? $humanitix_event['eventLocation'] ?? $humanitix_event['location'] ?? array();
		if ( empty( $timezone_string ) && isset( $venue_data['country'] ) ) {
			$timezone_string = $this->get_timezone_from_location( $venue_data );
			$debug_helper->log(
				'DataMapper',
				'Set timezone from venue',
				array(
					'timezone'      => $timezone_string,
					'venue_country' => $venue_data['country'] ?? 'unknown',
				)
			);
		}

		// Fallback to WordPress site timezone.
		if ( empty( $timezone_string ) ) {
			$timezone_string = get_option( 'timezone_string' );
			if ( empty( $timezone_string ) ) {
				$timezone_string = 'America/New_York'; // Final fallback.
			}
			$debug_helper->log(
				'DataMapper',
				'Set timezone from WordPress fallback',
				array(
					'timezone' => $timezone_string,
				)
			);
		}

		// Set the timezone field.
		$mapped_event['meta_input']['_EventTimezone'] = $timezone_string;
		$debug_helper->log(
			'DataMapper',
			'Final timezone set',
			array(
				'timezone' => $timezone_string,
			)
		);

		// Convert UTC dates to the event's timezone.
		if ( isset( $mapped_event['meta_input']['_humanitix_start_date_utc'] ) && ! empty( $mapped_event['meta_input']['_humanitix_start_date_utc'] ) ) {
			$converted_start_date                          = $this->convert_utc_to_timezone( $mapped_event['meta_input']['_humanitix_start_date_utc'], $timezone_string );
			$mapped_event['meta_input']['_EventStartDate'] = $converted_start_date;

			// Set timezone abbreviation.
			$mapped_event['meta_input']['_EventTimezoneAbbr'] = $this->get_timezone_abbr( $converted_start_date, $timezone_string );

			// Store UTC version for reference.
			$mapped_event['meta_input']['_EventStartDateUTC'] = $mapped_event['meta_input']['_humanitix_start_date_utc'];

			// Clean up temporary field.
			unset( $mapped_event['meta_input']['_humanitix_start_date_utc'] );
		}

		if ( isset( $mapped_event['meta_input']['_humanitix_end_date_utc'] ) && ! empty( $mapped_event['meta_input']['_humanitix_end_date_utc'] ) ) {
			$converted_end_date                          = $this->convert_utc_to_timezone( $mapped_event['meta_input']['_humanitix_end_date_utc'], $timezone_string );
			$mapped_event['meta_input']['_EventEndDate'] = $converted_end_date;

			// Store UTC version for reference.
			$mapped_event['meta_input']['_EventEndDateUTC'] = $mapped_event['meta_input']['_humanitix_end_date_utc'];

			// Clean up temporary field.
			unset( $mapped_event['meta_input']['_humanitix_end_date_utc'] );
		}

		return $mapped_event;
	}

	/**
	 * Set default values for required TEC fields.
	 *
	 * @param array $mapped_event Mapped event data.
	 * @return array Updated mapped event data.
	 */
	private function set_default_values( $mapped_event ) {
		// Set default event duration if not specified.
		if ( ! isset( $mapped_event['meta_input']['_EventEndDate'] ) ) {
			$mapped_event['meta_input']['_EventEndDate'] = $mapped_event['meta_input']['_EventStartDate'] ?? '';
		}

		// Set default event status.
		if ( ! isset( $mapped_event['meta_input']['_EventStatus'] ) ) {
			$mapped_event['meta_input']['_EventStatus'] = 'publish';
		}

		// Set default event cost if not specified.
		if ( ! isset( $mapped_event['meta_input']['_EventCost'] ) ) {
			$mapped_event['meta_input']['_EventCost'] = '';
		}

		return $mapped_event;
	}

	/**
	 * Get field mappings.
	 *
	 * @return array Field mappings.
	 */
	public function get_field_mappings() {
		return $this->field_mappings;
	}

	/**
	 * Set custom field mappings.
	 *
	 * @param array $mappings Custom field mappings.
	 */
	public function set_field_mappings( $mappings ) {
		$this->field_mappings = array_merge( $this->field_mappings, $mappings );
	}

	/**
	 * Suggest field mappings based on Humanitix event data.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @return array Array of mapping suggestions.
	 */
	public function suggest_mappings( $humanitix_event ) {
		$suggestions = array();

		// Basic field mappings.
		$basic_fields = array(
			'_id'           => 'Event ID',
			'name'          => 'Event Title',
			'description'   => 'Event Description',
			'startDate'     => 'Start Date',
			'endDate'       => 'End Date',
			'timezone'      => 'Timezone',
			'url'           => 'Event URL',
			'slug'          => 'Event Slug',
			'category'      => 'Event Category',
			'currency'      => 'Currency',
			'totalCapacity' => 'Total Capacity',
			'public'        => 'Public Event',
			'published'     => 'Published',
			'location'      => 'Location',
			'keywords'      => 'Keywords',
		);

		foreach ( $basic_fields as $field => $description ) {
			if ( isset( $humanitix_event[ $field ] ) ) {
				$suggestions[] = array(
					'humanitix_field'     => $field,
					'suggested_tec_field' => $this->suggest_tec_field( $field, $humanitix_event[ $field ] ),
					'description'         => $description,
					'sample_value'        => is_string( $humanitix_event[ $field ] ) ? substr( $humanitix_event[ $field ], 0, 50 ) : wp_json_encode( $humanitix_event[ $field ] ),
				);
			}
		}

		// Nested object mappings.
		if ( isset( $humanitix_event['eventLocation'] ) ) {
			$location_fields = array(
				'venueName'    => 'Venue Name',
				'address'      => 'Address',
				'city'         => 'City',
				'region'       => 'State/Region',
				'country'      => 'Country',
				'instructions' => 'Location Instructions',
				'onlineUrl'    => 'Online URL',
			);

			foreach ( $location_fields as $field => $description ) {
				if ( isset( $humanitix_event['eventLocation'][ $field ] ) ) {
					$suggestions[] = array(
						'humanitix_field'     => "eventLocation.{$field}",
						'suggested_tec_field' => $this->suggest_tec_field( $field, $humanitix_event['eventLocation'][ $field ] ),
						'description'         => "Location: {$description}",
						'sample_value'        => is_string( $humanitix_event['eventLocation'][ $field ] ) ? substr( $humanitix_event['eventLocation'][ $field ], 0, 50 ) : wp_json_encode( $humanitix_event['eventLocation'][ $field ] ),
					);
				}
			}
		}

		// Ticket type mappings.
		if ( isset( $humanitix_event['ticketTypes'] ) && is_array( $humanitix_event['ticketTypes'] ) ) {
			foreach ( $humanitix_event['ticketTypes'] as $index => $ticket ) {
				$ticket_fields = array(
					'name'        => 'Ticket Name',
					'price'       => 'Ticket Price',
					'quantity'    => 'Ticket Quantity',
					'description' => 'Ticket Description',
				);

				foreach ( $ticket_fields as $field => $description ) {
					if ( isset( $ticket[ $field ] ) ) {
						$suggestions[] = array(
							'humanitix_field'     => "ticketTypes[{$index}].{$field}",
							'suggested_tec_field' => $this->suggest_tec_field( $field, $ticket[ $field ] ),
							'description'         => "Ticket {$index}: {$description}",
							'sample_value'        => is_string( $ticket[ $field ] ) ? substr( $ticket[ $field ], 0, 50 ) : wp_json_encode( $ticket[ $field ] ),
						);
					}
				}
			}
		}

		// Image mappings.
		$image_fields = array(
			'bannerImage'  => 'Banner Image',
			'featureImage' => 'Feature Image',
			'socialImage'  => 'Social Image',
		);

		foreach ( $image_fields as $field => $description ) {
			if ( isset( $humanitix_event[ $field ]['url'] ) ) {
				$suggestions[] = array(
					'humanitix_field'     => "{$field}.url",
					'suggested_tec_field' => 'Event Image',
					'description'         => $description,
					'sample_value'        => substr( $humanitix_event[ $field ]['url'], 0, 50 ),
				);
			}
		}

		return $suggestions;
	}

	/**
	 * Suggest TEC field based on Humanitix field name and value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return string Suggested TEC field.
	 */
	private function suggest_tec_field( $field, $value ) {
		$field_lower = strtolower( $field );

		// Common field name patterns.
		$patterns = array(
			'title'       => 'post_title',
			'name'        => 'post_title',
			'description' => 'post_content',
			'content'     => 'post_content',
			'body'        => 'post_content',
			'start'       => 'EventStartDate',
			'end'         => 'EventEndDate',
			'date'        => 'EventStartDate',
			'time'        => 'EventStartTime',
			'venue'       => 'EventVenue',
			'location'    => 'EventVenue',
			'address'     => 'EventAddress',
			'city'        => 'EventCity',
			'state'       => 'EventState',
			'zip'         => 'EventZip',
			'postal'      => 'EventZip',
			'country'     => 'EventCountry',
			'url'         => 'EventURL',
			'link'        => 'EventURL',
			'image'       => 'EventImage',
			'photo'       => 'EventImage',
			'category'    => 'EventCategory',
			'organizer'   => 'EventOrganizer',
			'price'       => 'EventCost',
			'cost'        => 'EventCost',
			'fee'         => 'EventCost',
			'capacity'    => 'EventCapacity',
			'tickets'     => 'EventAvailableTickets',
			'status'      => 'EventStatus',
		);

		foreach ( $patterns as $pattern => $tec_field ) {
			if ( strpos( $field_lower, $pattern ) !== false ) {
				return $tec_field;
			}
		}

		// If no pattern match, suggest as custom field.
		return 'humanitix_' . $field;
	}

	/**
	 * Process and download event image from URL.
	 *
	 * @param string $image_url The URL of the image to download.
	 * @return int|false Attachment ID on success, false on failure.
	 */
	private function process_event_image( $image_url ) {
		if ( empty( $image_url ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "DataMapper: process_event_image called with empty URL" );
			}
			return false;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "DataMapper: Processing image URL: " . $image_url );
		}

		// Use static cache for this request.
		static $image_cache = array();
		$cache_key          = md5( $image_url );

		if ( isset( $image_cache[ $cache_key ] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "DataMapper: Using cached image ID: " . $image_cache[ $cache_key ] );
			}
			return $image_cache[ $cache_key ];
		}

		// Check if image already exists in database.
		$existing_attachment = $this->find_existing_image( $image_url );
		if ( $existing_attachment ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "DataMapper: Found existing image ID: " . $existing_attachment );
			}
			$image_cache[ $cache_key ] = $existing_attachment;
			return $existing_attachment;
		}

		// Check if image download is enabled
		$image_download_enabled = \SG\HumanitixApiImporter\Admin\PerformanceConfig::should_enable_image_download();
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "DataMapper: Image download enabled: " . ( $image_download_enabled ? 'true' : 'false' ) );
		}

		// For performance, skip image download by default during bulk imports.
		// Images can be processed separately or on-demand.
		// Only download images if explicitly enabled.
		if ( ! $image_download_enabled ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "DataMapper: Image download disabled, skipping" );
			}
			$image_cache[ $cache_key ] = false;
			return false;
		}

		// Download and attach image.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "DataMapper: Downloading and attaching image" );
		}
		$attachment_id = $this->download_and_attach_image( $image_url );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "DataMapper: Download result - attachment ID: " . ( $attachment_id ? $attachment_id : 'false' ) );
		}

		$image_cache[ $cache_key ] = $attachment_id;
		return $attachment_id;
	}

	/**
	 * Find existing image by URL.
	 *
	 * @param string $image_url The image URL to search for.
	 * @return int|false Attachment ID if found, false otherwise.
	 */
	private function find_existing_image( $image_url ) {
		global $wpdb;

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta 
                 WHERE meta_key = '_humanitix_image_url' 
                 AND meta_value = %s",
				$image_url
			)
		);

		return $attachment_id ? intval( $attachment_id ) : false;
	}

	/**
	 * Download and attach image to WordPress media library.
	 *
	 * @param string $image_url The URL of the image to download.
	 * @return int|false Attachment ID on success, false on failure.
	 */
	private function download_and_attach_image( $image_url ) {
		// Include WordPress media functions.
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download the image.
		$tmp = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			return false;
		}

		// Get file info and determine proper filename with extension.
		$file_info = $this->get_file_info_from_url( $image_url, $tmp );

		// Get file info.
		$file_array = array(
			'name'     => $file_info['filename'],
			'tmp_name' => $tmp,
			'type'     => $file_info['mime'],
		);

		// Move the temporary file into the uploads directory.
		$id = media_handle_sideload( $file_array, 0 );

		// Clean up the temporary file.
		wp_delete_file( $tmp );

		if ( is_wp_error( $id ) ) {
			return false;
		}

		// Store the original URL for future reference.
		update_post_meta( $id, '_humanitix_image_url', $image_url );

		return $id;
	}

	/**
	 * Get file information from URL and local file.
	 *
	 * @param string $image_url The image URL.
	 * @param string $tmp_file The temporary file path.
	 * @return array File information including MIME type, extension, and filename.
	 */
	private function get_file_info_from_url( $image_url, $tmp_file ) {
		// Get MIME type from the downloaded file.
		$mime_type = $this->get_mime_type( $tmp_file );

		// Map MIME types to extensions.
		$mime_to_ext = array(
			'image/jpeg'    => 'jpg',
			'image/jpg'     => 'jpg',
			'image/png'     => 'png',
			'image/gif'     => 'gif',
			'image/webp'    => 'webp',
			'image/svg+xml' => 'svg',
			'image/bmp'     => 'bmp',
			'image/tiff'    => 'tiff',
		);

		$extension = $mime_to_ext[ $mime_type ] ?? 'jpg'; // Default to jpg if unknown.

		// Try to get filename from URL.
		$url_parts         = parse_url( $image_url );
		$path              = $url_parts['path'] ?? '';
		$original_filename = basename( $path );

		// If the original filename doesn't have an extension, add one.
		if ( ! pathinfo( $original_filename, PATHINFO_EXTENSION ) ) {
			$filename = 'humanitix-image-' . uniqid() . '.' . $extension;
		} else {
			$filename = $original_filename;
		}

		return array(
			'mime'     => $mime_type,
			'ext'      => $extension,
			'filename' => $filename,
		);
	}

	/**
	 * Get MIME type of a file with fallback methods.
	 *
	 * @param string $file_path The file path.
	 * @return string The MIME type.
	 */
	private function get_mime_type( $file_path ) {
		// Method 1: Use fileinfo extension if available.
		if ( function_exists( 'finfo_open' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file_path );
			finfo_close( $finfo );

			if ( $mime_type && 'application/octet-stream' !== $mime_type ) {
				return $mime_type;
			}
		}

		// Method 2: Use mime_content_type if available.
		if ( function_exists( 'mime_content_type' ) ) {
			$mime_type = mime_content_type( $file_path );
			if ( $mime_type && 'application/octet-stream' !== $mime_type ) {
				return $mime_type;
			}
		}

		// Method 3: Check file extension as fallback.
		$extension   = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$ext_to_mime = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'svg'  => 'image/svg+xml',
			'bmp'  => 'image/bmp',
			'tiff' => 'image/tiff',
		);

		if ( isset( $ext_to_mime[ $extension ] ) ) {
			return $ext_to_mime[ $extension ];
		}

		// Method 4: Default to image/jpeg if all else fails.
		return 'image/jpeg';
	}

	/**
	 * Convert timezone for The Events Calendar.
	 *
	 * Converts UTC and offset-based timezones to geographic timezones that TEC prefers.
	 *
	 * @param string $timezone Humanitix timezone string.
	 * @return string Converted timezone string.
	 */
	private function convert_timezone_for_tec( $timezone ) {
		$original_timezone = $timezone;
		$timezone          = trim( $timezone );

		// Debug logging for timezone conversion.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Humanitix DataMapper: Converting timezone '{$original_timezone}' to geographic timezone" );
		}

		// If it's already a geographic timezone, return as is.
		if ( $this->is_geographic_timezone( $timezone ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix DataMapper: Timezone '{$timezone}' is already geographic" );
			}
			return $timezone;
		}

		// Handle UTC timezone variants.
		$utc_variants = array(
			'UTC',
			'UTC+0',
			'UTC+00:00',
			'UTC-0',
			'UTC-00:00',
			'GMT',
			'GMT+0',
			'GMT-0',
			'GMT+00:00',
			'GMT-00:00',
			'Z',
			'+00:00',
			'-00:00',
			'+0',
			'-0',
		);

		if ( in_array( strtoupper( $timezone ), array_map( 'strtoupper', $utc_variants ) ) ) {
			// Use WordPress site timezone as fallback.
			$wp_timezone = get_option( 'timezone_string' );
			if ( ! empty( $wp_timezone ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "Humanitix DataMapper: Converting UTC timezone to WordPress timezone: {$wp_timezone}" );
				}
				return $wp_timezone;
			}

			// If WordPress timezone is not set, use a default.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Humanitix DataMapper: Converting UTC timezone to default: America/New_York' );
			}
			return 'America/New_York';
		}

		// Handle offset-based timezones (e.g., UTC+5, UTC-8, +5, -8).
		$offset_patterns = array(
			'/^UTC([+-]\d{1,2}(?::\d{2})?)$/',  // UTC+5, UTC-8, UTC+5:30.
			'/^GMT([+-]\d{1,2}(?::\d{2})?)$/',  // GMT+5, GMT-8, GMT+5:30.
			'/^([+-]\d{1,2}(?::\d{2})?)$/',     // +5, -8, +5:30.
		);

		foreach ( $offset_patterns as $pattern ) {
			if ( preg_match( $pattern, $timezone, $matches ) ) {
				$offset              = $matches[1];
				$geographic_timezone = $this->get_geographic_timezone_from_offset( $offset );
				if ( $geographic_timezone ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "Humanitix DataMapper: Converting offset '{$offset}' to geographic timezone: {$geographic_timezone}" );
					}
					return $geographic_timezone;
				}
			}
		}

		// If we can't convert it, use WordPress site timezone as fallback.
		$wp_timezone = get_option( 'timezone_string' );
		if ( ! empty( $wp_timezone ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Humanitix DataMapper: Using WordPress timezone as fallback: {$wp_timezone}" );
			}
			return $wp_timezone;
		}

		// Final fallback.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Humanitix DataMapper: Using final fallback timezone: America/New_York' );
		}
		return 'America/New_York';
	}

	/**
	 * Check if a timezone is a geographic timezone.
	 *
	 * @param string $timezone The timezone to check.
	 * @return bool True if it's a geographic timezone.
	 */
	private function is_geographic_timezone( $timezone ) {
		// List of common geographic timezone patterns.
		$geographic_patterns = array(
			'/^[A-Z][a-z]+\/[A-Z][a-z_]+$/', // America/New_York, Europe/London.
			'/^[A-Z][a-z]+\/[A-Z][a-z]+\/[A-Z][a-z]+$/', // America/Indiana/Indianapolis.
			'/^[A-Z][a-z]+\/[A-Z][a-z]+$/', // Asia/Tokyo, Africa/Cairo.
			'/^[A-Z][a-z]+\/[A-Z][a-z]+\/[A-Z][a-z]+\/[A-Z][a-z]+$/', // America/Indiana/Indianapolis.
		);

		foreach ( $geographic_patterns as $pattern ) {
			if ( preg_match( $pattern, $timezone ) ) {
				return true;
			}
		}

		// Check against a comprehensive list of known geographic timezones.
		$known_geographic = array(
			// America.
			'America/New_York',
			'America/Chicago',
			'America/Denver',
			'America/Los_Angeles',
			'America/Toronto',
			'America/Vancouver',
			'America/Edmonton',
			'America/Winnipeg',
			'America/Mexico_City',
			'America/Sao_Paulo',
			'America/Argentina/Buenos_Aires',
			'America/Santiago',
			'America/Lima',
			'America/Caracas',
			'America/Bogota',
			'America/Guayaquil',
			'America/La_Paz',
			'America/Asuncion',
			'America/Montevideo',
			'America/Guyana',
			'America/Paramaribo',
			'America/Cayenne',

			// Europe.
			'Europe/London',
			'Europe/Paris',
			'Europe/Berlin',
			'Europe/Moscow',
			'Europe/Rome',
			'Europe/Madrid',
			'Europe/Amsterdam',
			'Europe/Brussels',
			'Europe/Zurich',
			'Europe/Vienna',
			'Europe/Stockholm',
			'Europe/Oslo',
			'Europe/Copenhagen',
			'Europe/Helsinki',
			'Europe/Warsaw',
			'Europe/Prague',
			'Europe/Budapest',
			'Europe/Bucharest',
			'Europe/Sofia',
			'Europe/Zagreb',
			'Europe/Ljubljana',
			'Europe/Bratislava',
			'Europe/Vilnius',
			'Europe/Riga',
			'Europe/Tallinn',
			'Europe/Dublin',
			'Europe/Lisbon',
			'Europe/Athens',
			'Europe/Istanbul',
			'Europe/Kiev',
			'Europe/Minsk',
			'Europe/Chisinau',

			// Asia.
			'Asia/Tokyo',
			'Asia/Shanghai',
			'Asia/Kolkata',
			'Asia/Dubai',
			'Asia/Seoul',
			'Asia/Singapore',
			'Asia/Kuala_Lumpur',
			'Asia/Bangkok',
			'Asia/Ho_Chi_Minh',
			'Asia/Manila',
			'Asia/Jakarta',
			'Asia/Colombo',
			'Asia/Dhaka',
			'Asia/Kathmandu',
			'Asia/Thimphu',
			'Asia/Yangon',
			'Asia/Vientiane',
			'Asia/Phnom_Penh',
			'Asia/Ulaanbaatar',
			'Asia/Almaty',
			'Asia/Tashkent',
			'Asia/Bishkek',
			'Asia/Dushanbe',
			'Asia/Ashgabat',
			'Asia/Kabul',
			'Asia/Karachi',
			'Asia/Tehran',
			'Asia/Baghdad',
			'Asia/Damascus',
			'Asia/Beirut',
			'Asia/Amman',
			'Asia/Jerusalem',
			'Asia/Gaza',
			'Asia/Riyadh',
			'Asia/Qatar',
			'Asia/Bahrain',
			'Asia/Kuwait',
			'Asia/Muscat',
			'Asia/Aden',
			'Asia/Tbilisi',
			'Asia/Yerevan',
			'Asia/Baku',
			'Asia/Nicosia',

			// Australia & Pacific.
			'Australia/Sydney',
			'Australia/Melbourne',
			'Australia/Perth',
			'Australia/Brisbane',
			'Australia/Adelaide',
			'Australia/Darwin',
			'Pacific/Auckland',
			'Pacific/Honolulu',
			'Pacific/Fiji',
			'Pacific/Guadalcanal',
			'Pacific/Midway',
			'Pacific/Kwajalein',

			// Africa.
			'Africa/Cairo',
			'Africa/Johannesburg',
			'Africa/Lagos',
			'Africa/Nairobi',
			'Africa/Accra',
			'Africa/Casablanca',
			'Africa/Tunis',
			'Africa/Algiers',
			'Africa/Tripoli',
			'Africa/Khartoum',
			'Africa/Addis_Ababa',
			'Africa/Kampala',
			'Africa/Dar_es_Salaam',
			'Africa/Lusaka',
			'Africa/Harare',
			'Africa/Gaborone',
			'Africa/Windhoek',
			'Africa/Johannesburg',

			// Indian Ocean.
			'Indian/Antananarivo',
			'Indian/Mauritius',
			'Indian/Mahe',
			'Indian/Maldives',

			// Atlantic.
			'Atlantic/Azores',
			'Atlantic/South_Georgia',
			'Atlantic/Stanley',

			// Other.
			'Etc/UTC',
			'Etc/Zulu',
			'ZULU',
		);

		return in_array( $timezone, $known_geographic );
	}

	/**
	 * Get geographic timezone from UTC offset.
	 *
	 * @param string $offset The UTC offset (e.g., +5, -8, +5:30).
	 * @return string|false Geographic timezone or false if not found.
	 */
	private function get_geographic_timezone_from_offset( $offset ) {
		// Map common UTC offsets to geographic timezones.
		$offset_map = array(
			// Positive offsets.
			'+0'     => 'Europe/London',
			'+1'     => 'Europe/Paris',
			'+2'     => 'Europe/Helsinki',
			'+3'     => 'Europe/Moscow',
			'+4'     => 'Asia/Dubai',
			'+5'     => 'Asia/Kolkata',
			'+5:30'  => 'Asia/Kolkata',
			'+6'     => 'Asia/Dhaka',
			'+7'     => 'Asia/Bangkok',
			'+8'     => 'Asia/Shanghai',
			'+9'     => 'Asia/Tokyo',
			'+10'    => 'Australia/Sydney',
			'+11'    => 'Pacific/Guadalcanal',
			'+12'    => 'Pacific/Auckland',
			'+13'    => 'Pacific/Auckland', // During daylight saving.
			'+14'    => 'Pacific/Kiritimati',

			// Negative offsets.
			'-1'     => 'Atlantic/Azores',
			'-2'     => 'Atlantic/South_Georgia',
			'-3'     => 'America/Sao_Paulo',
			'-4'     => 'America/New_York',
			'-5'     => 'America/Chicago',
			'-6'     => 'America/Denver',
			'-7'     => 'America/Los_Angeles',
			'-8'     => 'America/Los_Angeles',
			'-9'     => 'America/Anchorage',
			'-10'    => 'Pacific/Honolulu',
			'-11'    => 'Pacific/Midway',
			'-12'    => 'Pacific/Kwajalein',

			// Half-hour offsets.
			'+3:30'  => 'Asia/Tehran',
			'+4:30'  => 'Asia/Kabul',
			'+6:30'  => 'Asia/Yangon',
			'+8:30'  => 'Asia/Pyongyang',
			'+9:30'  => 'Australia/Adelaide',
			'+10:30' => 'Australia/Lord_Howe',
			'+11:30' => 'Pacific/Norfolk',
			'+12:45' => 'Pacific/Chatham',
			'-3:30'  => 'America/St_Johns',
			'-4:30'  => 'America/Caracas',
			'-9:30'  => 'Pacific/Marquesas',
		);

		return isset( $offset_map[ $offset ] ) ? $offset_map[ $offset ] : false;
	}

	/**
	 * Get timezone from event location.
	 *
	 * @param array $location Event location data.
	 * @return string Timezone string or empty string if not found.
	 */
	private function get_timezone_from_location( $location ) {
		// Map common countries to timezones.
		$country_timezone_map = array(
			'US' => 'America/New_York',
			'CA' => 'America/Toronto',
			'GB' => 'Europe/London',
			'AU' => 'Australia/Sydney',
			'DE' => 'Europe/Berlin',
			'FR' => 'Europe/Paris',
			'IT' => 'Europe/Rome',
			'ES' => 'Europe/Madrid',
			'NL' => 'Europe/Amsterdam',
			'BE' => 'Europe/Brussels',
			'CH' => 'Europe/Zurich',
			'AT' => 'Europe/Vienna',
			'SE' => 'Europe/Stockholm',
			'NO' => 'Europe/Oslo',
			'DK' => 'Europe/Copenhagen',
			'FI' => 'Europe/Helsinki',
			'PL' => 'Europe/Warsaw',
			'CZ' => 'Europe/Prague',
			'HU' => 'Europe/Budapest',
			'RO' => 'Europe/Bucharest',
			'BG' => 'Europe/Sofia',
			'HR' => 'Europe/Zagreb',
			'SI' => 'Europe/Ljubljana',
			'SK' => 'Europe/Bratislava',
			'LT' => 'Europe/Vilnius',
			'LV' => 'Europe/Riga',
			'EE' => 'Europe/Tallinn',
			'IE' => 'Europe/Dublin',
			'PT' => 'Europe/Lisbon',
			'GR' => 'Europe/Athens',
			'CY' => 'Asia/Nicosia',
			'MT' => 'Europe/Malta',
			'LU' => 'Europe/Luxembourg',
			'LI' => 'Europe/Vaduz',
			'MC' => 'Europe/Monaco',
			'SM' => 'Europe/San_Marino',
			'VA' => 'Europe/Vatican',
			'AD' => 'Europe/Andorra',
			'JP' => 'Asia/Tokyo',
			'CN' => 'Asia/Shanghai',
			'IN' => 'Asia/Kolkata',
			'KR' => 'Asia/Seoul',
			'SG' => 'Asia/Singapore',
			'MY' => 'Asia/Kuala_Lumpur',
			'TH' => 'Asia/Bangkok',
			'VN' => 'Asia/Ho_Chi_Minh',
			'PH' => 'Asia/Manila',
			'ID' => 'Asia/Jakarta',
			'NZ' => 'Pacific/Auckland',
			'BR' => 'America/Sao_Paulo',
			'MX' => 'America/Mexico_City',
			'AR' => 'America/Argentina/Buenos_Aires',
			'CL' => 'America/Santiago',
			'CO' => 'America/Bogota',
			'PE' => 'America/Lima',
			'VE' => 'America/Caracas',
			'EC' => 'America/Guayaquil',
			'BO' => 'America/La_Paz',
			'PY' => 'America/Asuncion',
			'UY' => 'America/Montevideo',
			'GY' => 'America/Guyana',
			'SR' => 'America/Paramaribo',
			'GF' => 'America/Cayenne',
			'FK' => 'Atlantic/Stanley',
			'ZA' => 'Africa/Johannesburg',
			'EG' => 'Africa/Cairo',
			'NG' => 'Africa/Lagos',
			'KE' => 'Africa/Nairobi',
			'GH' => 'Africa/Accra',
			'MA' => 'Africa/Casablanca',
			'TN' => 'Africa/Tunis',
			'DZ' => 'Africa/Algiers',
			'LY' => 'Africa/Tripoli',
			'SD' => 'Africa/Khartoum',
			'ET' => 'Africa/Addis_Ababa',
			'UG' => 'Africa/Kampala',
			'TZ' => 'Africa/Dar_es_Salaam',
			'ZM' => 'Africa/Lusaka',
			'ZW' => 'Africa/Harare',
			'BW' => 'Africa/Gaborone',
			'NA' => 'Africa/Windhoek',
			'MG' => 'Indian/Antananarivo',
			'MU' => 'Indian/Mauritius',
			'SC' => 'Indian/Mahe',
			'MV' => 'Indian/Maldives',
			'LK' => 'Asia/Colombo',
			'BD' => 'Asia/Dhaka',
			'NP' => 'Asia/Kathmandu',
			'BT' => 'Asia/Thimphu',
			'MM' => 'Asia/Yangon',
			'LA' => 'Asia/Vientiane',
			'KH' => 'Asia/Phnom_Penh',
			'MN' => 'Asia/Ulaanbaatar',
			'KZ' => 'Asia/Almaty',
			'UZ' => 'Asia/Tashkent',
			'KG' => 'Asia/Bishkek',
			'TJ' => 'Asia/Dushanbe',
			'TM' => 'Asia/Ashgabat',
			'AF' => 'Asia/Kabul',
			'PK' => 'Asia/Karachi',
			'IR' => 'Asia/Tehran',
			'IQ' => 'Asia/Baghdad',
			'SY' => 'Asia/Damascus',
			'LB' => 'Asia/Beirut',
			'JO' => 'Asia/Amman',
			'IL' => 'Asia/Jerusalem',
			'PS' => 'Asia/Gaza',
			'SA' => 'Asia/Riyadh',
			'AE' => 'Asia/Dubai',
			'QA' => 'Asia/Qatar',
			'BH' => 'Asia/Bahrain',
			'KW' => 'Asia/Kuwait',
			'OM' => 'Asia/Muscat',
			'YE' => 'Asia/Aden',
			'TR' => 'Europe/Istanbul',
			'RU' => 'Europe/Moscow',
			'UA' => 'Europe/Kiev',
			'BY' => 'Europe/Minsk',
			'MD' => 'Europe/Chisinau',
			'GE' => 'Asia/Tbilisi',
			'AM' => 'Asia/Yerevan',
			'AZ' => 'Asia/Baku',
		);

		if ( isset( $location['country'] ) && isset( $country_timezone_map[ $location['country'] ] ) ) {
			return $country_timezone_map[ $location['country'] ];
		}

		return '';
	}

	/**
	 * Get timezone abbreviation.
	 *
	 * @param string $datetime The datetime string.
	 * @param string $timezone The timezone string.
	 * @return string Timezone abbreviation.
	 */
	private function get_timezone_abbr( $datetime, $timezone ) {
		try {
			$date = new \DateTime( $datetime, new \DateTimeZone( $timezone ) );
			return $date->format( 'T' );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Convert UTC datetime to a specific timezone.
	 *
	 * @param string $utc_datetime The UTC datetime string (ISO 8601 format).
	 * @param string $timezone The target timezone.
	 * @return string Converted datetime string in Y-m-d H:i:s format.
	 */
	private function convert_utc_to_timezone( $utc_datetime, $timezone ) {
		try {
			// Create DateTime object from UTC string.
			$utc_date = new \DateTime( $utc_datetime, new \DateTimeZone( 'UTC' ) );

			// Convert to target timezone.
			$utc_date->setTimezone( new \DateTimeZone( $timezone ) );

			return $utc_date->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			// Fallback to simple conversion if DateTime fails.
			$timestamp = strtotime( $utc_datetime );
			if ( false !== $timestamp ) {
				return date( 'Y-m-d H:i:s', $timestamp );
			}
			return '';
		}
	}

	/**
	 * Convert datetime to UTC.
	 *
	 * @param string $datetime The datetime string in local timezone.
	 * @param string $timezone The timezone of the datetime.
	 * @return string UTC datetime string.
	 */
	private function convert_to_utc( $datetime, $timezone ) {
		try {
			$date = new \DateTime( $datetime, new \DateTimeZone( $timezone ) );
			$date->setTimezone( new \DateTimeZone( 'UTC' ) );
			return $date->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			// Fallback to simple conversion if DateTime fails.
			$timestamp = strtotime( $datetime );
			if ( false !== $timestamp ) {
				return gmdate( 'Y-m-d H:i:s', $timestamp );
			}
			return '';
		}
	}
}
