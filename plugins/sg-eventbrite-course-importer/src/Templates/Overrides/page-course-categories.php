<?php
/**
 * Template Name: Course Categories Landing Page
 *
 * Page template for displaying all course categories in a grid.
 *
 * @package SG\EventbriteCourseImporter\Templates
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Debug: Log that template is loading
error_log( 'SG Eventbrite: page-course-categories.php template is loading' );

// Load ArchiveHelpers functions
$archive_helpers_path = dirname( dirname( __FILE__ ) ) . '/ArchiveHelpers.php';
if ( file_exists( $archive_helpers_path ) ) {
	require_once $archive_helpers_path;
	error_log( 'SG Eventbrite: ArchiveHelpers loaded from: ' . $archive_helpers_path );
} else {
	// Fallback to constant-based path
	if ( defined( 'SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH' ) ) {
		$fallback_path = \SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH . '/src/Templates/ArchiveHelpers.php';
		if ( file_exists( $fallback_path ) ) {
			require_once $fallback_path;
			error_log( 'SG Eventbrite: ArchiveHelpers loaded from fallback: ' . $fallback_path );
		} else {
			error_log( 'SG Eventbrite: ERROR - ArchiveHelpers.php not found at: ' . $fallback_path );
		}
	} else {
		error_log( 'SG Eventbrite: ERROR - SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_PATH constant not defined' );
	}
}

// Enqueue archive styles
wp_enqueue_style(
	'sg-course-archives',
	\SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_URL . '/src/Templates/Assets/css/course-archives.css',
	array(),
	\SG_EVENTBRITE_COURSE_IMPORTER_PLUGIN_VERSION
);

// Get categories with open courses
error_log( 'SG Eventbrite: About to call get_categories_with_open_courses()' );
$categories = \SG\EventbriteCourseImporter\Templates\get_categories_with_open_courses();
error_log( 'SG Eventbrite: Found ' . count( $categories ) . ' categories with open courses' );

// Debug: Log all categories found
if ( ! empty( $categories ) ) {
	foreach ( $categories as $cat ) {
		error_log( 'SG Eventbrite: Category - ' . $cat->name . ' (ID: ' . $cat->term_id . ', Count: ' . $cat->course_count . ')' );
	}
} else {
	// Check if there are any categories at all
	$all_terms = get_terms( array(
		'taxonomy'   => 'sg_course_category',
		'hide_empty' => false,
	) );
	error_log( 'SG Eventbrite: Total categories found: ' . ( is_wp_error( $all_terms ) ? 'ERROR' : count( $all_terms ) ) );
	
	// Check if there are any published courses
	$all_courses = get_posts( array(
		'post_type'      => 'sg_course',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	error_log( 'SG Eventbrite: Total published courses found: ' . count( $all_courses ) );
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

		<?php if ( ! empty( $categories ) ) : ?>
			<div class="sg-categories-grid">
				<?php foreach ( $categories as $category ) : ?>
					<?php
					$category_link = get_term_link( $category );
					$category_description = term_description( $category->term_id, 'sg_course_category' );
					$category_image = get_term_meta( $category->term_id, 'term_image', true );
					
					// Check for Category Images plugin support
					if ( empty( $category_image ) && function_exists( 'z_taxonomy_image_url' ) ) {
						$category_image = z_taxonomy_image_url( $category->term_id );
					}
					?>
					<div class="sg-category-card">
						<a href="<?php echo esc_url( $category_link ); ?>" class="sg-category-link">
							<?php if ( $category_image ) : ?>
								<div class="sg-category-image">
									<img src="<?php echo esc_url( $category_image ); ?>" alt="<?php echo esc_attr( $category->name ); ?>" />
								</div>
							<?php endif; ?>
							<div class="sg-category-content">
								<h2 class="sg-category-name"><?php echo esc_html( $category->name ); ?></h2>
								<?php if ( $category_description ) : ?>
									<div class="sg-category-description">
										<?php echo wp_kses_post( wpautop( $category_description ) ); ?>
									</div>
								<?php endif; ?>
								<div class="sg-category-count">
									<?php
									printf(
										/* translators: %d: number of courses */
										_n( '%d course', '%d courses', $category->course_count, 'sg-eventbrite-course-importer' ),
										$category->course_count
									);
									?>
								</div>
							</div>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="sg-no-categories">
				<p><?php _e( 'No course categories found.', 'sg-eventbrite-course-importer' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();

