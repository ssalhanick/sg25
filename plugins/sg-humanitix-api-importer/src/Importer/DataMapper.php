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
<<<<<<< Updated upstream
		'startDate'       => 'EventStartDate',
		'endDate'         => 'EventEndDate',
		'timezone'        => 'EventTimezone',
		'url'             => 'EventURL',
		'slug'            => 'humanitix_slug',
		'category'        => 'EventCategory',
		'currency'        => 'EventCurrency',
		'totalCapacity'   => 'EventCapacity',
=======
		'startDate'       => '_EventStartDate',
		'endDate'         => '_EventEndDate',
		'timezone'        => '_EventTimezone',
		'url'             => '_EventURL',
		'slug'            => 'humanitix_slug',
		'category'        => '_EventCategory',
		'currency'        => '_EventCurrency',
		'totalCapacity'   => '_EventCapacity',
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
		'humanitix_id'            => 'humanitix_event_id',
=======
		'humanitix_id'            => '_humanitix_event_id',
>>>>>>> Stashed changes
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
		if ( ! is_array( $humanitix_event ) ) {
			return array();
		}

<<<<<<< Updated upstream
=======


>>>>>>> Stashed changes
		$mapped_event = array(
			'post_type'   => 'tribe_events',
			'post_status' => 'publish',
			'meta_input'  => array(),
		);

<<<<<<< Updated upstream
		// Map basic .
=======
		// Set publication date from Humanitix createdAt field if available.
		if ( isset( $humanitix_event['createdAt'] ) && ! empty( $humanitix_event['createdAt'] ) ) {
			$created_timestamp = strtotime( $humanitix_event['createdAt'] );
			if ( false !== $created_timestamp ) {
				$mapped_event['post_date'] = date( 'Y-m-d H:i:s', $created_timestamp );
				$mapped_event['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $created_timestamp );
			}
		}

		// Map basic fields.
>>>>>>> Stashed changes
		foreach ( $this->field_mappings as $humanitix_field => $tec_field ) {
			if ( isset( $humanitix_event[ $humanitix_field ] ) ) {
				$value = $humanitix_event[ $humanitix_field ];

				// Handle special field mappings.
				switch ( $tec_field ) {
					case 'post_title':
						$mapped_event['post_title'] = sanitize_text_field( $value );
						break;
					case 'post_content':
						$mapped_event['post_content'] = wp_kses_post( $value );
						break;
<<<<<<< Updated upstream
					case 'EventStartDate':
					case 'EventEndDate':
						$mapped_event['meta_input'][ $tec_field ] = $this->format_date( $value );
						break;
					case 'EventStartTime':
					case 'EventEndTime':
						$mapped_event['meta_input'][ $tec_field ] = $this->format_time( $value );
=======
					case '_EventStartDate':
						// Store the raw UTC date string for later timezone conversion
						$mapped_event['meta_input']['_humanitix_start_date_utc'] = $value;
						break;
					case '_EventEndDate':
						// Store the raw UTC date string for later timezone conversion
						$mapped_event['meta_input']['_humanitix_end_date_utc'] = $value;
						break;
					case '_EventTimezone':
						$mapped_event['meta_input'][ $tec_field ] = $this->convert_timezone_for_tec( $value );
>>>>>>> Stashed changes
						break;
					default:
						$mapped_event['meta_input'][ $tec_field ] = sanitize_text_field( $value );
						break;
				}
			}
		}

		// Map custom Humanitix fields.
		foreach ( $this->custom_mappings as $humanitix_field => $custom_field ) {
			if ( isset( $humanitix_event[ $humanitix_field ] ) ) {
				$mapped_event['meta_input'][ $custom_field ] = $this->sanitize_custom_field( $humanitix_event[ $humanitix_field ] );
			}
		}

		// Handle nested objects and arrays.
		$mapped_event = $this->map_nested_data( $humanitix_event, $mapped_event );

<<<<<<< Updated upstream
		// Set default values for required TEC fields.
		$mapped_event = $this->set_default_values( $mapped_event );

=======
		// Set timezone information after we have the event dates.
		$mapped_event = $this->set_timezone_info( $humanitix_event, $mapped_event );

		// Set default values for required TEC fields.
		$mapped_event = $this->set_default_values( $mapped_event );



>>>>>>> Stashed changes
		return $mapped_event;
	}

	/**
	 * Map nested data structures.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @return array Updated mapped event data.
	 */
	private function map_nested_data( $humanitix_event, $mapped_event ) {
<<<<<<< Updated upstream
		// Handle event location information.
		if ( isset( $humanitix_event['eventLocation'] ) && is_array( $humanitix_event['eventLocation'] ) ) {
			$location = $humanitix_event['eventLocation'];

			if ( isset( $location['venueName'] ) ) {
				$mapped_event['meta_input']['EventVenue'] = sanitize_text_field( $location['venueName'] );
			}
			if ( isset( $location['address'] ) ) {
				$mapped_event['meta_input']['EventAddress'] = sanitize_text_field( $location['address'] );
			}
			if ( isset( $location['city'] ) ) {
				$mapped_event['meta_input']['EventCity'] = sanitize_text_field( $location['city'] );
			}
			if ( isset( $location['region'] ) ) {
				$mapped_event['meta_input']['EventState'] = sanitize_text_field( $location['region'] );
			}
			if ( isset( $location['country'] ) ) {
				$mapped_event['meta_input']['EventCountry'] = sanitize_text_field( $location['country'] );
			}
			if ( isset( $location['instructions'] ) ) {
				$mapped_event['meta_input']['humanitix_location_instructions'] = sanitize_textarea_field( $location['instructions'] );
			}
			if ( isset( $location['onlineUrl'] ) ) {
				$mapped_event['meta_input']['humanitix_online_url'] = esc_url_raw( $location['onlineUrl'] );
			}
			if ( isset( $location['latLng'] ) && is_array( $location['latLng'] ) ) {
				$mapped_event['meta_input']['humanitix_lat_lng'] = wp_json_encode( $location['latLng'] );
			}

			// Store full location data.
			$mapped_event['meta_input']['humanitix_location_data'] = wp_json_encode( $location );
=======
		// Initialize debug helper
		$logger = new \SG\HumanitixApiImporter\Admin\Logger();
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $logger );
		
		// Handle venue data - store for reference but let EventsImporter handle venue creation.
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
			// Store venue instructions and online URL as custom fields (not standard TEC venue fields).
			if ( isset( $venue['instructions'] ) ) {
				$mapped_event['meta_input']['humanitix_location_instructions'] = sanitize_textarea_field( $venue['instructions'] );
			}
			if ( isset( $venue['online_url'] ) ) {
				$mapped_event['meta_input']['humanitix_online_url'] = esc_url_raw( $venue['online_url'] );
			}
			
			// Store coordinates from Humanitix eventLocation structure
			if ( isset( $venue['latLng'] ) && is_array( $venue['latLng'] ) ) {
				$mapped_event['meta_input']['humanitix_lat_lng'] = wp_json_encode( $venue['latLng'] );
			} elseif ( isset( $venue['lat_lng'] ) && is_array( $venue['lat_lng'] ) ) {
				$mapped_event['meta_input']['humanitix_lat_lng'] = wp_json_encode( $venue['lat_lng'] );
			}

			// Store full venue data for reference.
			$mapped_event['meta_input']['humanitix_venue_data'] = wp_json_encode( $venue );
>>>>>>> Stashed changes
		}

		// Handle ticket types information.
		if ( isset( $humanitix_event['ticketTypes'] ) && is_array( $humanitix_event['ticketTypes'] ) ) {
			$mapped_event['meta_input']['humanitix_ticket_types'] = wp_json_encode( $humanitix_event['ticketTypes'] );

<<<<<<< Updated upstream
=======
					$debug_helper->smart_log( 'DataMapper', 'Processing ticket types', array(
			'ticket_count' => count( $humanitix_event['ticketTypes'] ),
		) );

>>>>>>> Stashed changes
			// Calculate total capacity and available tickets.
			$total_capacity    = 0;
			$available_tickets = 0;
			$min_price         = null;
			$max_price         = null;

			foreach ( $humanitix_event['ticketTypes'] as $ticket ) {
				if ( ! isset( $ticket['disabled'] ) || ! $ticket['disabled'] ) {
					$quantity           = isset( $ticket['quantity'] ) ? intval( $ticket['quantity'] ) : 0;
					$total_capacity    += $quantity;
					$available_tickets += $quantity;

					// Track pricing.
					if ( isset( $ticket['price'] ) ) {
						$price = floatval( $ticket['price'] );
						if ( null === $min_price || $price < $min_price ) {
							$min_price = $price;
						}
						if ( null === $max_price || $price > $max_price ) {
							$max_price = $price;
						}
					}
				}
			}

<<<<<<< Updated upstream
			$mapped_event['meta_input']['EventCapacity']               = $total_capacity;
			$mapped_event['meta_input']['humanitix_available_tickets'] = $available_tickets;

			// Set pricing information.
			if ( null !== $min_price ) {
				$mapped_event['meta_input']['EventCost'] = $min_price;
				if ( $max_price !== $min_price ) {
					$mapped_event['meta_input']['EventCost'] .= ' - ' . $max_price;
=======
			$mapped_event['meta_input']['_EventCapacity']               = $total_capacity;
			$mapped_event['meta_input']['humanitix_available_tickets'] = $available_tickets;

			$debug_helper->smart_log( 'DataMapper', 'Calculated pricing from tickets', array(
				'min_price' => $min_price,
				'max_price' => $max_price,
				'total_capacity' => $total_capacity,
			) );

			// Set pricing information.
			if ( null !== $min_price ) {
				// Only set cost from ticket types if pricing data hasn't already set it
				if ( ! isset( $mapped_event['meta_input']['_EventCost'] ) ) {
					$mapped_event['meta_input']['_EventCost'] = $min_price;
					if ( $max_price !== $min_price ) {
						$mapped_event['meta_input']['_EventCost'] .= ' - ' . $max_price;
					}
					
					$debug_helper->smart_log( 'DataMapper', 'Set event cost from ticket types', array(
						'cost' => $mapped_event['meta_input']['_EventCost'],
					) );
				} else {
					$debug_helper->smart_log( 'DataMapper', 'Event cost already set from pricing data, skipping ticket types pricing' );
>>>>>>> Stashed changes
				}
			}
		}

		// Handle pricing information.
		if ( isset( $humanitix_event['pricing'] ) && is_array( $humanitix_event['pricing'] ) ) {
			$mapped_event['meta_input']['humanitix_pricing'] = wp_json_encode( $humanitix_event['pricing'] );
<<<<<<< Updated upstream
=======
			
			$debug_helper->smart_log( 'DataMapper', 'Processing pricing data', array(
				'pricing_keys' => array_keys( $humanitix_event['pricing'] ),
			) );
			
			// Check for maximumPrice in pricing data
			if ( isset( $humanitix_event['pricing']['maximumPrice'] ) ) {
				$maximum_price_from_pricing = floatval( $humanitix_event['pricing']['maximumPrice'] );
				$debug_helper->smart_log( 'DataMapper', 'Found maximumPrice in pricing data', array(
					'maximum_price' => $maximum_price_from_pricing,
				) );
				
				// Use maximumPrice as the event cost
				$mapped_event['meta_input']['_EventCost'] = $maximum_price_from_pricing;
			}
>>>>>>> Stashed changes
		}

		// Handle images.
		$images = array();
		if ( isset( $humanitix_event['bannerImage']['url'] ) ) {
			$images['banner'] = $humanitix_event['bannerImage']['url'];
		}
		if ( isset( $humanitix_event['featureImage']['url'] ) ) {
			$images['feature'] = $humanitix_event['featureImage']['url'];
		}
		if ( isset( $humanitix_event['socialImage']['url'] ) ) {
			$images['social'] = $humanitix_event['socialImage']['url'];
		}

		if ( ! empty( $images ) ) {
			$mapped_event['meta_input']['humanitix_images'] = wp_json_encode( $images );

			// Set featured image if available.
			if ( isset( $images['feature'] ) ) {
<<<<<<< Updated upstream
				$mapped_event['meta_input']['_thumbnail_id'] = $this->process_event_image( $images['feature'] );
			} elseif ( isset( $images['banner'] ) ) {
				$mapped_event['meta_input']['_thumbnail_id'] = $this->process_event_image( $images['banner'] );
			}
		}

=======
				$thumbnail_id = $this->process_event_image( $images['feature'] );
				$mapped_event['meta_input']['_thumbnail_id'] = $thumbnail_id;
			} elseif ( isset( $images['banner'] ) ) {
				$thumbnail_id = $this->process_event_image( $images['banner'] );
				$mapped_event['meta_input']['_thumbnail_id'] = $thumbnail_id;
			}
		}

		// Validate required fields
		if ( empty( $mapped_event['post_title'] ) ) {
			$debug_helper->log_critical_error( 'DataMapper', 'Event mapping failed: Missing required post_title', array(
				'humanitix_id' => $humanitix_event['_id'] ?? 'unknown',
				'available_fields' => array_keys( $humanitix_event ),
			) );
		}

		if ( empty( $mapped_event['meta_input']['_EventStartDate'] ) ) {
			$debug_helper->log_critical_error( 'DataMapper', 'Event mapping failed: Missing required start date', array(
				'humanitix_id' => $humanitix_event['_id'] ?? 'unknown',
				'start_date_raw' => $humanitix_event['startDate'] ?? 'not set',
			) );
		}

>>>>>>> Stashed changes
		// Handle dates array.
		if ( isset( $humanitix_event['dates'] ) && is_array( $humanitix_event['dates'] ) ) {
			$mapped_event['meta_input']['humanitix_dates'] = wp_json_encode( $humanitix_event['dates'] );
		}

		// Handle accessibility information.
		if ( isset( $humanitix_event['accessibility'] ) && is_array( $humanitix_event['accessibility'] ) ) {
			$mapped_event['meta_input']['humanitix_accessibility'] = wp_json_encode( $humanitix_event['accessibility'] );
		}

		// Handle additional questions.
		if ( isset( $humanitix_event['additionalQuestions'] ) && is_array( $humanitix_event['additionalQuestions'] ) ) {
			$mapped_event['meta_input']['humanitix_additional_questions'] = wp_json_encode( $humanitix_event['additionalQuestions'] );
		}

		// Handle payment options.
		if ( isset( $humanitix_event['paymentOptions'] ) && is_array( $humanitix_event['paymentOptions'] ) ) {
			$mapped_event['meta_input']['humanitix_payment_options'] = wp_json_encode( $humanitix_event['paymentOptions'] );
		}

		// Handle artists.
		if ( isset( $humanitix_event['artists'] ) && is_array( $humanitix_event['artists'] ) ) {
			$mapped_event['meta_input']['humanitix_artists'] = wp_json_encode( $humanitix_event['artists'] );
		}

		// Handle classification.
		if ( isset( $humanitix_event['classification'] ) && is_array( $humanitix_event['classification'] ) ) {
			$mapped_event['meta_input']['humanitix_classification'] = wp_json_encode( $humanitix_event['classification'] );
		}

		// Handle tag IDs.
		if ( isset( $humanitix_event['tagIds'] ) && is_array( $humanitix_event['tagIds'] ) ) {
			$mapped_event['meta_input']['humanitix_tag_ids'] = wp_json_encode( $humanitix_event['tagIds'] );
		}

		// Handle packaged tickets.
		if ( isset( $humanitix_event['packagedTickets'] ) && is_array( $humanitix_event['packagedTickets'] ) ) {
			$mapped_event['meta_input']['humanitix_packaged_tickets'] = wp_json_encode( $humanitix_event['packagedTickets'] );
		}

		return $mapped_event;
	}

	/**
	 * Format date for The Events Calendar.
	 *
<<<<<<< Updated upstream
	 * @param string $date_string Humanitix date string.
=======
	 * @param string $date_string Humanitix date string (ISO 8601 format).
>>>>>>> Stashed changes
	 * @return string Formatted date.
	 */
	private function format_date( $date_string ) {
		if ( empty( $date_string ) ) {
			return '';
		}

<<<<<<< Updated upstream
		// Handle ISO 8601 format from Humanitix API.
=======
		// Handle ISO 8601 format from Humanitix API (e.g., "2021-02-01T23:26:13.485Z").
		$timestamp = strtotime( $date_string );
		if ( false === $timestamp ) {
			return '';
		}

		// Use date() to preserve the original timezone information from the ISO string.
		return date( 'Y-m-d', $timestamp );
	}

	/**
	 * Format datetime for The Events Calendar.
	 *
	 * @param string $date_string Humanitix date string (ISO 8601 format).
	 * @return string Formatted datetime in Y-m-d H:i:s format.
	 */
	private function format_datetime( $date_string ) {
		if ( empty( $date_string ) ) {
			return '';
		}

		// Handle ISO 8601 format from Humanitix API (e.g., "2021-02-01T23:26:13.485Z").
		// Note: This method is now deprecated in favor of proper timezone conversion
		// in the set_timezone_info method.
>>>>>>> Stashed changes
		$timestamp = strtotime( $date_string );
		if ( false === $timestamp ) {
			return '';
		}

<<<<<<< Updated upstream
		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Format time for The Events Calendar.
	 *
	 * @param string $date_string Humanitix date string.
=======
		return date( 'Y-m-d H:i:s', $timestamp );
	}



	/**
	 * Format time for The Events Calendar.
	 *
	 * @param string $date_string Humanitix date string (ISO 8601 format).
>>>>>>> Stashed changes
	 * @return string Formatted time.
	 */
	private function format_time( $date_string ) {
		if ( empty( $date_string ) ) {
			return '';
		}

<<<<<<< Updated upstream
		// Handle ISO 8601 format from Humanitix API.
=======
		// Handle ISO 8601 format from Humanitix API (e.g., "2021-02-01T23:26:13.485Z").
>>>>>>> Stashed changes
		$timestamp = strtotime( $date_string );
		if ( false === $timestamp ) {
			return '';
		}

<<<<<<< Updated upstream
		return gmdate( 'H:i:s', $timestamp );
=======
		// Use date() to preserve the original timezone information from the ISO string.
		return date( 'H:i:s', $timestamp );
>>>>>>> Stashed changes
	}

	/**
	 * Sanitize custom field values.
	 *
	 * @param mixed $value Field value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_custom_field( $value ) {
		if ( is_array( $value ) ) {
			return wp_json_encode( $value );
		} elseif ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		} else {
			return $value;
		}
	}

	/**
<<<<<<< Updated upstream
=======
	 * Set timezone information for the event.
	 *
	 * @param array $humanitix_event Humanitix event data.
	 * @param array $mapped_event Mapped event data.
	 * @return array Updated mapped event data.
	 */
	private function set_timezone_info( $humanitix_event, $mapped_event ) {
		// Initialize debug helper
		$logger = new \SG\HumanitixApiImporter\Admin\Logger();
		$debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper( $logger );
		
		// Get the timezone from Humanitix data.
		$timezone_string = '';
		if ( isset( $humanitix_event['timezone'] ) ) {
			$timezone_string = $this->convert_timezone_for_tec( $humanitix_event['timezone'] );
			$debug_helper->log( 'DataMapper', 'Set timezone from Humanitix data', array(
				'timezone' => $timezone_string,
			) );
		}

		// If no timezone from Humanitix, try to determine from event venue.
		$venue_data = $humanitix_event['venue'] ?? $humanitix_event['eventLocation'] ?? $humanitix_event['location'] ?? array();
		if ( empty( $timezone_string ) && isset( $venue_data['country'] ) ) {
			$timezone_string = $this->get_timezone_from_location( $venue_data );
			$debug_helper->log( 'DataMapper', 'Set timezone from venue', array(
				'timezone' => $timezone_string,
				'venue_country' => $venue_data['country'] ?? 'unknown',
			) );
		}

		// Fallback to WordPress site timezone.
		if ( empty( $timezone_string ) ) {
			$timezone_string = get_option( 'timezone_string' );
			if ( empty( $timezone_string ) ) {
				$timezone_string = 'America/New_York'; // Final fallback.
			}
			$debug_helper->log( 'DataMapper', 'Set timezone from WordPress fallback', array(
				'timezone' => $timezone_string,
			) );
		}

		// Set the timezone field.
		$mapped_event['meta_input']['_EventTimezone'] = $timezone_string;
		$debug_helper->log( 'DataMapper', 'Final timezone set', array(
			'timezone' => $timezone_string,
		) );

		// Convert UTC dates to the event's timezone
		if ( isset( $mapped_event['meta_input']['_humanitix_start_date_utc'] ) && ! empty( $mapped_event['meta_input']['_humanitix_start_date_utc'] ) ) {
			$converted_start_date = $this->convert_utc_to_timezone( $mapped_event['meta_input']['_humanitix_start_date_utc'], $timezone_string );
			$mapped_event['meta_input']['_EventStartDate'] = $converted_start_date;
			
			// Set timezone abbreviation
			$mapped_event['meta_input']['_EventTimezoneAbbr'] = $this->get_timezone_abbr( $converted_start_date, $timezone_string );
			
			// Store UTC version for reference
			$mapped_event['meta_input']['_EventStartDateUTC'] = $mapped_event['meta_input']['_humanitix_start_date_utc'];
			
			// Clean up temporary field
			unset( $mapped_event['meta_input']['_humanitix_start_date_utc'] );
		}

		if ( isset( $mapped_event['meta_input']['_humanitix_end_date_utc'] ) && ! empty( $mapped_event['meta_input']['_humanitix_end_date_utc'] ) ) {
			$converted_end_date = $this->convert_utc_to_timezone( $mapped_event['meta_input']['_humanitix_end_date_utc'], $timezone_string );
			$mapped_event['meta_input']['_EventEndDate'] = $converted_end_date;
			
			// Store UTC version for reference
			$mapped_event['meta_input']['_EventEndDateUTC'] = $mapped_event['meta_input']['_humanitix_end_date_utc'];
			
			// Clean up temporary field
			unset( $mapped_event['meta_input']['_humanitix_end_date_utc'] );
		}

		return $mapped_event;
	}

	/**
>>>>>>> Stashed changes
	 * Set default values for required TEC fields.
	 *
	 * @param array $mapped_event Mapped event data.
	 * @return array Updated mapped event data.
	 */
	private function set_default_values( $mapped_event ) {
		// Set default event duration if not specified.
<<<<<<< Updated upstream
		if ( ! isset( $mapped_event['meta_input']['EventEndDate'] ) ) {
			$mapped_event['meta_input']['EventEndDate'] = $mapped_event['meta_input']['EventStartDate'] ?? '';
		}

		if ( ! isset( $mapped_event['meta_input']['EventEndTime'] ) ) {
			$mapped_event['meta_input']['EventEndTime'] = $mapped_event['meta_input']['EventStartTime'] ?? '';
		}

		// Set default event status.
		if ( ! isset( $mapped_event['meta_input']['EventStatus'] ) ) {
			$mapped_event['meta_input']['EventStatus'] = 'publish';
		}

		// Set default event cost if not specified.
		if ( ! isset( $mapped_event['meta_input']['EventCost'] ) ) {
			$mapped_event['meta_input']['EventCost'] = '';
=======
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
>>>>>>> Stashed changes
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
			return false;
		}

<<<<<<< Updated upstream
		// Check if image already exists.
		$existing_attachment = $this->find_existing_image( $image_url );
		if ( $existing_attachment ) {
			return $existing_attachment;
		}

		// Download and attach image.
		$attachment_id = $this->download_and_attach_image( $image_url );
=======
		// Use static cache for this request
		static $image_cache = array();
		$cache_key = md5( $image_url );
		
		if ( isset( $image_cache[ $cache_key ] ) ) {
			return $image_cache[ $cache_key ];
		}

		// Check if image already exists in database.
		$existing_attachment = $this->find_existing_image( $image_url );
		if ( $existing_attachment ) {
			$image_cache[ $cache_key ] = $existing_attachment;
			return $existing_attachment;
		}

		// For performance, skip image download by default during bulk imports
		// Images can be processed separately or on-demand
		// Only download images if explicitly enabled
		if ( ! \SG\HumanitixApiImporter\Admin\PerformanceConfig::should_enable_image_download() ) {
			$image_cache[ $cache_key ] = false;
			return false;
		}

		// Download and attach image.
		$attachment_id = $this->download_and_attach_image( $image_url );
		
		$image_cache[ $cache_key ] = $attachment_id;
>>>>>>> Stashed changes
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

<<<<<<< Updated upstream
		// Get file info.
		$file_array = array(
			'name'     => basename( $image_url ),
			'tmp_name' => $tmp,
=======
		// Get file info and determine proper filename with extension.
		$file_info = $this->get_file_info_from_url( $image_url, $tmp );

		// Get file info.
		$file_array = array(
			'name'     => $file_info['filename'],
			'tmp_name' => $tmp,
			'type'     => $file_info['mime'],
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
=======

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
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			'image/svg+xml' => 'svg',
			'image/bmp'  => 'bmp',
			'image/tiff' => 'tiff',
		);

		$extension = $mime_to_ext[ $mime_type ] ?? 'jpg'; // Default to jpg if unknown

		// Try to get filename from URL.
		$url_parts = parse_url( $image_url );
		$path = $url_parts['path'] ?? '';
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
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file_path );
			finfo_close( $finfo );
			
			if ( $mime_type && $mime_type !== 'application/octet-stream' ) {
				return $mime_type;
			}
		}

		// Method 2: Use mime_content_type if available.
		if ( function_exists( 'mime_content_type' ) ) {
			$mime_type = mime_content_type( $file_path );
			if ( $mime_type && $mime_type !== 'application/octet-stream' ) {
				return $mime_type;
			}
		}

		// Method 3: Check file extension as fallback.
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
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
		$timezone = trim( $timezone );
		
		// Debug logging for timezone conversion
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
			'UTC', 'UTC+0', 'UTC+00:00', 'UTC-0', 'UTC-00:00',
			'GMT', 'GMT+0', 'GMT-0', 'GMT+00:00', 'GMT-00:00',
			'Z', '+00:00', '-00:00', '+0', '-0'
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
				error_log( "Humanitix DataMapper: Converting UTC timezone to default: America/New_York" );
			}
			return 'America/New_York';
		}
		
		// Handle offset-based timezones (e.g., UTC+5, UTC-8, +5, -8).
		$offset_patterns = array(
			'/^UTC([+-]\d{1,2}(?::\d{2})?)$/',  // UTC+5, UTC-8, UTC+5:30
			'/^GMT([+-]\d{1,2}(?::\d{2})?)$/',  // GMT+5, GMT-8, GMT+5:30
			'/^([+-]\d{1,2}(?::\d{2})?)$/',     // +5, -8, +5:30
		);
		
		foreach ( $offset_patterns as $pattern ) {
			if ( preg_match( $pattern, $timezone, $matches ) ) {
				$offset = $matches[1];
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
			error_log( "Humanitix DataMapper: Using final fallback timezone: America/New_York" );
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
			'/^[A-Z][a-z]+\/[A-Z][a-z_]+$/', // America/New_York, Europe/London
			'/^[A-Z][a-z]+\/[A-Z][a-z]+\/[A-Z][a-z]+$/', // America/Indiana/Indianapolis
			'/^[A-Z][a-z]+\/[A-Z][a-z]+$/', // Asia/Tokyo, Africa/Cairo
			'/^[A-Z][a-z]+\/[A-Z][a-z]+\/[A-Z][a-z]+\/[A-Z][a-z]+$/', // America/Indiana/Indianapolis
		);
		
		foreach ( $geographic_patterns as $pattern ) {
			if ( preg_match( $pattern, $timezone ) ) {
				return true;
			}
		}
		
		// Check against a comprehensive list of known geographic timezones.
		$known_geographic = array(
			// America
			'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
			'America/Toronto', 'America/Vancouver', 'America/Edmonton', 'America/Winnipeg',
			'America/Mexico_City', 'America/Sao_Paulo', 'America/Argentina/Buenos_Aires',
			'America/Santiago', 'America/Lima', 'America/Caracas', 'America/Bogota',
			'America/Guayaquil', 'America/La_Paz', 'America/Asuncion', 'America/Montevideo',
			'America/Guyana', 'America/Paramaribo', 'America/Cayenne',
			
			// Europe
			'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Moscow',
			'Europe/Rome', 'Europe/Madrid', 'Europe/Amsterdam', 'Europe/Brussels',
			'Europe/Zurich', 'Europe/Vienna', 'Europe/Stockholm', 'Europe/Oslo',
			'Europe/Copenhagen', 'Europe/Helsinki', 'Europe/Warsaw', 'Europe/Prague',
			'Europe/Budapest', 'Europe/Bucharest', 'Europe/Sofia', 'Europe/Zagreb',
			'Europe/Ljubljana', 'Europe/Bratislava', 'Europe/Vilnius', 'Europe/Riga',
			'Europe/Tallinn', 'Europe/Dublin', 'Europe/Lisbon', 'Europe/Athens',
			'Europe/Istanbul', 'Europe/Kiev', 'Europe/Minsk', 'Europe/Chisinau',
			
			// Asia
			'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Kolkata', 'Asia/Dubai',
			'Asia/Seoul', 'Asia/Singapore', 'Asia/Kuala_Lumpur', 'Asia/Bangkok',
			'Asia/Ho_Chi_Minh', 'Asia/Manila', 'Asia/Jakarta', 'Asia/Colombo',
			'Asia/Dhaka', 'Asia/Kathmandu', 'Asia/Thimphu', 'Asia/Yangon',
			'Asia/Vientiane', 'Asia/Phnom_Penh', 'Asia/Ulaanbaatar', 'Asia/Almaty',
			'Asia/Tashkent', 'Asia/Bishkek', 'Asia/Dushanbe', 'Asia/Ashgabat',
			'Asia/Kabul', 'Asia/Karachi', 'Asia/Tehran', 'Asia/Baghdad',
			'Asia/Damascus', 'Asia/Beirut', 'Asia/Amman', 'Asia/Jerusalem',
			'Asia/Gaza', 'Asia/Riyadh', 'Asia/Qatar', 'Asia/Bahrain',
			'Asia/Kuwait', 'Asia/Muscat', 'Asia/Aden', 'Asia/Tbilisi',
			'Asia/Yerevan', 'Asia/Baku', 'Asia/Nicosia',
			
			// Australia & Pacific
			'Australia/Sydney', 'Australia/Melbourne', 'Australia/Perth',
			'Australia/Brisbane', 'Australia/Adelaide', 'Australia/Darwin',
			'Pacific/Auckland', 'Pacific/Honolulu', 'Pacific/Fiji',
			'Pacific/Guadalcanal', 'Pacific/Midway', 'Pacific/Kwajalein',
			
			// Africa
			'Africa/Cairo', 'Africa/Johannesburg', 'Africa/Lagos', 'Africa/Nairobi',
			'Africa/Accra', 'Africa/Casablanca', 'Africa/Tunis', 'Africa/Algiers',
			'Africa/Tripoli', 'Africa/Khartoum', 'Africa/Addis_Ababa', 'Africa/Kampala',
			'Africa/Dar_es_Salaam', 'Africa/Lusaka', 'Africa/Harare', 'Africa/Gaborone',
			'Africa/Windhoek', 'Africa/Johannesburg',
			
			// Indian Ocean
			'Indian/Antananarivo', 'Indian/Mauritius', 'Indian/Mahe', 'Indian/Maldives',
			
			// Atlantic
			'Atlantic/Azores', 'Atlantic/South_Georgia', 'Atlantic/Stanley',
			
			// Other
			'Etc/UTC', 'Etc/Zulu', 'ZULU',
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
			// Positive offsets
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
			'+13'    => 'Pacific/Auckland', // During daylight saving
			'+14'    => 'Pacific/Kiritimati',
			
			// Negative offsets
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
			
			// Half-hour offsets
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
			// Create DateTime object from UTC string
			$utc_date = new \DateTime( $utc_datetime, new \DateTimeZone( 'UTC' ) );
			
			// Convert to target timezone
			$utc_date->setTimezone( new \DateTimeZone( $timezone ) );
			
			return $utc_date->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			// Fallback to simple conversion if DateTime fails
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
>>>>>>> Stashed changes
}
