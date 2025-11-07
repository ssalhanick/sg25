<?php
/**
 * Single Course Template
 *
 * Template for displaying individual course posts.
 *
 * @package SG\EventbriteCourseImporter\Templates
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$start_date = get_post_meta( get_the_ID(), '_sg_course_start_date', true );
$start_time = get_post_meta( get_the_ID(), '_sg_course_start_time', true );
// Use dynamic price method
$price = \SG\EventbriteCourseImporter\EventbriteImporter::get_dynamic_price( get_the_ID() );
$location = get_post_meta( get_the_ID(), '_sg_course_location', true );
$instructor = get_post_meta( get_the_ID(), '_sg_course_instructor', true );
$class_length = get_post_meta( get_the_ID(), '_sg_course_class_length', true );
$course_length = get_post_meta( get_the_ID(), '_sg_course_course_length', true );
$day_of_week = get_post_meta( get_the_ID(), '_sg_course_day_of_week', true );
$drop_in_class = get_post_meta( get_the_ID(), '_sg_course_drop_in_class', true );
$eventbrite_url = get_post_meta( get_the_ID(), '_sg_course_eventbrite_url', true );
$early_bird_expires = get_post_meta( get_the_ID(), '_sg_course_early_bird_expires', true );
$early_bird_price_meta = get_post_meta( get_the_ID(), '_sg_course_early_bird_price', true );
$regular_price_meta = get_post_meta( get_the_ID(), '_sg_course_regular_price', true );

// Parse expiration date more reliably
$show_early_bird = false;
if ( ! empty( $early_bird_expires ) ) {
	try {
		// Try parsing as ISO 8601 with timezone
		$expires_dt = new \DateTime( $early_bird_expires, new \DateTimeZone( 'UTC' ) );
		$expires_timestamp = $expires_dt->getTimestamp();
		$show_early_bird = $expires_timestamp > time();
	} catch ( \Exception $e ) {
		// Fallback to strtotime
		$expires_timestamp = strtotime( $early_bird_expires );
		$show_early_bird = $expires_timestamp && $expires_timestamp > time();
	}
}

// Debug logging
error_log( "SG Eventbrite Template: Post ID " . get_the_ID() );
error_log( "SG Eventbrite Template: early_bird_expires: " . ( $early_bird_expires ? $early_bird_expires : 'not set' ) );
error_log( "SG Eventbrite Template: early_bird_price: " . ( $early_bird_price_meta ? $early_bird_price_meta : 'not set' ) );
error_log( "SG Eventbrite Template: regular_price: " . ( $regular_price_meta ? $regular_price_meta : 'not set' ) );
error_log( "SG Eventbrite Template: show_early_bird: " . ( $show_early_bird ? 'YES' : 'NO' ) );
error_log( "SG Eventbrite Template: Dynamic price returned: " . ( $price ? $price : 'empty' ) );



get_header(); ?>

<div class="sg-course-container">
	<?php
	// Add theme compatibility hooks
	do_action( 'sg_course_before_content' );
	?>
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'sg-course-single' ); ?>>
			
			<header class="sg-course-header">
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="sg-course-image">
						<?php the_post_thumbnail( 'large' ); ?>
					</div>
				<?php endif; ?>
				
				<div class="sg-course-meta">
					<h1 class="sg-course-title"><?php the_title(); ?></h1>
					<!-- Enroll Now Button -->
					<?php if ( $eventbrite_url ) : ?>
						<div class="sg-course-enroll">
							<a href="<?php echo esc_url( $eventbrite_url ); ?>" 
								class="sg-enroll-button" 
								target="_blank" 
								rel="noopener">
								<span class="enroll-text"><?php _e( 'Enroll Now', 'sg-eventbrite-course-importer' ); ?></span>
							</a>
						</div>
					<?php endif; ?>
					<?php
					// Get full datetime string for proper timezone handling
					$start_datetime_str = get_post_meta( get_the_ID(), '_sg_course_start_datetime', true );
					if ( empty( $start_datetime_str ) ) {
						// Fallback: combine date and time if full datetime not available
						$start_date = get_post_meta( get_the_ID(), '_sg_course_start_date', true );
						$start_time = get_post_meta( get_the_ID(), '_sg_course_start_time', true );
						if ( $start_date && $start_time ) {
							$start_datetime_str = $start_date . ' ' . $start_time . ':00';
						}
					}
					
					// Parse datetime - the stored time is already in the event's local timezone format
					// We parse it as-is (no timezone conversion needed - it's already the local event time)
					$start_datetime_display = null;
					if ( ! empty( $start_datetime_str ) ) {
						try {
							// Parse as-is (the datetime string is already in local format)
							// Use site timezone as the context (but value is already correct)
							$site_timezone = wp_timezone();
							$dt = new DateTime( $start_datetime_str, $site_timezone );
							$start_datetime_display = $dt;
						} catch ( Exception $e ) {
							error_log( 'SG Eventbrite: Error parsing start datetime: ' . $e->getMessage() );
						}
					}
					
					
					?>
					
					<!-- Main Course Information -->
					<div class="sg-course-main-info">
						<div class="sg-course-primary-details">
							<?php if ( $start_datetime_display ) : ?>
								<div class="sg-course-detail primary">
									<div class="detail-content">
										<strong><?php _e( 'Start Date & Time', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value">
											<?php echo esc_html( $start_datetime_display->format( get_option( 'date_format' ) ) ); ?>
											at <?php echo esc_html( $start_datetime_display->format( get_option( 'time_format' ) ) ); ?>
										</div>
									</div>
								</div>
							<?php elseif ( $start_date ) : ?>
								<div class="sg-course-detail primary">
									<div class="detail-content">
										<strong><?php _e( 'Start Date & Time', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value">
											<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ); ?>
											<?php if ( $start_time ) : ?>
												at <?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $start_date . ' ' . $start_time ) ) ); ?>
											<?php endif; ?>
										</div>
									</div>
								</div>
							<?php endif; ?>
							
							<?php if ( $day_of_week ) : ?>
								<div class="sg-course-detail primary">
									<div class="detail-content">
										<strong><?php _e( 'Day of Week', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value"><?php echo esc_html( $day_of_week ); ?></div>
									</div>
								</div>
							<?php endif; ?>
							
							<?php if ( $instructor ) : ?>
								<div class="sg-course-detail primary">
									<div class="detail-content">
										<strong><?php _e( 'Instructor', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value"><?php echo esc_html( $instructor ); ?></div>
									</div>
								</div>
							<?php endif; ?>
							
							<?php if ( $price ) : ?>
								<div class="sg-course-detail primary">
									<div class="detail-content">
										<strong><?php echo $show_early_bird ? __( 'Early Bird Price', 'sg-eventbrite-course-importer' ) : __( 'Price', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value">
											<?php if ( $show_early_bird ) : ?>
												<?php
												$regular_price = get_post_meta( get_the_ID(), '_sg_course_regular_price', true );
												if ( ! empty( $regular_price ) ) :
													$regular_price_formatted = '$' . number_format( floatval( $regular_price ), 2 );
												?>
													<span class="sg-price-regular" style="text-decoration: line-through; color: #999; margin-right: 8px;">
														<?php echo esc_html( $regular_price_formatted ); ?>
													</span>
												<?php endif; ?>
												<span class="sg-price-early-bird">
													<?php echo esc_html( $price ); ?>
												</span>
											<?php else : ?>
												<?php echo esc_html( $price ); ?>
											<?php endif; ?>
										</div>
									</div>
								</div>
							<?php endif; ?>
						</div>
						
						<!-- Course Description -->
						<div class="sg-course-description">
							<h3><?php _e( 'About This Event', 'sg-eventbrite-course-importer' ); ?></h3>
							<div class="description-content">
								<?php the_content(); ?>
							</div>
						</div>
						
						
					</div>
					
					<!-- Additional Course Information -->
					<div class="sg-course-additional-info">
						<h3><?php _e( 'Additional Information', 'sg-eventbrite-course-importer' ); ?></h3>
						<div class="sg-course-details-grid">
							<?php if ( $location ) : ?>
								<div class="sg-course-detail secondary">
									<div class="detail-icon">📍</div>
									<div class="detail-content">
										<strong><?php _e( 'Location', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value"><?php echo esc_html( $location ); ?></div>
									</div>
								</div>
							<?php endif; ?>
							
							<?php if ( $class_length ) : ?>
								<div class="sg-course-detail secondary">
									<div class="detail-icon">⏱️</div>
									<div class="detail-content">
										<strong><?php _e( 'Class Length', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value"><?php echo esc_html( $class_length ); ?> <?php _e( 'hours', 'sg-eventbrite-course-importer' ); ?></div>
									</div>
								</div>
							<?php endif; ?>
							
							<?php if ( $course_length ) : ?>
								<div class="sg-course-detail secondary">
									<div class="detail-icon">📚</div>
									<div class="detail-content">
										<strong><?php _e( 'Course Length', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value"><?php echo esc_html( $course_length ); ?> <?php _e( 'weeks', 'sg-eventbrite-course-importer' ); ?></div>
									</div>
								</div>
							<?php endif; ?>
							
							<?php if ( $drop_in_class === '1' ) : ?>
								<div class="sg-course-detail secondary">
									<div class="detail-icon">🎪</div>
									<div class="detail-content">
										<strong><?php _e( 'Class Type', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value">
											<span class="sg-drop-in-badge"><?php _e( 'Drop-in Class', 'sg-eventbrite-course-importer' ); ?></span>
										</div>
									</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</header>

			
		</article>
	<?php endwhile; ?>
	
	<?php
	// Add theme compatibility hooks
	do_action( 'sg_course_after_content' );
	?>
</div>

<?php get_footer(); ?>
