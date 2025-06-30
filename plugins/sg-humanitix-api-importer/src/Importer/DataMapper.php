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
		'startDate'       => 'EventStartDate',
		'endDate'         => 'EventEndDate',
		'timezone'        => 'EventTimezone',
		'url'             => 'EventURL',
		'slug'            => 'humanitix_slug',
		'category'        => 'EventCategory',
		'currency'        => 'EventCurrency',
		'totalCapacity'   => 'EventCapacity',
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
		'humanitix_id'            => 'humanitix_event_id',
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

		$mapped_event = array(
			'post_type'   => 'tribe_events',
			'post_status' => 'publish',
			'meta_input'  => array(),
		);

		// Map basic .
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
					case 'EventStartDate':
					case 'EventEndDate':
						$mapped_event['meta_input'][ $tec_field ] = $this->format_date( $value );
						break;
					case 'EventStartTime':
					case 'EventEndTime':
						$mapped_event['meta_input'][ $tec_field ] = $this->format_time( $value );
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

		// Set default values for required TEC fields.
		$mapped_event = $this->set_default_values( $mapped_event );

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
		}

		// Handle ticket types information.
		if ( isset( $humanitix_event['ticketTypes'] ) && is_array( $humanitix_event['ticketTypes'] ) ) {
			$mapped_event['meta_input']['humanitix_ticket_types'] = wp_json_encode( $humanitix_event['ticketTypes'] );

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

			$mapped_event['meta_input']['EventCapacity']               = $total_capacity;
			$mapped_event['meta_input']['humanitix_available_tickets'] = $available_tickets;

			// Set pricing information.
			if ( null !== $min_price ) {
				$mapped_event['meta_input']['EventCost'] = $min_price;
				if ( $max_price !== $min_price ) {
					$mapped_event['meta_input']['EventCost'] .= ' - ' . $max_price;
				}
			}
		}

		// Handle pricing information.
		if ( isset( $humanitix_event['pricing'] ) && is_array( $humanitix_event['pricing'] ) ) {
			$mapped_event['meta_input']['humanitix_pricing'] = wp_json_encode( $humanitix_event['pricing'] );
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
				$mapped_event['meta_input']['_thumbnail_id'] = $this->process_event_image( $images['feature'] );
			} elseif ( isset( $images['banner'] ) ) {
				$mapped_event['meta_input']['_thumbnail_id'] = $this->process_event_image( $images['banner'] );
			}
		}

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
	 * @param string $date_string Humanitix date string.
	 * @return string Formatted date.
	 */
	private function format_date( $date_string ) {
		if ( empty( $date_string ) ) {
			return '';
		}

		// Handle ISO 8601 format from Humanitix API.
		$timestamp = strtotime( $date_string );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Format time for The Events Calendar.
	 *
	 * @param string $date_string Humanitix date string.
	 * @return string Formatted time.
	 */
	private function format_time( $date_string ) {
		if ( empty( $date_string ) ) {
			return '';
		}

		// Handle ISO 8601 format from Humanitix API.
		$timestamp = strtotime( $date_string );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'H:i:s', $timestamp );
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
	 * Set default values for required TEC fields.
	 *
	 * @param array $mapped_event Mapped event data.
	 * @return array Updated mapped event data.
	 */
	private function set_default_values( $mapped_event ) {
		// Set default event duration if not specified.
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

		// Check if image already exists.
		$existing_attachment = $this->find_existing_image( $image_url );
		if ( $existing_attachment ) {
			return $existing_attachment;
		}

		// Download and attach image.
		$attachment_id = $this->download_and_attach_image( $image_url );
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

		// Get file info.
		$file_array = array(
			'name'     => basename( $image_url ),
			'tmp_name' => $tmp,
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
}
