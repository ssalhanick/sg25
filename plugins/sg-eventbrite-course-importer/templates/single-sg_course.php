<?php
/**
 * Single Course Template
 *
 * Template for displaying individual course posts.
 *
 * @package SG\EventbriteCourseImporter
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header(); ?>

<div class="sg-course-container">
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
					$start_date = get_post_meta( get_the_ID(), '_sg_course_start_date', true );
					$start_time = get_post_meta( get_the_ID(), '_sg_course_start_time', true );
					$price = get_post_meta( get_the_ID(), '_sg_course_price', true );
					$location = get_post_meta( get_the_ID(), '_sg_course_location', true );
					$instructor = get_post_meta( get_the_ID(), '_sg_course_instructor', true );
					$class_length = get_post_meta( get_the_ID(), '_sg_course_class_length', true );
					$course_length = get_post_meta( get_the_ID(), '_sg_course_course_length', true );
					$day_of_week = get_post_meta( get_the_ID(), '_sg_course_day_of_week', true );
					$drop_in_class = get_post_meta( get_the_ID(), '_sg_course_drop_in_class', true );
					$eventbrite_url = get_post_meta( get_the_ID(), '_sg_course_eventbrite_url', true );
					?>
					
					<!-- Main Course Information -->
					<div class="sg-course-main-info">
						<div class="sg-course-primary-details">
							<?php if ( $start_date ) : ?>
								<div class="sg-course-detail primary">
									<div class="detail-icon">📅</div>
									<div class="detail-content">
										<strong><?php _e( 'Start Date & Time', 'sg-eventbrite-course-importer' ); ?></strong>
										<div class="detail-value">
											<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ); ?>
											<?php if ( $start_time ) : ?>
												at <?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $start_time ) ) ); ?>
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
										<div class="detail-value"><?php echo esc_html( $price ); ?></div>
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
</div>

<style>
.sg-course-container {
	max-width: 800px;
	margin: 0 auto;
	padding: 20px;
}

.sg-course-single {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	overflow: hidden;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.sg-course-header {
	position: relative;
}

.sg-course-image img {
	width: 100%;
	height: 300px;
	object-fit: cover;
}

.sg-course-meta {
	padding: 30px;
}

.sg-course-title {
	margin: 0 0 20px 0;
	font-size: 2.5em;
	color: #333;
}

/* Main Course Information */
.sg-course-main-info {
	margin-bottom: 30px;
}

.sg-course-primary-details {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.sg-course-detail {
	display: flex;
	align-items: flex-start;
	padding: 20px;
	background: #fff;
	border: 1px solid #e1e5e9;
	border-radius: 8px;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
	transition: all 0.3s ease;
}

.sg-course-detail:hover {
	box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
	transform: translateY(-2px);
}

.sg-course-detail.primary {
	border-left: 4px solid #007cba;
}

.sg-course-detail.secondary {
	border-left: 4px solid #28a745;
}

.detail-icon {
	font-size: 1.5em;
	margin-right: 15px;
	margin-top: 2px;
}

.detail-content {
	flex: 1;
}

.detail-content strong {
	display: block;
	margin-bottom: 8px;
	color: #333;
	font-size: 0.9em;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	font-weight: 600;
}

.detail-value {
	color: #555;
	font-size: 1.1em;
	font-weight: 500;
}

/* Course Description */
.sg-course-description {
	background: #f8f9fa;
	padding: 25px;
	border-radius: 8px;
	margin-bottom: 30px;
	border-left: 4px solid #6f42c1;
}

.sg-course-description h3 {
	margin: 0 0 15px 0;
	color: #6f42c1;
	font-size: 1.3em;
}

.description-content {
	font-size: 1.1em;
	line-height: 1.6;
	color: #555;
}

/* Enroll Button */
.sg-course-enroll {
	text-align: center;
	margin-bottom: 30px;
}

.sg-enroll-button {
	display: inline-flex;
	flex-direction: column;
	align-items: center;
	padding: 20px 40px;
	background: linear-gradient(135deg, #ff8000, #ff6b00);
	color: white;
	text-decoration: none;
	border-radius: 12px;
	box-shadow: 0 4px 15px rgba(255, 128, 0, 0.3);
	transition: all 0.3s ease;
	font-weight: bold;
	min-width: 200px;
}

.sg-enroll-button:hover {
	background: linear-gradient(135deg, #e67300, #e55a00);
	transform: translateY(-2px);
	box-shadow: 0 6px 20px rgba(255, 128, 0, 0.4);
	color: white;
	text-decoration: none;
}

.enroll-icon {
	font-size: 2em;
	margin-bottom: 8px;
}

.enroll-text {
	font-size: 1.2em;
	margin-bottom: 4px;
}

.enroll-subtext {
	font-size: 0.9em;
	opacity: 0.9;
	font-weight: normal;
}

/* Additional Information */
.sg-course-additional-info {
	background: #f8f9fa;
	padding: 25px;
	border-radius: 8px;
	border-left: 4px solid #17a2b8;
}

.sg-course-additional-info h3 {
	margin: 0 0 20px 0;
	color: #17a2b8;
	font-size: 1.3em;
}

.sg-course-details-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 15px;
}

.sg-drop-in-badge {
	display: inline-block;
	padding: 5px 10px;
	background: #28a745;
	color: white;
	border-radius: 20px;
	font-size: 0.8em;
	font-weight: bold;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.sg-course-content {
	padding: 0 30px 30px;
	font-size: 1.1em;
	line-height: 1.6;
}

.sg-course-content h2,
.sg-course-content h3,
.sg-course-content h4 {
	color: #333;
	margin-top: 30px;
	margin-bottom: 15px;
}

.sg-course-content p {
	margin-bottom: 15px;
}

/* Responsive Design */
@media (max-width: 768px) {
	.sg-course-primary-details {
		grid-template-columns: 1fr;
		gap: 15px;
	}
	
	.sg-course-details-grid {
		grid-template-columns: 1fr;
	}
	
	.sg-course-detail {
		padding: 15px;
	}
	
	.sg-enroll-button {
		padding: 15px 30px;
		min-width: 180px;
	}
	
	.enroll-text {
		font-size: 1.1em;
	}
	
	.enroll-subtext {
		font-size: 0.8em;
	}
}

</style>

<?php get_footer(); ?>