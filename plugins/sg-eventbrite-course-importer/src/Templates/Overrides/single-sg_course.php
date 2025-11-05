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

get_header(); ?>

<style>
.sg-course-container {
	max-width: 1200px;
	margin: 0 auto;
	padding: 20px;
}
.sg-course-single {
	background: #fff;
	border-radius: 8px;
	overflow: hidden;
	box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.sg-course-header {
	display: flex;
	flex-direction: column;
	gap: 20px;
}
.sg-course-image {
	width: 100%;
	overflow: hidden;
}
.sg-course-image img {
	width: 100%;
	height: auto;
	display: block;
}
.sg-course-meta {
	padding: 20px;
}
.sg-course-title {
	font-size: 2em;
	margin: 0 0 20px 0;
	color: #333;
}
.sg-course-main-info {
	display: grid;
	grid-template-columns: 1fr;
	gap: 20px;
	margin-bottom: 30px;
}
.sg-course-primary-details {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 15px;
}
.sg-course-detail {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 15px;
	background: #f9f9f9;
	border-radius: 6px;
	border-left: 3px solid #0073aa;
}
.sg-course-detail.primary {
	background: #f0f6fc;
	border-left-color: #0073aa;
}
.sg-course-detail.secondary {
	background: #fafafa;
	border-left-color: #666;
}
.detail-icon {
	font-size: 1.5em;
	line-height: 1;
}
.detail-content {
	flex: 1;
}
.detail-content strong {
	display: block;
	margin-bottom: 5px;
	color: #333;
	font-size: 0.9em;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.detail-value {
	color: #666;
	font-size: 1.1em;
}
.sg-course-description {
	margin-top: 20px;
	padding-top: 20px;
	border-top: 2px solid #eee;
}
.sg-course-description h3 {
	margin: 0 0 15px 0;
	color: #333;
	font-size: 1.5em;
}
.description-content {
	line-height: 1.6;
	color: #555;
}
.sg-course-enroll {
	margin-top: 20px;
	padding-top: 20px;
	border-top: 2px solid #eee;
}
.sg-enroll-button {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	padding: 15px 30px;
	background: #0073aa;
	color: #fff !important;
	text-decoration: none;
	border-radius: 6px;
	font-weight: 600;
	transition: background 0.3s;
}
.sg-enroll-button:hover {
	background: #005a87;
}
.enroll-icon {
	font-size: 1.2em;
}
.enroll-text {
	font-size: 1.1em;
}
.enroll-subtext {
	font-size: 0.9em;
	opacity: 0.9;
}
.sg-course-additional-info {
	margin-top: 30px;
	padding-top: 30px;
	border-top: 2px solid #eee;
}
.sg-course-additional-info h3 {
	margin: 0 0 20px 0;
	color: #333;
	font-size: 1.3em;
}
.sg-course-details-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 15px;
}
.sg-drop-in-badge {
	display: inline-block;
	padding: 4px 12px;
	background: #ff6b6b;
	color: #fff;
	border-radius: 12px;
	font-size: 0.85em;
	font-weight: 600;
}
.sg-course-content {
	padding: 20px;
	border-top: 2px solid #eee;
	margin-top: 20px;
}
@media (max-width: 768px) {
	.sg-course-container {
		padding: 10px;
	}
	.sg-course-title {
		font-size: 1.5em;
	}
	.sg-course-primary-details,
	.sg-course-details-grid {
		grid-template-columns: 1fr;
	}
	.sg-enroll-button {
		width: 100%;
		justify-content: center;
	}
}
</style>

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
					$show_early_bird = ! empty( $early_bird_expires ) && strtotime( $early_bird_expires ) > time();
					?>
					
					<!-- Main Course Information -->
					<div class="sg-course-main-info">
						<div class="sg-course-primary-details">
							<?php if ( $start_datetime_display ) : ?>
								<div class="sg-course-detail primary">
									<div class="detail-icon">📅</div>
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
									<div class="detail-icon">📅</div>
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
									<div class="detail-icon">📆</div>
									<div class="detail-content">
										<strong><?php _e( 'Day of Week', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value"><?php echo esc_html( $day_of_week ); ?></div>
									</div>
								</div>
							<?php endif; ?>
							
							<?php if ( $instructor ) : ?>
								<div class="sg-course-detail primary">
									<div class="detail-icon">👨‍🏫</div>
									<div class="detail-content">
										<strong><?php _e( 'Instructor', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value"><?php echo esc_html( $instructor ); ?></div>
									</div>
								</div>
							<?php endif; ?>
							
							<?php if ( $price ) : ?>
								<div class="sg-course-detail primary">
									<div class="detail-icon">💰</div>
									<div class="detail-content">
										<strong><?php _e( 'Price', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value">
											<?php echo esc_html( $price ); ?>
											<?php if ( $show_early_bird ) : ?>
												<span class="sg-early-bird-badge" style="display: inline-block; margin-left: 8px; padding: 2px 8px; background: #ff6b6b; color: #fff; border-radius: 12px; font-size: 0.75em; font-weight: 600;">
													<?php _e( 'Early Bird', 'sg-eventbrite-course-importer' ); ?>
												</span>
											<?php endif; ?>
										</div>
										<?php if ( $show_early_bird ) : ?>
											<?php
											$regular_price = get_post_meta( get_the_ID(), '_sg_course_regular_price', true );
											if ( ! empty( $regular_price ) ) :
												$currency = get_post_meta( get_the_ID(), '_sg_course_price', true );
												$currency_code = 'USD';
												if ( preg_match( '/^([A-Z]{3})\s/', $currency, $matches ) ) {
													$currency_code = $matches[1];
												}
											?>
												<p style="margin-top: 5px; font-size: 0.85em; color: #666;">
													<?php printf( __( 'Regular price: %s', 'sg-eventbrite-course-importer' ), esc_html( $currency_code . ' ' . number_format( floatval( $regular_price ), 2 ) ) ); ?>
												</p>
											<?php endif; ?>
										<?php endif; ?>
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
						
						<!-- Enroll Now Button -->
						<?php if ( $eventbrite_url ) : ?>
							<div class="sg-course-enroll">
								<a href="<?php echo esc_url( $eventbrite_url ); ?>" 
								   class="sg-enroll-button" 
								   target="_blank" 
								   rel="noopener">
									<span class="enroll-icon">🎯</span>
									<span class="enroll-text"><?php _e( 'Enroll Now', 'sg-eventbrite-course-importer' ); ?></span>
									<span class="enroll-subtext"><?php _e( 'Register on Eventbrite', 'sg-eventbrite-course-importer' ); ?></span>
								</a>
							</div>
						<?php endif; ?>
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
			
			<div class="sg-course-content">
				<?php the_content(); ?>
			</div>
			
		</article>
	<?php endwhile; ?>
	
	<?php
	// Add theme compatibility hooks
	do_action( 'sg_course_after_content' );
	?>
</div>

<?php get_footer(); ?>
