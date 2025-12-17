<?php
/**
 * Template Name: Now Enrolling Courses
 *
 * Page template for displaying all currently enrolling courses in a grid.
 *
 * @package SG\EventbriteCourseImporter\Templates
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Load ArchiveHelpers functions
$archive_helpers_path = dirname( dirname( __FILE__ ) ) . '/ArchiveHelpers.php';
if ( file_exists( $archive_helpers_path ) ) {
	require_once $archive_helpers_path;
} else {
	// Fallback to constant-based path
	if ( defined( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH' ) ) {
		require_once \SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/src/Templates/ArchiveHelpers.php';
	}
}

// Enqueue archive styles
wp_enqueue_style(
	'sg-course-archives',
	\SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL . '/src/Templates/Assets/css/course-archives.css',
	array(),
	\SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_VERSION
);

// Get all enrolling courses
$enrolling_courses = \SG\EventbriteCourseImporter\Templates\get_enrolling_courses();

// Debug: Log the count and additional info
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	error_log( 'SG Eventbrite: Template page-now-enrolling.php - Found ' . count( $enrolling_courses ) . ' enrolling courses' );
	
	// Log sample course data for debugging
	if ( ! empty( $enrolling_courses ) ) {
		$sample_course = $enrolling_courses[0];
		$sales_end = get_post_meta( $sample_course->ID, '_sg_course_sales_end_overall', true );
		if ( empty( $sales_end ) ) {
			$sales_end = get_post_meta( $sample_course->ID, '_sg_course_ticket_expiration', true );
		}
		if ( empty( $sales_end ) ) {
			$sales_end = get_post_meta( $sample_course->ID, '_sg_course_end_datetime', true );
		}
		error_log( 'SG Eventbrite: Sample course ID ' . $sample_course->ID . ' - End date value: ' . ( $sales_end ? $sales_end : 'NOT SET' ) );
	} else {
		// Check if there are any published courses at all
		$all_courses = get_posts( array(
			'post_type'      => 'sg_course',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'fields'         => 'ids',
		) );
		error_log( 'SG Eventbrite: No enrolling courses found. Total published courses: ' . count( $all_courses ) );
		if ( ! empty( $all_courses ) ) {
			$sample_id = $all_courses[0];
			$sales_end = get_post_meta( $sample_id, '_sg_course_sales_end_overall', true );
			if ( empty( $sales_end ) ) {
				$sales_end = get_post_meta( $sample_id, '_sg_course_ticket_expiration', true );
			}
			if ( empty( $sales_end ) ) {
				$sales_end = get_post_meta( $sample_id, '_sg_course_end_datetime', true );
			}
			error_log( 'SG Eventbrite: Sample published course ID ' . $sample_id . ' - End date value: ' . ( $sales_end ? $sales_end : 'NOT SET' ) );
		}
	}
}

get_header();
?>

<div class="sg-course-archives-container">
	<div class="sg-course-archives-content">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<header class="sg-archive-header">
					<h1 class="sg-archive-title"><?php the_title(); ?></h1>
					<?php if ( get_the_content() ) : ?>
						<div class="sg-archive-description">
							<?php the_content(); ?>
						</div>
					<?php endif; ?>
				</header>
			<?php endwhile; ?>
		<?php endif; ?>

		<?php if ( ! empty( $enrolling_courses ) ) : ?>
			<div class="sg-courses-grid">
				<?php foreach ( $enrolling_courses as $course ) : ?>
					<?php
					setup_postdata( $course );
					$price = \SG\EventbriteCourseImporter\EventbriteImporter::get_dynamic_price( $course->ID );
					$start_date = get_post_meta( $course->ID, '_sg_course_start_date', true );

					$level_label = get_post_meta( $course->ID, '_sg_course_level_label', true );
					$level_number = get_post_meta( $course->ID, '_sg_course_level_number', true );
					if ( empty( $level_label ) && ! empty( $level_number ) ) {
						$level_label = \SG\EventbriteCourseImporter\Utils\CourseLevelHelper::format_level_label( intval( $level_number ) );
					}

					// Get instructor from ACF if available, otherwise from post meta
					if ( function_exists( 'get_field' ) ) {
						$instructor = get_field( 'instructor', $course->ID );
					} else {
						$instructor = '';
					}
					if ( empty( $instructor ) ) {
						$instructor = get_post_meta( $course->ID, '_sg_course_instructor', true );
					}
					?>
					<article class="sg-course-card">
						<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>" class="sg-course-link">
							<?php if ( has_post_thumbnail( $course->ID ) ) : ?>
								<div class="sg-course-thumbnail">
									<?php echo get_the_post_thumbnail( $course->ID, 'medium' ); ?>
								</div>
							<?php endif; ?>
							<div class="sg-course-card-content">
								<?php if ( $level_label ) : ?>
									<div class="sg-course-card-level">
										<?php echo esc_html( $level_label ); ?>
									</div>
								<?php endif; ?>
								<h2 class="sg-course-card-title"><?php echo esc_html( get_the_title( $course->ID ) ); ?></h2>
								<?php if ( $price ) : ?>
									<div class="sg-course-card-price">
										<?php echo esc_html( $price ); ?>
									</div>
								<?php endif; ?>
								<?php if ( $start_date ) : ?>
									<div class="sg-course-card-date">
										<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) ); ?>
									</div>
								<?php endif; ?>
								<?php if ( $instructor ) : ?>
									<div class="sg-course-card-instructor">
										<?php echo esc_html( $instructor ); ?>
									</div>
								<?php endif; ?>
							</div>
						</a>
					</article>
				<?php endforeach; ?>
				<?php wp_reset_postdata(); ?>
			</div>
		<?php else : ?>
			<div class="sg-no-courses">
				<?php
				$contact_email = get_option( 'admin_email' );
				$message = sprintf(
					/* translators: %s: Contact email address */
					__( 'No courses are currently enrolling. Reach out to %s to find out when the next session begins enrolling.', 'sg-eventbrite-course-importer' ),
					'<a href="mailto:' . esc_attr( $contact_email ) . '">' . esc_html( $contact_email ) . '</a>'
				);
				?>
				<p><?php echo $message; ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();

