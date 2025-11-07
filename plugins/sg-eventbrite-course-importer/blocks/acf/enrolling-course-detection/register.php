<?php
/**
 * Register Enrolling Course Detection Block
 *
 * @package SG\EventbriteCourseImporter\Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register the enrolling course detection block
 */
function sg_eventbrite_register_enrolling_course_detection_block() {
	if ( ! function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	acf_register_block_type( array(
		'name'            => 'enrolling-course-detection',
		'title'           => __( 'Enrolling Course Detection', 'sg-eventbrite-course-importer' ),
		'description'     => __( 'Displays enrolling course information and computes overall sales window from ticket classes.', 'sg-eventbrite-course-importer' ),
		'render_template' => plugin_dir_path( __FILE__ ) . 'content.php',
		'category'        => 'sg-eventbrite-course-importer-custom',
		'icon'            => 'calendar-alt',
		'keywords'        => array( 'course', 'enrolling', 'eventbrite', 'sales' ),
		'supports'        => array(
			'align'  => array( 'left', 'right', 'center', 'wide', 'full' ),
			'anchor' => true,
		),
		'enqueue_style'   => false,
		'enqueue_script'  => false,
	) );
}

add_action( 'acf/init', 'sg_eventbrite_register_enrolling_course_detection_block' );

