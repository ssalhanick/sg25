<?php
/**
 * Archive Helper Functions
 *
 * Helper functions for course archive templates.
 *
 * @package SG\EventbriteCourseImporter\Templates
 * @since 1.0.0
 */

namespace SG\EventbriteCourseImporter\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get categories that have at least one published course.
 *
 * @return array Array of term objects with course counts.
 */
function get_categories_with_open_courses() {
	$terms = get_terms( array(
		'taxonomy'   => 'sg_course_category',
		'hide_empty' => false,
	) );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$categories_with_courses = array();

	foreach ( $terms as $term ) {
		$count = get_open_courses_count( $term->term_id );
		if ( $count > 0 ) {
			$term->course_count = $count;
			$categories_with_courses[] = $term;
		}
	}

	return $categories_with_courses;
}

/**
 * Count published courses in a category.
 *
 * @param int $term_id Category term ID.
 * @return int Number of published courses.
 */
function get_open_courses_count( $term_id ) {
	$args = array(
		'post_type'      => 'sg_course',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'tax_query'      => array(
			array(
				'taxonomy' => 'sg_course_category',
				'field'    => 'term_id',
				'terms'    => $term_id,
			),
		),
		'fields'         => 'ids',
	);

	$query = new \WP_Query( $args );
	return $query->found_posts;
}

/**
 * Check if a course is published (open).
 *
 * @param int $post_id Post ID.
 * @return bool True if course is published.
 */
function is_course_open( $post_id ) {
	return 'publish' === get_post_status( $post_id );
}

/**
 * Check if a course is currently enrolling.
 *
 * @param int $post_id Post ID.
 * @return bool True if course is enrolling.
 */
function is_course_enrolling( $post_id ) {
	// First check if course is published
	if ( ! is_course_open( $post_id ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "SG Eventbrite: Course {$post_id} is not published, skipping enrollment check." );
		}
		return false;
	}

	// Get sales end date
	$sales_end_overall = get_post_meta( $post_id, '_sg_course_sales_end_overall', true );

	// Fallback to ticket expiration if overall sales end is not available
	if ( empty( $sales_end_overall ) ) {
		$sales_end_overall = get_post_meta( $post_id, '_sg_course_ticket_expiration', true );
	}

	// Fallback to event end date if still not available
	if ( empty( $sales_end_overall ) ) {
		$sales_end_overall = get_post_meta( $post_id, '_sg_course_end_datetime', true );
	}

	// If no end date is set, log it and return false (courses should have end dates)
	if ( empty( $sales_end_overall ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "SG Eventbrite: Course {$post_id} has no end date set. This may indicate incorrect data loading." );
		}
		return false;
	}

	// Parse the datetime string with comprehensive format handling
	try {
		$end_dt = null;
		$now = time();

		// Handle numeric timestamps
		if ( is_numeric( $sales_end_overall ) ) {
			$end_dt = new \DateTime( '@' . $sales_end_overall );
			$end_timestamp = $end_dt->getTimestamp();
		} else {
			// Handle string dates - try multiple formats
			$date_string = trim( $sales_end_overall );
			
			// Try ISO format with 'Z' suffix first (e.g., "2024-01-15T10:30:00Z")
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z?$/', $date_string ) ) {
				// Remove 'Z' if present and parse as UTC
				$date_string = rtrim( $date_string, 'Z' );
				$end_dt = \DateTime::createFromFormat( 'Y-m-d\TH:i:s', $date_string, new \DateTimeZone( 'UTC' ) );
			}
			// Try standard format (Y-m-d H:i:s)
			elseif ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date_string ) ) {
				$end_dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date_string, new \DateTimeZone( 'UTC' ) );
			}
			// Try date only format (Y-m-d)
			elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_string ) ) {
				$end_dt = \DateTime::createFromFormat( 'Y-m-d', $date_string, new \DateTimeZone( 'UTC' ) );
				// Set to end of day for date-only comparisons
				if ( $end_dt ) {
					$end_dt->setTime( 23, 59, 59 );
				}
			}
			// Fallback: try DateTime constructor with UTC timezone
			else {
				$end_dt = new \DateTime( $date_string, new \DateTimeZone( 'UTC' ) );
			}

			// If parsing failed, log and return false
			if ( ! $end_dt ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "SG Eventbrite: Failed to parse date '{$sales_end_overall}' for course {$post_id}." );
				}
				return false;
			}

			$end_timestamp = $end_dt->getTimestamp();
		}

		// Check if sales are still open (end date is in the future)
		$is_enrolling = $end_timestamp > $now;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$end_formatted = $end_dt->format( 'Y-m-d H:i:s' );
			$now_formatted = date( 'Y-m-d H:i:s', $now );
			error_log( "SG Eventbrite: Course {$post_id} - End: {$end_formatted}, Now: {$now_formatted}, Enrolling: " . ( $is_enrolling ? 'Yes' : 'No' ) );
		}

		return $is_enrolling;
	} catch ( \Exception $e ) {
		error_log( "SG Eventbrite: Error checking course enrollment status for course {$post_id}: " . $e->getMessage() );
		error_log( "SG Eventbrite: Date value was: '{$sales_end_overall}'" );
		// If we can't parse the date, return false (don't assume it's enrolling)
		return false;
	}
}

/**
 * Get all enrolling courses.
 *
 * @param array $args Additional query arguments.
 * @return array Array of course post objects.
 */
function get_enrolling_courses( $args = array() ) {
	$defaults = array(
		'post_type'      => 'sg_course',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	);

	$query_args = wp_parse_args( $args, $defaults );

	if ( empty( $query_args['meta_key'] ) ) {
		$query_args['meta_key'] = '_sg_course_level_number';
	}

	if ( empty( $query_args['orderby'] ) ) {
		$query_args['orderby'] = array(
			'meta_value_num' => 'ASC',
			'date'           => 'ASC',
		);
	}

	$all_courses = get_posts( $query_args );
	$enrolling_courses = array();
	$filtered_out = 0;
	$filtered_reasons = array();

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'SG Eventbrite: get_enrolling_courses() - Found ' . count( $all_courses ) . ' total published courses.' );
	}

	foreach ( $all_courses as $course ) {
		$is_enrolling = is_course_enrolling( $course->ID );
		
		if ( $is_enrolling ) {
			$enrolling_courses[] = $course;
		} else {
			$filtered_out++;
			// Get the reason why it was filtered
			$sales_end = get_post_meta( $course->ID, '_sg_course_sales_end_overall', true );
			if ( empty( $sales_end ) ) {
				$sales_end = get_post_meta( $course->ID, '_sg_course_ticket_expiration', true );
			}
			if ( empty( $sales_end ) ) {
				$sales_end = get_post_meta( $course->ID, '_sg_course_end_datetime', true );
			}
			
			if ( empty( $sales_end ) ) {
				$filtered_reasons['no_end_date'] = ( $filtered_reasons['no_end_date'] ?? 0 ) + 1;
			} else {
				$filtered_reasons['past_end_date'] = ( $filtered_reasons['past_end_date'] ?? 0 ) + 1;
			}
		}
	}

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'SG Eventbrite: get_enrolling_courses() - Returning ' . count( $enrolling_courses ) . ' enrolling courses.' );
		error_log( 'SG Eventbrite: get_enrolling_courses() - Filtered out ' . $filtered_out . ' courses.' );
		if ( ! empty( $filtered_reasons ) ) {
			error_log( 'SG Eventbrite: Filter reasons: ' . print_r( $filtered_reasons, true ) );
		}
	}

	usort(
		$enrolling_courses,
		function ( $a, $b ) {
			$level_a = intval( get_post_meta( $a->ID, '_sg_course_level_number', true ) );
			$level_b = intval( get_post_meta( $b->ID, '_sg_course_level_number', true ) );

			if ( empty( $level_a ) ) {
				$level_a = \SG\EventbriteCourseImporter\Utils\CourseLevelHelper::DEFAULT_LEVEL_NUMBER;
			}

			if ( empty( $level_b ) ) {
				$level_b = \SG\EventbriteCourseImporter\Utils\CourseLevelHelper::DEFAULT_LEVEL_NUMBER;
			}

			if ( $level_a === $level_b ) {
				$start_a = get_post_meta( $a->ID, '_sg_course_start_date', true );
				$start_b = get_post_meta( $b->ID, '_sg_course_start_date', true );
				return strcmp( $start_a, $start_b );
			}

			return ( $level_a < $level_b ) ? -1 : 1;
		}
	);

	return $enrolling_courses;
}

