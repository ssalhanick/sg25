<?php
/**
 * Archive Queries Class.
 *
 * Handles complex event queries for archiving operations.
 * Provides methods for finding events based on various criteria.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */

namespace SG\HumanitixApiImporter\Archive;

use SG\HumanitixApiImporter\Admin\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Archive Queries Class.
 *
 * Handles complex event queries for archiving operations.
 * Provides methods for finding events based on various criteria.
 *
 * @package SG\HumanitixApiImporter\Archive
 * @since 1.0.0
 */
class ArchiveQueries {

	/**
	 * The logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * Get events older than specified age threshold.
	 *
	 * @since 1.0.0
	 * @param float $age_threshold Age threshold in years (supports decimals like 0.5 for 6 months).
	 * @param int $limit Maximum number of events to return.
	 * @return array Array of event IDs.
	 */
	public function get_events_older_than( $age_threshold, $limit = null ) {
		// Convert years to days for more precise decimal handling
		$days_ago = $age_threshold * 365.25; // Using 365.25 days per year for leap year accuracy
		$cutoff_date = date( 'Y-m-d', strtotime( "-{$days_ago} days" ) );

		$args = array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
			'posts_per_page' => $limit ?: -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_EventStartDate',
					'value'   => $cutoff_date,
					'compare' => '<',
					'type'    => 'DATE',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => '_EventStartDate',
			'order'          => 'ASC',
		);

		$query = new \WP_Query( $args );
		$events = $query->posts;

		$this->logger->log(
			'info',
			'Found events older than threshold',
			array(
				'age_threshold' => $age_threshold,
				'cutoff_date'   => $cutoff_date,
				'count'         => count( $events ),
				'limit'         => $limit,
			)
		);

		return $events;
	}

	/**
	 * Get events in a specific date range.
	 *
	 * @since 1.0.0
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date End date in Y-m-d format.
	 * @param int    $limit Maximum number of events to return.
	 * @return array Array of event IDs.
	 */
	public function get_events_in_date_range( $start_date, $end_date, $limit = null ) {
		$args = array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
			'posts_per_page' => $limit ?: -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_EventStartDate',
					'value'   => array( $start_date, $end_date ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => '_EventStartDate',
			'order'          => 'ASC',
		);

		$query = new \WP_Query( $args );
		$events = $query->posts;

		$this->logger->log(
			'info',
			'Found events in date range',
			array(
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'count'      => count( $events ),
				'limit'      => $limit,
			)
		);

		return $events;
	}

	/**
	 * Get events from a specific month (for monthly rolling archive).
	 *
	 * @since 1.0.0
	 * @param string $month_year Month and year in Y-m format (e.g., '2022-01').
	 * @param int    $limit Maximum number of events to return.
	 * @return array Array of event IDs.
	 */
	public function get_events_from_month( $month_year, $limit = null ) {
		$start_date = $month_year . '-01';
		$end_date   = date( 'Y-m-t', strtotime( $start_date ) );

		return $this->get_events_in_date_range( $start_date, $end_date, $limit );
	}

	/**
	 * Get archived events count.
	 *
	 * @since 1.0.0
	 * @return int Number of archived events.
	 */
	public function get_archived_events_count() {
		$args = array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'archived',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Get archive statistics.
	 *
	 * @since 1.0.0
	 * @return array Archive statistics.
	 */
	public function get_archive_statistics() {
		$stats = array(
			'total_archived' => 0,
			'total_events'   => 0,
			'archived_this_month' => 0,
			'events_to_archive' => 0,
		);

		// Count total archived events
		$archived_query = new \WP_Query( array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'archived',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		$stats['total_archived'] = $archived_query->found_posts;

		// Count total events
		$total_query = new \WP_Query( array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		$stats['total_events'] = $total_query->found_posts;

		// Count archived this month
		$month_start = date( 'Y-m-01' );
		$archived_this_month_query = new \WP_Query( array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'archived',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_event_archived_date',
					'value'   => $month_start,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		) );
		$stats['archived_this_month'] = $archived_this_month_query->found_posts;

		// Count events that should be archived (older than 2 years)
		$events_to_archive = $this->get_events_older_than( 2 );
		$stats['events_to_archive'] = count( $events_to_archive );

		return $stats;
	}

	/**
	 * Get events that were archived in a specific date range.
	 *
	 * @since 1.0.0
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date End date in Y-m-d format.
	 * @return array Array of event IDs.
	 */
	public function get_events_archived_in_range( $start_date, $end_date ) {
		$args = array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'archived',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_event_archived_date',
					'value'   => array( $start_date, $end_date ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => '_event_archived_date',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Get events that can be unarchived.
	 *
	 * @since 1.0.0
	 * @param int $limit Maximum number of events to return.
	 * @return array Array of event IDs.
	 */
	public function get_events_to_unarchive( $limit = null ) {
		$args = array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'archived',
			'posts_per_page' => $limit ?: -1,
			'fields'         => 'ids',
			'orderby'        => 'meta_value',
			'meta_key'       => '_event_archived_date',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $args );
		return $query->posts;
	}
} 