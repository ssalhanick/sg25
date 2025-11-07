<?php
/**
 * Enrolling Course Detection Block Template
 *
 * @package SG\EventbriteCourseImporter\Blocks
 *
 * @var array $block The block settings and attributes.
 * @var string $content The block inner HTML (empty).
 * @var bool $is_preview True during backend preview render.
 * @var int $post_id The post ID the block is rendering content against.
 * @var array $context The context provided to the block by the post or its parent block.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Get block fields
$display_mode = get_field( 'display_mode' ) ?: 'count';
$show_sales_window = get_field( 'show_sales_window' ) ?: false;
$empty_message = get_field( 'empty_message' ) ?: __( 'No courses are currently enrolling.', 'sg-eventbrite-course-importer' );

// Get all courses
$args = array(
	'post_type'      => 'sg_course',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
);

$all_courses = get_posts( $args );
$enrolling_courses = array();
$now = time();

foreach ( $all_courses as $course ) {
	$sales_end_overall = get_post_meta( $course->ID, '_sg_course_sales_end_overall', true );
	
	// Fallback to ticket expiration if overall sales end is not available
	if ( empty( $sales_end_overall ) ) {
		$sales_end_overall = get_post_meta( $course->ID, '_sg_course_ticket_expiration', true );
	}
	
	// Fallback to event end date if still not available
	if ( empty( $sales_end_overall ) ) {
		$sales_end_overall = get_post_meta( $course->ID, '_sg_course_end_datetime', true );
	}
	
	if ( empty( $sales_end_overall ) ) {
		continue;
	}
	
	// Parse the datetime string
	try {
		$end_dt = null;
		if ( is_numeric( $sales_end_overall ) ) {
			$end_dt = new \DateTime( '@' . $sales_end_overall );
		} else {
			$end_dt = new \DateTime( $sales_end_overall, new \DateTimeZone( 'UTC' ) );
		}
		
		$end_timestamp = $end_dt->getTimestamp();
		
		// Check if sales are still open (end date is in the future)
		if ( $end_timestamp > $now ) {
			$enrolling_courses[] = $course;
		}
	} catch ( \Exception $e ) {
		error_log( 'SG Eventbrite: Error checking course enrollment status: ' . $e->getMessage() );
	}
}

$enrolling_count = count( $enrolling_courses );

// Block wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => 'sg-enrolling-course-detection',
) );
?>

<div <?php echo $wrapper_attributes; ?>>
	<?php if ( 'count' === $display_mode ) : ?>
		<div class="sg-enrolling-count">
			<?php if ( $enrolling_count > 0 ) : ?>
				<p class="sg-enrolling-message">
					<?php
					printf(
						_n(
							'%d course is currently enrolling.',
							'%d courses are currently enrolling.',
							$enrolling_count,
							'sg-eventbrite-course-importer'
						),
						$enrolling_count
					);
					?>
				</p>
			<?php else : ?>
				<p class="sg-enrolling-empty">
					<?php echo esc_html( $empty_message ); ?>
				</p>
			<?php endif; ?>
		</div>
	<?php elseif ( 'list' === $display_mode && $enrolling_count > 0 ) : ?>
		<div class="sg-enrolling-list">
			<h3><?php _e( 'Currently Enrolling Courses', 'sg-eventbrite-course-importer' ); ?></h3>
			<ul>
				<?php foreach ( $enrolling_courses as $course ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>">
							<?php echo esc_html( get_the_title( $course->ID ) ); ?>
						</a>
						<?php if ( $show_sales_window ) : ?>
							<?php
							$sales_start = get_post_meta( $course->ID, '_sg_course_sales_start_overall', true );
							$sales_end = get_post_meta( $course->ID, '_sg_course_sales_end_overall', true );
							?>
							<?php if ( $sales_start || $sales_end ) : ?>
								<span class="sg-sales-window">
									<?php
									if ( $sales_start ) {
										try {
											$start_dt = new \DateTime( $sales_start, new \DateTimeZone( 'UTC' ) );
											$start_dt->setTimezone( wp_timezone() );
											echo esc_html( $start_dt->format( get_option( 'date_format' ) ) );
										} catch ( \Exception $e ) {
											echo esc_html( $sales_start );
										}
									}
									if ( $sales_start && $sales_end ) {
										echo ' - ';
									}
									if ( $sales_end ) {
										try {
											$end_dt = new \DateTime( $sales_end, new \DateTimeZone( 'UTC' ) );
											$end_dt->setTimezone( wp_timezone() );
											echo esc_html( $end_dt->format( get_option( 'date_format' ) ) );
										} catch ( \Exception $e ) {
											echo esc_html( $sales_end );
										}
									}
									?>
								</span>
							<?php endif; ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php else : ?>
		<div class="sg-enrolling-empty">
			<?php echo esc_html( $empty_message ); ?>
		</div>
	<?php endif; ?>
</div>

