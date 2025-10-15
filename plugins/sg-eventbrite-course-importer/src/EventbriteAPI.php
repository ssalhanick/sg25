<?php
/**
 * Eventbrite API Class.
 *
 * Handles communication with the Eventbrite API for fetching events and event details.
 *
 * @package SG\EventbriteCourseImporter
 * @since 1.0.0
 */

namespace SG\EventbriteCourseImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Eventbrite API Class.
 *
 * Manages API communication with Eventbrite for event data retrieval.
 *
 * @package SG\EventbriteCourseImporter
 * @since 1.0.0
 */
class EventbriteAPI {

	/**
	 * Eventbrite API base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://www.eventbriteapi.com/v3';

	/**
	 * OAuth2 authentication instance.
	 *
	 * @var EventbriteOAuth
	 */
	private $oauth;

	/**
	 * Organization ID for filtering events.
	 *
	 * @var string
	 */
	private $organization_id;

	/**
	 * Rate limiting configuration.
	 *
	 * @var array
	 */
	private $rate_limit = array(
		'requests_per_minute' => 60,
		'last_request_time'   => 0,
		'request_count'       => 0,
		'minute_start'        => 0,
	);

	/**
	 * Cache duration in seconds.
	 *
	 * @var int
	 */
	private $cache_duration = 3600; // 1 hour

	/**
	 * Logger instance.
	 *
	 * @var \SG\EventbriteCourseImporter\Admin\Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param EventbriteOAuth $oauth OAuth2 instance.
	 * @param string $organization_id Eventbrite organization ID.
	 */
	public function __construct( EventbriteOAuth $oauth = null, $organization_id = null ) {
		$this->oauth = $oauth ? $oauth : new EventbriteOAuth();
		$this->organization_id = $organization_id ? $organization_id : get_option( 'sg_eventbrite_organization_id', '' );
		$this->logger = new \SG\EventbriteCourseImporter\Admin\Logger();
	}

	/**
	 * Set OAuth2 credentials.
	 *
	 * @param string $client_id OAuth2 client ID.
	 * @param string $client_secret OAuth2 client secret.
	 * @param string $organization_id Eventbrite organization ID.
	 */
	public function set_credentials( $client_id, $client_secret, $organization_id ) {
		$this->oauth->set_credentials( $client_id, $client_secret );
		$this->organization_id = $organization_id;
	}

	/**
	 * Get events from Eventbrite API.
	 *
	 * @param array $args Query arguments.
	 * @return array|WP_Error Event data or error.
	 */
	public function get_events( $args = array() ) {
		$defaults = array(
			'status'         => 'live',
			'order_by'       => 'start_asc',
			'expand'         => 'venue,organizer,logo,description',
			'page_size'      => 50,
			'page'           => 1,
			'time_filter'    => 'current_future',
		);

		$args = wp_parse_args( $args, $defaults );

		// Add organization filter if available
		if ( $this->organization_id ) {
			$args['organizer.id'] = $this->organization_id;
		}

		$cache_key = 'sg_eventbrite_events_' . md5( serialize( $args ) );
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			$this->logger->log( 'Retrieved events from cache', 'info' );
			return $cached_data;
		}

		$url = add_query_arg( $args, self::API_BASE_URL . '/events/search/' );
		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Cache the response
		set_transient( $cache_key, $response, $this->cache_duration );

		return $response;
	}

	/**
	 * Get a specific event by ID.
	 *
	 * @param string $event_id Eventbrite event ID.
	 * @param array  $expand   Fields to expand in the response.
	 * @return array|WP_Error Event data or error.
	 */
	public function get_event( $event_id, $expand = array() ) {
		if ( empty( $event_id ) ) {
			return new \WP_Error( 'invalid_event_id', __( 'Event ID is required', 'sg-eventbrite-course-importer' ) );
		}

		$default_expand = array( 'venue', 'organizer', 'logo', 'description', 'ticket_classes' );
		$expand = array_merge( $default_expand, $expand );

		$cache_key = 'sg_eventbrite_event_' . $event_id . '_' . md5( serialize( $expand ) );
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			$this->logger->log( "Retrieved event {$event_id} from cache", 'info' );
			return $cached_data;
		}

		$url = add_query_arg( array( 'expand' => implode( ',', $expand ) ), self::API_BASE_URL . '/events/' . $event_id . '/' );
		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Cache the response
		set_transient( $cache_key, $response, $this->cache_duration );

		return $response;
	}

	/**
	 * Get organization events.
	 *
	 * @param array $args Query arguments.
	 * @return array|WP_Error Event data or error.
	 */
	public function get_organization_events( $args = array() ) {
		if ( empty( $this->organization_id ) ) {
			return new \WP_Error( 'no_organization_id', __( 'Organization ID is required', 'sg-eventbrite-course-importer' ) );
		}

		$defaults = array(
			'status'      => 'live',
			'order_by'    => 'start_asc',
			'expand'      => 'venue,organizer,logo,description',
			'page_size'   => 50,
			'page'        => 1,
			'time_filter' => 'current_future',
		);

		$args = wp_parse_args( $args, $defaults );

		$cache_key = 'sg_eventbrite_org_events_' . $this->organization_id . '_' . md5( serialize( $args ) );
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			$this->logger->log( "Retrieved organization events from cache", 'info' );
			return $cached_data;
		}

		$url = add_query_arg( $args, self::API_BASE_URL . '/organizations/' . $this->organization_id . '/events/' );
		
		// Debug: Log the URL being called
		error_log( 'SG Eventbrite: API URL: ' . $url );
		error_log( 'SG Eventbrite: API parameters for get_organization_events: ' . print_r( $args, true ) );
		
		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Debug: Log the first few events from the API response
		if ( isset( $response['events'] ) && ! empty( $response['events'] ) ) {
			error_log( 'SG Eventbrite: First 5 events from get_organization_events API response:' );
			for ( $i = 0; $i < min(5, count($response['events'])); $i++ ) {
				$event = $response['events'][$i];
				$date = isset( $event['start']['utc'] ) ? $event['start']['utc'] : 'No date';
				$timestamp = isset( $event['start']['utc'] ) ? strtotime( $event['start']['utc'] ) : 0;
				error_log( 'SG Eventbrite: ' . ($i+1) . '. "' . $event['name']['text'] . '" - ' . $date . ' (timestamp: ' . $timestamp . ')' );
			}
		}

		// Cache the response
		set_transient( $cache_key, $response, $this->cache_duration );

		return $response;
	}

	/**
	 * Search events by query.
	 *
	 * @param string $query Search query.
	 * @param array  $args  Additional query arguments.
	 * @return array|WP_Error Event data or error.
	 */
	public function search_events( $query, $args = array() ) {
		// Extract date filtering parameters (for client-side filtering)
		$start_date = isset( $args['start_date.range_start'] ) ? $args['start_date.range_start'] : '';
		$end_date = isset( $args['start_date.range_end'] ) ? $args['start_date.range_end'] : '';
		
		// Convert API date format to simple date format for client-side filtering
		if ( ! empty( $start_date ) ) {
			$start_date = date( 'Y-m-d', strtotime( $start_date ) );
		}
		if ( ! empty( $end_date ) ) {
			$end_date = date( 'Y-m-d', strtotime( $end_date ) );
		}
		// Set default pagination parameters
		$page = isset( $args['page'] ) ? intval( $args['page'] ) : 1;
		$per_page = 10;
		
		// For search, we need to get events in batches to find all matches
		// Start with the most recent events first
		$all_matching_events = array();
		$current_page = 1;
		$max_pages_to_search = 50; // Limit to prevent infinite loops (increased to handle 50 events per page)
		
		while ( $current_page <= $max_pages_to_search ) {
			$org_args = array(
				'page_size' => 100, // Get 100 events per batch
				'page' => $current_page,
				'order_by' => 'start_desc', // Most recent first
				'time_filter' => 'all', // Get all events
			);
			
			// Note: Eventbrite API date filtering doesn't work reliably, so we do client-side filtering
			
			// Debug: Log the parameters being sent to the API
			if ( $current_page === 1 ) {
				error_log( 'SG Eventbrite: API parameters for search: ' . print_r( $org_args, true ) );
			}
			
			$org_events = $this->get_organization_events( $org_args );
			
			if ( is_wp_error( $org_events ) ) {
				return $org_events;
			}
			
			// If no more events, break
			if ( empty( $org_events['events'] ) ) {
				break;
			}
			
			// Debug: Log the first few events from this API batch
			if ( $current_page === 1 ) {
				error_log( 'SG Eventbrite: First 5 events from API batch 1:' );
				for ( $i = 0; $i < min(5, count($org_events['events'])); $i++ ) {
					$event = $org_events['events'][$i];
					$date = isset( $event['start']['utc'] ) ? $event['start']['utc'] : 'No date';
					error_log( 'SG Eventbrite: ' . ($i+1) . '. "' . $event['name']['text'] . '" - ' . $date );
				}
			}
			
			// Filter events based on search query
			foreach ( $org_events['events'] as $event ) {
				// Search in event name and description
				$search_text = strtolower( $event['name']['text'] );
				if ( isset( $event['description']['text'] ) ) {
					$search_text .= ' ' . strtolower( $event['description']['text'] );
				}
				
				// Check if query matches
				if ( strpos( $search_text, strtolower( $query ) ) !== false ) {
					// Debug: Log all events to see what years we have
					$event_date = isset( $event['start']['utc'] ) ? $event['start']['utc'] : null;
					if ( $event_date ) {
						$event_year = date( 'Y', strtotime( $event_date ) );
						error_log( 'SG Eventbrite: Found event from year: ' . $event_year . ' - ' . $event['name']['text'] );
					}
					
					// Additional client-side date filtering as fallback
					$include_event = true;
					
					if ( $event_date && ( ! empty( $start_date ) || ! empty( $end_date ) ) ) {
						$event_timestamp = strtotime( $event_date );
						$event_date_only = date( 'Y-m-d', $event_timestamp );
						
						// Debug: Log date filtering
						error_log( 'SG Eventbrite: Date filtering - Event: ' . $event_date_only . ', Start: ' . $start_date . ', End: ' . $end_date );
						
						// Check start date
						if ( ! empty( $start_date ) && $event_date_only < $start_date ) {
							$include_event = false;
							error_log( 'SG Eventbrite: Excluding event - before start date' );
						}
						
						// Check end date
						if ( ! empty( $end_date ) && $event_date_only > $end_date ) {
							$include_event = false;
							error_log( 'SG Eventbrite: Excluding event - after end date' );
						}
					}
					
					if ( $include_event ) {
						$all_matching_events[] = $event;
					}
				}
			}
			
			// Check if there are more pages using the pagination metadata
			$pagination = $org_events['pagination'] ?? array();
			$has_more_items = $pagination['has_more_items'] ?? false;
			
			error_log( 'SG Eventbrite: Search page ' . $current_page . ' pagination info: ' . print_r( $pagination, true ) );
			
			// If there are no more items, we've reached the last page
			if ( ! $has_more_items ) {
				error_log( 'SG Eventbrite: No more items available for search, stopping pagination' );
				break;
			}
			
			$current_page++;
		}
		
		error_log( 'SG Eventbrite: Found ' . count( $all_matching_events ) . ' events matching query "' . $query . '"' );
		
		// Sort by start date (most recent first) - they should already be sorted from API
		usort( $all_matching_events, function( $a, $b ) {
			$date_a = isset( $a['start']['utc'] ) ? strtotime( $a['start']['utc'] ) : 0;
			$date_b = isset( $b['start']['utc'] ) ? strtotime( $b['start']['utc'] ) : 0;
			
			// Debug: Log the first few comparisons
			static $debug_count = 0;
			if ( $debug_count < 5 ) {
				error_log( 'SG Eventbrite: Sorting - A: ' . (isset($a['start']['utc']) ? $a['start']['utc'] : 'No date') . ' (' . $date_a . ') vs B: ' . (isset($b['start']['utc']) ? $b['start']['utc'] : 'No date') . ' (' . $date_b . ') - Result: ' . ($date_b - $date_a) );
				$debug_count++;
			}
			
			return $date_b - $date_a; // Most recent first
		});
		
		// Debug: Log the first few events after sorting
		error_log( 'SG Eventbrite: First 5 events after sorting:' );
		for ( $i = 0; $i < min(5, count($all_matching_events)); $i++ ) {
			$event = $all_matching_events[$i];
			$date = isset( $event['start']['utc'] ) ? $event['start']['utc'] : 'No date';
			$timestamp = isset( $event['start']['utc'] ) ? strtotime( $event['start']['utc'] ) : 0;
			error_log( 'SG Eventbrite: ' . ($i+1) . '. "' . $event['name']['text'] . '" - ' . $date . ' (timestamp: ' . $timestamp . ')' );
		}
		
		// Apply pagination
		$total_events = count( $all_matching_events );
		$total_pages = ceil( $total_events / $per_page );
		$offset = ( $page - 1 ) * $per_page;
		$paginated_events = array_slice( $all_matching_events, $offset, $per_page );
		
		// Create pagination info
		$pagination = array(
			'object_count' => $total_events,
			'page_number' => $page,
			'page_size' => $per_page,
			'page_count' => $total_pages,
			'has_more_items' => $page < $total_pages,
		);
		
		return array(
			'events' => $paginated_events,
			'pagination' => $pagination
		);
	}

	/**
	 * Make a request to the Eventbrite API using OAuth2.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error Response data or error.
	 */
	private function make_request( $endpoint, $args = array() ) {
		if ( ! $this->oauth->is_authenticated() ) {
			return new \WP_Error( 'not_authenticated', __( 'Eventbrite OAuth2 authentication required', 'sg-eventbrite-course-importer' ) );
		}

		// Rate limiting
		$this->enforce_rate_limit();

		// Use OAuth2 authenticated request
		return $this->oauth->make_authenticated_request( $endpoint, $args );
	}

	/**
	 * Enforce rate limiting for API requests.
	 */
	private function enforce_rate_limit() {
		$current_time = time();
		$current_minute = floor( $current_time / 60 );

		// Reset counter if we're in a new minute
		if ( $current_minute !== $this->rate_limit['minute_start'] ) {
			$this->rate_limit['request_count'] = 0;
			$this->rate_limit['minute_start'] = $current_minute;
		}

		// Check if we've exceeded the rate limit
		if ( $this->rate_limit['request_count'] >= $this->rate_limit['requests_per_minute'] ) {
			$sleep_time = 60 - ( $current_time % 60 );
			$this->logger->log( "Rate limit reached, sleeping for {$sleep_time} seconds", 'info' );
			sleep( $sleep_time );
			$this->rate_limit['request_count'] = 0;
			$this->rate_limit['minute_start'] = floor( time() / 60 );
		}

		// Add delay between requests to be respectful
		$time_since_last_request = $current_time - $this->rate_limit['last_request_time'];
		if ( $time_since_last_request < 1 ) {
			$sleep_time = 1 - $time_since_last_request;
			usleep( $sleep_time * 1000000 ); // Convert to microseconds
		}

		$this->rate_limit['request_count']++;
		$this->rate_limit['last_request_time'] = time();
	}

	/**
	 * Clear all cached data.
	 */
	public function clear_cache() {
		global $wpdb;

		// Clear all transients that start with our cache prefix
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_sg_eventbrite_%',
				'_transient_timeout_sg_eventbrite_%'
			)
		);

		$this->logger->log( 'Cleared all Eventbrite API cache', 'info' );
	}

	/**
	 * Get user's organizations.
	 *
	 * @return array|WP_Error Organization data or error.
	 */
	public function get_user_organizations() {
		$cache_key = 'sg_eventbrite_user_organizations';
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			$this->logger->log( "Retrieved user organizations from cache", 'info' );
			return $cached_data;
		}

		$url = add_query_arg( array(
			'expand' => 'organization',
		), self::API_BASE_URL . '/users/me/organizations/' );
		
		
		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Cache the response for 1 hour
		set_transient( $cache_key, $response, HOUR_IN_SECONDS );

		return $response;
	}

	/**
	 * Test API connection.
	 *
	 * @return array|WP_Error Test result.
	 */
	public function test_connection() {
		$url = self::API_BASE_URL . '/users/me/';
		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'success' => true,
			'message' => __( 'API connection successful', 'sg-eventbrite-course-importer' ),
			'user'    => $response,
		);
	}

	/**
	 * Get API usage statistics.
	 *
	 * @return array Usage statistics.
	 */
	public function get_usage_stats() {
		return array(
			'requests_this_minute' => $this->rate_limit['request_count'],
			'rate_limit'          => $this->rate_limit['requests_per_minute'],
			'last_request_time'   => $this->rate_limit['last_request_time'],
		);
	}
}