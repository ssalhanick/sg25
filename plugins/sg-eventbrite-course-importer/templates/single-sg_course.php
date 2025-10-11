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
					
					<div class="sg-course-details">
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
						
						<?php if ( $start_date ) : ?>
							<div class="sg-course-detail">
								<strong><?php _e( 'Start Date:', 'sg-eventbrite-course-importer' ); ?></strong>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ); ?>
								<?php if ( $start_time ) : ?>
									at <?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $start_time ) ) ); ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						
						<?php if ( $day_of_week ) : ?>
							<div class="sg-course-detail">
								<strong><?php _e( 'Day:', 'sg-eventbrite-course-importer' ); ?></strong>
								<?php echo esc_html( $day_of_week ); ?>
							</div>
						<?php endif; ?>
						
						<?php if ( $price ) : ?>
							<div class="sg-course-detail">
								<strong><?php _e( 'Price:', 'sg-eventbrite-course-importer' ); ?></strong>
								<?php echo esc_html( $price ); ?>
							</div>
						<?php endif; ?>
						
						<?php if ( $location ) : ?>
							<div class="sg-course-detail">
								<strong><?php _e( 'Location:', 'sg-eventbrite-course-importer' ); ?></strong>
								<?php echo esc_html( $location ); ?>
							</div>
						<?php endif; ?>
						
						<?php if ( $instructor ) : ?>
							<div class="sg-course-detail">
								<strong><?php _e( 'Instructor:', 'sg-eventbrite-course-importer' ); ?></strong>
								<?php echo esc_html( $instructor ); ?>
							</div>
						<?php endif; ?>
						
						<?php if ( $class_length ) : ?>
							<div class="sg-course-detail">
								<strong><?php _e( 'Class Length:', 'sg-eventbrite-course-importer' ); ?></strong>
								<?php echo esc_html( $class_length ); ?> <?php _e( 'hours', 'sg-eventbrite-course-importer' ); ?>
							</div>
						<?php endif; ?>
						
						<?php if ( $course_length ) : ?>
							<div class="sg-course-detail">
								<strong><?php _e( 'Course Length:', 'sg-eventbrite-course-importer' ); ?></strong>
								<?php echo esc_html( $course_length ); ?>
							</div>
						<?php endif; ?>
						
						<?php if ( $drop_in_class === '1' ) : ?>
							<div class="sg-course-detail">
								<span class="sg-drop-in-badge"><?php _e( 'Drop-in Class', 'sg-eventbrite-course-importer' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</header>
			
			<div class="sg-course-content">
				<?php the_content(); ?>
			</div>
			
			<?php if ( $eventbrite_url ) : ?>
				<footer class="sg-course-footer">
					<div class="sg-course-actions">
						<a href="<?php echo esc_url( $eventbrite_url ); ?>" 
						   class="sg-eventbrite-link button button-primary" 
						   target="_blank" 
						   rel="noopener">
							<?php _e( 'Register on Eventbrite', 'sg-eventbrite-course-importer' ); ?>
						</a>
					</div>
				</footer>
			<?php endif; ?>
			
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

.sg-course-details {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 15px;
	margin-bottom: 20px;
}

.sg-course-detail {
	padding: 10px;
	background: #f8f9fa;
	border-radius: 4px;
	border-left: 4px solid #007cba;
}

.sg-course-detail strong {
	display: block;
	margin-bottom: 5px;
	color: #007cba;
	font-size: 0.9em;
	text-transform: uppercase;
	letter-spacing: 0.5px;
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

.sg-course-footer {
	padding: 20px 30px;
	background: #f8f9fa;
	border-top: 1px solid #ddd;
}

.sg-course-actions {
	text-align: center;
}

.sg-eventbrite-link {
	display: inline-block;
	padding: 12px 30px;
	background: #ff8000;
	color: white;
	text-decoration: none;
	border-radius: 5px;
	font-weight: bold;
	transition: background-color 0.3s ease;
}

.sg-eventbrite-link:hover {
	background: #e67300;
	color: white;
	text-decoration: none;
}

@media (max-width: 768px) {
	.sg-course-container {
		padding: 10px;
	}
	
	.sg-course-meta,
	.sg-course-content,
	.sg-course-footer {
		padding: 20px;
	}
	
	.sg-course-title {
		font-size: 2em;
	}
	
	.sg-course-details {
		grid-template-columns: 1fr;
	}
}
</style>

<?php get_footer(); ?>